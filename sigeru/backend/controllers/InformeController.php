<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Recoleccion.php';

/**
 * Informes simples: listado de incidencias abiertas/cerradas y de recolecciones
 * en un período, con totales. Se pueden descargar en CSV (se abre con Excel).
 */
class InformeController
{
    private Incidencia $modeloIncidencia;
    private Recoleccion $modeloRecoleccion;

    public const COLUMNAS_INCIDENCIAS = [
        'id_incidencia'        => 'N°',
        'fecha_apertura'       => 'Fecha de apertura',
        'tipo'                 => 'Tipo',
        'prioridad'            => 'Prioridad',
        'estado'               => 'Estado',
        'contenedor_ubicacion' => 'Contenedor',
        'nombre_zona'          => 'Zona',
        'cuadrilla'            => 'Cuadrilla',
        'fecha_resolucion'     => 'Fecha de resolución',
        'horas_resolucion'     => 'Horas hasta resolver',
        'descripcion'          => 'Descripción',
    ];

    public const COLUMNAS_RECOLECCIONES = [
        'id_recoleccion'       => 'N°',
        'recolectado_en'       => 'Fecha',
        'contenedor_ubicacion' => 'Contenedor',
        'nombre_zona'          => 'Zona',
        'camion_matricula'     => 'Camión',
        'cuadrilla'            => 'Cuadrilla',
        'nivel_previo'         => 'Nivel previo (%)',
        'kg_recolectados'      => 'Kg recolectados',
        'observaciones'        => 'Observaciones',
    ];

    public function __construct(PDO $conexion, ?Incidencia $modeloIncidencia = null, ?Recoleccion $modeloRecoleccion = null)
    {
        $this->modeloIncidencia = $modeloIncidencia ?? new Incidencia($conexion);
        $this->modeloRecoleccion = $modeloRecoleccion ?? new Recoleccion($conexion);
    }

    /**
     * Valida y completa el período. Si no se indica, se usa el mes en curso.
     * Devuelve [filtros, error].
     */
    public static function normalizarPeriodo(array $parametros): array
    {
        $desde = $parametros['desde'] ?? date('Y-m-01');
        $hasta = $parametros['hasta'] ?? date('Y-m-d');

        if (!Validacion::fecha($desde) || !Validacion::fecha($hasta)) {
            return [null, 'Las fechas deben tener el formato AAAA-MM-DD'];
        }
        if ($desde > $hasta) {
            return [null, 'La fecha "desde" no puede ser posterior a "hasta"'];
        }
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > 366) {
            return [null, 'El período máximo de un informe es un año'];
        }

