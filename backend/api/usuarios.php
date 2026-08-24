<?php
/**
 * UPTEC - API Endpoints de Usuarios
 * CRUD completo, gestion por rol, asignacion de analistas
 */

// ============================================
// GET /backend/api/api.php?endpoint=usuarios
// Lista de usuarios (Admin y Analista)
// Filtros: ?rol=&buscar=&activo=
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'usuarios') return null;

    $rol = $_GET['rol'] ?? '';
    $buscar = $_GET['buscar'] ?? '';
    $activo = $_GET['activo'] ?? '';

    try {
        $db = getDB();

        $sql = "SELECT id, cedula, nombre, apellidos, correo, telefono, rol, activo, ultimo_acceso, creado_en FROM usuarios WHERE 1=1";
        $values = [];

        // Admin puede ver todos, Analista solo facilitadores y participantes
        $userRole = Auth::role();
        if ($userRole === 'Analista') {
            $sql .= " AND rol IN ('Facilitador', 'Participante')";
        }

        if ($rol && in_array($rol, ['Administrador', 'Analista', 'Facilitador', 'Participante'])) {
            $sql .= " AND rol = ?";
            $values[] = $rol;
        }

        if ($activo !== '' && in_array($activo, ['0', '1'])) {
            $sql .= " AND activo = ?";
            $values[] = (int)$activo;
        }

        if ($buscar) {
            $search = "%{$buscar}%";
            $sql .= " AND (cedula LIKE ? OR nombre LIKE ? OR apellidos LIKE ? OR correo LIKE ?)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $sql .= " ORDER BY creado_en DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $usuarios = $stmt->fetchAll();

        return jsonResponse(true, ['usuarios' => $usuarios, 'total' => count($usuarios)]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error listando usuarios: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener usuarios');
    }

}, [requireAnalystOrAbove()], 'usuarios');

// ============================================
// GET /backend/api/api.php?endpoint=usuario&id=X
// Detalle de un usuario
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'usuario') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de usuario requerido');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, cedula, nombre, apellidos, correo, telefono, rol, activo, ultimo_acceso, creado_en
            FROM usuarios WHERE id = ?
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            http_response_code(404);
            return jsonResponse(false, null, 'Usuario no encontrado');
        }

        return jsonResponse(true, ['usuario' => $usuario]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error obteniendo usuario: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener usuario');
    }

}, [requireAnalystOrAbove()], 'usuario');

// ============================================
// POST /backend/api/api.php?endpoint=usuario
// Crear usuario (Admin solo)
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'usuario') return null;

    $data = getJsonInput();

    $validator = new Validator();
    $validator->cedula($data['cedula'] ?? '');
    $validator->name($data['nombre'] ?? '', 'nombre');
    $validator->name($data['apellidos'] ?? '', 'apellidos');
    $validator->email($data['correo'] ?? '', false);
    $validator->telefono($data['telefono'] ?? '');
    $validator->rol($data['rol'] ?? '');

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // Verificar duplicados
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE cedula = ? OR correo = ?");
        $stmt->execute([$data['cedula'], $data['correo']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return jsonResponse(false, null, 'La cedula o correo ya existen');
        }

        // Generar contrasena temporal
        $tempPass = bin2hex(random_bytes(4)); // 8 caracteres
        $hash = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare("
            INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, password, rol, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $data['cedula'],
            $data['nombre'],
            $data['apellidos'],
            $data['correo'],
            $data['telefono'],
            $hash,
            $data['rol']
        ]);

        $userId = (int)$db->lastInsertId();
        $currentUser = Auth::user();

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'CREATE', 'usuarios', $userId, null, [
            'cedula' => $data['cedula'],
            'nombre' => $data['nombre'],
            'apellidos' => $data['apellidos'],
            'correo' => $data['correo'],
            'rol' => $data['rol']
        ]);

        return jsonResponse(true, [
            'message' => 'Usuario creado exitosamente',
            'user_id' => $userId,
            'temp_password' => $tempPass
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error creando usuario: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al crear usuario');
    }

}, [requireAdmin()], 'usuario');

