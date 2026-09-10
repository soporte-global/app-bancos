<?php
namespace AppBancos\Application;

use AppBancos\Repository\AsignacionMovimientoRepository;
use InvalidArgumentException;

final class AsignarResponsableMovimientoMensual
{
    private $asignaciones;
    private $ejecutor;

    public function __construct(
        AsignacionMovimientoRepository $asignaciones,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->asignaciones = $asignaciones;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, $operadorId, $claveIdempotencia)
    {
        $movimientoId = $this->enteroPositivo($entrada['movimiento_id'] ?? null, 'movimiento_id');
        $responsableId = $this->enteroPositivo($entrada['responsable_id'] ?? null, 'responsable_id');
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaBancariaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $inicioPeriodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $inicioPeriodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }
        $canonica = [
            'movimiento_id' => $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'responsable_id' => $responsableId,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.asignar-responsable',
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => 'MOVIMIENTO_ASIGNAR_RESPONSABLE',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => ['responsable_id' => $responsableId],
            ],
            function () use ($canonica, $operadorId, $claveIdempotencia) {
                return [
                    'codigo_http' => 200,
                    'respuesta' => $this->asignaciones->asignar(
                        $canonica['movimiento_id'],
                        $canonica['cuenta_bancaria_id'],
                        $canonica['inicio_periodo'],
                        $canonica['responsable_id'],
                        $operadorId,
                        $claveIdempotencia
                    ),
                ];
            }
        );
    }

    private function enteroPositivo($valor, $campo)
    {
        $valor = filter_var($valor, FILTER_VALIDATE_INT);
        if ($valor === false || $valor <= 0) {
            throw new InvalidArgumentException($campo . ' debe ser un entero positivo.');
        }
        return (int) $valor;
    }
}
