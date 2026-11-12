# START OF FILE `02-base-de-datos.md`

# Base de Datos

## UPTECMS Cursos v2.0 - Documentación Completa de Base de Datos

---

## 1. Información General del Sistema Gestor

| Propiedad | Valor / Configuración | Justificación Técnica |
|:---|:---|:---|
| **Sistema Gestor (RDBMS)** | MySQL 5.7+ / MariaDB 10.4+ | Compatibilidad nativa con InnoDB, vistas y triggers. |
| **Nombre de Base de Datos** | `uptec_cursos` | Esquema centralizado de gestión académica. |
| **Conjunto de Caracteres (Charset)** | `utf8mb4` | Soporte pleno de caracteres internacionales, tildes y símbolos. |
| **Cotejamiento (Collation)** | `utf8mb4_unicode_ci` | Comparaciones y ordenamientos insensibles a mayúsculas y acentos. |
| **Motor de Almacenamiento** | InnoDB | Integridad referencial (`FOREIGN KEY`), concurrencia y transacciones ACID. |
| **Zona Horaria del Motor** | `America/Caracas` (GMT-4 / `-04:00`) | Sincronización estricta con la hora legal de Venezuela. |
| **Modo SQL Recomendado** | `STRICT_TRANS_TABLES` | Rechazo automático de tipos inválidos o fechas vacías. |

---

## 2. Diagrama Entidad-Relación Completo

