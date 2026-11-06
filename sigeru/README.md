# SiGeRU — Sistema de Gestión de Residuos Urbanos

**Grupo LIGHT** · ESI Buceo · UTU/ANEP · 2026

Sistema web para coordinar la recolección de residuos en Montevideo. Conecta a vecinos, cuadrillas, operarios de centros de acopio y administradores.

- **Backend:** PHP 8 con arquitectura MVC y API REST en JSON (PDO + MySQL)
- **Frontend:** HTML, CSS y JavaScript sin frameworks, mapas con Leaflet y gráficos con Chart.js
- **Base de datos:** MySQL 8 (`sigeru.sql`)
- **Tests:** PHPUnit 10 (unitarios + integración)
- **Ejecución:** Docker Compose o XAMPP

---

## 3ra entrega — Qué se implementó

### Implementación final de frontend y backend

| Requisito | Backend | Frontend |
|---|---|---|
| **API de recolección** | `recolecciones.php` + `RecoleccionController` + modelo `Recoleccion` | `recolecciones.html` |
| **Registro y seguimiento de incidencias** | `incidencias.php` + tabla `incidencia_seguimiento` | `incidencias.html` (detalle con historial) |
| **Mapa interactivo** | `mapa.php` + `MapaController` | `mapa.html` |
| **Informes simples** | `informes.php` (JSON o CSV) | `informes.html` (imprimir / CSV) |
| **Panel de estadísticas** | `estadisticas.php` + `EstadisticaController` | `estadisticas.html` |
| **Notificaciones y alertas** | `notificaciones.php` + `services/ServicioAlertas.php` | campanita en el menú + `notificaciones.html` |

**API de recolección.** Registrar una recolección (vaciado de un contenedor) hace todo en una transacción: guarda el registro con kilos, camión y cuadrilla; deja el contenedor en 0 %; resuelve automáticamente los desbordes y faltas de recolección pendientes de ese contenedor y avisa a los vecinos que los habían reportado. También permite actualizar el nivel de llenado (como si fuera un sensor). Al marcar una parada de la ruta del día, la cuadrilla registra la recolección sin pasos extra.

**Incidencias.** El flujo es: `abierta` → `en_curso` (una cuadrilla la toma o el admin la asigna) → `resuelta`. Cada paso queda en el historial con quién lo hizo, cuándo y un comentario opcional. Además:
- No se aceptan reportes duplicados del mismo problema en el mismo contenedor.
- La prioridad se calcula sola: un desborde, o un contenedor al 90 % o más, es prioridad alta.
- Una cuadrilla no puede tomar una incidencia que ya tomó otra.
- Una cuadrilla solo cambia el estado de las incidencias que tomó.
- Un vecino solo ve sus propios reportes.

**Mapa interactivo.** Muestra contenedores (color según estado, número con el nivel de llenado), incidencias pendientes (rombos coloreados por prioridad), camiones y centros de acopio. Tiene capas que se prenden y apagan, filtros por zona, estado, nivel, tipo y prioridad, y acciones directas desde cada punto (reportar, registrar recolección, ver seguimiento). Se actualiza solo cada minuto. Al vecino no se le muestran datos personales ni la ubicación de los camiones.

**Informes.** Hay dos informes, por período y zona:
- Incidencias abiertas, cerradas o todas, con totales, tiempo promedio de resolución y conteos por tipo y por zona.
- Recolecciones.

Se pueden imprimir o guardar como PDF, y descargar en CSV preparado para Excel en español (separador `;` y tildes correctas).

**Estadísticas.** El panel de control muestra, por mes:
- Indicadores comparados con el mes anterior.
- Top de contenedores desbordados del mes.
- Evolución diaria de incidencias reportadas y resueltas.
- Incidencias por zona y por tipo.
- Kilos recolectados por día.
- Ocupación de los centros de acopio.
- Rendimiento de cada cuadrilla.

Cada gráfico tiene la opción «Ver como tabla».

**Notificaciones y alertas.** Una campanita en el menú muestra las notificaciones con un contador de no leídas. Las nuevas aparecen como aviso emergente, revisando cada 30 segundos. Se generan solas en estos casos:

