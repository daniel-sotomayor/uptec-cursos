# START OF FILE `04-seguridad.md`

# Seguridad

## UPTECMS Cursos v2.0 - Documentación de Seguridad

---

## Visión General

El sistema **UPTECMS Cursos** implementa una arquitectura de defensa en profundidad alineada con los estándares internacionales del **OWASP Top 10** (Open Web Application Security Project) para mitigar vectores de ataque comunes en aplicaciones web institucionales.

```
┌─────────────────────────────────────────────────────────────┐
│                    CAPAS DE SEGURIDAD                       │
├─────────────────────────────────────────────────────────────┤
│ Layer 1: Transporte        → HTTPS (TLS 1.3 recomendado)    │
│ Layer 2: CORS / Headers    → Cabeceras de seguridad HTTP    │
│ Layer 3: Autenticación     → Sesiones endurecidas + Bcrypt  │
│ Layer 4: Autorización      → RBAC jerárquico + Ownership    │
│ Layer 5: CSRF Protection   → Synchronizer Token Pattern     │
│ Layer 6: Input Validation  → Validator class + Tipo nativo  │
│ Layer 7: Output Encoding   → Sanitizer PHP + Utils.escape() │
│ Layer 8: SQL Injection     → PDO Prepared Statements        │
│ Layer 9: File Inspection   → Subida estricta de .sql (25MB) │
│ Layer 10: Trazabilidad     → Auditoría total logs_actividad │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Protección contra CSRF (Cross-Site Request Forgery)

**Archivo:** `backend/security/csrf.php`

### 1.1. Implementación: Synchronizer Token Pattern
Cada sesión autenticada genera un token criptográfico pseudoaleatorio de 64 caracteres hexadecimales (256 bits de entropía). Las comparaciones se realizan con `hash_equals()` para prevenir ataques de temporización (*timing attacks*).

```php
class CSRF {
    private const TOKEN_NAME = '_csrf_token';
    private const TOKEN_LIFETIME = 3600; // 1 hora de vigencia

    public static function generateToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'time' => time()
        ];
        return $token;
    }

    public static function validate(?string $token = null): bool {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        // Validación de expiración (1 hora)
        if (time() - $_SESSION[self::TOKEN_NAME]['time'] > self::TOKEN_LIFETIME) {
            self::regenerateToken();
            return false;
        }

        $sessionToken = $_SESSION[self::TOKEN_NAME]['token'];
        return hash_equals($sessionToken, $token ?? '');
    }

    public static function regenerateToken(): string {
        return self::generateToken();
    }
}
```

### 1.2. Integración en el Cliente Frontend (`api.js`)
El cliente HTTP nativo recupera el token inicial vía `GET ?endpoint=csrf` y lo inyecta automáticamente en la cabecera `X-CSRF-Token` en todas las operaciones mutables:

```javascript
// En frontend/js/api.js
async function fetchCsrfToken() {
    try {
        const response = await fetch(`${API_BASE}?endpoint=csrf`, { credentials: 'include' });
        const data = await response.json();
        if (data.success && data.token) {
            csrfToken = data.token;
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = csrfToken;
        }
    } catch (err) {
        console.error('[UPTEC] Error obteniendo CSRF token:', err);
    }
}
```

### 1.3. Matriz de Cobertura CSRF

| Método HTTP | Endpoint / Operación | Validación CSRF |
|:---:|:---|:---:|
| **POST** | Login, Register, Cursos, Evaluaciones, Calificaciones, Restore | ✅ Requerido |
| **PUT** | Modificación de Cursos, Usuarios, Notas, Inscripciones | ✅ Requerido |
| **DELETE** | Eliminación de Cursos, Evaluaciones, Calificaciones, Usuarios | ✅ Requerido |
| **GET** | Consultas, Estadísticas, Descarga de PDFs y Respaldos | ❌ No aplica (Idempotente) |

---

## 2. Prevención de XSS (Cross-Site Scripting)

**Archivos:** `backend/security/sanitizer.php` y `frontend/js/dashboard.js`

### 2.1. Estrategia de Doble Barrera (Encoding en Backend y DOM)
Para evitar la ejecución de código JavaScript malicioso almacenado o reflejado:
1. **En Backend (`Sanitizer`):** Escapado contextual de cadenas con banderas `ENT_QUOTES | ENT_HTML5`.
2. **En Frontend (`Utils.escapeHtml`):** Cualquier dato dinámico recibido de la base de datos se inserta en el DOM pasando por una función de neutralización antes de concatenar cadenas HTML.

```php
class Sanitizer {
    public static function html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function js(string $text): string {
        return str_replace(
            ['\\', "'", '"', "\n", "\r", '&', '<', '>', '/'],
            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\x26', '\\x3c', '\\x3e', '\\x2f'],
            $text
        );
    }

