<?php
/**
 * UPTEC - API Endpoints de Cursos
 * CRUD, asignacion de facilitadores/analistas, plan de evaluacion
 */

// ============================================
// GET /backend/api/api.php?endpoint=cursos
// Lista de cursos con filtros
// Filtros: ?estado=&facilitador_id=&area=&buscar=
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'cursos') return null;

    $estado = $_GET['estado'] ?? '';
    $facilitadorId = $_GET['facilitador_id'] ?? '';
    $area = $_GET['area'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    try {
        $db = getDB();

        $sql = "
            SELECT c.*,
                   CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador,
                   f.correo AS correo_facilitador,
                   (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id AND i.estado != 'Abandonado') AS total_inscritos
            FROM cursos c
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE c.activo = 1
        ";
        $values = [];

        if ($estado && in_array($estado, ['Planificado', 'En Curso', 'Finalizado', 'Cancelado'])) {
            $sql .= " AND c.estado = ?";
            $values[] = $estado;
        }

        if ($facilitadorId && is_numeric($facilitadorId)) {
            $sql .= " AND c.facilitador_id = ?";
            $values[] = (int)$facilitadorId;
        }

        if ($area) {
            $sql .= " AND c.area = ?";
            $values[] = $area;
        }

        if ($buscar) {
            $sql .= " AND (c.codigo LIKE ? OR c.nombre LIKE ? OR c.descripcion LIKE ?)";
            $search = "%{$buscar}%";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $sql .= " ORDER BY c.creado_en DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $cursos = $stmt->fetchAll();

        return jsonResponse(true, ['cursos' => $cursos, 'total' => count($cursos)]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error listando cursos: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener cursos');
    }
}, [], 'cursos');

// ============================================
// GET /backend/api/api.php?endpoint=curso&id=X
// Detalle de curso con plan de evaluacion y horarios
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de curso requerido');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT c.*,
                   CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador,
                   CONCAT(a.nombre, ' ', a.apellidos) AS nombre_analista,
                   (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND estado != 'Abandonado') AS total_inscritos
            FROM cursos c
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            LEFT JOIN usuarios a ON c.analista_id = a.id
            WHERE c.id = ? AND c.activo = 1
        ");
        $stmt->execute([$id]);
        $curso = $stmt->fetch();

        if (!$curso) {
            http_response_code(404);
            return jsonResponse(false, null, 'Curso no encontrado');
        }

        // Plan de evaluacion
        $stmt = $db->prepare("SELECT * FROM evaluaciones WHERE curso_id = ? AND activo = 1 ORDER BY orden");
        $stmt->execute([$id]);
        $evaluaciones = $stmt->fetchAll();

        // Horarios
        $stmt = $db->prepare("SELECT * FROM horarios WHERE curso_id = ? ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo')");
        $stmt->execute([$id]);
        $horarios = $stmt->fetchAll();

        // Estudiantes inscritos
        $stmt = $db->prepare("
            SELECT i.id AS inscripcion_id, i.estado, i.nota_final,
                   u.id AS estudiante_id, u.cedula, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante, u.correo
            FROM inscripciones i
            JOIN usuarios u ON i.usuario_id = u.id
            WHERE i.curso_id = ? AND i.estado != 'Abandonado'
            ORDER BY i.fecha_inscripcion
        ");
        $stmt->execute([$id]);
        $estudiantes = $stmt->fetchAll();

        return jsonResponse(true, [
            'curso' => $curso,
            'evaluaciones' => $evaluaciones,
            'horarios' => $horarios,
            'estudiantes' => $estudiantes
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error obteniendo curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener curso');
    }
}, [], 'curso');

// ============================================
// POST /backend/api/api.php?endpoint=curso
// Crear curso (Analista y Admin)
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso') return null;

    $data = getJsonInput();
    $currentUser = Auth::user();

    $validator = new Validator();
    $validator->required($data['codigo'] ?? '', 'codigo');
    $validator->required($data['nombre'] ?? '', 'nombre');
    $validator->integer($data['duracion_horas'] ?? 0, 'duracion_horas', 1);
    $validator->integer($data['cupo_maximo'] ?? 0, 'cupo_maximo', 1);

    if (!empty($data['fecha_inicio']) && !empty($data['fecha_fin'])) {
        $validator->date($data['fecha_inicio'], 'fecha_inicio');
        $validator->date($data['fecha_fin'], 'fecha_fin');
    }

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // Verificar codigo unico
        $stmt = $db->prepare("SELECT id FROM cursos WHERE codigo = ?");
        $stmt->execute([$data['codigo']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return jsonResponse(false, null, 'El codigo de curso ya existe');
        }

        $stmt = $db->prepare("
            INSERT INTO cursos (codigo, nombre, descripcion, duracion_horas, fecha_inicio, fecha_fin, cupo_maximo, area, nivel, estado, facilitador_id, analista_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['codigo'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            (int)$data['duracion_horas'],
            $data['fecha_inicio'] ?: null,
            $data['fecha_fin'] ?: null,
            (int)$data['cupo_maximo'],
            $data['area'] ?? null,
            $data['nivel'] ?? 'Basico',
            $data['estado'] ?? 'Planificado',
            ($data['facilitador_id'] ?? null) ?: null,
            ($data['analista_id'] ?? null) ?: $currentUser['id'] // El analista actual por defecto
        ]);

        $cursoId = (int)$db->lastInsertId();

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'CREATE', 'cursos', $cursoId, null, $data);

        return jsonResponse(true, [
            'message' => 'Curso creado exitosamente',
            'curso_id' => $cursoId
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error creando curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al crear curso');
    }

}, [requireAnalystOrAbove()], 'curso');

// ============================================
// PUT /backend/api/api.php?endpoint=curso&id=X
// Actualizar curso
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    $data = getJsonInput();
    $currentUser = Auth::user();

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            http_response_code(404);
            return jsonResponse(false, null, 'Curso no encontrado');
        }

        $campos = [];
        $values = [];

        $camposActualizables = ['nombre', 'descripcion', 'duracion_horas', 'fecha_inicio', 'fecha_fin',
                                'cupo_maximo', 'area', 'nivel', 'estado', 'facilitador_id', 'analista_id'];

        foreach ($camposActualizables as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "{$campo} = ?";
                $values[] = $data[$campo] ?: null;
            }
        }

        if (empty($campos)) {
            http_response_code(400);
            return jsonResponse(false, null, 'No hay campos para actualizar');
        }

        $sql = "UPDATE cursos SET " . implode(', ', $campos) . " WHERE id = ?";
        $values[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'UPDATE', 'cursos', $id, $anterior, $data);

        return jsonResponse(true, null, 'Curso actualizado exitosamente');

    } catch (PDOException $e) {
        error_log("[UPTEC] Error actualizando curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al actualizar curso');
    }

}, [requireAnalystOrAbove()], 'curso');

