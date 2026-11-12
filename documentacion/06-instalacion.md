# START OF FILE `06-instalacion.md`

# Guía de Instalación y Puesta en Marcha

## UPTECMS Cursos v2.0 - Instalación en Entornos Locales (XAMPP / Windows) y Servidores de Producción

---

## 1. Requisitos del Sistema

### 1.1. Software Base

| Componente | Versión Mínima Requerida | Propósito y Módulos Necesarios |
|:---|:---:|:---|
| **Servidor Web** | Apache 2.4+ / Nginx 1.18+ | Servidor HTTP con soporte de `mod_rewrite` y cabeceras personalizadas. |
| **Intérprete PHP** | PHP 8.0 o superior | Extensiones obligatorias: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `session`. |
| **Base de Datos** | MySQL 5.7+ / MariaDB 10.4+ | Motor **InnoDB** obligatorio (claves foráneas, vistas y triggers). |
| **Navegador Web** | Chrome 90+, Firefox 88+, Edge 90+ | Soporte de ECMAScript 6+, Fetch API nativa y CSS Grid/Flexbox. |

### 1.2. Requisitos de Hardware Recomendados

| Recurso | Entorno Local / Desarrollo | Servidor de Producción Institucional |
|:---|:---:|:---:|
| **Memoria RAM** | 2 GB mínimo | 4 GB a 8 GB (garantiza holgura para imports SQL grandes) |
| **Almacenamiento** | 1 GB de espacio libre | 10 GB+ SSD (previendo copias de seguridad acumuladas) |
| **Procesador** | Dual-Core (2.0 GHz) | Quad-Core o superior |

---

## 2. Instalación Paso a Paso en XAMPP (Windows)