    public static function email(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function int($value): int {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
}
```

```javascript
// En frontend/js/dashboard.js
const Utils = {
    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
};
```

### 2.2. Mitigación según Contexto de Inserción

| Contexto de Renderizado | Función Responsable | Ejemplo de Aplicación |
|:---|:---|:---|
| **Cuerpo HTML** | `Utils.escapeHtml()` / `Sanitizer::html()` | `<td>${Utils.escapeHtml(usuario.nombre)}</td>` |
| **Atributos HTML** | `Sanitizer::attr()` | `<input value="${Utils.escapeHtml(curso.codigo)}">` |
| **Literales en JS** | `Sanitizer::js()` | `const nombre = '${Sanitizer::js($val)}';` |
| **Direcciones URL** | `filter_var(..., FILTER_VALIDATE_URL)` | Validación de redirecciones seguras internas |

---

## 3. Prevención de Inyección SQL (SQLi)

**Implementación:** Uso estricto de **Sentencias Preparadas con PDO** en todos los modelos y consultas dinámicas.

### 3.1. Configuración Inmune del Controlador PDO
En `backend/db/config.php`, la emulación de consultas preparadas se desactiva explícitamente (`ATTR_EMULATE_PREPARES => false`). Esto garantiza que el motor MySQL compile el plan de ejecución de la consulta por separado de los parámetros de entrada:

```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // ✅ Prepared Statements reales del motor MySQL
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];
```

### 3.2. Reglas de Codificación
* **Prohibido:** Cualquier uso de concatenación de variables en consultas (`$db->query("... '$variable'")`).
* **Obligatorio:** Uso de comodines posicionales (`?`) o nombrados (`:param`) pasados en el arreglo del método `execute()`:
  ```php
  // ✅ IMPLEMENTACIÓN SEGURA
  $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = ?");
  $stmt->execute([$correo, 1]);
  ```
* **Búsquedas LIKE con Wildcards Sanitizados:**
  ```php
  $searchSanitized = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $buscar);
  $stmt = $db->prepare("SELECT * FROM cursos WHERE nombre LIKE ?");
  $stmt->execute(["%{$searchSanitized}%"]);
  ```

---

## 4. Seguridad en Contraseñas

**Archivos:** `backend/security/auth.php` y `backend/security/validator.php`

### 4.1. Hashing Criptográfico con Bcrypt (Cost 12)
No se almacenan contraseñas en texto plano. Se emplea `PASSWORD_BCRYPT` con un factor de coste de **12** ($2^{12} = 4.096$ iteraciones de derivación de claves):

```php
// Creación de hash seguro
$hash = password_hash($passwordPlana, PASSWORD_BCRYPT, ['cost' => 12]);

// Verificación en login
if (password_verify($passwordIngresada, $user['password'])) {
    // Si la versión de PHP actualiza algoritmos recomendados, se re-hashea en caliente
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
        self::updatePassword($user['id'], $passwordIngresada);
    }
}
```

### 4.2. Políticas de Complejidad de Contraseña
Validadas en `backend/security/validator.php` y verificadas en cliente:
* Longitud mínima: **6 caracteres**.
* Contenido obligatorio: Al menos **una letra** (`[A-Za-z]`) y al menos **un número** (`[0-9]`).
* Verificación doble: Campo de confirmación coincidente en registro y cambio de clave.

---

## 5. Seguridad de Sesiones y Protección contra Fuerza Bruta

**Archivo:** `backend/security/auth.php`

### 5.1. Directivas de Endurecimiento de Cookies de Sesión
Configuradas en `Auth::init()` antes de invocar `session_start()`:

```php
public static function init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');      // ⛔ Inaccesible desde JavaScript (mitiga robo por XSS)
        ini_set('session.use_strict_mode', '1');      // 🛡️ Previene fijación de sesión (Session Fixation)
        ini_set('session.cookie_samesite', 'Strict');  // 🚫 Bloquea envío de cookie en peticiones entre sitios
        ini_set('session.gc_maxlifetime', '1800');    // ⏱️ Expiración de recolección de basura (30 minutos)
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');    // 🔒 Transmisión exclusiva bajo canal HTTPS
        }
        
        session_start();
    }
}
```

### 5.2. Mitigación de Fuerza Bruta en Login
* **Límite:** Máximo **5 intentos fallidos** consecutivos por dirección IP.
* **Penalización:** Bloqueo temporal de autenticación durante **15 minutos (900 segundos)**:

```php
private const MAX_LOGIN_ATTEMPTS = 5;
private const LOCKOUT_TIME = 900;

