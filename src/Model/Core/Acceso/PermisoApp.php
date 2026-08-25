<?php
namespace GlobalApps\Core\Acceso;

final class PermisoApp
{
    private $id;
    private $aplicacionId;
    private $tipo;
    private $nivel;
    private $descripcion;
    private $nombreInterno;
    private $icono;

    public function __construct(
        $id,
        $aplicacionId,
        $tipo,
        $nivel,
        $descripcion,
        $nombreInterno,
        $icono
    ) {
        $this->id = (int) $id;
        $this->aplicacionId = (int) $aplicacionId;
        $this->tipo = (int) $tipo;
        $this->nivel = (int) $nivel;
        $this->descripcion = $descripcion === null ? null : (string) $descripcion;
        $this->nombreInterno = $nombreInterno === null ? null : (string) $nombreInterno;
        $this->icono = $icono === null ? null : (string) $icono;
    }

    public function id()
    {
        return $this->id;
    }

    public function aplicacionId()
    {
        return $this->aplicacionId;
    }

    public function tipo()
    {
        return $this->tipo;
    }

    public function nivel()
    {
        return $this->nivel;
    }

    public function descripcion()
    {
        return $this->descripcion;
    }

    public function nombreInterno()
    {
        return $this->nombreInterno;
    }

    public function icono()
    {
        return $this->icono;
    }
}
