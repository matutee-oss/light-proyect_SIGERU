<?php

require_once __DIR__ . '/../models/Notificacion.php';

/**
 * Sistema de notificaciones y alertas.
 *
 * Centraliza QUIÉN se entera de QUÉ evento. Los controladores solo llaman
 * a estos métodos y no tienen que saber a qué usuarios avisar.
 */
class ServicioAlertas
{
    public const UMBRAL_ADVERTENCIA = 80;
    public const UMBRAL_CRITICO = 100;

    private Notificacion $notificaciones;

    public function __construct(Notificacion $notificaciones)
    {
        $this->notificaciones = $notificaciones;
    }

    /**
     * Devuelve el umbral que se cruzó hacia arriba entre dos valores, o null.
     * Ej.: 70 → 85 cruza 80; 85 → 90 no cruza nada (ya se avisó antes).
     */
    public static function umbralCruzado(float $anterior, float $nuevo): ?int
    {
        if ($anterior < self::UMBRAL_CRITICO && $nuevo >= self::UMBRAL_CRITICO) {
            return self::UMBRAL_CRITICO;
        }
        if ($anterior < self::UMBRAL_ADVERTENCIA && $nuevo >= self::UMBRAL_ADVERTENCIA) {
            return self::UMBRAL_ADVERTENCIA;
        }
        return null;
    }

    public static function etiquetaTipo(string $tipo): string
    {
        $etiquetas = [
            'rotura' => 'Rotura',
            'desborde' => 'Desborde',
            'falta_recoleccion' => 'Falta de recolección',
            'otro' => 'Incidencia',
        ];
        return $etiquetas[$tipo] ?? 'Incidencia';
    }

    public function incidenciaCreada(array $incidencia, int $idReportador): void
    {
        $enlace = 'incidencias.html?id=' . $incidencia['id_incidencia'];
        $tipo = self::etiquetaTipo($incidencia['tipo']);
        $ubicacion = $incidencia['contenedor_ubicacion'] ?? 'contenedor';
        $detalle = !empty($incidencia['descripcion']) ? ': ' . $incidencia['descripcion'] : '';
        $nivel = ($incidencia['prioridad'] ?? 'media') === 'alta' ? 'critica' : 'advertencia';

        $this->notificaciones->crearParaRoles(
            ['administrador'], 'incidencia', $nivel,
            "Nueva incidencia: {$tipo}",
            "{$ubicacion}{$detalle}",
            $enlace, $idReportador
        );

        // Las cuadrillas se enteran de lo urgente para poder tomarlo
        if ($incidencia['tipo'] === 'desborde' || ($incidencia['prioridad'] ?? '') === 'alta') {
            $this->notificaciones->crearParaRoles(
                ['cuadrilla'], 'incidencia', 'critica',
                'Incidencia disponible',
                "{$tipo} en {$ubicacion} sin cuadrilla asignada.",
                $enlace, $idReportador
            );
        }
    }

    public function incidenciaAsignada(array $incidencia, string $nombreCuadrilla, int $idActor): void
    {
        if (!empty($incidencia['reportado_por']) && (int) $incidencia['reportado_por'] !== $idActor) {
            $this->notificaciones->crear(
                (int) $incidencia['reportado_por'], 'incidencia', 'info',
                'Tu reporte está siendo atendido',
                "La cuadrilla de {$nombreCuadrilla} tomó el reporte de {$incidencia['contenedor_ubicacion']}.",
                'incidencias.html?id=' . $incidencia['id_incidencia']
            );
        }
    }

