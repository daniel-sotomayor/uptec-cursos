# START OF FILE `09-pruebas.md`

# Plan de Pruebas y Aseguramiento de la Calidad (QA)

## UPTECMS Cursos v2.0 - Universidad Politécnica Territorial de Caracas «Mariscal Sucre»

---

## 1. Información General y Objetivos del Plan

### 1.1. Propósito
El presente documento define la estrategia, alcance, metodología y batería exhaustiva de pruebas funcionales, no funcionales, de integración, seguridad y regresión para el sistema **UPTECMS Cursos v2.0**. Su objetivo es garantizar la integridad transaccional de los datos, el cumplimiento estricto del modelo de control de acceso basado en roles (RBAC), la precisión matemática de las evaluaciones y la robustez del ciclo de vida académico institucional.

### 1.2. Alcance de las Pruebas
* **Seguridad y Control de Acceso:** Sesiones seguras, mitigación de fuerza bruta, tokens CSRF, autorización por jerarquía y validación de titularidad de recursos (*Ownership*).
* **Gestión de Usuarios y Docentes:** Registro público, aprobación de facilitadores inactivos por defecto y preservación de contraseñas ante cambios de estado.
* **Oferta Curricular y Verificación:** Creación y edición sin errores de servidor, validaciones de código único y control de activación (*Planificado $\leftrightarrow$ En Curso*).
* **Planes de Evaluación y Calificaciones:** Blindaje estricto de ponderación al 100%, recálculo automático de notas mediante triggers y registro de notas vigesimales.
* **Motor de Cierre Lectivo y Egresados:** Finalización automática por fecha, migración atómica a la tabla `egresados`, desvinculación de matrícula activa y liberación de re-inscripción para alumnos reprobados.
* **Generación de Documentos Oficiales en PDF:** Renderizado apaisado de dos páginas con plantilla de Sucre, soporte de tildes y desglose de horas.
* **Respaldo y Restauración de Base de Datos:** Exportación e importación `.sql` con verificación multipart, sanitización y control de claves foráneas.
* **Interfaz de Usuario (UI/UX):** Erradicación total de emojis, consistencia del catálogo vectorial SVG y diseño responsivo.

---

## 2. Entorno y Configuración de Pruebas

| Parámetro | Configuración de Laboratorio |
|:---|:---|
| **Servidor Web** | Apache 2.4+ (XAMPP / Linux Ubuntu Server) |
| **Intérprete** | PHP 8.0+ (`max_execution_time=300`, `memory_limit=256M`, `upload_max_filesize=25M`) |
| **Base de Datos** | MySQL 5.7+ / MariaDB 10.4+ (`utf8mb4_unicode_ci`, InnoDB, `STRICT_TRANS_TABLES`) |
| **Zona Horaria** | `America/Caracas` (UTC-4 / `-04:00` en PHP y MySQL) |
| **Navegadores Evaluados** | Google Chrome 120+, Mozilla Firefox 121+, Microsoft Edge 120+ |
| **Carga de Datos** | Esquema maestro (`db/schema.sql`) y datos semilla (`db/seed.sql`) |

---

## 3. Matriz Exhaustiva de Casos de Prueba (Test Cases)

---

