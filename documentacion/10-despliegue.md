Aquí tienes el archivo **`10-despliegue.md`** completamente estructurado, ampliado y formalizado con estándares de producción de nivel institucional para la **UPTECMS**:

---

# START OF FILE `10-despliegue.md`

# Manual de Despliegue en Producción

## UPTECMS Cursos v2.0 - Guía de Puesta en Producción, Configuración de Servidores y Mantenimiento

---

## 1. Información General y Objetivos del Despliegue

### 1.1. Propósito
Proveer una guía técnica exhaustiva, reproducible y estandarizada para el despliegue del sistema **UPTECMS Cursos v2.0** en servidores de producción institucionales (Linux Ubuntu Server / Debian / Rocky Linux) y redes locales de campus universitario (LAN), garantizando alta disponibilidad, aislamiento de seguridad perimetral, integridad transaccional y optimización de rendimiento sin dependencias externas.

### 1.2. Topología de Despliegue Recomendada
El sistema opera bajo un esquema monolítico modular de dos capas de infraestructura:

```
                          [ Clientes / Navegadores ]
                                     │
                                     ▼ (HTTPS / TLS 1.3 - Puerto 443)
┌────────────────────────────────────────────────────────────────────────┐
│             SERVIDOR DE APLICACIÓN WEB (Apache 2.4 / Nginx)            │
│  ├── DocumentRoot: /var/www/html/uptec-cursos/                         │
│  │     ├── frontend/  (Contenido estático: HTML5, CSS3, JS ES6+, SVG)  │
│  │     └── backend/   (Lógica de negocio, APIs RESTful y SimplePDF)    │
│  └── Intérprete: PHP 8.0+ (FPM o mod_php con extensiones nativas)      │
└────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼ (Conexión Local / Unix Socket / 3306)
┌────────────────────────────────────────────────────────────────────────┐
│             SERVIDOR DE PERSISTENCIA (MySQL 5.7+ / MariaDB 10.4+)      │
│  ├── Base de Datos: uptec_cursos (InnoDB, utf8mb4_unicode_ci)          │
│  ├── Triggers: Recálculo automático de promedio ponderado              │
│  └── Zona Horaria: America/Caracas (UTC-4 / -04:00)                     │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Requisitos Previos del Servidor

### 2.1. Plataforma y Sistema Operativo
* **Distribución recomendada:** Ubuntu Server 22.04 LTS / Debian 12 / Rocky Linux 9.
* **Acceso administrativo:** Usuario con privilegios de ejecución `sudo`.
* **Nombre de Dominio / DNS:** FQDN institucional asignado (ej. `cursos.uptec.edu.ve`) o dirección IP fija en red LAN universitaria (ej. `192.168.1.150`).

### 2.2. Pila Tecnológica Requerida

| Componente | Versión Mínima | Paquetes / Módulos Requeridos en Linux |
|:---|:---:|:---|
| **Servidor Web** | Apache 2.4+ / Nginx 1.20+ | `apache2`, `mod_rewrite`, `mod_headers`, `mod_ssl`, `mod_deflate` |
| **Intérprete PHP** | PHP 8.0 o superior | `php`, `php-cli`, `php-fpm`, `php-mysql`, `php-pdo`, `php-mbstring`, `php-json`, `php-openssl`, `php-gd` |
| **Base de Datos** | MySQL 5.7+ / MariaDB 10.4+ | `mysql-server` o `mariadb-server` (Motor InnoDB activo) |
| **Herramientas Auxiliares** | - | `curl`, `git`, `unzip`, `cron`, `certbot`, `python3-certbot-apache` |

---

## 3. Preparación del Sistema Operativo y Servidor Web (Paso a Paso)

### Paso 1: Actualización del Sistema Base
Conectarse al servidor vía SSH y actualizar la lista de repositorios y paquetes instalados:
```bash
sudo apt update && sudo apt upgrade -y
```

### Paso 2: Instalación de la Pila LAMP (Apache, MySQL, PHP 8.1+)
Ejecutar la instalación de los servicios esenciales:
```bash
sudo apt install -y apache2 mysql-server php php-cli php-mysql php-mbstring php-json php-openssl php-gd php-zip libapache2-mod-php
```

### Paso 3: Configuración de Parámetros de Producción en PHP (`php.ini`)
Localizar y editar el archivo de configuración de PHP para Apache (o PHP-FPM si se utiliza Nginx):
```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

