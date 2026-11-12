# START OF FILE `08-diagramas.md`

# Diagramas del Sistema

## UPTECMS Cursos v2.0 - Especificación Gráfica y Diagramas de Flujo

---

## 1. Arquitectura General del Sistema

El sistema implementa una **arquitectura monolítica modular por capas** con estricta política de **cero dependencias externas (Zero-Dependencies)**. Todas las peticiones del frontend se canalizan a través de un único punto de entrada RESTful (`api.php`) que orquesta la autenticación, validación, persistencia en base de datos y la generación binaria de documentos oficiales (PDF y SQL).

![Arquitectura General](./diagrams/architecture.png)

```mermaid
graph TB
    subgraph Frontend ["Capa de Presentación (Frontend Vanilla)"]
        UI_PUBLIC["index.html (Lobby Institucional)<br/>login.html (Slider de Acceso)"]
        UI_VIEWS["views/{rol}/*.html<br/>(26 Vistas: Admin, Analista, Docente, Alumno)"]
        UI_SCRIPTS["js/api.js (Cliente HTTP + Upload)<br/>js/auth.js (Control de Sesión)<br/>js/dashboard.js (Icons SVG + Modales)"]
        UI_PUBLIC --> UI_SCRIPTS
        UI_VIEWS --> UI_SCRIPTS
    end

    subgraph Backend ["Capa de Servicios y API (Backend PHP 8)"]
        ROUTER["backend/api/api.php<br/>(Enrutador REST + CORS + CSRF)"]
        ENDPOINTS["Controladores de Endpoints:<br/>auth, usuarios, cursos, egresados,<br/>inscripciones, calificaciones, reportes, backup"]
        SECURITY["Capa de Seguridad:<br/>Auth, CSRF, Sanitizer, Validator"]
        LIBS["Librerías Nativas & Assets:<br/>SimplePDF.php + certificado-plantilla.jpg"]
        ENGINE["Motor de Ciclo Lectivo:<br/>procesarEgresoCurso() + verificarVencidos()"]
        CONFIG["backend/db/config.php<br/>(Singleton Database PDO / Timezone UTC-4)"]
        
        ROUTER --> SECURITY
        ROUTER --> ENDPOINTS
        ENDPOINTS --> ENGINE
        ENDPOINTS --> LIBS
        ENDPOINTS --> CONFIG
    end

    subgraph Database ["Capa de Persistencia (MySQL 5.7+ InnoDB)"]
        DB_TABLES[("Tablas Principales:<br/>usuarios, cursos, evaluaciones,<br/>inscripciones, egresados, calificaciones,<br/>horarios, asistencias, logs_actividad")]
        DB_TRIGGERS["Triggers de Nota Final:<br/>AFTER INSERT / UPDATE / DELETE"]
        DB_VIEWS["Vistas SQL:<br/>vista_cursos_facilitadores<br/>vista_participantes_cursos"]
        CONFIG --> DB_TABLES
        DB_TABLES --- DB_TRIGGERS
        DB_TABLES --- DB_VIEWS
    end

    UI_SCRIPTS -->|HTTP Fetch JSON / Multipart / X-CSRF-Token| ROUTER
```

---

## 2. Diagrama Entidad-Relación (ER)

Este diagrama representa el modelo relacional completo de **UPTECMS Cursos**, incluyendo la entidad histórica **`EGRESADOS`**, la precisión decimal corregida en **`EVALUACIONES`** (`DECIMAL(5,2)`), la auditoría transaccional y la liberación de la clave única de matrícula.

![Diagrama Entidad-Relación](./diagrams/er-diagram.png)

