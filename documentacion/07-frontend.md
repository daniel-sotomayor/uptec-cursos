# START OF FILE `07-frontend.md`

# Frontend

## UPTECMS Cursos v2.0 - Documentación Integral de la Capa de Presentación

---

## 1. Visión General de la Arquitectura Frontend

La interfaz de usuario de **UPTECMS Cursos** está concebida bajo la filosofía **Zero-Dependencies (sin dependencias externas ni compiladores)**. Opera directamente en el navegador con tecnologías web nativas, garantizando tiempos de carga casi instantáneos ($\approx 120\text{ ms}$), bajo consumo de memoria RAM en equipos cliente y facilidad de mantenimiento sin requerir entornos de desarrollo pesados (Node.js, Webpack o npm).

| Tecnología | Implementación Técnica | Propósito en el Sistema |
|:---|:---|:---|
| **HTML5 Semántico** | Estructura nativa sin frameworks | Maquetado accesible, formularios reactivos y modales dinámicos. |
| **CSS3 Modular** | Variables CSS, Grid, Flexbox y Glassmorphism | Sistema de diseño institucional consistente, temas y soporte responsivo. |
| **JavaScript ES6+** | Clases, Funciones Flecha, Promesas y Async/Await | Interactividad cliente, manipulación del DOM y renderizado de componentes. |
| **Fetch API Nativa** | Protocolo HTTP asíncrono con Cookies y CSRF | Comunicación con la API RESTful (JSON, Multipart y Stream binario). |
| **Iconografía SVG Pura** | Catálogo centralizado en `dashboard.js` | **Cero Emojis**. Vectores SVG embebidos con `stroke="currentColor"`. |

---

## 2. Estructura Exhaustiva de Carpetas y Vistas

El frontend se organiza de forma modular, segregando los recursos compartidos (`css/`, `js/`, `img/`) de las vistas privadas protegidas por el control de acceso basado en roles (`views/{rol}/`):

```text
frontend/
├── index.html                     # Portal de bienvenida institucional (Lobby)
├── login.html                     # Interfaz con slider para inicio de sesión y registro público
│
├── css/
│   ├── main.css                   # Sistema de diseño de paneles, tablas, cards, botones y modales
│   └── login.css                  # Estilos específicos del slider de autenticación y animaciones
│
├── img/                           # Logotipos vectoriales y rasterizados de la institución
│   ├── logo-uptec.png             # Isotipo oficial y Favicon del sistema
│   ├── logo-uptec-grande.png      # Logotipo de alta resolución para cabeceras y sidebar
│   └── logo-uptec-grande-v2.png   # Variante institucional alternativa
│
├── js/                            # Librerías cliente compartidas (Zero-Dependency)
│   ├── api.js                     # Cliente HTTP: GET, POST, PUT, DELETE, download, upload y CSRF
│   ├── auth.js                    # Gestor de estado de sesión, permisos y redirecciones
│   └── dashboard.js               # Core de interfaz: Sidebar, Header, Modales, Toast, Icons SVG
│
└── views/                         # Vistas segregadas por rol (Total: 26 interfaces)
    ├── administrador/             # Rol Nivel 4 (Control Total - 9 Vistas)
    │   ├── dashboard.html         # Métricas institucionales, gráficos de área y cursos recientes
    │   ├── usuarios.html          # Gestión integral de usuarios, contraseñas temporales y roles
    │   ├── egresados.html         # Histórico general de egresados con filtros avanzados
    │   ├── cursos.html            # Catálogo curricular, creación y asignación docente
    │   ├── inscripciones.html     # Matrícula general y modificación de estados
    │   ├── calificaciones.html    # Supervisión y anulación administrativa de notas
    │   ├── reportes.html          # Estadísticas globales y descarga de informe PDF
    │   ├── auditoria.html         # Monitor de traza transaccional forense (logs_actividad)
    │   └── backup.html            # Panel de exportación (.sql) y restauración con subida
    │
    ├── analista/                  # Rol Nivel 3 (Gestión Curricular - 7 Vistas)
    │   ├── dashboard.html         # Balance operativo de cursos, facilitadores y planificados
    │   ├── cursos.html            # Verificación de asignaturas y asignación de facilitadores
    │   ├── egresados.html         # Histórico de egresados y rendimiento por carrera
    │   ├── facilitadores.html     # Nómina docente con habilitación/inhabilitación de cuentas
    │   ├── usuarios.html          # Directorio supervisado de participantes y docentes
    │   ├── verificacion.html      # Control de activación ("En Curso" <-> "Planificado")
    │   └── reportes.html          # Balance de oferta académica e informe PDF de analista
    │
    ├── facilitador/               # Rol Nivel 2 (Docente Titular - 5 Vistas)
    │   ├── dashboard.html         # Resumen de carga académica y matrícula a cargo
    │   ├── mis-cursos.html        # Asignaturas asignadas, plan de evaluación (100%) y egresados
    │   ├── estudiantes.html       # Matriz de progreso estudiantil y avance evaluado
    │   ├── calificaciones.html    # Asentamiento y corrección de notas por evaluación
    │   └── reportes.html          # Estadísticas de aprobación y distribución vigesimal
    │
    └── participante/              # Rol Nivel 1 (Estudiante - 5 Vistas)
        ├── dashboard.html         # Panel personal, asignaturas en curso y promedio general
        ├── inscribir.html         # Oferta de materias por carrera con validación de cupos
        ├── mis-cursos.html        # Asignaturas donde mantiene matrícula activa
        ├── mis-notas.html         # Desglose de evaluaciones parciales y boletín en PDF
        └── certificados.html      # Galería de diplomas oficiales apaisados con vista previa y PDF
```