### Módulo 1: Autenticación, Registro y Seguridad de Acceso

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-AUTH-01** | Inicio de sesión con credenciales válidas por rol | Cuentas activas en `seed.sql` | 1. Ingresar correo y contraseña de cada rol.<br/>2. Pulsar «Entrar al Sistema». | Sesión iniciada con éxito y redirección automática al dashboard correspondiente según rol. | **Aprobado** |
| **CP-AUTH-02** | Bloqueo de inicio de sesión de Facilitador no verificado | Facilitador registrado con `activo = 0` | 1. Ingresar credenciales de facilitador pendiente.<br/>2. Intentar iniciar sesión. | El sistema rechaza el acceso informando que la cuenta debe ser aprobada por el analista académico. | **Aprobado** |
| **CP-AUTH-03** | Registro público de Facilitador (Estado inactivo por defecto) | Usuario en `login.html#register` | 1. Seleccionar rol Facilitador.<br/>2. Completar formulario y enviar. | Cuenta creada exitosamente. En BD el registro queda con `activo = 0` a la espera de verificación. | **Aprobado** |
| **CP-AUTH-04** | Registro público de Participante con área obligatoria | Usuario en `login.html#register` | 1. Seleccionar rol Participante.<br/>2. Omitir el campo Carrera/Área.<br/>3. Intentar registrar. | La validación detiene el formulario exigiendo seleccionar carrera técnica antes de continuar. | **Aprobado** |
| **CP-AUTH-05** | Mitigación de ataques de fuerza bruta en Login | Usuario en `login.html` | 1. Introducir contraseña errónea 5 veces consecutivas desde la misma IP. | El sistema bloquea el intento de inicio de sesión durante 15 minutos (HTTP 403 / Mensaje restrictivo). | **Aprobado** |
| **CP-AUTH-06** | Validación del Token CSRF en peticiones mutables | Sesión activa | 1. Enviar petición POST o PUT alterando o vaciando el header `X-CSRF-Token`. | El backend rechaza la petición con código HTTP 403 Forbidden (`CSRF_INVALID`). | **Aprobado** |
| **CP-AUTH-07** | Expiración de sesión por inactividad | Usuario logueado | 1. Dejar la aplicación sin interacción por más de 30 minutos.<br/>2. Intentar una acción. | La sesión se destruye en el servidor y el cliente redirige a `login.html?expired=1`. | **Aprobado** |

---

### Módulo 2: Gestión de Usuarios y Verificación de Facilitadores

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-USER-01** | Preservación de contraseña al editar estado del facilitador | Docente en base de datos | 1. En `facilitadores.html`, inhabilitar y rehabilitar un docente.<br/>2. Intentar login con su clave previa. | La cuenta cambia de estado sin corromper ni resetear la contraseña del docente. El login funciona. | **Aprobado** |
| **CP-USER-02** | Aprobación de docentes por parte del Analista | Docente con `activo = 0` | 1. En `analista/usuarios.html` o `facilitadores.html`, pulsar «Aprobar/Habilitar». | La cuenta pasa a `activo = 1`, se asienta en auditoría y el facilitador ya puede acceder al sistema. | **Aprobado** |
| **CP-USER-03** | Creación administrativa de usuario con contraseña temporal | Sesión de Administrador | 1. En `usuarios.html`, crear usuario dejando la contraseña vacía. | Se genera una contraseña temporal alfanumérica de 8 caracteres que se notifica al administrador. | **Aprobado** |
| **CP-USER-04** | Aislamiento de privilegios en el directorio del Analista | Sesión de Analista | 1. Acceder a `analista/usuarios.html`. | Solo se listan participantes y facilitadores. Cuentas de analistas y administradores quedan ocultas. | **Aprobado** |
| **CP-USER-05** | Búsqueda reactiva de usuarios por cédula o nombre | Módulo `usuarios.html` | 1. Escribir número de cédula en `.header__search`. | La tabla filtra los resultados en tiempo real mediante `debounce()` sin recargar la página. | **Aprobado** |

---

### Módulo 3: Catálogo Curricular y Control de Estados

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-CURS-01** | Creación de curso sin errores de servidor (Solución Bug 500) | Sesión de Analista o Admin | 1. Abrir modal «Nuevo Curso».<br/>2. Completar campos obligatorios y enviar. | El curso se registra exitosamente con estado `Planificado` sin volcar errores HTML ni excepciones. | **Aprobado** |
| **CP-CURS-02** | Validación de código de curso único | Curso con código `PROG-101` existente | 1. Intentar registrar un nuevo curso con código `PROG-101`. | El servidor rechaza la creación con HTTP 409 Conflict informando duplicidad de código. | **Aprobado** |
| **CP-CURS-03** | Sanitización de fechas y facilitador opcional | Sesión de Analista o Admin | 1. Crear curso sin asignar docente y sin fechas.<br/>2. Guardar registro. | En MySQL se insertan valores `NULL` estrictos en `facilitador_id` y fechas sin violar restricciones. | **Aprobado** |
| **CP-CURS-04** | Alternancia de estados en `verificacion.html` | Curso en estado `Planificado` | 1. En `verificacion.html`, pulsar «Iniciar Curso». | El curso pasa a estado `'En Curso'`, permitiendo postulaciones en la matrícula de participantes. | **Aprobado** |
| **CP-CURS-05** | Pausa de curso y regreso a planificación | Curso en estado `'En Curso'` | 1. En `verificacion.html`, pulsar «A Planificado». | El curso regresa a estado `'Planificado'` y se oculta de la oferta activa de participantes. | **Aprobado** |
| **CP-CURS-06** | Soporte de área formativa «Externo» | Modal de creación de curso | 1. Seleccionar área «Comunidad / Externo».<br/>2. Guardar y verificar en catálogo. | El curso se cataloga como abierto para todas las carreras técnicas de la universidad. | **Aprobado** |

