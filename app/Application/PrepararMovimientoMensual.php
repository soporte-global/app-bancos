<?php
namespace AppBancos\Application;

use AppBancos\Repository\MovimientoMensualRepository;
use InvalidArgumentException;

final class PrepararMovimientoMensual
{
    private $movimientos;
    private $ejecutor;

    public function __construct(
        MovimientoMensualRepository $movimientos,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->movimientos = $movimientos;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, $usuarioId, $claveIdempotencia)
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

        $entradaCanonica = [
            'movimiento_id' => (int) $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.preparar',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_PREPARAR',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'estado_anterior' => 'ABIERTO',
                    'estado_nuevo' => 'PARA_CERRAR',
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                ],
            ],
            function () use ($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $claveIdempotencia) {
                return [
                    'codigo_http' => 200,
                    'respuesta' => $this->movimientos->preparar(
                        $movimientoId,
                        $cuentaBancariaId,
                        $inicioPeriodo,
                        $usuarioId,
                        $claveIdempotencia
                    ),
                ];
            }
        );
    }
}
