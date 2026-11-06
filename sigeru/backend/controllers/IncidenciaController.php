<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

/**
 * Registro y seguimiento de incidencias.
 *
 * Flujo: abierta → en_curso (una cuadrilla la toma o el admin la asigna) → resuelta.
 * Cada paso queda en incidencia_seguimiento y dispara las notificaciones que correspondan.
 */
class IncidenciaController
{
    private Incidencia $modelo;
    private Contenedor $modeloContenedor;
    private Usuario $modeloUsuario;
    private ServicioAlertas $alertas;

    public function __construct(
        PDO $conexion,
        ?Incidencia $modelo = null,
        ?Contenedor $modeloContenedor = null,
        ?Usuario $modeloUsuario = null,
        ?ServicioAlertas $alertas = null
    ) {
        $this->modelo = $modelo ?? new Incidencia($conexion);
        $this->modeloContenedor = $modeloContenedor ?? new Contenedor($conexion);
        $this->modeloUsuario = $modeloUsuario ?? new Usuario($conexion);
        $this->alertas = $alertas ?? new ServicioAlertas(new Notificacion($conexion));
    }

    // ------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------

    public function listarSegunRol(array $usuario, array $filtros = []): array
    {
        $filtros = $this->limpiarFiltros($filtros);

        switch ($usuario['tipo_usuario']) {
            case 'administrador':
                return $this->modelo->listar($filtros);
            case 'cuadrilla':
                $lista = $this->modelo->listarParaCuadrilla((int) $usuario['id_usuario']);
                return $this->filtrarEnMemoria($lista, $filtros);
            case 'vecino':
                return $this->filtrarEnMemoria($this->modelo->listarPorVecino((int) $usuario['id_usuario']), $filtros);
            default:
                return [];
        }
    }

    public function obtener(int $id, array $usuario): ?array
    {
        $incidencia = $this->modelo->buscarPorId($id);
        if (!$incidencia || !$this->puedeVer($incidencia, $usuario)) {
            return null;
        }

        $incidencia['seguimiento'] = $this->modelo->listarSeguimiento($id);
        return $incidencia;
    }

    // ------------------------------------------------------------
    // Registro
    // ------------------------------------------------------------

    public function reportar(array $usuario, array $datos): array
    {
        $rol = $usuario['tipo_usuario'];
        if (!in_array($rol, ['vecino', 'administrador', 'cuadrilla'], true)) {
            return $this->error('Tu rol no puede reportar incidencias', 403);
        }

        if (empty($datos['id_contenedor']) || empty($datos['tipo'])) {
            return $this->error('Faltan datos: id_contenedor y tipo');
        }

        if (!Validacion::enLista($datos['tipo'], Validacion::TIPOS_INCIDENCIA)) {
            return $this->error('Tipo de incidencia inválido');
        }

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        if (mb_strlen($descripcion) > 500) {
            return $this->error('La descripción no puede superar los 500 caracteres');
        }
        if ($datos['tipo'] === 'otro' && $descripcion === '') {
            return $this->error('Para el tipo "otro" contanos qué pasa en la descripción');
        }

        $contenedor = $this->modeloContenedor->buscarPorId((int) $datos['id_contenedor']);
        if (!$contenedor) {
            return $this->error('El contenedor indicado no existe', 404);
        }

        // Evita reportes duplicados del mismo problema
        $abiertas = $this->modelo->listar([
            'id_contenedor' => (int) $datos['id_contenedor'],
            'tipo'          => $datos['tipo'],
            'pendientes'    => true,
        ], 1);
        if (!empty($abiertas)) {
            return $this->error(
                'Ya hay un reporte pendiente de ese problema para este contenedor (#' . $abiertas[0]['id_incidencia'] . '). ¡Gracias igual!',
                409
            );
        }

        // Prioridad: el personal la elige; para vecinos se calcula sola
        if ($rol !== 'vecino' && !empty($datos['prioridad'])) {
            if (!Validacion::enLista($datos['prioridad'], Validacion::PRIORIDADES)) {
                return $this->error('Prioridad inválida');
            }
            $prioridad = $datos['prioridad'];
        } else {
            $prioridad = self::prioridadSugerida($datos['tipo'], (int) $contenedor['nivel_llenado']);
        }

        $idUsuario = (int) $usuario['id_usuario'];
        $idIncidencia = $this->modelo->crear(
            (int) $datos['id_contenedor'],
            $datos['tipo'],
            $rol === 'vecino' ? $idUsuario : null,
            $idUsuario,
            $descripcion,
            $prioridad
        );

        $this->modelo->registrarSeguimiento($idIncidencia, $idUsuario, 'creada', null, 'abierta', $descripcion ?: 'Incidencia reportada');

        // El estado del contenedor refleja el problema reportado
        if ($datos['tipo'] === 'desborde' && $contenedor['estado'] === 'funcional') {
            $this->modeloContenedor->marcarEstado((int) $contenedor['id_contenedor'], 'desbordado');
        } elseif ($datos['tipo'] === 'rotura' && $contenedor['estado'] !== 'roto') {
            $this->modeloContenedor->marcarEstado((int) $contenedor['id_contenedor'], 'roto');
        }

        $nueva = $this->modelo->buscarPorId($idIncidencia);
        if ($nueva) {
            $this->alertas->incidenciaCreada($nueva, $idUsuario);
        }

        return ['exito' => true, 'id' => $idIncidencia, 'mensaje' => 'Reporte enviado, gracias por avisar'];
    }