```mermaid
erDiagram
    USUARIOS {
        INT id PK "Identificador único"
        VARCHAR cedula UK "Cédula de identidad (V-XXXXXXX)"
        VARCHAR nombre "Nombres del usuario"
        VARCHAR apellidos "Apellidos completos"
        VARCHAR correo UK "Correo institucional (@uptec.edu.ve)"
        VARCHAR telefono "Teléfono móvil nacional"
        VARCHAR password "Hash bcrypt cost 12"
        ENUM rol "Participante, Facilitador, Analista, Admin"
        VARCHAR area "Especialidad académica"
        TINYINT activo "1=Habilitado, 0=Bloqueado"
        DATETIME ultimo_acceso "Timestamp del último login"
    }

    CURSOS {
        INT id PK "Identificador único"
        VARCHAR codigo UK "Código curricular (PROG-101)"
        VARCHAR nombre "Nombre de la asignatura"
        TEXT descripcion "Alcance y objetivos"
        INT duracion_horas "Carga horaria académica"
        DATE fecha_inicio "Apertura del ciclo lectivo"
        DATE fecha_fin "Límite de vencimiento del curso"
        INT cupo_maximo "Capacidad máxima de plazas"
        VARCHAR area "Carrera técnica afín"
        ENUM nivel "Básico, Intermedio, Avanzado"
        ENUM estado "Planificado, En Curso, Finalizado, Cancelado, Verificado"
        INT facilitador_id FK "Docente titular asignado"
        INT analista_id FK "Analista supervisor"
        INT verificado_por FK "Operador que verificó"
        TINYINT activo "1=Activo, 0=Eliminado"
    }

    EVALUACIONES {
        INT id PK "Identificador único"
        INT curso_id FK "Asignatura vinculada"
        VARCHAR nombre "Nombre de la evaluación"
        TEXT descripcion "Criterios de la prueba"
        ENUM tipo "Parcial, Proyecto, Asistencia, etc."
        DECIMAL peso "Ponderación (Tope estricto al 100%)"
        DATE fecha_evaluacion "Fecha programada"
        INT orden "Secuencia cronológica"
        TINYINT activo "1=Activa, 0=Eliminada"
    }

    INSCRIPCIONES {
        INT id PK "Identificador único"
        INT usuario_id FK "Estudiante matriculado"
        INT curso_id FK "Curso en desarrollo"
        TIMESTAMP fecha_inscripcion "Registro de matrícula"
        ENUM estado "Inscrito, En Progreso, Completado, Abandonado, Reprobado"
        DECIMAL nota_final "Promedio acumulado actual"
        DATETIME fecha_completado "Fecha de culminación"
        TEXT observaciones "Notas del docente"
    }

    EGRESADOS {
        INT id PK "Identificador histórico único"
        INT usuario_id FK "Estudiante culminado"
        INT curso_id FK "Asignatura culminada"
        DECIMAL nota_final "Calificación definitiva (0-20)"
        ENUM estado_academico "Aprobado (>=10) o Reprobado (<10)"
        DATETIME fecha_egreso "Fecha de cierre lectivo"
        VARCHAR codigo_certificado "Código único de diploma"
        INT duracion_horas "Snapshot de horas del curso"
        VARCHAR facilitador_nombre "Snapshot del nombre del docente"
        TEXT observaciones "Historial de culminación"
    }

    CALIFICACIONES {
        INT id PK "Identificador único"
        INT inscripcion_id FK "Matrícula activa asociada"
        INT evaluacion_id FK "Actividad evaluada del plan"
        VARCHAR tipo_evaluacion "Tipo de evaluación"
        DECIMAL nota "Calificación vigesimal (0.00-20.00)"
        DECIMAL peso "Ponderación porcentual"
        DATE fecha_evaluacion "Fecha de presentación"
        TEXT observaciones "Retroalimentación docente"
    }

    HORARIOS {
        INT id PK "Identificador único"
        INT curso_id FK "Curso programado"
        ENUM dia_semana "Lunes a Domingo"
        TIME hora_inicio "Hora de entrada"
        TIME hora_fin "Hora de salida"
        VARCHAR aula "Salón o laboratorio físico"
    }

    ASISTENCIAS {
        INT id PK "Identificador único"
        INT inscripcion_id FK "Matrícula asociada"
        DATE fecha "Fecha de la sesión"
        ENUM estado "Presente, Ausente, Tarde, Justificado"
        VARCHAR observacion "Anotación del docente"
    }

    LOGS_ACTIVIDAD {
        BIGINT id PK "Identificador de bitácora"
        INT usuario_id FK "Operador actuante (NULL=Sistema)"
        VARCHAR usuario_nombre "Nombre completo del operador"
        VARCHAR accion "CREATE, UPDATE, DELETE, RESTORE, etc."
        VARCHAR tabla_afectada "Entidad modificada"
        INT registro_id "ID del registro afectado"
        JSON datos_anteriores "Estado previo del registro"
        JSON datos_nuevos "Estado modificado del registro"
        VARCHAR ip_address "Dirección IP del cliente"
        VARCHAR user_agent "Navegador y sistema operativo"
        DATETIME fecha_hora "Timestamp de la transacción"
    }

    %% Relaciones y Cardinalidades
    USUARIOS ||--o{ CURSOS : "asigna_como_docente"
    USUARIOS ||--o{ CURSOS : "supervisa_como_analista"
    CURSOS ||--o{ EVALUACIONES : "estructura_plan"
    CURSOS ||--o{ INSCRIPCIONES : "recibe_matricula"
    CURSOS ||--o{ EGRESADOS : "genera_historico"
    CURSOS ||--o{ HORARIOS : "programa_sesiones"
    
    USUARIOS ||--o{ INSCRIPCIONES : "cursa_como_alumno"
    USUARIOS ||--o{ EGRESADOS : "egresa_como_alumno"
    USUARIOS ||--o{ LOGS_ACTIVIDAD : "ejecuta_operaciones"

    INSCRIPCIONES ||--o{ CALIFICACIONES : "acumula_notas"
    INSCRIPCIONES ||--o{ ASISTENCIAS : "registra_asistencias"
    EVALUACIONES ||--o{ CALIFICACIONES : "pondera"
```