    public function incidenciaCambioEstado(array $incidencia, string $estadoNuevo, int $idActor): void
    {
        $enlace = 'incidencias.html?id=' . $incidencia['id_incidencia'];
        $ubicacion = $incidencia['contenedor_ubicacion'] ?? 'el contenedor';
        $tipo = mb_strtolower(self::etiquetaTipo($incidencia['tipo']));

        // Al que reportó
        if (!empty($incidencia['reportado_por']) && (int) $incidencia['reportado_por'] !== $idActor) {
            $titulos = [
                'resuelta' => 'Tu reporte fue resuelto',
                'en_curso' => 'Tu reporte está en curso',
                'abierta'  => 'Tu reporte se reabrió',
            ];
            $mensajes = [
                'resuelta' => "El reporte de {$tipo} en {$ubicacion} ya fue atendido. ¡Gracias por avisar!",
                'en_curso' => "Una cuadrilla está trabajando en el reporte de {$ubicacion}.",
                'abierta'  => "El reporte de {$ubicacion} volvió a quedar abierto.",
            ];
            $this->notificaciones->crear(
                (int) $incidencia['reportado_por'], 'incidencia', 'info',
                $titulos[$estadoNuevo], $mensajes[$estadoNuevo], $enlace
            );
        }

        // A la cuadrilla asignada, si el cambio lo hizo otra persona (ej. el admin)
        if (!empty($incidencia['id_cuadrilla']) && (int) $incidencia['id_cuadrilla'] !== $idActor) {
            $this->notificaciones->crear(
                (int) $incidencia['id_cuadrilla'], 'incidencia', $estadoNuevo === 'abierta' ? 'advertencia' : 'info',
                'Cambio en una incidencia asignada',
                "La incidencia de {$ubicacion} pasó a estado «" . str_replace('_', ' ', $estadoNuevo) . '».',
                $enlace
            );
        }
    }

    public function incidenciaAsignadaPorAdmin(array $incidencia, int $idCuadrilla): void
    {
        $this->notificaciones->crear(
            $idCuadrilla, 'incidencia', ($incidencia['prioridad'] ?? '') === 'alta' ? 'critica' : 'advertencia',
            'Te asignaron una incidencia',
            self::etiquetaTipo($incidencia['tipo']) . ' en ' . ($incidencia['contenedor_ubicacion'] ?? 'un contenedor') . '.',
            'incidencias.html?id=' . $incidencia['id_incidencia']
        );
    }

    /** Avisa cuando un contenedor cruza el 80 % o llega al 100 %. Devuelve el umbral cruzado. */
    public function nivelContenedor(array $contenedor, float $nivelAnterior, float $nivelNuevo): ?int
    {
        $umbral = self::umbralCruzado($nivelAnterior, $nivelNuevo);
        if ($umbral === null) {
            return null;
        }

        $critico = $umbral === self::UMBRAL_CRITICO;
        $this->notificaciones->crearParaRoles(
            ['administrador', 'cuadrilla'], 'contenedor', $critico ? 'critica' : 'advertencia',
            $critico ? 'Contenedor lleno' : 'Contenedor casi lleno',
            "{$contenedor['ubicacion']} está al " . (int) $nivelNuevo . ' %.' . ($critico ? ' Requiere recolección urgente.' : ''),
            'mapa.html?contenedor=' . $contenedor['id_contenedor']
        );
        return $umbral;
    }

    /** Avisa cuando un centro de acopio cruza el 80 % o el 100 % de su capacidad. */
    public function capacidadCentro(array $centro, float $porcentajeAnterior, float $porcentajeNuevo): ?int
    {
        $umbral = self::umbralCruzado($porcentajeAnterior, $porcentajeNuevo);
        if ($umbral === null) {
            return null;
        }

        $critico = $umbral === self::UMBRAL_CRITICO;
        $titulo = $critico ? 'Centro de acopio sin capacidad' : 'Centro con alta ocupación';
        $mensaje = "{$centro['nombre']} llegó al " . (int) $porcentajeNuevo . ' % de su capacidad mensual.';

        $this->notificaciones->crearParaRoles(['administrador'], 'capacidad', $critico ? 'critica' : 'advertencia', $titulo, $mensaje, 'centros-acopio.html');
        $this->notificaciones->crearParaOperariosDeCentro((int) $centro['id_centro'], 'capacidad', $critico ? 'critica' : 'advertencia', $titulo, $mensaje, 'panel-operario.html');
        return $umbral;
    }

    public function avisoPublicado(string $titulo, string $mensaje, ?int $idZona): int
    {
        return $this->notificaciones->crearParaVecinos($idZona, $titulo, $mensaje, 'panel-vecino.html');
    }

    public function usuarioPendiente(array $usuario): void
    {
        $this->notificaciones->crearParaRoles(
            ['administrador'], 'sistema', 'info',
            'Usuario pendiente de aprobación',
            "{$usuario['nombre']} {$usuario['apellido']} se registró como {$usuario['tipo_usuario']}.",
            'panel-admin.html'
        );
    }
}
