<?php
namespace AppBancos\Application;

use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\BorradorAsientoErpGateway;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use AppBancos\Repository\ValorErpGateway;
use InvalidArgumentException;

final class CerrarMovimientoMensual
{
    private $cierres;
    private $preflight;
    private $valores;
    private $borradores;
    private $ejecutor;

    public function __construct(
        CierreMovimientoRepository $cierres,
        PreflightCierreMovimientoRepository $preflight,
        ValorErpGateway $valores,
        BorradorAsientoErpGateway $borradores,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->cierres = $cierres;
        $this->preflight = $preflight;
        $this->valores = $valores;
        $this->borradores = $borradores;
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
            'movimiento.cerrar',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_CERRAR',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'estado_anterior' => 'PARA_CERRAR',
                    'estado_nuevo' => 'CERRADO',
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'inicio_periodo' => $inicioPeriodo,
                ],
            ],
            function () use ($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $claveIdempotencia) {
                $this->cierres->bloquear($movimientoId, $cuentaBancariaId, $inicioPeriodo);
                $evaluacion = $this->preflight->evaluar($movimientoId, $cuentaBancariaId, $inicioPeriodo);
                if (!$evaluacion['listo']) {
                    throw new RecursoNoDisponibleException(
                        'El cierre esta bloqueado por el preflight: ' . $this->resumir($evaluacion['bloqueos'])
                    );
                }
                if (!$evaluacion['ejecutable_ahora']) {
                    throw new RecursoNoDisponibleException(
                        'El cierre requiere un efecto ERP que todavia no esta habilitado.'
                    );
                }

                $efectosAplicados = [];
                $cantidadEfectosErp = 0;
                foreach ($evaluacion['efectos_previstos'] as $efecto) {
                    if ($efecto['tipo'] === 'VALOR' && in_array(
                        $efecto['accion'],
                        ['LIQUIDAR_ESTADO_7', 'SIN_CAMBIOS_YA_LIQUIDADO'],
                        true
                    )) {
                        $resultadoValor = $this->valores->liquidar($efecto['recurso_id']);
                        $efectosAplicados[] = [
                            'tipo' => 'VALOR',
                            'resultado' => $resultadoValor,
                        ];
                        if ($resultadoValor['modificado']) {
                            $cantidadEfectosErp++;
                        }
                    }
                    if ($efecto['tipo'] === 'BORRADOR_ASIENTO' && in_array(
                        $efecto['accion'],
                        ['CREAR_ASIENTO', 'SIN_CAMBIOS_YA_MATERIALIZADO'],
                        true
                    )) {
                        $resultadoBorrador = $this->borradores->materializar(
                            $efecto['recurso_id'],
                            $usuarioId
                        );
                        $efectosAplicados[] = [
                            'tipo' => 'BORRADOR_ASIENTO',
                            'resultado' => $resultadoBorrador,
                        ];
                        if ($resultadoBorrador['modificado']) {
                            $cantidadEfectosErp++;
                        }
                    }
                }
                $respuesta = $this->cierres->registrar(
                    $movimientoId,
                    $usuarioId,
                    $claveIdempotencia,
                    $cantidadEfectosErp,
                    $evaluacion['alcance_ejecucion']
                );
                $respuesta['alcance'] = $evaluacion['alcance_ejecucion'];
                $respuesta['efectos_aplicados'] = $efectosAplicados;
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
                        'alcance' => $evaluacion['alcance_ejecucion'],
                        'efectos_aplicados' => $efectosAplicados,
                        'efectos_previstos' => $evaluacion['efectos_previstos'],
                    ],
                ];
            }
        );
    }

    private function resumir(array $hallazgos)
    {
        return implode(', ', array_column($hallazgos, 'codigo'));
    }
}
