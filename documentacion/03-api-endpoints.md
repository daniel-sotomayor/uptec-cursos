  # START OF FILE `03-api-endpoints.md`

# API Endpoints

## UPTECMS Cursos v2.0 - Documentación de API RESTful

---

## Información General

| Propiedad | Valor |
|:---|:---|
| **Base URL** | `/uptec-cursos/backend/api/api.php` |
| **Formato de intercambio** | JSON (`application/json`) / Descargas binarias (`application/pdf`, `application/sql`) |
| **Autenticación** | Sesiones nativas de PHP + Tokens CSRF sincronizados |
| **Charset** | UTF-8 |

### Headers Requeridos para Peticiones Mutables (POST, PUT, DELETE)

```http
Content-Type: application/json
X-CSRF-Token: {token_csrf}
```
*(Nota: Para la subida de archivos en `POST ?endpoint=restore`, el header `Content-Type` debe omitirse en la petición para que el navegador asigne automáticamente `multipart/form-data` con su respectivo `boundary`).*

### CORS Headers Globales

```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token
```

---

## Convención de Respuestas

### 1. Formato Exitoso Estándar (JSON)
```json
{
  "success": true,
  "data": { ... },
  "message": "Mensaje informativo opcional"
}
```

### 2. Formato de Error Estándar (JSON)
```json
{
  "success": false,
  "error": "Descripción general del fallo",
  "errors": {
    "campo_especifico": "Descripción detallada del error de validación"
  }
}
```

### 3. Códigos de Estado HTTP Utilizados

| Código | Significado | Escenario de Uso |
|:---:|:---|:---|
| **200** | OK | Petición exitosa con datos de respuesta. |
| **201** | Created | Registro creado exitosamente (cursos, inscripciones, evaluaciones). |
| **400** | Bad Request | Parámetros obligatorios ausentes o formato de archivo inválido. |
| **401** | Unauthorized | Sesión no iniciada o caducada. |
| **403** | Forbidden | Permiso insuficiente para el rol del usuario autenticado. |
| **404** | Not Found | Registro no encontrado en la base de datos. |
| **409** | Conflict | Violación de unicidad (cédula, correo o código de curso ya existente). |
| **422** | Unprocessable Entity | Error de validación (ej. ponderación que supera el 100%, campos vacíos). |
| **500** | Internal Server Error | Excepción capturada en el servidor o error en base de datos. |

---

## 1. Endpoints de Autenticación y Seguridad

### POST /api.php?endpoint=login
Autentica al usuario mediante correo y contraseña, verifica bcrypt y crea la sesión PHP.

* **Permisos:** Público (sin autenticación).
* **Body:**
  ```json
  {
    "email": "admin@uptec.edu.ve",
    "password": "admin123"
  }
  ```
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "user": {
      "id": 1,
      "cedula": "V12345678",
      "nombre": "Administrador",
      "apellidos": "Sistema UPTEC",
      "correo": "admin@uptec.edu.ve",
      "telefono": "04121234567",
      "rol": "Administrador"
    },
    "token": "4a8f9c1b2d3e...",
    "redirect": "/uptec-cursos/frontend/views/administrador/dashboard.html"
  }
  ```

---

### POST /api.php?endpoint=logout
Cierra la sesión activa del usuario y limpia las cookies de autenticación.

* **Middleware:** `requireAuth()`
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "message": "Sesión cerrada exitosamente"
  }
  ```

---

### GET /api.php?endpoint=me
Obtiene los datos del perfil del usuario actualmente autenticado en la sesión.

* **Middleware:** `requireAuth()`
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "user": {
      "id": 5,
      "cedula": "V32361670",
      "nombre": "Pedro",
      "apellidos": "Hernández",
      "correo": "phernandez@uptec.edu.ve",
      "telefono": "04242987214",
      "rol": "Participante",
      "area": "Informatica"
    }
  }
  ```

---

### POST /api.php?endpoint=register
Registro público para nuevos usuarios (limitado estrictamente a roles `Participante` y `Facilitador`).

* **Permisos:** Público.
* **Body:**
  ```json
  {
    "cedula": "32361670",
    "nombre": "Pedro",
    "apellidos": "Hernández",
    "correo": "phernandez@uptec.edu.ve",
    "telefono": "04242987214",
    "password": "Password123",
    "password_confirm": "Password123",
    "rol": "Participante",
    "area": "Informatica"
  }
  ```
* **Validaciones:**
  * Cédula: 6 a 9 dígitos numéricos (única).
  * Correo: formato válido institucional y único.
  * Contraseña: mínimo 6 caracteres, al menos una letra y un número.
  * Área obligatoria si `rol = 'Participante'`.

---

### PUT /api.php?endpoint=password
Cambio voluntario de contraseña del usuario autenticado.

* **Middleware:** `requireAuth()`, `validateCsrf()`
* **Body:**
  ```json
  {
    "current_password": "PasswordActual123",
    "new_password": "NuevaPassword456"
  }
  ```

---

### GET /api.php?endpoint=csrf
Genera o retorna el token CSRF activo asociado a la sesión PHP para su consumo en cabeceras `X-CSRF-Token`.

* **Permisos:** Público.
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "token": "d7a8f9c2b1e4..."
  }
  ```

