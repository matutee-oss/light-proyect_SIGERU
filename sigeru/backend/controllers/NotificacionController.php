<?php

require_once __DIR__ . '/../models/Notificacion.php';

class NotificacionController
{
    private Notificacion $modelo;

    public function __construct(PDO $conexion, ?Notificacion $modelo = null)
    {
        $this->modelo = $modelo ?? new Notificacion($conexion);
    }

    /** Lista + contador de no leídas (lo usa la campanita del menú). */
    public function listar(int $idUsuario, bool $soloNoLeidas = false, int $limite = 30): array
    {
        return [
            'no_leidas'      => $this->modelo->contarNoLeidas($idUsuario),
            'notificaciones' => $this->modelo->listarPorUsuario($idUsuario, $soloNoLeidas, $limite),
        ];
    }

    /** Solo las que llegaron después de cierto id (para mostrar el aviso emergente). */
    public function nuevas(int $idUsuario, int $despuesDeId): array
    {
        return [
            'no_leidas' => $this->modelo->contarNoLeidas($idUsuario),
            'nuevas'    => $despuesDeId > 0 ? $this->modelo->listarNuevas($idUsuario, $despuesDeId) : [],
        ];
    }

    public function marcarLeida(int $idNotificacion, int $idUsuario): array
    {
        if ($idNotificacion <= 0) {
            return ['exito' => false, 'mensaje' => 'Debe indicar la notificación', 'codigo' => 400];
        }
        if (!$this->modelo->marcarLeida($idNotificacion, $idUsuario)) {
            return ['exito' => false, 'mensaje' => 'Notificación no encontrada', 'codigo' => 404];
        }
        return ['exito' => true, 'mensaje' => 'Notificación marcada como leída'];
    }

    public function marcarTodas(int $idUsuario): array
    {
        $cantidad = $this->modelo->marcarTodasLeidas($idUsuario);
        return ['exito' => true, 'actualizadas' => $cantidad, 'mensaje' => 'Todas las notificaciones quedaron leídas'];
    }

    public function eliminar(int $idNotificacion, int $idUsuario): array
    {
        if (!$this->modelo->eliminar($idNotificacion, $idUsuario)) {
            return ['exito' => false, 'mensaje' => 'Notificación no encontrada', 'codigo' => 404];
        }
        return ['exito' => true, 'mensaje' => 'Notificación eliminada'];
    }
}