// ============================================
// DELETE /backend/api/api.php?endpoint=curso&id=X
// ============================================
$router->register('DELETE', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    $currentUser = Auth::user();

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        $stmt = $db->prepare("UPDATE cursos SET activo = 0, estado = 'Cancelado' WHERE id = ?");
        $stmt->execute([$id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'DELETE', 'cursos', $id, $anterior, ['activo' => 0]);

        return jsonResponse(true, null, 'Curso eliminado exitosamente');

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al eliminar curso');
    }

}, [requireAdmin()], 'curso');

// ============================================
// PUT /backend/api/api.php?endpoint=curso-verificar&id=X
// Verificar curso (cambiar estado a Verificado)
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso-verificar') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    $data = getJsonInput();
    $currentUser = Auth::user();

    try {
        $db = getDB();

        // Obtener datos actuales
        $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $curso = $stmt->fetch();

        if (!$curso) {
            http_response_code(404);
            return jsonResponse(false, null, 'Curso no encontrado');
        }

        // Actualizar estado a Verificado
        $stmt = $db->prepare("UPDATE cursos SET estado = 'Verificado', verificado_por = ?, fecha_verificacion = NOW() WHERE id = ?");
        $stmt->execute([$currentUser['id'], $id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'VERIFY', 'cursos', $id, ['estado' => $curso['estado']], ['estado' => 'Verificado']);

        return jsonResponse(true, ['message' => 'Curso verificado exitosamente']);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error verificando curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al verificar curso');
    }

}, [requireAnalystOrAbove()], 'curso-verificar');

