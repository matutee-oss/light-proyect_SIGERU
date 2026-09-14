-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-07-2026 a las 03:24:01
-- Versión del servidor: 8.0.46
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sigeru`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

CREATE TABLE `administradores` (
  `id_usuario` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `administradores`
--

INSERT INTO `administradores` (`id_usuario`) VALUES
(1),
(2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avisos`
--

CREATE TABLE `avisos` (
  `id_aviso` int NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_zona` int DEFAULT NULL,
  `publicado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `avisos`
--

INSERT INTO `avisos` (`id_aviso`, `titulo`, `mensaje`, `id_zona`, `publicado_en`, `activo`) VALUES
(1, 'Sin recolección el jueves', 'Por feriado nacional, no habrá recolección de residuos el jueves. Se retomará el viernes en el horario habitual.', NULL, '2026-07-18 00:28:44', 1),
(2, 'Nuevo contenedor en Buceo', 'Se instaló un nuevo contenedor de reciclables en Rambla y Massini.', 1, '2026-07-18 00:28:44', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camiones`
--

CREATE TABLE `camiones` (
  `id_camion` int NOT NULL,
  `matricula` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `estado` enum('disponible','en_ruta','mantenimiento','fuera_servicio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `capacidad_kg` decimal(10,2) NOT NULL DEFAULT '6000.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `camiones`
--

INSERT INTO `camiones` (`id_camion`, `matricula`, `latitud`, `longitud`, `estado`, `capacidad_kg`) VALUES
(1, 'SBC 1234', -34.8969000, -56.1425000, 'disponible', 6000.00),
(2, 'SBD 5678', -34.8988000, -56.1613000, 'en_ruta', 6000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `centros_acopio`
--

CREATE TABLE `centros_acopio` (
  `id_centro` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacidad` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `centros_acopio`
--

INSERT INTO `centros_acopio` (`id_centro`, `nombre`, `ubicacion`, `capacidad`) VALUES
(1, 'Centro de Acopio Buceo', 'Rambla Rep. de México, Montevideo', 500.00),
(2, 'Vertedero Felipe Cardoso', 'Camino Felipe Cardoso, Montevideo', 5000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_funcionario`
--

CREATE TABLE `codigos_funcionario` (
  `id_codigo` int NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_usuario` enum('administrador','cuadrilla','operario') COLLATE utf8mb4_unicode_ci NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT '0',
  `id_usuario` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `codigos_funcionario`
--

INSERT INTO `codigos_funcionario` (`id_codigo`, `codigo`, `tipo_usuario`, `usado`, `id_usuario`, `creado_en`) VALUES
(1, 'SIGERU-ADM-7F2K', 'administrador', 1, 2, '2026-07-18 00:27:59'),
(2, 'SIGERU-CUA-9X3M', 'cuadrilla', 0, NULL, '2026-07-18 00:27:59'),
(3, 'SIGERU-OPE-4L8N', 'operario', 0, NULL, '2026-07-18 00:27:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contenedores`
--

CREATE TABLE `contenedores` (
  `id_contenedor` int NOT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('funcional','roto','desbordado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'funcional',
  `tipo_residuo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_zona` int DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `contenedores`
--

INSERT INTO `contenedores` (`id_contenedor`, `ubicacion`, `estado`, `tipo_residuo`, `id_zona`, `latitud`, `longitud`) VALUES
(1, 'Av. Italia y Rivera', 'funcional', 'Residuos comunes', 2, -34.8969000, -56.1425000),
(2, 'Bvar. Artigas y 8 de Octubre', 'desbordado', 'Reciclables', 1, -34.8988000, -56.1613000),
(3, 'Camino Maldonado km 15', 'roto', 'Residuos comunes', 4, -34.8562000, -56.0801000),
(4, 'Rambla y Massini', 'funcional', 'Reciclables', 1, -34.9022000, -56.1362000),
(5, '18 de Julio y Yaguarón', 'funcional', 'Residuos comunes', 2, -34.9046000, -56.1912000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuadrillas`
--

CREATE TABLE `cuadrillas` (
  `id_usuario` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id_incidencia` int NOT NULL,
  `tipo` enum('rotura','desborde','falta_recoleccion','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('abierta','en_curso','resuelta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `fecha_apertura` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  `id_contenedor` int NOT NULL,
  `id_vecino` int DEFAULT NULL,
  `id_cuadrilla` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos_centro`
--

CREATE TABLE `ingresos_centro` (
  `id_ingreso` int NOT NULL,
  `id_centro` int NOT NULL,
  `id_camion` int DEFAULT NULL,
  `toneladas` decimal(10,2) NOT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ingresos_centro`
--

INSERT INTO `ingresos_centro` (`id_ingreso`, `id_centro`, `id_camion`, `toneladas`, `observaciones`, `registrado_en`) VALUES
(1, 1, 1, 2.40, NULL, '2026-07-12 00:28:44'),
(2, 1, 2, 1.80, NULL, '2026-07-13 00:28:44'),
(3, 1, 1, 2.70, NULL, '2026-07-14 00:28:44'),
(4, 1, 1, 3.10, NULL, '2026-07-15 00:28:44'),
(5, 1, 2, 2.90, NULL, '2026-07-16 00:28:44'),
(6, 1, 1, 2.20, NULL, '2026-07-17 00:28:44'),
(7, 2, 2, 4.50, NULL, '2026-07-12 00:28:44'),
(8, 2, 1, 3.80, NULL, '2026-07-15 00:28:44'),
(9, 2, 2, 4.10, NULL, '2026-07-17 00:28:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `operarios`
--

CREATE TABLE `operarios` (
  `id_usuario` int NOT NULL,
  `id_centro` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos`
--

CREATE TABLE `recursos` (
  `id_recurso` int NOT NULL,
  `id_centro` int NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recursos`
--

INSERT INTO `recursos` (`id_recurso`, `id_centro`, `tipo`, `cantidad`) VALUES
(1, 1, 'Contenedores de repuesto', 10),
(2, 2, 'Palas cargadoras', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id_ruta` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zona` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cuadrilla` int DEFAULT NULL,
  `id_camion` int DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha` date DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ruta_contenedor`
--

CREATE TABLE `ruta_contenedor` (
  `id_ruta` int NOT NULL,
  `id_contenedor` int NOT NULL,
  `orden` int NOT NULL,
  `atendido` tinyint(1) NOT NULL DEFAULT '0',
  `atendido_en` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones`
--

CREATE TABLE `sesiones` (
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario` int NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sesiones`
--

INSERT INTO `sesiones` (`token`, `id_usuario`, `creado_en`, `expira_en`) VALUES
('39c0404452403eaaae2c3d77a2bbfd49a970ffe1d0be4cd268f7d9ecf07bb8d3', 2, '2026-07-18 00:31:35', '2026-07-18 09:31:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_usuario` enum('administrador','cuadrilla','operario','vecino') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('activo','pendiente','inactivo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `ci`, `telefono`, `email`, `password_hash`, `tipo_usuario`, `estado`, `foto`, `creado_en`) VALUES
(1, 'Admin', 'SiGeRU', '11111111', '099111111', 'admin@sigeru.uy', '$2y$10$92Ck2t8NlYQ6q7nQ0m0Zp.Y0h1u3G5zj1yQe0m2k3n4p5q6r7s8t9', 'administrador', 'activo', NULL, '2026-07-18 00:27:59'),
(2, 'Martin', 'Estevez', '57484312', '092442544', 'martinestevezesi@gmail.com', '$2y$10$Qksnf3H41ZfGNs9nx3X5fO1RkZRnJ/B1vHL6NSZzGyNWze93QwKEC', 'administrador', 'activo', NULL, '2026-07-18 00:30:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vecinos`
--

CREATE TABLE `vecinos` (
  `id_usuario` int NOT NULL,
  `id_zona` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zonas`
--

CREATE TABLE `zonas` (
  `id_zona` int NOT NULL,
  `nombre_zona` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `zonas`
--

INSERT INTO `zonas` (`id_zona`, `nombre_zona`) VALUES
(1, 'Municipio A'),
(2, 'Municipio B'),
(3, 'Municipio C'),
(4, 'Municipio CH'),
(5, 'Municipio D'),
(6, 'Municipio E'),
(7, 'Municipio F'),
(8, 'Municipio G');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `avisos`
--
ALTER TABLE `avisos`
  ADD PRIMARY KEY (`id_aviso`),
  ADD KEY `fk_aviso_zona` (`id_zona`);

--
-- Indices de la tabla `camiones`
--
ALTER TABLE `camiones`
  ADD PRIMARY KEY (`id_camion`),
  ADD UNIQUE KEY `matricula` (`matricula`);

--
-- Indices de la tabla `centros_acopio`
--
ALTER TABLE `centros_acopio`
  ADD PRIMARY KEY (`id_centro`);

--
-- Indices de la tabla `codigos_funcionario`
--
ALTER TABLE `codigos_funcionario`
  ADD PRIMARY KEY (`id_codigo`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_codigo_usuario` (`id_usuario`);

--
-- Indices de la tabla `contenedores`
--
ALTER TABLE `contenedores`
  ADD PRIMARY KEY (`id_contenedor`),
  ADD KEY `fk_contenedor_zona` (`id_zona`);

--
-- Indices de la tabla `cuadrillas`
--
ALTER TABLE `cuadrillas`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id_incidencia`),
  ADD KEY `fk_incidencia_contenedor` (`id_contenedor`),
  ADD KEY `fk_incidencia_vecino` (`id_vecino`),
  ADD KEY `fk_incidencia_cuadrilla` (`id_cuadrilla`);

--
-- Indices de la tabla `ingresos_centro`
--
ALTER TABLE `ingresos_centro`
  ADD PRIMARY KEY (`id_ingreso`),
  ADD KEY `fk_ingreso_centro` (`id_centro`),
  ADD KEY `fk_ingreso_camion` (`id_camion`);

--
-- Indices de la tabla `operarios`
--
ALTER TABLE `operarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_operario_centro` (`id_centro`);

--
-- Indices de la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`,`id_centro`),
  ADD KEY `fk_recurso_centro` (`id_centro`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id_ruta`),
  ADD KEY `fk_ruta_cuadrilla` (`id_cuadrilla`),
  ADD KEY `fk_ruta_camion` (`id_camion`);

--
-- Indices de la tabla `ruta_contenedor`
--
ALTER TABLE `ruta_contenedor`
  ADD PRIMARY KEY (`id_ruta`,`id_contenedor`),
  ADD KEY `fk_rc_contenedor` (`id_contenedor`);

--
-- Indices de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`token`),
  ADD KEY `fk_sesion_usuario` (`id_usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vecinos`
--
ALTER TABLE `vecinos`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_vecino_zona` (`id_zona`);

--
-- Indices de la tabla `zonas`
--
ALTER TABLE `zonas`
  ADD PRIMARY KEY (`id_zona`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `avisos`
--
ALTER TABLE `avisos`
  MODIFY `id_aviso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `camiones`
--
ALTER TABLE `camiones`
  MODIFY `id_camion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `centros_acopio`
--
ALTER TABLE `centros_acopio`
  MODIFY `id_centro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `codigos_funcionario`
--
ALTER TABLE `codigos_funcionario`
  MODIFY `id_codigo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `contenedores`
--
ALTER TABLE `contenedores`
  MODIFY `id_contenedor` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id_incidencia` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ingresos_centro`
--
ALTER TABLE `ingresos_centro`
  MODIFY `id_ingreso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `recursos`
--
ALTER TABLE `recursos`
  MODIFY `id_recurso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id_ruta` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `zonas`
--
ALTER TABLE `zonas`
  MODIFY `id_zona` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `avisos`
--
ALTER TABLE `avisos`
  ADD CONSTRAINT `fk_aviso_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);

--
-- Filtros para la tabla `codigos_funcionario`
--
ALTER TABLE `codigos_funcionario`
  ADD CONSTRAINT `fk_codigo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `contenedores`
--
ALTER TABLE `contenedores`
  ADD CONSTRAINT `fk_contenedor_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);

--
-- Filtros para la tabla `cuadrillas`
--
ALTER TABLE `cuadrillas`
  ADD CONSTRAINT `fk_cuadrilla_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedores` (`id_contenedor`),
  ADD CONSTRAINT `fk_incidencia_cuadrilla` FOREIGN KEY (`id_cuadrilla`) REFERENCES `cuadrillas` (`id_usuario`),
  ADD CONSTRAINT `fk_incidencia_vecino` FOREIGN KEY (`id_vecino`) REFERENCES `vecinos` (`id_usuario`);

--
-- Filtros para la tabla `ingresos_centro`
--
ALTER TABLE `ingresos_centro`
  ADD CONSTRAINT `fk_ingreso_camion` FOREIGN KEY (`id_camion`) REFERENCES `camiones` (`id_camion`),
  ADD CONSTRAINT `fk_ingreso_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`);

--
-- Filtros para la tabla `operarios`
--
ALTER TABLE `operarios`
  ADD CONSTRAINT `fk_operario_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`),
  ADD CONSTRAINT `fk_operario_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD CONSTRAINT `fk_recurso_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`) ON DELETE CASCADE;

--
-- Filtros para la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD CONSTRAINT `fk_ruta_camion` FOREIGN KEY (`id_camion`) REFERENCES `camiones` (`id_camion`),
  ADD CONSTRAINT `fk_ruta_cuadrilla` FOREIGN KEY (`id_cuadrilla`) REFERENCES `cuadrillas` (`id_usuario`);

--
-- Filtros para la tabla `ruta_contenedor`
--
ALTER TABLE `ruta_contenedor`
  ADD CONSTRAINT `fk_rc_contenedor` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedores` (`id_contenedor`),
  ADD CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vecinos`
--
ALTER TABLE `vecinos`
  ADD CONSTRAINT `fk_vecino_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vecino_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- DATOS DE PRUEBA PARA 2da ENTREGA
-- ============================================================

-- Usuarios adicionales de prueba (contraseña para todos: 'test1234')
INSERT INTO `usuarios` (`nombre`, `apellido`, `ci`, `telefono`, `email`, `password_hash`, `tipo_usuario`, `estado`) VALUES
('Ana',    'García',   '22222222', '099222222', 'ana.garcia@sigeru.uy',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vecino',    'activo'),
('Carlos', 'López',    '33333333', '099333333', 'carlos.lopez@sigeru.uy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuadrilla', 'activo'),
('María',  'Rodríguez','44444444', '099444444', 'maria.rod@sigeru.uy',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operario',  'activo'),
('Pedro',  'Pérez',    '55555555', '099555555', 'pedro.perez@sigeru.uy',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vecino',    'pendiente');

-- Insertar en las subtablas correspondientes
INSERT INTO `vecinos` (`id_usuario`) VALUES (3), (6);
INSERT INTO `cuadrillas` (`id_usuario`) VALUES (4);
INSERT INTO `operarios` (`id_usuario`, `id_centro`) VALUES (5, 1);

-- Incidencias de prueba
INSERT INTO `incidencias` (`tipo`, `estado`, `fecha_apertura`, `fecha_resolucion`, `id_contenedor`, `id_vecino`, `id_cuadrilla`) VALUES
('desborde',          'resuelta',  '2026-07-10 08:30:00', '2026-07-11 14:00:00', 2, 3, 4),
('rotura',            'en_curso',  '2026-07-15 10:00:00', NULL,                  3, 3, 4),
('falta_recoleccion', 'abierta',   '2026-07-17 09:00:00', NULL,                  1, 3, NULL),
('otro',              'abierta',   '2026-07-17 12:30:00', NULL,                  4, 3, NULL),
('desborde',          'resuelta',  '2026-07-05 07:00:00', '2026-07-06 11:00:00', 2, 3, 4);
