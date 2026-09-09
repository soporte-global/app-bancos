<?php
namespace AppBancos\Application;

use AppBancos\Repository\BandejaMensualRepository;
use InvalidArgumentException;

final class ConsultarBandejaMensual
{
    private $repositorio;
    private $cursores;

    public function __construct(BandejaMensualRepository $repositorio, CursorBandejaMensual $cursores)
    {
        $this->repositorio = $repositorio;
        $this->cursores = $cursores;
    }

    public function ejecutar(array $entrada)
    {
        $cuentaBancariaId = $entrada['cuenta_bancaria_id'] ?? null;
        $inicioPeriodo = $entrada['inicio_periodo'] ?? null;
        if ($cuentaBancariaId === null || $inicioPeriodo === null) {
            throw new InvalidArgumentException('cuenta_bancaria_id e inicio_periodo son obligatorios.');
        }

        $limite = array_key_exists('limite', $entrada) && $entrada['limite'] !== ''
            ? $entrada['limite']
            : 50;
        $filtros = $this->filtros($entrada);
        $cursor = $this->cursores->decodificar(
            $entrada['cursor'] ?? null,
            $cuentaBancariaId,
            $inicioPeriodo,
            $filtros
        );
        $pagina = $this->repositorio->listarPagina(
            $cuentaBancariaId,
            $inicioPeriodo,
            $cursor,
            $limite,
            $filtros
        );
        $movimientos = $pagina['movimientos'];
        $ultimo = $movimientos ? $movimientos[count($movimientos) - 1] : null;
        $paginaActual = $cursor === null ? 1 : ($cursor['pagina'] ?? null);
        $inicioActual = $cursor === null ? 1 : ($cursor['inicio'] ?? null);
        $cantidadActual = count($movimientos);
        $inicioVisible = $cantidadActual > 0 ? $inicioActual : 0;
        $paginaSiguiente = $paginaActual === null ? null : $paginaActual + 1;
        $inicioSiguiente = $inicioActual === null ? null : $inicioActual + $cantidadActual;

        return [
            'cuenta_bancaria_id' => (int) $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'limite' => (int) $limite,
            'filtros' => $filtros,
            'movimientos' => $movimientos,
            'pagina_actual' => $paginaActual,
            'inicio_actual' => $inicioVisible,
            'siguiente_cursor' => $pagina['hay_mas']
                ? $this->cursores->codificar(
                    $ultimo,
                    $cuentaBancariaId,
                    $inicioPeriodo,
                    $paginaSiguiente,
                    $inicioSiguiente,
                    $filtros
                )
                : null,
        ];
    }

    private function filtros(array $entrada)
    {
        $estado = strtoupper(trim((string) ($entrada['estado'] ?? '')));
        if ($estado === '') {
            $estado = null;
        } elseif (!in_array($estado, ['ABIERTO', 'PARA_CERRAR', 'CERRADO'], true)) {
            throw new InvalidArgumentException('estado no es válido.');
        }

        $responsable = $entrada['responsable_id'] ?? null;
        if ($responsable === '') {
            $responsable = null;
        }
        if ($responsable !== null
            && (filter_var($responsable, FILTER_VALIDATE_INT) === false || (int) $responsable <= 0)
        ) {
            throw new InvalidArgumentException('responsable_id debe ser un entero positivo.');
        }

        return [
            'estado' => $estado,
            'responsable_id' => $responsable === null ? null : (int) $responsable,
            'asociacion' => $this->presencia($entrada['asociacion'] ?? '', 'asociacion'),
            'mensajes' => $this->presencia($entrada['mensajes'] ?? '', 'mensajes'),
        ];
    }

    private function presencia($valor, $nombre)
    {
        $valor = strtoupper(trim((string) $valor));
        if ($valor === '') {
            return null;
        }
        if (!in_array($valor, ['CON', 'SIN'], true)) {
            throw new InvalidArgumentException($nombre . ' no es válido.');
        }
        return $valor;
    }
}
