--
-- UPTEC - Sistema de Control de Cursos v2.0
-- Esquema de Base de Datos MySQL
-- Universidad Politecnica Territorial de Caracas Mariscal Sucre
--
-- Roles del sistema:
-- - Administrador: Control total del sistema
-- - Analista: Verifica informacion de cursos, organiza facilitadores
-- - Facilitador (Docente): Imparte cursos, gestiona notas
-- - Participante (Estudiante): Se inscribe en cursos, ve notas
--

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS uptec_cursos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE uptec_cursos;

-- ============================================
-- TABLA: usuarios
-- Todos los usuarios del sistema con sus roles
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE COMMENT 'Cedula de identidad venezolana',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre del usuario',
    apellidos VARCHAR(100) NOT NULL COMMENT 'Apellidos del usuario',
    correo VARCHAR(150) NOT NULL UNIQUE COMMENT 'Correo electronico institucional',
    telefono VARCHAR(20) NOT NULL COMMENT 'Numero de contacto',
    password VARCHAR(255) DEFAULT NULL COMMENT 'Hash de contrasena (bcrypt)',
    rol ENUM('Participante', 'Facilitador', 'Analista', 'Administrador')
        DEFAULT 'Participante' COMMENT 'Rol del usuario en el sistema',
    area VARCHAR(100) NULL COMMENT 'Area/carrera del participante (Administracion, Informatica, etc.)',
    activo TINYINT(1) DEFAULT 1 COMMENT 'Estado de la cuenta (1=activo, 0=inactivo)',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL COMMENT 'Ultima fecha de login',

    INDEX idx_cedula (cedula),
    INDEX idx_correo (correo),
    INDEX idx_rol (rol),
    INDEX idx_area (area),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tabla principal de usuarios del sistema';