---

## 3. Catálogo Centralizado de Iconos Vectoriales SVG (`Icons`)

**Archivo:** `frontend/js/dashboard.js`  
A partir de la versión 2.0, **se erradicaron al 100% los emojis Unicode** de todas las vistas del sistema. En su lugar, el objeto global `window.Icons` provee vectores SVG puros estilizados con trazo uniforme (`stroke-width="2"`, `stroke-linecap="round"`, `stroke-linejoin="round"` y `stroke="currentColor"`), lo que permite que adopten dinámicamente el color del texto o botón contenedor.

```javascript
// Definición en frontend/js/dashboard.js
const Icons = {
    dashboard: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
    usuarios: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    egresados: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
    cursos: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>',
    inscripciones: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
    calificaciones: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
    reportes: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>',
    auditoria: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
    respaldo: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>',
    certificados: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
    alerta: (size = 48) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    vacio: (size = 48) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>`
};
window.Icons = Icons;
```

---

## 4. Módulos JavaScript Compartidos

### 4.1. Cliente de Comunicación HTTP (`api.js`)
Centraliza el manejo de peticiones asíncronas hacia el backend (`/uptec-cursos/backend/api/api.php`), la resolución de tokens CSRF, la subida de archivos multipart y el blindaje ante errores que arrojen HTML.

#### Métodos Públicos Expuestos (`window.API`):
* `API.init()`: Obtiene el token CSRF inicial desde `GET ?endpoint=csrf`.
* `API.get(endpoint, params)`: Petición GET autenticada con parámetros query serializados.
* `API.post(endpoint, data)`: Envía datos JSON con cabecera `Content-Type: application/json` y `X-CSRF-Token`.
* `API.put(endpoint, id, data)`: Modificación de registros con validación CSRF.
* `API.delete(endpoint, id)`: Eliminación lógica o física con verificación de permisos.
* `API.upload(endpoint, formData)`: **Nuevo método para multipart/form-data**. Permite enviar objetos `FormData` con archivos binarios (ej. restauración de base de datos) omitiendo deliberadamente la cabecera manual `Content-Type` para que el navegador inyecte el delimitador `boundary` correcto junto al `X-CSRF-Token`.
* `API.download(endpoint, params, filename)`: Descarga por streaming seguro binario creando un Blob temporal revocable (`URL.createObjectURL`).

#### Filtro Antifallos (`_handleResponse`):
```javascript
async _handleResponse(response) {
    const text = await response.text();
    // Detección estricta de volcados de error en PHP (HTML)
    if (text.trim().startsWith('<')) {
        console.error('[UPTEC] Respuesta HTML de error recibida:', text.substring(0, 300));
        const error = new Error('Error del servidor. Revise la consola para más detalles.');
        error.status = response.status;
        error.htmlResponse = true;
        throw error;
    }
    const data = JSON.parse(text);
    if (!response.ok) {
        throw new Error(data.error || data.message || 'Error en la petición');
    }
    return data;
}
```

---

### 4.2. Módulo de Autenticación de Interfaz (`auth.js`)
Controla la sesión en el cliente utilizando `localStorage` para almacenamiento ligero de datos del usuario autenticado (`uptec_user`) y token:
* `AUTH.login(email, password)`
* `AUTH.logout()`: Limpia el almacenamiento y redirige a `login.html`.
* `AUTH.check()`: Valida vigencia de sesión con el endpoint `GET ?endpoint=me`.
* `AUTH.user`: Objeto con las propiedades del usuario logueado (`nombre`, `apellidos`, `cedula`, `correo`, `rol`).
* `AUTH.getRole()`: Retorna el rol activo para la construcción del menú.

---

### 4.3. Core de Dashboard y Utilidades UI (`dashboard.js`)
Provee las herramientas visuales compartidas por todos los roles:
* **`autoInjectFavicon()`:** Función autoejecutable que inspecciona la profundidad de la URL (`window.location.pathname`) y enlaza automáticamente el isotipo en la cabecera `<head>` sin romper rutas relativas (`../../img/logo-uptec.png`).
* **`Sidebar.init(rol)`:** Construye el menú de navegación lateral según el rol del usuario utilizando los vectores del objeto `Icons`.
* **`Header.init()`:** Inicializa el avatar con las iniciales del usuario, el nombre, el rol y vincula el botón de cerrar sesión.
* **`Toast.show(message, type)`:** Notificaciones flotantes estilizadas (`success`, `error`, `warning`, `info`) con iconos SVG y temporizador de auto-cierre de 4 segundos.
* **`Modal.open(content, options)`:** Diálogos modales accesibles con bloqueo de scroll corporal, soporte de tamaños (`modal--lg`) y botones de pie configurables.
* **`ConfirmDialog.open(message, onConfirm, onCancel)`:** Diálogo emergente de advertencia para acciones destructivas utilizando `Icons.alerta(48)`.
* **`DataTable.render(containerId, data, columns, options)`:** Motor de tablas dinámicas con formato de celdas, badges de estado y botones de acción inline.
* **`Utils.escapeHtml(str)`:** Neutralizador de código HTML para prevenir inyecciones XSS en el cliente.
* **`Utils.formatDate(date)` / `Utils.formatDateTime(date)`:** Formateadores de fecha adaptados a la localización venezolana (`es-VE`).

---

## 5. Descripción Detallada de las Vistas Clave

### 5.1. Portal de Inicio Institucional (`index.html`)
Diseñado como un portal universitario formal de alta gama:
* **Cabecera Institucional:** Barra de navegación fija translúcida con el escudo de la **UPTECMS**, accesos anclados y botones directos a iniciar sesión y registro.
* **Hero Section con Identidad Fotográfica:** Encabezado con imagen de campus universitario de fondo, tipografía sobria con acentos dorados (`var(--uptec-gold)`) y tarjeta de exhibición (*Showcase*) con fotografías reales de estudiantes aprendiendo en equipo.
* **Beneficios de la Plataforma:** 6 tarjetas de características equipadas con vectores SVG institucionales y fondos suaves diferenciados.
* **Comunidad Estudiantil:** Galería fotográfica con testimonios sobre aprendizaje colaborativo, motivación constante y mentoría docente personalizada.
* **Reseña Universitaria:** Bloque histórico y filosófico con la Misión, Visión, Ubicación oficial (Antímano, Caracas) y reseña histórica del Gran Mariscal Antonio José de Sucre.
* **Cintillo Estadístico:** Contadores de carreras, cursos, egresados y años de trayectoria sobre una cinta en color azul marino profundo (`#0f172a`).

