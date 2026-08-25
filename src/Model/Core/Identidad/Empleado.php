<?php
namespace GlobalApps\Core\Identidad;

use InvalidArgumentException;

final class Empleado
{
    private $id;
    private $legajo;
    private $nombre;
    private $apellido;
    private $cuil;
    private $dni;
    private $empleadoFarmacia;
    private $usuarioZwebId;
    private $clienteId;
    private $relacionesLaborales;

    public function __construct(
        $id,
        $legajo,
        $nombre,
        $apellido,
        $cuil,
        $dni,
        $empleadoFarmacia,
        $usuarioZwebId,
        $clienteId,
        array $relacionesLaborales
    ) {
        foreach ($relacionesLaborales as $relacion) {
            if (!$relacion instanceof RelacionLaboral) {
                throw new InvalidArgumentException('El empleado contiene una relacion laboral invalida.');
            }
        }

        $this->id = (int) $id;
        $this->legajo = (string) $legajo;
        $this->nombre = (string) $nombre;
        $this->apellido = (string) $apellido;
        $this->cuil = $cuil === null ? null : (string) $cuil;
        $this->dni = $dni === null ? null : (string) $dni;
        $this->empleadoFarmacia = (bool) $empleadoFarmacia;
        $this->usuarioZwebId = $usuarioZwebId === null ? null : (int) $usuarioZwebId;
        $this->clienteId = $clienteId === null ? null : (int) $clienteId;
        $this->relacionesLaborales = array_values($relacionesLaborales);
    }

    public function id()
    {
        return $this->id;
    }

    public function legajo()
    {
        return $this->legajo;
    }

    public function nombre()
    {
        return $this->nombre;
    }

    public function apellido()
    {
        return $this->apellido;
    }

    public function nombreCompleto()
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function cuil()
    {
        return $this->cuil;
    }

    public function dni()
    {
        return $this->dni;
    }

    public function empleadoFarmacia()
    {
        return $this->empleadoFarmacia;
    }

    public function usuarioZwebId()
    {
        return $this->usuarioZwebId;
    }

    public function clienteId()
    {
        return $this->clienteId;
    }

    public function relacionesLaborales()
    {
        return $this->relacionesLaborales;
    }
}