// ============================================
// GET /backend/api/api.php?endpoint=areas
// Lista de areas de conocimiento
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'areas') return null;

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT DISTINCT area FROM cursos WHERE area IS NOT NULL AND activo = 1 ORDER BY area");
        $stmt->execute();
        $areas = array_column($stmt->fetchAll(), 'area');

        return jsonResponse(true, ['areas' => $areas]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener areas');
    }
}, [], 'areas');

// ============================================
// POST /backend/api/api.php?endpoint=evaluacion
// Crear evaluacion para un curso (Analista, Admin y Facilitador)
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'evaluacion') return null;

    $data = getJsonInput();
    $currentUser = Auth::user();

    $validator = new Validator();
    $validator->required($data['nombre'] ?? '', 'nombre');
    $validator->decimal($data['peso'] ?? 0, 'peso', 0, 100);
    $validator->integer($data['curso_id'] ?? 0, 'curso_id', 1);

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // SEGURIDAD: Verificar que el Facilitador sea el dueño del curso
        if ($currentUser['rol'] === 'Facilitador') {
            $stmtCheck = $db->prepare("SELECT id FROM cursos WHERE id = ? AND facilitador_id = ?");
            $stmtCheck->execute([$data['curso_id'], $currentUser['id']]);
            if (!$stmtCheck->fetch()) {
                http_response_code(403);
                return jsonResponse(false, null, 'No tiene permiso para agregar evaluaciones a este curso');
            }
        }

        $stmt = $db->prepare("
            INSERT INTO evaluaciones (curso_id, nombre, descripcion, tipo, peso, fecha_evaluacion, orden)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['curso_id'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo'] ?? 'Otro',
            $data['peso'],
            $data['fecha_evaluacion'] ?: null,
            $data['orden'] ?? 0
        ]);

        $evalId = (int)$db->lastInsertId();

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'CREATE', 'evaluaciones', $evalId, null, $data);

        return jsonResponse(true, ['message' => 'Evaluacion creada', 'evaluacion_id' => $evalId]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error creando evaluacion: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al crear evaluacion');
    }

}, [requireTeacherOrAbove()], 'evaluacion'); // <--- IMPORTANTE: Se cambió el middleware aquí

// ============================================
// GET /backend/api/api.php?endpoint=mis-cursos
// Cursos del facilitador o participante logueado
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'mis-cursos') return null;

    $user = Auth::user();

    try {
        $db = getDB();

        if ($user['rol'] === 'Facilitador') {
            $stmt = $db->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND estado != 'Abandonado') AS total_inscritos
                FROM cursos c
                WHERE c.facilitador_id = ? AND c.activo = 1
                ORDER BY c.fecha_inicio DESC
            ");
            $stmt->execute([$user['id']]);
        } elseif ($user['rol'] === 'Participante') {
            $stmt = $db->prepare("
                SELECT c.*, i.estado AS estado_inscripcion, i.nota_final
                FROM inscripciones i
                JOIN cursos c ON i.curso_id = c.id
                WHERE i.usuario_id = ? AND i.estado != 'Abandonado'
                ORDER BY i.fecha_inscripcion DESC
            ");
            $stmt->execute([$user['id']]);
        } else {
            http_response_code(403);
            return jsonResponse(false, null, 'Solo para facilitadores y participantes');
        }

        return jsonResponse(true, ['cursos' => $stmt->fetchAll()]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener cursos');
    }

}, [requireAuth()], 'mis-cursos');

// ============================================
// GET /backend/api/api.php?endpoint=evaluaciones-curso
// Lista las evaluaciones creadas para un curso especifico
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'evaluaciones-curso') return null;

    $cursoId = Sanitizer::int($_GET['curso_id'] ?? 0);

    if (!$cursoId) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de curso requerido');
    }

    try {
        $db = getDB();
        
        // Buscamos las evaluaciones activas del curso
        $stmt = $db->prepare("
            SELECT id, nombre, peso 
            FROM evaluaciones 
            WHERE curso_id = ? AND activo = 1 
            ORDER BY orden ASC
        ");
        $stmt->execute([$cursoId]);
        $evaluaciones = $stmt->fetchAll();

        return jsonResponse(true, ['evaluaciones' => $evaluaciones]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error obteniendo evaluaciones del curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener evaluaciones');
    }

}, [requireTeacherOrAbove()], 'evaluaciones-curso');