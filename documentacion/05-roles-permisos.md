# START OF FILE `05-roles-permisos.md`

# Roles y Permisos

## UPTECMS Cursos v2.0 - Sistema de Control de Acceso Basado en Roles (RBAC)

---

## 1. Visión General

El sistema **UPTECMS Cursos** implementa un modelo estricto de **Control de Acceso Basado en Roles (RBAC - Role-Based Access Control)** estructurado en **4 niveles jerárquicos**. Cada nivel superior hereda las capacidades de consulta de los niveles inferiores, complementándose con comprobaciones de **titularidad de recursos (*Resource Ownership*)** para garantizar que los docentes y participantes interactúen únicamente con su información correspondiente.

```
                    ┌─────────────────────────┐
                    │      ADMINISTRADOR      │  ← Nivel 4: Control total, usuarios,
                    │        (Nivel 4)        │     respaldo/restauración y auditoría
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │        ANALISTA         │  ← Nivel 3: Gestión curricular, asignación
                    │        (Nivel 3)        │     docente, control de cursos y egresados
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │       FACILITADOR       │  ← Nivel 2: Planes de evaluación, notas y
                    │        (Nivel 2)        │     alumnos de sus cursos asignados
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │      PARTICIPANTE       │  ← Nivel 1: Inscripción por carrera,
                    │        (Nivel 1)        │     consulta de notas y certificados
                    └─────────────────────────┘
```

---

## 2. Jerarquía de Roles y Reglas de Herencia

En `backend/security/auth.php`, la jerarquía se formaliza numéricamente:

```php
private static array $roleHierarchy = [
    'Administrador' => 4,
    'Analista'      => 3,
    'Facilitador'   => 2,
    'Participante'  => 1
];
```

### Reglas de Evaluación de Permisos
1. **Herencia Vertical:** Un usuario con nivel $N$ satisface automáticamente cualquier verificación que requiera un nivel $\le N$.
2. **`isAdmin()`:** Retorna `true` exclusivamente si el usuario posee nivel 4 (`Administrador`).
3. **`isAnalystOrAbove()`:** Autoriza a usuarios de nivel 3 (`Analista`) y nivel 4 (`Administrador`).
4. **`isTeacherOrAbove()`:** Autoriza a usuarios de nivel 2 (`Facilitador`), nivel 3 (`Analista`) y nivel 4 (`Administrador`).
5. **Comprobación de Titularidad (Ownership):** Cuando un `Facilitador` intenta calificar o modificar una evaluación, el backend verifica que `curso.facilitador_id === user.id`. Si no coincide, la petición se rechaza con código `HTTP 403 Forbidden`.

---

## 3. Definición Detallada de Roles

### 3.1. ADMINISTRADOR (Nivel 4)

#### Descripción
Máxima autoridad técnica y directiva de la plataforma. Posee control absoluto sobre la infraestructura de datos, cuentas de todos los roles, copias de seguridad y registros forenses.

#### Matriz de Privilegios
* **Gestión de Usuarios:** Crear, consultar, editar contraseñas/roles y desactivar cuentas de cualquier usuario (`Administrador`, `Analista`, `Facilitador`, `Participante`).
* **Control de Cursos:** Crear, editar, cancelar y eliminar asignaturas del catálogo curricular.
* **Control de Egresados:** Acceso irrestricto al histórico general de egresados de todas las carreras y asignaturas.
* **Inscripciones y Matrícula:** Ver todas las matrículas del sistema y modificar sus estados manualmente.
* **Calificaciones:** Supervisar todas las notas asentadas en la institución.
* **Reportes:** Generar consolidados institucionales globales y descargar reportes estadísticos en PDF.
* **Auditoría Forense:** Acceso exclusivo a la bitácora transaccional inmutable (`logs_actividad`).
* **Módulo de Base de Datos:** Descargar volcados SQL (`backup`) e importar/restaurar bases de datos completas (`restore`).

