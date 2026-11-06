<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Recoleccion.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Camion.php';
require_once __DIR__ . '/../models/Ruta.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

/**
 * API de recolección.
 *
 * Registrar una recolección hace, en una sola transacción:
 *  1. Guarda el registro (contenedor, camión, cuadrilla, kilos, nivel previo).
 *  2. Deja el contenedor vacío (nivel 0) y, si estaba desbordado, funcional.
 *  3. Resuelve los desbordes / faltas de recolección pendientes de ese contenedor.
 *  4. Si vino desde una ruta, marca la parada como atendida.
 */
class RecoleccionController
{
    public const KG_MAXIMO_POR_RECOLECCION = 3000;

    private PDO $conexion;
    private Recoleccion $modelo;
    private Contenedor $modeloContenedor;
    private Incidencia $modeloIncidencia;
    private Camion $modeloCamion;
    private Ruta $modeloRuta;
    private ServicioAlertas $alertas;

    public function __construct(
        PDO $conexion,
        ?Recoleccion $modelo = null,
        ?Contenedor $modeloContenedor = null,
        ?Incidencia $modeloIncidencia = null,
        ?Camion $modeloCamion = null,
        ?Ruta $modeloRuta = null,
        ?ServicioAlertas $alertas = null
    ) {
        $this->conexion = $conexion;
        $this->modelo = $modelo ?? new Recoleccion($conexion);
        $this->modeloContenedor = $modeloContenedor ?? new Contenedor($conexion);
        $this->modeloIncidencia = $modeloIncidencia ?? new Incidencia($conexion);
        $this->modeloCamion = $modeloCamion ?? new Camion($conexion);
        $this->modeloRuta = $modeloRuta ?? new Ruta($conexion);
        $this->alertas = $alertas ?? new ServicioAlertas(new Notificacion($conexion));
    }

    public function listar(array $usuario, array $filtros = []): array
    {
        $limpios = [];
        foreach (['id_contenedor', 'id_camion', 'id_zona', 'id_cuadrilla'] as $clave) {
            if (!empty($filtros[$clave]) && ctype_digit((string) $filtros[$clave])) {
                $limpios[$clave] = (int) $filtros[$clave];
            }
        }
        foreach (['desde', 'hasta'] as $clave) {
            if (!empty($filtros[$clave]) && Validacion::fecha($filtros[$clave])) {
                $limpios[$clave] = $filtros[$clave];
            }
        }
        // Cada cuadrilla ve solo su propio historial
        if ($usuario['tipo_usuario'] === 'cuadrilla') {
            $limpios['id_cuadrilla'] = (int) $usuario['id_usuario'];
        }
        return $this->modelo->listar($limpios);
    }

    public function obtener(int $id): ?array
    {
        return $this->modelo->buscarPorId($id);
    }