    /** Desbordes urgentes; si el contenedor ya está casi lleno, cualquier reporte sube de prioridad. */
    public static function prioridadSugerida(string $tipo, int $nivelLlenado): string
    {
        if ($tipo === 'desborde' || $nivelLlenado >= 90) {
            return 'alta';
        }
        if ($tipo === 'otro') {
            return 'baja';
        }
        return 'media';
    }

    // ------------------------------------------------------------
    // Seguimiento
    // ------------------------------------------------------------

    public function tomar(int $idIncidencia, array $usuario): array
    {
        if ($usuario['tipo_usuario'] !== 'cuadrilla') {
            return $this->error('Solo una cuadrilla puede tomar incidencias', 403);
        }

        $incidencia = $this->modelo->buscarPorId($idIncidencia);
        if (!$incidencia) {
            return $this->error('Incidencia no encontrada', 404);
        }
        if ($incidencia['estado'] === 'resuelta') {
            return $this->error('La incidencia ya está resuelta');
        }

        $idCuadrilla = (int) $usuario['id_usuario'];
        if (!empty($incidencia['id_cuadrilla'])) {
            return (int) $incidencia['id_cuadrilla'] === $idCuadrilla
                ? $this->error('Ya tenés asignada esta incidencia')
                : $this->error('Otra cuadrilla ya tomó esta incidencia', 409);
        }

        $this->modelo->asignarCuadrilla($idIncidencia, $idCuadrilla);
        $this->modelo->registrarSeguimiento(
            $idIncidencia, $idCuadrilla, 'asignada', $incidencia['estado'], 'en_curso',
            'La cuadrilla tomó la incidencia'
        );
        $this->alertas->incidenciaAsignada($incidencia, $usuario['nombre'] . ' ' . $usuario['apellido'], $idCuadrilla);

        return ['exito' => true, 'mensaje' => 'Incidencia asignada a tu cuadrilla'];
    }