### Paso 1: Descargar e Instalar XAMPP
1. Descargar el instalador de XAMPP con **PHP 8.0 o superior** desde el sitio oficial: [https://www.apachefriends.org](https://www.apachefriends.org).
2. Ejecutar el instalador con privilegios de Administrador.
3. En la selección de componentes, verificar que estén seleccionados:
   - ✅ **Apache**
   - ✅ **MySQL**
   - ✅ **PHP**
   - ✅ **phpMyAdmin**
4. Seleccionar la ruta de instalación por defecto: `C:\xampp`.
5. Completar la instalación.

---

### Paso 2: Iniciar los Servicios del Servidor
1. Abrir el **XAMPP Control Panel**.
2. Hacer clic en **Start** para el módulo **Apache**.
3. Hacer clic en **Start** para el módulo **MySQL**.
4. Confirmar que ambos servicios muestren el fondo verde y sus respectivos puertos activos (`80/443` para Apache y `3306` para MySQL).

```
┌────────────────────────────────────────────────────────┐
│               XAMPP Control Panel v3.3+                │
├────────────────────────────────────────────────────────┤
│  Módulo      │  PID(s)    │  Puerto(s)  │  Estado      │
│  Apache      │  1042 321  │  80, 443    │  🟢 Running  │
│  MySQL       │  4520      │  3306       │  🟢 Running  │
└────────────────────────────────────────────────────────┘
```

---

### Paso 3: Despliegue de los Archivos del Proyecto
1. Descomprimir o clonar el repositorio del proyecto.
2. Copiar la carpeta completa con el nombre **`uptec-cursos`** dentro de la raíz web de XAMPP:
   ```text
   C:\xampp\htdocs\uptec-cursos\
   ```
3. Verificar que la ruta no contenga carpetas anidadas redundantes (ej. evitar `C:\xampp\htdocs\uptec-cursos\uptec-cursos\`).

---

### Paso 4: Ajustes Críticos en la Configuración de PHP (`php.ini`)
Para garantizar que la **restauración de bases de datos** (`POST ?endpoint=restore`), la emisión de certificados PDF y las operaciones de auditoría funcionen sin bloqueos por límites de memoria o tiempo de ejecución, es **obligatorio** ajustar estas directivas en el archivo `php.ini`.

1. En el **XAMPP Control Panel**, en la fila de **Apache**, hacer clic en **Config** $\rightarrow$ **PHP (php.ini)**.
2. Buscar y modificar las siguientes líneas:

```ini
; 1. Tiempo máximo de ejecución para scripts pesados (Respaldos y Restauraciones)
max_execution_time = 300
max_input_time = 300

; 2. Límite de memoria suficiente para procesar scripts SQL en memoria
memory_limit = 256M

; 3. Tamaños máximos para permitir la subida del backup SQL (hasta 25 MB)
post_max_size = 30M
upload_max_filesize = 25M

; 4. Zona horaria oficial de Venezuela
date.timezone = "America/Caracas"
```

3. Guardar el archivo (`Ctrl + S`).
4. **Reiniciar Apache** desde el XAMPP Control Panel (clic en *Stop* y luego en *Start*).

---

### Paso 5: Creación de la Base de Datos

#### Opción A: A través de phpMyAdmin (Recomendado)
1. Abrir el navegador e ingresar a: `http://localhost/phpmyadmin`.
2. En la barra lateral izquierda, hacer clic en **Nueva**.
3. En el campo *Nombre de la base de datos*, escribir: `uptec_cursos`.
4. En el selector de cotejamiento, elegir: **`utf8mb4_unicode_ci`**.
5. Hacer clic en **Crear**.

#### Opción B: A través de la Consola de MySQL (CLI)
Abrir una terminal de comandos (`cmd`) y ejecutar:
```bash
cd C:\xampp\mysql\bin
mysql -u root -e "CREATE DATABASE IF NOT EXISTS uptec_cursos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

### Paso 6: Importación del Esquema y Datos Semilla

Para que el sistema cuente con las tablas maestras, la nueva tabla `egresados`, vistas, triggers y usuarios iniciales, se deben importar los dos archivos SQL ubicados en `db/`.

#### Usando phpMyAdmin:
1. En la lista izquierda de phpMyAdmin, seleccionar la base de datos **`uptec_cursos`**.
2. Ir a la pestaña **Importar** (en el menú superior).
3. En *Archivo a importar*, hacer clic en **Seleccionar archivo**.
4. Buscar y seleccionar: `C:\xampp\htdocs\uptec-cursos\db\schema.sql`.
5. Bajar al final y hacer clic en **Continuar** (o *Importar*). Esperar la confirmación verde.
6. Repetir el mismo procedimiento para el archivo de datos iniciales: `C:\xampp\htdocs\uptec-cursos\db\seed.sql`.

#### Usando la Línea de Comandos (Más rápido):
```bash
cd C:\xampp\htdocs\uptec-cursos\db

# 1. Importar el esquema DDL (tablas, triggers, vistas, índices)
C:\xampp\mysql\bin\mysql -u root uptec_cursos < schema.sql

# 2. Importar los datos de prueba y roles semilla
C:\xampp\mysql\bin\mysql -u root uptec_cursos < seed.sql
```
*(Nota: Si tu usuario `root` de MySQL posee contraseña, añade el flag `-p` a los comandos).*

---

### Paso 7: Configuración de la Conexión en el Backend

Abrir el archivo de configuración de base de datos:
`C:\xampp\htdocs\uptec-cursos\backend\db\config.php`

Verificar que las credenciales coincidan con el entorno local de XAMPP:

```php
private string $host = 'localhost';
private string $database = 'uptec_cursos';
private string $username = 'root';    // Usuario por defecto en XAMPP
private string $password = '';        // Contraseña por defecto (vacía)
private string $charset  = 'utf8mb4';
```

*(La clase `Database` ya incluye la directiva `SET time_zone = '-04:00'` para sincronizar MySQL con la hora de Venezuela).*

---

### Paso 8: Comprobación del Despliegue y URLs de Acceso

Abre tu navegador e ingresa a las siguientes rutas para comprobar el correcto funcionamiento:

| Módulo / Interfaz | URL Local en Navegador |
|:---|:---|
| **Portal Institucional (Landing)** | `http://localhost/uptec-cursos/frontend/index.html` |
| **Inicio de Sesión y Registro** | `http://localhost/uptec-cursos/frontend/login.html` |
| **Panel Administrador** | `http://localhost/uptec-cursos/frontend/views/administrador/dashboard.html` |
| **Panel Analista** | `http://localhost/uptec-cursos/frontend/views/analista/dashboard.html` |
| **Panel Facilitador** | `http://localhost/uptec-cursos/frontend/views/facilitador/dashboard.html` |
| **Panel Participante** | `http://localhost/uptec-cursos/frontend/views/participante/dashboard.html` |
| **Gestión phpMyAdmin** | `http://localhost/phpmyadmin` |

---

## 3. Cuentas y Credenciales Semilla para Pruebas

El script `seed.sql` genera cuentas con contraseñas hasheadas en `bcrypt` (cost 12) listas para iniciar sesión:

| Rol de Usuario | Nivel RBAC | Cédula | Correo Electrónico | Contraseña Plana |
|:---|:---:|:---|:---|:---|
| **Administrador** | Nivel 4 | `V12345678` | `admin@uptec.edu.ve` | `admin123` |
| **Analista Académico** | Nivel 3 | `V87654321` | `analista@uptec.edu.ve` | `analista123` |
| **Facilitador (Docente 1)** | Nivel 2 | `V11111111` | `jperez@uptec.edu.ve` | `facilitador123` |
| **Facilitador (Docente 2)** | Nivel 2 | `V22222222` | `mlopez@uptec.edu.ve` | `facilitador123` |
| **Facilitador (Docente 3)** | Nivel 2 | `V33333333` | `cgonzalez@uptec.edu.ve` | `facilitador123` |
| **Facilitador (Docente 4)** | Nivel 2 | `V44444444` | `aramirez@uptec.edu.ve` | `facilitador123` |

* **Para probar el rol Participante (Estudiante):** Ingresar a `login.html#register`, registrarse seleccionando el rol *Participante*, elegir una carrera (ej. *Informática*) y acceder de inmediato.

---

## 4. Estructura Exhaustiva del Árbol de Archivos

Verifica que tu proyecto contenga la totalidad de los siguientes archivos sin omisiones:

```text
C:\xampp\htdocs\uptec-cursos\
├── backend\
│   ├── api\
│   │   ├── api.php
│   │   ├── auth.php
│   │   ├── backup.php
│   │   ├── calificaciones.php
│   │   ├── cursos.php
│   │   ├── inscripciones.php
│   │   ├── reportes.php
│   │   └── usuarios.php
│   ├── assets\
│   │   └── certificado-plantilla.jpg
│   ├── db\
│   │   └── config.php
│   ├── libs\
│   │   └── SimplePDF.php
│   └── security\
│       ├── auth.php
│       ├── csrf.php
│       ├── sanitizer.php
│       └── validator.php
├── db\
│   ├── schema.sql
│   └── seed.sql
├── documentacion\
│   ├── 00-resumen-ejecutivo.md
│   ├── 01-arquitectura.md
│   ├── 02-base-de-datos.md
│   ├── 03-api-endpoints.md
│   ├── 04-seguridad.md
│   ├── 05-roles-permisos.md
│   ├── 06-instalacion.md
│   ├── 07-frontend.md
│   ├── 08-diagramas.md
│   ├── 09-pruebas.md
│   ├── manual-administrador.md
│   ├── manual-analista.md
│   ├── manual-facilitador.md
│   └── manual-participante.md
└── frontend\
    ├── index.html
    ├── login.html
    ├── css\
    │   ├── login.css
    │   └── main.css
    ├── img\
    │   ├── logo-uptec.png
    │   ├── logo-uptec-grande.png
    │   └── logo-uptec-grande-v2.png
    ├── js\
    │   ├── api.js
    │   ├── auth.js
    │   └── dashboard.js
    └── views\
        ├── administrador\
        │   ├── auditoria.html
        │   ├── backup.html
        │   ├── calificaciones.html
        │   ├── cursos.html
        │   ├── dashboard.html
        │   ├── egresados.html
        │   ├── inscripciones.html
        │   ├── reportes.html
        │   └── usuarios.html
        ├── analista\
        │   ├── cursos.html
        │   ├── dashboard.html
        │   ├── egresados.html
        │   ├── facilitadores.html
        │   ├── reportes.html
        │   ├── usuarios.html
        │   └── verificacion.html
        ├── facilitador\
        │   ├── calificaciones.html
        │   ├── dashboard.html
        │   ├── estudiantes.html
        │   ├── mis-cursos.html
        │   └── reportes.html
        └── participante\
            ├── certificados.html
            ├── dashboard.html
            ├── inscribir.html
            ├── mis-cursos.html
            └── mis-notas.html
```

---

## 5. Diagnóstico y Resolución de Problemas (Troubleshooting)

### 5.1. Error: "Error del servidor. Revise la consola para más detalles"
* **Causa:** PHP emitió una advertencia o error fatal en formato HTML (`<br /><b>...`) rompiendo la respuesta JSON.
* **Diagnóstico:**
  1. Abrir la consola de desarrollador del navegador (`F12` $\rightarrow$ pestaña *Console*).
  2. Revisar la línea roja con el mensaje `[UPTEC] Respuesta HTML recibida:`.
  3. Revisar el archivo de registro de errores de PHP en: `C:\xampp\php\logs\php_error_log`.
* **Causas habituales resueltas:**
  - Inserción de strings vacíos `""` en campos numéricos foráneos (`facilitador_id`) o fechas (`fecha_inicio`). El backend ahora sanitiza campos vacíos convirtiéndolos a `NULL`.
  - Falta de métodos en `Validator`. El endpoint `POST curso` cuenta con validaciones nativas independientes.

---

### 5.2. Error: "Fallo al restaurar la base de datos" o archivo rechazado
* **Causa:** El archivo SQL subido sobrepasa la directiva `upload_max_filesize` de PHP o contiene sentencias incompatibles.
* **Solución:**
  1. Verificar que en `C:\xampp\php\php.ini` existan las líneas:
     ```ini
     upload_max_filesize = 25M
     post_max_size = 30M
     memory_limit = 256M
     ```
  2. Reiniciar Apache desde el XAMPP Control Panel.
  3. Asegurarse de que el archivo tenga extensión `.sql` en minúsculas.

---

### 5.3. Error: "Token CSRF inválido" / Código HTTP 403
* **Causa:** La sesión PHP expiró por inactividad (>30 min) o se limpiaron las cookies del navegador mientras la página seguía abierta.
* **Solución:**
  1. Recargar la página con `Ctrl + F5` para forzar la sincronización del token.
  2. Si persiste, cerrar la sesión desde el botón de la cabecera e ingresar nuevamente.

---

### 5.4. Los cursos finalizan antes o después de la hora esperada
* **Causa:** Desfase horario entre el sistema operativo y el motor MySQL.
* **Verificación:** Ejecutar en phpMyAdmin:
  ```sql
  SELECT NOW(), CURDATE(), @@global.time_zone, @@session.time_zone;
  ```
* **Solución:** Asegurar que `backend/db/config.php` contenga:
  ```php
  date_default_timezone_set('America/Caracas');
  $this->connection->exec("SET time_zone = '-04:00'");
  ```

---

## 6. Procedimiento de Migración a Servidor de Producción (Linux / Apache)

Cuando el sistema se traslade de XAMPP a un VPS o servidor institucional (Debian, Ubuntu Server o Rocky Linux):

### 1. Permisos del Sistema de Archivos
Asegurar que el usuario del servidor web (`www-data` o `apache`) sea el propietario de la aplicación:
```bash
sudo chown -R www-data:www-data /var/www/html/uptec-cursos
sudo find /var/www/html/uptec-cursos -type d -exec chmod 755 {} \;
sudo find /var/www/html/uptec-cursos -type f -exec chmod 644 {} \;
```

### 2. Configuración Segura de Apache (`VirtualHost`)
Configurar el VirtualHost activando el módulo de reescritura y cabeceras de seguridad:
```apache
<VirtualHost *:443>
    ServerName cursos.uptec.edu.ve
    DocumentRoot /var/www/html/uptec-cursos

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/uptec_cert.crt
    SSLCertificateKeyFile /etc/ssl/private/uptec_key.key

    <Directory /var/www/html/uptec-cursos>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Protección de cabeceras
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

### 3. Fortalecimiento de Producción en `php.ini`
```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"
```

---
# END OF FILE `06-instalacion.md`