---

## 3. Flujo de Autenticación y Autorización (Security Flow)

Este flujo ilustra el protocolo de acceso seguro implementado en `login.html`, `auth.php` y `csrf.php`:

![Flujo de Autenticación](./diagrams/auth-flow.png)

```mermaid
sequenceDiagram
    autonumber
    actor Usuario
    participant Front as Frontend (login.html)
    participant API as Router (api.php)
    participant Auth as Auth & Security Layer
    participant DB as Base de Datos (MySQL)

    Usuario->>Front: Ingresa Correo y Contraseña
    Front->>API: GET ?endpoint=csrf
    API-->>Front: Retorna Token CSRF Activo
    Front->>API: POST ?endpoint=login (JSON + X-CSRF-Token)
    API->>Auth: Validar Token CSRF + Formato de Credenciales
    Auth->>Auth: Comprobar intentos fallidos (Anti-Fuerza Bruta)
    Auth->>DB: Consultar usuario activo por correo
    DB-->>Auth: Retorna registro con hash Bcrypt (cost 12)
    Auth->>Auth: password_verify(contraseña, hash)
    
    alt Credenciales Válidas
        Auth->>Auth: Iniciar sesión PHP (HttpOnly, Secure, SameSite=Strict)
        Auth->>Auth: Regenerar session_id (Anti-Session-Fixation)
        Auth->>DB: INSERT logs_actividad ('LOGIN', IP, User-Agent)
        Auth-->>API: Sesión autorizada + Datos del Perfil + Rol
        API-->>Front: JSON 200 OK (user, token, redirect por rol)
        Front->>Usuario: Redirección automática al Dashboard respectivo
    else Credenciales Inválidas o Bloqueado
        Auth->>Auth: Incrementar contador de intentos fallidos por IP
        Auth-->>API: Error 401 Unauthorized / 403 Lockout
        API-->>Front: JSON con mensaje de error específico
        Front->>Usuario: Notificación Toast de error + Efecto Shake
    end
```

---

## 4. Flujo de Consulta e Inscripción Curricular

Este flujo describe cómo un participante explora asignaturas de su carrera, validando cupos y excluyendo materias aprobadas previamente en `egresados`:

![Flujo de Inscripción](./diagrams/inscripcion-flow.png)

