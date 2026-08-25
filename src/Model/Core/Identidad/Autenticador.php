<?php
namespace GlobalApps\Core\Identidad;

use GlobalApps\Core\Acceso\PermisoAccesoRepository;
use PDO;
use RuntimeException;

final class Autenticador
{
    private $pdo;
    private $empleados;
    private $usuariosZweb;
    private $clientes;
    private $permisosAcceso;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->empleados = new EmpleadoRepository($pdo);
        $this->usuariosZweb = new UsuarioZwebRepository($pdo);
        $this->clientes = new ClienteRepository($pdo);
        $this->permisosAcceso = new PermisoAccesoRepository($pdo);
    }

    public function autenticar($username, $password)
    {
        $fila = $this->buscarCuentaPorUsername($username);
        if ($fila === null || !password_verify((string) $password, $fila['password_hash'])) {
            throw new AutenticacionException('Usuario o contrasena invalidos.');
        }

        return $this->crearSesion(
            new Cuenta($fila['id'], $fila['username'], $fila['alias'])
        );
    }

    public function restaurar($cuentaId)
    {
        $fila = $this->consultarCuenta(
            __DIR__ . '/sql/cuenta-activa-por-id.sql',
            [':cuenta' => (int) $cuentaId]
        );
        if ($fila === null) {
            throw new AutenticacionException('La cuenta de la sesion ya no esta habilitada.');
        }

        return $this->crearSesion(
            new Cuenta($fila['id'], $fila['username'], $fila['alias'])
        );
    }

    private function buscarCuentaPorUsername($username)
    {
        return $this->consultarCuenta(
            __DIR__ . '/sql/cuenta-activa-por-username.sql',
            [':username' => trim((string) $username)]
        );
    }

    private function consultarCuenta($archivo, array $parametros)
    {
        $prepare = $this->pdo->prepare(file_get_contents($archivo));
        $prepare->execute($parametros);
        $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) > 1) {
            throw new RuntimeException('La cuenta no tiene una identidad unica.');
        }
        return count($filas) === 1 ? $filas[0] : null;
    }

    private function crearSesion(Cuenta $cuenta)
    {
        $empleado = $this->empleados->buscarActivoPorCuenta($cuenta->id());
        $usuarioZweb = null;
        $cliente = null;
        if ($empleado !== null && $empleado->usuarioZwebId() !== null) {
            $usuarioZweb = $this->usuariosZweb->buscarPorId($empleado->usuarioZwebId());
        }
        if ($empleado !== null && $empleado->clienteId() !== null) {
            $cliente = $this->clientes->buscarPorId($empleado->clienteId());
        }

        return new Sesion(
            $cuenta,
            $empleado,
            $usuarioZweb,
            $cliente,
            $this->permisosAcceso->listarPorCuenta($cuenta->id())
        );
    }
}
