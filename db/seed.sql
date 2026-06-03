--
-- UPTEC - Sistema de Control de Cursos v2.0
-- Datos Iniciales (Seed)
-- Universidad Politecnica Territorial de Caracas Mariscal Sucre
--

USE uptec_cursos;

-- ============================================
-- INSERTAR: Usuarios por rol
-- Contrasenas hasheadas con bcrypt cost 12
-- ============================================

-- Administrador del sistema (contrasena: admin123)
INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, password, rol, activo)
VALUES (
    '12345678',
    'Administrador',
    'Sistema UPTEC',
    'admin@uptec.edu.ve',
    '04121234567',
    '$2b$12$ti0VnSFjG8wMeF0L1eTk1Ow0Ga/jzmEZloYsa/DW88lm383mBPGdi',
    'Administrador',
    1
);

-- Analista academico (contrasena: analista123)
INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, password, rol, activo)
VALUES (
    '87654321',
    'Analista',
    'Academico',
    'analista@uptec.edu.ve',
    '04147654321',
    '$2b$12$iL/yL65FpoIfyD7gSbuJoeHUbDZXy8/ll6dSalJXmIGVAAo/4jNcq',
    'Analista',
    1
);

-- Facilitadores (Docentes) (contrasena: facilitador123)
INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, password, rol, activo) VALUES
('32361671', 'Juan', 'Perez Garcia', 'jperez@uptec.edu.ve', '04121111111', '$2b$12$bKhVT3pYhKJRmua1wwhuWu.5zNRSkjiZ1jq7s1x4pzm42AyqvsSke', 'Facilitador', 1),
('32361672', 'Maria', 'Lopez Rodriguez', 'mlopez@uptec.edu.ve', '04142222222', '$2b$12$bKhVT3pYhKJRmua1wwhuWu.5zNRSkjiZ1jq7s1x4pzm42AyqvsSke', 'Facilitador', 1),
('32361673', 'Carlos', 'Gonzalez Martinez', 'cgonzalez@uptec.edu.ve', '0416-3333333', '$2b$12$bKhVT3pYhKJRmua1wwhuWu.5zNRSkjiZ1jq7s1x4pzm42AyqvsSke', 'Facilitador', 1),
('32361674', 'Ana', 'Ramirez Silva', 'aramirez@uptec.edu.ve', '04244444444', '$2b$12$bKhVT3pYhKJRmua1wwhuWu.5zNRSkjiZ1jq7s1x4pzm42AyqvsSke', 'Facilitador', 1);

-- Participantes (Estudiantes) - sin contrasena (se registran publicamente)
-- Cada uno con su area/carrera asignada
INSERT INTO usuarios (cedula, nombre, apellidos, correo, telefono, rol, area, activo) VALUES
('32361675', 'Pedro', 'Hernandez Castro', 'phernandez@uptec.edu.ve', '04125555555', 'Participante', 'Informatica', 1),
('32361676', 'Laura', 'Diaz Morales', 'ldiaz@uptec.edu.ve', '0414-6666666', 'Participante', 'Administracion', 1),
('32361677', 'Miguel', 'Torres Vargas', 'mtorres@uptec.edu.ve', '04167777777', 'Participante', 'Mecanica', 1),
('32361678', 'Carmen', 'Flores Ruiz', 'cflores@uptec.edu.ve', '04248888888', 'Participante', 'Electrica', 1),
('32361679', 'Jose', 'Jimenez Aguilar', 'jjimenez@uptec.edu.ve', '04129999999', 'Participante', 'Mantenimiento', 1),
('32361610', 'Patricia', 'Reyes Mendoza', 'preyes@uptec.edu.ve', '04140000000', 'Participante', 'Transporte Ferroviario', 1);

