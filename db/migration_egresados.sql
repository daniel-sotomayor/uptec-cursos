-- ============================================
-- UPTEC - Migración: Sistema de Egresados
-- Ejecutar en la base de datos uptec_cursos
-- ============================================

USE uptec_cursos;

-- ============================================
-- TABLA: egresados
-- Registro permanente de estudiantes que terminaron un curso
-- (aprobados o reprobados) al momento de la finalización
-- ============================================
CREATE TABLE IF NOT EXISTS egresados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'Estudiante egresado',
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso que finalizó',
    nota_final DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Nota final obtenida (0-20)',
    estado ENUM('Aprobado', 'Reprobado') NOT NULL COMMENT 'Resultado del estudiante',
    fecha_egreso DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha en que se finalizó el curso',
    certificado_codigo VARCHAR(80) DEFAULT NULL COMMENT 'Código único del certificado (solo Aprobados)',
    observaciones TEXT DEFAULT NULL COMMENT 'Notas adicionales del facilitador',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_egresados_curso (curso_id),
    INDEX idx_egresados_usuario (usuario_id),
    INDEX idx_egresados_estado (estado),
    UNIQUE KEY uk_usuario_curso (usuario_id, curso_id) COMMENT 'Un registro por estudiante por curso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro permanente de egresados por curso';