    public function registrar(array $usuario, array $datos): array
    {
        $rol = $usuario['tipo_usuario'];
        if (!in_array($rol, ['cuadrilla', 'administrador'], true)) {
            return $this->error('Solo cuadrillas y administradores registran recolecciones', 403);
        }

        if (empty($datos['id_contenedor'])) {
            return $this->error('Falta el dato obligatorio: id_contenedor');
        }

        $kg = $datos['kg_recolectados'] ?? null;
        if ($kg !== null && $kg !== '') {
            if (!Validacion::numeroPositivo($kg, true) || (float) $kg > self::KG_MAXIMO_POR_RECOLECCION) {
                return $this->error('Los kilos deben ser un número entre 0 y ' . self::KG_MAXIMO_POR_RECOLECCION);
            }
            $kg = round((float) $kg, 2);
        } else {
            $kg = null;
        }

        $contenedor = $this->modeloContenedor->buscarPorId((int) $datos['id_contenedor']);
        if (!$contenedor) {
            return $this->error('El contenedor indicado no existe', 404);
        }

        $idCamion = Validacion::enteroONulo($datos['id_camion'] ?? null);
        if ($idCamion !== null && !$this->modeloCamion->buscarPorId($idCamion)) {
            return $this->error('El camión indicado no existe', 404);
        }

        $idRuta = Validacion::enteroONulo($datos['id_ruta'] ?? null);
        $idCuadrilla = $rol === 'cuadrilla'
            ? (int) $usuario['id_usuario']
            : Validacion::enteroONulo($datos['id_cuadrilla'] ?? null);

        if ($idRuta !== null) {
            if ($rol === 'cuadrilla' && !$this->modeloRuta->pertenece($idRuta, (int) $usuario['id_usuario'])) {
                return $this->error('Esa ruta no está asignada a tu cuadrilla', 403);
            }
            if ($this->modelo->existeEnRuta($idRuta, (int) $contenedor['id_contenedor'])) {
                return $this->error('Esa parada de la ruta ya tiene su recolección registrada', 409);
            }
            if ($idCamion === null) {
                $ruta = $this->modeloRuta->buscarPorId($idRuta);
                $idCamion = $ruta && $ruta['id_camion'] ? (int) $ruta['id_camion'] : null;
            }
        }

        $this->conexion->beginTransaction();
        try {
            $idRecoleccion = $this->modelo->crear([
                'id_contenedor'   => (int) $contenedor['id_contenedor'],
                'id_camion'       => $idCamion,
                'id_cuadrilla'    => $idCuadrilla,
                'id_ruta'         => $idRuta,
                'kg_recolectados' => $kg,
                'nivel_previo'    => (int) $contenedor['nivel_llenado'],
                'observaciones'   => $datos['observaciones'] ?? null,
            ]);

            $this->modeloContenedor->marcarVaciado((int) $contenedor['id_contenedor']);

            if ($idRuta !== null) {
                $this->modeloRuta->marcarParada($idRuta, (int) $contenedor['id_contenedor'], true);
            }

            $resueltas = $this->modeloIncidencia->resolverPorRecoleccion((int) $contenedor['id_contenedor']);
            foreach ($resueltas as $incidencia) {
                $this->modeloIncidencia->registrarSeguimiento(
                    (int) $incidencia['id_incidencia'], (int) $usuario['id_usuario'], 'estado',
                    $incidencia['estado'], 'resuelta', 'Resuelta automáticamente al registrar la recolección #' . $idRecoleccion
                );
            }

            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }

        // Las notificaciones van después del commit: si fallan no deshacen la recolección
        foreach ($resueltas as $incidencia) {
            $this->alertas->incidenciaCambioEstado($incidencia, 'resuelta', (int) $usuario['id_usuario']);
        }

        $mensaje = 'Recolección registrada';
        if (count($resueltas) > 0) {
            $mensaje .= ' · ' . count($resueltas) . (count($resueltas) === 1 ? ' incidencia resuelta' : ' incidencias resueltas');
        }

        return [
            'exito'              => true,
            'id'                 => $idRecoleccion,
            'incidencias_resueltas' => count($resueltas),
            'mensaje'            => $mensaje,
        ];
    }

    /** Actualiza el nivel de llenado (lectura de sensor o reporte de la cuadrilla) y dispara alertas. */
    public function actualizarNivel(int $idContenedor, $nivel, array $usuario): array
    {
        if (!in_array($usuario['tipo_usuario'], ['cuadrilla', 'administrador'], true)) {
            return $this->error('No tenés permiso para actualizar niveles', 403);
        }
        if (!Validacion::porcentaje($nivel)) {
            return $this->error('El nivel debe ser un porcentaje entre 0 y 100');
        }

        $contenedor = $this->modeloContenedor->buscarPorId($idContenedor);
        if (!$contenedor) {
            return $this->error('El contenedor indicado no existe', 404);
        }

        $nivel = (int) round((float) $nivel);
        $this->modeloContenedor->actualizarNivel($idContenedor, $nivel);
        $umbral = $this->alertas->nivelContenedor($contenedor, (float) $contenedor['nivel_llenado'], (float) $nivel);

        return [
            'exito'   => true,
            'mensaje' => 'Nivel actualizado al ' . $nivel . ' %' . ($umbral ? ' · se envió una alerta' : ''),
            'alerta'  => $umbral !== null,
        ];
    }

    public function eliminar(int $id, array $usuario): array
    {
        if ($usuario['tipo_usuario'] !== 'administrador') {
            return $this->error('Solo un administrador puede eliminar registros de recolección', 403);
        }
        if (!$this->modelo->eliminar($id)) {
            return $this->error('Recolección no encontrada', 404);
        }
        return ['exito' => true, 'mensaje' => 'Registro de recolección eliminado'];
    }

    public function resumen(): array
    {
        return [
            'por_dia'    => $this->modelo->kilosPorDia(14),
            'mes_actual' => $this->modelo->resumenMes(date('Y-m')),
        ];
    }

    private function error(string $mensaje, int $codigo = 400): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'codigo' => $codigo];
    }
}
