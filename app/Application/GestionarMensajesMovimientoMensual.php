<?php
namespace AppBancos\Application;

use AppBancos\Repository\MensajeriaMovimientoRepository;
use InvalidArgumentException;

final class GestionarMensajesMovimientoMensual
{
    private $mensajeria;
    private $ejecutor;

    public function __construct(
        MensajeriaMovimientoRepository $mensajeria,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->mensajeria = $mensajeria;
        $this->ejecutor = $ejecutor;
    }

    public function agregar(array $entrada, $usuarioId, $claveIdempotencia)
    {
        $contexto = $this->validarContexto($entrada);
        $cuerpo = trim((string) ($entrada['cuerpo'] ?? ''));
        $largo = mb_strlen($cuerpo, 'UTF-8');
        if ($largo < 1 || $largo > 2000) {
            throw new InvalidArgumentException('cuerpo debe contener entre 1 y 2000 caracteres.');
        }
        $canonica = $contexto + ['cuerpo' => $cuerpo];
        return $this->ejecutor->ejecutar(
            'movimiento.agregar-mensaje',
            $claveIdempotencia,
            $usuarioId,
            $canonica,
            [
                'accion' => 'MOVIMIENTO_AGREGAR_MENSAJE',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $contexto['movimiento_id'],
                'resultado' => 'EXITOSA',
                'detalles' => ['longitud' => $largo],
            ],
            function () use ($contexto, $usuarioId, $cuerpo) {
                return [
                    'codigo_http' => 201,
                    'respuesta' => $this->mensajeria->agregar(
                        $contexto['movimiento_id'],
                        $contexto['cuenta_bancaria_id'],
                        $contexto['inicio_periodo'],
                        $usuarioId,
                        $cuerpo
                    ),
                ];
            }
        );
    }

    public function marcarLeidos(array $entrada, $usuarioId, $claveIdempotencia)
    {
        $contexto = $this->validarContexto($entrada);
        return $this->ejecutor->ejecutar(
            'movimiento.marcar-mensajes-leidos',
            $claveIdempotencia,
            $usuarioId,
            $contexto,
            [
                'accion' => 'MOVIMIENTO_MARCAR_MENSAJES_LEIDOS',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $contexto['movimiento_id'],
                'resultado' => 'EXITOSA',
                'detalles' => [],
            ],
            function () use ($contexto, $usuarioId) {
                return [
                    'codigo_http' => 200,
                    'respuesta' => $this->mensajeria->marcarLeidos(
                        $contexto['movimiento_id'],
                        $contexto['cuenta_bancaria_id'],
                        $contexto['inicio_periodo'],
                        $usuarioId
                    ),
                ];
            }
        );
    }

    private function validarContexto(array $entrada)
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
        return [
            'movimiento_id' => (int) $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
        ];
    }
}