-- ============================================
-- TABLA: cursos
-- Informacion de los cursos ofrecidos
-- ============================================
CREATE TABLE IF NOT EXISTS cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Codigo unico del curso (ej: CUR-001)',
    nombre VARCHAR(200) NOT NULL COMMENT 'Nombre del curso',
    descripcion TEXT COMMENT 'Descripcion detallada del curso',
    duracion_horas INT UNSIGNED NOT NULL COMMENT 'Duracion en horas academicas',
    fecha_inicio DATE NULL COMMENT 'Fecha de inicio del curso',
    fecha_fin DATE NULL COMMENT 'Fecha de finalizacion del curso',
    cupo_maximo INT UNSIGNED DEFAULT 30 COMMENT 'Cantidad maxima de estudiantes',
    area VARCHAR(100) COMMENT 'Area de conocimiento (Tecnologia, Administracion, etc.)',
    nivel ENUM('Basico', 'Intermedio', 'Avanzado') DEFAULT 'Basico',
    estado ENUM('Planificado', 'En Curso', 'Finalizado', 'Cancelado', 'Verificado')
        DEFAULT 'Planificado' COMMENT 'Estado actual del curso',
    facilitador_id INT UNSIGNED NULL COMMENT 'Facilitador asignado al curso',
    analista_id INT UNSIGNED NULL COMMENT 'Analista que verifica el curso',
    verificado_por INT UNSIGNED NULL COMMENT 'Usuario que verifico el curso',
    fecha_verificacion DATETIME NULL COMMENT 'Fecha de verificacion del curso',
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (facilitador_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    FOREIGN KEY (analista_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado),
    INDEX idx_facilitador (facilitador_id),
    INDEX idx_analista (analista_id),
    INDEX idx_verificado (verificado_por),
    INDEX idx_area (area),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogo de cursos disponibles';

-- ============================================
-- TABLA: evaluaciones (plan de evaluacion del curso)
-- Define las actividades/evaluaciones que tendra un curso
-- ============================================
CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso al que pertenece',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre de la evaluacion (ej: Parcial 1)',
    descripcion TEXT COMMENT 'Descripcion de la evaluacion',
    tipo ENUM('Parcial', 'Final', 'Proyecto', 'Asistencia', 'Trabajo', 'Otro') DEFAULT 'Otro',
    peso DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Peso en la nota final (0-100%)',
    fecha_evaluacion DATE NULL COMMENT 'Fecha programada de la evaluacion',
    orden INT UNSIGNED DEFAULT 0 COMMENT 'Orden de la evaluacion en el plan',
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (curso_id) REFERENCES cursos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_curso_eval (curso_id),
    INDEX idx_tipo_eval (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Plan de evaluacion de cada curso';

-- ============================================
-- TABLA: inscripciones
-- Gestiona las inscripciones de participantes a cursos
-- ============================================
CREATE TABLE IF NOT EXISTS inscripciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'Participante inscrito',
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso al que esta inscrito',
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Inscrito', 'En Progreso', 'Completado', 'Abandonado', 'Reprobado')
        DEFAULT 'Inscrito',
    nota_final DECIMAL(4,2) NULL COMMENT 'Calificacion final (0.00 - 20.00)',
    fecha_completado DATETIME NULL COMMENT 'Fecha de finalizacion',
    observaciones TEXT COMMENT 'Observaciones del facilitador',

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY unique_inscripcion (usuario_id, curso_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_curso (curso_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inscripciones de participantes a cursos';

-- ============================================
-- TABLA: horarios
-- Almacena los horarios de cada curso
-- ============================================
CREATE TABLE IF NOT EXISTS horarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    dia_semana ENUM('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    aula VARCHAR(50) COMMENT 'Salon o aula asignada',

    FOREIGN KEY (curso_id) REFERENCES cursos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_curso_horario (curso_id),
    INDEX idx_dia (dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Horarios de los cursos';

-- ============================================
-- TABLA: calificaciones
-- Registro detallado de calificaciones por evaluacion
-- ============================================
CREATE TABLE IF NOT EXISTS calificaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id INT UNSIGNED NOT NULL,
    evaluacion_id INT UNSIGNED NULL COMMENT 'Evaluacion del plan (opcional)',
    tipo_evaluacion VARCHAR(50) NOT NULL COMMENT 'Tipo: Parcial, Final, Proyecto, etc.',
    descripcion VARCHAR(200) COMMENT 'Descripcion de la evaluacion',
    nota DECIMAL(4,2) NOT NULL COMMENT 'Calificacion (0.00 - 20.00)',
    peso DECIMAL(4,2) DEFAULT 1.00 COMMENT 'Peso de la evaluacion (0-100%)',
    fecha_evaluacion DATE NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,

    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_inscripcion (inscripcion_id),
    INDEX idx_evaluacion (evaluacion_id),
    INDEX idx_tipo (tipo_evaluacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Calificaciones detalladas de los participantes';

-- ============================================
-- TABLA: asistencias
-- Control de asistencia a las sesiones de curso
-- ============================================
CREATE TABLE IF NOT EXISTS asistencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('Presente', 'Ausente', 'Tarde', 'Justificado') DEFAULT 'Presente',
    observacion VARCHAR(255),

    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY unique_asistencia (inscripcion_id, fecha),
    INDEX idx_inscripcion_asist (inscripcion_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de asistencias';

-- ============================================
-- TABLA: logs_actividad
-- Auditoria completa de actividades del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS logs_actividad (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    usuario_nombre VARCHAR(200) NULL COMMENT 'Nombre del usuario que realizo la accion',
    accion VARCHAR(100) NOT NULL COMMENT 'Tipo de accion realizada (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, etc.)',
    tabla_afectada VARCHAR(50) COMMENT 'Tabla modificada',
    registro_id INT UNSIGNED COMMENT 'ID del registro afectado',
    datos_anteriores JSON COMMENT 'Datos antes de la modificacion',
    datos_nuevos JSON COMMENT 'Datos despues de la modificacion',
    ip_address VARCHAR(45) COMMENT 'Direccion IP del usuario',
    user_agent VARCHAR(255) COMMENT 'Navegador/Dispositivo',
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_usuario_log (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_tabla (tabla_afectada),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Logs de auditoria completa del sistema';

-- ============================================
-- VISTA: vista_cursos_facilitadores
-- Vista de cursos con informacion del facilitador y analista
-- ============================================
CREATE OR REPLACE VIEW vista_cursos_facilitadores AS
SELECT
    c.id,
    c.codigo,
    c.nombre,
    c.descripcion,
    c.duracion_horas,
    c.fecha_inicio,
    c.fecha_fin,
    c.cupo_maximo,
    c.area,
    c.nivel,
    c.estado,
    CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador,
    f.correo AS correo_facilitador,
    CONCAT(a.nombre, ' ', a.apellidos) AS nombre_analista,
    (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id AND i.estado != 'Abandonado') AS total_inscritos,
    (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id AND i.estado = 'Completado') AS total_completados,
    (SELECT ROUND(AVG(nota_final), 2) FROM inscripciones i WHERE i.curso_id = c.id AND i.nota_final IS NOT NULL) AS promedio_general
FROM cursos c
LEFT JOIN usuarios f ON c.facilitador_id = f.id
LEFT JOIN usuarios a ON c.analista_id = a.id
WHERE c.activo = 1;

-- ============================================
-- VISTA: vista_participantes_cursos
-- Vista de participantes con sus cursos inscritos
-- ============================================
CREATE OR REPLACE VIEW vista_participantes_cursos AS
SELECT
    u.id AS participante_id,
    u.cedula,
    CONCAT(u.nombre, ' ', u.apellidos) AS nombre_completo,
    u.correo,
    u.telefono,
    c.codigo AS codigo_curso,
    c.nombre AS nombre_curso,
    i.fecha_inscripcion,
    i.estado AS estado_inscripcion,
    i.nota_final,
    i.fecha_completado,
    CONCAT(f.nombre, ' ', f.apellidos) AS nombre_facilitador
FROM usuarios u
INNER JOIN inscripciones i ON u.id = i.usuario_id
INNER JOIN cursos c ON i.curso_id = c.id
LEFT JOIN usuarios f ON c.facilitador_id = f.id
WHERE u.rol = 'Participante';

-- ============================================
-- TRIGGER: actualizar_nota_final_inscripcion
-- Actualiza automaticamente la nota final basada en calificaciones
-- ============================================
DELIMITER //

CREATE TRIGGER actualizar_nota_final_inscripcion
AFTER INSERT ON calificaciones
FOR EACH ROW
BEGIN
    DECLARE promedio DECIMAL(4,2);
    DECLARE total_peso DECIMAL(6,2);

    SELECT SUM(nota * (peso/100)), SUM(peso/100) INTO promedio, total_peso
    FROM calificaciones
    WHERE inscripcion_id = NEW.inscripcion_id;

    IF total_peso > 0 THEN
        SET promedio = ROUND(promedio / total_peso, 2);
    END IF;

    UPDATE inscripciones
    SET nota_final = promedio,
        estado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN NOW()
            ELSE fecha_completado
        END
    WHERE id = NEW.inscripcion_id;
END//

CREATE TRIGGER actualizar_nota_final_update
AFTER UPDATE ON calificaciones
FOR EACH ROW
BEGIN
    DECLARE promedio DECIMAL(4,2);
    DECLARE total_peso DECIMAL(6,2);

    SELECT SUM(nota * (peso/100)), SUM(peso/100) INTO promedio, total_peso
    FROM calificaciones
    WHERE inscripcion_id = NEW.inscripcion_id;

    IF total_peso > 0 THEN
        SET promedio = ROUND(promedio / total_peso, 2);
    END IF;

    UPDATE inscripciones
    SET nota_final = promedio,
        estado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN NOW()
            ELSE fecha_completado
        END
    WHERE id = NEW.inscripcion_id;
END//

CREATE TRIGGER actualizar_nota_final_delete
AFTER DELETE ON calificaciones
FOR EACH ROW
BEGIN
    DECLARE promedio DECIMAL(4,2);
    DECLARE total_peso DECIMAL(6,2);

    SELECT COALESCE(SUM(nota * (peso/100)), 0), COALESCE(SUM(peso/100), 0) INTO promedio, total_peso
    FROM calificaciones
    WHERE inscripcion_id = OLD.inscripcion_id;

    IF total_peso > 0 THEN
        SET promedio = ROUND(promedio / total_peso, 2);
    ELSE
        SET promedio = NULL;
    END IF;

    UPDATE inscripciones
    SET nota_final = promedio,
        estado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10 THEN NOW()
            ELSE NULL
        END
    WHERE id = OLD.inscripcion_id;
END//

DELIMITER ;
