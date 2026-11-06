<?php

use PHPUnit\Framework\TestCase;

/**
 * Base para los tests de integración contra MySQL.
 *
 * - Crea (una sola vez por ejecución) la base `sigeru_test` a partir de sigeru.sql.
 *   Nunca toca la base real `sigeru`.
 * - Cada test corre dentro de una transacción que se deshace al final,
 *   así los tests no dependen del orden y la base queda limpia.
 * - Si no hay MySQL disponible, los tests se marcan como "saltados" (no fallan).
 */
abstract class BaseDatosTestCase extends TestCase
{
    protected static ?PDO $conexion = null;
    private static bool $baseCreada = false;
    private static ?string $errorConexion = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$conexion !== null || self::$errorConexion !== null) {
            return;
        }

        $host = getenv('DB_TEST_HOST') ?: '127.0.0.1';
        $puerto = getenv('DB_TEST_PORT') ?: '3306';
        $nombre = getenv('DB_TEST_NAME') ?: 'sigeru_test';
        $usuario = getenv('DB_TEST_USER') ?: 'root';
        $clave = getenv('DB_TEST_PASS') !== false ? getenv('DB_TEST_PASS') : 'light';

        if ($nombre === 'sigeru') {
            self::$errorConexion = 'Por seguridad la base de pruebas no puede llamarse "sigeru"';
            return;
        }

        try {
            $servidor = new PDO("mysql:host={$host};port={$puerto};charset=utf8mb4", $usuario, $clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            if (!self::$baseCreada) {
                $sql = file_get_contents(__DIR__ . '/../../../sigeru.sql');
                $sql = str_replace('`sigeru`', "`{$nombre}`", $sql);
                $servidor->exec($sql);
                self::$baseCreada = true;
            }

            self::$conexion = new PDO("mysql:host={$host};port={$puerto};dbname={$nombre};charset=utf8mb4", $usuario, $clave, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$conexion->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            self::$errorConexion = $e->getMessage();
        }
    }

    protected function setUp(): void
    {
        if (self::$conexion === null) {
            $this->markTestSkipped('MySQL de pruebas no disponible: ' . self::$errorConexion);
        }
        self::$conexion->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (self::$conexion !== null && self::$conexion->inTransaction()) {
            self::$conexion->rollBack();
        }
    }

    protected function contar(string $sql, array $parametros = []): int
    {
        $consulta = self::$conexion->prepare($sql);
        $consulta->execute($parametros);
        return (int) $consulta->fetchColumn();
    }

    /** Usuario autenticado de prueba, como lo devuelve requerirUsuario(). */
    protected function usuario(int $id): array
    {
        $consulta = self::$conexion->prepare('SELECT * FROM usuarios WHERE id_usuario = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }
}

/**
 * PDO "de mentira" que acepta transacciones anidadas: los controladores abren su
 * propia transacción y el test ya tiene una abierta para poder deshacer todo.
 */
class PdoConTransaccionAnidada extends PDO
{
    private PDO $real;

    public function __construct(PDO $real)
    {
        $this->real = $real;
    }

    public function beginTransaction(): bool { return $this->real->exec('SAVEPOINT sp_controlador') !== false; }
    public function commit(): bool { return $this->real->exec('RELEASE SAVEPOINT sp_controlador') !== false; }
    public function rollBack(): bool { return $this->real->exec('ROLLBACK TO SAVEPOINT sp_controlador') !== false; }
    public function inTransaction(): bool { return true; }

    public function prepare(string $query, array $options = []): PDOStatement|false { return $this->real->prepare($query, $options); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $fetchMode === null ? $this->real->query($query) : $this->real->query($query, $fetchMode, ...$fetchModeArgs);
    }
    public function exec(string $statement): int|false { return $this->real->exec($statement); }
    public function lastInsertId(?string $name = null): string|false { return $this->real->lastInsertId($name); }
}
