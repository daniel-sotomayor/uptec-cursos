<?php
/**
 * UPTEC - API Endpoints de Inscripciones
 * Inscribir, cambiar estado, listar por curso o participante
 */

// ============================================
// GET /backend/api/api.php?endpoint=inscripciones
// Listar inscripciones con filtros
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'inscripciones') return null;

    $cursoId = $_GET['curso_id'] ?? '';
    $estado = $_GET['estado'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    try {
        $db = getDB();

        $sql = "
            SELECT i.*,
                   CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante,
                   u.cedula, u.correo,
                   c.codigo AS codigo_curso, c.nombre AS nombre_curso
            FROM inscripciones i
            JOIN usuarios u ON i.usuario_id = u.id
            JOIN cursos c ON i.curso_id = c.id
            WHERE 1=1
        ";
        $values = [];

        // Facilitador solo ve inscripciones de sus cursos
        $user = Auth::user();
        if ($user['rol'] === 'Facilitador') {
            $sql .= " AND c.facilitador_id = ?";
            $values[] = $user['id'];
        }

        if ($cursoId && is_numeric($cursoId)) {
            $sql .= " AND i.curso_id = ?";
            $values[] = (int)$cursoId;
        }

        if ($estado) {
            $sql .= " AND i.estado = ?";
            $values[] = $estado;
        }

        if ($buscar) {
            $sql .= " AND (u.cedula LIKE ? OR u.nombre LIKE ? OR u.apellidos LIKE ?)";
            $search = "%{$buscar}%";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $sql .= " ORDER BY i.fecha_inscripcion DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        return jsonResponse(true, ['inscripciones' => $stmt->fetchAll()]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error listando inscripciones: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener inscripciones');
    }

}, [requireTeacherOrAbove()], 'inscripciones');

// ============================================
// POST /backend/api/api.php?endpoint=inscripcion
// Inscribir participante en un curso (participante o admin)
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'inscripcion') return null;

    $data = getJsonInput();
    $user = Auth::user();

    $cursoId = Sanitizer::int($data['curso_id'] ?? 0);

    // Participante se inscribe a si mismo
    $usuarioId = ($user['rol'] === 'Participante') ? $user['id'] : Sanitizer::int($data['usuario_id'] ?? 0);

    if (!$cursoId || !$usuarioId) {
        http_response_code(400);
        return jsonResponse(false, null, 'Curso y usuario son requeridos');
    }

    // Verificar permisos
    if ($user['rol'] === 'Participante' && $usuarioId !== $user['id']) {
        http_response_code(403);
        return jsonResponse(false, null, 'No puede inscribir a otro participante');
    }

    try {
        $db = getDB();

        // Verificar curso activo
        $stmt = $db->prepare("SELECT cupo_maximo, estado FROM cursos WHERE id = ? AND activo = 1");
        $stmt->execute([$cursoId]);
        $curso = $stmt->fetch();

        if (!$curso) {
            http_response_code(404);
            return jsonResponse(false, null, 'Curso no encontrado');
        }

        if ($curso['estado'] === 'Cancelado' || $curso['estado'] === 'Finalizado') {
            http_response_code(400);
            return jsonResponse(false, null, 'No se pueden hacer inscripciones en este curso');
        }

        // Verificar cupo
        $stmt = $db->prepare("SELECT COUNT(*) FROM inscripciones WHERE curso_id = ? AND estado != 'Abandonado'");
        $stmt->execute([$cursoId]);
        if ($stmt->fetchColumn() >= $curso['cupo_maximo']) {
            http_response_code(400);
            return jsonResponse(false, null, 'No hay cupos disponibles para este curso');
        }

        // Verificar que no este ya inscrito
        $stmt = $db->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ? AND estado != 'Abandonado'");
        $stmt->execute([$usuarioId, $cursoId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return jsonResponse(false, null, 'El participante ya esta inscrito en este curso');
        }

        $stmt = $db->prepare("
            INSERT INTO inscripciones (usuario_id, curso_id, estado)
            VALUES (?, ?, 'Inscrito')
        ");
        $stmt->execute([$usuarioId, $cursoId]);

        $inscripcionId = (int)$db->lastInsertId();

        Auth::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'INSCRIBIR', 'inscripciones', $inscripcionId, null, [
            'usuario_id' => $usuarioId,
            'curso_id' => $cursoId
        ]);

        return jsonResponse(true, [
            'message' => 'Inscripcion realizada exitosamente',
            'inscripcion_id' => $inscripcionId
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error en inscripcion: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al realizar inscripcion');
    }

}, [requireAuth()], 'inscripcion');

// ============================================
// PUT /backend/api/api.php?endpoint=inscripcion&id=X
// Actualizar estado de inscripcion
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'inscripcion') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    $data = getJsonInput();
    $user = Auth::user();

    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM inscripciones WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            http_response_code(404);
            return jsonResponse(false, null, 'Inscripcion no encontrada');
        }

        $campos = [];
        $values = [];

        if (isset($data['estado'])) {
            $campos[] = "estado = ?";
            $values[] = $data['estado'];
        }
        if (isset($data['observaciones'])) {
            $campos[] = "observaciones = ?";
            $values[] = $data['observaciones'];
        }

        if (empty($campos)) {
            http_response_code(400);
            return jsonResponse(false, null, 'No hay campos para actualizar');
        }

        $sql = "UPDATE inscripciones SET " . implode(', ', $campos) . " WHERE id = ?";
        $values[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        Auth::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'UPDATE', 'inscripciones', $id, $anterior, $data);

        return jsonResponse(true, null, 'Inscripcion actualizada');

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al actualizar inscripcion');
    }

}, [requireTeacherOrAbove()], 'inscripcion');

