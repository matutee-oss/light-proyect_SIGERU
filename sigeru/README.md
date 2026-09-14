# SiGeRU — Sistema de Gestión de Residuos Urbanos
**Grupo LIGHT** | ESI Buceo, UTU/ANEP | 2026

---

## 2da Entrega — Cambios implementados

### Frontend
- ✅ Correcciones de la primera entrega (nav, camiones, contenedores con editar)
- ✅ Registro e inicio de sesión completo (con JWT-like token, redirección por rol)
- ✅ **CRUD completo de usuarios** (`usuarios.html` + `usuarios.js`) — filtros por rol y estado
- ✅ **CRUD de contenedores** — crear, editar, eliminar con coordenadas
- ✅ **CRUD de camiones (flota)** — crear, editar, eliminar con capacidad y coordenadas
- ✅ **CRUD de centros de acopio y maquinaria** (`centros-acopio.html`) — gestión de recursos

### Backend
- ✅ `usuarios.php` — endpoint CRUD protegido (solo administradores)
- ✅ `camiones.php` — ahora requiere autenticación + rol administrador
- ✅ `contenedores.php` — ahora requiere autenticación + rol administrador
- ✅ `centros_acopio.php` — CRUD completo con gestión de maquinaria
- ✅ `incidencias.php` — endpoint separado con separación por roles
- ✅ Seguridad: todos los endpoints protegidos con `requerirUsuario()` + `requerirRol()`

### Testing
- ✅ Tests unitarios de la API en `backend/tests/`
  - `AuthTest.php`
  - `CamionControllerTest.php`
  - `ContenedorControllerTest.php`
  - `UsuarioControllerTest.php`
- Ejecutar: `cd backend && composer install && vendor/bin/phpunit`

### Base de datos
- ✅ Correcciones de primera entrega
- ✅ Datos de prueba cargados (usuarios, incidencias, recursos)

---

## Ejecución con Docker

```bash
# Clonar / descomprimir el proyecto
cd sigeru_v2

# Levantar todos los servicios
docker compose up -d

# URLs:
# Frontend:    http://localhost:3000
# Backend API: http://localhost:8080
# phpMyAdmin:  http://localhost:8081
```

### Credenciales de prueba
| Email | Contraseña | Rol |
|-------|-----------|-----|
| martinestevezesi@gmail.com | (la del sistema) | Administrador |
| ana.garcia@sigeru.uy | test1234 | Vecino |
| carlos.lopez@sigeru.uy | test1234 | Cuadrilla |
| maria.rod@sigeru.uy | test1234 | Operario |

---

## Estructura del proyecto
```
sigeru_v2/
├── backend/
│   ├── config/
│   │   ├── cabeceras.php
│   │   └── conexion.php
│   ├── controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── CamionController.php
│   │   ├── CentroAcopioController.php   ← NUEVO
│   │   ├── ContenedorController.php
│   │   ├── CuadrillaController.php
│   │   ├── IncidenciaController.php
│   │   ├── OperarioController.php
│   │   ├── UsuarioController.php        ← NUEVO
│   │   └── VecinoController.php
│   ├── helpers/
│   │   ├── autenticacion.php
│   │   └── respuesta.php
│   ├── models/
│   │   ├── Aviso.php
│   │   ├── Camion.php
│   │   ├── CentroAcopio.php
│   │   ├── CodigoFuncionario.php
│   │   ├── Contenedor.php
│   │   ├── Incidencia.php
│   │   ├── Ruta.php
│   │   ├── Usuario.php
│   │   └── Zona.php
│   ├── tests/                           ← NUEVO
│   │   ├── AuthTest.php
│   │   ├── CamionControllerTest.php
│   │   ├── ContenedorControllerTest.php
│   │   └── UsuarioControllerTest.php
│   ├── camiones.php      ← protegido con auth
│   ├── centros_acopio.php ← NUEVO
│   ├── contenedores.php  ← protegido con auth
│   ├── incidencias.php   ← NUEVO
│   ├── index.php
│   ├── login.php
│   ├── panel_admin.php
│   ├── panel_cuadrilla.php
│   ├── panel_operario.php
│   ├── panel_vecino.php
│   ├── perfil.php
│   ├── registro.php
│   ├── usuarios.php      ← NUEVO
│   ├── composer.json     ← NUEVO
│   └── phpunit.xml       ← NUEVO
├── frontend/
│   ├── css/estilos.css
│   ├── js/
│   │   ├── camiones.js      ← actualizado (editar)
│   │   ├── centros-acopio.js ← NUEVO
│   │   ├── config.js
│   │   ├── contenedores.js  ← actualizado (editar)
│   │   ├── login.js
│   │   ├── panel-admin.js
│   │   ├── panel-cuadrilla.js
│   │   ├── panel-operario.js
│   │   ├── panel-vecino.js
│   │   ├── perfil.js
│   │   ├── registro.js
│   │   ├── sesion.js
│   │   └── usuarios.js      ← NUEVO
│   ├── camiones.html        ← actualizado
│   ├── centros-acopio.html  ← NUEVO
│   ├── contenedores.html    ← actualizado
│   ├── index.html
│   ├── login.html
│   ├── panel-admin.html     ← actualizado (nav, accesos rápidos)
│   ├── panel-cuadrilla.html
│   ├── panel-operario.html
│   ├── panel-vecino.html
│   ├── perfil.html
│   └── registro.html
├── docker/
│   ├── apache.conf
│   ├── Dockerfile
│   └── nginx.conf
├── sigeru.sql               ← con datos de prueba de 2da entrega
├── docker-compose.yml       ← NUEVO
└── README.md                ← NUEVO
```
