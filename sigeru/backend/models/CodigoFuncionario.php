<?php

class CodigoFuncionario
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function buscarParaConsumir(string $codigo, string $tipoUsuario): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM codigos_funcionario
             WHERE codigo = :codigo AND tipo_usuario = :tipo_usuario
             FOR UPDATE'
        );
        $consulta->execute([
            'codigo'       => $codigo,
            'tipo_usuario' => $tipoUsuario,
        ]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function marcarUsado(int $idCodigo, int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE codigos_funcionario
             SET usado = TRUE, id_usuario = :id_usuario
             WHERE id_codigo = :id_codigo'
        );
        return $consulta->execute([
            'id_usuario' => $idUsuario,
            'id_codigo'  => $idCodigo,
        ]);
    }
}
