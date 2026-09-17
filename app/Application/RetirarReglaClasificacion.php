<?php
namespace AppBancos\Application;

use AppBancos\Repository\ConfiguracionClasificacionRepository;
use InvalidArgumentException;

final class RetirarReglaClasificacion
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
        $motivo = trim((string) ($entrada['motivo'] ?? ''));
        if (mb_strlen($motivo, 'UTF-8') < 3 || mb_strlen($motivo, 'UTF-8') > 200) {
            throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');
        }
        $canonica = [
            'configuracion_id' => $configuracionId,
            'regla_id' => $reglaId,
            'motivo' => $motivo,
        ];
        return $this->ejecutor->ejecutar(
            'configuracion.retirar-regla',
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => 'CONFIGURACION_RETIRAR_REGLA',
                'recurso_tipo' => 'REGLA_CLASIFICACION',
                'recurso_id' => $reglaId,
                'resultado' => 'EXITOSA',
                'detalles' => ['motivo' => $motivo],
            ],
            function () use ($canonica, $operadorId) {
                return [
                    'codigo_http' => 200,
                    'respuesta' => $this->configuraciones->retirarRegla(
                        $canonica['configuracion_id'],
                        $canonica['regla_id'],
                        $operadorId
                    ),
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