-- ============================================
-- INSERTAR: Cursos de ejemplo
-- ============================================
INSERT INTO cursos (codigo, nombre, descripcion, duracion_horas, fecha_inicio, fecha_fin, cupo_maximo, area, nivel, estado, facilitador_id, analista_id) VALUES
('PROG-001', 'Programacion en PHP', 'Curso completo de desarrollo web con PHP, MySQL y conceptos de seguridad. Incluye practicas de programacion estructurada y POO.', 40, '2026-01-15', '2026-03-15', 25, 'Informatica', 'Intermedio', 'En Curso', 3, 2),
('WEB-001', 'Desarrollo Web Frontend', 'HTML5, CSS3 y JavaScript moderno. Diseno responsive, animaciones y consumo de APIs REST.', 30, '2026-01-20', '2026-03-20', 30, 'Informatica', 'Basico', 'En Curso', 4, 2),
('BASE-001', 'Bases de Datos MySQL', 'Diseno, normalizacion y gestion de bases de datos relacionales con MySQL. Consultas SQL avanzadas.', 35, '2026-02-01', '2026-04-01', 20, 'Informatica', 'Intermedio', 'Planificado', 3, 2),
('RED-001', 'Fundamentos de Redes', 'Conceptos basicos de redes de computadoras, protocolos TCP/IP, configuracion de routers y switches.', 25, '2026-02-10', '2026-03-10', 25, 'Informatica', 'Basico', 'Planificado', 5, 2),
('ADM-001', 'Gestion de Proyectos', 'Metodologias agiles (Scrum, Kanban), planificacion, seguimiento y control de proyectos tecnologicos.', 20, '2025-11-01', '2025-12-15', 20, 'Administracion', 'Intermedio', 'Finalizado', 3, 2),
('MEC-001', 'Mecanica Automotriz Basica', 'Fundamentos de mecanica de vehiculos, sistemas de motor, transmision y suspension.', 30, '2026-01-15', '2026-03-15', 20, 'Mecanica', 'Basico', 'En Curso', 3, 2),
('ELEC-001', 'Electricidad Industrial', 'Principios de electricidad, motores electricos, instalaciones industriales y seguridad electrica.', 35, '2026-02-01', '2026-04-01', 25, 'Electrica', 'Intermedio', 'Planificado', 4, 2),
('MANT-001', 'Mantenimiento Preventivo', 'Tecnicas de mantenimiento preventivo y correctivo de equipos industriales.', 25, '2026-01-20', '2026-02-20', 20, 'Mantenimiento', 'Basico', 'En Curso', 5, 2),
('TF-001', 'Sistemas de Transporte Ferroviario', 'Operacion y mantenimiento de sistemas ferroviarios, seguridad y normativas.', 40, '2026-02-15', '2026-04-15', 30, 'Transporte Ferroviario', 'Intermedio', 'Planificado', 3, 2),
('OFI-001', 'Ofimatica Avanzada', 'Microsoft Office y herramientas libres. Word, Excel avanzado, PowerPoint y manejo de documentos.', 24, '2026-03-01', '2026-04-15', 35, 'Administracion', 'Basico', 'Planificado', 4, 2);

-- ============================================
-- INSERTAR: Plan de evaluacion de cada curso
-- ============================================
INSERT INTO evaluaciones (curso_id, nombre, descripcion, tipo, peso, fecha_evaluacion, orden) VALUES
-- PROG-001
(1, 'Parcial 1', 'Primera evaluacion parcial - Fundamentos', 'Parcial', 20.00, '2026-02-01', 1),
(1, 'Proyecto Final', 'Proyecto integrador del curso', 'Proyecto', 30.00, '2026-03-10', 3),
(1, 'Asistencia', 'Puntualidad y asistencia a clases', 'Asistencia', 25.00, '2026-03-14', 4),
-- WEB-001
(2, 'Parcial 1', 'HTML5 y CSS3 avanzado', 'Parcial', 25.00, '2026-02-05', 1),
(2, 'Proyecto Web', 'Sitio web completo', 'Proyecto', 30.00, '2026-03-15', 3),
(2, 'Asistencia', 'Puntualidad y asistencia', 'Asistencia', 20.00, '2026-03-19', 4),
-- BASE-001
(3, 'Parcial 1', 'Modelado y normalizacion', 'Parcial', 25.00, '2026-02-15', 1),
(3, 'Proyecto BD', 'Diseno de base de datos', 'Proyecto', 35.00, '2026-03-25', 3),
(3, 'Asistencia', 'Puntualidad y asistencia', 'Asistencia', 15.00, '2026-03-30', 4);

