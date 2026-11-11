<?php
/**
 * Informes simples (solo administradores).
 *
 * GET /informes.php?tipo=incidencias&situacion=abiertas|cerradas|todas&desde=AAAA-MM-DD&hasta=AAAA-MM-DD&id_zona=&tipo_incidencia=
 * GET /informes.php?tipo=recolecciones&desde=...&hasta=...&id_zona=
 * Agregar &formato=csv para descargar el informe.
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/InformeController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(405, ['mensaje' => 'Método no permitido, use GET']);
}

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);
requerirRol($usuario, ['administrador']);

$controlador = new InformeController($conexion);
$tipoInforme = $_GET['tipo'] ?? 'incidencias';

$parametros = $_GET;
// "tipo" ya se usa para elegir el informe; el tipo de incidencia viaja como tipo_incidencia
$parametros['tipo'] = $_GET['tipo_incidencia'] ?? '';

if ($tipoInforme === 'incidencias') {
    $resultado = $controlador->incidencias($parametros);
    $columnas = InformeController::COLUMNAS_INCIDENCIAS;
} elseif ($tipoInforme === 'recolecciones') {
    $resultado = $controlador->recolecciones($parametros);
    $columnas = InformeController::COLUMNAS_RECOLECCIONES;
} else {
    responderJson(400, ['mensaje' => 'Tipo de informe inválido (incidencias o recolecciones)']);
}

if (!$resultado['exito']) {
    responderResultado($resultado);
}

if (($_GET['formato'] ?? '') === 'csv') {
    $nombre = "sigeru_{$tipoInforme}_{$resultado['periodo']['desde']}_{$resultado['periodo']['hasta']}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    echo InformeController::generarCsv($resultado['filas'], $columnas);
    exit;
}

responderJson(200, $resultado);
