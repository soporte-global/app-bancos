<?php
namespace AppBancos\Application;

use AppBancos\Repository\MovimientoMensualRepository;
use InvalidArgumentException;

final class AsociarAsientoMovimientoMensual
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
        $asientoId = filter_var($entrada['asiento_zetti_id'] ?? null, FILTER_VALIDATE_INT);
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        $compartido = $entrada['compartido'] ?? null;
        if ($movimientoId === false || $movimientoId <= 0) {
            throw new InvalidArgumentException('movimiento_id debe ser un entero positivo.');
        }
        if ($asientoId === false || $asientoId <= 0) {
            throw new InvalidArgumentException('asiento_zetti_id debe ser un entero positivo.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaBancariaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $inicioPeriodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $inicioPeriodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }
        if (!is_bool($compartido)) {
            throw new InvalidArgumentException('compartido debe ser booleano.');
        }

        $entradaCanonica = [
            'movimiento_id' => (int) $movimientoId,
            'asiento_zetti_id' => (int) $asientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'compartido' => $compartido,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.asociar-asiento',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_ASOCIAR_ASIENTO',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'asiento_zetti_id' => (string) $asientoId,
                    'compartido' => $compartido,
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                ],
            ],
            function () use ($entradaCanonica, $usuarioId, $claveIdempotencia) {
                $respuesta = $this->movimientos->asociarAsiento(
                    $entradaCanonica['movimiento_id'],
                    $entradaCanonica['cuenta_bancaria_id'],
                    $entradaCanonica['inicio_periodo'],
                    $entradaCanonica['asiento_zetti_id'],
                    $entradaCanonica['compartido'],
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
                        'cantidad_lineas_asiento' => $respuesta['cantidad_lineas_asiento'],
                    ],
                ];
            }
        );
    }
}