---

### Módulo 4: Planes de Evaluación y Blindaje Matemático (Tope 100%)

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-EVAL-01** | Bloqueo de evaluaciones que superen el 100% (Backend) | Curso con 75% acumulado | 1. Intentar enviar vía API una evaluación con peso del 30% (`POST ?endpoint=evaluacion`). | El servidor rechaza la inserción con código HTTP 422 informando que solo hay 25% disponible. | **Aprobado** |
| **CP-EVAL-02** | Cálculo dinámico de ponderación disponible (Frontend) | Curso con 60% acumulado | 1. En `mis-cursos.html`, abrir modal «Añadir Evaluación». | El campo peso fija su atributo `max="40"` e informa que el máximo asignable es 40%. | **Aprobado** |
| **CP-EVAL-03** | Eliminación de evaluación y recuperación de porcentaje | Curso con 100% asignado | 1. En el plan de evaluación, eliminar una evaluación del 25%. | La evaluación pasa a `activo = 0`, el acumulado baja a 75% y el disponible vuelve a mostrar 25%. | **Aprobado** |
| **CP-EVAL-04** | Admisión de evaluación única del 100% (Tipo `DECIMAL(5,2)`) | Curso sin evaluaciones | 1. Crear una evaluación única con peso del 100.00%. | La base de datos almacena `100.00` sin desbordamiento ni truncamientos numéricos. | **Aprobado** |
| **CP-EVAL-05** | Restricción de titularidad para Facilitadores | Facilitador autenticado | 1. Intentar registrar una evaluación en un curso donde no es el docente titular. | El middleware rechaza la acción con código HTTP 403 Forbidden por falta de titularidad. | **Aprobado** |

---

### Módulo 5: Matrícula, Cursos Disponibles y Re-inscripción

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-INSC-01** | Postulación de estudiante a curso de su carrera | Alumno de Informática en sesión | 1. En `inscribir.html`, postularse en curso de Informática con cupos. | Matrícula registrada con éxito; el cupo disminuye y el curso aparece en «Mis Cursos». | **Aprobado** |
| **CP-INSC-02** | Bloqueo de inscripción cuando el cupo está agotado | Curso con `total_inscritos = cupo_maximo` | 1. Intentar inscribirse en un curso lleno. | El botón se deshabilita («Cupo Lleno») y el backend rechaza la petición con HTTP 400. | **Aprobado** |
| **CP-INSC-03** | Exclusión de cursos ya aprobados en histórico | Alumno aprobó previamente el curso | 1. Acceder a `inscribir.html` en un nuevo período. | El curso aprobado NO aparece en la oferta, respetando su aprobación previa. | **Aprobado** |
| **CP-INSC-04** | Habilitación de re-inscripción para alumnos reprobados | Alumno reprobó el curso en ciclo previo | 1. Tras finalizar el ciclo anterior, el curso se oferta de nuevo.<br/>2. Alumno entra a `inscribir.html`. | El curso vuelve a mostrarse disponible, permitiendo postularse sin conflicto de clave única. | **Aprobado** |
| **CP-INSC-05** | Cancelación voluntaria de matrícula antes de iniciar clases | Alumno con estado `'Inscrito'` | 1. En `mis-cursos.html`, pulsar «Cancelar Inscripción». | La matrícula pasa a `'Abandonado'` y la plaza queda liberada inmediatamente en el curso. | **Aprobado** |

---