---

## 2. Endpoints de Usuarios

### GET /api.php?endpoint=usuarios
Lista de usuarios con soporte de filtros combinados.

* **Middleware:** `requireAnalystOrAbove()`
* **Query Parameters:**
  * `rol`: Filtra por rol (`Participante`, `Facilitador`, `Analista`, `Administrador`).
  * `activo`: Filtra por estado (`1` = Activo, `0` = Inactivo/Pendiente).
  * `buscar`: Coincidencia por nombre, apellido, correo o cédula.
* **Nota de Seguridad:** El rol `Analista` solo tiene visibilidad sobre usuarios con rol `Participante` y `Facilitador`.

---

### GET /api.php?endpoint=usuario&id={id}
Obtiene la ficha técnica completa de un usuario.

* **Middleware:** `requireAnalystOrAbove()`

---

### POST /api.php?endpoint=usuario
Crea un usuario administrativamente (ej. nuevos analistas o facilitadores creados por dirección).

* **Middleware:** `requireAdmin()`, `validateCsrf()`
* **Body:**
  ```json
  {
    "cedula": "12345678",
    "nombre": "Ana",
    "apellidos": "Ramírez",
    "correo": "aramirez@uptec.edu.ve",
    "telefono": "04121234567",
    "rol": "Analista",
    "area": null,
    "password": ""
  }
  ```
* **Comportamiento:** Si no se suministra contraseña, el servidor genera una contraseña temporal de 8 caracteres alfanuméricos y la devuelve en la respuesta.

---

### PUT /api.php?endpoint=usuario&id={id}
Actualiza los datos personales, rol o estado (`activo`) de un usuario.

* **Middleware:** `requireAnalystOrAbove()`, `validateCsrf()`
* **Restricción:** El analista solo puede modificar el estado de facilitadores o participantes; el administrador tiene control total.

---

### DELETE /api.php?endpoint=usuario&id={id}
Desactiva una cuenta de usuario (*soft delete*: `activo = 0`).

* **Middleware:** `requireAdmin()`, `validateCsrf()`
* **Restricción:** No es posible eliminar ni desactivar al Administrador principal (`id = 1`).

---

### GET /api.php?endpoint=facilitadores
Retorna el catálogo simplificado de facilitadores activos para selectores de asignación.

* **Middleware:** `requireAnalystOrAbove()`
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "facilitadores": [
      {
        "id": 3,
        "cedula": "V11111111",
        "nombre_completo": "Juan Pérez García",
        "correo": "jperez@uptec.edu.ve"
      }
    ]
  }
  ```

---

## 3. Endpoints de Cursos y Ciclo Lectivo

### GET /api.php?endpoint=cursos
Retorna la oferta curricular activa. Ejecuta en segundo plano la función `verificarCursosVencidos()` para finalizar asignaturas cuya `fecha_fin` ya venció.

* **Permisos:** Público.
* **Query Parameters:** `estado`, `facilitador_id`, `area`, `buscar`.

---

### GET /api.php?endpoint=curso&id={id}
Retorna la ficha integral del curso: datos generales, horarios, lista de estudiantes inscritos y plan de evaluación activo.

* **Permisos:** Público.

---

### POST /api.php?endpoint=curso
Crea una nueva asignatura en el sistema.

* **Middleware:** `requireAnalystOrAbove()`, `validateCsrf()`
* **Body:**
  ```json
  {
    "codigo": "PROG-101",
    "nombre": "Programación Web en PHP",
    "descripcion": "Desarrollo backend y bases de datos relacionales",
    "duracion_horas": 40,
    "cupo_maximo": 25,
    "nivel": "Intermedio",
    "fecha_inicio": "2026-03-01",
    "fecha_fin": "2026-05-30",
    "area": "Informatica",
    "facilitador_id": 3
  }
  ```
* **Validaciones del Servidor:**
  * `codigo` y `nombre` obligatorios.
  * Código único verificado en base de datos.
  * `duracion_horas` $\ge 1$ y `cupo_maximo` $\ge 1$.
  * Fechas y `facilitador_id` sanitizados (si vienen vacíos se insertan como `NULL` estricto en MySQL).

---

### PUT /api.php?endpoint=curso&id={id}
Actualiza los datos de un curso.

* **Middleware:** `requireAnalystOrAbove()`, `validateCsrf()`
* **Comportamiento Especial (Cierre Manual):** Si el payload envía `"estado": "Finalizado"` y el curso estaba activo, el servidor ejecuta automáticamente el procedimiento `procesarEgresoCurso()`, migrando los estudiantes a `egresados`, consolidando notas definitivas y liberando las inscripciones activas.

---

### DELETE /api.php?endpoint=curso&id={id}
Marca el curso como eliminado (*soft delete*: `activo = 0`, `estado = 'Cancelado'`).

* **Middleware:** `requireAdmin()`, `validateCsrf()`

---

### PUT /api.php?endpoint=curso-verificar&id={id}
Cambia el estado de una asignatura a `'Verificado'`, registrando al usuario y la fecha exacta de verificación.

* **Middleware:** `requireAnalystOrAbove()`, `validateCsrf()`

---

### GET /api.php?endpoint=areas
Retorna la lista de carreras y áreas de conocimiento únicas con cursos activos.

* **Permisos:** Público.
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "areas": [
      "Administracion",
      "Electrica",
      "Informatica",
      "Mantenimiento",
      "Mecanica",
      "Transporte Ferroviario"
    ]
  }
  ```