    public function asignar(int $idIncidencia, int $idCuadrilla, array $usuario): array
    {
        if ($usuario['tipo_usuario'] !== 'administrador') {
            return $this->error('Solo un administrador puede asignar incidencias', 403);
        }

        $incidencia = $this->modelo->buscarPorId($idIncidencia);
        if (!$incidencia) {
            return $this->error('Incidencia no encontrada', 404);
        }
        if ($incidencia['estado'] === 'resuelta') {
            return $this->error('La incidencia ya está resuelta');
        }
        if (!$this->modeloUsuario->esCuadrillaActiva($idCuadrilla)) {
            return $this->error('La cuadrilla indicada no existe o no está activa');
        }
        if ((int) $incidencia['id_cuadrilla'] === $idCuadrilla) {
            return $this->error('La incidencia ya está asignada a esa cuadrilla');
        }

        $this->modelo->asignarCuadrilla($idIncidencia, $idCuadrilla);
        $cuadrilla = $this->modeloUsuario->buscarPorId($idCuadrilla);
        $nombre = $cuadrilla ? $cuadrilla['nombre'] . ' ' . $cuadrilla['apellido'] : 'la cuadrilla';

        $this->modelo->registrarSeguimiento(
            $idIncidencia, (int) $usuario['id_usuario'], 'asignada', $incidencia['estado'],
            $incidencia['estado'] === 'abierta' ? 'en_curso' : $incidencia['estado'],
            "Asignada a {$nombre}"
        );
        $this->alertas->incidenciaAsignadaPorAdmin($incidencia, $idCuadrilla);
        $this->alertas->incidenciaAsignada($incidencia, $nombre, (int) $usuario['id_usuario']);

        return ['exito' => true, 'mensaje' => "Incidencia asignada a {$nombre}"];
    }

    public function cambiarEstado(int $idIncidencia, string $estado, array $usuario, ?string $comentario = null): array
    {
        if (!Validacion::enLista($estado, Validacion::ESTADOS_INCIDENCIA)) {
            return $this->error('Estado inválido');
        }

        $incidencia = $this->modelo->buscarPorId($idIncidencia);
        if (!$incidencia) {
            return $this->error('Incidencia no encontrada', 404);
        }

        $rol = $usuario['tipo_usuario'];
        $idUsuario = (int) $usuario['id_usuario'];

        if ($rol === 'cuadrilla' && (int) $incidencia['id_cuadrilla'] !== $idUsuario) {
            return $this->error('Primero tenés que tomar la incidencia para cambiar su estado', 403);
        }
        if (!in_array($rol, ['administrador', 'cuadrilla'], true)) {
            return $this->error('No tenés permiso para cambiar el estado', 403);
        }
        if ($incidencia['estado'] === $estado) {
            return $this->error('La incidencia ya está en ese estado');
        }
        if ($estado === 'en_curso' && empty($incidencia['id_cuadrilla'])) {
            return $this->error('Para pasarla a "en curso" primero hay que asignarle una cuadrilla');
        }

        $this->modelo->actualizarEstado($idIncidencia, $estado);
        $this->modelo->registrarSeguimiento($idIncidencia, $idUsuario, 'estado', $incidencia['estado'], $estado, $comentario);

        // Al resolver, el contenedor vuelve a quedar en condiciones
        if ($estado === 'resuelta') {
            $idContenedor = (int) $incidencia['id_contenedor'];
            if ($incidencia['tipo'] === 'rotura' && $incidencia['contenedor_estado'] === 'roto') {
                $this->modeloContenedor->marcarEstado($idContenedor, 'funcional');
            } elseif (in_array($incidencia['tipo'], ['desborde', 'falta_recoleccion'], true)) {
                $this->modeloContenedor->marcarVaciado($idContenedor);
            }
        }

        $this->alertas->incidenciaCambioEstado($incidencia, $estado, $idUsuario);

        return ['exito' => true, 'mensaje' => 'Estado actualizado'];
    }

