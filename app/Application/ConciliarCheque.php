<?php
namespace AppBancos\Application;

use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\ConciliacionChequeErpGateway;
use AppBancos\Repository\PreflightConciliacionChequeRepository;
use InvalidArgumentException;

final class ConciliarCheque
{
    private $cierres;
    private $preflight;
    private $gateway;
    private $ejecutor;

    public function __construct(
        CierreMovimientoRepository $cierres,
        PreflightConciliacionChequeRepository $preflight,
        ConciliacionChequeErpGateway $gateway,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->cierres = $cierres;
        $this->preflight = $preflight;
        $this->gateway = $gateway;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, $usuarioId, $claveIdempotencia)
    {
        $movimientoId = filter_var($entrada['movimiento_id'] ?? null, FILTER_VALIDATE_INT);
        $cuentaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $periodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        if ($movimientoId === false || $movimientoId <= 0) {
            throw new InvalidArgumentException('movimiento_id debe ser un entero positivo.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $periodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }
        $entradaCanonica = [
            'movimiento_id' => (int) $movimientoId,
            'cuenta_bancaria_id' => $cuentaId,
            'inicio_periodo' => $periodo,
        ];
        return $this->ejecutor->ejecutar(
            'cheque.conciliar',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'CHEQUE_CONCILIAR',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => ['cuenta_bancaria_id' => $cuentaId, 'inicio_periodo' => $periodo],
            ],
            function () use ($movimientoId, $cuentaId, $periodo, $usuarioId, $claveIdempotencia) {
                $this->cierres->bloquear($movimientoId, $cuentaId, $periodo);
                $evaluacion = $this->preflight->evaluar($movimientoId, $cuentaId, $periodo);
                if (!$evaluacion['listo_para_conciliar']) {
                    throw new RecursoNoDisponibleException(
                        'La conciliacion esta bloqueada: ' . implode(', ', array_column($evaluacion['bloqueos'], 'codigo'))
                    );
                }
                $respuesta = $this->gateway->conciliar($evaluacion, $usuarioId, $claveIdempotencia);
                return [
                    'codigo_http' => 201,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => $respuesta,
                ];
            }
        );
    }
}
