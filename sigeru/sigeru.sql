-- ============================================================
--  SiGeRU — Sistema de Gestión de Residuos Urbanos
--  Grupo LIGHT · ESI Buceo · UTU/ANEP · 2026
--  Script completo de base de datos — 3ra entrega
--
--  Cómo usarlo:
--   - XAMPP / phpMyAdmin: importar este archivo (borra y recrea la base `sigeru`).
--   - Docker: se ejecuta solo la primera vez que se crea el volumen de MySQL.
--
--  Correcciones respecto a entregas anteriores:
--   - Hash de contraseña del admin (id 1) era inválido: ahora es admin1234.
--   - Los usuarios de prueba decían "test1234" pero el hash correspondía a
--     "password": se regeneró el hash para que test1234 funcione.
--   - Se eliminó el token de sesión que había quedado en el volcado.
--   - Las FK no tenían reglas ON DELETE: borrar un vecino, cuadrilla o
--     camión con historial fallaba. Ahora se usa SET NULL donde corresponde.
--   - Zonas de los contenedores corregidas según el municipio real.
--   - Los datos de prueba usan fechas relativas (NOW()) para que los
--     informes y estadísticas del mes muestren información.
--   - Nuevas tablas: recolecciones, incidencia_seguimiento, notificaciones.
--   - Nuevas columnas: nivel de llenado en contenedores, descripción y
--     prioridad en incidencias, coordenadas en centros de acopio.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `sigeru`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `sigeru`;

DROP TABLE IF EXISTS `notificaciones`, `incidencia_seguimiento`, `recolecciones`,
  `ruta_contenedor`, `rutas`, `incidencias`, `ingresos_centro`, `recursos`,
  `sesiones`, `codigos_funcionario`, `operarios`, `cuadrillas`, `vecinos`,
  `administradores`, `usuarios`, `avisos`, `contenedores`, `camiones`,
  `centros_acopio`, `zonas`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Zonas (los 8 municipios de Montevideo)