```mermaid
sequenceDiagram
    autonumber
    actor Alumno as Participante (Estudiante)
    participant Front as Frontend (inscribir.html)
    participant API as Router (api.php)
    participant Engine as Middleware & Reglas de Negocio
    participant DB as Base de Datos (MySQL)

    Alumno->>Front: Ingresa a "Inscribirme en Asignaturas"
    Front->>API: GET ?endpoint=cursos-disponibles
    API->>Engine: Validar requireRole('Participante')
    Engine->>DB: Consultar área/carrera del participante
    DB-->>Engine: Retorna carrera (ej. "Informatica")
    
    Engine->>DB: SELECT cursos WHERE area = carrera AND estado IN ('Planificado','En Curso')
    Note over Engine,DB: Excluye cursos con inscripción activa en 'inscripciones'<br/>Excluye cursos con estado_academico = 'Aprobado' en 'egresados'
    DB-->>Engine: Lista de cursos válidos con cupo disponible
    Engine-->>API: Cursos filtrados
    API-->>Front: JSON 200 OK con catálogo de materias
    Front->>Alumno: Renderiza tarjetas interactivas con cupos disponibles
    
    Alumno->>Front: Selecciona curso y hace clic en "Inscribirme"
    Front->>API: POST ?endpoint=inscripcion (curso_id + X-CSRF-Token)
    API->>Engine: Verificar requireAuth() + validateCsrf()
    Engine->>DB: Verificar cupo (total_inscritos < cupo_maximo)
    Engine->>DB: Verificar duplicidad activa (unique_inscripcion)
    Engine->>DB: INSERT INTO inscripciones (usuario_id, curso_id, estado='Inscrito')
    Engine->>DB: INSERT INTO logs_actividad ('INSCRIBIR')
    DB-->>Engine: Matrícula creada exitosamente
    Engine-->>API: Confirmación de registro
    API-->>Front: JSON 201 Created (inscripcion_id)
    Front->>Alumno: Notificación Toast de éxito y actualización de catálogo
```

---

## 5. Flujo del Ciclo de Cierre Lectivo y Transición a Egresados (Nuevo)

Este diagrama documenta la arquitectura de **cierre automático y manual de asignaturas**, la consolidación de notas, la expulsión de la matrícula activa y la habilitación de re-inscripción:

```mermaid
sequenceDiagram
    autonumber
    actor Operador as Analista / Administrador / Sistema
    participant Router as API Router (api.php)
    participant Engine as Lifecycle Engine (cursos.php)
    participant DB as Base de Datos (MySQL)
    actor Alumno as Participante Aprobado / Reprobado

    alt Vencimiento Automático (Lazy Trigger)
        Operador->>Router: Petición regular a cursos (GET ?endpoint=cursos)
        Router->>Engine: verificarCursosVencidos($db)
        Engine->>DB: SELECT id FROM cursos WHERE fecha_fin <= CURDATE() AND estado != 'Finalizado'
        DB-->>Engine: Retorna IDs de asignaturas vencidas
    else Cierre Manual por Analista / Admin
        Operador->>Router: PUT ?endpoint=curso&id=X {"estado": "Finalizado"}
        Router->>Engine: Detecta transición de estado a 'Finalizado'
    end

    Engine->>DB: BEGIN TRANSACTION (Aislamiento ACID)
    Engine->>DB: SELECT alumnos inscritos y notas finales en 'inscripciones'
    DB-->>Engine: Retorna lista de estudiantes con notas
    
    loop Por cada estudiante inscrito en el curso
        alt Nota Final >= 10.00
            Engine->>Engine: Estado = 'Aprobado' | Generar código certificado (UPTEC-XXXX-XXX)
        else Nota Final < 10.00
            Engine->>Engine: Estado = 'Reprobado' | Certificado = NULL
        end
        Engine->>DB: INSERT INTO egresados (usuario_id, curso_id, nota_final, estado_academico, snapshot_docente, horas)
    end

    Engine->>DB: DELETE FROM inscripciones WHERE curso_id = X
    Note over Engine,DB: Elimina matrícula activa, liberando el constraint UNIQUE<br/>Permite que alumnos reprobados vuelvan a inscribirse en el futuro
    Engine->>DB: UPDATE cursos SET estado = 'Finalizado' WHERE id = X
    Engine->>DB: INSERT logs_actividad ('FINALIZAR_CURSO')
    Engine->>DB: COMMIT TRANSACTION
    
    alt Alumno Aprobó
        Alumno->>Router: GET ?endpoint=mis-certificados
        Router->>DB: SELECT FROM egresados WHERE estado_academico = 'Aprobado'
        DB-->>Alumno: Visualiza y descarga Diploma Oficial en PDF
    else Alumno Reprobó
        Alumno->>Router: GET ?endpoint=cursos-disponibles (Siguiente ciclo)
        Router->>DB: Comprueba que no tiene aprobación en 'egresados'
        DB-->>Alumno: La asignatura vuelve a aparecer disponible para inscripción
    end
```