---

### 5.2. Portal de Acceso y Registro Deslizable (`login.html`)
* **Slider Deslizante con Contenedor Blanco Pulido:** Formularios sobre tarjetas blancas de alta legibilidad, eliminando los fondos saturados previos.
* **Formulario de Inicio de Sesión:** Validación con iconos SVG sutiles integrados (`✉️` $\rightarrow$ sobre vectorial, `🔒` $\rightarrow$ candado vectorial), feedback de carga con spinner y soporte para tecla *Escape*.
* **Formulario de Registro Público:**
  * Selector visual de rol con tarjetas interactivas: **Participante** (birrete SVG) o **Facilitador** (docente SVG).
  * Selector dinámico de carrera o área técnica para participantes (*Administración, Informática, Mecánica, Eléctrica, Mantenimiento, Transporte Ferroviario* o *Externo*).
  * Validación en cliente campo por campo (cédula de 6 a 9 dígitos, teléfonos nacionales con formato `04XXXXXXXXX` y contraseñas de al menos 6 caracteres con letra y número).
  * Sistema de notificaciones Toast conectado a las respuestas de la API.

---

### 5.3. Módulo de Administración (`views/administrador/`)
* **`egresados.html`:** Histórico institucional de egresados. Cuenta con buscador en la barra superior (`.header__search`), selector por curso y filtro por condición (`Aprobado`/`Reprobado`), mostrando notas definitivas y códigos de certificados emitidos.
* **`backup.html`:** Interfaz bidireccional de persistencia:
  * Tarjeta de **Exportación**: Descarga directa de archivos `.sql` generados al vuelo por streaming.
  * Tarjeta de **Restauración**: Selector de archivos `.sql`, inspección de peso ($\le 25\text{ MB}$), modal de confirmación crítica con `Icons.alerta(48)` y envío vía `API.upload()`.