#### Vistas Disponibles (`frontend/views/administrador/`)
1. `dashboard.html`: Métricas generales, carreras activas, top cursos y actividad reciente.
2. `usuarios.html`: Tabla administrativa de usuarios con modal de creación y edición.
3. `egresados.html`: Histórico general de egresados con filtros por carrera, curso y condición académica.
4. `cursos.html`: Catálogo curricular con modal de creación y asignación docente.
5. `inscripciones.html`: Supervisión de inscripciones activas por curso y estado.
6. `calificaciones.html`: Visor global de notas asentadas con opción de eliminación administrativa.
7. `reportes.html`: Estadísticas institucionales y descarga de informe PDF.
8. `auditoria.html`: Monitor de traza de eventos con filtros por fecha, tabla y acción.
9. `backup.html`: Panel bidireccional para exportar e importar copias de seguridad `.sql`.

---

### 3.2. ANALISTA (Nivel 3)

#### Descripción
Responsable de la planificación académica, organización de la oferta curricular, verificación de asignaturas y supervisión del cuerpo docente.

#### Matriz de Privilegios
* **Gestión de Usuarios:** Consultar directorio de facilitadores y participantes; aprobar y habilitar docentes pendientes (`activo = 1`). *No tiene acceso a cuentas de Administradores ni otros Analistas*.
* **Control de Cursos:** Crear y editar cursos; asignar docentes facilitadores; alternar cursos entre estado `'Planificado'` y `'En Curso'` / `'Verificado'`.
* **Control de Egresados:** Consultar el listado histórico de egresados y auditar el rendimiento curricular de asignaturas culminadas.
* **Planes de Evaluación:** Crear y supervisar la estructura del plan de evaluación de cualquier asignatura.
* **Inscripciones y Calificaciones:** Supervisar matrículas y notas asentadas para fines de control académico.
* **Reportes:** Generar informes de cursos por área, estados de asignaturas y facilitadores asignados.
* **Restricciones Estrictas:**
  * ❌ No puede eliminar cursos físicamente (solo Admin).
  * ❌ No puede crear usuarios administrativos ni analistas.
  * ❌ No tiene acceso a la bitácora de auditoría (`auditoria.html`).
  * ❌ No tiene acceso a la exportación ni restauración de bases de datos (`backup.html`).

#### Vistas Disponibles (`frontend/views/analista/`)
1. `dashboard.html`: Balance operativo de asignaturas, cursos planificados y facilitadores disponibles.
2. `cursos.html`: Gestión y edición de asignaturas curriculares con asignación de facilitador.
3. `egresados.html`: Consulta del histórico general de alumnos egresados.
4. `facilitadores.html`: Nómina docente con botones de habilitación e inhabilitación.
5. `usuarios.html`: Directorio de participantes y facilitadores con visor de ficha de usuario.
6. `verificacion.html`: Módulo de control de estados para iniciar asignaturas o regresarlas a planificación.
7. `reportes.html`: Informes de oferta académica y descarga de informe PDF para analistas.

---

### 3.3. FACILITADOR (Nivel 2)

#### Descripción
Docente universitario responsable de dictar asignaturas, estructurar planes de evaluación pedagógicos y calificar el desempeño de sus alumnos inscritos.

#### Matriz de Privilegios
* **Cursos Asignados:** Consultar únicamente las asignaturas donde figura como docente titular (`facilitador_id = user.id`).
* **Plan de Evaluación:** Crear, consultar y eliminar actividades evaluativas de sus cursos, con validación matemática obligatoria de ponderación al 100%.
* **Calificaciones:** Registrar, editar y anular calificaciones de los estudiantes inscritos en sus cursos.
* **Estudiantes:** Monitorear el progreso individual y la barra de avance de sus alumnos a cargo.
* **Control de Egresados:** Consultar la nómina de egresados (aprobados y reprobados) de sus cursos culminados mediante el botón «Lista de Egresados».
* **Reportes:** Visualizar la distribución de notas, promedios y porcentaje de aprobación de sus asignaturas.
* **Restricciones Estrictas:**
  * ❌ No puede ver ni calificar estudiantes de cursos impartidos por otros docentes.
  * ❌ No puede crear ni editar cursos generales (código, fechas o cupos).
  * ❌ No puede modificar el estado de inscripción de un estudiante.
  * ❌ No puede acceder a módulos administrativos, auditoría ni respaldos.