---

### GET /api.php?endpoint=mis-cursos
Retorna los cursos correspondientes al usuario en sesión según su rol:
* **Facilitador:** Asignaturas donde está asignado como docente (`facilitador_id = user.id` y `activo = 1`).
* **Participante:** Asignaturas donde tiene matrícula activa (`inscripciones.usuario_id = user.id` y `estado != 'Abandonado'`).
* **Middleware:** `requireAuth()`

---

## 4. Endpoints del Plan de Evaluación

### GET /api.php?endpoint=evaluaciones-curso&curso_id={id}
Consulta las evaluaciones configuradas para un curso, calculando la ponderación acumulada y el porcentaje restante disponible.

* **Middleware:** `requireTeacherOrAbove()`
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "evaluaciones": [
      {
        "id": 1,
        "nombre": "Parcial 1",
        "tipo": "Parcial",
        "peso": "25.00",
        "fecha_evaluacion": "2026-03-20",
        "orden": 1
      }
    ],
    "ponderacion_acumulada": 25.0,
    "total_peso": 25.0,
    "disponible": 75.0
  }
  ```

---

### POST /api.php?endpoint=evaluacion
Agrega una nueva evaluación al plan de evaluación del curso.

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`
* **Control de Seguridad:** Si quien ejecuta la acción es un `Facilitador`, el backend verifica que sea el titular asignado al curso.
* **Blindaje de Ponderación (Límite 100%):** Consulta `COALESCE(SUM(peso), 0)`. Si la suma actual más el nuevo peso supera `100.01%`, la petición es rechazada con **código HTTP 422**.
* **Body:**
  ```json
  {
    "curso_id": 1,
    "nombre": "Proyecto Final",
    "tipo": "Proyecto",
    "peso": 30.0,
    "fecha_evaluacion": "2026-05-15",
    "descripcion": "Defensa presencial de software comunitario"
  }
  ```

---

### DELETE /api.php?endpoint=evaluacion&id={id}
Elimina una evaluación del plan de evaluación (*soft delete*: `activo = 0`).

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`
* **Control de Seguridad:** Si es `Facilitador`, valida que el curso le pertenezca.

---

## 5. Endpoints de Egresados (Histórico de Cierre Lectivo)

### GET /api.php?endpoint=egresados-curso&curso_id={id}
Retorna la nómina definitiva de estudiantes que culminaron una asignatura cerrada, con su calificación final, condición académica (`Aprobado` o `Reprobado`) y código de certificado.

* **Middleware:** `requireTeacherOrAbove()`
* **Control de Seguridad:** El facilitador solo puede consultar egresados de sus cursos.
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "egresados": [
      {
        "id": 12,
        "usuario_id": 5,
        "cedula": "32361670",
        "nombre_estudiante": "Pedro Hernández",
        "correo": "phernandez@uptec.edu.ve",
        "codigo_curso": "PROG-101",
        "nombre_curso": "Programación Web en PHP",
        "nota_final": "17.50",
        "estado_academico": "Aprobado",
        "fecha_egreso": "2026-05-30 23:59:59",
        "codigo_certificado": "UPTEC-0005-001"
      }
    ],
    "total": 1
  }
  ```