* **`cursos.html`:** Mantenimiento de asignaturas con modal que permite cargar nombre, código, cupo, horas, nivel, carrera y asignar al docente facilitador.
* **`usuarios.html`:** Nómina de operadores y estudiantes con botones vectoriales de edición y desactivación segura mediante `ConfirmDialog`.
* **`auditoria.html`:** Trazabilidad forense con distintivos vectoriales coloreados por cada acción del sistema (`CREATE`, `UPDATE`, `DELETE`, `LOGIN`, `RESTORE`, `FINALIZAR_CURSO`).

---

### 5.4. Módulo de Analista Académico (`views/analista/`)
* **`verificacion.html`:** Panel de activación de asignaturas. Permite a los analistas revisar la carga horaria y docentes asignados; si el curso está en estado `'Planificado'`, presenta el botón vectorial **«Iniciar Curso»** pasándolo a `'En Curso'`. Si requiere correcciones, el botón **«A Planificado»** lo pausa de inmediato.
* **`facilitadores.html`:** Directorio del cuerpo docente que permite al analista habilitar cuentas de profesores recién registrados mediante botones vectoriales de activación.
* **`egresados.html`:** Supervisión del histórico de asignaturas concluidas y porcentaje de aprobación.

---

### 5.5. Módulo del Facilitador Docente (`views/facilitador/`)
* **`mis-cursos.html`:** 
  * Asignaturas en curso presentan el botón **«Plan de Evaluación»**, abriendo un modal interactivo con la barra de progreso acumulada del curso y el porcentaje restante para alcanzar el 100%.
  * Cursos en estado `'Finalizado'` ocultan el plan y despliegan el botón **«Lista de Egresados»**, abriendo un modal con el balance de alumnos aprobados y reprobados del grupo.
* **`estudiantes.html`:** Matriz integral de calificaciones por curso. Despliega una columna por cada evaluación del plan pedagógico, el cálculo individual del porcentaje evaluado del estudiante y su nota promedio vigesimal.
* **`calificaciones.html`:** Formulario interactivo donde el facilitador selecciona la asignatura, el estudiante matriculado y la evaluación a calificar, registrando notas entre `0.00` y `20.00` con recálculo automático en segundo plano.

---

### 5.6. Módulo del Participante / Estudiante (`views/participante/`)
* **`inscribir.html`:** Catálogo inteligente de asignaturas abiertas. Excluye automáticamente cursos ya cursados y materias que el estudiante **ya haya aprobado en el histórico de egresados**. Si el estudiante reprobó, el curso se vuelve a mostrar disponible cuando se aperture en un nuevo ciclo.
* **`mis-notas.html`:** Resumen de calificaciones obtenidas por cada asignatura inscrita con barra de avance de notas cargadas y botón para descargar el boletín oficial en PDF.
* **`certificados.html`:** Galería de certificados aprobados:
  * Resuelve de forma infalible el nombre del curso, código oficial, horas académicas y nombre del docente facilitador.
  * Botón **«Vista Previa»**: Despliega un modal que replica el anverso del diploma apaisado oficial con marco dorado, franja tricolor, datos caligráficos del alumno y firmas de las autoridades.
  * Botón **«Descargar PDF»**: Descarga el documento oficial de dos páginas emitido por `SimplePDF.php`.