#### Vistas Disponibles (`frontend/views/facilitador/`)
1. `dashboard.html`: Resumen de asignaturas activas y total de alumnos bajo su tutela.
2. `mis-cursos.html`: Tarjetas de cursos asignados, acceso al plan de evaluación y botón de egresados.
3. `estudiantes.html`: Matriz de estudiantes por curso con notas por evaluación y barra de avance.
4. `calificaciones.html`: Interfaz para asentar calificaciones según las evaluaciones del plan.
5. `reportes.html`: Gráficos de desempeño, promedios y distribución vigesimal de notas.

---

### 3.4. PARTICIPANTE (Nivel 1)

#### Descripción
Estudiante universitario que se postula a cursos formativos, hace seguimiento a sus evaluaciones parciales y descarga certificaciones oficiales aprobatorias.

#### Matriz de Privilegios
* **Inscripción Curricular:** Explorar la oferta de asignaturas abiertas para su carrera y formalizar su inscripción personal de forma autónoma.
* **Re-inscripción Académica:** Si reprueba un curso, al cerrarse el ciclo lectivo el sistema le permite volver a postularse cuando la materia sea ofertada en un nuevo período.
* **Seguimiento de Notas:** Consultar en tiempo real el desglose de sus notas parciales, la ponderación evaluada y el promedio definitivo.
* **Certificados Oficiales:** Consultar y descargar el diploma oficial apaisado en PDF de dos páginas de todas las asignaturas que haya aprobado con nota $\ge 10.00$.
* **Descarga de Boletín:** Descargar el informe de notas en PDF de sus asignaturas cursadas.
* **Restricciones Estrictas:**
  * ❌ No puede ver asignaturas correspondientes a carreras distintas a la suya (salvo cursos generales/externos).
  * ❌ No puede inscribir materias a nombre de terceros.
  * ❌ No puede visualizar calificaciones de otros estudiantes.
  * ❌ No puede alterar notas, planes de evaluación ni estados de cursos.

#### Vistas Disponibles (`frontend/views/participante/`)
1. `dashboard.html`: Panel personal con promedio ponderado general y cursos en progreso.
2. `inscribir.html`: Catálogo de asignaturas disponibles con validación de cupos en tiempo real.
3. `mis-cursos.html`: Listado de cursos donde mantiene matrícula activa.
4. `mis-notas.html`: Desglose pormenorizado de evaluaciones por curso y descarga de boletín PDF.
5. `certificados.html`: Galería de diplomas oficiales con vista previa apaisada y descarga directa en PDF.

---

## 4. Matriz de Permisos Completa

