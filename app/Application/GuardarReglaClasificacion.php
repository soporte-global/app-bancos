<?php
namespace AppBancos\Application;

use AppBancos\Repository\ConfiguracionClasificacionRepository;
use InvalidArgumentException;

final class GuardarReglaClasificacion
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
        $reglaId = ($entrada['regla_id'] ?? null) === null || trim((string) $entrada['regla_id']) === ''
            ? null
            : $this->identificador($entrada['regla_id'], 'regla_id');
        $subtipoId = $this->enteroRango(
            $entrada['subtipo_valor_zetti_id'] ?? null,
            'subtipo_valor_zetti_id',
            1,
            32767
        );
        $sentido = strtoupper(trim((string) ($entrada['sentido'] ?? '')));
        if (!in_array($sentido, ['C', 'D', 'A'], true)) {
            throw new InvalidArgumentException('sentido debe ser C, D o A.');
        }
        $codigo = trim((string) ($entrada['codigo_extracto'] ?? ''));
        if (mb_strlen($codigo, 'UTF-8') > 60) {
            throw new InvalidArgumentException('codigo_extracto no puede superar 60 caracteres.');
        }
        $codigo = $codigo === '' ? null : mb_strtoupper($codigo, 'UTF-8');
        if (!array_key_exists('validar_automaticamente', $entrada)
            || !is_bool($entrada['validar_automaticamente'])
        ) {
            throw new InvalidArgumentException('validar_automaticamente debe ser booleano.');
        }
        $motivo = trim((string) ($entrada['motivo'] ?? ''));
        if (mb_strlen($motivo, 'UTF-8') < 3 || mb_strlen($motivo, 'UTF-8') > 200) {
            throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');
        }

        $canonica = [
            'configuracion_id' => $configuracionId,
            'regla_id' => $reglaId,
            'subtipo_valor_zetti_id' => $subtipoId,
            'sentido' => $sentido,
            'codigo_extracto' => $codigo,
            'validar_automaticamente' => $entrada['validar_automaticamente'],
            'motivo' => $motivo,
        ];
        $operacion = $reglaId === null
            ? 'configuracion.crear-regla'
            : 'configuracion.versionar-regla';

        return $this->ejecutor->ejecutar(
            $operacion,
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => $reglaId === null
                    ? 'CONFIGURACION_CREAR_REGLA'
                    : 'CONFIGURACION_VERSIONAR_REGLA',
                'recurso_tipo' => 'REGLA_CLASIFICACION',
                'recurso_id' => $reglaId,
                'resultado' => 'EXITOSA',
                'detalles' => ['motivo' => $motivo],
            ],
            function () use ($canonica, $operadorId) {
                $respuesta = $this->configuraciones->guardarRegla(
                    $canonica['configuracion_id'],
                    $canonica['regla_id'],
                    [
                        'subtipo_valor_zetti_id' => $canonica['subtipo_valor_zetti_id'],
                        'sentido' => $canonica['sentido'],
                        'codigo_extracto' => $canonica['codigo_extracto'],
                        'validar_automaticamente' => $canonica['validar_automaticamente'],
                    ],
                    $operadorId,
                    $canonica['motivo']
                );
                return [
                    'codigo_http' => $canonica['regla_id'] === null ? 201 : 200,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'regla_id_anterior' => $respuesta['regla_id_anterior'],
                        'regla_id' => $respuesta['regla_id'],
                        'version' => $respuesta['version'],
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

    private function enteroRango($valor, $campo, $minimo, $maximo)
    {
        $valor = filter_var($valor, FILTER_VALIDATE_INT);
        if ($valor === false || $valor < $minimo || $valor > $maximo) {
            throw new InvalidArgumentException($campo . ' esta fuera de rango.');
        }
        return (int) $valor;
    }
}
