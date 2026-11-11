<?php

require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Contenedor.php';

class IncidenciaController
{
    private Incidencia $modelo;
    private Contenedor $modeloContenedor;

    public function __construct(PDO $conexion)
    {
        $this->modelo = new Incidencia($conexion);
        $this->modeloContenedor = new Contenedor($conexion);
    }

    public function listarSegunRol(array $usuario): array
    {
        switch ($usuario['tipo_usuario']) {
            case 'administrador':
                return $this->modelo->listarTodas();
            case 'cuadrilla':
                return $this->modelo->listarParaCuadrilla((int) $usuario['id_usuario']);
            case 'vecino':
                return $this->modelo->listarPorVecino((int) $usuario['id_usuario']);
            default:
                return [];
        }
    }

    public function obtener(int $id, array $usuario): ?array
    {
        $incidencia = $this->modelo->buscarPorId($id);
        if (!$incidencia) return null;

        if ($usuario['tipo_usuario'] === 'vecino' && (int) $incidencia['id_vecino'] !== (int) $usuario['id_usuario']) {
            return null;
        }

        return $incidencia;
    }

    public function reportar(int $idVecino, array $datos): array
    {
        if (empty($datos['id_contenedor']) || empty($datos['tipo'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos: id_contenedor y tipo'];
        }

        if (!$this->modeloContenedor->buscarPorId((int) $datos['id_contenedor'])) {
            return ['exito' => false, 'mensaje' => 'El contenedor indicado no existe'];
        }

        $tiposValidos = ['rotura', 'desborde', 'falta_recoleccion', 'otro'];
        if (!in_array($datos['tipo'], $tiposValidos, true)) {
            return ['exito' => false, 'mensaje' => 'Tipo de incidencia inválido'];
        }

        $id = $this->modelo->crear((int) $datos['id_contenedor'], $datos['tipo'], $idVecino);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Reporte enviado, gracias por avisar'];
    }

    public function tomar(int $idIncidencia, array $usuarioAutenticado): array
    {
        $incidencia = $this->modelo->buscarPorId($idIncidencia);

        if (!$incidencia) {
            return ['exito' => false, 'mensaje' => 'Incidencia no encontrada'];
        }

        $this->modelo->asignarCuadrilla($idIncidencia, (int) $usuarioAutenticado['id_usuario']);
        return ['exito' => true, 'mensaje' => 'Incidencia asignada a tu cuadrilla'];
    }

    public function cambiarEstado(int $idIncidencia, string $estado): array
    {
        $estadosValidos = ['abierta', 'en_curso', 'resuelta'];

        if (!in_array($estado, $estadosValidos, true)) {
            return ['exito' => false, 'mensaje' => 'Estado inválido'];
        }

        if (!$this->modelo->buscarPorId($idIncidencia)) {
            return ['exito' => false, 'mensaje' => 'Incidencia no encontrada'];
        }

        $this->modelo->actualizarEstado($idIncidencia, $estado);
        return ['exito' => true, 'mensaje' => 'Estado actualizado'];
    }
}