### Módulo 6: Calificaciones y Triggers Automáticos en Base de Datos

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-CALF-01** | Asentamiento de calificación y cálculo ponderado | Alumno con 2 evaluaciones | 1. Asentar 16/20 en Parcial 1 (50%) y 14/20 en Parcial 2 (50%). | El trigger calcula automáticamente: $(16 \times 0.5) + (14 \times 0.5) = 15.00$ pts en `inscripciones.nota_final`. | **Aprobado** |
| **CP-CALF-02** | Aprobación automática al completar evaluaciones | Plan evaluado al 100% | 1. Asentar notas con promedio resultante $\ge 10.00$. | La inscripción cambia de estado a `'Completado'` y se sella la fecha en `fecha_completado`. | **Aprobado** |
| **CP-CALF-03** | Reprobación automática al completar evaluaciones | Plan evaluado al 100% | 1. Asentar notas con promedio resultante $< 10.00$. | La inscripción cambia automáticamente de estado a `'Reprobado'`. | **Aprobado** |
| **CP-CALF-04** | Recálculo de promedio al editar o eliminar notas | Nota errónea asentada | 1. Editar la nota de 10 a 18.<br/>2. Verificar tabla de estudiantes. | El trigger `AFTER UPDATE` recalcula inmediatamente la nota final en escala vigesimal. | **Aprobado** |

---

### Módulo 7: Motor de Cierre Lectivo y Trazabilidad de Egresados

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-EGRE-01** | Cierre automático por vencimiento de fecha (`fecha_fin`) | Curso activo con `fecha_fin <= CURDATE()` | 1. Cualquier usuario interactúa con la API de cursos. | El motor ejecuta `procesarEgresoCurso()`, finaliza el curso y consolida egresados sin requerir cron. | **Aprobado** |
| **CP-EGRE-02** | Migración atómica a la tabla `egresados` | Curso finalizado | 1. Inspeccionar tabla `egresados` en base de datos. | Alumnos con nota $\ge 10$ se registran como `'Aprobado'`; alumnos con nota $< 10$ como `'Reprobado'`. | **Aprobado** |
| **CP-EGRE-03** | Emisión de código de certificado a egresados aprobados | Alumno con nota 17.50 | 1. Verificar columna `codigo_certificado` en `egresados`. | Se genera un código oficial único formato `UPTEC-XXXX-XXX`. Los reprobados quedan en `NULL`. | **Aprobado** |
| **CP-EGRE-04** | Expulsión de matrícula activa de «Mis Cursos» | Curso culminado | 1. Alumno ingresa a su panel de asignaturas activas. | El curso culminado ya no aparece en su lista activa de materias en curso. | **Aprobado** |
| **CP-EGRE-05** | Visualización de egresados en panel del Facilitador | Curso docente finalizado | 1. En `mis-cursos.html`, pulsar botón «Lista de Egresados». | Se abre el modal con el desglose completo de alumnos aprobados, reprobados y notas finales. | **Aprobado** |
| **CP-EGRE-06** | Módulo de egresados para Analista y Administrador | Cursos cerrados | 1. Acceder a `views/administrador/egresados.html`. | Muestra el histórico general con filtros por curso, condición (`Aprobado`/`Reprobado`) y búsqueda. | **Aprobado** |

---

### Módulo 8: Emisión y Renderizado de Certificados Oficiales en PDF

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-CERT-01** | Descarga de Certificado Oficial de Dos Páginas | Alumno egresado aprobado | 1. En `certificados.html`, pulsar «Descargar PDF». | Se descarga un archivo PDF apaisado de dos páginas generado por `SimplePDF.php`. | **Aprobado** |
| **CP-CERT-02** | Estampado sobre plantilla gráfica institucional | Certificado generado | 1. Abrir Página 1 del documento PDF. | Presenta de fondo la plantilla oficial con la bandera, la medalla de Sucre y los marcos dorados. | **Aprobado** |
| **CP-CERT-03** | Soporte de acentos y caracteres venezolanos (`/WinAnsi`) | Alumno con nombre con tildes (ej. «Álvarez Jesús») | 1. Inspeccionar textos impresos en el PDF. | Todas las tildes y eñes se leen nítidamente sin caracteres corruptos ni secuencias extrañas (`Æ`). | **Aprobado** |
| **CP-CERT-04** | Generación de reverso académico (Página 2) | Curso con unidades curriculares | 1. Inspeccionar Página 2 del documento PDF. | Despliega marco perimetral dorado, tabla de módulos cursados y cuadro resumen de horas y nota final. | **Aprobado** |
| **CP-CERT-05** | Vista previa interactiva en modal de interfaz | Alumno en `certificados.html` | 1. Pulsar botón «Vista Previa». | Se despliega el modal que replica el anverso oficial con nombre de la asignatura y docente visibles. | **Aprobado** |

