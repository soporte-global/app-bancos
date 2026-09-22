<?php
namespace AppBancos\Application;

use AppBancos\Repository\ConfiguracionClasificacionRepository;
use InvalidArgumentException;

final class DesvincularCuentaConfiguracion
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
        $cuentaId = $this->identificador($entrada['cuenta_bancaria_id'] ?? null, 'cuenta_bancaria_id');
        $motivo = trim((string) ($entrada['motivo'] ?? ''));
        if (mb_strlen($motivo, 'UTF-8') < 3 || mb_strlen($motivo, 'UTF-8') > 200) {
            throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');
        }
        $canonica = [
            'configuracion_id' => $configuracionId,
            'cuenta_bancaria_id' => $cuentaId,
            'motivo' => $motivo,
        ];
        return $this->ejecutor->ejecutar(
            'configuracion.desvincular-cuenta',
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => 'CONFIGURACION_DESVINCULAR_CUENTA',
                'recurso_tipo' => 'CONFIGURACION_CUENTA',
                'recurso_id' => $configuracionId . ':' . $cuentaId,
                'resultado' => 'EXITOSA',
                'detalles' => ['motivo' => $motivo],
            ],
            function () use ($canonica, $operadorId) {
                return [
                    'codigo_http' => 200,
                    'respuesta' => $this->configuraciones->desvincularCuenta(
                        $canonica['configuracion_id'],
                        $canonica['cuenta_bancaria_id'],
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
