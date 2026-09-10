<?php
namespace AppBancos\Application;

use AppBancos\Repository\MovimientoMensualRepository;
use InvalidArgumentException;

final class RevertirPreparacionMovimientoMensual
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
        $motivo = trim((string) ($entrada['motivo'] ?? ''));
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
        if (mb_strlen($motivo, 'UTF-8') < 3 || mb_strlen($motivo, 'UTF-8') > 200) {
            throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');
        }

        $entradaCanonica = [
            'movimiento_id' => (int) $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'motivo' => $motivo,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.revertir-preparacion',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_REVERTIR_PREPARACION',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'estado_anterior' => 'PARA_CERRAR',
                    'estado_nuevo' => 'ABIERTO',
                    'motivo' => $motivo,
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                ],
            ],
            function () use ($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $motivo, $claveIdempotencia) {
                $respuesta = $this->movimientos->revertirPreparacion(
                    $movimientoId,
                    $cuentaBancariaId,
                    $inicioPeriodo,
                    $usuarioId,
                    $motivo,
                    $claveIdempotencia
                );
                return [
                    'codigo_http' => 200,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => ['descartados' => $respuesta['descartados']],
                ];
            }
        );
    }
}
