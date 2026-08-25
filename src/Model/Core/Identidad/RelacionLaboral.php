<?php
namespace GlobalApps\Core\Identidad;

final class RelacionLaboral
{
    private $empresaId;
    private $empresa;
    private $cuit;
    private $nodoId;
    private $liquida;
    private $numeroLegajoOrigen;
    private $fechaIngreso;

    public function __construct(
        $empresaId,
        $empresa,
        $cuit,
        $nodoId,
        $liquida,
        $numeroLegajoOrigen,
        $fechaIngreso
    ) {
        $this->empresaId = (int) $empresaId;
        $this->empresa = (string) $empresa;
        $this->cuit = $cuit === null ? null : (string) $cuit;
        $this->nodoId = $nodoId === null ? null : (int) $nodoId;
        $this->liquida = (bool) $liquida;
        $this->numeroLegajoOrigen = $numeroLegajoOrigen === null
            ? null
            : (string) $numeroLegajoOrigen;
        $this->fechaIngreso = $fechaIngreso === null ? null : (string) $fechaIngreso;
    }

    public function empresaId()
    {
        return $this->empresaId;
    }

    public function empresa()
    {
        return $this->empresa;
    }

    public function cuit()
    {
        return $this->cuit;
    }

    public function nodoId()
    {
        return $this->nodoId;
    }

    public function liquida()
    {
        return $this->liquida;
    }

    public function numeroLegajoOrigen()
    {
        return $this->numeroLegajoOrigen;
    }

    public function fechaIngreso()
    {
        return $this->fechaIngreso;
    }
}
