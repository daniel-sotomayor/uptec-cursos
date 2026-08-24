<?php
/**
 * UPTEC - API Endpoints de Calificaciones
 * Registrar notas, ver notas, historial academico
 */

// ============================================
// GET /backend/api/api.php?endpoint=calificaciones
// Listar calificaciones por curso o estudiante
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'calificaciones') return null;

    $inscripcionId = $_GET['inscripcion_id'] ?? '';
    $cursoId = $_GET['curso_id'] ?? '';
    $user = Auth::user();

    try {
        $db = getDB();

        // Participante solo ve sus calificaciones
        if ($user['rol'] === 'Participante') {
            $stmt = $db->prepare("
                SELECT cal.*,
                       CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante,
                       c.nombre AS nombre_curso, c.codigo AS codigo_curso
                FROM calificaciones cal
                JOIN inscripciones ins ON cal.inscripcion_id = ins.id
                JOIN usuarios u ON ins.usuario_id = u.id
                JOIN cursos c ON ins.curso_id = c.id
                WHERE ins.usuario_id = ?
                ORDER BY cal.fecha_registro DESC
            ");
            $stmt->execute([$user['id']]);
            return jsonResponse(true, ['calificaciones' => $stmt->fetchAll()]);
        }

        // Facilitador ve calificaciones de sus cursos
        // Analista y Admin ven todas
        $sql = "
            SELECT cal.*,
                   CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante,
                   c.nombre AS nombre_curso, c.codigo AS codigo_curso,
                   ins.usuario_id, ins.curso_id
            FROM calificaciones cal
            JOIN inscripciones ins ON cal.inscripcion_id = ins.id
            JOIN usuarios u ON ins.usuario_id = u.id
            JOIN cursos c ON ins.curso_id = c.id
            WHERE 1=1
        ";
        $values = [];

        if ($user['rol'] === 'Facilitador') {
            $sql .= " AND c.facilitador_id = ?";
            $values[] = $user['id'];
        }

        if ($inscripcionId && is_numeric($inscripcionId)) {
            $sql .= " AND cal.inscripcion_id = ?";
            $values[] = (int)$inscripcionId;
        }

        if ($cursoId && is_numeric($cursoId)) {
            $sql .= " AND ins.curso_id = ?";
            $values[] = (int)$cursoId;
        }

        $sql .= " ORDER BY cal.fecha_registro DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        return jsonResponse(true, ['calificaciones' => $stmt->fetchAll()]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error listando calificaciones: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener calificaciones');
    }

}, [requireAuth()], 'calificaciones');