```
┌─────────────────────────┐         ┌─────────────────────────┐         ┌─────────────────────────┐
│        usuarios         │         │         cursos          │         │      evaluaciones       │
├─────────────────────────┤         ├─────────────────────────┤         ├─────────────────────────┤
│ PK id (INT UNSIGNED)    │────┐    │ PK id (INT UNSIGNED)    │◄────────┤ PK id (INT UNSIGNED)    │
│    cedula VARCHAR(20) UQ│    │    │    codigo VARCHAR(20) UQ│         │ FK curso_id INT UNSIGNED│
│    nombre VARCHAR(100)  │    │    │    nombre VARCHAR(200)  │         │    nombre VARCHAR(100)  │
│    apellidos VARCHAR(100│    │    │    descripcion TEXT     │         │    descripcion TEXT     │
│    correo VARCHAR(150)UQ│    │    │    duracion_horas INT   │         │    tipo ENUM(...)       │
│    telefono VARCHAR(20) │    │    │    fecha_inicio DATE    │         │    peso DECIMAL(5,2)    │
│    password VARCHAR(255)│    │    │    fecha_fin DATE       │         │    fecha_evaluacion DATE│
│    rol ENUM(...)        │    │    │    cupo_maximo INT      │         │    orden INT UNSIGNED   │
│    area VARCHAR(100)    │    │    │    area VARCHAR(100)    │         │    activo TINYINT(1)    │
│    activo TINYINT(1)    │    │    │    nivel ENUM(...)      │         │    creado_en TIMESTAMP  │
│    ultimo_acceso DTIME  │    │    │    estado ENUM(...)     │         └─────────────────────────┘
└─────────────────────────┘    │    │ FK facilitador_id INT   │◄────┐
           │     │             │    │ FK analista_id INT      │◄──┐ │
           │     │             │    │ FK verificado_por INT   │   │ │
           │     │             │    │    activo TINYINT(1)    │   │ │
           │     │             │    └─────────────────────────┘   │ │
           │     │             │                 │                │ │
           │     │             │                 ▼                │ │
           │     │             │    ┌─────────────────────────┐   │ │
           │     │             │    │      inscripciones      │   │ │
           │     │             │    ├─────────────────────────┤   │ │
           │     │             │    │ PK id (INT UNSIGNED)    │   │ │
           │     │             └───►│ FK usuario_id (INT)     │   │ │
           │     │                  │ FK curso_id (INT)       │   │ │
           │     │                  │    fecha_inscripcion TS │   │ │
           │     │                  │    estado ENUM(...)     │   │ │
           │     │                  │    nota_final DEC(4,2)  │   │ │
           │     │                  │    observaciones TEXT   │   │ │
           │     │                  └─────────────────────────┘   │ │
           │     │                               │                │ │
           │     │                               ▼                │ │
           │     │                  ┌─────────────────────────┐   │ │
           │     │                  │      calificaciones     │   │ │
           │     │                  ├─────────────────────────┤   │ │
           │     │                  │ PK id (INT UNSIGNED)    │   │ │
           │     │                  │ FK inscripcion_id INT   │   │ │
           │     │                  │ FK evaluacion_id INT    │───┼─┼──┐
           │     │                  │    tipo_evaluacion      │   │ │  │
           │     │                  │    nota DECIMAL(4,2)    │   │ │  │
           │     │                  │    peso DECIMAL(4,2)    │   │ │  │
           │     │                  │    fecha_evaluacion DATE│   │ │  │
           │     │                  └─────────────────────────┘   │ │  │
           │     │                                                │ │  │
           │     │                  ┌─────────────────────────┐   │ │  │
           │     │                  │        egresados        │   │ │  │
           │     │                  ├─────────────────────────┤   │ │  │
           │     │                  │ PK id (INT UNSIGNED)    │   │ │  │
           │     └─────────────────►│ FK usuario_id INT       │   │ │  │
           │                        │ FK curso_id INT         │───┴─┘  │
           │                        │    nota_final DEC(4,2)  │        │
           │                        │    estado_academico ENUM│        │
           │                        │    fecha_egreso DATETIME│        │
           │                        │    codigo_certificado   │        │
           │                        │    duracion_horas INT   │        │
           │                        │    facilitador_nombre   │        │
           │                        └─────────────────────────┘        │
           │                                                           │
           ▼                                                           │
┌─────────────────────────┐         ┌─────────────────────────┐        │
│     logs_actividad      │         │        horarios         │        │
├─────────────────────────┤         ├─────────────────────────┤        │
│ PK id BIGINT UNSIGNED   │         │ PK id (INT UNSIGNED)    │        │
│ FK usuario_id INT (NULL)│         │ FK curso_id (INT)       │────────┘
│    usuario_nombre       │         │    dia_semana ENUM(...) │
│    accion VARCHAR(100)  │         │    hora_inicio TIME     │
│    tabla_afectada       │         │    hora_fin TIME        │
│    registro_id INT      │         │    aula VARCHAR(50)     │
│    datos_anteriores JSON│         └─────────────────────────┘
│    datos_nuevos JSON    │
│    ip_address           │         ┌─────────────────────────┐
│    fecha_hora TIMESTAMP │         │       asistencias       │
└─────────────────────────┘         ├─────────────────────────┤
                                    │ PK id (INT UNSIGNED)    │
                                    │ FK inscripcion_id INT   │
                                    │    fecha DATE           │
                                    │    estado ENUM(...)     │
                                    │    observacion VARCHAR  │
                                    └─────────────────────────┘
```

---

## 3. Especificación Detallada de Tablas

### 3.1. Tabla: `usuarios`
Almacena las credenciales, perfiles y datos personales de todas las personas con acceso a la plataforma.