---

### GET /api.php?endpoint=egresados
Retorna el registro histórico general de egresados de la institución con filtros.

* **Middleware:** `requireAnalystOrAbove()`
* **Query Parameters:**
  * `curso_id`: Filtra por asignatura específica.
  * `estado`: Filtra por condición (`Aprobado` o `Reprobado`).
  * `buscar`: Búsqueda por cédula, nombre o asignatura.

---

## 6. Endpoints de Inscripciones

### GET /api.php?endpoint=inscripciones
Lista las matrículas activas del sistema.

* **Middleware:** `requireTeacherOrAbove()`
* **Filtros:** `curso_id`, `estado`, `buscar`.
* **Restricción:** El facilitador solo visualiza inscripciones de sus asignaturas.

---

### POST /api.php?endpoint=inscripcion
Inscribe a un participante en una asignatura.

* **Middleware:** `requireAuth()`, `validateCsrf()`
* **Validaciones:**
  * El participante solo puede inscribirse a sí mismo; Analista y Admin pueden inscribir a terceros.
  * Verifica estado del curso (no cancelado ni finalizado).
  * Verifica disponibilidad de cupo (`total_inscritos < cupo_maximo`).
  * Impide doble inscripción activa (`unique_inscripcion`).
* **Body:**
  ```json
  {
    "curso_id": 1,
    "usuario_id": 5
  }
  ```

---

### PUT /api.php?endpoint=inscripcion&id={id}
Modifica el estado u observaciones de una inscripción activa.

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`
* **Estados Permitidos:** `Inscrito`, `En Progreso`, `Completado`, `Abandonado`, `Reprobado`.

---

### DELETE /api.php?endpoint=inscripcion&id={id}
Cancela una inscripción marcándola como `'Abandonado'`.

* **Middleware:** `requireAuth()`, `validateCsrf()`
* **Restricción:** El participante solo puede cancelar asignaturas antes de que el curso inicie.

---

### GET /api.php?endpoint=cursos-disponibles
Retorna el catálogo de cursos a los que el participante en sesión puede postularse.

* **Middleware:** `requireRole('Participante')`
* **Reglas de Negocio:**
  1. Filtra únicamente cursos del área/carrera del participante.
  2. Excluye cursos donde ya tiene una inscripción activa.
  3. **Excluye cursos que el participante ya haya APROBADO previamente en la tabla `egresados`** (si reprobó, el curso vuelve a aparecer disponible para cursarlo nuevamente).

---

## 7. Endpoints de Calificaciones

### GET /api.php?endpoint=calificaciones
Lista las calificaciones individuales registradas por evaluación.

* **Middleware:** `requireAuth()`
* **Query Parameters:** `curso_id`, `inscripcion_id`, `tipo`.

---

### GET /api.php?endpoint=mis-notas
Consulta el boletín de calificaciones y avance porcentual del estudiante en sesión.

* **Middleware:** `requireRole('Participante')`

---

### POST /api.php?endpoint=calificacion
Asienta una calificación para un estudiante en una evaluación del plan.

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`
* **Validaciones:** Nota numérica entre `0.00` y `20.00`. El facilitador debe ser el titular de la asignatura.
* **Efecto Secundario:** El trigger `actualizar_nota_final_inscripcion` en MySQL recalcula inmediatamente la nota final y el estado de la inscripción.
* **Body:**
  ```json
  {
    "inscripcion_id": 2,
    "evaluacion_id": 1,
    "nota": 16.5,
    "observaciones": "Excelente desempeño práctico"
  }
  ```

---

