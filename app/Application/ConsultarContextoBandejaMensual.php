<?php
namespace AppBancos\Application;

use AppBancos\Repository\ContextoBandejaMensualRepository;

final class ConsultarContextoBandejaMensual
{
    private $repositorio;

    public function __construct(ContextoBandejaMensualRepository $repositorio)
    {
        $this->repositorio = $repositorio;
    }

    public function ejecutar()
    {
        return $this->repositorio->consultar();
    }
}