```sql
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE COMMENT 'Cédula venezolana sin puntos (ej: 12345678 o V12345678)',
    nombre VARCHAR(100) NOT NULL COMMENT 'Primer y segundo nombre del usuario',
    apellidos VARCHAR(100) NOT NULL COMMENT 'Apellidos completos del usuario',
    correo VARCHAR(150) NOT NULL UNIQUE COMMENT 'Correo electrónico institucional (@uptec.edu.ve)',
    telefono VARCHAR(20) NOT NULL COMMENT 'Número telefónico móvil en formato nacional (ej: 04121234567)',
    password VARCHAR(255) DEFAULT NULL COMMENT 'Hash seguro bcrypt cost 12',
    rol ENUM('Participante', 'Facilitador', 'Analista', 'Administrador') DEFAULT 'Participante' COMMENT 'Rol en el sistema RBAC',
    area VARCHAR(100) NULL COMMENT 'Especialidad / Carrera técnica (aplica a Participantes)',
    activo TINYINT(1) DEFAULT 1 COMMENT '1 = Activo / Habilitado, 0 = Inactivo / Bloqueado',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL COMMENT 'Timestamp del último inicio de sesión exitoso',

    INDEX idx_cedula (cedula),
    INDEX idx_correo (correo),
    INDEX idx_rol (rol),
    INDEX idx_area (area),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

* **Reglas de Integridad:**
  * `cedula` y `correo` poseen restricciones de unicidad (`UNIQUE`) a nivel de base de datos.
  * El usuario `id = 1` corresponde al Administrador principal del sistema y no puede ser eliminado por software.
  * El campo `area` se valida contra las carreras autorizadas: *Administración, Informática, Mecánica, Eléctrica, Mantenimiento, Transporte Ferroviario* o *Externo*.

---

### 3.2. Tabla: `cursos`
Catálogo maestro de asignaturas, cursos y diplomados impartidos por la UPTECMS.

```sql
CREATE TABLE IF NOT EXISTS cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código alfanumérico institucional (ej: PROG-101)',
    nombre VARCHAR(200) NOT NULL COMMENT 'Nombre completo de la asignatura',
    descripcion TEXT NULL COMMENT 'Descripción de objetivos, perfil y alcance del curso',
    duracion_horas INT UNSIGNED NOT NULL COMMENT 'Carga horaria académica total',
    fecha_inicio DATE NULL COMMENT 'Fecha formal de inicio de actividades',
    fecha_fin DATE NULL COMMENT 'Fecha límite de finalización del ciclo',
    cupo_maximo INT UNSIGNED DEFAULT 30 COMMENT 'Límite máximo de participantes aceptados',
    area VARCHAR(100) NULL COMMENT 'Área del conocimiento o carrera afín',
    nivel ENUM('Basico', 'Intermedio', 'Avanzado') DEFAULT 'Basico',
    estado ENUM('Planificado', 'En Curso', 'Finalizado', 'Cancelado', 'Verificado') DEFAULT 'Planificado',
    facilitador_id INT UNSIGNED NULL COMMENT 'Docente asignado para impartir el curso',
    analista_id INT UNSIGNED NULL COMMENT 'Analista que creó o supervisa el curso',
    verificado_por INT UNSIGNED NULL COMMENT 'Usuario que aprobó la verificación curricular',
    fecha_verificacion DATETIME NULL COMMENT 'Fecha y hora en que se aprobó la verificación',
    activo TINYINT(1) DEFAULT 1 COMMENT '1 = Visible en catálogo, 0 = Eliminado lógicamente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (facilitador_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (analista_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (verificado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado),
    INDEX idx_facilitador (facilitador_id),
    INDEX idx_analista (analista_id),
    INDEX idx_area (area),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

* **Reglas de Integridad y Ciclo de Vida:**
  * Si un facilitador o analista es eliminado de la base de datos, las claves foráneas ejecutan `ON DELETE SET NULL`, preservando la asignatura intacta.
  * La columna `fecha_fin` actúa como disparador de tiempo: cuando `fecha_fin <= CURDATE()` y el curso no está `'Finalizado'`, el backend ejecuta `procesarEgresoCurso()`.

---

### 3.3. Tabla: `evaluaciones`
Estructura del plan de evaluación planificado para cada curso. La suma de ponderaciones de un curso debe totalizar exactamente el 100%.