Establecer rigurosamente los siguientes valores para habilitar la **restauración de base de datos de hasta 25 MB**, evitar caídas en emisión de reportes y ocultar trazas de error:

```ini
; 1. Gestión de Errores y Seguridad (Ocultar errores a usuarios)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
expose_php = Off

; 2. Tiempos de Ejecución (Para respaldos SQL masivos y SimplePDF)
max_execution_time = 300
max_input_time = 300

; 3. Asignación de Memoria
memory_limit = 256M

; 4. Límites de Carga para Restauración de Base de Datos (restore)
upload_max_filesize = 25M
post_max_size = 30M

; 5. Sincronización Horaria Oficial de Venezuela
date.timezone = "America/Caracas"

; 6. Endurecimiento de Cookies de Sesión
session.cookie_httponly = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
session.gc_maxlifetime = 1800
```
Guardar los cambios (`Ctrl + O`, luego `Enter`) y salir (`Ctrl + X`).

---

## 4. Despliegue de Archivos y Permisos del Sistema Operativo

### Paso 4: Clonación o Copia del Código Fuente
1. Crear el directorio de destino y clonar el repositorio (o transferir el paquete `.zip` vía SFTP):
   ```bash
   sudo mkdir -p /var/www/html/uptec-cursos
   sudo git clone https://github.com/tu-organizacion/uptec-cursos.git /var/www/html/uptec-cursos
   ```
2. Verificar que la estructura de directorios concuerde exactamente con la esperada:
   ```text
   /var/www/html/uptec-cursos/
   ├── backend/
   │   ├── api/
   │   ├── assets/
   │   │   └── certificado-plantilla.jpg
   │   ├── db/
   │   ├── libs/
   │   │   └── SimplePDF.php
   │   └── security/
   ├── db/
   │   ├── schema.sql
   │   └── seed.sql
   ├── documentacion/
   └── frontend/
   ```

### Paso 5: Asignación Estricta de Propietario y Permisos UNIX
El servidor web (`www-data` en Debian/Ubuntu) debe ser el propietario del código. Los directorios deben tener permisos de ejecución y lectura `755`, y los archivos `644`:

```bash
# Asignar usuario del servidor web como propietario
sudo chown -R www-data:www-data /var/www/html/uptec-cursos

# Directorios con permisos 755 (drwxr-xr-x)
sudo find /var/www/html/uptec-cursos -type d -exec chmod 755 {} \;

# Archivos con permisos 644 (-rw-r--r--)
sudo find /var/www/html/uptec-cursos -type f -exec chmod 644 {} \;

# Permiso explícito de lectura para la plantilla gráfica oficial
sudo chmod 644 /var/www/html/uptec-cursos/backend/assets/certificado-plantilla.jpg
```

---

## 5. Configuración y Aislamiento de Base de Datos en Producción

### Paso 6: Configuración Inicial de MySQL
Ejecutar el script de endurecimiento de seguridad de MySQL:
```bash
sudo mysql_secure_installation
```
*(Responder `Y` para eliminar usuarios anónimos, deshabilitar inicio de sesión root remoto y eliminar la base de datos de prueba).*

### Paso 7: Creación de la Base de Datos y Usuario de Mínimo Privilegio
Por seguridad, el sistema en producción **no debe conectarse con el usuario `root`**. Se debe crear un usuario con acceso exclusivo a la base de datos `uptec_cursos`:

1. Ingresar a la consola de MySQL:
   ```bash
   sudo mysql -u root
   ```

2. Ejecutar las siguientes instrucciones SQL:
   ```sql
   -- 1. Crear base de datos con charset institucional
   CREATE DATABASE IF NOT EXISTS uptec_cursos 
       CHARACTER SET utf8mb4 
       COLLATE utf8mb4_unicode_ci;

   -- 2. Crear usuario exclusivo con contraseña fuerte
   CREATE USER IF NOT EXISTS 'uptec_app'@'localhost' IDENTIFIED BY 'TuClaveSegura_2026!#';

   -- 3. Otorgar privilegios necesarios (DML, DDL, Triggers, Views)
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, TRIGGER, CREATE VIEW, SHOW VIEW 
   ON uptec_cursos.* TO 'uptec_app'@'localhost';

   -- 4. Aplicar cambios y salir
   FLUSH PRIVILEGES;
   EXIT;
   ```

