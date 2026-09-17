<?php
namespace AppBancos\Application;

use AppBancos\Repository\ConfiguracionClasificacionRepository;
use InvalidArgumentException;

final class ActualizarValidacionAutomaticaRegla
{
    private $configuraciones;
    private $ejecutor;

    public function __construct(
        ConfiguracionClasificacionRepository $configuraciones,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->configuraciones = $configuraciones;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, $operadorId, $claveIdempotencia)
    {
        $configuracionId = $this->identificador($entrada['configuracion_id'] ?? null, 'configuracion_id');
        $reglaId = $this->identificador($entrada['regla_id'] ?? null, 'regla_id');
        if (!array_key_exists('validar_automaticamente', $entrada)
            || !is_bool($entrada['validar_automaticamente'])
        ) {
            throw new InvalidArgumentException('validar_automaticamente debe ser booleano.');
        }
        $validar = $entrada['validar_automaticamente'];
        $canonica = [
            'configuracion_id' => $configuracionId,
            'regla_id' => $reglaId,
            'validar_automaticamente' => $validar,
        ];

        return $this->ejecutor->ejecutar(
            'configuracion.actualizar-validacion-automatica',
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => 'CONFIGURACION_ACTUALIZAR_VALIDACION_AUTOMATICA',
                'recurso_tipo' => 'REGLA_CLASIFICACION',
                'recurso_id' => $reglaId,
                'resultado' => 'EXITOSA',
                'detalles' => ['solicitada' => $validar],
            ],
            function () use ($canonica, $operadorId) {
                $respuesta = $this->configuraciones->actualizarValidacionAutomatica(
                    $canonica['configuracion_id'],
                    $canonica['regla_id'],
                    $canonica['validar_automaticamente'],
                    $operadorId
                );
                return [
                    'codigo_http' => 200,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'anterior' => $respuesta['validar_automaticamente_anterior'],
                        'cambio' => $respuesta['cambio'],
                    ],
                ];
            }
        );
    }

    private function identificador($valor, $campo)
    {
        $valor = trim((string) $valor);
        if (!preg_match('/^[0-9]{1,20}$/', $valor)) {
            throw new InvalidArgumentException($campo . ' es invalido.');
        }
        $valor = ltrim($valor, '0');
        if ($valor === '') {
            throw new InvalidArgumentException($campo . ' es invalido.');
        }
        return $valor;
    }
}