| Módulo / Funcionalidad Operativa | Administrador | Analista | Facilitador | Participante |
|:---|:---:|:---:|:---:|:---:|
| **GESTIÓN DE USUARIOS** | | | | |
| Crear nuevos usuarios de cualquier rol | ✅ | ❌ | ❌ | ❌ |
| Consultar todos los usuarios del sistema | ✅ | ❌ | ❌ | ❌ |
| Consultar facilitadores y participantes | ✅ | ✅ | ❌ | ❌ |
| Habilitar / Activar facilitadores pendientes | ✅ | ✅ | ❌ | ❌ |
| Modificar contraseñas de otros usuarios | ✅ | ❌ | ❌ | ❌ |
| Desactivar usuarios (*soft delete*) | ✅ | ❌ | ❌ | ❌ |
| Registro público de cuenta nueva | ❌ | ❌ | ✅ *(Público)* | ✅ *(Público)* |
| Modificar su propia contraseña | ✅ | ✅ | ✅ | ✅ |
| **OFERTA ACADÉMICA Y CURSOS** | | | | |
| Crear asignaturas en el catálogo | ✅ | ✅ | ❌ | ❌ |
| Modificar datos del curso (fechas, cupos, área) | ✅ | ✅ | ❌ | ❌ |
| Asignar facilitador docente al curso | ✅ | ✅ | ❌ | ❌ |
| Iniciar curso / Regresar a planificado | ✅ | ✅ | ❌ | ❌ |
| Finalizar curso manualmente (procesa egresados) | ✅ | ✅ | ❌ | ❌ |
| Eliminar curso (*soft delete*) | ✅ | ❌ | ❌ | ❌ |
| Consultar cursos asignados a su docencia | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| Consultar oferta disponible para inscripción | ✅ | ✅ | ❌ | ✅ *(Su Área)* |
| **PLANES DE EVALUACIÓN** | | | | |
| Crear evaluación en el plan (tope 100%) | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| Eliminar evaluación del plan | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| Consultar plan y ponderación acumulada | ✅ | ✅ | ✅ *(Propios)* | ✅ *(Inscrito)* |
| **HISTÓRICO DE EGRESADOS** | | | | |
| Consultar histórico global de egresados | ✅ | ✅ | ❌ | ❌ |
| Consultar egresados de cursos culminados | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| Conservar registro y diploma tras culminar | ❌ | ❌ | ❌ | ✅ *(Propios)* |
| **INSCRIPCIONES Y MATRÍCULA** | | | | |
| Consultar todas las matrículas del sistema | ✅ | ✅ | ❌ | ❌ |
| Consultar alumnos inscritos en asignaturas | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| Inscribir a un estudiante administrativamente | ✅ | ✅ | ❌ | ❌ |
| Inscribirse a sí mismo en una asignatura | ❌ | ❌ | ❌ | ✅ |
| Modificar estado de inscripción manualmente | ✅ | ❌ | ❌ | ❌ |
| Cancelar inscripción antes del inicio de clases | ✅ | ❌ | ❌ | ✅ *(Propias)* |
| **CALIFICACIONES Y NOTAS** | | | | |
| Consultar todas las notas del sistema | ✅ | ✅ | ❌ | ❌ |
| Asentar / Registrar nota por evaluación | ✅ | ❌ | ✅ *(Propios)* | ❌ |
| Modificar calificación registrada | ✅ | ❌ | ✅ *(Propios)* | ❌ |
| Eliminar calificación registrada | ✅ | ❌ | ✅ *(Propios)* | ❌ |
| Consultar notas y boletín personal | ❌ | ❌ | ❌ | ✅ *(Propias)* |
| **CERTIFICADOS Y DOCUMENTOS PDF** | | | | |
| Emitir / Descargar Certificado Oficial (PDF) | ✅ | ✅ | ❌ | ✅ *(Aprobados)*|
| Descargar Boletín de Notas (PDF) | ❌ | ❌ | ❌ | ✅ |
| Descargar Informe Estadístico Consolidado | ✅ | ✅ | ✅ *(Propios)* | ❌ |
| **CONTROL, AUDITORÍA Y BASE DE DATOS**| | | | |
| Consultar bitácora transaccional (logs) | ✅ | ❌ | ❌ | ❌ |
| Exportar respaldo completo (.sql) | ✅ | ❌ | ❌ | ❌ |
| Restaurar base de datos (.sql) | ✅ | ❌ | ❌ | ❌ |

---

## 5. Implementación de los Middlewares de Seguridad

Ubicados en `backend/api/api.php`, los middlewares son interceptores que se ejecutan antes de transferir el control al manejador del endpoint:

