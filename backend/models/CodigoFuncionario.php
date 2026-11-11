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
               AND usado = 0
               AND (expires_at IS NULL OR expires_at > NOW())
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
    public function generar(string $tipoUsuario, int $generadoPor): array
    {
        $prefijos = [
            'administrador' => 'ADM',
            'operario'      => 'OPR',
            'cuadrilla'     => 'CUA',
        ];
        $prefijo   = $prefijos[$tipoUsuario] ?? 'FUN';
        $codigo    = 'SIGERU-' . $prefijo . '-' . strtoupper(bin2hex(random_bytes(8)));
        $expiresAt = (new DateTime())->modify('+48 hours')->format('Y-m-d H:i:s');

        $consulta = $this->conexion->prepare(
            'INSERT INTO codigos_funcionario (codigo, tipo_usuario, usado, generado_por, expires_at)
             VALUES (:codigo, :tipo_usuario, 0, :generado_por, :expires_at)'
        );
        $consulta->execute([
            'codigo'       => $codigo,
            'tipo_usuario' => $tipoUsuario,
            'generado_por' => $generadoPor,
            'expires_at'   => $expiresAt,
        ]);

        return [
            'id_codigo'    => (int) $this->conexion->lastInsertId(),
            'codigo'       => $codigo,
            'tipo_usuario' => $tipoUsuario,
            'expires_at'   => $expiresAt,
        ];
    }

    public function listar(): array
    {
        return $this->conexion->query(
            'SELECT id_codigo, codigo, tipo_usuario, usado, id_usuario, expires_at
             FROM codigos_funcionario ORDER BY id_codigo DESC'
        )->fetchAll();
    }

    public function esValido(string $codigo, string $tipoUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_codigo FROM codigos_funcionario
             WHERE codigo = :codigo AND tipo_usuario = :tipo_usuario
               AND usado = 0
               AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $consulta->execute(['codigo' => $codigo, 'tipo_usuario' => $tipoUsuario]);
        return (bool) $consulta->fetch();
    }

}
