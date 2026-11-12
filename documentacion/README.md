# UPTEC Cursos v2.0 - Documentación Técnica

## Sistema de Control de Cursos
### Universidad Politécnica Territorial de Caracas "Mariscal Sucre"

---

## Índice de Documentación

### Documentación Técnica

| Documento | Descripción |
|-----------|-------------|
| [00-resumen-ejecutivo.md](./00-resumen-ejecutivo.md) | Resumen ejecutivo del proyecto |
| [01-arquitectura.md](./01-arquitectura.md) | Arquitectura del sistema y estructura de carpetas |
| [02-base-de-datos.md](./02-base-de-datos.md) | Esquema de base de datos, tablas y relaciones |
| [03-api-endpoints.md](./03-api-endpoints.md) | Documentación completa de la API REST |
| [04-seguridad.md](./04-seguridad.md) | Medidas de seguridad implementadas (OWASP) |
| [05-roles-permisos.md](./05-roles-permisos.md) | Sistema de roles y permisos |
| [06-instalacion.md](./06-instalacion.md) | Guía de instalación paso a paso |
| [07-frontend.md](./07-frontend.md) | Estructura del frontend y vistas por rol |
| [08-diagramas.md](./08-diagramas.md) | Diagramas de arquitectura, ER y flujos de datos |
| [09-pruebas.md](./09-pruebas.md) | Plan de pruebas, casos de uso y validación |
| [10-despliegue.md](./10-despliegue.md) | Manual de despliegue y configuración de entorno |

### Manuales de Usuario

| Documento | Para quién es |
|-----------|---------------|
| [manual-administrador.md](./manual-administrador.md) | Guía completa para el Administrador del sistema |
| [manual-analista.md](./manual-analista.md) | Guía para el Analista académico |
| [manual-facilitador.md](./manual-facilitador.md) | Guía para Docentes/Facilitadores |
| [manual-participante.md](./manual-participante.md) | Guía para Estudiantes/Participantes |

---

## Resumen Ejecutivo

**UPTEC Cursos** es un sistema web completo para la gestión de cursos académicos desarrollado para la Universidad Politécnica Territorial de Caracas. Implementa arquitectura escalable, metodologías DRY (Don't Repeat Yourself) y seguridad OWASP.

### Tecnologías Utilizadas

| Capa | Tecnología |
|------|------------|
| **Backend** | PHP 8.0+, MySQL 5.7+ |
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla) |
| **Seguridad** | Bcrypt, CSRF Tokens, Prepared Statements |
| **Arquitectura** | RESTful API, Singleton Pattern, MVC-like |

### Características Principales

- **4 Roles de Usuario**: Administrador, Analista, Facilitador, Participante
- **Landing Page Institucional** con información de la UPTEC
- **Login con Registro** de participantes y facilitadores
- **Gestión de Cursos**: CRUD completo con facilitadores asignados
- **Inscripciones Online**: Los participantes se inscriben en cursos disponibles
- **Control de Calificaciones**: Registro de notas con ponderación automática
- **Plan de Evaluación**: Cada curso tiene su plan de evaluación definido
- **Auditoría Completa**: Logs de todas las actividades del sistema
- **Respaldo de BD**: Exportación a SQL para el administrador
- **Reportes y Estadísticas**: Visualización de métricas académicas
- **Interfaz Responsiva**: Adaptable a dispositivos móviles

---

## Estructura del Proyecto

```
uptec-cursos/
├── backend/
│   ├── api/              # Endpoints RESTful
│   │   ├── api.php       # Router principal
│   │   ├── auth.php      # Autenticación
│   │   ├── usuarios.php  # Gestión de usuarios
│   │   ├── cursos.php    # Gestión de cursos
│   │   ├── inscripciones.php
│   │   ├── calificaciones.php
│   │   ├── reportes.php
│   │   └── backup.php
│   ├── security/         # Clases de seguridad
│   │   ├── auth.php      # Auth class (sesiones, bcrypt)
│   │   ├── csrf.php      # CSRF protection
│   │   ├── sanitizer.php # XSS prevention
│   │   └── validator.php # Validación de datos
│   └── db/
│       └── config.php    # Configuración BD (Singleton)
├── db/
│   ├── schema.sql        # Esquema de base de datos
│   └── seed.sql          # Datos iniciales
├── frontend/
│   ├── index.html        # Landing page
│   ├── login.html        # Login y registro
│   ├── css/              # Estilos
│   ├── js/               # JavaScript modules
│   └── views/            # Vistas por rol
│       ├── administrador/
│       ├── analista/
│       ├── facilitador/
│       └── participante/
└── documentacion/        # Esta carpeta
```

---

## Usuarios de Prueba

| Rol | Cédula | Correo | Contraseña |
|-----|--------|--------|------------|
| Administrador | 12345678 | admin@uptec.edu.ve | admin123 |
| Analista | 87654321 | analista@uptec.edu.ve | analista123 |
| Facilitador | 32361671 | jperez@uptec.edu.ve | facilitador123 |
| Facilitador | 32361672 | mlopez@uptec.edu.ve | facilitador123 |
| Participante | 32361675 | phernandez@uptec.edu.ve | participante123 |

---

## Autor

**Universidad Politécnica Territorial de Caracas "Mariscal Sucre"**

Sistema desarrollado para la gestión académica de cursos.

---

*Documentación generada para UPTEC Cursos v2.0*
