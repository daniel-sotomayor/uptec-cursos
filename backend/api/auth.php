<?php
/**
 * UPTEC - API Endpoints de Autenticacion
 * Login, Logout, Registro, Perfil, Cambio de Password
 */

declare(strict_types=1);

// ============================================
// POST /backend/api/api.php?endpoint=login
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $data = getJsonInput();
    $email = Sanitizer::email($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        return jsonResponse(false, null, 'Correo y contrasena son requeridos');
    }

    $user = Auth::login($email, $password);

    if (is_array($user) && isset($user['error'])) {
        http_response_code(400);
        return jsonResponse(false, null, $user['error']);
    }

    if ($user === false) {
        http_response_code(401);
        return jsonResponse(false, null, 'Credenciales incorrectas');
    }

    return jsonResponse(true, [
        'user' => $user,
        'token' => CSRF::regenerate(),
        'redirect' => '/uptec-cursos/frontend/views/' . match($user['rol']) {
            'Administrador' => 'administrador/dashboard.html',
            'Analista' => 'analista/dashboard.html',
            'Facilitador' => 'facilitador/dashboard.html',
            'Participante' => 'participante/dashboard.html',
            default => 'login.html'
        }
    ]);
}, [], 'login');

// ============================================
// POST /backend/api/api.php?endpoint=logout
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    Auth::logout();
    CSRF::invalidate();
    return jsonResponse(true, null, 'Sesion cerrada exitosamente');
}, [requireAuth()], 'logout');

// ============================================
// GET /backend/api/api.php?endpoint=me
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $user = Auth::user();
    if (!$user) {
        http_response_code(401);
        return jsonResponse(false, null, 'No autenticado');
    }
    return jsonResponse(true, ['user' => $user]);
}, [requireAuth()], 'me');

// ============================================
// POST /backend/api/api.php?endpoint=register
// Registro publico de participantes y facilitadores
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $data = getJsonInput();

    // Validar que se permita el registro
    $allowedRoles = ['Participante', 'Facilitador'];
    $requestedRole = $data['rol'] ?? 'Participante';

    if (!in_array($requestedRole, $allowedRoles, true)) {
        http_response_code(400);
        return jsonResponse(false, null, 'Solo se permite registro de Participantes y Facilitadores');
    }

    // Validar datos
    $validator = new Validator();

    $validator->cedula(strtoupper($data['cedula'] ?? ''));
    $validator->name($data['nombre'] ?? '', 'nombre');
    $validator->name($data['apellidos'] ?? '', 'apellidos');
    $validator->email($data['correo'] ?? '', false);
    $validator->telefono($data['telefono'] ?? '');
    $validator->passwordConfirm(
        $data['password'] ?? '',
        $data['password_confirm'] ?? '',
        'password'
    );

    // Validar area para participantes
    $area = null;
    if ($requestedRole === 'Participante') {
        $area = $data['area'] ?? '';
        $areasPermitidas = ['Administracion', 'Informatica', 'Mecanica', 'Electrica', 'Mantenimiento', 'Transporte Ferroviario', 'Externo'];
        if (!in_array($area, $areasPermitidas)) {
            http_response_code(422);
            return jsonResponse(false, null, 'Debe seleccionar un area/carrera valida');
        }
    }

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // Verificar que cedula no exista
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE cedula = ? OR correo = ?");
        $stmt->execute([strtoupper($data['cedula']), $data['correo']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return jsonResponse(false, null, 'La cedula o correo ya estan registrados');
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        if ($requestedRole === 'Facilitador') {
            $activo = 0;
        } else {
            $activo = 1;
}

        $stmt = $db->prepare("
            INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, password, rol, area, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            strtoupper($data['cedula']),
            $data['nombre'],
            $data['apellidos'],
            $data['correo'],
            $data['telefono'],
            $hash,
            $requestedRole,
            $area,
            $activo,
        ]);

        $userId = (int)$db->lastInsertId();

        Auth::logActivity($userId, $data['nombre'] . ' ' . $data['apellidos'], 'CREATE', 'usuarios', $userId, null, [
            'cedula' => strtoupper($data['cedula']),
            'nombre' => $data['nombre'],
            'apellidos' => $data['apellidos'],
            'correo' => $data['correo'],
            'rol' => $requestedRole,
            'area' => $area
        ]);

        return jsonResponse(true, [
            'message' => 'Registro exitoso. Ahora puede iniciar sesion.',
            'user_id' => $userId
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error en registro: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al registrar usuario');
    }
}, [], 'register');

// ============================================
// PUT /backend/api/api.php?endpoint=password
// Cambio de contrasena (autenticado)
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $data = getJsonInput();
    $user = Auth::user();

    if (empty($data['current_password']) || empty($data['new_password'])) {
        http_response_code(400);
        return jsonResponse(false, null, 'Contrasena actual y nueva son requeridas');
    }

    if (!Auth::verifyPassword($user['id'], $data['current_password'])) {
        http_response_code(401);
        return jsonResponse(false, null, 'Contrasena actual incorrecta');
    }

    $validator = new Validator();
    if (!$validator->password($data['new_password'], 'new_password')) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    if (Auth::changePassword($user['id'], $data['new_password'])) {
        return jsonResponse(true, null, 'Contrasena actualizada exitosamente');
    }

    http_response_code(500);
    return jsonResponse(false, null, 'Error al actualizar contrasena');

}, [requireAuth()], 'password');

// ============================================
// GET /backend/api/api.php?endpoint=csrf
// Obtener token CSRF
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    return jsonResponse(true, ['token' => CSRF::regenerate()]);
}, [], 'csrf');

// ============================================
// Middleware helper para validar endpoint
// ============================================
function handleEndpoint($router, $method, $endpoint, $callback, array $middleware = []) {
    $router->register($method, '/backend/api/api.php', function($params) use ($endpoint, $callback) {
        $params['endpoint'] = $endpoint;
        return $callback($params);
    }, $middleware);
}