```sql
CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso al que pertenece el plan de evaluación',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre de la actividad (ej: Parcial 1, Proyecto)',
    descripcion TEXT NULL COMMENT 'Pautas y criterios de evaluación',
    tipo ENUM('Parcial', 'Final', 'Proyecto', 'Asistencia', 'Trabajo', 'Otro') DEFAULT 'Otro',
    peso DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje asignado sobre la nota final (0.01 a 100.00)',
    fecha_evaluacion DATE NULL COMMENT 'Fecha prevista para la evaluación',
    orden INT UNSIGNED DEFAULT 0 COMMENT 'Secuencia de presentación cronológica',
    activo TINYINT(1) DEFAULT 1 COMMENT '1 = Activa en el plan, 0 = Eliminada lógicamente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_curso_eval (curso_id),
    INDEX idx_tipo_eval (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

* **Nota Crítica sobre el Tipo de Dato:**
  * La columna `peso` está definida como **`DECIMAL(5,2)`** (admite valores hasta `999.99`). Esto resuelve el desbordamiento de `DECIMAL(4,2)` que solo admitía hasta `99.99` y truncaba evaluaciones con ponderación de 100.00%.

---

### 3.4. Tabla: `inscripciones`
Maneja la matrícula **activa y transaccional** de estudiantes inscritos en cursos no finalizados.

```sql
CREATE TABLE IF NOT EXISTS inscripciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'Participante inscrito en el curso',
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso en el cual participa',
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Inscrito', 'En Progreso', 'Completado', 'Abandonado', 'Reprobado') DEFAULT 'Inscrito',
    nota_final DECIMAL(4,2) NULL COMMENT 'Calificación promedio acumulada ponderada (0.00 a 20.00)',
    fecha_completado DATETIME NULL COMMENT 'Fecha en que culminó la última evaluación',
    observaciones TEXT NULL COMMENT 'Anotaciones del docente facilitador',

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY unique_inscripcion (usuario_id, curso_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_curso (curso_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

* **Regla de Operación y Transición:**
  * Posee una clave única `unique_inscripcion` para evitar que un alumno se inscriba dos veces en el mismo curso en curso.
  * Cuando un curso finaliza, los registros de esta tabla son migrados a la tabla histórica `egresados` y eliminados de `inscripciones`. Esto libera la clave única y permite que un alumno reprobado pueda cursar la materia nuevamente en el siguiente período.

---

### 3.5. Tabla: `egresados` (Histórico Definitivo)
Tabla de almacenamiento histórico que preserva permanentemente las calificaciones finales, condición académica y emisión de certificados tras el cierre de un curso.

```sql
CREATE TABLE IF NOT EXISTS egresados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'Estudiante egresado',
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso que fue culminado',
    nota_final DECIMAL(4,2) NOT NULL DEFAULT 0.00 COMMENT 'Calificación definitiva obtenida (0.00 a 20.00)',
    estado_academico ENUM('Aprobado', 'Reprobado') NOT NULL COMMENT 'Condición final: Aprobado (>=10) o Reprobado (<10)',
    fecha_egreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha formal de cierre lectivo',
    codigo_certificado VARCHAR(50) NULL COMMENT 'Identificador único del certificado emitido (ej: UPTEC-0005-001)',
    duracion_horas INT UNSIGNED NULL COMMENT 'Copia de las horas académicas del curso al egresar',
    facilitador_nombre VARCHAR(200) NULL COMMENT 'Copia del nombre del facilitador que impartió el curso',
    observaciones TEXT NULL,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_egresado_usuario (usuario_id),
    INDEX idx_egresado_curso (curso_id),
    INDEX idx_egresado_estado (estado_academico),
    INDEX idx_egresado_fecha (fecha_egreso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

* **Propósito Arquitectónico:**
  * **Sin clave única `(usuario_id, curso_id)`:** Si un alumno reprueba en un ciclo y aprueba en otro, acumula ambos históricos sin conflictos de base de datos.
  * **Inmutabilidad:** Almacena snapshots de `duracion_horas` y `facilitador_nombre` para que cambios futuros en el curso no alteren los certificados emitidos en el pasado.

---

### 3.6. Tabla: `calificaciones`
Almacena el registro detallado de cada nota obtenida por un estudiante en una evaluación específica mientras el curso está activo.

```sql
CREATE TABLE IF NOT EXISTS calificaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id INT UNSIGNED NOT NULL COMMENT 'Matrícula activa asociada',
    evaluacion_id INT UNSIGNED NULL COMMENT 'Evaluación del plan (opcional)',
    tipo_evaluacion VARCHAR(50) NOT NULL COMMENT 'Tipo: Parcial, Proyecto, Asistencia, etc.',
    descripcion VARCHAR(200) NULL COMMENT 'Descripción o número de la actividad evaluada',
    nota DECIMAL(4,2) NOT NULL COMMENT 'Calificación en escala vigesimal (0.00 a 20.00)',
    peso DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Ponderación porcentual asignada a la evaluación',
    fecha_evaluacion DATE NOT NULL COMMENT 'Fecha de presentación de la prueba',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT NULL,

    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_inscripcion (inscripcion_id),
    INDEX idx_evaluacion (evaluacion_id),
    INDEX idx_tipo (tipo_evaluacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.7. Tabla: `horarios`
Programa semanal de sesiones presenciales o de laboratorio para cada curso.

```sql
CREATE TABLE IF NOT EXISTS horarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL COMMENT 'Curso asociado',
    dia_semana ENUM('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo') NOT NULL,
    hora_inicio TIME NOT NULL COMMENT 'Hora de inicio de clase (ej: 08:00:00)',
    hora_fin TIME NOT NULL COMMENT 'Hora de culminación (ej: 12:00:00)',
    aula VARCHAR(50) NULL COMMENT 'Espacio físico asignado (ej: Laboratorio 2, Aula 14)',

    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_curso_horario (curso_id),
    INDEX idx_dia (dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.8. Tabla: `asistencias`
Registro diario o por sesión de la asistencia de los participantes.

```sql
CREATE TABLE IF NOT EXISTS asistencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('Presente', 'Ausente', 'Tarde', 'Justificado') DEFAULT 'Presente',
    observacion VARCHAR(255) NULL,

    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY unique_asistencia (inscripcion_id, fecha),
    INDEX idx_inscripcion_asist (inscripcion_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.9. Tabla: `logs_actividad`
Bitácora de auditoría transaccional para trazabilidad forense y control interno.

```sql
CREATE TABLE IF NOT EXISTS logs_actividad (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL COMMENT 'ID del usuario actuante (NULL si es Sistema)',
    usuario_nombre VARCHAR(200) NULL COMMENT 'Nombre completo del operador',
    accion VARCHAR(100) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, LOGIN, RESTORE, FINALIZAR_CURSO',
    tabla_afectada VARCHAR(50) NULL COMMENT 'Entidad impactada',
    registro_id INT UNSIGNED NULL COMMENT 'ID del registro modificado',
    datos_anteriores JSON NULL COMMENT 'Snapshot JSON previo a la modificación',
    datos_nuevos JSON NULL COMMENT 'Snapshot JSON con los nuevos valores',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IPv4 o IPv6 de origen',
    user_agent VARCHAR(255) NULL COMMENT 'Navegador y sistema operativo',
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_usuario_log (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_tabla (tabla_afectada),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Vistas SQL (Views)

### 4.1. `vista_cursos_facilitadores`
Consolida el estado operativo de los cursos activos con información combinada de docentes, analistas y métricas de inscripción.

```sql
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
```

---

### 4.2. `vista_participantes_cursos`
Muestra a los estudiantes y el estado de sus inscripciones en cursos activos.

```sql
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
```

---

## 5. Triggers Automáticos

### 5.1. `actualizar_nota_final_inscripcion` (Al insertar calificación)
Recalcula automáticamente la nota final ponderada y el estado académico del alumno al ingresar una nueva nota.

```sql
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
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10.0 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN NOW()
            ELSE fecha_completado
        END
    WHERE id = NEW.inscripcion_id;
END//
DELIMITER ;
```

---

### 5.2. `actualizar_nota_final_update` (Al editar calificación)
Misma lógica ponderada ejecutada tras la actualización de una calificación existente.

```sql
DELIMITER //
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
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10.0 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN NOW()
            ELSE fecha_completado
        END
    WHERE id = NEW.inscripcion_id;
END//
DELIMITER ;
```

---

### 5.3. `actualizar_nota_final_delete` (Al eliminar calificación)
Recalcula el promedio o lo setea en `NULL` si no quedan evaluaciones registradas para esa matrícula.

```sql
DELIMITER //
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
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN 'Completado'
            WHEN total_peso >= 1.0 AND promedio < 10.0 THEN 'Reprobado'
            ELSE 'En Progreso'
        END,
        fecha_completado = CASE
            WHEN total_peso >= 1.0 AND promedio >= 10.0 THEN NOW()
            ELSE NULL
        END
    WHERE id = OLD.inscripcion_id;
END//
DELIMITER ;
```

---

## 6. Datos Iniciales de Prueba (Seed Data)

### 6.1. Cuentas de Acceso Base
*(Todas las contraseñas base están hasheadas con bcrypt cost 12)*.

| Rol | Cédula | Nombre Completo | Correo Institucional | Contraseña Plana |
|:---|:---|:---|:---|:---|
| **Administrador** | V12345678 | Administrador Sistema UPTECMS | `admin@uptec.edu.ve` | `admin123` |
| **Analista** | V87654321 | Analista Académico | `analista@uptec.edu.ve` | `analista123` |
| **Facilitador** | V11111111 | Juan Pérez García | `jperez@uptec.edu.ve` | `facilitador123` |
| **Facilitador** | V22222222 | María López Rodríguez | `mlopez@uptec.edu.ve` | `facilitador123` |
| **Facilitador** | V33333333 | Carlos González Martínez | `cgonzalez@uptec.edu.ve` | `facilitador123` |
| **Facilitador** | V44444444 | Ana Ramírez Silva | `aramirez@uptec.edu.ve` | `facilitador123` |

---

### 6.2. Catálogo Semilla de Cursos

| Código | Asignatura | Área / Carrera | Nivel | Horas | Estado |
|:---|:---|:---|:---|:---:|:---|
| **PROG-001** | Programación en PHP | Informática | Intermedio | 40 | En Curso |
| **WEB-001** | Desarrollo Web Frontend | Informática | Básico | 30 | En Curso |
| **BASE-001** | Bases de Datos MySQL | Informática | Intermedio | 45 | Planificado |
| **RED-001** | Fundamentos de Redes | Informática | Básico | 35 | Planificado |
| **ADM-001** | Gestión de Proyectos | Administración | Intermedio | 40 | Finalizado |
| **MEC-001** | Mecánica Automotriz Básica | Mecánica | Básico | 50 | En Curso |
| **ELEC-001**| Electricidad Industrial | Eléctrica | Intermedio | 45 | Planificado |
| **MANT-001**| Mantenimiento Preventivo | Mantenimiento | Básico | 40 | En Curso |
| **TF-001**  | Sistemas Ferroviarios | Transporte Ferroviario | Intermedio | 60 | Planificado |
| **OFI-001** | Ofimática Avanzada | Administración | Básico | 25 | Planificado |

---

## 7. Consultas SQL Frecuentes y de Diagnóstico

### 7.1. Consulta de Egresados y Aprobación por Asignatura
```sql
SELECT 
    c.codigo, 
    c.nombre AS curso,
    COUNT(e.id) AS total_egresados,
    SUM(CASE WHEN e.estado_academico = 'Aprobado' THEN 1 ELSE 0 END) AS aprobados,
    SUM(CASE WHEN e.estado_academico = 'Reprobado' THEN 1 ELSE 0 END) AS reprobados,
    ROUND(AVG(e.nota_final), 2) AS promedio_definitivo
FROM cursos c
INNER JOIN egresados e ON c.id = e.curso_id
GROUP BY c.id, c.codigo, c.nombre
ORDER BY c.codigo ASC;
```

### 7.2. Identificación de Cursos con Vencimiento Pendiente
```sql
SELECT 
    id, codigo, nombre, fecha_inicio, fecha_fin, estado
FROM cursos
WHERE activo = 1 
  AND estado IN ('Planificado', 'En Curso', 'Verificado')
  AND fecha_fin IS NOT NULL 
  AND fecha_fin <= CURDATE();
```

### 7.3. Verificación de Integridad de Ponderación (Planes al 100%)
```sql
SELECT 
    c.codigo,
    c.nombre,
    COALESCE(SUM(e.peso), 0) AS total_peso_planificado,
    CASE 
        WHEN COALESCE(SUM(e.peso), 0) = 100.00 THEN 'Plan Completo (100%)'
        WHEN COALESCE(SUM(e.peso), 0) < 100.00 THEN CONCAT('Incompleto: Faltan ', 100.00 - SUM(e.peso), '%')
        ELSE 'Error: Supera el 100%'
    END AS estado_evaluativo
FROM cursos c
LEFT JOIN evaluaciones e ON c.id = e.curso_id AND (e.activo = 1 OR e.activo IS NULL)
WHERE c.activo = 1
GROUP BY c.id, c.codigo, c.nombre;
```

---
# END OF FILE `02-base-de-datos.md`