### Paso 8: Importación del Esquema Maestro y Semilla
Importar el DDL que contiene la nueva tabla `egresados`, tipos corregidos y triggers:
```bash
cd /var/www/html/uptec-cursos/db

# Importar tablas, vistas y triggers
mysql -u uptec_app -p uptec_cursos < schema.sql

# Importar datos iniciales de roles y asignaturas base
mysql -u uptec_app -p uptec_cursos < seed.sql
```

### Paso 9: Configurar Credenciales en `config.php`
Editar el archivo `backend/db/config.php`:
```bash
sudo nano /var/www/html/uptec-cursos/backend/db/config.php
```

Configurar los datos de conexión correspondientes:
```php
private string $host = 'localhost';
private string $database = 'uptec_cursos';
private string $username = 'uptec_app';
private string $password = 'TuClaveSegura_2026!#';
private string $charset = 'utf8mb4';
```

---

## 6. Configuración del Servidor Web Apache (`VirtualHost`)

### Paso 10: Habilitar Módulos de Apache
Activar los módulos necesarios para URLs amigables, cabeceras seguras y compresión de tráfico:
```bash
sudo a2enmod rewrite headers ssl deflate
```

### Paso 11: Crear el Archivo de VirtualHost Institucional
Crear la configuración del sitio:
```bash
sudo nano /etc/apache2/sites-available/uptec-cursos.conf
```

Agregar la siguiente configuración optimizada:

```apache
<VirtualHost *:80>
    ServerName cursos.uptec.edu.ve
    ServerAdmin webmaster@uptec.edu.ve
    DocumentRoot /var/www/html/uptec-cursos

    # Redirección global a HTTPS (cuando SSL esté instalado)
    # RewriteEngine On
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    <Directory /var/www/html/uptec-cursos>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Bloqueo directo de acceso a archivos de configuración y base de datos
        <FilesMatch "\.(sql|env|ini|log|sh)$">
            Require all denied
        </FilesMatch>
    </Directory>

    # Bloqueo de navegación web directa a directorios internos sensibles
    <Directory /var/www/html/uptec-cursos/db>
        Require all denied
    </Directory>
    <Directory /var/www/html/uptec-cursos/documentacion>
        Require all denied
    </Directory>
    <Directory /var/www/html/uptec-cursos/backend/db>
        Require all denied
    </Directory>
    <Directory /var/www/html/uptec-cursos/backend/security>
        Require all denied
    </Directory>

    # Cabeceras globales de seguridad HTTP
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Registro de Logs
    ErrorLog ${APACHE_LOG_DIR}/uptec_error.log
    CustomLog ${APACHE_LOG_DIR}/uptec_access.log combined
</VirtualHost>
```

### Paso 12: Activar Sitio y Reiniciar Apache
```bash
sudo a2ensite uptec-cursos.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest   # Debe arrojar: Syntax OK
sudo systemctl restart apache2
```

---

## 7. Certificado de Seguridad SSL/TLS (HTTPS)

Para proteger las contraseñas, sesiones y descargas de certificados en tránsito, el protocolo HTTPS es mandatorio.

