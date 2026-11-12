<?php
/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * Modulo de Autenticacion y Autorizacion
 *
 * Roles: Administrador, Analista, Facilitador, Participante
 * Implementa sesiones seguras, bcrypt, control de intentos, RBAC
 */

declare(strict_types=1);

if (!defined('UPTEC_ACCESS')) {
    define('UPTEC_ACCESS', true);
}

require_once __DIR__ . '/../db/config.php';

/**
 * Clase Auth - Gestion completa de autenticacion
 */
class Auth
{
    private const SESSION_USER = '_uptec_user';
    private const SESSION_LAST_ACTIVITY = '_uptec_last_activity';
    private const INACTIVITY_LIMIT = 1800; // 30 minutos
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 180; // 3 minutos
    private static bool $initialized = false;

    private static array $roleHierarchy = [
        'Administrador' => 4,
        'Analista' => 3,
        'Facilitador' => 2,
        'Participante' => 1
    ];

    /**
     * Inicializa el sistema de autenticacion
     */
    public static function init(): void
    {
        if (self::$initialized && session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', (string)self::INACTIVITY_LIMIT);

            session_start();
        }

        self::$initialized = true;
        self::checkInactivity();
        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    }

    /**
     * Autentica un usuario con correo y contrasena
     */
    public static function login(string $email, string $password)
    {
        self::init();

        if (self::isLockedOut()) {
            return ['error' => 'Cuenta temporalmente bloqueada por intentos fallidos. Espere 3 minutos.'];
        }

        try {
            $db = getDB();

            $stmt = $db->prepare("
                SELECT id, cedula, nombre, apellidos, correo, telefono, password, rol, activo
                FROM usuarios
                WHERE correo = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                self::recordFailedAttempt();
                return false;
            }

            if (empty($user['password'])) {
                return ['error' => 'Este tipo de usuario debe registrarse primero para crear una contrasena'];
            }

            if (!password_verify($password, $user['password'])) {
                self::recordFailedAttempt();
                return false;
            }

            // Validar si el usuario está inactivo o pendiente de verificación
            if ((int)$user['activo'] !== 1) {
                if ($user['rol'] === 'Facilitador') {
                    return ['error' => 'Su cuenta de facilitador está en espera de ser verificada por el analista o administrador. Por favor espere a ser verificado para poder acceder.'];
                }
                return ['error' => 'Su cuenta se encuentra inactiva. Comuníquese con la administración del sistema.'];
            }

            if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                self::updatePassword($user['id'], $password);
            }

            self::clearFailedAttempts();
            self::createSession($user);
            self::updateLastAccess($user['id']);
            self::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'LOGIN', 'usuarios', $user['id']);