-- ------------------------------------------------------------
CREATE TABLE `zonas` (
  `id_zona`     INT NOT NULL AUTO_INCREMENT,
  `nombre_zona` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_zona`),
  UNIQUE KEY `uq_nombre_zona` (`nombre_zona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Usuarios y especialización (total y disjunta)
-- ------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id_usuario`    INT NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100) NOT NULL,
  `apellido`      VARCHAR(100) NOT NULL,
  `ci`            VARCHAR(20)  NOT NULL,
  `telefono`      VARCHAR(20)  DEFAULT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `tipo_usuario`  ENUM('administrador','cuadrilla','operario','vecino') NOT NULL,
  `estado`        ENUM('activo','pendiente','inactivo') NOT NULL DEFAULT 'pendiente',
  `foto`          VARCHAR(255) DEFAULT NULL,
  `creado_en`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_ci` (`ci`),
  UNIQUE KEY `uq_usuario_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `administradores` (
  `id_usuario` INT NOT NULL,
  PRIMARY KEY (`id_usuario`),
  CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cuadrillas` (
  `id_usuario` INT NOT NULL,
  PRIMARY KEY (`id_usuario`),
  CONSTRAINT `fk_cuadrilla_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vecinos` (
  `id_usuario` INT NOT NULL,
  `id_zona`    INT DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  KEY `fk_vecino_zona` (`id_zona`),
  CONSTRAINT `fk_vecino_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_vecino_zona` FOREIGN KEY (`id_zona`)
    REFERENCES `zonas` (`id_zona`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Centros de acopio, recursos e ingresos
-- ------------------------------------------------------------
CREATE TABLE `centros_acopio` (
  `id_centro` INT NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(100) NOT NULL,
  `ubicacion` VARCHAR(200) NOT NULL,
  `capacidad` DECIMAL(10,2) NOT NULL COMMENT 'Capacidad en m3',
  `latitud`   DECIMAL(10,7) DEFAULT NULL,
  `longitud`  DECIMAL(10,7) DEFAULT NULL,
  PRIMARY KEY (`id_centro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `operarios` (
  `id_usuario` INT NOT NULL,
  `id_centro`  INT NOT NULL,
  PRIMARY KEY (`id_usuario`),
  KEY `fk_operario_centro` (`id_centro`),
  CONSTRAINT `fk_operario_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_operario_centro` FOREIGN KEY (`id_centro`)
    REFERENCES `centros_acopio` (`id_centro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Corrección: la PK compuesta (id_recurso, id_centro) impedía el AUTO_INCREMENT
-- en algunos servidores. id_recurso ya es único por sí solo.
CREATE TABLE `recursos` (
  `id_recurso` INT NOT NULL AUTO_INCREMENT,
  `id_centro`  INT NOT NULL,
  `tipo`       VARCHAR(100) NOT NULL,
  `cantidad`   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_recurso`),
  KEY `fk_recurso_centro` (`id_centro`),
  CONSTRAINT `fk_recurso_centro` FOREIGN KEY (`id_centro`)
    REFERENCES `centros_acopio` (`id_centro`) ON DELETE CASCADE,
  CONSTRAINT `chk_recurso_cantidad` CHECK (`cantidad` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Flota y contenedores
-- ------------------------------------------------------------
CREATE TABLE `camiones` (
  `id_camion`    INT NOT NULL AUTO_INCREMENT,
  `matricula`    VARCHAR(20) NOT NULL,
  `latitud`      DECIMAL(10,7) DEFAULT NULL,
  `longitud`     DECIMAL(10,7) DEFAULT NULL,
  `estado`       ENUM('disponible','en_ruta','mantenimiento','fuera_servicio') NOT NULL DEFAULT 'disponible',
  `capacidad_kg` DECIMAL(10,2) NOT NULL DEFAULT 6000.00,
  PRIMARY KEY (`id_camion`),
  UNIQUE KEY `uq_camion_matricula` (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingresos_centro` (
  `id_ingreso`    INT NOT NULL AUTO_INCREMENT,
  `id_centro`     INT NOT NULL,
  `id_camion`     INT DEFAULT NULL,
  `toneladas`     DECIMAL(10,2) NOT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `registrado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ingreso`),
  KEY `fk_ingreso_centro` (`id_centro`),
  KEY `fk_ingreso_camion` (`id_camion`),
  CONSTRAINT `fk_ingreso_centro` FOREIGN KEY (`id_centro`)
    REFERENCES `centros_acopio` (`id_centro`) ON DELETE CASCADE,
  CONSTRAINT `fk_ingreso_camion` FOREIGN KEY (`id_camion`)
    REFERENCES `camiones` (`id_camion`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contenedores` (
  `id_contenedor`      INT NOT NULL AUTO_INCREMENT,
  `ubicacion`          VARCHAR(200) NOT NULL,
  `estado`             ENUM('funcional','roto','desbordado') NOT NULL DEFAULT 'funcional',
  `tipo_residuo`       VARCHAR(100) NOT NULL,
  `id_zona`            INT DEFAULT NULL,
  `latitud`            DECIMAL(10,7) DEFAULT NULL,
  `longitud`           DECIMAL(10,7) DEFAULT NULL,
  `capacidad_litros`   INT NOT NULL DEFAULT 2400,
  `nivel_llenado`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Porcentaje 0-100',
  `ultima_recoleccion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_contenedor`),
  KEY `fk_contenedor_zona` (`id_zona`),
  CONSTRAINT `fk_contenedor_zona` FOREIGN KEY (`id_zona`)
    REFERENCES `zonas` (`id_zona`) ON DELETE SET NULL,
  CONSTRAINT `chk_contenedor_nivel` CHECK (`nivel_llenado` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rutas de recolección
-- ------------------------------------------------------------
CREATE TABLE `rutas` (
  `id_ruta`      INT NOT NULL AUTO_INCREMENT,
  `nombre`       VARCHAR(100) NOT NULL,
  `zona`         VARCHAR(100) NOT NULL,
  `id_cuadrilla` INT DEFAULT NULL,
  `id_camion`    INT DEFAULT NULL,
  `descripcion`  TEXT,
  `fecha`        DATE DEFAULT NULL,
  `creado_en`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ruta`),
  KEY `fk_ruta_cuadrilla` (`id_cuadrilla`),
  KEY `fk_ruta_camion` (`id_camion`),
  CONSTRAINT `fk_ruta_cuadrilla` FOREIGN KEY (`id_cuadrilla`)
    REFERENCES `cuadrillas` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_ruta_camion` FOREIGN KEY (`id_camion`)
    REFERENCES `camiones` (`id_camion`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ruta_contenedor` (
  `id_ruta`       INT NOT NULL,
  `id_contenedor` INT NOT NULL,
  `orden`         INT NOT NULL,
  `atendido`      TINYINT(1) NOT NULL DEFAULT 0,
  `atendido_en`   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id_ruta`, `id_contenedor`),
  KEY `fk_rc_contenedor` (`id_contenedor`),
  CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`)
    REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE,
  CONSTRAINT `fk_rc_contenedor` FOREIGN KEY (`id_contenedor`)
    REFERENCES `contenedores` (`id_contenedor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVA (3ra entrega): cada vaciado de un contenedor queda registrado.
CREATE TABLE `recolecciones` (
  `id_recoleccion` INT NOT NULL AUTO_INCREMENT,
  `id_contenedor`  INT NOT NULL,
  `id_camion`      INT DEFAULT NULL,
  `id_cuadrilla`   INT DEFAULT NULL,
  `id_ruta`        INT DEFAULT NULL,
  `kg_recolectados` DECIMAL(8,2) DEFAULT NULL,
  `nivel_previo`   TINYINT UNSIGNED DEFAULT NULL,
  `observaciones`  VARCHAR(255) DEFAULT NULL,
  `recolectado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_recoleccion`),
  KEY `idx_recoleccion_fecha` (`recolectado_en`),
  KEY `fk_recoleccion_contenedor` (`id_contenedor`),
  KEY `fk_recoleccion_camion` (`id_camion`),
  KEY `fk_recoleccion_cuadrilla` (`id_cuadrilla`),
  KEY `fk_recoleccion_ruta` (`id_ruta`),
  CONSTRAINT `fk_recoleccion_contenedor` FOREIGN KEY (`id_contenedor`)
    REFERENCES `contenedores` (`id_contenedor`) ON DELETE CASCADE,
  CONSTRAINT `fk_recoleccion_camion` FOREIGN KEY (`id_camion`)
    REFERENCES `camiones` (`id_camion`) ON DELETE SET NULL,
  CONSTRAINT `fk_recoleccion_cuadrilla` FOREIGN KEY (`id_cuadrilla`)
    REFERENCES `cuadrillas` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_recoleccion_ruta` FOREIGN KEY (`id_ruta`)
    REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL,
  CONSTRAINT `chk_recoleccion_kg` CHECK (`kg_recolectados` IS NULL OR `kg_recolectados` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Incidencias y su seguimiento
-- ------------------------------------------------------------
CREATE TABLE `incidencias` (
  `id_incidencia`    INT NOT NULL AUTO_INCREMENT,
  `tipo`             ENUM('rotura','desborde','falta_recoleccion','otro') NOT NULL,
  `estado`           ENUM('abierta','en_curso','resuelta') NOT NULL DEFAULT 'abierta',
  `prioridad`        ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  `descripcion`      TEXT,
  `fecha_apertura`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` TIMESTAMP NULL DEFAULT NULL,
  `id_contenedor`    INT NOT NULL,
  `id_vecino`        INT DEFAULT NULL,
  `id_cuadrilla`     INT DEFAULT NULL,
  `reportado_por`    INT DEFAULT NULL,
  PRIMARY KEY (`id_incidencia`),
  KEY `idx_incidencia_estado` (`estado`),
  KEY `idx_incidencia_fecha` (`fecha_apertura`),
  KEY `fk_incidencia_contenedor` (`id_contenedor`),
  KEY `fk_incidencia_vecino` (`id_vecino`),
  KEY `fk_incidencia_cuadrilla` (`id_cuadrilla`),
  KEY `fk_incidencia_reporta` (`reportado_por`),
  CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`id_contenedor`)
    REFERENCES `contenedores` (`id_contenedor`) ON DELETE CASCADE,
  CONSTRAINT `fk_incidencia_vecino` FOREIGN KEY (`id_vecino`)
    REFERENCES `vecinos` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_incidencia_cuadrilla` FOREIGN KEY (`id_cuadrilla`)
    REFERENCES `cuadrillas` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_incidencia_reporta` FOREIGN KEY (`reportado_por`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVA (3ra entrega): historial de cada incidencia (quién hizo qué y cuándo).
CREATE TABLE `incidencia_seguimiento` (
  `id_seguimiento`  INT NOT NULL AUTO_INCREMENT,
  `id_incidencia`   INT NOT NULL,
  `id_usuario`      INT DEFAULT NULL,
  `accion`          ENUM('creada','asignada','estado','comentario') NOT NULL,
  `estado_anterior` ENUM('abierta','en_curso','resuelta') DEFAULT NULL,
  `estado_nuevo`    ENUM('abierta','en_curso','resuelta') DEFAULT NULL,
  `comentario`      VARCHAR(500) DEFAULT NULL,
  `creado_en`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_seguimiento`),
  KEY `fk_seguimiento_incidencia` (`id_incidencia`),
  KEY `fk_seguimiento_usuario` (`id_usuario`),
  CONSTRAINT `fk_seguimiento_incidencia` FOREIGN KEY (`id_incidencia`)
    REFERENCES `incidencias` (`id_incidencia`) ON DELETE CASCADE,
  CONSTRAINT `fk_seguimiento_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Comunicación: avisos y notificaciones
-- ------------------------------------------------------------
CREATE TABLE `avisos` (
  `id_aviso`     INT NOT NULL AUTO_INCREMENT,
  `titulo`       VARCHAR(150) NOT NULL,
  `mensaje`      TEXT NOT NULL,
  `id_zona`      INT DEFAULT NULL,
  `publicado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `activo`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_aviso`),
  KEY `fk_aviso_zona` (`id_zona`),
  CONSTRAINT `fk_aviso_zona` FOREIGN KEY (`id_zona`)
    REFERENCES `zonas` (`id_zona`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NUEVA (3ra entrega): alertas personales para cada usuario.
CREATE TABLE `notificaciones` (
  `id_notificacion` INT NOT NULL AUTO_INCREMENT,
  `id_usuario`      INT NOT NULL,
  `tipo`            ENUM('incidencia','recoleccion','contenedor','capacidad','aviso','sistema') NOT NULL DEFAULT 'sistema',
  `nivel`           ENUM('info','advertencia','critica') NOT NULL DEFAULT 'info',
  `titulo`          VARCHAR(150) NOT NULL,
  `mensaje`         VARCHAR(500) NOT NULL,
  `enlace`          VARCHAR(255) DEFAULT NULL,
  `leida`           TINYINT(1) NOT NULL DEFAULT 0,
  `creada_en`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notificacion_usuario` (`id_usuario`, `leida`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seguridad: códigos de funcionario y sesiones
-- ------------------------------------------------------------
CREATE TABLE `codigos_funcionario` (
  `id_codigo`    INT NOT NULL AUTO_INCREMENT,
  `codigo`       VARCHAR(30) NOT NULL,
  `tipo_usuario` ENUM('administrador','cuadrilla','operario') NOT NULL,
  `usado`        TINYINT(1) NOT NULL DEFAULT 0,
  `id_usuario`   INT DEFAULT NULL,
  `creado_en`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_codigo`),
  UNIQUE KEY `uq_codigo` (`codigo`),
  KEY `fk_codigo_usuario` (`id_usuario`),
  CONSTRAINT `fk_codigo_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sesiones` (
  `token`      VARCHAR(64) NOT NULL,
  `id_usuario` INT NOT NULL,
  `creado_en`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en`  DATETIME NOT NULL,
  PRIMARY KEY (`token`),
  KEY `fk_sesion_usuario` (`id_usuario`),
  CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- DATOS INICIALES
-- ============================================================

INSERT INTO `zonas` (`id_zona`, `nombre_zona`) VALUES
(1, 'Municipio A'), (2, 'Municipio B'), (3, 'Municipio C'), (4, 'Municipio CH'),
(5, 'Municipio D'), (6, 'Municipio E'), (7, 'Municipio F'), (8, 'Municipio G');

-- Contraseñas: admin@sigeru.uy → admin1234 · usuarios de prueba → test1234
INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `ci`, `telefono`, `email`, `password_hash`, `tipo_usuario`, `estado`) VALUES
(1, 'Admin',  'SiGeRU',    '11111111', '099111111', 'admin@sigeru.uy',            '$2y$10$5/IMJt8I/X.SGrFNC8Vd.OptcKb3tZmA3uL9kudZcXZeOT6FphTQ2', 'administrador', 'activo'),
(2, 'Martin', 'Estevez',   '57484312', '092442544', 'martinestevezesi@gmail.com', '$2y$10$Qksnf3H41ZfGNs9nx3X5fO1RkZRnJ/B1vHL6NSZzGyNWze93QwKEC', 'administrador', 'activo'),
(3, 'Ana',    'García',    '22222222', '099222222', 'ana.garcia@sigeru.uy',       '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'vecino',        'activo'),
(4, 'Carlos', 'López',     '33333333', '099333333', 'carlos.lopez@sigeru.uy',     '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'cuadrilla',     'activo'),
(5, 'María',  'Rodríguez', '44444444', '099444444', 'maria.rod@sigeru.uy',        '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'operario',      'activo'),
(6, 'Pedro',  'Pérez',     '55555555', '099555555', 'pedro.perez@sigeru.uy',      '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'vecino',        'pendiente'),
(7, 'Lucía',  'Fernández', '66666666', '099666666', 'lucia.fernandez@sigeru.uy',  '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'vecino',        'activo'),
(8, 'Jorge',  'Silva',     '77777777', '099777777', 'jorge.silva@sigeru.uy',      '$2y$10$/ii4DFF/DG931L39U3LfM.UBf.E5pg9jTD1uExArHFRDkRMRSbYyq', 'cuadrilla',     'activo');

INSERT INTO `administradores` (`id_usuario`) VALUES (1), (2);
INSERT INTO `cuadrillas` (`id_usuario`) VALUES (4), (8);
INSERT INTO `vecinos` (`id_usuario`, `id_zona`) VALUES (3, 3), (6, NULL), (7, 4);

INSERT INTO `centros_acopio` (`id_centro`, `nombre`, `ubicacion`, `capacidad`, `latitud`, `longitud`) VALUES
(1, 'Centro de Acopio Buceo',   'Rambla Rep. de México, Montevideo', 500.00,  -34.9050000, -56.1290000),
(2, 'Vertedero Felipe Cardoso', 'Camino Felipe Cardoso, Montevideo', 5000.00, -34.8300000, -56.1290000);

INSERT INTO `operarios` (`id_usuario`, `id_centro`) VALUES (5, 1);

INSERT INTO `recursos` (`id_centro`, `tipo`, `cantidad`) VALUES
(1, 'Contenedores de repuesto', 10),
(1, 'Balanza de pesaje', 1),
(2, 'Palas cargadoras', 2),
(2, 'Compactadoras', 1);

INSERT INTO `codigos_funcionario` (`codigo`, `tipo_usuario`, `usado`, `id_usuario`) VALUES
('SIGERU-ADM-7F2K', 'administrador', 1, 2),
('SIGERU-CUA-9X3M', 'cuadrilla',     0, NULL),
('SIGERU-OPE-4L8N', 'operario',      0, NULL);

INSERT INTO `camiones` (`id_camion`, `matricula`, `latitud`, `longitud`, `estado`, `capacidad_kg`) VALUES
(1, 'SBC 1234', -34.8969000, -56.1425000, 'disponible',    6000.00),
(2, 'SBD 5678', -34.8988000, -56.1613000, 'en_ruta',       6000.00),
(3, 'SBE 9012', -34.8650000, -56.2030000, 'en_ruta',       8000.00),
(4, 'SBF 3456', NULL,         NULL,         'mantenimiento', 6000.00);

-- Contenedores: zonas corregidas según el municipio de cada dirección
INSERT INTO `contenedores` (`id_contenedor`, `ubicacion`, `estado`, `tipo_residuo`, `id_zona`, `latitud`, `longitud`, `capacidad_litros`, `nivel_llenado`, `ultima_recoleccion`) VALUES
(1,  'Av. Italia y Propios',                   'funcional',  'Residuos comunes', 4, -34.8969000, -56.1425000, 2400, 55,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2,  'Bvar. Artigas y 8 de Octubre',           'desbordado', 'Reciclables',      3, -34.8988000, -56.1613000, 2400, 100, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3,  'Camino Maldonado km 15',                 'roto',       'Residuos comunes', 7, -34.8562000, -56.0801000, 2400, 40,  DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4,  'Rambla y Massini',                       'funcional',  'Reciclables',      6, -34.9022000, -56.1362000, 1100, 30,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5,  '18 de Julio y Yaguarón',                 'funcional',  'Residuos comunes', 2, -34.9046000, -56.1912000, 2400, 70,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6,  'Grecia y Carlos María Ramírez',          'desbordado', 'Residuos comunes', 1, -34.8885000, -56.2560000, 2400, 100, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(7,  'Rincón y Misiones',                      'funcional',  'Reciclables',      2, -34.9065000, -56.2060000, 1100, 20,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(8,  'Av. Agraciada y Castro',                 'funcional',  'Residuos comunes', 3, -34.8650000, -56.2030000, 2400, 45,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9,  'Bvar. Aparicio Saravia y San Martín',    'desbordado', 'Residuos comunes', 5, -34.8370000, -56.1730000, 2400, 95,  DATE_SUB(NOW(), INTERVAL 4 DAY)),
(10, 'Av. Italia y Michigan',                  'funcional',  'Reciclables',      6, -34.8915000, -56.1010000, 2400, 35,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 'Camino Maldonado y Felipe Cardoso',      'funcional',  'Residuos comunes', 7, -34.8430000, -56.1010000, 2400, 85,  DATE_SUB(NOW(), INTERVAL 3 DAY)),
(12, 'Av. Garzón y Lezica',                    'funcional',  'Residuos comunes', 8, -34.8020000, -56.2200000, 2400, 60,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(13, '21 de Setiembre y Ellauri',              'funcional',  'Reciclables',      4, -34.9160000, -56.1560000, 1100, 15,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(14, 'Av. Millán y Lucas Obes',                'roto',       'Residuos comunes', 3, -34.8600000, -56.1980000, 2400, 50,  DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO `avisos` (`titulo`, `mensaje`, `id_zona`, `publicado_en`, `activo`) VALUES
('Sin recolección el jueves', 'Por feriado nacional, no habrá recolección de residuos el jueves. Se retomará el viernes en el horario habitual.', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), 1),
('Nuevo contenedor en Buceo', 'Se instaló un nuevo contenedor de reciclables en Rambla y Massini.', 6, DATE_SUB(NOW(), INTERVAL 6 DAY), 1);

-- Ingresos a centros de acopio (últimos días, para los gráficos del operario)
INSERT INTO `ingresos_centro` (`id_centro`, `id_camion`, `toneladas`, `registrado_en`) VALUES
(1, 1, 2.40, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 2, 1.80, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 1, 2.70, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 1, 3.10, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, 2.90, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 2.20, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 4.50, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 3, 3.80, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 3, 4.10, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Incidencias: mezcla de este mes y el anterior para informes y estadísticas
INSERT INTO `incidencias` (`id_incidencia`, `tipo`, `estado`, `prioridad`, `descripcion`, `fecha_apertura`, `fecha_resolucion`, `id_contenedor`, `id_vecino`, `id_cuadrilla`, `reportado_por`) VALUES
(1,  'desborde',          'resuelta', 'alta',  'Bolsas en la vereda alrededor del contenedor.', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 39 DAY), 2,  3,    4,    3),
(2,  'rotura',            'en_curso', 'media', 'La tapa está partida.',                         DATE_SUB(NOW(), INTERVAL 9 DAY),  NULL,                             3,  3,    4,    3),
(3,  'falta_recoleccion', 'abierta',  'media', 'Hace tres días que no pasa el camión.',         DATE_SUB(NOW(), INTERVAL 2 DAY),  NULL,                             1,  7,    NULL, 7),
(4,  'otro',              'abierta',  'baja',  'Hay un mueble viejo al lado del contenedor.',   DATE_SUB(NOW(), INTERVAL 1 DAY),  NULL,                             4,  3,    NULL, 3),
(5,  'desborde',          'resuelta', 'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 34 DAY), 2,  3,    4,    3),
(6,  'desborde',          'resuelta', 'alta',  'Desborde después del fin de semana.',           DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), 2,  3,    4,    3),
(7,  'desborde',          'resuelta', 'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 8 DAY),  DATE_SUB(NOW(), INTERVAL 7 DAY),  2,  3,    8,    3),
(8,  'desborde',          'abierta',  'alta',  'Otra vez desbordado, hay olor.',                DATE_SUB(NOW(), INTERVAL 5 HOUR), NULL,                             2,  3,    NULL, 3),
(9,  'desborde',          'resuelta', 'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY), 6,  NULL, 8,    1),
(10, 'desborde',          'en_curso', 'alta',  'Basura desparramada por la calle.',             DATE_SUB(NOW(), INTERVAL 1 DAY),  NULL,                             6,  NULL, 8,    1),
(11, 'desborde',          'abierta',  'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL,                             9,  NULL, NULL, 4),
(12, 'desborde',          'resuelta', 'media', NULL,                                            DATE_SUB(NOW(), INTERVAL 6 DAY),  DATE_SUB(NOW(), INTERVAL 5 DAY),  9,  NULL, 4,    4),
(13, 'rotura',            'abierta',  'media', 'Rueda rota, no se puede mover.',                DATE_SUB(NOW(), INTERVAL 4 DAY),  NULL,                             14, 3,    NULL, 3),
(14, 'falta_recoleccion', 'resuelta', 'media', NULL,                                            DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY),  11, NULL, 4,    1),
(15, 'desborde',          'resuelta', 'media', NULL,                                            DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_SUB(NOW(), INTERVAL 2 DAY),  4,  7,    8,    7),
(16, 'desborde',          'resuelta', 'alta',  'Bolsas apiladas al costado.',                   DATE_SUB(NOW(), INTERVAL 4 DAY),  DATE_SUB(NOW(), INTERVAL 3 DAY),  2,  3,    4,    3),
(17, 'desborde',          'resuelta', 'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 2 DAY),  DATE_SUB(NOW(), INTERVAL 1 DAY),  2,  NULL, 8,    4),
(18, 'desborde',          'resuelta', 'alta',  NULL,                                            DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_SUB(NOW(), INTERVAL 2 DAY),  6,  NULL, 8,    1);

-- Historial: alta de cada incidencia + cierre de las resueltas
INSERT INTO `incidencia_seguimiento` (`id_incidencia`, `id_usuario`, `accion`, `estado_nuevo`, `comentario`, `creado_en`)
SELECT `id_incidencia`, `reportado_por`, 'creada', 'abierta', 'Incidencia reportada', `fecha_apertura` FROM `incidencias`;

INSERT INTO `incidencia_seguimiento` (`id_incidencia`, `id_usuario`, `accion`, `estado_anterior`, `estado_nuevo`, `comentario`, `creado_en`)
SELECT `id_incidencia`, `id_cuadrilla`, 'asignada', 'abierta', 'en_curso', 'La cuadrilla tomó la incidencia', DATE_ADD(`fecha_apertura`, INTERVAL 1 HOUR)
FROM `incidencias` WHERE `id_cuadrilla` IS NOT NULL;

INSERT INTO `incidencia_seguimiento` (`id_incidencia`, `id_usuario`, `accion`, `estado_anterior`, `estado_nuevo`, `comentario`, `creado_en`)
SELECT `id_incidencia`, `id_cuadrilla`, 'estado', 'en_curso', 'resuelta', 'Contenedor vaciado y zona limpia', `fecha_resolucion`
FROM `incidencias` WHERE `estado` = 'resuelta';

-- Recolecciones de las últimas dos semanas
INSERT INTO `recolecciones` (`id_contenedor`, `id_camion`, `id_cuadrilla`, `kg_recolectados`, `nivel_previo`, `recolectado_en`) VALUES
(1,  1, 4, 310.00, 90,  DATE_SUB(NOW(), INTERVAL 13 DAY)),
(2,  2, 4, 420.00, 100, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(5,  1, 4, 280.00, 85,  DATE_SUB(NOW(), INTERVAL 10 DAY)),
(11, 3, 4, 350.00, 95,  DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2,  2, 8, 400.00, 100, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(8,  3, 8, 260.00, 80,  DATE_SUB(NOW(), INTERVAL 6 DAY)),
(9,  1, 4, 390.00, 100, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(6,  3, 8, 410.00, 100, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2,  2, 4, 380.00, 95,  DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1,  1, 4, 250.00, 75,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8,  3, 8, 230.00, 70,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(10, 1, 8, 270.00, 80,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(12, 3, 8, 300.00, 85,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4,  2, 8, 140.00, 90,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5,  1, 4, 290.00, 88,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7,  2, 4, 120.00, 70,  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(13, 2, 8, 110.00, 65,  DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Algunas notificaciones de ejemplo
INSERT INTO `notificaciones` (`id_usuario`, `tipo`, `nivel`, `titulo`, `mensaje`, `enlace`, `leida`, `creada_en`) VALUES
(1, 'incidencia', 'critica',     'Nuevo desborde reportado',   'Bvar. Artigas y 8 de Octubre: Otra vez desbordado, hay olor.', 'incidencias.html?id=8',  0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(2, 'incidencia', 'critica',     'Nuevo desborde reportado',   'Bvar. Artigas y 8 de Octubre: Otra vez desbordado, hay olor.', 'incidencias.html?id=8',  0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1, 'contenedor', 'advertencia', 'Contenedor casi lleno',      'Camino Maldonado y Felipe Cardoso está al 85 %.',               'mapa.html',              0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, 'incidencia', 'critica',     'Incidencia disponible',      'Desborde en Bvar. Artigas y 8 de Octubre sin cuadrilla asignada.', 'incidencias.html?id=8', 0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(8, 'incidencia', 'critica',     'Incidencia disponible',      'Desborde en Bvar. Aparicio Saravia y San Martín sin cuadrilla asignada.', 'incidencias.html?id=11', 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 'incidencia', 'info',        'Tu reporte fue resuelto',    'El desborde en Bvar. Artigas y 8 de Octubre ya fue atendido. ¡Gracias por avisar!', 'incidencias.html?id=7', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 'incidencia', 'info',        'Tu reporte fue resuelto',    'El desborde en Rambla y Massini ya fue atendido. ¡Gracias por avisar!', 'incidencias.html?id=15', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'aviso',      'info',        'Sin recolección el jueves',  'Por feriado nacional, no habrá recolección de residuos el jueves.', 'panel-vecino.html', 0, DATE_SUB(NOW(), INTERVAL 3 DAY));
