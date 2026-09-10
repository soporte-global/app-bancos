<?php
namespace AppBancos\Application;

use AppBancos\Repository\MovimientoMensualRepository;
use InvalidArgumentException;

final class AsociarValorMovimientoMensual
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
        $valorId = filter_var($entrada['valor_zetti_id'] ?? null, FILTER_VALIDATE_INT);
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        if ($movimientoId === false || $movimientoId <= 0) {
            throw new InvalidArgumentException('movimiento_id debe ser un entero positivo.');
        }
        if ($valorId === false || $valorId <= 0) {
            throw new InvalidArgumentException('valor_zetti_id debe ser un entero positivo.');
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
            'valor_zetti_id' => (int) $valorId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.asociar-valor',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_ASOCIAR_VALOR',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'valor_zetti_id' => (int) $valorId,
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                ],
            ],
            function () use ($movimientoId, $cuentaBancariaId, $inicioPeriodo, $valorId, $usuarioId, $claveIdempotencia) {
                $respuesta = $this->movimientos->asociarValor(
                    $movimientoId,
                    $cuentaBancariaId,
                    $inicioPeriodo,
                    $valorId,
                    $usuarioId,
                    $claveIdempotencia
                );
                return [
                    'codigo_http' => 201,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'reserva_id' => $respuesta['reserva_id'],
                        'asociacion_id' => $respuesta['asociacion_id'],
                        'monto_asociado' => $respuesta['monto_asociado'],
                    ],
                ];
            }
        );
    }
}