    public function comentar(int $idIncidencia, array $usuario, string $comentario): array
    {
        $comentario = trim($comentario);
        if ($comentario === '') {
            return $this->error('El comentario no puede estar vacío');
        }
        if (mb_strlen($comentario) > 500) {
            return $this->error('El comentario no puede superar los 500 caracteres');
        }

        $incidencia = $this->modelo->buscarPorId($idIncidencia);
        if (!$incidencia || !$this->puedeVer($incidencia, $usuario)) {
            return $this->error('Incidencia no encontrada', 404);
        }

        $this->modelo->registrarSeguimiento($idIncidencia, (int) $usuario['id_usuario'], 'comentario', null, null, $comentario);
        return ['exito' => true, 'mensaje' => 'Comentario agregado'];
    }

    public function cambiarPrioridad(int $idIncidencia, string $prioridad, array $usuario): array
    {
        if ($usuario['tipo_usuario'] !== 'administrador') {
            return $this->error('Solo un administrador puede cambiar la prioridad', 403);
        }
        if (!Validacion::enLista($prioridad, Validacion::PRIORIDADES)) {
            return $this->error('Prioridad inválida');
        }
        $incidencia = $this->modelo->buscarPorId($idIncidencia);
        if (!$incidencia) {
            return $this->error('Incidencia no encontrada', 404);
        }

        $this->modelo->actualizarPrioridad($idIncidencia, $prioridad);
        $this->modelo->registrarSeguimiento(
            $idIncidencia, (int) $usuario['id_usuario'], 'comentario', null, null,
            "Prioridad cambiada de {$incidencia['prioridad']} a {$prioridad}"
        );
        return ['exito' => true, 'mensaje' => 'Prioridad actualizada'];
    }

    public function eliminar(int $idIncidencia, array $usuario): array
    {
        if ($usuario['tipo_usuario'] !== 'administrador') {
            return $this->error('Solo un administrador puede eliminar incidencias', 403);
        }
        if (!$this->modelo->eliminar($idIncidencia)) {
            return $this->error('Incidencia no encontrada', 404);
        }
        return ['exito' => true, 'mensaje' => 'Incidencia eliminada'];
    }

    // ------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------

    private function puedeVer(array $incidencia, array $usuario): bool
    {
        switch ($usuario['tipo_usuario']) {
            case 'administrador':
            case 'cuadrilla':
                return true;
            case 'vecino':
                $id = (int) $usuario['id_usuario'];
                return (int) $incidencia['reportado_por'] === $id || (int) $incidencia['id_vecino'] === $id;
            default:
                return false;
        }
    }

    private function limpiarFiltros(array $filtros): array
    {
        $limpios = [];
        if (!empty($filtros['estado']) && Validacion::enLista($filtros['estado'], Validacion::ESTADOS_INCIDENCIA)) {
            $limpios['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['tipo']) && Validacion::enLista($filtros['tipo'], Validacion::TIPOS_INCIDENCIA)) {
            $limpios['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['prioridad']) && Validacion::enLista($filtros['prioridad'], Validacion::PRIORIDADES)) {
            $limpios['prioridad'] = $filtros['prioridad'];
        }
        foreach (['id_zona', 'id_contenedor', 'id_cuadrilla'] as $clave) {
            if (!empty($filtros[$clave]) && ctype_digit((string) $filtros[$clave])) {
                $limpios[$clave] = (int) $filtros[$clave];
            }
        }
        foreach (['desde', 'hasta'] as $clave) {
            if (!empty($filtros[$clave]) && Validacion::fecha($filtros[$clave])) {
                $limpios[$clave] = $filtros[$clave];
            }
        }
        if (!empty($filtros['texto'])) {
            $limpios['texto'] = mb_substr(trim($filtros['texto']), 0, 100);
        }
        return $limpios;
    }

    private function filtrarEnMemoria(array $lista, array $filtros): array
    {
        return array_values(array_filter($lista, function ($i) use ($filtros) {
            foreach (['estado', 'tipo', 'prioridad', 'id_zona', 'id_contenedor'] as $clave) {
                if (isset($filtros[$clave]) && (string) $i[$clave] !== (string) $filtros[$clave]) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function error(string $mensaje, int $codigo = 400): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'codigo' => $codigo];
    }
}