-- ============================================
-- INSERTAR: Horarios de cursos
-- ============================================
INSERT INTO horarios (curso_id, dia_semana, hora_inicio, hora_fin, aula) VALUES
(1, 'Lunes', '08:00:00', '12:00:00', 'Laboratorio 1'),
(1, 'Miercoles', '08:00:00', '12:00:00', 'Laboratorio 1'),
(2, 'Martes', '14:00:00', '17:00:00', 'Aula Multimedia'),
(2, 'Jueves', '14:00:00', '17:00:00', 'Aula Multimedia'),
(3, 'Lunes', '14:00:00', '17:30:00', 'Laboratorio 2'),
(3, 'Viernes', '14:00:00', '17:30:00', 'Laboratorio 2'),
(4, 'Miercoles', '08:00:00', '13:00:00', 'Sala de Redes'),
(5, 'Sabado', '08:00:00', '12:00:00', 'Aula 101'),
(6, 'Lunes', '18:00:00', '21:00:00', 'Laboratorio 1'),
(6, 'Miercoles', '18:00:00', '21:00:00', 'Laboratorio 1');

-- ============================================
-- INSERTAR: Inscripciones de participantes
-- ============================================
INSERT INTO inscripciones (usuario_id, curso_id, estado, nota_final, fecha_completado) VALUES
-- Curso finalizado (ADM-001)
(5, 5, 'Completado', 18.50, '2025-12-15 10:30:00'),
-- Cursos en curso
(5, 1, 'En Progreso', NULL, NULL),
(5, 2, 'En Progreso', NULL, NULL),
(6, 1, 'En Progreso', NULL, NULL),
(6, 2, 'En Progreso', NULL, NULL),
(7, 1, 'Inscrito', NULL, NULL),
(8, 2, 'Inscrito', NULL, NULL),
(9, 1, 'Inscrito', NULL, NULL),
(10, 2, 'Inscrito', NULL, NULL);

-- ============================================
-- INSERTAR: Calificaciones de ejemplo
-- ============================================
INSERT INTO calificaciones (inscripcion_id, evaluacion_id, tipo_evaluacion, descripcion, nota, peso, fecha_evaluacion) VALUES
-- Participante 5 en curso 5 (finalizado con 18.50)
(1, NULL, 'Parcial 1', 'Primera evaluacion parcial', 17.00, 25.00, '2025-11-15'),
(1, NULL, 'Proyecto', 'Proyecto final del curso', 20.00, 30.00, '2025-12-10'),
(1, NULL, 'Asistencia', 'Puntualidad y asistencia', 18.00, 20.00, '2025-12-15'),
-- Participante 5 en curso 1 (en progreso)
(2, 1, 'Parcial 1', 'Primera evaluacion parcial', 16.00, 20.00, '2026-02-01'),
-- Participante 5 en curso 2 (en progreso)
(3, 5, 'Parcial 1', 'Primera evaluacion parcial', 15.50, 25.00, '2026-02-05'),
-- Participante 6 en curso 1 (en progreso)
(4, 1, 'Parcial 1', 'Primera evaluacion parcial', 14.00, 20.00, '2026-02-01'),
-- Participante 6 en curso 2 (en progreso)
(5, 5, 'Parcial 1', 'Primera evaluacion parcial', 17.50, 25.00, '2026-02-05');

-- ============================================
-- INSERTAR: Asistencias de ejemplo
-- ============================================
INSERT INTO asistencias (inscripcion_id, fecha, estado) VALUES
-- Curso 5 (finalizado) - participante 5
(1, '2025-11-01', 'Presente'),
(1, '2025-11-04', 'Presente'),
(1, '2025-11-08', 'Presente'),
(1, '2025-11-11', 'Tarde'),
(1, '2025-11-15', 'Presente'),
-- Curso 1 - participante 5
(2, '2026-01-15', 'Presente'),
(2, '2026-01-17', 'Presente'),
(2, '2026-01-22', 'Presente'),
(2, '2026-01-24', 'Ausente'),
(2, '2026-01-29', 'Presente'),
(2, '2026-01-31', 'Presente'),
(2, '2026-02-05', 'Presente'),
(2, '2026-02-07', 'Presente');
