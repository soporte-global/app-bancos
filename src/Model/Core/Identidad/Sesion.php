<?php
namespace GlobalApps\Core\Identidad;

use GlobalApps\Core\Acceso\PermisoAcceso;
use InvalidArgumentException;

final class Sesion
{
    private $cuenta;
    private $empleado;
    private $usuarioZweb;
    private $cliente;
    private $permisosAcceso;

    public function __construct(
        Cuenta $cuenta,
        Empleado $empleado = null,
        UsuarioZweb $usuarioZweb = null,
        Cliente $cliente = null,
        array $permisosAcceso = []
    ) {
        foreach ($permisosAcceso as $permisoAcceso) {
            if (!$permisoAcceso instanceof PermisoAcceso) {
                throw new InvalidArgumentException('La sesion contiene un permiso de acceso invalido.');
            }
        }
        if ($usuarioZweb !== null && $empleado === null) {
            throw new InvalidArgumentException('La identidad Zweb requiere un empleado asociado.');
        }
        if ($cliente !== null && $empleado === null) {
            throw new InvalidArgumentException('El cliente requiere un empleado asociado.');
        }
        if ($usuarioZweb !== null && $empleado->usuarioZwebId() !== $usuarioZweb->id()) {
            throw new InvalidArgumentException('La identidad Zweb no corresponde al empleado.');
        }
        if ($cliente !== null && $empleado->clienteId() !== $cliente->id()) {
            throw new InvalidArgumentException('El cliente no corresponde al empleado.');
        }

        $this->cuenta = $cuenta;
        $this->empleado = $empleado;
        $this->usuarioZweb = $usuarioZweb;
        $this->cliente = $cliente;
        $this->permisosAcceso = [];
        foreach ($permisosAcceso as $permisoAcceso) {
            $this->permisosAcceso[$permisoAcceso->aplicacionId()] = $permisoAcceso;
        }
    }

    public function cuenta()
    {
        return $this->cuenta;
    }

    public function empleado()
    {
        return $this->empleado;
    }

    public function usuarioZweb()
    {
        return $this->usuarioZweb;
    }

    public function cliente()
    {
        return $this->cliente;
    }

    public function permisosAcceso()
    {
        return array_values($this->permisosAcceso);
    }

    public function permisoAccesoDe($aplicacionId)
    {
        $aplicacionId = (int) $aplicacionId;
        return $this->permisosAcceso[$aplicacionId] ?? null;
    }

    public function permisosAppDe($aplicacionId)
    {
        $permisoAcceso = $this->permisoAccesoDe($aplicacionId);
        return $permisoAcceso === null ? [] : $permisoAcceso->permisosApp();
    }

    public function nombreMostrado()
    {
        if ($this->empleado !== null && $this->empleado->nombreCompleto() !== '') {
            return $this->empleado->nombreCompleto();
        }
        return $this->cuenta->alias();
    }
}
