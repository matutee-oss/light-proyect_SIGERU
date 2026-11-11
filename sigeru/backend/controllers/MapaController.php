<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Camion.php';
require_once __DIR__ . '/../models/CentroAcopio.php';
require_once __DIR__ . '/../models/Zona.php';

/**
 * Datos para el mapa interactivo: contenedores, incidencias, camiones y centros.
 */
class MapaController
{
    private Contenedor $modeloContenedor;
    private Incidencia $modeloIncidencia;
    private Camion $modeloCamion;
    private CentroAcopio $modeloCentro;
    private Zona $modeloZona;

    public function __construct(
        PDO $conexion,
        ?Contenedor $modeloContenedor = null,
        ?Incidencia $modeloIncidencia = null,
        ?Camion $modeloCamion = null,
        ?CentroAcopio $modeloCentro = null,
        ?Zona $modeloZona = null
    ) {
        $this->modeloContenedor = $modeloContenedor ?? new Contenedor($conexion);
        $this->modeloIncidencia = $modeloIncidencia ?? new Incidencia($conexion);
        $this->modeloCamion = $modeloCamion ?? new Camion($conexion);
        $this->modeloCentro = $modeloCentro ?? new CentroAcopio($conexion);
        $this->modeloZona = $modeloZona ?? new Zona($conexion);
    }

    public function obtener(array $usuario, array $parametros = []): array
    {
        $filtrosContenedor = [];
        $filtrosIncidencia = [];

        if (!empty($parametros['id_zona']) && ctype_digit((string) $parametros['id_zona'])) {
            $filtrosContenedor['id_zona'] = (int) $parametros['id_zona'];
            $filtrosIncidencia['id_zona'] = (int) $parametros['id_zona'];
        }
        if (!empty($parametros['estado_contenedor']) && Validacion::enLista($parametros['estado_contenedor'], Validacion::ESTADOS_CONTENEDOR)) {
            $filtrosContenedor['estado'] = $parametros['estado_contenedor'];
        }
        if (isset($parametros['nivel_minimo']) && Validacion::porcentaje($parametros['nivel_minimo'])) {
            $filtrosContenedor['nivel_minimo'] = (int) $parametros['nivel_minimo'];
        }
        if (!empty($parametros['tipo']) && Validacion::enLista($parametros['tipo'], Validacion::TIPOS_INCIDENCIA)) {
            $filtrosIncidencia['tipo'] = $parametros['tipo'];
        }
        if (!empty($parametros['prioridad']) && Validacion::enLista($parametros['prioridad'], Validacion::PRIORIDADES)) {
            $filtrosIncidencia['prioridad'] = $parametros['prioridad'];
        }
        if (!empty($parametros['estado_incidencia']) && Validacion::enLista($parametros['estado_incidencia'], Validacion::ESTADOS_INCIDENCIA)) {
            $filtrosIncidencia['estado'] = $parametros['estado_incidencia'];
        }

        $incidencias = $this->modeloIncidencia->listarParaMapa($filtrosIncidencia);
        $esPersonal = in_array($usuario['tipo_usuario'], ['administrador', 'cuadrilla'], true);

        if (!$esPersonal) {
            // Un vecino ve dónde hay problemas, pero no quién los reportó
            $incidencias = array_map([self::class, 'anonimizar'], $incidencias);
        }

        $centros = array_values(array_filter(
            $this->modeloCentro->listarTodos(),
            fn($c) => $c['latitud'] !== null && $c['longitud'] !== null
        ));

        return [
            'contenedores' => $this->modeloContenedor->listarConFiltros($filtrosContenedor),
            'incidencias'  => $incidencias,
            'camiones'     => $esPersonal ? $this->modeloCamion->listarGeolocalizados() : [],
            'centros'      => $centros,
            'zonas'        => $this->modeloZona->listarTodas(),
        ];
    }

    public static function anonimizar(array $incidencia): array
    {
        foreach (['vecino_nombre', 'vecino_apellido', 'reporta_nombre', 'reporta_apellido', 'reportado_por', 'id_vecino', 'descripcion'] as $campo) {
            unset($incidencia[$campo]);
        }
        return $incidencia;
    }
}