### Instalación de Certificado Gratuito con Let's Encrypt (Certbot):
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d cursos.uptec.edu.ve
```
*(Certbot configurará automáticamente la redirección 301 de HTTP a HTTPS y renovará los certificados cada 90 días mediante un temporizador systemd).*

### Habilitar Flag `Secure` en Cookies de Sesión:
Una vez activo HTTPS, editar `backend/security/auth.php` y asegurar que la directiva de cookie segura esté en `1`:
```php
ini_set('session.cookie_secure', '1');
```

---

## 8. Automatización de Copias de Seguridad del Sistema (Cron Job)

Además del módulo de respaldo manual en la interfaz (`backup.html`), se debe programar un respaldo nocturno en el servidor que guarde volcados SQL con compresión `gzip` y retención de 30 días:

1. Crear el script de respaldo automatizado:
   ```bash
   sudo nano /opt/backup_uptecms.sh
   ```

2. Pegar el siguiente contenido:
   ```bash
   #!/bin/bash
   BACKUP_DIR="/var/backups/uptecms"
   FECHA=$(date +"%Y-%m-%d_%H%M%S")
   DB_USER="uptec_app"
   DB_PASS="TuClaveSegura_2026!#"
   DB_NAME="uptec_cursos"

   mkdir -p $BACKUP_DIR
   mysqldump --single-transaction --quick -u $DB_USER -p$DB_PASS $DB_NAME | gzip > "$BACKUP_DIR/backup_${DB_NAME}_${FECHA}.sql.gz"

   # Eliminar respaldos con más de 30 días de antigüedad
   find $BACKUP_DIR -type f -name "*.sql.gz" -mtime +30 -delete
   ```

3. Asignar permisos de ejecución exclusivos a root:
   ```bash
   sudo chmod 700 /opt/backup_uptecms.sh
   ```

4. Programar en la tabla de tareas `cron`:
   ```bash
   sudo crontab -e
   ```
   Añadir la siguiente línea para ejecutar todos los días a las 2:00 AM:
   ```cron
   0 2 * * * /opt/backup_uptecms.sh > /dev/null 2>&1
   ```

---

## 9. Lista de Verificación de Salida a Producción (Smoke Testing)

Antes de entregar el sistema a la comunidad universitaria, el administrador de infraestructura debe ejecutar este checklist de 10 puntos:

- [ ] **Acceso HTTPS:** Comprobar que `https://cursos.uptec.edu.ve` cargue sin advertencias de certificado en el navegador.
- [ ] **Aislamiento de Archivos Sensibles:** Intentar ingresar desde el navegador a `https://cursos.uptec.edu.ve/db/schema.sql` y `backend/db/config.php`; el servidor debe responder `403 Forbidden` o `404 Not Found`.
- [ ] **Inicio de Sesión:** Iniciar sesión con los 4 roles semilla (`Admin`, `Analista`, `Facilitador`, `Participante`).
- [ ] **Creación de Cursos:** Crear una asignatura de prueba; verificar que responda JSON 201 y no arroje advertencias HTML de PHP.
- [ ] **Control de Ponderación:** Intentar agregar una evaluación que supere el 100%; verificar que el sistema la bloquee.
- [ ] **Cierre de Ciclo:** Asignar `fecha_fin` vencida a un curso de prueba; comprobar que finalice y migre a `egresados`.
- [ ] **Descarga de Certificado Oficial:** Generar un certificado de egresado aprobado; verificar que el PDF abra en 2 páginas horizontales, con la plantilla de Sucre y los acentos correctos.
- [ ] **Subida y Restauración:** En `backup.html`, probar la exportación de un volcado SQL y verificar la restauración del mismo con el modal de advertencia crítica.
- [ ] **Bitácora de Auditoría:** Verificar que las acciones queden asentadas en `auditoria.html` con la IP real del cliente.
- [ ] **Responsividad:** Abrir la interfaz en un dispositivo móvil; verificar el menú hamburguesa lateral y la adaptación de tablas.

---

## 10. Procedimiento de Contingencia y Rollback

Si una actualización de código o de esquema SQL genera inconsistencias en producción, se debe seguir el siguiente procedimiento de marcha atrás:

```bash
# 1. Poner temporalmente la página en modo mantenimiento (opcional)
sudo a2disite uptec-cursos.conf && sudo systemctl reload apache2

# 2. Restaurar la base de datos al último volcado nocturno seguro
gunzip < /var/backups/uptecms/backup_uptec_cursos_YYYY-MM-DD_XXXXXX.sql.gz | mysql -u uptec_app -p uptec_cursos

# 3. Restaurar la versión anterior del código fuente vía Git
cd /var/www/html/uptec-cursos
git reset --hard TAG_VERSION_ANTERIOR

# 4. Reactivar el sitio web
sudo a2ensite uptec-cursos.conf && sudo systemctl reload apache2
```

---
# END OF FILE `10-despliegue.md`