| Evento | A quién le llega |
|---|---|
| Nueva incidencia | Administradores; también cuadrillas si es un desborde o es urgente |
| La cuadrilla toma o resuelve un reporte | El vecino que lo reportó |
| Asignación por el administrador | La cuadrilla asignada |
| Contenedor al 80 % o al 100 % | Administradores y cuadrillas |
| Centro de acopio al 80 % o al 100 % | Administradores y operarios del centro |
| Aviso publicado | Vecinos de la zona |
| Usuario nuevo pendiente de aprobación | Administradores |

### Correcciones de las entregas anteriores

**Base de datos**
- El hash del admin `admin@sigeru.uy` era inválido y no se podía entrar; ahora la contraseña es `admin1234`.
- Los usuarios de prueba decían `test1234`, pero su hash correspondía a `password`; se regeneró.
- Las FK no tenían `ON DELETE`: borrar un vecino, una cuadrilla o un camión con historial daba error 500.
- La PK compuesta de `recursos` impedía el `AUTO_INCREMENT` en algunos servidores.
- Había quedado un token de sesión en el volcado; se eliminó.
- Las zonas de varios contenedores no correspondían a su municipio.
- Los datos de prueba ahora usan fechas relativas (`NOW()`), así los informes del mes siempre muestran algo.

**Backend**
- `Ruta::crearRutaDemo` usaba columnas inexistentes (`cuadrilla_id`, `camion_id`): el botón «Generar ruta de demo» siempre fallaba.
- El panel de cuadrilla llamaba a `incidencias.php` con `PUT` y `accion=tomar` / `accion=estado`, que la API no aceptaba. La cuadrilla no podía tomar ni resolver incidencias.
- Editar un contenedor no guardaba la zona.
- Cambiar el rol de un usuario no lo movía de subtabla (vecinos → cuadrillas), lo que rompía la especialización.
- Eliminar un centro con operarios daba error 500. Ahora devuelve un mensaje claro.
- La conexión estaba fija a `localhost`, así que con Docker no conectaba. Ahora usa variables de entorno.
- Los errores no controlados devolvían HTML. Ahora devuelven JSON.
- Cerrar sesión no invalidaba el token. Ahora existe `logout.php`.
- Al desactivar un usuario o cambiarle la contraseña se cierran sus sesiones abiertas.
- El login revelaba si un email estaba registrado aunque la contraseña fuera incorrecta.
- Validaciones nuevas: cédula uruguaya con dígito verificador, email, contraseña de 8 caracteres o más, coordenadas, matrícula y estados. Un admin no puede desactivarse a sí mismo.

**Frontend**
- **Seguridad (XSS):** los datos que escriben los usuarios se insertaban con `innerHTML` sin escapar. Ahora todo pasa por `escaparHtml()`.
- El menú del administrador desaparecía en el panel: `sesion.js` lo pisaba. Ahora hay un menú por rol.
- Los centros de acopio no se dibujaban en el mapa (el bucle estaba vacío).
- `API_URL` estaba fijo para XAMPP. Ahora detecta solo si se abre desde XAMPP o desde Docker.
- Contenedores: la zona se elige de una lista (antes era un número) y se agregaron nivel y capacidad.
- El mensaje de «guardado» no llegaba a verse porque se borraba enseguida.

---

## Cómo ejecutarlo

### Opción A: Docker (recomendada)

```bash
docker compose up -d --build
```

| Servicio | URL |
|---|---|
| Frontend | http://localhost:3000 |
| API | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| MySQL (desde la PC) | `localhost:3307` (usuario `root`, contraseña `light`) |

La base se crea sola con `sigeru.sql` la primera vez. Para volver a cargarla desde cero:

```bash
docker compose down -v      # borra el volumen de la base
docker compose up -d --build
```

### Opción B: XAMPP

1. Copiar la carpeta del proyecto a `C:\xampp\htdocs\sigeru` (tiene que quedar `htdocs/sigeru/backend` y `htdocs/sigeru/frontend`).
2. En phpMyAdmin, importar `sigeru.sql`. Borra y recrea la base `sigeru`.
3. Si la contraseña de root de MySQL no es `light`, cambiarla en `backend/config/conexion.php`.
4. Abrir http://localhost/sigeru/frontend/index.html

### Usuarios de prueba

