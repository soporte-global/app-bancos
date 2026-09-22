<?php
namespace AppBancos\Application;

use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use InvalidArgumentException;

final class CerrarMovimientoMensualSinErp
{
    private $cierres;
    private $preflight;
    private $ejecutor;

    public function __construct(
        CierreMovimientoRepository $cierres,
        PreflightCierreMovimientoRepository $preflight,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->cierres = $cierres;
        $this->preflight = $preflight;
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
            'alcance' => 'SIN_EFECTO_ERP',
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.cerrar-sin-erp',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_CERRAR_SIN_ERP',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'estado_anterior' => 'PARA_CERRAR',
                    'estado_nuevo' => 'CERRADO',
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                    'alcance' => 'SIN_EFECTO_ERP',
                ],
            ],
            function () use ($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $claveIdempotencia) {
                $this->cierres->bloquear($movimientoId, $cuentaBancariaId, $inicioPeriodo);
                $evaluacion = $this->preflight->evaluar(
                    $movimientoId,
                    $cuentaBancariaId,
                    $inicioPeriodo
                );
                if (!$evaluacion['listo']) {
                    throw new RecursoNoDisponibleException(
                        'El cierre esta bloqueado por el preflight: ' . $this->resumir($evaluacion['bloqueos'])
                    );
                }
                foreach ($evaluacion['efectos_previstos'] as $efecto) {
                    if (in_array($efecto['tipo'], ['VALOR', 'BORRADOR_ASIENTO'], true)) {
                        throw new RecursoNoDisponibleException(
                            'Este endpoint solo admite cierres sin efectos ERP.'
                        );
                    }
                }
                if (!$evaluacion['ejecutable_ahora']) {
                    throw new RecursoNoDisponibleException(
                        'El cierre requiere efectos ERP que todavia no estan habilitados.'
                    );
                }
                $respuesta = $this->cierres->registrar($movimientoId, $usuarioId, $claveIdempotencia);
                $respuesta['preflight'] = [
                    'contrato_version' => $evaluacion['contrato_version'],
                    'resultado' => $evaluacion['resultado'],
                    'advertencias' => $evaluacion['advertencias'],
                    'efectos_previstos' => $evaluacion['efectos_previstos'],
                ];
                return [
                    'codigo_http' => 200,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'efectos_erp' => 0,
                        'efectos_previstos' => $evaluacion['efectos_previstos'],
                        'advertencias' => $evaluacion['advertencias'],
                    ],
                ];
            }
        );
    }

    private function resumir(array $hallazgos)
    {
        $mensajes = array_map(function (array $hallazgo) {
            return $hallazgo['codigo'];
        }, $hallazgos);
        return implode(', ', $mensajes);
    }
}
