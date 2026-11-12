# Manual de Despliegue

## Objetivo

Proveer una guía clara para desplegar UPTEC Cursos en un entorno de producción o desarrollo local.

## Requisitos

- PHP 8.0 o superior
- Extensión PDO MySQL activa
- MySQL 5.7 o superior
- Servidor web Apache o Nginx
- Carpeta `frontend/` disponible como contenido estático
- Permisos de lectura para archivos HTML, CSS, JS y PHP

## Estructura recomendada

El proyecto debe colocarse en el directorio raíz del servidor web (por ejemplo en `htdocs/uptec-cursos` en XAMPP o `/var/www/html/uptec-cursos` en Linux):

```
uptec-cursos/
├── backend/
├── db/
├── documentacion/
├── frontend/
└── README.md
```

## Configuración del servidor

### Apache (XAMPP / Linux)

El proyecto incluye archivos `.htaccess` preconfigurados para proteger archivos `.sql`, `.env` y directorios sensibles.

Ejemplo de VirtualHost (opcional):

```apache
<VirtualHost *:80>
    ServerName uptec-cursos.local
    DocumentRoot "C:/xampp/htdocs/uptec-cursos"
    <Directory "C:/xampp/htdocs/uptec-cursos">
        Options -Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Base de datos

1. Crear la base de datos en MySQL:

```sql
CREATE DATABASE uptec_cursos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importar el esquema y los datos iniciales:

```bash
mysql -u root -p uptec_cursos < db/schema.sql
mysql -u root -p uptec_cursos < db/seed.sql
```

3. Ajustar las credenciales en `backend/db/config.php` o mediante variables de entorno (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

## Usuarios de Prueba

| Rol | Cédula | Correo | Contraseña |
|-----|--------|--------|------------|
| Administrador | 12345678 | admin@uptec.edu.ve | admin123 |
| Analista | 87654321 | analista@uptec.edu.ve | analista123 |
| Facilitador | 32361671 | jperez@uptec.edu.ve | facilitador123 |
| Facilitador | 32361672 | mlopez@uptec.edu.ve | facilitador123 |
| Participante | 32361675 | phernandez@uptec.edu.ve | participante123 |

## Prueba de acceso

1. Abrir en el navegador `http://localhost/uptec-cursos/frontend/index.html` (o la IP local si se accede desde otra PC).
2. Iniciar sesión con un usuario de prueba o registrar una nueva cuenta.