private static function checkBruteForce(string $ip): bool {
    $key = '_login_attempts_' . md5($ip);
    if (isset($_SESSION[$key])) {
        if ($_SESSION[$key]['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            if (time() - $_SESSION[$key]['time'] < self::LOCKOUT_TIME) {
                return true; // Bloqueado
            }
            unset($_SESSION[$key]); // Expiró el bloqueo
        }
    }
    return false;
}
```

### 5.3. Expiración por Inactividad
Si transcurren más de 30 minutos sin que el usuario interactúe con el backend, la sesión es destruida automáticamente en el servidor y la siguiente petición devuelve `HTTP 401 Unauthorized`.

---

## 6. Validación de Entradas y Lógica de Negocio

**Archivo:** `backend/security/validator.php`

### 6.1. Métodos de Validación Disponibles

| Método | Validación Aplicada | Regla Técnica |
|:---|:---|:---|
| `email()` | Formato de correo institucional | `FILTER_VALIDATE_EMAIL`. Flag opcional `@uptec.edu.ve` obligatorio para staff. |
| `cedula()` | Cédula de identidad venezolana | Numérica, longitud entre 6 y 9 dígitos (`^\d{6,9}$`). |
| `telefono()` | Teléfono celular venezolano | Prefijo `0412`, `0414`, `0424`, `0416`, `0426` seguido de 7 dígitos. |
| `name()` | Nombres y Apellidos | 2 a 100 caracteres alfabéticos, espacios y tildes. |
| `date()` | Fechas de inicio, fin y evaluaciones | Formato ISO `YYYY-MM-DD` válido cronológicamente. |
| `rol()` | Rol en el sistema | Coincidencia en lista blanca: `Participante`, `Facilitador`, `Analista`, `Administrador`. |
| `nota()` | Calificación académica | Escala numérica vigesimal entre `0.00` y `20.00`. |
| `decimal()` | Ponderaciones porcentuales | Número flotante positivo entre límites definidos. |

### 6.2. Blindaje Matemático en Planes de Evaluación
Al crear una evaluación (`POST ?endpoint=evaluacion`), el servidor ejecuta una comprobación en base de datos independiente de las entradas del cliente:
```php
$stmtPeso = $db->prepare("SELECT COALESCE(SUM(peso), 0) FROM evaluaciones WHERE curso_id = ? AND (activo = 1 OR activo IS NULL)");
$stmtPeso->execute([$cursoId]);
$acumulado = (float)$stmtPeso->fetchColumn();

if (($acumulado + $nuevoPeso) > 100.01) {
    http_response_code(422);
    return jsonResponse(false, null, "La ponderación total superaría el 100%. Disponible: " . (100 - $acumulado) . "%");
}
```

---

## 7. Cabeceras HTTP de Seguridad (Security Headers)

Inyectadas globalmente en `backend/api/api.php` para todas las respuestas emitidas por el sistema:

```php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');                    // ⛔ Prohíbe al navegador interpretar MIME types erróneos
header('X-Frame-Options: DENY');                               // 🖼️ Bloquea Clickjacking impidiendo iframes externos
header('X-XSS-Protection: 1; mode=block');                    // 🛡️ Activa el filtro heurístico anti-XSS del navegador
header('Referrer-Policy: strict-origin-when-cross-origin');   // 🔗 Limita fugas de URLs en cabeceras Referer
header('Permissions-Policy: geolocation=(), camera=(), microphone=()'); // 🚫 Restringe APIs innecesarias del navegador
```

---

## 8. Control de Acceso y Autorización Basado en Roles (RBAC)

**Archivo:** `backend/security/auth.php`

### 8.1. Jerarquía de Privilegios

```
[Nivel 4] Administrador  ──► Control total, usuarios, backup, auditoría
            │
[Nivel 3] Analista       ──► Gestión curricular, asignación de docentes, control de egresados
            │
[Nivel 2] Facilitador    ──► Plan de evaluación, notas de sus alumnos, egresados de sus cursos
            │
[Nivel 1] Participante   ──► Inscripción personal, consulta de notas propias y certificados
```

### 8.2. Verificación de Titularidad de Recursos (Resource Ownership)
Además de validar el nivel de rol en el middleware, el sistema verifica que el usuario sea el **propietario o docente asignado** al recurso antes de autorizar modificaciones:
* **Facilitadores:** No pueden agregar evaluaciones ni asentar calificaciones en asignaturas donde no sean el docente titular asignado (`c.facilitador_id === currentUser.id`).
* **Participantes:** No pueden consultar certificados ni inscribir materias a nombre de terceros (`usuario_id === currentUser.id`).

---

## 9. Auditoría y Trazabilidad Forense

**Archivo:** `backend/security/auth.php` $\rightarrow$ Tabla: `logs_actividad`

Toda mutación de datos en el sistema ejecuta `Auth::logActivity()`, almacenando de forma inmutable:
* Identificador y nombre del operador.
* Acción normalizada: `CREATE`, `UPDATE`, `DELETE`, `LOGIN`, `LOGOUT`, `RESTORE`, `BACKUP`, `FINALIZAR_CURSO`, `PASSWORD_CHANGE`, `VERIFY`.
* Snapshot en JSON de los datos anteriores (`datos_anteriores`) y nuevos (`datos_nuevos`).
* Dirección IP cliente (`REMOTE_ADDR`) y agente de usuario (`HTTP_USER_AGENT`).
* Timestamp transaccional del servidor.

---

## 10. Seguridad en Subida de Archivos y Restauración (`.sql`)

A partir de la versión 2.0, el sistema cuenta con el endpoint **`POST ?endpoint=restore`** para restaurar bases de datos. Dado que la ejecución de scripts SQL es una operación de máximo riesgo, se implementó un **protocolo de aislamiento y validación en 5 pasos**:

```
[Cliente Admin envía FormData]
               │
               ▼
