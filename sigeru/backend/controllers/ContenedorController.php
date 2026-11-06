<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

class ContenedorController
{
    private Contenedor $modelo;
    private ServicioAlertas $alertas;

    public function __construct(PDO $conexion, ?Contenedor $modelo = null, ?ServicioAlertas $alertas = null)
    {
        $this->modelo = $modelo ?? new Contenedor($conexion);
        $this->alertas = $alertas ?? new ServicioAlertas(new Notificacion($conexion));
    }

    public function listar(array $filtros = []): array
    {
        return $filtros ? $this->modelo->listarConFiltros($filtros) : $this->modelo->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        return $this->modelo->buscarPorId($id);
    }

    private function validar(array $datos): ?string
    {
        if (Validacion::faltante($datos, ['ubicacion', 'tipo_residuo']) !== null) {
            return 'Faltan datos obligatorios (ubicacion, tipo_residuo)';
        }
        if (isset($datos['estado']) && !Validacion::enLista($datos['estado'], Validacion::ESTADOS_CONTENEDOR)) {
            return 'Estado de contenedor inválido';
        }
        if (!Validacion::coordenadas($datos['latitud'] ?? null, $datos['longitud'] ?? null)) {
            return 'Coordenadas inválidas: indicá latitud y longitud, o dejá ambas vacías';
        }
        if (isset($datos['nivel_llenado']) && $datos['nivel_llenado'] !== '' && !Validacion::porcentaje($datos['nivel_llenado'])) {
            return 'El nivel de llenado debe estar entre 0 y 100';
        }
        if (isset($datos['capacidad_litros']) && $datos['capacidad_litros'] !== '' && !Validacion::numeroPositivo($datos['capacidad_litros'])) {
            return 'La capacidad debe ser mayor a 0';
        }
        if (isset($datos['id_zona']) && $datos['id_zona'] !== '' && $datos['id_zona'] !== null
            && !(ctype_digit((string) $datos['id_zona']) && (int) $datos['id_zona'] >= 1 && (int) $datos['id_zona'] <= 8)) {
            return 'Zona inválida';
        }
        return null;
    }

    public function crear(array $datos): array
    {
        if ($error = $this->validar($datos)) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $id = $this->modelo->crear($datos);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Contenedor creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->modelo->buscarPorId($id);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Contenedor no encontrado', 'codigo' => 404];
        }

        $combinados = array_merge($actual, $datos);
        if ($error = $this->validar($combinados)) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $this->modelo->actualizar($id, $datos, $actual);

        if (isset($datos['nivel_llenado']) && $datos['nivel_llenado'] !== '') {
            $this->alertas->nivelContenedor($actual, (float) $actual['nivel_llenado'], (float) $datos['nivel_llenado']);
        }

        return ['exito' => true, 'mensaje' => 'Contenedor actualizado'];
    }

    public function eliminar(int $id): array
    {
        $actual = $this->modelo->buscarPorId($id);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Contenedor no encontrado', 'codigo' => 404];
        }
        if ((int) ($actual['incidencias_pendientes'] ?? 0) > 0) {
            return ['exito' => false, 'mensaje' => 'El contenedor tiene incidencias pendientes. Resolvelas antes de eliminarlo.', 'codigo' => 409];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Contenedor eliminado'];
    }
}
