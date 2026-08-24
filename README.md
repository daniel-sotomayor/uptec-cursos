# UPTEC Cursos

Sistema de gestión académica para la Universidad Politécnica Territorial de Caracas "Mariscal Sucre".

## Descripción

UPTEC Cursos es una aplicación web para la administración de cursos, inscripciones, calificaciones y reportes académicos.

## Contenido del repositorio

- `backend/` - API PHP y lógica de seguridad
- `frontend/` - Interfaz de usuario en HTML, CSS y JavaScript
- `db/` - Esquema SQL y datos iniciales
- `documentacion/` - Documentación técnica, manuales, diagramas y plan de pruebas

## Documentación completa

Consulta `documentacion/README.md` para ver todos los documentos disponibles:

- Arquitectura
- Base de datos
- API endpoints
- Seguridad
- Roles y permisos
- Instalación
- Frontend
- Diagramas del sistema
- Plan de pruebas
- Despliegue

## Presentación del proyecto

**UPTEC Cursos** es un sistema web completo para la gestión académica de cursos, inscripciones, calificaciones y reportes. Está diseñado para un entorno universitario con control de roles, seguridad y auditoría.

## Cómo comenzar

1. Clona el repositorio:
   ```bash
git clone https://github.com/daniel-sotomayor/uptec-cursos.git
cd daniel-sotomayoruptec-cursos
```
2. Configura el servidor web y la base de datos según `documentacion/06-instalacion.md`.
3. Importa `db/schema.sql` y `db/seed.sql` en MySQL.
4. Accede a `login.html` o al dashboard según el rol.

## Enlaces de documentación

- `documentacion/08-diagramas.md` - Diagramas de arquitectura, ER y flujos.
- `documentacion/09-pruebas.md` - Plan de pruebas y casos de uso.

## Licencia

Repositorio destinado a la Universidad, documentado para uso académico y despliegue interno.