// ============================================
// PUT /backend/api/api.php?endpoint=usuario&id=X
// Actualizar usuario (Admin o Analista)
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'usuario') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    $data = getJsonInput();
    $currentUser = Auth::user();

    try {
        $db = getDB();

        // Obtener datos anteriores para auditoria
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            http_response_code(404);
            return jsonResponse(false, null, 'Usuario no encontrado');
        }

        // Campos permitidos
        $campos = [];
        $values = [];

        if (isset($data['nombre'])) { $campos[] = "nombre = ?"; $values[] = $data['nombre']; }
        if (isset($data['apellidos'])) { $campos[] = "apellidos = ?"; $values[] = $data['apellidos']; }
        if (isset($data['telefono'])) { $campos[] = "telefono = ?"; $values[] = $data['telefono']; }
        if (isset($data['activo']) && Auth::isAnalystOrAbove()) { $campos[] = "activo = ?"; $values[] = $data['activo']; }
        if (isset($data['rol']) && Auth::isAdmin()) { $campos[] = "rol = ?"; $values[] = $data['rol']; }
        if (isset($data['area']) && Auth::isAdmin()) { $campos[] = "area = ?"; $values[] = $data['area']; }
        // Actualizar contraseña si se proporciona (solo Admin o Analista puede cambiar)
        // Solo procesar la contraseña si viene definida, no está vacía y tiene al menos 6 caracteres
        if (isset($data['password']) && trim($data['password']) !== '') {
            $password = trim($data['password']);
            if (strlen($password) >= 6) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $campos[] = "password = ?";
                $values[] = $hash;
            }
        }

        if (empty($campos)) {
            http_response_code(400);
            return jsonResponse(false, null, 'No hay campos para actualizar');
        }
        error_log("[DEBUG UPTEC] Campos a actualizar: " . print_r($campos, true));
        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
        $values[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        // Obtener nuevos datos
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $nuevo = $stmt->fetch();

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'UPDATE', 'usuarios', $id, $anterior, $nuevo);

        return jsonResponse(true, null, 'Usuario actualizado exitosamente');

    } catch (PDOException $e) {
        error_log("[UPTEC] Error actualizando usuario: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al actualizar usuario');
    }

}, [requireAnalystOrAbove()], 'usuario');

// ============================================
// DELETE /backend/api/api.php?endpoint=usuario&id=X
// Eliminar/desactivar usuario (Admin)
// ============================================
$router->register('DELETE', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'usuario') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    // No eliminar admin principal
    if ($id === 1) {
        http_response_code(403);
        return jsonResponse(false, null, 'No se puede eliminar el administrador principal');
    }

    $currentUser = Auth::user();

    try {
        $db = getDB();

        // Soft delete - desactivar
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        $stmt = $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'DELETE', 'usuarios', $id, $anterior, ['activo' => 0]);

        return jsonResponse(true, null, 'Usuario desactivado exitosamente');

    } catch (PDOException $e) {
        error_log("[UPTEC] Error desactivando usuario: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al desactivar usuario');
    }

}, [requireAdmin()], 'usuario');

// ============================================
// GET /backend/api/api.php?endpoint=facilitadores
// Lista de facilitadores (para asignar a cursos)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'facilitadores') return null;

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, cedula, CONCAT(nombre, ' ', apellidos) AS nombre_completo, correo
            FROM usuarios WHERE rol = 'Facilitador' AND activo = 1 ORDER BY nombre
        ");
        $stmt->execute();

        return jsonResponse(true, ['facilitadores' => $stmt->fetchAll()]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener facilitadores');
    }

}, [requireAnalystOrAbove()], 'facilitadores');

// ============================================
// GET /backend/api/api.php?endpoint=analistas
// Lista de analistas (para asignar a cursos)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'analistas') return null;

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, cedula, CONCAT(nombre, ' ', apellidos) AS nombre_completo, correo
            FROM usuarios WHERE rol = 'Analista' AND activo = 1 ORDER BY nombre
        ");
        $stmt->execute();

        return jsonResponse(true, ['analistas' => $stmt->fetchAll()]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener analistas');
    }

}, [requireAdmin()], 'analistas');
