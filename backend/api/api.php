<?php
/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * API Router Principal
 *
 * Universidad Politecnica Territorial de Caracas Mariscal Sucre
 * Arquitectura RESTful, Middleware de Autenticacion, Auditoria
 */

declare(strict_types=1);

if (!defined('UPTEC_ACCESS')) {
    define('UPTEC_ACCESS', true);
}

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../security/csrf.php';
require_once __DIR__ . '/../security/sanitizer.php';
require_once __DIR__ . '/../security/validator.php';

// Headers CORS y de seguridad
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Clase Router
class Router
{
    private array $routes = [];

    public function register(string $method, string $pattern, callable $handler, array $middleware = [], ?string $endpoint = null): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
            'endpoint' => $endpoint
        ];
    }

    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $requestedEndpoint = $_GET['endpoint'] ?? null;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            // Soportar subcarpetas: la URI debe terminar con el patron
            $pattern = $route['pattern'];
            if (!str_ends_with($uri, $pattern)) continue;

            // Extraer la parte relativa para el match (quitar prefijo de subcarpeta)
            $relativeUri = substr($uri, -strlen($pattern));
            $params = $this->match($pattern, $relativeUri);
            if ($params === false) continue;

            // Verificar endpoint si la ruta tiene uno definido
            if ($route['endpoint'] !== null && $route['endpoint'] !== $requestedEndpoint) continue;

            // Ejecutar middleware
            foreach ($route['middleware'] as $mw) {
                $result = call_user_func($mw);
                if ($result === false) return;
            }

            try {
                $result = call_user_func_array($route['handler'], $params);
                if ($result !== null) {
                    echo json_encode($result);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
    }

    private function match(string $pattern, string $uri)
    {
        // Convertir patron a regex
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';

        if (!preg_match($regex, $uri, $matches)) {
            return false;
        }

        // Extraer parametros nombrados
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return [$params];
    }
}

// Helpers de middleware
function requireAuth(): callable {
    return function() {
        Auth::init();
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sesion requerida']);
            return false;
        }
        return true;
    };
}

function requireAdmin(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Se requiere rol Administrador']);
            return false;
        }
        return true;
    };
}

function requireAnalystOrAbove(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isAnalystOrAbove()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Se requiere rol Analista o superior']);
            return false;
        }
        return true;
    };
}

function requireTeacherOrAbove(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isTeacherOrAbove()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Se requiere rol Facilitador o superior']);
            return false;
        }
        return true;
    };
}

function requireRole(string $role): callable {
    return function() use ($role) {
        Auth::init();
        if (!Auth::check() || !Auth::hasRole($role)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => "Se requiere rol {$role}"]);
            return false;
        }
        return true;
    };
}

function validateCsrf(): callable {
    return function() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            if (!CSRF::validate()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Token CSRF invalido']);
                return false;
            }
        }
        return true;
    };
}

// Helper para obtener datos JSON del body
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

// Helper para respuestas JSON
function jsonResponse(bool $success, $data = null, ?string $error = null): array {
    $response = ['success' => $success];
    if ($data !== null) $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
    if ($error) $response['error'] = $error;
    return $response;
}

// Inicializar router
$router = new Router();

// Endpoint para importar y restaurar la base de datos (.sql)
// Recibe un POST en ?endpoint=restore
$router->register('POST', '/backend/api/api.php', function() {
    require_once __DIR__ . '/backup.php';
    return importarRestauracionBD();
}, [requireAdmin(), validateCsrf()], 'restore');

// ============================================
// ENDPOINTS DE AUTENTICACION
// ============================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios.php';
require_once __DIR__ . '/cursos.php';
require_once __DIR__ . '/inscripciones.php';
require_once __DIR__ . '/calificaciones.php';
require_once __DIR__ . '/reportes.php';
require_once __DIR__ . '/backup.php';

// ============================================
// DESPACHAR
// ============================================
$router->dispatch();