---

## 6. Flujo de Restauración Segura de Base de Datos (Nuevo)

Este diagrama detalla el protocolo de subida `multipart/form-data`, verificación heurística, aislamiento de claves foráneas y ejecución en bloque del módulo de respaldo:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Administrador del Sistema
    participant Front as Frontend (backup.html)
    participant Client as Cliente HTTP (api.js - upload)
    participant Router as API Router (api.php)
    participant Backend as Controlador (backup.php)
    participant DB as Motor MySQL (uptec_cursos)

    Admin->>Front: Selecciona archivo local de respaldo (.sql)
    Front->>Front: Muestra Modal de Confirmación y Advertencia Crítica (Icons.alerta)
    Admin->>Front: Pulsa "Confirmar y Restaurar"
    Front->>Client: API.upload('restore', formData)
    Client->>Router: POST ?endpoint=restore (multipart/form-data + X-CSRF-Token)
    
    Router->>Backend: Validar requireAdmin() + validateCsrf()
    Backend->>Backend: 1. Validar transmisión sin errores (UPLOAD_ERR_OK)
    Backend->>Backend: 2. Validar tamaño máximo (file_size <= 25 MB)
    Backend->>Backend: 3. Validar extensión obligatoria (.sql)
    Backend->>Backend: 4. Inspección heurística de contenido (CREATE, INSERT, DROP)
    
    Backend->>Backend: Ampliar límites: set_time_limit(300), memory_limit(256M)
    Backend->>DB: SET FOREIGN_KEY_CHECKS = 0; (Desactivar integridad temporalmente)
    
    loop Por cada lote de sentencias SQL delimitadas
        Backend->>DB: $db->exec($queryLote)
    end
    
    Backend->>DB: SET FOREIGN_KEY_CHECKS = 1; (Reactivar integridad referencial)
    Backend->>DB: INSERT INTO logs_actividad ('RESTORE', total_sentencias, archivo)
    
    Backend-->>Router: JSON 200 OK ("Base de datos restaurada exitosamente")
    Router-->>Client: Respuesta procesada
    Client-->>Front: Notificación Toast de éxito
    Front->>Admin: Recarga automática del navegador para sincronizar esquema
```

---

## 7. Notas Técnicas de los Diagramas

1. **Gestión de Sesión y Autenticación:** El sistema no utiliza tokens JWT en LocalStorage para autorización sensible. El token devuelto en el login es complementario; la autorización real reside en **sesiones seguras de PHP en el servidor** protegidas con flags `HttpOnly`, `SameSite=Strict` y cabeceras `X-CSRF-Token` validadas por middleware.
2. **Cliente HTTP Centralizado:** Todo el tráfico del frontend fluye a través de `frontend/js/api.js`. Este módulo abstrae la inyección del token CSRF, serializa peticiones JSON, maneja flujos multipart para subida de archivos y captura respuestas HTML anómalas arrojando mensajes legibles en la interfaz.
3. **Persistencia Transaccional:** Todas las operaciones de múltiples pasos (cierre de cursos, egreso de alumnos, actualización de planes con dependencias) se ejecutan dentro de bloques `try { $db->beginTransaction(); ... $db->commit(); } catch { $db->rollBack(); }` garantizando consistencia atómica.
4. **Métricas en Tiempo Real:** El cálculo de notas no depende de tareas asíncronas ni de cálculos manuales de interfaz; los triggers de MySQL (`actualizar_nota_final_inscripcion`) actualizan la ponderación vigesimal inmediatamente después de cada sentencia `INSERT`, `UPDATE` o `DELETE` sobre la tabla `calificaciones`.

---
# END OF FILE `08-diagramas.md`