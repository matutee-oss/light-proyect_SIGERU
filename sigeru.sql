
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `administradores` (
  `id_usuario` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `administradores` (`id_usuario`) VALUES
(1),
(2),
(7);

CREATE TABLE `avisos` (
  `id_aviso` int NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_zona` int DEFAULT NULL,
  `publicado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `avisos` (`id_aviso`, `titulo`, `mensaje`, `id_zona`, `publicado_en`, `activo`) VALUES
(1, 'Sin recolección el jueves', 'Por feriado nacional, no habrá recolección de residuos el jueves. Se retomará el viernes en el horario habitual.', NULL, '2026-07-18 00:28:44', 1),
(2, 'Nuevo contenedor en Buceo', 'Se instaló un nuevo contenedor de reciclables en Rambla y Massini.', 1, '2026-07-18 00:28:44', 1),
(3, '18 de Julio, Independencia.', 'Por el día presente, los trabajadores mantendran el dia sabatico debido a la independencia del país.', NULL, '2026-07-19 17:03:44', 1);

CREATE TABLE `camiones` (
  `id_camion` int NOT NULL,
  `matricula` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `estado` enum('disponible','en_ruta','mantenimiento','fuera_servicio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `capacidad_kg` decimal(10,2) NOT NULL DEFAULT '6000.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `camiones` (`id_camion`, `matricula`, `latitud`, `longitud`, `estado`, `capacidad_kg`) VALUES
(1, 'SBC 1234', -34.8969000, -56.1425000, 'disponible', 6000.00),
(2, 'SBD 5678', -34.8988000, -56.1613000, 'en_ruta', 6000.00);

CREATE TABLE `centros_acopio` (
  `id_centro` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacidad` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `centros_acopio` (`id_centro`, `nombre`, `ubicacion`, `capacidad`) VALUES
(1, 'Centro de Acopio Buceo', 'Rambla Rep. de México, Montevideo', 500.00),
(2, 'Vertedero Felipe Cardoso', 'Camino Felipe Cardoso, Montevideo', 5000.00);

CREATE TABLE `codigos_funcionario` (
  `id_codigo` int NOT NULL,
  `codigo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_usuario` enum('administrador','cuadrilla','operario') COLLATE utf8mb4_unicode_ci NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT '0',
  `id_usuario` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `generado_por` int DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `codigos_funcionario` (`id_codigo`, `codigo`, `tipo_usuario`, `usado`, `id_usuario`, `creado_en`, `generado_por`, `expires_at`) VALUES
(1, 'SIGERU-ADM-7F2K', 'administrador', 1, 2, '2026-07-18 00:27:59', NULL, NULL),
(2, 'SIGERU-CUA-9X3M', 'cuadrilla', 0, NULL, '2026-07-18 00:27:59', NULL, NULL),
(3, 'SIGERU-OPE-4L8N', 'operario', 1, 9, '2026-07-18 00:27:59', NULL, NULL),
(4, 'SIGERU-ADM-0001', 'administrador', 1, 7, '2026-11-10 22:42:32', NULL, NULL),
(5, 'SIGERU-ADM-131BF6C721607F6F', 'administrador', 0, NULL, '2026-11-11 01:26:15', 7, '2026-11-13 02:26:15'),
(6, 'SIGERU-ADM-F82AC0E5FE97D0AB', 'administrador', 0, NULL, '2026-11-11 01:27:31', 7, '2026-11-13 02:27:31'),
(7, 'SIGERU-CUA-8147C205180239A8', 'cuadrilla', 1, 10, '2026-11-11 01:35:17', 7, '2026-11-13 02:35:17');

CREATE TABLE `contenedores` (
  `id_contenedor` int NOT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('funcional','roto','desbordado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'funcional',
  `tipo_residuo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_zona` int DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `contenedores` (`id_contenedor`, `ubicacion`, `estado`, `tipo_residuo`, `id_zona`, `latitud`, `longitud`) VALUES
(1, 'Av. Italia y Rivera', 'funcional', 'Residuos comunes', 2, -34.8969000, -56.1425000),
(2, 'Bvar. Artigas y 8 de Octubre', 'desbordado', 'Reciclables', 1, -34.8988000, -56.1613000),
(3, 'Camino Maldonado km 15', 'roto', 'Residuos comunes', 4, -34.8562000, -56.0801000),
(4, 'Rambla y Massini', 'funcional', 'Reciclables', 1, -34.9022000, -56.1362000),
(5, '18 de Julio y Yaguarón', 'funcional', 'Residuos comunes', 2, -34.9046000, -56.1912000);

CREATE TABLE `cuadrillas` (
  `id_usuario` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cuadrillas` (`id_usuario`) VALUES
(10);

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

CREATE TABLE `ingresos_centro` (
  `id_ingreso` int NOT NULL,
  `id_centro` int NOT NULL,
  `id_camion` int DEFAULT NULL,
  `toneladas` decimal(10,2) NOT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `operarios` (
  `id_usuario` int NOT NULL,
  `id_centro` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `operarios` (`id_usuario`, `id_centro`) VALUES
(9, 1);

CREATE TABLE `recursos` (
  `id_recurso` int NOT NULL,
  `id_centro` int NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `recursos` (`id_recurso`, `id_centro`, `tipo`, `cantidad`) VALUES
(1, 1, 'Contenedores de repuesto', 10),
(2, 2, 'Palas cargadoras', 2);

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

CREATE TABLE `ruta_contenedor` (
  `id_ruta` int NOT NULL,
  `id_contenedor` int NOT NULL,
  `orden` int NOT NULL,
  `atendido` tinyint(1) NOT NULL DEFAULT '0',
  `atendido_en` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sesiones` (
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario` int NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sesiones` (`token`, `id_usuario`, `creado_en`, `expira_en`) VALUES
('39c0404452403eaaae2c3d77a2bbfd49a970ffe1d0be4cd268f7d9ecf07bb8d3', 2, '2026-07-18 00:31:35', '2026-07-18 09:31:35'),
('ab8570d323dbfc560e113e2076dd044fa4cf57dd85ac4c11cd32baff95ba9bcb', 7, '2026-11-11 01:22:11', '2026-11-11 10:22:11'),
('b13d80a51e3b892c40ebf41faf27db320decf045c72d372d17c4116c5c1b71c2', 2, '2026-07-19 16:40:03', '2026-07-20 01:40:03'),
('b6a41e38632b84f005caeacbcd1274db577e907349870f31944895105eed7620', 7, '2026-11-10 22:59:00', '2026-11-11 07:59:00');

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

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `ci`, `telefono`, `email`, `password_hash`, `tipo_usuario`, `estado`, `foto`, `creado_en`) VALUES
(1, 'Admin', 'SiGeRU', '11111111', '099111111', 'admin@sigeru.uy', '$2y$10$92Ck2t8NlYQ6q7nQ0m0Zp.Y0h1u3G5zj1yQe0m2k3n4p5q6r7s8t9', 'administrador', 'activo', NULL, '2026-07-18 00:27:59'),
(2, 'Martin', 'Estevez', '57484312', '092442544', 'martinestevezesi@gmail.com', '$2y$10$azbO6x.7abJypfWJRETrDecVZWpwdEAOF2veuQdhrQM7rXAxFg6DC', 'administrador', 'activo', NULL, '2026-07-18 00:30:01'),
(7, 'Light', 'Software', '22222222', '939484433', 'lightsoftware@gmail.com', '$2y$10$yk8oyBcLevKIWqBODnuoy.XEqaZG6.7CtJeGX2Ql1aIe6aStWX5VO', 'administrador', 'activo', NULL, '2026-11-10 22:56:22'),
(8, 'Vecino', 'Vecino', '66666666', '098765543', 'vecinoproyecto@gmail.com', '$2y$10$19YNCwntQrsOTJ468vLkEOHdy.26QOVF6nKBljiLkmB1NBmeBRf.e', 'vecino', 'activo', NULL, '2026-11-11 00:02:00'),
(9, 'Operario', 'Operario', '76767676', '098754132', 'operarioproyecto@gmail.com', '$2y$10$ltWbJ2GyX8q..giVSxSz0u0cuUbiSmBr6yb0ZKWdJ9mFUuLOAklBS', 'operario', 'activo', NULL, '2026-11-11 01:30:14'),
(10, 'Cuadrilla', 'Cuadrilla', '45643214', '92334544', 'cuadrillaproyecto@gmail.com', '$2y$10$w55ATb5kph1281GyMTbovOrbjioOvtH6Bo3p80NsNX0R75wRlE9fu', 'cuadrilla', 'activo', NULL, '2026-11-11 01:35:30');

CREATE TABLE `vecinos` (
  `id_usuario` int NOT NULL,
  `id_zona` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `vecinos` (`id_usuario`, `id_zona`) VALUES
(8, NULL);

CREATE TABLE `zonas` (
  `id_zona` int NOT NULL,
  `nombre_zona` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `zonas` (`id_zona`, `nombre_zona`) VALUES
(1, 'Municipio A'),
(2, 'Municipio B'),
(3, 'Municipio C'),
(4, 'Municipio CH'),
(5, 'Municipio D'),
(6, 'Municipio E'),
(7, 'Municipio F'),
(8, 'Municipio G');

ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id_usuario`);

ALTER TABLE `avisos`
  ADD PRIMARY KEY (`id_aviso`),
  ADD KEY `fk_aviso_zona` (`id_zona`);

ALTER TABLE `camiones`
  ADD PRIMARY KEY (`id_camion`),
  ADD UNIQUE KEY `matricula` (`matricula`);

ALTER TABLE `centros_acopio`
  ADD PRIMARY KEY (`id_centro`);

ALTER TABLE `codigos_funcionario`
  ADD PRIMARY KEY (`id_codigo`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_codigo_usuario` (`id_usuario`);

ALTER TABLE `contenedores`
  ADD PRIMARY KEY (`id_contenedor`),
  ADD KEY `fk_contenedor_zona` (`id_zona`);

ALTER TABLE `cuadrillas`
  ADD PRIMARY KEY (`id_usuario`);

ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id_incidencia`),
  ADD KEY `fk_incidencia_contenedor` (`id_contenedor`),
  ADD KEY `fk_incidencia_vecino` (`id_vecino`),
  ADD KEY `fk_incidencia_cuadrilla` (`id_cuadrilla`);

ALTER TABLE `ingresos_centro`
  ADD PRIMARY KEY (`id_ingreso`),
  ADD KEY `fk_ingreso_centro` (`id_centro`),
  ADD KEY `fk_ingreso_camion` (`id_camion`);

ALTER TABLE `operarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_operario_centro` (`id_centro`);

ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`,`id_centro`),
  ADD KEY `fk_recurso_centro` (`id_centro`);

ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id_ruta`),
  ADD KEY `fk_ruta_cuadrilla` (`id_cuadrilla`),
  ADD KEY `fk_ruta_camion` (`id_camion`);

ALTER TABLE `ruta_contenedor`
  ADD PRIMARY KEY (`id_ruta`,`id_contenedor`),
  ADD KEY `fk_rc_contenedor` (`id_contenedor`);

ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`token`),
  ADD KEY `fk_sesion_usuario` (`id_usuario`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `vecinos`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_vecino_zona` (`id_zona`);

ALTER TABLE `zonas`
  ADD PRIMARY KEY (`id_zona`);

ALTER TABLE `avisos`
  MODIFY `id_aviso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `camiones`
  MODIFY `id_camion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `centros_acopio`
  MODIFY `id_centro` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `codigos_funcionario`
  MODIFY `id_codigo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `contenedores`
  MODIFY `id_contenedor` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `incidencias`
  MODIFY `id_incidencia` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `ingresos_centro`
  MODIFY `id_ingreso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `recursos`
  MODIFY `id_recurso` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `rutas`
  MODIFY `id_ruta` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `zonas`
  MODIFY `id_zona` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `administradores`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `avisos`
  ADD CONSTRAINT `fk_aviso_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);

ALTER TABLE `codigos_funcionario`
  ADD CONSTRAINT `fk_codigo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

ALTER TABLE `contenedores`
  ADD CONSTRAINT `fk_contenedor_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);

ALTER TABLE `cuadrillas`
  ADD CONSTRAINT `fk_cuadrilla_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedores` (`id_contenedor`),
  ADD CONSTRAINT `fk_incidencia_cuadrilla` FOREIGN KEY (`id_cuadrilla`) REFERENCES `cuadrillas` (`id_usuario`),
  ADD CONSTRAINT `fk_incidencia_vecino` FOREIGN KEY (`id_vecino`) REFERENCES `vecinos` (`id_usuario`);

ALTER TABLE `ingresos_centro`
  ADD CONSTRAINT `fk_ingreso_camion` FOREIGN KEY (`id_camion`) REFERENCES `camiones` (`id_camion`),
  ADD CONSTRAINT `fk_ingreso_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`);

ALTER TABLE `operarios`
  ADD CONSTRAINT `fk_operario_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`),
  ADD CONSTRAINT `fk_operario_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `recursos`
  ADD CONSTRAINT `fk_recurso_centro` FOREIGN KEY (`id_centro`) REFERENCES `centros_acopio` (`id_centro`) ON DELETE CASCADE;

ALTER TABLE `rutas`
  ADD CONSTRAINT `fk_ruta_camion` FOREIGN KEY (`id_camion`) REFERENCES `camiones` (`id_camion`),
  ADD CONSTRAINT `fk_ruta_cuadrilla` FOREIGN KEY (`id_cuadrilla`) REFERENCES `cuadrillas` (`id_usuario`);

ALTER TABLE `ruta_contenedor`
  ADD CONSTRAINT `fk_rc_contenedor` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedores` (`id_contenedor`),
  ADD CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE;

ALTER TABLE `sesiones`
  ADD CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `vecinos`
  ADD CONSTRAINT `fk_vecino_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vecino_zona` FOREIGN KEY (`id_zona`) REFERENCES `zonas` (`id_zona`);
COMMIT;


