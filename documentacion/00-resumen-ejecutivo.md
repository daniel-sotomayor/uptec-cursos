# START OF FILE `00-resumen-ejecutivo.md`

# Resumen Ejecutivo

## UPTECMS Cursos

**UPTECMS Cursos** es un sistema web integral para la gestión académica, control de oferta curricular, inscripciones, calificaciones, certificación y auditoría, desarrollado a medida para la **Universidad Politécnica Territorial de Caracas «Mariscal Sucre» (UPTECMS)**.

### Propósito

El sistema centraliza y optimiza la administración de la oferta académica de la institución, facilitando la interacción fluida entre cuatro roles jerárquicos: **Administrador**, **Analista**, **Facilitador (Docente)** y **Participante (Estudiante)**. Brinda control total sobre usuarios, catálogo de cursos, planes de evaluación con ponderación blindada, cierre automático de ciclo lectivo, emisión de certificados oficiales y trazabilidad de operaciones mediante auditoría en tiempo real.

---

### Alcance del Sistema

- **Gestión integral de usuarios:** Control de perfiles con jerarquía basada en roles (RBAC), verificación de docentes y segmentación de participantes por área o carrera.
- **Catálogo de cursos y ciclo lectivo:** Creación y verificación curricular con asignación docente, control de cupos y finalización automática de asignaturas al cumplirse la fecha de cierre.
- **Histórico y control de egresados:** Migración atómica de estudiantes a la tabla histórica de egresados al culminar el curso, permitiendo la re-inscripción de alumnos reprobados y la conservación permanente de certificados aprobados.
- **Plan de evaluación y calificaciones:** Estructuración del plan académico con límite matemático estricto al 100%, cálculo ponderado de nota final y actualización automática de condición académica.
- **Generación y emisión de certificados oficiales:** Motor nativo de renderizado PDF de dos páginas apaisadas (anverso con plantilla oficial, medalla y firmas; reverso con desglose de módulos curriculares y resumen de horas académicas).
- **Respaldo y restauración de datos:** Exportación e importación integral de volcados SQL (`.sql`) con control de claves foráneas, validación de integridad y advertencia de seguridad.
- **Seguridad perimetral y trazabilidad:** Autenticación por sesiones endurecidas (HttpOnly, SameSite), protección contra ataques CSRF con tokens sincronizados, sanitización XSS, prevención de inyección SQL mediante sentencias preparadas PDO y registro detallado de bitácora (`logs_actividad`).
- **Frontend nativo sin dependencias (Zero-Dependencies):** Interfaz moderna y responsiva construida exclusivamente con HTML5 semántico, CSS3 institucional y JavaScript ES6+, con iconografía vectorial SVG pura (sin librerías de terceros ni emojis).

---

### Componentes Principales

```
uptec-cursos/
├── backend/
│   ├── api/             # Endpoints RESTful (auth, usuarios, cursos, inscripciones, calificaciones, reportes, backup)
│   ├── db/              # Conexión Singleton PDO y configuración de zona horaria
│   ├── libs/            # SimplePDF.php (Generador nativo de PDF en horizontal con soporte JPG y fuentes)
│   ├── security/        # Capa de seguridad (Auth, CSRF, Sanitizer, Validator)
│   └── assets/          # Plantilla gráfica oficial del certificado de aprobación
├── db/
│   ├── schema.sql       # Esquema DDL de tablas, vistas, índices y triggers
│   └── seed.sql         # Datos semilla con usuarios de prueba y cursos iniciales
├── frontend/
│   ├── css/             # Hojas de estilo institucional (main.css, login.css)
│   ├── js/              # Cliente HTTP (api.js), Auth (auth.js) y Core de Dashboard (dashboard.js con catálogo SVG)
│   └── views/           # Vistas distribuidas por rol (Administrador, Analista, Facilitador, Participante)
└── documentacion/       # Manuales técnicos, de arquitectura, base de datos, APIs y guías operativas
```

---

### Funcionalidades Destacadas

1. **Ciclo de Cierre y Migración de Egresados:** Detección automática de cursos vencidos (`fecha_fin <= CURDATE()`) que finaliza el curso, consolida las notas definitivas, archiva los registros en la tabla `egresados` y libera la matrícula activa.
2. **Blindaje de Evaluaciones al 100%:** Validación matemática previa tanto en interfaz como en controlador backend (`POST evaluacion`), impidiendo crear evaluaciones que excedan la ponderación disponible.
3. **Certificados Oficiales con Doble Página:** Emisión en formato apaisado sin dependencias externas:
   - **Página 1:** Nombre en tipografía clásica/caligráfica, cédula formateada, mención del curso, fecha de emisión formal y firmas del Rector y Facilitador.
   - **Página 2:** Pensum de unidades curriculares y cuadro resumen de horas teóricas, prácticas y calificación definitiva.
4. **Módulo Bidireccional de Base de Datos:** Exportación instantánea del esquema y datos en `.sql` y panel de restauración con subida `multipart/form-data`, ejecución en lote y reactivación segura de claves foráneas.
5. **Auditoría Transaccional:** Registro pormenorizado en `logs_actividad` con usuario, acción (`CREATE`, `UPDATE`, `DELETE`, `LOGIN`, `RESTORE`, `FINALIZAR_CURSO`), tabla, cambios en formato JSON, IP y fecha exacta.
6. **Interfaz Limpia y Profesional:** Homologación completa a la identidad **UPTECMS**, erradicación total de emojis en toda la plataforma y adopción de vectores SVG nativos con `stroke="currentColor"`.

---

### Estado Actual del Proyecto

- **Backend y Base de Datos:**
  - Tabla `egresados` implementada e integrada en consultas de certificados y disponibles.
  - Columna `peso` en `evaluaciones` configurada a `DECIMAL(5,2)` para admitir el 100.00%.
  - Motor automático de vencimiento y endpoints de consulta (`egresados-curso`, `egresados`, `restore`).
  - `SimplePDF.php` optimizado con codificación `/WinAnsiEncoding` (tildes y ñ resueltas).
- **Frontend y Vistas:**
  - Vistas de los 4 roles (Administrador, Analista, Facilitador y Participante) adaptadas a la nueva iconografía SVG y ortografía rigurosa.
  - Vistas `egresados.html` desplegadas para Administrador y Analista con filtros dinámicos.
  - Barra de búsqueda superior unificada (`.header__search`) en todos los módulos de gestión.
  - Formulario de creación y edición de cursos operativo y blindado ante errores de servidor.

---

### Próximos Pasos Recomendados

1. **Actualizar el resto de la documentación técnica:** Sincronizar `01-arquitectura.md`, `02-base-de-datos.md`, `03-api-endpoints.md`, `05-roles-permisos.md`, `07-frontend.md` y `08-diagramas.md` con las nuevas tablas, endpoints y flujos.
2. **Ajuste de zona horaria en producción:** Confirmar que `date_default_timezone_set('America/Caracas')` y la directiva `SET time_zone = '-04:00'` estén activos en el servidor donde se aloje el sistema.
3. **Pruebas de restauración en staging:** Validar que las directivas de PHP en el servidor final (`upload_max_filesize` y `post_max_size`) estén configuradas en al menos 25 MB para soportar archivos `.sql` voluminosos.
4. **Respaldo programado:** Implementar una tarea cron periódica en el servidor para ejecutar respaldos preventivos automáticos además del módulo manual.

---
# END OF FILE `00-resumen-ejecutivo.md`