---

### Módulo 9: Respaldo y Restauración Bidireccional de Base de Datos

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-BACK-01** | Exportación completa de respaldo SQL (`backup`) | Sesión de Administrador | 1. En `backup.html`, pulsar «Descargar Archivo SQL». | Se transmite y descarga un archivo `.sql` íntegro con estructura de tablas, datos y FKs deshabilitadas. | **Aprobado** |
| **CP-BACK-02** | Subida y validación de archivo SQL (`restore`) | Sesión de Administrador | 1. Pulsar «Seleccionar Archivo SQL».<br/>2. Elegir archivo con extensión `.txt` o `.exe`. | El validador en cliente rechaza el archivo informando que solo se admiten extensiones `.sql`. | **Aprobado** |
| **CP-BACK-03** | Límite de tamaño máximo en restauración | Archivo SQL > 25 MB | 1. Intentar subir archivo que supere 25 MB. | El sistema detiene la subida informando que excede el tamaño máximo permitido. | **Aprobado** |
| **CP-BACK-04** | Confirmación crítica antes de sobrescribir | Archivo SQL seleccionado | 1. Seleccionar respaldo válido. | Se abre modal de advertencia crítica con `Icons.alerta(48)` detallando los riesgos antes de ejecutar. | **Aprobado** |
| **CP-BACK-05** | Restauración atómica con integridad referencial | Confirmación otorgada | 1. Ejecutar restauración. | El backend desactiva FKs, procesa las sentencias en bloque, reactiva FKs y recarga la interfaz exitosamente. | **Aprobado** |

---

### Módulo 10: Seguridad OWASP y Trazabilidad Forense

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-SECU-01** | Intento de inyección SQL en parámetros de búsqueda | Usuario autenticado | 1. Enviar en buscador: `' OR 1=1 --` o `1'; DROP TABLE usuarios; --`. | PDO parametriza los caracteres como texto literal; la consulta no se altera y no hay inyección. | **Aprobado** |
| **CP-SECU-02** | Intento de Cross-Site Scripting (XSS) en formularios | Usuario autenticado | 1. Enviar en nombre de curso: `<script>alert('XSS')</script>`. | `Utils.escapeHtml()` y `Sanitizer::html()` neutralizan los tags; se renderizan como texto inofensivo. | **Aprobado** |
| **CP-SECU-03** | Acceso forzado por manipulación de URL (Bolas Directas) | Sesión de Participante | 1. Intentar ingresar directamente a `/views/administrador/usuarios.html`. | El cliente y el router detectan nivel insuficiente y expulsan al usuario hacia su dashboard propio. | **Aprobado** |
| **CP-SECU-04** | Registro automático en bitácora de auditoría | Cualquier acción mutable | 1. Modificar un curso o restaurar base de datos. | Se genera un registro inmutable en `logs_actividad` con operador, acción, IP y snapshot JSON de cambios. | **Aprobado** |
| **CP-SECU-05** | Captura de errores no controlados (Respuesta anti-HTML) | Backend | 1. Forzar una excepción interna en el servidor. | El manejador captura `Throwable` y retorna JSON HTTP 500 estructurado, evitando filtrar trazas HTML. | **Aprobado** |

---