| Email | Contraseña | Rol |
|---|---|---|
| admin@sigeru.uy | `admin1234` | Administrador |
| martinestevezesi@gmail.com | (la de siempre) | Administrador |
| ana.garcia@sigeru.uy | `test1234` | Vecino (Municipio C) |
| lucia.fernandez@sigeru.uy | `test1234` | Vecino (Municipio CH) |
| carlos.lopez@sigeru.uy | `test1234` | Cuadrilla |
| jorge.silva@sigeru.uy | `test1234` | Cuadrilla |
| maria.rod@sigeru.uy | `test1234` | Operario (Centro Buceo) |
| pedro.perez@sigeru.uy | `test1234` | Vecino **pendiente** (para probar la aprobación) |

Códigos de funcionario libres para probar el registro: `SIGERU-CUA-9X3M` (cuadrilla) y `SIGERU-OPE-4L8N` (operario).

---

## Testing

Hay 129 tests en `backend/tests/`, en dos grupos:

- **`tests/Unit/`** (117 tests): reglas de negocio de todos los controladores, con dobles de prueba en lugar de la base. Cubren los CRUD de usuarios, contenedores, camiones y centros, más incidencias, recolección, alertas, informes, estadísticas, mapa, autenticación y validaciones.
- **`tests/Integracion/`** (12 tests): CRUD y flujos completos contra MySQL real, por ejemplo reportar → tomar → recolectar → resolver → notificar. Usan una base aparte, `sigeru_test`, que se crea sola desde `sigeru.sql`; nunca tocan la base `sigeru`. Cada test se deshace al terminar. Si no hay MySQL, se marcan como «saltados» y no fallan.

```bash
# Con Docker
docker compose exec backend composer test

# Con XAMPP / PHP local
cd backend
composer install
composer test                  # todos
composer test:unit             # solo unitarios (no necesitan base)
composer test:integracion      # solo integración
```

---

## API — Endpoints

Todos devuelven JSON. Los protegidos requieren el header `Authorization: Bearer <token>` que devuelve `login.php`.

| Método | Endpoint | Descripción | Roles |
|---|---|---|---|
| POST | `/login.php` | Iniciar sesión | público |
| POST | `/logout.php` | Cerrar sesión (invalida el token) | todos |
| POST | `/registro.php` | Registro (queda pendiente) | público |
| GET | `/zonas.php` | Municipios | público |
| GET/POST/PUT/DELETE | `/incidencias.php` | Reportar, tomar, asignar, cambiar estado, comentar, historial | admin, cuadrilla, vecino |
| GET/POST/PUT/DELETE | `/recolecciones.php` | Registrar recolección, nivel de llenado, historial, resumen | admin, cuadrilla |
| GET | `/mapa.php` | Contenedores, incidencias, camiones y centros con filtros | admin, cuadrilla, vecino |
| GET | `/informes.php?tipo=incidencias\|recolecciones` | Informes (`&formato=csv` para descargar) | admin |
| GET | `/estadisticas.php?mes=AAAA-MM` | Panel de control | admin |
| GET/PUT/DELETE | `/notificaciones.php` | Notificaciones propias | todos |
| GET/POST/PUT/DELETE | `/usuarios.php`, `/contenedores.php`, `/camiones.php`, `/centros_acopio.php` | ABM | admin |
| GET/POST/PUT | `/panel_*.php` | Datos de cada panel | según el rol |

---

## Estructura

```
sigeru/
├── sigeru.sql                  Base de datos completa + datos de prueba
├── docker-compose.yml          db · backend · frontend · phpmyadmin
├── docker/                     Dockerfile, Apache y Nginx
├── backend/
│   ├── config/                 Conexión (variables de entorno) y cabeceras
│   ├── helpers/                Respuestas JSON, autenticación y validaciones
│   ├── models/                 Acceso a datos (una clase por entidad)
│   ├── controllers/            Reglas de negocio
│   ├── services/               ServicioAlertas (quién se entera de cada evento)
│   ├── tests/                  Unit/ e Integracion/ (PHPUnit)
│   └── *.php                   Endpoints de la API
└── frontend/
    ├── css/estilos.css
    ├── js/                     Un archivo por página + sesion.js y config.js comunes
    └── *.html
```