```php
/**
 * Exige sesión activa válida para cualquier rol autenticado
 */
function requireAuth(): callable {
    return function() {
        Auth::init();
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sesión requerida o expirada']);
            return false;
        }
        return true;
    };
}

/**
 * Exige nivel 4 (Administrador)
 */
function requireAdmin(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado: Se requiere rol Administrador']);
            return false;
        }
        return true;
    };
}

/**
 * Exige nivel 3 o superior (Analista o Administrador)
 */
function requireAnalystOrAbove(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isAnalystOrAbove()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado: Se requiere rol Analista o superior']);
            return false;
        }
        return true;
    };
}

/**
 * Exige nivel 2 o superior (Facilitador, Analista o Administrador)
 */
function requireTeacherOrAbove(): callable {
    return function() {
        Auth::init();
        if (!Auth::check() || !Auth::isTeacherOrAbove()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado: Se requiere rol Facilitador o superior']);
            return false;
        }
        return true;
    };
}

/**
 * Exige coincidencia con un rol específico
 */
function requireRole(string $role): callable {
    return function() use ($role) {
        Auth::init();
        if (!Auth::check() || !Auth::hasRole($role)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => "Acceso denegado: Se requiere rol {$role}"]);
            return false;
        }
        return true;
    };
}
```

---

## 6. Cuotas, Alcance de Datos y Aislamiento por Rol

| Dimensión Operativa | Administrador | Analista | Facilitador | Participante |
|:---|:---:|:---:|:---:|:---:|
| **Visibilidad de Cursos** | Todas las asignaturas | Todas las asignaturas | Solo sus asignaturas asignadas | Solo cursos de su carrera |
| **Visibilidad de Alumnos** | Todos los inscritos | Todos los inscritos | Solo sus alumnos asignados | Únicamente su perfil |
| **Capacidad de Inscribir** | Ilimitada (Cualquiera) | Ilimitada (Cualquiera) | Ninguna | Solo su propia cuenta |
| **Edición de Evaluaciones**| En cualquier curso | En cualquier curso | Solo en sus asignaturas | Ninguna |
| **Edición de Notas** | Administrativa | Ninguna | Solo en sus asignaturas | Ninguna |
| **Consultar Egresados** | Histórico general | Histórico general | Solo sus cursos finalizados | Solo sus diplomas |
| **Respaldos de Sistema** | Exportar e Importar | Ninguna | Ninguna | Ninguna |

---

## 7. Escenarios Reales de Interacción entre Roles

### Escenario A: Ciclo de Apertura y Planificación Curricular
1. El **Analista** crea el curso `"PROG-101"` en estado `'Planificado'`, define fechas y cupo de 25 plazas.
2. El **Analista** asigna como docente al **Facilitador** Carlos González.
3. El **Facilitador** ingresa a `mis-cursos.html`, pulsa «Plan de Evaluación» y define 4 evaluaciones: Parcial 1 (25%), Parcial 2 (25%), Proyecto (30%) y Asistencia (20%), completando el 100% de la ponderación.
4. El **Analista** revisa en `verificacion.html` que la asignatura tenga facilitador y plan, y pulsa «Iniciar Curso», pasando el estado a `'En Curso'`.

### Escenario B: Postulación, Evaluación y Cierre de Ciclo
1. El **Participante** ingresa a `inscribir.html`, visualiza `"PROG-101"` abierto para su área de Informática y pulsa «Inscribirme».
2. El **Facilitador** imparte las clases y registra las calificaciones en `calificaciones.html`. El trigger de MySQL actualiza el promedio ponderado del alumno en tiempo real.
3. Al cumplirse la fecha límite (`fecha_fin`), el motor automático finaliza la asignatura:
   - Los alumnos aprobados ($\text{nota} \ge 10.00$) y reprobados ($\text{nota} < 10.00$) son archivados en `egresados`.
   - La matrícula activa en `inscripciones` se libera.
4. El **Participante** aprobado ingresa a `certificados.html` y descarga su diploma oficial de dos páginas con las firmas institucionales.
5. El **Participante** reprobado ve liberado el curso y puede volver a postularse cuando se abra un nuevo período.
6. El **Facilitador**, el **Analista** y el **Administrador** auditan la nómina de cierre en sus respectivos módulos de egresados.

---
# END OF FILE `05-roles-permisos.md`