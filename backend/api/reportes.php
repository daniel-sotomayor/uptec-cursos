<?php
/**
 * UPTEC - API Endpoints de Reportes
 * Estadisticas, informes, auditoria, resumen academico
 */

// ============================================
// GET /backend/api/api.php?endpoint=resumen
// Resumen del dashboard por rol
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'resumen') return null;

    $user = Auth::user();

    try {
        $db = getDB();
        $resumen = [];

        // Datos comunes
        $resumen['usuario'] = $user;

        if ($user['rol'] === 'Administrador') {
            // Totales generales
            $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Participante' AND activo = 1");
            $resumen['total_estudiantes'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Facilitador' AND activo = 1");
            $resumen['total_facilitadores'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Analista' AND activo = 1");
            $resumen['total_analistas'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1");
            $resumen['total_cursos'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM inscripciones WHERE estado != 'Abandonado'");
            $resumen['total_inscripciones'] = $stmt->fetchColumn();

            // Cursos por estado
            $stmt = $db->query("
                SELECT estado, COUNT(*) AS cantidad
                FROM cursos WHERE activo = 1 GROUP BY estado
            ");
            $resumen['cursos_por_estado'] = $stmt->fetchAll();

            // Cursos recientes
            $stmt = $db->query("
                SELECT c.*, CONCAT(f.nombre, ' ', f.apellidos) AS facilitador
                FROM cursos c
                LEFT JOIN usuarios f ON c.facilitador_id = f.id
                WHERE c.activo = 1
                ORDER BY c.creado_en DESC LIMIT 5
            ");
            $resumen['cursos_recientes'] = $stmt->fetchAll();

            // Usuarios recientes
            $stmt = $db->query("
                SELECT id, cedula, CONCAT(nombre, ' ', apellidos) AS nombre_completo, rol, creado_en
                FROM usuarios ORDER BY creado_en DESC LIMIT 5
            ");
            $resumen['usuarios_recientes'] = $stmt->fetchAll();

            // Logs recientes
            $stmt = $db->query("
                SELECT usuario_nombre, accion, tabla_afectada, fecha_hora
                FROM logs_actividad ORDER BY fecha_hora DESC LIMIT 10
            ");
            $resumen['actividad_reciente'] = $stmt->fetchAll();

            // ============================================
            // NUEVAS METRICAS: Estudiantes y Cursos por Area
            // ============================================

            // Estudiantes por area (carrera)
            $stmt = $db->query("
                SELECT 
                    u.area,
                    COUNT(*) AS total_estudiantes
                FROM usuarios u
                WHERE u.rol = 'Participante' 
                  AND u.activo = 1
                  AND u.area IS NOT NULL
                GROUP BY u.area
                ORDER BY total_estudiantes DESC
            ");
            $resumen['estudiantes_por_area'] = $stmt->fetchAll();

            // Cursos con estudiantes por area
            $stmt = $db->query("
                SELECT 
                    c.area,
                    c.id,
                    c.codigo,
                    c.nombre,
                    c.estado,
                    COUNT(i.id) AS total_estudiantes,
                    CONCAT(f.nombre, ' ', f.apellidos) AS facilitador
                FROM cursos c
                LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado != 'Abandonado'
                LEFT JOIN usuarios f ON c.facilitador_id = f.id
                WHERE c.activo = 1
                  AND c.area IS NOT NULL
                GROUP BY c.id, c.area, c.codigo, c.nombre, c.estado, f.nombre, f.apellidos
                ORDER BY c.area, total_estudiantes DESC
            ");
            $resumen['cursos_con_estudiantes_por_area'] = $stmt->fetchAll();

            // Top 5 cursos con mas estudiantes
            $stmt = $db->query("
                SELECT 
                    c.codigo,
                    c.nombre,
                    c.area,
                    COUNT(i.id) AS total_estudiantes,
                    CONCAT(f.nombre, ' ', f.apellidos) AS facilitador
                FROM cursos c
                LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado != 'Abandonado'
                LEFT JOIN usuarios f ON c.facilitador_id = f.id
                WHERE c.activo = 1
                GROUP BY c.id, c.codigo, c.nombre, c.area, f.nombre, f.apellidos
                HAVING total_estudiantes > 0
                ORDER BY total_estudiantes DESC
                LIMIT 5
            ");
            $resumen['top_cursos'] = $stmt->fetchAll();
        }

        if ($user['rol'] === 'Analista') {
            $stmt = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1");
            $resumen['total_cursos'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM cursos WHERE estado = 'Planificado' AND activo = 1");
            $resumen['cursos_planificados'] = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM cursos WHERE estado = 'En Curso' AND activo = 1");
            $resumen['cursos_activos'] = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM cursos WHERE (estado = 'Verificado' OR verificado_por IS NOT NULL) AND activo = 1");
            $resumen['cursos_verificados'] = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Facilitador' AND activo = 1");
            $resumen['total_facilitadores'] = $stmt->fetchColumn();

            $stmt = $db->query("
                SELECT COUNT(*) FROM inscripciones i
                JOIN cursos c ON i.curso_id = c.id WHERE c.activo = 1
            ");
            $resumen['total_inscripciones'] = $stmt->fetchColumn();

            // Cursos por area
            $stmt = $db->query("
                SELECT area, COUNT(*) AS cantidad FROM cursos WHERE activo = 1 AND area IS NOT NULL GROUP BY area
            ");
            $resumen['cursos_por_area'] = $stmt->fetchAll();

            // Cursos sin facilitador
            $stmt = $db->query("
                SELECT id, codigo, nombre, estado FROM cursos
                WHERE facilitador_id IS NULL AND activo = 1 AND estado = 'Planificado'
            ");
            $resumen['cursos_sin_facilitador'] = $stmt->fetchAll();
            $resumen['total_sin_facilitador'] = count($resumen['cursos_sin_facilitador']);
        }

        if ($user['rol'] === 'Facilitador') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM cursos WHERE facilitador_id = ? AND activo = 1");
            $stmt->execute([$user['id']]);
            $resumen['mis_cursos'] = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM inscripciones i
                JOIN cursos c ON i.curso_id = c.id
                WHERE c.facilitador_id = ? AND i.estado != 'Abandonado'
            ");
            $stmt->execute([$user['id']]);
            $resumen['mis_estudiantes'] = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND estado != 'Abandonado') AS total_inscritos
                FROM cursos c
                WHERE c.facilitador_id = ? AND c.activo = 1
                ORDER BY c.fecha_inicio DESC
            ");
            $stmt->execute([$user['id']]);
            $resumen['cursos_activos'] = $stmt->fetchAll();
        }

        if ($user['rol'] === 'Participante') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM inscripciones WHERE usuario_id = ? AND estado != 'Abandonado'");
            $stmt->execute([$user['id']]);
            $resumen['mis_cursos'] = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM inscripciones WHERE usuario_id = ? AND estado = 'Completado'
            ");
            $stmt->execute([$user['id']]);
            $resumen['cursos_completados'] = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT ROUND(AVG(nota_final), 2) FROM inscripciones
                WHERE usuario_id = ? AND nota_final IS NOT NULL
            ");
            $stmt->execute([$user['id']]);
            $resumen['promedio_general'] = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("
                SELECT i.*, c.codigo, c.nombre, c.area
                FROM inscripciones i
                JOIN cursos c ON i.curso_id = c.id
                WHERE i.usuario_id = ? AND i.estado != 'Abandonado'
                ORDER BY i.fecha_inscripcion DESC
            ");
            $stmt->execute([$user['id']]);
            $resumen['inscripciones'] = $stmt->fetchAll();
        }

        return jsonResponse(true, $resumen);

    } catch (PDOException $e) {
        error_log("[UPTEC] Error en resumen: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener resumen');
    }

}, [requireAuth()], 'resumen');

// ============================================
// GET /backend/api/api.php?endpoint=estadisticas
// Estadisticas globales (Admin)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'estadisticas') return null;

    try {
        $db = getDB();
        $stats = [];

        // Promedio general de notas
        $stmt = $db->query("SELECT ROUND(AVG(nota_final), 2) FROM inscripciones WHERE nota_final IS NOT NULL");
        $stats['promedio_general'] = $stmt->fetchColumn() ?: 0;

        // Tasa de aprobacion
        $stmt = $db->query("SELECT COUNT(*) FROM inscripciones WHERE nota_final IS NOT NULL AND nota_final >= 10");
        $aprobados = $stmt->fetchColumn();
        $stmt = $db->query("SELECT COUNT(*) FROM inscripciones WHERE nota_final IS NOT NULL");
        $total = $stmt->fetchColumn();
        $stats['tasa_aprobacion'] = $total > 0 ? round(($aprobados / $total) * 100, 1) : 0;

        // Cursos por area
        $stmt = $db->query("
            SELECT COALESCE(NULLIF(area, ''), 'General') AS area, COUNT(*) AS total FROM cursos WHERE activo = 1 GROUP BY area
        ");
        $stats['cursos_por_area'] = $stmt->fetchAll();

        // Inscripciones por mes
        $stmt = $db->query("
            SELECT DATE_FORMAT(fecha_inscripcion, '%Y-%m') AS mes, COUNT(*) AS total
            FROM inscripciones GROUP BY mes ORDER BY mes DESC LIMIT 6
        ");
        $stats['inscripciones_por_mes'] = $stmt->fetchAll();

        // Top facilitadores
        $stmt = $db->query("
            SELECT CONCAT(u.nombre, ' ', u.apellidos) AS nombre, COUNT(*) AS cursos_impartidos
            FROM cursos c JOIN usuarios u ON c.facilitador_id = u.id
            WHERE c.activo = 1 GROUP BY c.facilitador_id ORDER BY cursos_impartidos DESC LIMIT 5
        ");
        $stats['top_facilitadores'] = $stmt->fetchAll();

        return jsonResponse(true, $stats);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener estadisticas');
    }

}, [requireAdmin()], 'estadisticas');

// ============================================
// GET /backend/api/api.php?endpoint=auditoria
// Logs de auditoria completa (Admin)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'auditoria') return null;

    $accion = $_GET['accion'] ?? '';
    $tabla = $_GET['tabla'] ?? '';
    $fechaDesde = $_GET['fecha_desde'] ?? '';
    $fechaHasta = $_GET['fecha_hasta'] ?? '';

    try {
        $db = getDB();

        $sql = "SELECT * FROM logs_actividad WHERE 1=1";
        $values = [];

        if ($accion) {
            $sql .= " AND accion = ?";
            $values[] = $accion;
        }

        if ($tabla) {
            $sql .= " AND tabla_afectada = ?";
            $values[] = $tabla;
        }

        if ($fechaDesde) {
            $sql .= " AND fecha_hora >= ?";
            $values[] = $fechaDesde . ' 00:00:00';
        }

        if ($fechaHasta) {
            $sql .= " AND fecha_hora <= ?";
            $values[] = $fechaHasta . ' 23:59:59';
        }

        $sql .= " ORDER BY fecha_hora DESC LIMIT 500";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        return jsonResponse(true, ['logs' => $stmt->fetchAll(), 'total' => $stmt->rowCount()]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener auditoria');
    }

}, [requireAdmin()], 'auditoria');

// GET /backend/api/api.php?endpoint=certificado
// Generar certificado de aprobacion (participante)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'certificado') return null;

    $inscripcionId = Sanitizer::int($_GET['id'] ?? 0);
    $user = Auth::user();

    if (!$inscripcionId) {
        http_response_code(400);
        return jsonResponse(false, null, 'ID de inscripcion requerido');
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT i.*, c.codigo, c.nombre AS curso_nombre, c.duracion_horas,
                   CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador,
                   CONCAT(u.nombre, ' ', u.apellidos) AS nombre_estudiante,
                   u.cedula
            FROM inscripciones i
            JOIN cursos c ON i.curso_id = c.id
            JOIN usuarios u ON i.usuario_id = u.id
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE i.id = ? AND i.estado = 'Completado'
        ");
        $stmt->execute([$inscripcionId]);
        $certificado = $stmt->fetch();

        if (!$certificado) {
            http_response_code(404);
            return jsonResponse(false, null, 'Certificado no encontrado o curso no completado');
        }

        // Verificar que sea el participante o admin
        if ($user['rol'] === 'Participante' && $certificado['usuario_id'] != $user['id']) {
            http_response_code(403);
            return jsonResponse(false, null, 'No tiene acceso a este certificado');
        }

        return jsonResponse(true, ['certificado' => $certificado]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al generar certificado');
    }

}, [requireAuth()], 'certificado');

// ============================================
// GET /backend/api/api.php?endpoint=mis-certificados
// Lista certificados disponibles del participante
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'mis-certificados') return null;

    $user = Auth::user();

    if (!$user) {
        http_response_code(401);
        return jsonResponse(false, null, 'No autenticado');
    }

    try {
        $db = getDB();

        // Certificados desde tabla egresados (cursos finalizados formalmente)
        $stmt = $db->prepare("
            SELECT
                e.id, e.curso_id, e.nota_final,
                e.fecha_egreso AS fecha_completado,
                e.codigo_certificado AS certificado_codigo,
                c.codigo, c.nombre AS curso_nombre, c.duracion_horas,
                CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador
            FROM egresados e
            JOIN cursos c ON e.curso_id = c.id
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE e.usuario_id = ? AND e.estado_academico = 'Aprobado'
            ORDER BY e.fecha_egreso DESC
        ");
        $stmt->execute([$user['id']]);
        $certificados = $stmt->fetchAll();

        return jsonResponse(true, ['certificados' => $certificados]);

    } catch (PDOException $e) {
        http_response_code(500);
        return jsonResponse(false, null, 'Error al obtener certificados');
    }

}, [requireAuth()], 'mis-certificados');

// ============================================
// GET /backend/api/api.php?endpoint=mis-notas-pdf
// Descargar notas del participante en PDF
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'mis-notas-pdf') return null;

    $user = Auth::user();

    if (!$user) {
        http_response_code(401);
        return jsonResponse(false, null, 'No autenticado');
    }

    require_once __DIR__ . '/../libs/SimplePDF.php';

    try {
        $db = getDB();

        // Obtener datos del usuario
        $stmt = $db->prepare("
            SELECT nombre, apellidos, cedula, correo, area
            FROM usuarios WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $estudiante = $stmt->fetch();

        // Obtener inscripciones con notas
        $stmt = $db->prepare("
            SELECT 
                c.codigo,
                c.nombre AS curso_nombre,
                c.duracion_horas,
                c.area,
                i.fecha_inscripcion,
                i.fecha_completado,
                i.estado,
                i.nota_final,
                CONCAT(f.nombre, ' ', f.apellidos) AS facilitador
            FROM inscripciones i
            JOIN cursos c ON i.curso_id = c.id
            LEFT JOIN usuarios f ON c.facilitador_id = f.id
            WHERE i.usuario_id = ?
            ORDER BY i.fecha_inscripcion DESC
        ");
        $stmt->execute([$user['id']]);
        $inscripciones = $stmt->fetchAll();

        // Generar PDF
        $pdf = new SimplePDF();
        $pdf->addPage();

        // Encabezado
        $pdf->setFont('Helvetica-Bold', 16);
        $pdf->text(20, 30, 'UPTEC - Sistema de Control de Cursos');
        $pdf->setFont('Helvetica', 12);
        $pdf->text(20, 40, 'Reporte de Notas del Estudiante');

        // Datos del estudiante
        $pdf->setFont('Helvetica-Bold', 12);
        $pdf->text(20, 55, 'Datos del Estudiante:');
        $pdf->setFont('Helvetica', 10);
        $pdf->text(20, 62, "Nombre: {$estudiante['nombre']} {$estudiante['apellidos']}");
        $pdf->text(20, 68, "Cedula: {$estudiante['cedula']}");
        $pdf->text(20, 74, "Correo: {$estudiante['correo']}");
        $pdf->text(20, 80, "Area: " . ($estudiante['area'] ?: 'No especificada'));

        // Tabla de cursos
        $pdf->setFont('Helvetica-Bold', 12);
        $pdf->text(20, 95, 'Cursos Inscritos:');

        $y = 105;
        $pdf->setFont('Helvetica-Bold', 9);
        $pdf->text(20, $y, 'Curso');
        $pdf->text(80, $y, 'Area');
        $pdf->text(110, $y, 'Estado');
        $pdf->text(140, $y, 'Nota');
        $pdf->text(160, $y, 'Facilitador');

        $y += 8;
        $pdf->setFont('Helvetica', 8);

        foreach ($inscripciones as $inscripcion) {
            if ($y > 270) {
                $pdf->addPage();
                $y = 30;
            }

            $pdf->text(20, $y, substr($inscripcion['curso_nombre'], 0, 30));
            $pdf->text(80, $y, substr($inscripcion['area'], 0, 15));
            $pdf->text(110, $y, $inscripcion['estado']);
            $pdf->text(140, $y, $inscripcion['nota_final'] ? number_format($inscripcion['nota_final'], 1) : '--');
            $pdf->text(160, $y, substr($inscripcion['facilitador'], 0, 20));
            $y += 6;
        }

        // Pie de pagina
        $pdf->setFont('Helvetica', 8);
        $pdf->text(20, 285, 'Generado el: ' . date('d/m/Y H:i:s'));

        $filename = "notas_{$estudiante['cedula']}.pdf";
        $pdf->output($filename, 'D');
        exit;

    } catch (Exception $e) {
        error_log("[UPTEC] Error generando PDF de notas: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al generar PDF');
    }

}, [requireAuth()], 'mis-notas-pdf');

// ============================================
// ============================================
// GET /backend/api/api.php?endpoint=reportes-pdf
// Descargar o Previsualizar reportes en PDF (Admin, Analista, Facilitador)
// ============================================
$router->register('GET', '/backend/api/api.php', function($params) {
    $endpoint = $_GET['endpoint'] ?? '';
    if ($endpoint !== 'reportes-pdf') return null;

    $user = Auth::user();

    if (!$user) {
        http_response_code(401);
        return jsonResponse(false, null, 'No autenticado');
    }

    // Solo Admin, Analista y Facilitador pueden descargar o previsualizar reportes
    if (!in_array($user['rol'], ['Administrador', 'Analista', 'Facilitador'])) {
        http_response_code(403);
        return jsonResponse(false, null, 'Permiso denegado');
    }

    $preview = isset($_GET['preview']) && ($_GET['preview'] == '1' || $_GET['preview'] === 'true');
    $dest = $preview ? 'I' : 'D';

    require_once __DIR__ . '/../libs/SimplePDF.php';

    try {
        $db = getDB();
        $tipo = $user['rol'];
        $pdf = new SimplePDF();

        // Funciones auxiliares de diseno institucional
        $drawHeader = function($pdf, $title, $subtitle, $user, $pageNum, $totalPages) {
            $pdf->setColor(26, 54, 93); // Navy #1A365D
            $pdf->rect(0, 0, 215.9, 4, true, false);

            $pdf->setColor(49, 130, 206); // Blue #3182CE
            $pdf->rect(0, 4, 215.9, 1, true, false);

            $pdf->setColor(100, 116, 139);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->textCenter(10, 'REPÚBLICA BOLIVARIANA DE VENEZUELA');
            $pdf->textCenter(13.5, 'MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN UNIVERSITARIA');

            $pdf->setColor(26, 54, 93);
            $pdf->setFont('Helvetica-Bold', 11);
            $pdf->textCenter(18.5, 'UNIVERSIDAD POLITÉCNICA TERRITORIAL DE CARACAS «MARISCAL SUCRE»');

            $pdf->setColor(71, 85, 105);
            $pdf->setFont('Helvetica', 8);
            $pdf->textCenter(23, 'SISTEMA DE GESTIÓN Y CONTROL ACADÉMICO DE CURSOS (UPTECMS)');

            $pdf->setStrokeColor(203, 213, 225);
            $pdf->setLineWidth(0.3);
            $pdf->line(15, 26, 200.9, 26);

            if ($pageNum === 1) {
                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica-Bold', 13);
                $pdf->text(15, 33, $title);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 8.5);
                $pdf->text(15, 37.5, $subtitle);

                $pdf->setColor(248, 250, 252);
                $pdf->setStrokeColor(226, 232, 240);
                $pdf->rect(15, 41, 185.9, 14, true, true);

                $pdf->setColor(26, 54, 93);
                $pdf->rect(15, 41, 2.5, 14, true, false);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 7.5);
                $pdf->text(21, 46, 'EMISOR / CARGO:');
                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->text(48, 46, "{$user['nombre']} {$user['apellidos']} ({$user['rol']})");

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 7.5);
                $pdf->text(21, 51.5, 'CÉDULA / USUARIO:');
                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->text(48, 51.5, "V-{$user['cedula']}  |  {$user['correo']}");

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 7.5);
                $pdf->text(125, 46, 'FECHA DE EMISIÓN:');
                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->text(155, 46, date('d/m/Y - h:i A'));

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 7.5);
                $pdf->text(125, 51.5, 'CÓDIGO DE INFORME:');
                $pdf->setColor(26, 54, 93);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->text(155, 51.5, 'UPTEC-REP-' . date('Ymd') . '-' . sprintf('%03d', $user['id']));
            } else {
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 8);
                $pdf->text(15, 31, "Informe General de Gestión Académica - UPTECMS");
                $pdf->textRight(200.9, 31, date('d/m/Y') . " | Pág. $pageNum de $totalPages");
                
                $pdf->setStrokeColor(226, 232, 240);
                $pdf->setLineWidth(0.2);
                $pdf->line(15, 33, 200.9, 33);
            }
        };

        $drawFooter = function($pdf, $pageNum, $totalPages) {
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->setLineWidth(0.3);
            $pdf->line(15, 269, 200.9, 269);

            $pdf->setColor(148, 163, 184);
            $pdf->setFont('Helvetica', 7);
            $pdf->text(15, 273, 'Sistema de Control y Gestión Curricular UPTECMS - Documento Oficial de Consulta y Auditoría');
            $pdf->textRight(200.9, 273, "Página $pageNum de $totalPages");
        };

        $drawSectionTitle = function($pdf, $x, $y, $number, $title) {
            $pdf->setColor(26, 54, 93);
            $pdf->rect($x, $y - 3.2, 3, 4, true, false);

            $pdf->setColor(26, 54, 93);
            $pdf->setFont('Helvetica-Bold', 9.5);
            $pdf->text($x + 5, $y, "$number. " . mb_strtoupper($title, 'UTF-8'));
        };

        if ($tipo === 'Administrador') {
            // Totales de usuarios
            $totalEstudiantes = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Participante' AND activo = 1")->fetchColumn() ?: 0;
            $totalFacilitadores = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Facilitador' AND activo = 1")->fetchColumn() ?: 0;
            $totalAnalistas = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Analista' AND activo = 1")->fetchColumn() ?: 0;

            // Cursos
            $totalCursos = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1")->fetchColumn() ?: 0;
            $cursosEnCurso = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'En Curso'")->fetchColumn() ?: 0;
            $cursosPlanificados = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'Planificado'")->fetchColumn() ?: 0;
            $cursosFinalizados = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'Finalizado'")->fetchColumn() ?: 0;

            // Inscripciones
            $totalInscripciones = $db->query("SELECT COUNT(*) FROM inscripciones WHERE estado != 'Abandonado'")->fetchColumn() ?: 0;

            // Notas y rendimiento
            $promedioGeneral = $db->query("SELECT ROUND(AVG(nota_final), 2) FROM inscripciones WHERE nota_final IS NOT NULL")->fetchColumn() ?: '0.00';
            $aprobados = $db->query("SELECT COUNT(*) FROM inscripciones WHERE nota_final IS NOT NULL AND nota_final >= 10")->fetchColumn() ?: 0;
            $conNota = $db->query("SELECT COUNT(*) FROM inscripciones WHERE nota_final IS NOT NULL")->fetchColumn() ?: 0;
            $tasaAprobacion = $conNota > 0 ? round(($aprobados / $conNota) * 100, 1) : 0;

            // Cursos por área
            $cursosPorArea = $db->query("
                SELECT COALESCE(NULLIF(TRIM(c.area), ''), 'General') AS area,
                       COUNT(DISTINCT c.id) AS total_cursos,
                       COUNT(i.id) AS total_estudiantes
                FROM cursos c
                LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado != 'Abandonado'
                WHERE c.activo = 1
                GROUP BY area
                ORDER BY total_cursos DESC, total_estudiantes DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Top facilitadores
            $topFacilitadores = $db->query("
                SELECT CONCAT(u.nombre, ' ', u.apellidos) AS nombre,
                       u.cedula,
                       COUNT(DISTINCT c.id) AS cursos_impartidos,
                       COUNT(i.id) AS total_estudiantes
                FROM cursos c
                JOIN usuarios u ON c.facilitador_id = u.id
                LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado != 'Abandonado'
                WHERE c.activo = 1
                GROUP BY c.facilitador_id
                ORDER BY cursos_impartidos DESC, total_estudiantes DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Top cursos más demandados
            $topCursos = $db->query("
                SELECT c.codigo, c.nombre,
                       COALESCE(NULLIF(TRIM(c.area), ''), 'General') AS area,
                       c.estado, c.cupo_maximo,
                       COUNT(i.id) AS total_inscritos,
                       CONCAT(f.nombre, ' ', f.apellidos) AS facilitador
                FROM cursos c
                LEFT JOIN usuarios f ON c.facilitador_id = f.id
                LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.estado != 'Abandonado'
                WHERE c.activo = 1
                GROUP BY c.id
                ORDER BY total_inscritos DESC, c.fecha_inicio DESC
                LIMIT 6
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Inscripciones por mes
            $inscripcionesMes = $db->query("
                SELECT DATE_FORMAT(fecha_inscripcion, '%Y-%m') AS mes, COUNT(*) AS total
                FROM inscripciones
                WHERE estado != 'Abandonado'
                GROUP BY mes
                ORDER BY mes DESC
                LIMIT 6
            ")->fetchAll(PDO::FETCH_ASSOC);

            // -------------------------------------------------------------
            // PÁGINA 1: RESUMEN EJECUTIVO Y DISTRIBUCIÓN POR ÁREA
            // -------------------------------------------------------------
            $pdf->addPage('P');
            $drawHeader($pdf, 'INFORME GENERAL DE RENDIMIENTO Y GESTIÓN', 'Consolidado institucional del programa académico y estado del sistema', $user, 1, 2);

            // Sección 1: KPIs
            $y = 61;
            $drawSectionTitle($pdf, 15, $y, '1', 'Indicadores Clave de Rendimiento (KPIs)');

            $kpiY = $y + 4;
            $cardW = 43.5;
            $cardH = 20;
            $gap = 4.0;

            $kpis = [
                [
                    'label' => 'PROMEDIO GENERAL',
                    'value' => number_format((float)$promedioGeneral, 2) . ' pts',
                    'sub'   => 'Escala 0 a 20 puntos',
                    'color' => [26, 54, 93],
                    'top'   => [49, 130, 206]
                ],
                [
                    'label' => 'TASA APROBACIÓN',
                    'value' => $tasaAprobacion . '%',
                    'sub'   => ($conNota > 0 ? "$aprobados de $conNota evaluados" : 'Sin evaluaciones'),
                    'color' => [39, 103, 73],
                    'top'   => [34, 197, 94]
                ],
                [
                    'label' => 'ESTUDIANTES ACTIVOS',
                    'value' => (string)$totalEstudiantes,
                    'sub'   => 'Participantes en sistema',
                    'color' => [15, 23, 42],
                    'top'   => [217, 119, 6]
                ],
                [
                    'label' => 'CURSOS REGISTRADOS',
                    'value' => (string)$totalCursos,
                    'sub'   => "$cursosEnCurso en curso / $cursosFinalizados finaliz.",
                    'color' => [26, 54, 93],
                    'top'   => [99, 102, 241]
                ]
            ];

            foreach ($kpis as $idx => $kpi) {
                $kx = 15 + ($idx * ($cardW + $gap));
                $pdf->setColor(248, 250, 252);
                $pdf->setStrokeColor(226, 232, 240);
                $pdf->rect($kx, $kpiY, $cardW, $cardH, true, true);

                $pdf->setColor($kpi['top'][0], $kpi['top'][1], $kpi['top'][2]);
                $pdf->rect($kx, $kpiY, $cardW, 2, true, false);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Bold', 6.5);
                $pdf->text($kx + 3, $kpiY + 6.5, $kpi['label']);

                $pdf->setColor($kpi['color'][0], $kpi['color'][1], $kpi['color'][2]);
                $pdf->setFont('Helvetica-Bold', 12);
                $pdf->text($kx + 3, $kpiY + 12.5, $kpi['value']);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 6.5);
                $pdf->text($kx + 3, $kpiY + 17, $kpi['sub']);
            }

            // Sección 2: Estado general
            $y = 90;
            $drawSectionTitle($pdf, 15, $y, '2', 'Estado de la Oferta Académica y Comunidad');

            $boxY = $y + 4;
            $pdf->setColor(255, 255, 255);
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->rect(15, $boxY, 185.9, 16, true, true);

            $estadoItems = [
                ['label' => 'Cursos en Curso', 'val' => $cursosEnCurso, 'color' => [22, 101, 52]],
                ['label' => 'Cursos Planificados', 'val' => $cursosPlanificados, 'color' => [30, 64, 175]],
                ['label' => 'Cursos Finalizados', 'val' => $cursosFinalizados, 'color' => [100, 116, 139]],
                ['label' => 'Docentes Facilitadores', 'val' => $totalFacilitadores, 'color' => [15, 23, 42]],
                ['label' => 'Inscripciones Totales', 'val' => $totalInscripciones, 'color' => [180, 83, 9]]
            ];

            $colW = 185.9 / count($estadoItems);
            foreach ($estadoItems as $idx => $it) {
                $ix = 15 + ($idx * $colW);
                if ($idx > 0) {
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->line($ix, $boxY + 2, $ix, $boxY + 14);
                }
                $pdf->setColor($it['color'][0], $it['color'][1], $it['color'][2]);
                $pdf->setFont('Helvetica-Bold', 11);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 7, (string)$it['val']);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 6.8);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 12, $it['label']);
            }

            // Sección 3: Distribución por área
            $y = 115;
            $drawSectionTitle($pdf, 15, $y, '3', 'Distribución de Cursos y Participantes por Área Académica');

            $tableY = $y + 4;
            $rowH = 6.2;
            $pdf->setColor(26, 54, 93);
            $pdf->rect(15, $tableY, 185.9, $rowH, true, false);

            $pdf->setColor(255, 255, 255);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->text(18, $tableY + 4.3, '#');
            $pdf->text(28, $tableY + 4.3, 'ÁREA FORMATIVA / ESPECIALIDAD');
            $pdf->text(105, $tableY + 4.3, 'CURSOS OFERTADOS');
            $pdf->text(145, $tableY + 4.3, 'ESTUDIANTES REGISTRADOS');
            $pdf->text(182, $tableY + 4.3, '% TOTAL');

            $curY = $tableY + $rowH;
            $totalCursosCalc = array_sum(array_column($cursosPorArea, 'total_cursos')) ?: 1;
            $totalEstudCalc = array_sum(array_column($cursosPorArea, 'total_estudiantes')) ?: 0;

            foreach ($cursosPorArea as $idx => $area) {
                $bg = ($idx % 2 === 0) ? 255 : 248;
                $pdf->setColor($bg, $bg, ($bg === 255 ? 255 : 252));
                $pdf->setStrokeColor(226, 232, 240);
                $pdf->rect(15, $curY, 185.9, $rowH, true, true);

                $pct = round(($area['total_cursos'] / $totalCursosCalc) * 100, 1);

                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Bold', 7.5);
                $pdf->text(18, $curY + 4.3, (string)($idx + 1));

                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->text(28, $curY + 4.3, $area['area']);

                $pdf->setColor(26, 54, 93);
                $pdf->setFont('Helvetica', 8);
                $pdf->text(115, $curY + 4.3, "{$area['total_cursos']} asignaturas");

                $pdf->setColor(15, 23, 42);
                $pdf->setFont('Helvetica', 8);
                $pdf->text(155, $curY + 4.3, "{$area['total_estudiantes']} inscritos");

                $pdf->setColor(71, 85, 105);
                $pdf->setFont('Helvetica-Bold', 7.5);
                $pdf->text(184, $curY + 4.3, "{$pct}%");

                $curY += $rowH;
            }

            // Fila Total
            $pdf->setColor(241, 245, 249);
            $pdf->setStrokeColor(203, 213, 225);
            $pdf->rect(15, $curY, 185.9, $rowH + 1, true, true);

            $pdf->setColor(15, 23, 42);
            $pdf->setFont('Helvetica-Bold', 8);
            $pdf->text(28, $curY + 4.8, 'TOTAL GENERAL CONSOLIDADO');
            $pdf->setColor(26, 54, 93);
            $pdf->text(115, $curY + 4.8, "$totalCursosCalc asignaturas");
            $pdf->setColor(15, 23, 42);
            $pdf->text(155, $curY + 4.8, "$totalEstudCalc inscritos");
            $pdf->setColor(71, 85, 105);
            $pdf->text(184, $curY + 4.8, '100%');

            $drawFooter($pdf, 1, 2);

            // -------------------------------------------------------------
            // PÁGINA 2: CARGA DOCENTE, CURSOS POPULARES, MESES Y FIRMAS
            // -------------------------------------------------------------
            $pdf->addPage('P');
            $drawHeader($pdf, '', '', $user, 2, 2);

            // Sección 4: Top facilitadores
            $y = 38;
            $drawSectionTitle($pdf, 15, $y, '4', 'Docentes Facilitadores con Mayor Carga Académica');

            $tableY = $y + 4;
            $rowH = 6.2;
            $pdf->setColor(26, 54, 93);
            $pdf->rect(15, $tableY, 185.9, $rowH, true, false);

            $pdf->setColor(255, 255, 255);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->text(18, $tableY + 4.3, '#');
            $pdf->text(28, $tableY + 4.3, 'FACILITADOR / DOCENTE');
            $pdf->text(95, $tableY + 4.3, 'CÉDULA DE IDENTIDAD');
            $pdf->text(138, $tableY + 4.3, 'CURSOS ASIGNADOS');
            $pdf->text(172, $tableY + 4.3, 'ESTUDIANTES');

            $curY = $tableY + $rowH;
            if (empty($topFacilitadores)) {
                $pdf->setColor(255, 255, 255);
                $pdf->rect(15, $curY, 185.9, $rowH, true, true);
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Italic', 7.5);
                $pdf->textCenterBox(15, 200.9, $curY + 4.3, 'No hay facilitadores asignados actualmente');
                $curY += $rowH;
            } else {
                foreach ($topFacilitadores as $idx => $fac) {
                    $bg = ($idx % 2 === 0) ? 255 : 248;
                    $pdf->setColor($bg, $bg, ($bg === 255 ? 255 : 252));
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->rect(15, $curY, 185.9, $rowH, true, true);

                    $pdf->setColor(26, 54, 93);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(18, $curY + 4.3, "#" . ($idx + 1));

                    $pdf->setColor(15, 23, 42);
                    $pdf->setFont('Helvetica-Bold', 8);
                    $pdf->text(28, $curY + 4.3, $fac['nombre']);

                    $pdf->setColor(71, 85, 105);
                    $pdf->setFont('Helvetica', 8);
                    $pdf->text(95, $curY + 4.3, "V-" . $fac['cedula']);

                    $pdf->setColor(26, 54, 93);
                    $pdf->setFont('Helvetica-Bold', 8);
                    $pdf->text(145, $curY + 4.3, "{$fac['cursos_impartidos']} cursos");

                    $pdf->setColor(34, 197, 94);
                    $pdf->setFont('Helvetica-Bold', 8);
                    $pdf->text(175, $curY + 4.3, "{$fac['total_estudiantes']} alumnos");

                    $curY += $rowH;
                }
            }

            // Sección 5: Top cursos
            $y = $curY + 8;
            $drawSectionTitle($pdf, 15, $y, '5', 'Asignaturas con Mayor Demanda y Participación');

            $tableY = $y + 4;
            $pdf->setColor(26, 54, 93);
            $pdf->rect(15, $tableY, 185.9, $rowH, true, false);

            $pdf->setColor(255, 255, 255);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->text(18, $tableY + 4.3, 'CÓDIGO');
            $pdf->text(40, $tableY + 4.3, 'NOMBRE DE LA ASIGNATURA');
            $pdf->text(105, $tableY + 4.3, 'ÁREA');
            $pdf->text(142, $tableY + 4.3, 'DOCENTE RESPONSABLE');
            $pdf->text(178, $tableY + 4.3, 'INSCRITOS');

            $curY = $tableY + $rowH;
            if (empty($topCursos)) {
                $pdf->setColor(255, 255, 255);
                $pdf->rect(15, $curY, 185.9, $rowH, true, true);
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Italic', 7.5);
                $pdf->textCenterBox(15, 200.9, $curY + 4.3, 'No hay cursos registrados');
                $curY += $rowH;
            } else {
                foreach ($topCursos as $idx => $cur) {
                    $bg = ($idx % 2 === 0) ? 255 : 248;
                    $pdf->setColor($bg, $bg, ($bg === 255 ? 255 : 252));
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->rect(15, $curY, 185.9, $rowH, true, true);

                    $pdf->setColor(71, 85, 105);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(18, $curY + 4.3, $cur['codigo']);

                    $pdf->setColor(15, 23, 42);
                    $pdf->setFont('Helvetica-Bold', 8);
                    $pdf->text(40, $curY + 4.3, substr($cur['nombre'], 0, 32));

                    $pdf->setColor(100, 116, 139);
                    $pdf->setFont('Helvetica', 7.5);
                    $pdf->text(105, $curY + 4.3, substr($cur['area'], 0, 20));

                    $pdf->setColor(71, 85, 105);
                    $pdf->setFont('Helvetica', 7.5);
                    $pdf->text(142, $curY + 4.3, substr($cur['facilitador'] ?: 'Por asignar', 0, 20));

                    $cupoLleno = ($cur['total_inscritos'] >= $cur['cupo_maximo']);
                    $pdf->setColor($cupoLleno ? 220 : 22, $cupoLleno ? 38 : 101, $cupoLleno ? 38 : 52);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(180, $curY + 4.3, "{$cur['total_inscritos']} / {$cur['cupo_maximo']}");

                    $curY += $rowH;
                }
            }

            // Sección 6: Histórico por mes
            $y = $curY + 8;
            $drawSectionTitle($pdf, 15, $y, '6', 'Histórico de Inscripciones Registradas');

            $boxY = $y + 4;
            $pdf->setColor(248, 250, 252);
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->rect(15, $boxY, 185.9, 14, true, true);

            if (empty($inscripcionesMes)) {
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Italic', 7.5);
                $pdf->textCenterBox(15, 200.9, $boxY + 8, 'Sin registros históricos de inscripción');
            } else {
                $mesesCount = min(6, count($inscripcionesMes));
                $mesColW = 185.9 / $mesesCount;
                foreach ($inscripcionesMes as $idx => $m) {
                    $mx = 15 + ($idx * $mesColW);
                    if ($idx > 0) {
                        $pdf->setStrokeColor(226, 232, 240);
                        $pdf->line($mx, $boxY + 2, $mx, $boxY + 12);
                    }
                    $pdf->setColor(26, 54, 93);
                    $pdf->setFont('Helvetica-Bold', 9);
                    $pdf->textCenterBox($mx, $mx + $mesColW, $boxY + 6.5, "{$m['total']} inscritos");

                    $pdf->setColor(100, 116, 139);
                    $pdf->setFont('Helvetica', 7);
                    $pdf->textCenterBox($mx, $mx + $mesColW, $boxY + 10.5, "Período {$m['mes']}");
                }
            }

            // Sección 7: Firmas y validación
            $firmaY = 222;

            $pdf->setStrokeColor(148, 163, 184);
            $pdf->setLineWidth(0.3);
            $pdf->line(30, $firmaY, 95, $firmaY);

            $pdf->setColor(15, 23, 42);
            $pdf->setFont('Helvetica-Bold', 8);
            $pdf->textCenterBox(30, 95, $firmaY + 4.5, "{$user['nombre']} {$user['apellidos']}");
            $pdf->setColor(71, 85, 105);
            $pdf->setFont('Helvetica', 7.2);
            $pdf->textCenterBox(30, 95, $firmaY + 8.5, 'Administración Central y Control Curricular');
            $pdf->setColor(100, 116, 139);
            $pdf->setFont('Helvetica', 6.8);
            $pdf->textCenterBox(30, 95, $firmaY + 12, "C.I. V-{$user['cedula']}  |  UPTECMS");

            $pdf->line(120, $firmaY, 185, $firmaY);

            $pdf->setColor(15, 23, 42);
            $pdf->setFont('Helvetica-Bold', 8);
            $pdf->textCenterBox(120, 185, $firmaY + 4.5, 'DIRECCIÓN DE EVALUACIÓN ACADÉMICA');
            $pdf->setColor(71, 85, 105);
            $pdf->setFont('Helvetica', 7.2);
            $pdf->textCenterBox(120, 185, $firmaY + 8.5, 'Sello Institucional y Validación Curricular');
            $pdf->setColor(100, 116, 139);
            $pdf->setFont('Helvetica', 6.8);
            $pdf->textCenterBox(120, 185, $firmaY + 12, 'UPTEC «Mariscal Sucre»');

            // Nota de certificación
            $pdf->setColor(248, 250, 252);
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->rect(15, $firmaY + 18, 185.9, 12, true, true);

            $pdf->setColor(71, 85, 105);
            $pdf->setFont('Helvetica-Bold', 6.8);
            $pdf->textCenterBox(15, 200.9, $firmaY + 22.5, 'CERTIFICACIÓN DE VERACIDAD DOCUMENTAL');
            $pdf->setColor(100, 116, 139);
            $pdf->setFont('Helvetica', 6.5);
            $pdf->textCenterBox(15, 200.9, $firmaY + 26.5, 'Este informe oficial refleja fielmente los datos auditados y registrados en la plataforma institucional UPTECMS.');

            $drawFooter($pdf, 2, 2);

        } elseif ($tipo === 'Analista') {
            $pdf->addPage('P');
            $drawHeader($pdf, 'INFORME DE CONTROL CURRICULAR', 'Supervisión y auditoría del estado de asignaturas y docentes', $user, 1, 1);

            $y = 61;
            $drawSectionTitle($pdf, 15, $y, '1', 'Resumen General de Asignaturas');

            $boxY = $y + 4;
            $pdf->setColor(255, 255, 255);
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->rect(15, $boxY, 185.9, 16, true, true);

            $totalCursos = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1")->fetchColumn() ?: 0;
            $cursosPlan = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'Planificado'")->fetchColumn() ?: 0;
            $cursosCurso = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'En Curso'")->fetchColumn() ?: 0;
            $cursosFin = $db->query("SELECT COUNT(*) FROM cursos WHERE activo = 1 AND estado = 'Finalizado'")->fetchColumn() ?: 0;
            $totalFac = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Facilitador' AND activo = 1")->fetchColumn() ?: 0;

            $items = [
                ['label' => 'Total Cursos', 'val' => $totalCursos],
                ['label' => 'Planificados', 'val' => $cursosPlan],
                ['label' => 'En Curso', 'val' => $cursosCurso],
                ['label' => 'Finalizados', 'val' => $cursosFin],
                ['label' => 'Facilitadores', 'val' => $totalFac]
            ];
            $colW = 185.9 / count($items);
            foreach ($items as $idx => $it) {
                $ix = 15 + ($idx * $colW);
                if ($idx > 0) {
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->line($ix, $boxY + 2, $ix, $boxY + 14);
                }
                $pdf->setColor(26, 54, 93);
                $pdf->setFont('Helvetica-Bold', 11);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 7, (string)$it['val']);
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 6.8);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 12, $it['label']);
            }

            $y = 88;
            $drawSectionTitle($pdf, 15, $y, '2', 'Cursos sin Docente Asignado (Atención Requerida)');
            $stmt = $db->query("SELECT codigo, nombre, estado, cupo_maximo FROM cursos WHERE facilitador_id IS NULL AND activo = 1");
            $sinFac = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tableY = $y + 4;
            $rowH = 6.2;
            $pdf->setColor(26, 54, 93);
            $pdf->rect(15, $tableY, 185.9, $rowH, true, false);
            $pdf->setColor(255, 255, 255);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->text(18, $tableY + 4.3, 'CÓDIGO');
            $pdf->text(50, $tableY + 4.3, 'ASIGNATURA');
            $pdf->text(140, $tableY + 4.3, 'ESTADO');
            $pdf->text(175, $tableY + 4.3, 'CAPACIDAD');

            $curY = $tableY + $rowH;
            if (empty($sinFac)) {
                $pdf->setColor(248, 250, 252);
                $pdf->rect(15, $curY, 185.9, $rowH + 2, true, true);
                $pdf->setColor(22, 101, 52);
                $pdf->setFont('Helvetica-Bold', 8);
                $pdf->textCenterBox(15, 200.9, $curY + 5, 'Excelente: Todas las asignaturas activas cuentan con facilitador asignado.');
                $curY += $rowH + 2;
            } else {
                foreach ($sinFac as $idx => $c) {
                    $bg = ($idx % 2 === 0) ? 255 : 248;
                    $pdf->setColor($bg, $bg, ($bg === 255 ? 255 : 252));
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->rect(15, $curY, 185.9, $rowH, true, true);
                    $pdf->setColor(15, 23, 42);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(18, $curY + 4.3, $c['codigo']);
                    $pdf->setFont('Helvetica', 8);
                    $pdf->text(50, $curY + 4.3, substr($c['nombre'], 0, 45));
                    $pdf->text(140, $curY + 4.3, $c['estado']);
                    $pdf->text(175, $curY + 4.3, "{$c['cupo_maximo']} cupos");
                    $curY += $rowH;
                }
            }

            $drawFooter($pdf, 1, 1);

        } elseif ($tipo === 'Facilitador') {
            $facilitadorId = $user['id'];
            $pdf->addPage('P');
            $drawHeader($pdf, 'INFORME DE ASIGNATURAS Y ESTUDIANTES', 'Desempeño y carga docente asignada al facilitador', $user, 1, 1);

            $stmt = $db->prepare("SELECT COUNT(*) FROM cursos WHERE facilitador_id = ? AND activo = 1");
            $stmt->execute([$facilitadorId]);
            $misCursosCount = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("SELECT COUNT(*) FROM cursos WHERE facilitador_id = ? AND activo = 1 AND estado = 'En Curso'");
            $stmt->execute([$facilitadorId]);
            $misCursosActivos = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM inscripciones i
                JOIN cursos c ON i.curso_id = c.id
                WHERE c.facilitador_id = ? AND i.estado != 'Abandonado'
            ");
            $stmt->execute([$facilitadorId]);
            $misEstudiantes = $stmt->fetchColumn() ?: 0;

            $y = 61;
            $drawSectionTitle($pdf, 15, $y, '1', 'Resumen de Carga Docente');

            $boxY = $y + 4;
            $pdf->setColor(255, 255, 255);
            $pdf->setStrokeColor(226, 232, 240);
            $pdf->rect(15, $boxY, 185.9, 16, true, true);

            $fItems = [
                ['label' => 'Total Cursos Asignados', 'val' => $misCursosCount],
                ['label' => 'Cursos Activos (En Curso)', 'val' => $misCursosActivos],
                ['label' => 'Total Estudiantes a Cargo', 'val' => $misEstudiantes]
            ];
            $colW = 185.9 / count($fItems);
            foreach ($fItems as $idx => $it) {
                $ix = 15 + ($idx * $colW);
                if ($idx > 0) {
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->line($ix, $boxY + 2, $ix, $boxY + 14);
                }
                $pdf->setColor(26, 54, 93);
                $pdf->setFont('Helvetica-Bold', 11);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 7, (string)$it['val']);
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica', 6.8);
                $pdf->textCenterBox($ix, $ix + $colW, $boxY + 12, $it['label']);
            }

            $y = 88;
            $drawSectionTitle($pdf, 15, $y, '2', 'Detalle de Cursos Impartidos');

            $stmt = $db->prepare("
                SELECT c.codigo, c.nombre, c.estado, c.duracion_horas,
                       (SELECT COUNT(*) FROM inscripciones WHERE curso_id = c.id AND estado != 'Abandonado') as inscritos,
                       c.cupo_maximo
                FROM cursos c
                WHERE c.facilitador_id = ? AND c.activo = 1
                ORDER BY c.fecha_inicio DESC
            ");
            $stmt->execute([$facilitadorId]);
            $misCursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tableY = $y + 4;
            $rowH = 6.2;
            $pdf->setColor(26, 54, 93);
            $pdf->rect(15, $tableY, 185.9, $rowH, true, false);
            $pdf->setColor(255, 255, 255);
            $pdf->setFont('Helvetica-Bold', 7.5);
            $pdf->text(18, $tableY + 4.3, 'CÓDIGO');
            $pdf->text(45, $tableY + 4.3, 'NOMBRE DE LA ASIGNATURA');
            $pdf->text(125, $tableY + 4.3, 'DURACIÓN');
            $pdf->text(150, $tableY + 4.3, 'ESTADO');
            $pdf->text(175, $tableY + 4.3, 'INSCRITOS');

            $curY = $tableY + $rowH;
            if (empty($misCursos)) {
                $pdf->setColor(248, 250, 252);
                $pdf->rect(15, $curY, 185.9, $rowH + 2, true, true);
                $pdf->setColor(100, 116, 139);
                $pdf->setFont('Helvetica-Italic', 7.5);
                $pdf->textCenterBox(15, 200.9, $curY + 5, 'No tiene cursos asignados actualmente.');
                $curY += $rowH + 2;
            } else {
                foreach ($misCursos as $idx => $c) {
                    $bg = ($idx % 2 === 0) ? 255 : 248;
                    $pdf->setColor($bg, $bg, ($bg === 255 ? 255 : 252));
                    $pdf->setStrokeColor(226, 232, 240);
                    $pdf->rect(15, $curY, 185.9, $rowH, true, true);
                    $pdf->setColor(15, 23, 42);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(18, $curY + 4.3, $c['codigo']);
                    $pdf->setFont('Helvetica', 8);
                    $pdf->text(45, $curY + 4.3, substr($c['nombre'], 0, 38));
                    $pdf->text(125, $curY + 4.3, "{$c['duracion_horas']} hrs");
                    $pdf->text(150, $curY + 4.3, $c['estado']);
                    $pdf->setColor(22, 101, 52);
                    $pdf->setFont('Helvetica-Bold', 7.5);
                    $pdf->text(175, $curY + 4.3, "{$c['inscritos']} / {$c['cupo_maximo']}");
                    $curY += $rowH;
                }
            }

            $drawFooter($pdf, 1, 1);
        }

        $filename = "reporte_{$tipo}_{$user['cedula']}.pdf";
        $pdf->output($filename, $dest);
        exit;

    } catch (Exception $e) {
        error_log("[UPTEC] Error generando PDF de reportes: " . $e->getMessage());
        http_response_code(500);
        return jsonResponse(false, null, 'Error al generar PDF: ' . $e->getMessage());
    }

}, [requireTeacherOrAbove()], 'reportes-pdf');