---

## 6. Sistema de Diseño CSS (Design System)

**Archivo:** `frontend/css/main.css`

### 6.1. Paleta de Colores Institucionales UPTECMS
```css
:root {
    /* Identidad Principal */
    --color-primary: #1d4ed8;         /* Azul UPTECMS */
    --color-primary-dark: #1e3a8a;    /* Azul Marino Profundo */
    --color-navy: #0f172a;            /* Base oscura ejecutiva */
    
    /* Estados y Acentos */
    --color-accent: #059669;          /* Esmeralda: Aprobados y Completados */
    --color-warning: #d97706;         /* Ámbar: Pendientes y Advertencias */
    --color-danger: #dc2626;          /* Rojo: Reprobados y Eliminación */
    --color-gold: #b45309;            /* Dorado de certificaciones */
    
    /* Superficies y Neutros */
    --color-bg: #f8fafc;              /* Fondo general de la plataforma */
    --color-bg-card: #ffffff;         /* Superficie de tarjetas y modales */
    --color-bg-secondary: #f1f5f9;    /* Fondos secundarios de tablas y badges */
    --color-border: #e2e8f0;          /* Líneas divisorias estándar */
    --color-border-light: #edf2f7;    /* Rieles de barras de progreso */
    
    /* Tipografía */
    --color-text-primary: #1e293b;    /* Texto principal de alto contraste */
    --color-text-secondary: #475569;  /* Texto secundario y descriptivo */
    --color-text-muted: #64748b;      /* Metadatos, etiquetas y fechas */
}
```

### 6.2. Badges de Estado Estandarizados

| Estado Académico / Registro | Clase CSS Aplicada | Color Representativo |
|:---|:---|:---|
| **Activo / Aprobado / En Curso / Verificado** | `.badge--accent` | Fondo verde suave con texto `#047857` |
| **Planificado / Inscrito** | `.badge--primary` | Fondo azul suave con texto `#1d4ed8` |
| **En Progreso** | `.badge--info` | Fondo celeste con texto `#0284c7` |
| **Pendiente / Advertencia** | `.badge--warning` | Fondo ámbar con texto `#b45309` |
| **Reprobado / Inactivo / Cancelado** | `.badge--danger` | Fondo rojo con texto `#b91c1c` |
| **Finalizado / Abandonado / Código** | `.badge--neutral` | Fondo pizarra con texto `#475569` |

### 6.3. Breakpoints de Diseño Responsivo

```css
/* Dispositivos Móviles (Teléfonos) */
@media (max-width: 768px) {
    .sidebar { display: none; }
    .sidebar.open { display: block; position: fixed; z-index: 1000; width: 280px; }
    .grid--2, .grid--3, .grid--4 { grid-template-columns: 1fr; }
    .header__search-input { width: 140px; }
}

/* Tabletas y Pantallas Medianas */
@media (min-width: 769px) and (max-width: 1024px) {
    .sidebar { width: 220px; }
    .grid--4 { grid-template-columns: repeat(2, 1fr); }
    .grid--3 { grid-template-columns: repeat(2, 1fr); }
}

/* Equipos de Escritorio y Monitores */
@media (min-width: 1025px) {
    .sidebar { width: 260px; }
    .main { margin-left: 260px; }
}
```

---

## 7. Accesibilidad y Estándares de Usabilidad

1. **Contraste Tipográfico WCAG 2.1 AA:** Todo el texto sobre fondos claros u oscuros mantiene una relación de contraste mínima de $4.5:1$.
2. **Navegación Asistida por Teclado:** Los modales capturan el foco y permiten cierre instantáneo con la tecla `Escape`. Los formularios cuentan con secuencias de tabulación lógicas (`tabindex`).
3. **Comentarios de Carga Inmediatos:** Todas las consultas asíncronas inicializan un bloque de esqueleto visual (`.skeleton`) o un spinner SVG para evitar parpadeos y desorientación del usuario.

---
# END OF FILE `07-frontend.md`