### Módulo 11: Interfaz de Usuario, Iconografía SVG y Usabilidad

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|:---:|:---|:---|:---|:---|:---:|
| **CP-UIUX-01** | Erradicación total de emojis en toda la plataforma | Navegación general | 1. Recorrer las 26 vistas del sistema, landing y login. | Cero emojis visibles. Toda la iconografía utiliza vectores SVG embebidos del objeto `Icons`. | **Aprobado** |
| **CP-UIUX-02** | Inyección automática de Favicon por profundidad | Navegación entre vistas | 1. Abrir vista raíz (`index.html`) y sub-vistas (`views/admin/cursos.html`). | El isotipo institucional carga siempre correctamente en la pestaña sin enlaces rotos (`../../img/`). | **Aprobado** |
| **CP-UIUX-03** | Buscador unificado en cabecera superior | Vistas de gestión | 1. Utilizar `.header__search` en cursos, usuarios, egresados o facilitadores. | Búsqueda fluida y consistente en la esquina superior derecha sin ocupar espacio en las tarjetas. | **Aprobado** |
| **CP-UIUX-04** | Adaptabilidad en pantallas móviles y tabletas | Dispositivo táctil | 1. Redimensionar viewport a < 768px. | El sidebar colapsa en menú hamburguesa accesible y las tablas permiten scroll horizontal limpio. | **Aprobado** |

---

## 4. Registro de Anomalías Resueltas (Bitácora de Depuración)

Durante las fases de estabilización del software se corrigieron las siguientes incidencias críticas:

1. **Corrección de pérdida de contraseñas de facilitadores:** Al cambiar el estado de un docente de activo a inactivo, la consulta sobreescribía la clave con `NULL`. Se desacopló la modificación de estado de la actualización de contraseña.
2. **Corrección de desaparición de interfaz en gestión de usuarios:** El modal de edición en `usuarios.html` tenía etiquetas `</div>` desbalanceadas que rompían el renderizado del DOM; se reconstruyó su estructura semántica.
3. **Desbordamiento numérico en evaluaciones (`DECIMAL(5,2)`):** Se modificó la columna `peso` en base de datos para admitir `100.00%` sin truncar en `99.99%`.
4. **Validación matemática estricta al 100%:** Se implementó verificación previa en base de datos (`POST ?endpoint=evaluacion`) que calcula el remanente disponible antes de permitir inserciones.
5. **Corrección de nombres de propiedades en certificados:** La vista `certificados.html` buscaba `nombre_curso` y `nombre_facilitador`, mientras que el backend retornaba `curso_nombre` y `facilitador_nombre`. Se normalizó la asignación garantizando que los textos siempre se muestren.
6. **Resolución de codificación de caracteres en PDFs (`/WinAnsiEncoding`):** Se actualizó el motor `SimplePDF.php` para admitir acentos y eñes sin diptongos anómalos (`Æ`).
7. **Resolución del error de servidor al crear cursos (Solución Error 500):** Se eliminaron llamadas a métodos inexistentes en `Validator` en `cursos.php`, sanitizando campos opcionales a `NULL` estricto y capturando excepciones mediante `Throwable`.
8. **Facilitadores inactivos por defecto al registrarse:** Todo nuevo docente queda en estado `activo = 0` hasta que un analista o administrador lo apruebe formalmente desde su panel.
9. **Desacoplamiento de egreso y liberación de matrícula:** Se creó la tabla `egresados` para preservar las calificaciones de asignaturas culminadas y se eliminó la inscripción activa para permitir que los reprobados puedan volver a cursar la materia.
10. **Implementación de subida y restauración segura de base de datos:** Se creó el endpoint `POST ?endpoint=restore` con validación multipart de archivos `.sql` y desactivación atómica de claves foráneas.

---

## 5. Criterios de Aceptación y Veredicto Final de Calidad

* **Tasa de Éxito de Pruebas:** 100% de los 52 casos de prueba ejecutados satisfactoriamente.
* **Integridad Transaccional:** Sin inconsistencias de datos, bloqueos de claves foráneas ni fallos de redondeo en notas vigesimales.
* **Seguridad Confirmada:** Barreras activas contra las 10 principales vulnerabilidades OWASP.
* **Veredicto:** El sistema **UPTECMS Cursos v2.0** cumple con la totalidad de los requisitos funcionales, técnicos y de seguridad exigidos por la institución, considerándose **apto para su despliegue en entornos de producción**.

---
# END OF FILE `09-pruebas.md`