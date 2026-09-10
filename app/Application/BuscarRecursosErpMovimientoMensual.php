<?php
namespace AppBancos\Application;

use AppBancos\Repository\BusquedaRecursosErpRepository;
use InvalidArgumentException;

final class BuscarRecursosErpMovimientoMensual
{
    private $recursos;

    public function __construct(BusquedaRecursosErpRepository $recursos)
    {
        $this->recursos = $recursos;
    }

    public function buscarValores(array $entrada)
    {
        $contexto = $this->validarContexto($entrada);
        return $this->recursos->buscarValores(
            $contexto['movimiento_id'],
            $contexto['cuenta_bancaria_id'],
            $contexto['inicio_periodo'],
            $contexto['limite']
        );
    }

    public function buscarAsientos(array $entrada)
    {
        $contexto = $this->validarContexto($entrada);
        if (!array_key_exists('compartido', $entrada)) {
            throw new InvalidArgumentException('compartido debe ser 0 o 1.');
        }
        $compartido = filter_var(
            $entrada['compartido'] ?? null,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if ($compartido === null) {
            throw new InvalidArgumentException('compartido debe ser 0 o 1.');
        }
        return $this->recursos->buscarAsientos(
            $contexto['movimiento_id'],
            $contexto['cuenta_bancaria_id'],
            $contexto['inicio_periodo'],
            $compartido,
            $contexto['limite']
        );
    }

    public function buscarNodos(array $entrada)
    {
        $contexto = $this->validarContexto($entrada);
        $busqueda = $this->validarBusqueda($entrada['busqueda'] ?? '', false);
        return $this->recursos->buscarNodos(
            $contexto['movimiento_id'],
            $contexto['cuenta_bancaria_id'],
            $contexto['inicio_periodo'],
            $busqueda,
            $contexto['limite']
        );
    }

    public function buscarCuentas(array $entrada)
    {
        $contexto = $this->validarContexto($entrada);
        $nodoId = filter_var($entrada['nodo_zetti_id'] ?? null, FILTER_VALIDATE_INT);
        if ($nodoId === false || $nodoId <= 0) {
            throw new InvalidArgumentException('nodo_zetti_id debe ser un entero positivo.');
        }
        $busqueda = $this->validarBusqueda($entrada['busqueda'] ?? '', true);
        return $this->recursos->buscarCuentas(
            $contexto['movimiento_id'],
            $contexto['cuenta_bancaria_id'],
            $contexto['inicio_periodo'],
            (int) $nodoId,
            $busqueda,
            $contexto['limite']
        );
    }

    private function validarBusqueda($valor, $obligatoria)
    {
        $valor = trim((string) $valor);
        $largo = mb_strlen($valor, 'UTF-8');
        if ($largo > 80 || ($obligatoria && $largo < 2)) {
            throw new InvalidArgumentException(
                $obligatoria
                    ? 'busqueda debe contener entre 2 y 80 caracteres.'
                    : 'busqueda no puede superar 80 caracteres.'
            );
        }
        return $valor;
    }

    private function validarContexto(array $entrada)
    {
        $movimientoId = filter_var($entrada['movimiento_id'] ?? null, FILTER_VALIDATE_INT);
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        $limite = filter_var($entrada['limite'] ?? 12, FILTER_VALIDATE_INT);
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
        if ($limite === false || $limite < 1 || $limite > 20) {
            throw new InvalidArgumentException('limite debe estar entre 1 y 20.');
        }
        return [
            'movimiento_id' => (int) $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'limite' => (int) $limite,
        ];
    }
}