### PUT /api.php?endpoint=calificacion&id={id}
Actualiza una nota ya registrada. Dispara el trigger `actualizar_nota_final_update`.

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`

---

### DELETE /api.php?endpoint=calificacion&id={id}
Elimina un registro de calificación individual. Dispara el trigger `actualizar_nota_final_delete`.

* **Middleware:** `requireTeacherOrAbove()`, `validateCsrf()`

---

### GET /api.php?endpoint=estudiantes-curso&id={curso_id}
Retorna la matriz de estudiantes de un curso con todas sus calificaciones asignadas evaluación por evaluación.

* **Middleware:** `requireTeacherOrAbove()`

---

## 8. Endpoints de Reportes, Métricas y Certificados

### GET /api.php?endpoint=resumen
Genera el consolidado de métricas del panel principal según el rol del usuario autenticado.

* **Middleware:** `requireAuth()`
* **Métricas suministradas:**
  * **Administrador:** Totales de alumnos, docentes, analistas, cursos activos, distribución por carrera, cursos más populares y actividad reciente.
  * **Analista:** Total de cursos, planificados, verificados, asignaturas sin docente asignado y facilitadores disponibles.
  * **Facilitador:** Asignaturas activas y total de alumnos a cargo.
  * **Participante:** Asignaturas inscritas, aprobadas y promedio ponderado global.

---

### GET /api.php?endpoint=estadisticas
Estadísticas consolidadas globales (promedio institucional, tasa de aprobación, matrículas por mes y facilitadores con más horas).

* **Middleware:** `requireAdmin()`

---

### GET /api.php?endpoint=auditoria
Consulta la bitácora transaccional (`logs_actividad`).

* **Middleware:** `requireAdmin()`
* **Query Parameters:** `accion`, `tabla`, `fecha_desde`, `fecha_hasta`. Límite de 500 registros ordenados descendentemente.

---

### GET /api.php?endpoint=mis-certificados
Retorna la lista de certificados obtenidos por el estudiante en sesión. Consulta exclusivamente la tabla histórica **`egresados`** con condición `'Aprobado'`.

* **Middleware:** `requireAuth()`

---

### GET /api.php?endpoint=certificado&id={egresado_id}
Genera y descarga por streaming el **PDF Oficial del Certificado** emitido por la UPTECMS.

* **Middleware:** `requireAuth()`
* **Formato del Documento:**
  * Orientación horizontal (Landscape).
  * **Página 1:** Plantilla institucional oficial de fondo, medalla de Sucre, nombre caligráfico, cédula formateada (`CI V-XX.XXX.XXX`), fecha formal de culminación y firmas de Rector y Facilitador.
  * **Página 2:** Marco perimetral dorado con tabla de unidades curriculares/módulos cursados y cuadro resumen con horas teóricas, horas prácticas y nota final definitiva.
* **Respuesta:** Stream binario directo con cabecera `Content-Type: application/pdf`.

---

### GET /api.php?endpoint=mis-notas-pdf
Descarga el boletín de notas oficial del estudiante en sesión en formato PDF.

* **Middleware:** `requireAuth()`

---

### GET /api.php?endpoint=reportes-pdf
Descarga el informe estadístico consolidado en PDF según el rol que lo solicite (Administrador, Analista o Facilitador).

* **Middleware:** `requireTeacherOrAbove()`

---

## 9. Endpoints de Respaldo y Restauración de Base de Datos

### GET /api.php?endpoint=backup
Genera y descarga un volcado completo de la base de datos en formato `.sql`.

* **Middleware:** `requireAdmin()`
* **Query Parameters:** `tablas` (`all` o lista separada por comas).
* **Contenido generado:**
  * Cabecera institucional con fecha y usuario responsable.
  * `SET FOREIGN_KEY_CHECKS = 0;`
  * Sentencias `DROP TABLE IF EXISTS`, `CREATE TABLE` e `INSERT` de todos los registros.
  * `SET FOREIGN_KEY_CHECKS = 1;`
* **Respuesta:** Archivo descargable con cabecera `Content-Type: application/sql`.

---

### GET /api.php?endpoint=tablas
Retorna la lista de tablas activas en el esquema `uptec_cursos` con su conteo exacto de registros.

* **Middleware:** `requireAdmin()`

---

### POST /api.php?endpoint=restore
Sube y ejecuta un archivo de respaldo `.sql` para restaurar la base de datos completa.

* **Middleware:** `requireAdmin()`, `validateCsrf()`
* **Formato de Petición:** `multipart/form-data` con campo `backup_file`.
* **Protocolo de Seguridad del Servidor:**
  1. Valida que el archivo tenga extensión `.sql`.
  2. Valida tamaño máximo permitido ($\le 25\text{ MB}$).
  3. Comprueba que el archivo contenga instrucciones SQL reconocibles (`CREATE`, `INSERT`, `DROP`).
  4. Amplía temporalmente límites de tiempo (`set_time_limit(300)`) y memoria (`256M`).
  5. Desactiva temporalmente `FOREIGN_KEY_CHECKS = 0;`.
  6. Divide y ejecuta las sentencias SQL en bloque.
  7. Restaura `FOREIGN_KEY_CHECKS = 1;`.
  8. Asienta el evento `RESTORE` en la bitácora `logs_actividad`.
* **Respuesta Exitosa (200 OK):**
  ```json
  {
    "success": true,
    "message": "Base de datos restaurada exitosamente.",
    "sentencias": 142
  }
  ```

---
# END OF FILE `03-api-endpoints.md`