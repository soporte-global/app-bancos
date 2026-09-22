<?php
namespace AppBancos\Application;

use AppBancos\Repository\PreflightCierreMovimientoRepository;
use InvalidArgumentException;

final class PrevalidarCierreMovimientoMensual
{
    private $preflight;

    public function __construct(PreflightCierreMovimientoRepository $preflight)
    {
        $this->preflight = $preflight;
    }

    public function ejecutar(array $entrada)
    {
        $movimientoId = filter_var($entrada['movimiento_id'] ?? null, FILTER_VALIDATE_INT);
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        if ($movimientoId === false || $movimientoId <= 0) {
            throw new InvalidArgumentException('movimiento_id debe ser un entero positivo.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaBancariaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $inicioPeriodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $inicioPeriodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }

        return $this->preflight->evaluar(
            (int) $movimientoId,
            $cuentaBancariaId,
            $inicioPeriodo
        );
    }
}