            unset($user['password']);
            return $user;

        } catch (PDOException $e) {
            error_log("[UPTEC] Error en login: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cierra la sesion del usuario actual
     */
        public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[self::SESSION_USER]['id'])) {
            $user = $_SESSION[self::SESSION_USER];
            self::logActivity((int)$user['id'], trim(($user['nombre'] ?? '') . ' ' . ($user['apellidos'] ?? '')), 'LOGOUT', 'usuarios', (int)$user['id']);
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$initialized = false;
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public static function check(): bool
    {
        self::init();

        return isset($_SESSION[self::SESSION_USER]) &&
               isset($_SESSION[self::SESSION_USER]['id']) &&
               !empty($_SESSION[self::SESSION_USER]['id']);
    }

    /** Obtiene el ID del usuario actual */
    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_USER]['id'] ?? null;
    }

    /** Obtiene los datos del usuario actual */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_USER] ?? null;
    }

    /** Obtiene el rol del usuario actual */
    public static function role(): ?string
    {
        return $_SESSION[self::SESSION_USER]['rol'] ?? null;
    }

    /**
     * Verifica si el usuario tiene un rol especifico o superior
     */
    public static function hasRole($roles): bool
    {
        if (!self::check()) {
            return false;
        }

        $userRole = self::role();
        $userLevel = self::$roleHierarchy[$userRole] ?? 0;

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $requiredLevel = self::$roleHierarchy[$role] ?? 0;
                if ($userLevel >= $requiredLevel) {
                    return true;
                }
            }
            return false;
        }

        $requiredLevel = self::$roleHierarchy[$roles] ?? 0;
        return $userLevel >= $requiredLevel;
    }

    /** Verifica si es administrador */
    public static function isAdmin(): bool
    {
        return self::hasRole('Administrador');
    }

    /** Verifica si es analista o superior */
    public static function isAnalystOrAbove(): bool
    {
        return self::hasRole(['Analista', 'Administrador']);
    }

    /** Verifica si es facilitador o superior */
    public static function isTeacherOrAbove(): bool
    {
        return self::hasRole(['Facilitador', 'Analista', 'Administrador']);
    }

    /**
     * Requiere autenticacion
     */
    public static function requireAuth(string $redirect = '/login.html'): void
    {
        if (!self::check()) {
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Cambia la contrasena del usuario
     */
    public static function changePassword(int $userId, string $newPassword): bool
    {
        try {
            $db = getDB();
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);

            self::logActivity($userId, '', 'PASSWORD_CHANGE', 'usuarios', $userId);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("[UPTEC] Error cambiando password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica la contrasena actual
     */
    public static function verifyPassword(int $userId, string $password): bool
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) return false;

            return password_verify($password, $user['password']);

        } catch (PDOException $e) {
            error_log("[UPTEC] Error verificando password: " . $e->getMessage());
            return false;
        }
    }

    /** Crea la sesion del usuario */
    private static function createSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        } else {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_USER] = [
            'id' => $user['id'],
            'cedula' => $user['cedula'],
            'nombre' => $user['nombre'],
            'apellidos' => $user['apellidos'],
            'correo' => $user['correo'],
            'telefono' => $user['telefono'],
            'rol' => $user['rol']
        ];

        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    }

        /** Verifica inactividad de sesion */
    private static function checkInactivity(): void
    {
        if (isset($_SESSION[self::SESSION_LAST_ACTIVITY])) {
            $inactive = time() - (int)$_SESSION[self::SESSION_LAST_ACTIVITY];

            if ($inactive > self::INACTIVITY_LIMIT) {
                unset($_SESSION[self::SESSION_LAST_ACTIVITY]);
                self::logout();

                $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                      || (!empty($_SERVER['REQUEST_URI']) && (str_contains($_SERVER['REQUEST_URI'], '/backend/api/') || str_contains($_SERVER['REQUEST_URI'], 'api.php')));

                if ($isApi) {
                    $endpoint = $_GET['endpoint'] ?? '';
                    if ($endpoint === 'login') {
                        return;
                    }
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'Sesion expirada por inactividad', 'expired' => true]);
                    exit;
                }

                header('Location: /login.html?expired=1');
                exit;
            }
        }
    }

    /** Verifica si la cuenta esta bloqueada */
    private static function isLockedOut(): bool
    {
        $key = '_uptec_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!isset($_SESSION[$key])) {
            return false;
        }

        $attempts = $_SESSION[$key];

        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            if (time() - $attempts['time'] < self::LOCKOUT_TIME) {
                return true;
            }
            unset($_SESSION[$key]);
        }

        return false;
    }

    /** Registra intento fallido de login */
    private static function recordFailedAttempt(): void
    {
        $key = '_uptec_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }

        $_SESSION[$key]['count']++;
        $_SESSION[$key]['time'] = time();
    }

    /** Limpia intentos fallidos */
    private static function clearFailedAttempts(): void
    {
        $key = '_uptec_login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        unset($_SESSION[$key]);
    }

    /** Actualiza contrasena en BD */
    private static function updatePassword(int $userId, string $password): void
    {
        try {
            $db = getDB();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
        } catch (PDOException $e) {
            error_log("[UPTEC] Error actualizando password hash: " . $e->getMessage());
        }
    }

    /** Actualiza fecha de ultimo acceso */
    private static function updateLastAccess(int $userId): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("[UPTEC] Error actualizando ultimo acceso: " . $e->getMessage());
        }
    }

    /**
     * Registra actividad en logs de auditoria (mejorado)
     */
    public static function logActivity(?int $userId, string $userName, string $action, string $table, ?int $recordId, ?array $datosAnteriores = null, ?array $datosNuevos = null): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO logs_actividad (usuario_id, usuario_nombre, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $userName,
                $action,
                $table,
                $recordId,
                $datosAnteriores ? json_encode($datosAnteriores) : null,
                $datosNuevos ? json_encode($datosNuevos) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("[UPTEC] Error en log de actividad: " . $e->getMessage());
        }
    }
}

/** Helpers globales */
function auth_check(): bool { return Auth::check(); }
function auth_user(): ?array { return Auth::user(); }
function auth_role(): ?string { return Auth::role(); }
function auth_is_admin(): bool { return Auth::isAdmin(); }
function auth_require(string $redirect = '/login.html'): void { Auth::requireAuth($redirect); }