        $filtros = ['desde' => $desde, 'hasta' => $hasta];
        if (!empty($parametros['id_zona']) && ctype_digit((string) $parametros['id_zona'])) {
            $filtros['id_zona'] = (int) $parametros['id_zona'];
        }
        return [$filtros, null];
    }

    public function incidencias(array $parametros): array
    {
        [$filtros, $error] = self::normalizarPeriodo($parametros);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error, 'codigo' => 400];
        }

        // "cerradas" = resueltas; "abiertas" = abierta + en curso
        $situacion = $parametros['situacion'] ?? 'todas';
        if (!in_array($situacion, ['todas', 'abiertas', 'cerradas'], true)) {
            return ['exito' => false, 'mensaje' => 'Situación inválida (todas, abiertas o cerradas)', 'codigo' => 400];
        }
        foreach (['tipo' => Validacion::TIPOS_INCIDENCIA, 'prioridad' => Validacion::PRIORIDADES] as $clave => $permitidos) {
            if (!empty($parametros[$clave])) {
                if (!Validacion::enLista($parametros[$clave], $permitidos)) {
                    return ['exito' => false, 'mensaje' => "Filtro {$clave} inválido", 'codigo' => 400];
                }
                $filtros[$clave] = $parametros[$clave];
            }
        }

        $filtrosLista = $filtros;
        if ($situacion === 'cerradas') {
            $filtrosLista['estado'] = 'resuelta';
        } elseif ($situacion === 'abiertas') {
            $filtrosLista['pendientes'] = true;
        }

        $filas = array_map([$this, 'prepararFilaIncidencia'], $this->modeloIncidencia->listar($filtrosLista, 2000));
        $resumen = $this->modeloIncidencia->resumenPorEstado($filtros);

        return [
            'exito'    => true,
            'periodo'  => ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']],
            'situacion' => $situacion,
            'resumen'  => [
                'total'      => (int) $resumen['total'],
                'abiertas'   => (int) $resumen['abiertas'] + (int) $resumen['en_curso'],
                'sin_asignar' => (int) $resumen['abiertas'],
                'en_curso'   => (int) $resumen['en_curso'],
                'cerradas'   => (int) $resumen['resueltas'],
                'porcentaje_cerradas' => $resumen['total'] > 0 ? (int) round($resumen['resueltas'] / $resumen['total'] * 100) : 0,
                'horas_promedio_resolucion' => $resumen['horas_promedio_resolucion'],
            ],
            'por_tipo' => self::contarPor($filas, 'tipo'),
            'por_zona' => self::contarPor($filas, 'nombre_zona'),
            'filas'    => $filas,
        ];
    }

    public function recolecciones(array $parametros): array
    {
        [$filtros, $error] = self::normalizarPeriodo($parametros);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error, 'codigo' => 400];
        }

        $filas = array_map(function ($r) {
            $r['cuadrilla'] = $r['cuadrilla_nombre'] ? trim($r['cuadrilla_nombre'] . ' ' . $r['cuadrilla_apellido']) : null;
            return $r;
        }, $this->modeloRecoleccion->listar($filtros, 1000));

        $kg = array_sum(array_map(fn($r) => (float) $r['kg_recolectados'], $filas));

        return [
            'exito'   => true,
            'periodo' => ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']],
            'resumen' => [
                'recolecciones'          => count($filas),
                'kg'                     => round($kg, 2),
                'contenedores_atendidos' => count(array_unique(array_column($filas, 'id_contenedor'))),
                'kg_promedio'            => count($filas) ? round($kg / count($filas), 1) : 0,
            ],
            'por_zona' => self::contarPor($filas, 'nombre_zona'),
            'filas'    => $filas,
        ];
    }

    /** Agrega cuadrilla legible y horas que tardó en resolverse. */
    public function prepararFilaIncidencia(array $i): array
    {
        $i['cuadrilla'] = $i['cuadrilla_nombre'] ? trim($i['cuadrilla_nombre'] . ' ' . $i['cuadrilla_apellido']) : null;
        $i['horas_resolucion'] = ($i['fecha_resolucion'] && $i['fecha_apertura'])
            ? round((strtotime($i['fecha_resolucion']) - strtotime($i['fecha_apertura'])) / 3600, 1)
            : null;
        return $i;
    }

    /** Cuenta filas agrupando por una columna: [['clave' => 'desborde', 'total' => 3], ...] */
    public static function contarPor(array $filas, string $columna): array
    {
        $conteo = [];
        foreach ($filas as $fila) {
            $clave = $fila[$columna] ?? 'Sin dato';
            $clave = $clave === null || $clave === '' ? 'Sin dato' : $clave;
            $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
        }
        arsort($conteo);
        $resultado = [];
        foreach ($conteo as $clave => $total) {
            $resultado[] = ['clave' => (string) $clave, 'total' => $total];
        }
        return $resultado;
    }

    /**
     * Genera CSV con separador ";" y BOM UTF-8 para que Excel en español
     * muestre bien columnas y tildes.
     */
    public static function generarCsv(array $filas, array $columnas): string
    {
        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, "\xEF\xBB\xBF");
        fputcsv($flujo, array_values($columnas), ';', '"', '\\');
        foreach ($filas as $fila) {
            $linea = [];
            foreach (array_keys($columnas) as $clave) {
                $valor = $fila[$clave] ?? '';
                // Evita que Excel interprete el texto como fórmula (inyección CSV)
                if (is_string($valor) && $valor !== '' && in_array($valor[0], ['=', '+', '-', '@'], true)) {
                    $valor = "'" . $valor;
                }
                $linea[] = $valor;
            }
            fputcsv($flujo, $linea, ';', '"', '\\');
        }
        rewind($flujo);
        $csv = stream_get_contents($flujo);
        fclose($flujo);
        return $csv;
    }
}
