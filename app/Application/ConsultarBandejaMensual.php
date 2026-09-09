<?php
namespace AppBancos\Application;

use AppBancos\Repository\BandejaMensualRepository;
use InvalidArgumentException;

final class ConsultarBandejaMensual
{
    private $repositorio;
    private $cursores;

    public function __construct(BandejaMensualRepository $repositorio, CursorBandejaMensual $cursores)
    {
        $this->repositorio = $repositorio;
        $this->cursores = $cursores;
    }

    public function ejecutar(array $entrada)
    {
        $cuentaBancariaId = $entrada['cuenta_bancaria_id'] ?? null;
        $inicioPeriodo = $entrada['inicio_periodo'] ?? null;
        if ($cuentaBancariaId === null || $inicioPeriodo === null) {
            throw new InvalidArgumentException('cuenta_bancaria_id e inicio_periodo son obligatorios.');
        }

        $limite = array_key_exists('limite', $entrada) && $entrada['limite'] !== ''
            ? $entrada['limite']
            : 50;
        $cursor = $this->cursores->decodificar(
            $entrada['cursor'] ?? null,
            $cuentaBancariaId,
            $inicioPeriodo
        );
        $pagina = $this->repositorio->listarPagina(
            $cuentaBancariaId,
            $inicioPeriodo,
            $cursor,
            $limite
        );
        $movimientos = $pagina['movimientos'];
        $ultimo = $movimientos ? $movimientos[count($movimientos) - 1] : null;
        $paginaActual = $cursor === null ? 1 : ($cursor['pagina'] ?? null);
        $inicioActual = $cursor === null ? 1 : ($cursor['inicio'] ?? null);
        $cantidadActual = count($movimientos);
        $inicioVisible = $cantidadActual > 0 ? $inicioActual : 0;
        $paginaSiguiente = $paginaActual === null ? null : $paginaActual + 1;
        $inicioSiguiente = $inicioActual === null ? null : $inicioActual + $cantidadActual;

        return [
            'cuenta_bancaria_id' => (int) $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'limite' => (int) $limite,
            'movimientos' => $movimientos,
            'pagina_actual' => $paginaActual,
            'inicio_actual' => $inicioVisible,
            'siguiente_cursor' => $pagina['hay_mas']
                ? $this->cursores->codificar(
                    $ultimo,
                    $cuentaBancariaId,
                    $inicioPeriodo,
                    $paginaSiguiente,
                    $inicioSiguiente
                )
                : null,
        ];
    }
}