// ============================================
// GET /backend/api/api.php?endpoint=mis-notas
// Mis notas como participante (con detalle de curso)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'mis-notas') return null;

    $user = Auth::user();

    try {
        $db = getDB();

        // Inscripciones con notas
        $stmt = $db->prepare("
            SELECT i.id AS inscripcion_id, i.estado, i.nota_final,
                   c.id AS curso_id, c.codigo, c.nombre, c.duracion_horas, c.area,
                   CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador
            FROM inscripciones i
            JOIN cursos c ON i.curso_id = c.id
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE i.usuario_id = ? AND i.estado != 'Abandonado'
            ORDER BY i.fecha_inscripcion DESC
        ");
        $stmt->execute([$user['id']]);
        $inscripciones = $stmt->fetchAll();

        // Para cada inscripcion, obtener calificaciones detalladas y plan de evaluacion
        foreach ($inscripciones as &$insc) {
            $stmt = $db->prepare("
                SELECT cal.id, cal.evaluacion_id, cal.tipo_evaluacion, cal.descripcion, cal.nota, cal.peso, cal.fecha_evaluacion, cal.observaciones
                FROM calificaciones cal
                WHERE cal.inscripcion_id = ?
                ORDER BY cal.fecha_evaluacion
            ");
            $stmt->execute([$insc['inscripcion_id']]);
            $insc['calificaciones'] = $stmt->fetchAll();

            // Plan de evaluacion del curso
            $stmt = $db->prepare("
                SELECT * FROM evaluaciones WHERE curso_id = ? AND activo = 1 ORDER BY orden
            ");
            $stmt->execute([$insc['curso_id']]);
            $insc['plan_evaluacion'] = $stmt->fetchAll();
        }

        return jsonResponse(true, ['inscripciones' => $inscripciones]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error obteniendo notas: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener notas');
    }

}, [requireRole('Participante')], 'mis-notas');

// ============================================
// POST /backend/api/api.php?endpoint=calificacion
// Registrar calificacion (Facilitador) - ESTRICTO CON EVALUACIÓN MATRIZ
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'calificacion') return null;

    $data = getJsonInput();
    $user = Auth::user();

    $inscripcionId = Sanitizer::int($data['inscripcion_id'] ?? 0);
    $evaluacionId = Sanitizer::int($data['evaluacion_id'] ?? 0); // Requisito estricto
    $nota = Sanitizer::float($data['nota'] ?? -1);

    $validator = new Validator();
    $validator->integer($inscripcionId, 'inscripcion_id', 1);
    $validator->integer($evaluacionId, 'evaluacion_id', 1); // Validación obligatoria
    $validator->nota($nota ?? 0, 'nota');

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // 1. Obtener la inscripción para saber de qué curso es el alumno
        $stmt = $db->prepare("
            SELECT i.*, c.facilitador_id, c.nombre AS curso_nombre
            FROM inscripciones i
            JOIN cursos c ON i.curso_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$inscripcionId]);
        $inscripcion = $stmt->fetch();

        if (!$inscripcion) {
            http_response_code(404);
            return jsonResponse(false, null, 'Inscripcion no encontrada');
        }

        // Verificar acceso del Facilitador a este curso
        if ($user['rol'] === 'Facilitador' && $inscripcion['facilitador_id'] != $user['id']) {
            http_response_code(403);
            return jsonResponse(false, null, 'No tiene acceso a este curso');
        }

        // 2. Obtener la Evaluación Matriz para forzar el Peso y la Fecha oficial
        $stmtEval = $db->prepare("SELECT * FROM evaluaciones WHERE id = ? AND activo = 1");
        $stmtEval->execute([$evaluacionId]);
        $evaluacionOficial = $stmtEval->fetch();

        if (!$evaluacionOficial) {
            http_response_code(404);
            return jsonResponse(false, null, 'Evaluacion no encontrada en el plan de estudios');
        }

        // 3. Validar que no califiquen a un alumno con el parcial de OTRA materia
        if ($evaluacionOficial['curso_id'] != $inscripcion['curso_id']) {
            http_response_code(400);
            return jsonResponse(false, null, 'Esta evaluacion no pertenece al curso del estudiante');
        }

        // 4. Verificar que no exista ya una calificación para esta evaluación e inscripción
        $stmtDuplicado = $db->prepare("SELECT id FROM calificaciones WHERE inscripcion_id = ? AND evaluacion_id = ?");
        $stmtDuplicado->execute([$inscripcionId, $evaluacionId]);
        if ($stmtDuplicado->fetch()) {
             http_response_code(409);
             return jsonResponse(false, null, 'El estudiante ya tiene una nota cargada para esta evaluacion');
        }

        // 5. Inserción BLINDADA: Usamos los metadatos de $evaluacionOficial, no los del $data del frontend
        $stmt = $db->prepare("
            INSERT INTO calificaciones (inscripcion_id, evaluacion_id, tipo_evaluacion, descripcion, nota, peso, fecha_evaluacion, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $inscripcionId,
            $evaluacionId,
            $evaluacionOficial['tipo'],
            $evaluacionOficial['descripcion'],
            $nota,
            $evaluacionOficial['peso'],
            $evaluacionOficial['fecha_evaluacion'] ?? date('Y-m-d'),
            $data['observaciones'] ?? null
        ]);

        $calId = (int)$db->lastInsertId();

        Auth::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'CREATE', 'calificaciones', $calId, null, [
            'inscripcion_id' => $inscripcionId,
            'evaluacion_id'  => $evaluacionId,
            'nota' => $nota,
            'peso_oficial' => $evaluacionOficial['peso']
        ]);

        return jsonResponse(true, [
            'message' => 'Calificacion registrada exitosamente',
            'calificacion_id' => $calId,
            'nota_actualizada' => $nota
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error registrando calificacion: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al registrar calificacion');
    }

}, [requireTeacherOrAbove()], 'calificacion');