1. Validación de Roles y CSRF: requireAdmin() + validateCsrf()
               │
               ▼
2. Validación de Carga Útil:
   - Verificación de error de subida: $_FILES['backup_file']['error'] === UPLOAD_ERR_OK
   - Tamaño máximo restringido: <= 25 MB
   - Extensión obligatoria en lista blanca: .sql estricto
               │
               ▼
3. Inspección de Contenido (Sanity Check):
   - Lectura del archivo en memoria temporal (no se almacena en directorios públicos)
   - Comprobación heurística de sintaxis: preg_match('/(CREATE|INSERT|DROP|TABLE)/i', $content)
               │
               ▼
4. Ejecución Controlada en Aislamiento:
   - SET FOREIGN_KEY_CHECKS = 0; (previene bloqueos por orden de dependencias)
   - División segura de sentencias delimitadas
   - SET FOREIGN_KEY_CHECKS = 1; (restauración obligatoria de integridad)
               │
               ▼
5. Registro de Auditoría:
   - Asienta evento 'RESTORE' en logs_actividad con total de sentencias ejecutadas
```

---

## 11. Matriz de Cobertura de Amenazas OWASP Top 10

| Vulnerabilidad OWASP | Mecanismo de Mitigación Implementado | Archivos Clave |
|:---|:---|:---|
| **A01: Broken Access Control** | Jerarquía RBAC (1 a 4), verificación de titularidad y middlewares | `auth.php`, `api.php` |
| **A02: Cryptographic Failures** | Bcrypt cost 12, hashes SHA-256 internos y canal HTTPS | `auth.php`, `csrf.php` |
| **A03: Injection (SQL / Command)** | PDO Prepared Statements sin emulación, parseo de SQL sin comandos shell | `config.php`, `backup.php` |
| **A04: Insecure Design** | Blindaje de evaluaciones al 100%, motor de cierre atómico | `cursos.php`, `validator.php` |
| **A05: Security Misconfiguration** | Cabeceras de seguridad estrictas, manejo global de errores con JSON | `api.php` |
| **A06: Vulnerable Components** | Arquitectura **Zero-Dependencies** (sin librerías vulnerables de terceros) | Todo el proyecto |
| **A07: Identification & Auth** | Bloqueo por fuerza bruta (5 intentos/15 min), cookies HttpOnly/Strict | `auth.php` |
| **A08: Software & Data Integrity** | Tokens CSRF sincronizados, validación de archivos `.sql` en memoria | `csrf.php`, `backup.php` |
| **A09: Security Logging Failures** | Bitácora forense de auditoría inmutable en base de datos | `auth.php`, `reportes.php` |
| **A10: SSRF** | El sistema no realiza peticiones HTTP salientes hacia servicios externos | `api.php` |

---

## 12. Recomendaciones de Despliegue en Servidor de Producción

1. **Forzado de HTTPS en Servidor Web:**
   Configurar redirección obligatoria HTTP $\rightarrow$ HTTPS en Apache (`.htaccess`) o Nginx:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
2. **Ocultamiento de Información de PHP:**
   En el archivo `php.ini` del servidor de producción:
   ```ini
   expose_php = Off
   display_errors = Off
   log_errors = On
   error_log = /var/log/php_errors.log
   ```
3. **Límites de Carga para Restauración de Respaldos:**
   Asegurar que la configuración de PHP admita archivos `.sql` de hasta 25 MB:
   ```ini
   upload_max_filesize = 25M
   post_max_size = 30M
   max_execution_time = 300
   memory_limit = 256M
   ```
4. **Permisos de Archivos en Sistema Operativo:**
   * Directorios con permisos `755` (`drwxr-xr-x`).
   * Archivos PHP con permisos `644` (`-rw-r--r--`).
   * Carpeta `backend/assets/` con permisos de solo lectura para el usuario de Apache/Nginx (`www-data`).

---
# END OF FILE `04-seguridad.md`