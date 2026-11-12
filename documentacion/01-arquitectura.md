# START OF FILE `01-arquitectura.md`

# Arquitectura del Sistema

## UPTECMS Cursos v2.0 - Documentación de Arquitectura

---

## 1. Visión General

El sistema **UPTECMS Cursos** implementa una **arquitectura monolítica modular por capas** con estricta separación de responsabilidades (*Separation of Concerns*). Está diseñado bajo una premisa estricta de **cero dependencias externas (Zero-Dependencies)**: no utiliza frameworks pesados en backend ni en frontend, corriendo de forma ultraligera sobre cualquier servidor estándar con **PHP 8.0+** y **MySQL 5.7+**.

Para consultar los esquemas visuales, diagramas Entidad-Relación y flujos de secuencia, revise `08-diagramas.md`.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                               │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────────┐  │
│  │   Landing Page   │  │  Login / Registro│  │    Dashboard Views    │  │
│  │   (index.html)   │  │   (login.html)   │  │   (views/{rol}/*.html)│  │
│  └──────────────────┘  └──────────────────┘  └───────────────────────┘  │
│                           │         │                                   │
│              ┌────────────┴─────────┴─────────────┐                     │
│              ▼                                    ▼                     │
│     ┌──────────────────┐                ┌───────────────────────┐       │
│     │   api.js Client  │                │  dashboard.js (Icons) │       │
│     │ (JSON/Multipart) │                │  (Modals, Toasts, UI) │       │
│     └──────────────────┘                └───────────────────────┘       │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼ (HTTP REST / JSON / Multipart / CSRF)
┌─────────────────────────────────────────────────────────────────────────┐
│                        API LAYER (RESTful Router)                       │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────────┐  │
│  │      Router      │  │    Endpoints     │  │      Middleware       │  │
│  │   (api.php)      │  │ (*.php Handlers) │  │  (Auth, CSRF, RBAC)   │  │
│  └──────────────────┘  └──────────────────┘  └───────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     BUSINESS LOGIC & SERVICES LAYER                     │
│  ┌───────────────┐  ┌───────────────┐  ┌─────────────┐  ┌────────────┐  │
│  │     Auth      │  │   Validator   │  │  Sanitizer  │  │  SimplePDF │  │
│  │  (auth.php)   │  │(validator.php)│  │(sanitizer.p)│  │ (libs/*.php│  │
│  └───────────────┘  └───────────────┘  └─────────────┘  └────────────┘  │
│                           │                                             │
│                           ▼                                             │
│       ┌──────────────────────────────────────────────┐                  │
│       │ Course Lifecycle & Egress Engine             │                  │
│       │ (procesarEgresoCurso, verificarVencidos)     │                  │
│       └──────────────────────────────────────────────┘                  │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼ (Prepared Statements / Transactions)
┌─────────────────────────────────────────────────────────────────────────┐
│                         DATA ACCESS LAYER (DAL)                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────────┐  │
│  │ Database (PDO)   │  │  MySQL Database  │  │  Foreign Keys, Views  │  │
│  │ (db/config.php)  │  │  (uptec_cursos)   │  │  & Automatic Triggers │  │
│  └──────────────────┘  └──────────────────┘  └───────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Patrones de Diseño Implementados

### 2.1. Singleton Pattern (Acceso a Datos)
* **Archivo:** `backend/db/config.php`
* **Implementación:** Garantiza una única instancia compartida de conexión `PDO` durante todo el ciclo de vida de la petición HTTP, previniendo sobrecarga en el pool de conexiones de MySQL.
* **Configuración:** Establece `ERRMODE_EXCEPTION`, desactiva emulación de consultas preparadas (`ATTR_EMULATE_PREPARES => false`) y fija la zona horaria a `America/Caracas` (`UTC-4`).

```php
class Database {
    private static ?self $instance = null;
    private PDO $connection;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function getConnection(): PDO { return $this->connection; }
}
function getDB(): PDO { return Database::getInstance()->getConnection(); }
```

---

### 2.2. Router Pattern (Enrutamiento Declarativo)
* **Archivo:** `backend/api/api.php`
* **Implementación:** Mapea el método HTTP (`GET`, `POST`, `PUT`, `DELETE`) y el parámetro de consulta `?endpoint={nombre}` hacia funciones controladoras (`handlers`) desacopladas.

```php
class Router {
    private array $routes = [];
    public function register(string $method, string $pattern, callable $handler, array $middleware = [], string $endpoint = ''): void;
    public function dispatch(): void;
}
```

---

### 2.3. Middleware Chain Pattern (Seguridad y Control de Acceso)
* **Archivo:** `backend/api/api.php`
* **Implementación:** Funciones de intercepción encadenadas que se ejecutan antes del controlador. Si una condición falla (ej. sesión ausente, CSRF inválido o permisos insuficientes), la cadena corta la ejecución y responde inmediatamente con un código HTTP y JSON de error.

```php
function requireAuth(): callable
function requireAdmin(): callable
function requireAnalystOrAbove(): callable
function requireTeacherOrAbove(): callable
function requireRole(string $role): callable
function validateCsrf(): callable
```

---

### 2.4. Centralized SVG Vector Catalog Pattern (UI Icons)
* **Archivo:** `frontend/js/dashboard.js`
* **Implementación:** Objeto global `Icons` que almacena cadenas vectoriales SVG puras con `stroke="currentColor"`. Los componentes UI consumen directamente `Icons.dashboard`, `Icons.cursos`, `Icons.vacio(size)`, etc., eliminando la necesidad de librerías de terceros (FontAwesome, Lucide externa) y erradicando completamente los emojis en favor de una estética profesional universitaria.

---

### 2.5. Lazy Trigger / Reactive Lifecycle Engine (Cierre de Cursos)
* **Archivo:** `backend/api/cursos.php`
* **Implementación:** Al no requerir obligatoriamente tareas *Cron* en servidores compartidos, el backend incorpora un verificador reactivo que se activa en consultas a cursos. Evalúa si `fecha_fin <= CURDATE()` en asignaturas activas y ejecuta de forma atómica la consolidación de notas, la migración a la tabla `egresados` y la liberación de inscripciones activas.

---

## 3. Estructura Detallada de Directorios

```
uptec-cursos/
│
├── backend/                             # Capa de lógica, APIs y persistencia
│   ├── api/                             # Controladores RESTful registrados en el Router
│   │   ├── api.php                      # Enrutador central, CORS, cabeceras y dispatcher
│   │   ├── auth.php                     # Endpoints: login, logout, register, me, password
│   │   ├── usuarios.php                 # Endpoints: CRUD usuarios, facilitadores, analistas
│   │   ├── cursos.php                   # Endpoints: CRUD cursos, evaluaciones, egresados-curso
│   │   ├── inscripciones.php            # Endpoints: inscripción, cursos-disponibles, anulación
│   │   ├── calificaciones.php           # Endpoints: notas, promedio, desglose por estudiante
│   │   ├── reportes.php                 # Endpoints: resumen, estadísticas, certificados, PDFs
│   │   └── backup.php                   # Endpoints: exportación (GET) y restauración (POST .sql)
│   │
│   ├── assets/                          # Recursos gráficos del backend
│   │   └── certificado-plantilla.jpg    # Plantilla gráfica oficial del certificado de aprobación
│   │
│   ├── libs/                            # Librerías nativas del sistema (sin vendor/composer)
│   │   └── SimplePDF.php                # Motor PDF nativo: modo Landscape, imágenes JPG y WinAnsi
│   │
│   ├── security/                        # Capa de seguridad y sanitización
│   │   ├── auth.php                     # Clase Auth: sesiones seguras, bcrypt cost 12, logs de auditoría
│   │   ├── csrf.php                     # Clase CSRF: Synchronizer Token Pattern
│   │   ├── sanitizer.php                # Clase Sanitizer: prevención de XSS y casteo estricto
│   │   └── validator.php                # Clase Validator: validación de formatos, correos y fechas
│   │
│   └── db/                              # Conexión a Base de Datos
│       └── config.php                   # Singleton Database, configuración PDO y zona horaria
│
├── db/                                  # Persistencia y esquemas
│   ├── schema.sql                       # DDL completo: tablas, índices, vistas y triggers
│   └── seed.sql                         # Datos semilla con roles y asignaturas base
│
├── frontend/                            # Capa de presentación (Vanilla Stack)
│   ├── index.html                       # Portal de inicio institucional UPTECMS (Lobby)
│   ├── login.html                       # Interfaz deslizable de inicio de sesión y registro público
│   │
│   ├── css/
│   │   ├── main.css                     # Sistema de diseño de dashboards, tablas y modales
│   │   └── login.css                    # Estilos específicos del login y slider
│   │
│   ├── img/                             # Logotipos institucionales
│   │   ├── logo-uptec.png               # Isotipo institucional y favicon
│   │   ├── logo-uptec-grande.png        # Logo de alta resolución para cabeceras y sidebar
│   │   └── logo-uptec-grande-v2.png     # Variante institucional
│   │
│   ├── js/                              # Módulos JavaScript compartidos
│   │   ├── api.js                       # Cliente HTTP Fetch: GET, POST, PUT, DELETE, download, upload
│   │   ├── auth.js                      # Manejador de estado de sesión en localStorage y verificación
│   │   └── dashboard.js                 # UI Core: Sidebar dinámico, Header, Modales, Toast, Icons SVG
│   │
│   └── views/                           # Módulos de interfaz segmentados por rol (RBAC)
│       ├── administrador/               # Rol Nivel 4 (Control Total)
│       │   ├── dashboard.html           # Panel con métricas generales, cursos y usuarios recientes
│       │   ├── usuarios.html            # Gestión completa de usuarios y credenciales temporales
│       │   ├── egresados.html           # Histórico general de egresados con filtros
│       │   ├── cursos.html              # Catálogo de asignaturas, creación y asignación docente
│       │   ├── inscripciones.html       # Auditoría de inscripciones y estados
│       │   ├── calificaciones.html      # Supervisión de notas asentadas en el sistema
│       │   ├── reportes.html            # Informes estadísticos y descarga de informe PDF
│       │   ├── auditoria.html           # Visor de traza transaccional (logs_actividad)
│       │   └── backup.html              # Panel bidireccional: Exportar e Importar (.sql)
│       │
│       ├── analista/                    # Rol Nivel 3 (Gestión Curricular)
│       │   ├── dashboard.html           # Panel de control de cursos y facilitadores
│       │   ├── cursos.html              # Verificación curricular y asignación de docentes
│       │   ├── egresados.html           # Histórico de egreso y rendimiento curricular
│       │   ├── facilitadores.html       # Control de disponibilidad y habilitación de facilitadores
│       │   ├── usuarios.html            # Directorio supervisado de participantes y facilitadores
│       │   ├── verificacion.html        # Control de activación ("En Curso" <-> "Planificado")
│       │   └── reportes.html            # Reportes académicos de la oferta formativa
│       │
│       ├── facilitador/                 # Rol Nivel 2 (Docente)
│       │   ├── dashboard.html           # Panel docente con carga académica y alumnos a cargo
│       │   ├── mis-cursos.html          # Cursos asignados, planes de evaluación y egresados
│       │   ├── estudiantes.html         # Lista de estudiantes por curso con barra de progreso
│       │   ├── calificaciones.html      # Registro y actualización de notas por evaluación
│       │   └── reportes.html            # Rendimiento y distribución de notas de sus cursos
│       │
│       └── participante/                # Rol Nivel 1 (Estudiante)
│           ├── dashboard.html           # Panel personal, promedio general y avance
│           ├── inscribir.html           # Catálogo de asignaturas disponibles por carrera
│           ├── mis-cursos.html          # Cursos cursados actualmente
│           ├── mis-notas.html           # Desglose de evaluaciones parciales y boletín PDF
│           └── certificados.html        # Galería de diplomas oficiales y descarga PDF
│
└── documentacion/                       # Especificaciones técnicas y manuales de usuario
    ├── 00-resumen-ejecutivo.md
    ├── 01-arquitectura.md
    ├── 02-base-de-datos.md
    ├── 03-api-endpoints.md
    ├── 04-seguridad.md
    ├── 05-roles-permisos.md
    ├── 06-instalacion.md
    ├── 07-frontend.md
    ├── 08-diagramas.md
    ├── 09-pruebas.md
    ├── manual-administrador.md
    ├── manual-analista.md
    ├── manual-facilitador.md
    └── manual-participante.md
```

---

## 4. Flujos de Datos Transaccionales

### 4.1. Ciclo de Cierre Lectivo y Transición a Egresados

```
[Consulta / Interacción con Cursos]
               │
               ▼
   verificarCursosVencidos()
               │
   ¿Existen cursos donde fecha_fin <= CURDATE() y estado != 'Finalizado'?
               ├── NO ──> Continúa flujo regular
               └── SÍ ──> procesarEgresoCurso($cursoId)
                              │
               ┌──────────────┴──────────────────────────┐
               │ 1. Transacción Inicia (BEGIN)            │
               │ 2. Calcula notas finales de alumnos     │
               │ 3. Inserta en tabla 'egresados':        │
               │    - Estado: Aprobado / Reprobado       │
               │    - Genera código de certificado       │
               │    - Guarda snapshot del facilitador    │
               │ 4. DELETE FROM inscripciones            │
               │    (Libera matrícula y UNIQUE constraint│
               │     para permitir re-inscripción futura)│
               │ 5. UPDATE cursos SET estado='Finalizado'│
               │ 6. Registra log 'FINALIZAR_CURSO'       │
               │ 7. Transacción Confirma (COMMIT)        │
               └─────────────────────────────────────────┘
```

---

### 4.2. Generación Nativa de Certificados Oficiales

```
Participante solicita: GET /api.php?endpoint=certificado&id=X
               │
               ▼
      Middleware: requireAuth() + Verificación de Titularidad
               │
               ▼
Consulta datos en tabla 'egresados' (Solo si estado_academico = 'Aprobado')
               │
               ▼
Instancia SimplePDF con orientación Horizontal ('L'):
  ├── PÁGINA 1 (Anverso):
  │     1. Estampa imagen de fondo: backend/assets/certificado-plantilla.jpg
  │     2. Configura /WinAnsiEncoding (soporta tildes y ñ sin alteración)
  │     3. Centra nombre del alumno en Times-BoldItalic sobre línea dorada
  │     4. Imprime Cédula, Nombre del Curso y Fecha formal
  │     5. Alinea firmas oficiales de Rector y Facilitador bajo sus trazos
  └── PÁGINA 2 (Reverso / Pensum):
        1. Dibuja marco perimetral dorado con SimplePDF::rect()
        2. Dibuja tabla de Unidades Curriculares / Módulos
        3. Dibuja cuadro resumen con Horas Teóricas, Prácticas y Calificación
               │
               ▼
Stream binario directo al navegador (Content-Type: application/pdf)
```

---

### 4.3. Respaldo y Restauración Bidireccional (`.sql`)

```
[ADMINISTRADOR]
   │
   ├── EXPORTAR (GET ?endpoint=backup)
   │     1. Lee lista de tablas del esquema `uptec_cursos`
   │     2. Genera sentencias DROP TABLE, CREATE TABLE e INSERTs
   │     3. Desactiva y reactiva temporalmente FOREIGN_KEY_CHECKS
   │     4. Transmite archivo SQL por streaming seguro
   │
   └── RESTAURAR (POST ?endpoint=restore con FormData)
         1. Recibe archivo multipart vía API.upload()
         2. Valida extensión .sql y tamaño máximo (≤ 25 MB)
         3. Inspecciona sintaxis SQL básica
         4. Setea SET FOREIGN_KEY_CHECKS = 0;
         5. Ejecuta sentencias SQL en lote
         6. Restaura SET FOREIGN_KEY_CHECKS = 1;
         7. Registra evento RESTORE en logs_actividad
         8. Retorna JSON de éxito y fuerza recarga del cliente
```

---

## 5. Convenciones de Código y Estándares

### 5.1. PHP (Backend)
* **Versión mínima:** PHP 8.0.
* **Manejo de Errores:** Bloques `try ... catch (Throwable $e)` para evitar fugas de traza HTML hacia el cliente API.
* **Estándar de Nombres:**
  * Clases: `PascalCase` (`Database`, `Sanitizer`, `SimplePDF`).
  * Métodos y funciones: `camelCase` (`getDB()`, `procesarEgresoCurso()`).
  * Constantes y directivas: `UPPER_SNAKE_CASE` (`SESSION_USER`).
* **Zona Horaria Oficial:** `America/Caracas` (GMT-4).

### 5.2. JavaScript (Frontend)
* **Estándar:** Vanilla ECMAScript 6+ (sin compiladores como Babel ni empaquetadores como Webpack).
* **Manejo Asíncrono:** `async/await` nativo sobre `Fetch API`.
* **Seguridad DOM:** Inyección de variables dinámicas sanitizadas mediante `Utils.escapeHtml()`.
* **Iconografía:** Consumo del objeto centralizado `Icons.{nombre}` ubicado en `dashboard.js`.

### 5.3. CSS
* **Arquitectura:** Metodología adaptada BEM (*Block-Element-Modifier*) sobre variables CSS nativas en `frontend/css/main.css`.
* **Colores Institucionales:**
  * `--uptec-navy: #0f172a` (Base oscura ejecutiva).
  * `--uptec-blue: #1d4ed8` / `--uptec-blue-dark: #1e3a8a` (Azul institucional UPTECMS).
  * `--uptec-gold: #d97706` / `--uptec-gold-light: #fbbf24` (Acentos de excelencia y medallas).
  * `--uptec-emerald: #059669` (Aprobados y estados completados).

---

## 6. Dependencias Externas (Política Zero-Dependencies)

El sistema opera con **cero librerías externas**:

| Módulo / Función | Solución Convencional | Solución Implementada en UPTECMS |
| :--- | :--- | :--- |
| **Peticiones HTTP** | Axios / jQuery AJAX | **Fetch API nativo** (`api.js`) |
| **Iconografía** | FontAwesome / Bootstrap Icons / CDN | **Catálogo SVG puro embebido** (`Icons` en `dashboard.js`) |
| **Generación PDF** | DomPDF / TCPDF / mPDF | **Librería nativa ligera `SimplePDF.php`** |
| **Componentes UI** | Bootstrap / Tailwind / React | **CSS3 Grid/Flexbox + Vanilla JS** (`Modal`, `Toast`, `DataTable`) |
| **Base de Datos** | Doctrine / Eloquent ORM | **PDO Prepared Statements nativo** |

---

## 7. Escalabilidad y Límites Operativos

| Parámetro | Límite Configurado | Ubicación de Control |
| :--- | :--- | :--- |
| **Tiempo de Inactividad de Sesión** | 30 minutos | `backend/security/auth.php` |
| **Intentos de Login Fallidos** | 5 intentos (Bloqueo de 15 min) | `backend/security/auth.php` |
| **Vigencia del Token CSRF** | 3600 segundos (1 hora) | `backend/security/csrf.php` |
| **Límite de Logs en Consulta** | 500 registros recientes | `backend/api/reportes.php` |
| **Tamaño Máximo de Restauración SQL** | 25 MB | `backend/api/backup.php` |
| **Tiempo Máximo de Script Backup/Restore**| 300 segundos (5 minutos) | `set_time_limit(300)` en `backup.php` |

---
# END OF FILE `01-arquitectura.md`