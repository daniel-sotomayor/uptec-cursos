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
/**
 * Motor de Egreso y Finalización de Cursos
 */
function procesarEgresoCurso(PDO $db, int $cursoId): bool {
    try {
        $db->beginTransaction();

        // 1. Obtener información del curso
        $stmtCurso = $db->prepare("
            SELECT c.*, CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador
            FROM cursos c
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE c.id = ? FOR UPDATE
        ");
        $stmtCurso->execute([$cursoId]);
        $curso = $stmtCurso->fetch();

        if (!$curso || $curso['estado'] === 'Finalizado') {
            $db->rollBack();
            return false;
        }

        // 2. Obtener los estudiantes inscritos activos en este curso
        $stmtInscritos = $db->prepare("
            SELECT i.id AS inscripcion_id, i.usuario_id, i.nota_final, i.observaciones
            FROM inscripciones i
            WHERE i.curso_id = ? AND i.estado != 'Abandonado'
        ");
        $stmtInscritos->execute([$cursoId]);
        $estudiantes = $stmtInscritos->fetchAll();

        // 3. Registrar a cada estudiante en la tabla 'egresados'
        $stmtInsertEgresado = $db->prepare("
            INSERT INTO egresados (
                usuario_id, curso_id, nota_final, estado_academico,
                fecha_egreso, codigo_certificado, duracion_horas,
                facilitador_nombre, observaciones
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)
        ");

        foreach ($estudiantes as $est) {
            $nota = $est['nota_final'] !== null ? (float)$est['nota_final'] : 0.00;
            $estadoAcademico = ($nota >= 10.00) ? 'Aprobado' : 'Reprobado';

            // Código de certificado solo si aprobó
            $codCertificado = null;
            if ($estadoAcademico === 'Aprobado') {
                $codCertificado = 'UPTEC-' . str_pad($est['usuario_id'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($cursoId, 3, '0', STR_PAD_LEFT);
            }

            $stmtInsertEgresado->execute([
                $est['usuario_id'],
                $cursoId,
                $nota,
                $estadoAcademico,
                $codCertificado,
                $curso['duracion_horas'],
                $curso['nombre_facilitador'] ?: 'Facilitador no asignado',
                $est['observaciones']
            ]);
        }

        // 4. "Expulsar": Eliminar inscripciones activas de este curso.
        // Esto desvincula el curso de "Mis Cursos" del estudiante y libera
        // la restricción UNIQUE para que los reprobados puedan volver a inscribirse.
        $stmtDeleteInscripciones = $db->prepare("DELETE FROM inscripciones WHERE curso_id = ?");
        $stmtDeleteInscripciones->execute([$cursoId]);

        // 5. Marcar el curso como Finalizado
        $stmtFinalizar = $db->prepare("UPDATE cursos SET estado = 'Finalizado' WHERE id = ?");
        $stmtFinalizar->execute([$cursoId]);

        $db->commit();

        Auth::logActivity(
            null, 
            'Sistema Automático', 
            'FINALIZAR_CURSO', 
            'cursos', 
            $cursoId, 
            ['estado' => $curso['estado']], 
            ['estado' => 'Finalizado', 'egresados_procesados' => count($estudiantes)]
        );

        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[UPTEC] Error finalizando curso $cursoId: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si existen cursos vencidos según fecha_fin y los finaliza automáticamente
 */
function verificarCursosVencidos(PDO $db): void {
    try {
        // Cursos activos cuya fecha_fin ya se cumplió (fecha_fin <= hoy)
        $stmt = $db->query("
            SELECT id FROM cursos 
            WHERE activo = 1 
              AND estado IN ('Planificado', 'En Curso', 'Verificado')
              AND fecha_fin IS NOT NULL 
              AND fecha_fin <= CURDATE()
        ");
        $vencidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($vencidos as $cursoId) {
            procesarEgresoCurso($db, (int)$cursoId);
        }
    } catch (Exception $e) {
        error_log("[UPTEC] Error verificando cursos vencidos: " . $e->getMessage());
    }
}

$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'cursos') return null;

    $estado = $_GET['estado'] ?? '';
    $facilitadorId = $_GET['facilitador_id'] ?? '';
    $area = $_GET['area'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    try {
        $db = getDB();
        verificarCursosVencidos($db);

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
// Crear curso (Analista y Administrador)
// ============================================
$router->register('POST', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso') return null;

    try {
        $data = getJsonInput();
        $currentUser = Auth::user();

        // 1. Validación nativa robusta (sin depender de métodos ausentes en Validator)
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $duracion = isset($data['duracion_horas']) ? (int)$data['duracion_horas'] : 0;
        $cupo = isset($data['cupo_maximo']) ? (int)$data['cupo_maximo'] : 30;

        $errors = [];
        if (empty($codigo)) {
            $errors['codigo'] = 'El código del curso es obligatorio.';
        }
        if (empty($nombre)) {
            $errors['nombre'] = 'El nombre de la asignatura es obligatorio.';
        }
        if ($duracion < 1) {
            $errors['duracion_horas'] = 'La duración debe ser de al menos 1 hora académica.';
        }
        if ($cupo < 1) {
            $errors['cupo_maximo'] = 'El cupo mínimo debe ser de 1 estudiante.';
        }

        if (!empty($errors)) {
            http_response_code(422);
            return jsonResponse(false, ['errors' => $errors], 'Por favor verifique los campos obligatorios.');
        }

        $db = getDB();

        // 2. Verificar código único
        $stmt = $db->prepare("SELECT id FROM cursos WHERE codigo = ?");
        $stmt->execute([$codigo]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return jsonResponse(false, null, 'El código de curso ya se encuentra registrado.');
        }

        // 3. Sanitización de fechas y claves foráneas opcionales
        $fechaInicio = !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
        $fechaFin = !empty($data['fecha_fin']) ? $data['fecha_fin'] : null;
        $facilitadorId = !empty($data['facilitador_id']) ? (int)$data['facilitador_id'] : null;
        $analistaId = !empty($data['analista_id']) ? (int)$data['analista_id'] : ($currentUser['id'] ?? null);
        $descripcion = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $area = !empty($data['area']) ? trim($data['area']) : null;
        $nivel = !empty($data['nivel']) ? $data['nivel'] : 'Basico';
        $estado = !empty($data['estado']) ? $data['estado'] : 'Planificado';

        // 4. Inserción con activo = 1 explícito
        $stmt = $db->prepare("
            INSERT INTO cursos (
                codigo, nombre, descripcion, duracion_horas, fecha_inicio, fecha_fin, 
                cupo_maximo, area, nivel, estado, facilitador_id, analista_id, activo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $codigo,
            $nombre,
            $descripcion,
            $duracion,
            $fechaInicio,
            $fechaFin,
            $cupo,
            $area,
            $nivel,
            $estado,
            $facilitadorId,
            $analistaId
        ]);

        $cursoId = (int)$db->lastInsertId();

        // 5. Registro seguro de auditoría
        if (class_exists('Auth') && method_exists('Auth', 'logActivity')) {
            $userId = $currentUser['id'] ?? null;
            $userName = trim(($currentUser['nombre'] ?? '') . ' ' . ($currentUser['apellidos'] ?? '')) ?: 'Usuario';
            Auth::logActivity($userId, $userName, 'CREATE', 'cursos', $cursoId, null, [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'area' => $area,
                'cupo_maximo' => $cupo
            ]);
        }

        return jsonResponse(true, [
            'message' => 'Curso registrado exitosamente.',
            'curso_id' => $cursoId
        ]);

    } catch (Throwable $e) {
        // Throwable captura tanto PDOException como Fatal Errors y TypeErrors de PHP 8
        error_log("[UPTEC ERROR CREAR CURSO] " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error interno del servidor: ' . $e->getMessage());
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

        // Si el analista/admin cambia manualmente el estado a 'Finalizado'
        if (isset($data['estado']) && $data['estado'] === 'Finalizado' && $anterior['estado'] !== 'Finalizado') {
            procesarEgresoCurso($db, $id);
            return jsonResponse(true, null, 'Curso finalizado y egresados procesados exitosamente');
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
// Activar curso (cambiar estado a En Curso)
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

        // Actualizar estado a En Curso
        $stmt = $db->prepare("UPDATE cursos SET estado = 'En Curso', verificado_por = ?, fecha_verificacion = NOW() WHERE id = ?");
        $stmt->execute([$currentUser['id'], $id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'ACTIVATE', 'cursos', $id, ['estado' => $curso['estado']], ['estado' => 'En Curso']);

        return jsonResponse(true, ['message' => 'Curso activado exitosamente']);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error activando curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al activar curso');
    }

}, [requireAnalystOrAbove()], 'curso-verificar');

// ============================================
// PUT /backend/api/api.php?endpoint=curso-desverificar&id=X
// Desverificar curso (cambiar estado a Planificado y limpiar verificación)
// ============================================
$router->register('PUT', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'curso-desverificar') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID requerido');
    }

    $currentUser = Auth::user();
    try {
        $db = getDB();
        // Obtener datos actuales para log
        $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        $curso = $stmt->fetch();
        if (!$curso) {
            http_response_code(404);
            return jsonResponse(false, null, 'Curso no encontrado');
        }

        // Resetear estado a Planificado y limpiar campos de verificación
        $stmt = $db->prepare("UPDATE cursos SET estado = 'Planificado', verificado_por = NULL, fecha_verificacion = NULL WHERE id = ?");
        $stmt->execute([$id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'UNVERIFY', 'cursos', $id, ['estado' => $curso['estado']], ['estado' => 'Planificado']);
        return jsonResponse(true, ['message' => 'Curso desverificado exitosamente']);
    } catch (PDOException $e) {
        error_log("[UPTEC] Error desverificando curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al desverificar curso');
    }
}, [requireAnalystOrAbove()], 'curso-desverificar');

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
    $validator->decimal($data['peso'] ?? 0, 'peso', 0.01, 100);
    $validator->integer($data['curso_id'] ?? 0, 'curso_id', 1);

    if ($validator->hasErrors()) {
        http_response_code(422);
        return jsonResponse(false, ['errors' => $validator->getErrors()]);
    }

    try {
        $db = getDB();

        // 1. SEGURIDAD: Verificar que el Facilitador sea el dueño del curso
        if ($currentUser['rol'] === 'Facilitador') {
            $stmtCheck = $db->prepare("SELECT id FROM cursos WHERE id = ? AND facilitador_id = ?");
            $stmtCheck->execute([$data['curso_id'], $currentUser['id']]);
            if (!$stmtCheck->fetch()) {
                http_response_code(403);
                return jsonResponse(false, null, 'No tiene permiso para agregar evaluaciones a este curso');
            }
        }

        // 2. BLINDAJE: Validar que la suma no supere el 100%
        $stmtPeso = $db->prepare("
            SELECT COALESCE(SUM(peso), 0) AS total_peso 
            FROM evaluaciones 
            WHERE curso_id = ? AND (activo = 1 OR activo IS NULL)
        ");
        $stmtPeso->execute([$data['curso_id']]);
        $acumuladoActual = (float)$stmtPeso->fetchColumn();

        $nuevoPeso = (float)$data['peso'];
        $disponible = 100.0 - $acumuladoActual;

        // Margen de 0.01 por temas de redondeo decimal
        if (($acumuladoActual + $nuevoPeso) > 100.01) {
            http_response_code(422);
            return jsonResponse(false, null, "La ponderación supera el 100%. Acumulado: {$acumuladoActual}%, disponible: {$disponible}%, intentó agregar: {$nuevoPeso}%");
        }

        // 3. INSERT explícito con activo = 1
        $stmt = $db->prepare("
            INSERT INTO evaluaciones (curso_id, nombre, descripcion, tipo, peso, fecha_evaluacion, orden, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $data['curso_id'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo'] ?? 'Otro',
            $nuevoPeso,
            $data['fecha_evaluacion'] ?: null,
            $data['orden'] ?? 0
        ]);

        $evalId = (int)$db->lastInsertId();

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'CREATE', 'evaluaciones', $evalId, null, $data);

        return jsonResponse(true, [
            'message' => 'Evaluación creada exitosamente',
            'evaluacion_id' => $evalId,
            'ponderacion_acumulada' => round($acumuladoActual + $nuevoPeso, 2),
            'disponible' => round(max(0, 100 - ($acumuladoActual + $nuevoPeso)), 2)
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error creando evaluacion: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al crear evaluacion: ' . $e->getMessage());
    }

}, [requireTeacherOrAbove()], 'evaluacion');

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
        verificarCursosVencidos($db);

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
// Lista las evaluaciones creadas para un curso especifico y sus porcentajes
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
        
        // Incluimos activo = 1 o activo IS NULL por compatibilidad
        $stmt = $db->prepare("
            SELECT id, nombre, tipo, peso, fecha_evaluacion, orden 
            FROM evaluaciones 
            WHERE curso_id = ? AND (activo = 1 OR activo IS NULL)
            ORDER BY orden ASC, id ASC
        ");
        $stmt->execute([$cursoId]);
        $evaluaciones = $stmt->fetchAll();

        // Calcular la suma acumulada directamente en el backend
        $totalPeso = 0.0;
        foreach ($evaluaciones as $eval) {
            $totalPeso += (float)$eval['peso'];
        }
        $disponible = max(0.0, 100.0 - $totalPeso);

        return jsonResponse(true, [
            'evaluaciones' => $evaluaciones,
            'ponderacion_acumulada' => round($totalPeso, 2),
            'total_peso' => round($totalPeso, 2), // Por si el frontend usa esta clave
            'disponible' => round($disponible, 2)
        ]);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error obteniendo evaluaciones del curso: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener evaluaciones');
    }

}, [requireTeacherOrAbove()], 'evaluaciones-curso');
// ============================================
// DELETE /backend/api/api.php?endpoint=evaluacion&id=X
// Eliminar evaluacion (Analista, Admin y Facilitador dueño del curso)
// ============================================
$router->register('DELETE', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'evaluacion') return null;

    $id = Sanitizer::int($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de evaluación requerido');
    }

    $currentUser = Auth::user();

    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT e.*, c.facilitador_id 
            FROM evaluaciones e 
            JOIN cursos c ON e.curso_id = c.id 
            WHERE e.id = ? AND e.activo = 1
        ");
        $stmt->execute([$id]);
        $eval = $stmt->fetch();

        if (!$eval) {
            http_response_code(404);
            return jsonResponse(false, null, 'Evaluación no encontrada');
        }

        // Si es Facilitador, verificar que sea el dueño del curso
        if ($currentUser['rol'] === 'Facilitador' && (int)$eval['facilitador_id'] !== (int)$currentUser['id']) {
            http_response_code(403);
            return jsonResponse(false, null, 'No tiene permiso para eliminar evaluaciones de este curso');
        }

        $stmt = $db->prepare("UPDATE evaluaciones SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);

        Auth::logActivity($currentUser['id'], $currentUser['nombre'] . ' ' . $currentUser['apellidos'], 'DELETE', 'evaluaciones', $id, $eval, ['activo' => 0]);

        return jsonResponse(true, ['message' => 'Evaluación eliminada exitosamente']);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error eliminando evaluación: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al eliminar evaluación');
    }

}, [requireTeacherOrAbove()], 'evaluacion');

// ============================================
// GET /backend/api/api.php?endpoint=egresados-curso
// Lista los egresados de un curso específico (Facilitador, Analista, Admin)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'egresados-curso') return null;

    $cursoId = Sanitizer::int($_GET['curso_id'] ?? 0);
    $user = Auth::user();

    if (!$cursoId) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de curso requerido');
    }

    try {
        $db = getDB();

        // Si es Facilitador, validar que sea el docente asignado a ese curso
        if ($user['rol'] === 'Facilitador') {
            $stmtPermiso = $db->prepare("SELECT id FROM cursos WHERE id = ? AND facilitador_id = ?");
            $stmtPermiso->execute([$cursoId, $user['id']]);
            if (!$stmtPermiso->fetch()) {
                http_response_code(403);
                return jsonResponse(false, null, 'No tiene permiso para ver los egresados de este curso');
            }
        }

        $stmt = $db->prepare("
            SELECT e.*, 
                   u.cedula, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante, u.correo,
                   c.codigo AS codigo_curso, c.nombre AS nombre_curso
            FROM egresados e
            JOIN usuarios u ON e.usuario_id = u.id
            JOIN cursos c ON e.curso_id = c.id
            WHERE e.curso_id = ?
            ORDER BY e.nota_final DESC, u.apellidos ASC
        ");
        $stmt->execute([$cursoId]);
        $egresados = $stmt->fetchAll();

        return jsonResponse(true, [
            'egresados' => $egresados,
            'total' => count($egresados)
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener lista de egresados: ' . $e->getMessage());
    }
}, [requireTeacherOrAbove()], 'egresados-curso');

// ============================================
// GET /backend/api/api.php?endpoint=egresados
// Lista general de egresados con filtros (Analista y Administrador)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'egresados') return null;

    $cursoId = $_GET['curso_id'] ?? '';
    $estado = $_GET['estado'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    try {
        $db = getDB();

        $sql = "
            SELECT e.*, 
                   u.cedula, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante, u.correo,
                   c.codigo AS codigo_curso, c.nombre AS nombre_curso
            FROM egresados e
            JOIN usuarios u ON e.usuario_id = u.id
            JOIN cursos c ON e.curso_id = c.id
            WHERE 1=1
        ";
        $values = [];

        if ($cursoId && is_numeric($cursoId)) {
            $sql .= " AND e.curso_id = ?";
            $values[] = (int)$cursoId;
        }

        if ($estado && in_array($estado, ['Aprobado', 'Reprobado'])) {
            $sql .= " AND e.estado_academico = ?";
            $values[] = $estado;
        }

        if ($buscar) {
            $sql .= " AND (u.cedula LIKE ? OR u.nombre LIKE ? OR u.apellidos LIKE ? OR c.nombre LIKE ?)";
            $search = "%{$buscar}%";
            $values = array_merge($values, [$search, $search, $search, $search]);
        }

        $sql .= " ORDER BY e.fecha_egreso DESC, c.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $egresados = $stmt->fetchAll();

        return jsonResponse(true, [
            'egresados' => $egresados,
            'total' => count($egresados)
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener egresados');
    }
}, [requireAnalystOrAbove()], 'egresados');
