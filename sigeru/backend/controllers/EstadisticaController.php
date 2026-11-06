<?php

require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Recoleccion.php';
require_once __DIR__ . '/../models/CentroAcopio.php';

/**
 * Panel de control con estadísticas: indicadores del mes, top de contenedores
 * desbordados, evolución diaria, distribución por zona y tipo, rendimiento de
 * cuadrillas y ocupación de los centros de acopio.
 */
class EstadisticaController
{
    private Incidencia $modeloIncidencia;
    private Contenedor $modeloContenedor;
    private Recoleccion $modeloRecoleccion;
    private CentroAcopio $modeloCentro;

    public function __construct(
        PDO $conexion,
        ?Incidencia $modeloIncidencia = null,
        ?Contenedor $modeloContenedor = null,
        ?Recoleccion $modeloRecoleccion = null,
        ?CentroAcopio $modeloCentro = null
    ) {
        $this->modeloIncidencia = $modeloIncidencia ?? new Incidencia($conexion);
        $this->modeloContenedor = $modeloContenedor ?? new Contenedor($conexion);
        $this->modeloRecoleccion = $modeloRecoleccion ?? new Recoleccion($conexion);
        $this->modeloCentro = $modeloCentro ?? new CentroAcopio($conexion);
    }

    public static function mesValido(?string $mes): bool
    {
        return $mes !== null && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes);
    }

    public static function mesAnterior(string $mes): string
    {
        return date('Y-m', strtotime($mes . '-01 -1 month'));
    }

    /** Variación porcentual entre dos valores (null si no hay base para comparar). */
    public static function variacion(float $actual, float $anterior): ?int
    {
        if ($anterior == 0) {
            return null;
        }
        return (int) round(($actual - $anterior) / $anterior * 100);
    }

    public function panel(?string $mes = null): array
    {
        $mes = $mes ?: date('Y-m');
        if (!self::mesValido($mes)) {
            return ['exito' => false, 'mensaje' => 'El mes debe tener el formato AAAA-MM', 'codigo' => 400];
        }
        $anterior = self::mesAnterior($mes);
        $primerDia = $mes . '-01';
        $ultimoDia = date('Y-m-t', strtotime($primerDia));

        $incidenciasMes = $this->modeloIncidencia->resumenPorEstado(['desde' => $primerDia, 'hasta' => $ultimoDia]);
        $incidenciasAnterior = $this->modeloIncidencia->resumenPorEstado([
            'desde' => $anterior . '-01',
            'hasta' => date('Y-m-t', strtotime($anterior . '-01')),
        ]);
        $topDesbordados = $this->modeloIncidencia->topContenedoresDesbordados($mes, 5);
        $desbordesMes = $this->contarDesbordes($mes);
        $desbordesAnterior = $this->contarDesbordes($anterior);
        $recoleccionMes = $this->modeloRecoleccion->resumenMes($mes);
        $recoleccionAnterior = $this->modeloRecoleccion->resumenMes($anterior);

        $centros = [];
        foreach ($this->modeloCentro->listarTodos() as $centro) {
            $ocupacion = $this->modeloCentro->calcularCapacidadUsada((int) $centro['id_centro'], (float) $centro['capacidad']);
            $centros[] = [
                'nombre'        => $centro['nombre'],
                'capacidad'     => (float) $centro['capacidad'],
                'toneladas_30d' => $ocupacion['toneladas_30d'],
                'porcentaje'    => $ocupacion['porcentaje'],
            ];
        }

        return [
            'exito' => true,
            'mes'   => $mes,
            'indicadores' => [
                'incidencias_mes'          => (int) $incidenciasMes['total'],
                'incidencias_variacion'    => self::variacion((float) $incidenciasMes['total'], (float) $incidenciasAnterior['total']),
                'incidencias_pendientes'   => $this->modeloIncidencia->contarAbiertas(),
                'porcentaje_resueltas'     => $incidenciasMes['total'] > 0 ? (int) round($incidenciasMes['resueltas'] / $incidenciasMes['total'] * 100) : 0,
                'horas_promedio_resolucion' => $incidenciasMes['horas_promedio_resolucion'],
                'desbordes_mes'            => $desbordesMes,
                'desbordes_variacion'      => self::variacion($desbordesMes, $desbordesAnterior),
                'recolecciones_mes'        => $recoleccionMes['recolecciones'],
                'kg_mes'                   => $recoleccionMes['kg'],
                'kg_variacion'             => self::variacion($recoleccionMes['kg'], $recoleccionAnterior['kg']),
            ],
            'contenedores'          => $this->modeloContenedor->resumenEstados(),
            'top_desbordados'       => $topDesbordados,
            'evolucion_incidencias' => $this->modeloIncidencia->evolucionDiaria(30),
            'incidencias_por_tipo'  => $this->modeloIncidencia->conteoPorTipo(30),
            'incidencias_por_zona'  => $this->modeloIncidencia->conteoPorZona($mes),
            'kg_por_dia'            => $this->modeloRecoleccion->kilosPorDia(14),
            'kg_por_zona'           => $this->modeloRecoleccion->kilosPorZona($mes),
            'rendimiento_cuadrillas' => $this->modeloIncidencia->rendimientoCuadrillas($mes),
            'centros'               => $centros,
        ];
    }

    private function contarDesbordes(string $mes): int
    {
        return (int) array_sum(array_column($this->modeloIncidencia->topContenedoresDesbordados($mes, 20), 'desbordes'));
    }
}