// ============================================
// DELETE /backend/api/api.php?endpoint=inscripcion&id=X
// Cancelar inscripcion (marcar como abandonada)
// ============================================
$router->register('DELETE', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'inscripcion') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    $user = Auth::user();

    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    try {
        $db = getDB();

        // Participante solo puede cancelar sus propias inscripciones
        if ($user['rol'] === 'Participante') {
            $stmt = $db->prepare("SELECT usuario_id FROM inscripciones WHERE id = ?");
            $stmt->execute([$id]);
            $insc = $stmt->fetch();
            if (!$insc || $insc['usuario_id'] != $user['id']) {
                http_response_code(403);
                return jsonResponse(false, null, 'No puede cancelar esta inscripcion');
            }
        }

        $stmt = $db->prepare("SELECT * FROM inscripciones WHERE id = ?");
        $stmt->execute([$id]);
        $anterior = $stmt->fetch();

        $stmt = $db->prepare("UPDATE inscripciones SET estado = 'Abandonado' WHERE id = ?");
        $stmt->execute([$id]);

        Auth::logActivity($user['id'], $user['nombre'] . ' ' . $user['apellidos'], 'DELETE', 'inscripciones', $id, $anterior, ['estado' => 'Abandonado']);

        return jsonResponse(true, null, 'Inscripcion cancelada');

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al cancelar inscripcion');
    }

}, [requireAuth()], 'inscripcion');

// ============================================
// GET /backend/api/api.php?endpoint=cursos-disponibles
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'cursos-disponibles') return null;

    $user = Auth::user();

    try {
        $db = getDB();

        // 1. Verificar si hay cursos que deban finalizar hoy
        if (function_exists('verificarCursosVencidos')) {
            verificarCursosVencidos($db);
        }

        // Obtener el area del participante (solo para referencia y display)
        $stmtUser = $db->prepare("SELECT area FROM usuarios WHERE id = ?");
        $stmtUser->execute([$user['id']]);
        $userArea = $stmtUser->fetchColumn() ?: 'General';

        // Mostrar TODOS los cursos disponibles (las áreas y estados previos no ocultan cursos)
        $stmt = $db->prepare("
            SELECT c.*,
                   CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador,
                   (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND estado != 'Abandonado') AS total_inscritos,
                   (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND usuario_id = ? AND estado != 'Abandonado') AS ya_inscrito,
                   (SELECT COUNT(*) FROM egresados WHERE curso_id = c.id AND usuario_id = ? AND estado_academico = 'Aprobado') AS ya_aprobado
            FROM cursos c
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE c.activo = 1
              AND c.estado IN ('Verificado', 'En Curso', 'Planificado')
            ORDER BY c.fecha_inicio DESC
        ");
        $stmt->execute([$user['id'], $user['id']]);

        return jsonResponse(true, ['cursos' => $stmt->fetchAll(), 'area_usuario' => $userArea]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener cursos disponibles');
    }

}, [requireRole('Participante')], 'cursos-disponibles');