// ============================================
// PUT /backend/api/api.php?endpoint=calificacion&id=X
// Actualizar calificacion
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'calificacion') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    $data = getJsonInput();
    $user = Auth::user();

    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM calificaciones WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            http_response_code(404);
            return jsonResponse(false, null, 'Calificacion no encontrada');
        }

        $campos = [];
        $values = [];

        if (isset($data['nota'])) {
            $campos[] = "nota = ?";
            $values[] = $data['nota'];
        }
        if (isset($data['tipo_evaluacion'])) {
            $campos[] = "tipo_evaluacion = ?";
            $values[] = $data['tipo_evaluacion'];
        }
        if (isset($data['descripcion'])) {
            $campos[] = "descripcion = ?";
            $values[] = $data['descripcion'];
        }
        if (isset($data['observaciones'])) {
            $campos[] = "observaciones = ?";
            $values[] = $data['observaciones'];
        }

        if (empty($campos)) {
            http_response_code(400);
            return jsonResponse(false, null, 'No hay campos para actualizar');
        }

        $sql = "UPDATE calificaciones SET " . implode(', ', $campos) . " WHERE id = ?";
        $values[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        Auth::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'UPDATE', 'calificaciones', $id, $anterior, $data);

        return jsonResponse(true, null, 'Calificacion actualizada');

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al actualizar calificacion');
    }

}, [requireTeacherOrAbove()], 'calificacion');

// ============================================
// GET /backend/api/api.php?endpoint=estudiantes-curso&id=X
// Estudiantes de un curso especifico con calificaciones
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'estudiantes-curso') return null;

    $cursoId = Sanitizer::int($_GET['id'] ?? 0);
    $user = Auth::user();

    if (!$cursoId) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de curso requerido');
    }

    try {
        $db = getDB();

        // Verificar acceso al curso
        if ($user['rol'] === 'Facilitador') {
            $stmt = $db->prepare("SELECT id FROM cursos WHERE id = ? AND facilitador_id = ?");
            $stmt->execute([$cursoId, $user['id']]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                return jsonResponse(false, null, 'No tiene acceso a este curso');
            }
        }

        $stmt = $db->prepare("
            SELECT i.id AS inscripcion_id, i.estado, i.nota_final, i.observaciones,
                   u.id AS usuario_id, u.cedula, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante, u.correo,
                   (SELECT COUNT(*) FROM calificaciones WHERE inscripcion_id = i.id) AS total_calificaciones
            FROM inscripciones i
            JOIN usuarios u ON i.usuario_id = u.id
            WHERE i.curso_id = ? AND i.estado != 'Abandonado'
            ORDER BY u.apellidos
        ");
        $stmt->execute([$cursoId]);
        $estudiantes = $stmt->fetchAll();

        // Obtener calificaciones por estudiante
        foreach ($estudiantes as &$est) {
            $stmt = $db->prepare("
                SELECT id, evaluacion_id, tipo_evaluacion, nota, peso, fecha_evaluacion, observaciones
                FROM calificaciones
                WHERE inscripcion_id = ?
                ORDER BY fecha_registro
            ");
            $stmt->execute([$est['inscripcion_id']]);
            $est['calificaciones'] = $stmt->fetchAll();
        }

        // Plan de evaluacion
        $stmt = $db->prepare("SELECT * FROM evaluaciones WHERE curso_id = ? AND activo = 1 ORDER BY orden");
        $stmt->execute([$cursoId]);
        $planEvaluacion = $stmt->fetchAll();

        return jsonResponse(true, [
            'estudiantes' => $estudiantes,
            'plan_evaluacion' => $planEvaluacion,
            'total_estudiantes' => count($estudiantes)
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener estudiantes');
    }

}, [requireTeacherOrAbove()], 'estudiantes-curso');
