<?php
namespace AppBancos\Application;

use AppBancos\Repository\MovimientoMensualRepository;
use InvalidArgumentException;

final class CrearBorradorMovimientoMensual
{
    private $movimientos;
    private $ejecutor;

    public function __construct(
        MovimientoMensualRepository $movimientos,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->movimientos = $movimientos;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, $usuarioId, $claveIdempotencia)
    {
        $movimientoId = $this->enteroPositivo($entrada['movimiento_id'] ?? null, 'movimiento_id');
        $nodoId = $this->enteroPositivo($entrada['nodo_zetti_id'] ?? null, 'nodo_zetti_id');
        $cuentaBancariaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $inicioPeriodo = $this->fechaValida($entrada['inicio_periodo'] ?? '', 'inicio_periodo', true);
        $fechaContable = $this->fechaValida($entrada['fecha_contable'] ?? '', 'fecha_contable', false);
        $modelo = trim((string) ($entrada['modelo'] ?? ''));
        if ($modelo === '' || mb_strlen($modelo, 'UTF-8') > 80) {
            throw new InvalidArgumentException('modelo debe contener entre 1 y 80 caracteres.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaBancariaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        $lineasEntrada = $entrada['lineas'] ?? null;
        if (!is_array($lineasEntrada) || count($lineasEntrada) < 2 || count($lineasEntrada) > 200) {
            throw new InvalidArgumentException('lineas debe contener entre 2 y 200 elementos.');
        }

        $lineas = [];
        $totalDebe = '0';
        $totalHaber = '0';
        foreach (array_values($lineasEntrada) as $indice => $linea) {
            if (!is_array($linea)) {
                throw new InvalidArgumentException('La linea ' . ($indice + 1) . ' es invalida.');
            }
            $cuentaId = $this->enteroPositivo($linea['cuenta_zetti_id'] ?? null, 'cuenta_zetti_id');
            $debe = $this->importe($linea['debe'] ?? '0', 'debe');
            $haber = $this->importe($linea['haber'] ?? '0', 'haber');
            if (($debe !== '0.00000') === ($haber !== '0.00000')) {
                throw new InvalidArgumentException(
                    'Cada linea debe tener un importe positivo exclusivamente en debe o haber.'
                );
            }
            $observacion = trim((string) ($linea['observacion'] ?? ''));
            if (mb_strlen($observacion, 'UTF-8') > 200) {
                throw new InvalidArgumentException('La observacion de linea no puede superar 200 caracteres.');
            }
            $lineas[] = [
                'cuenta_zetti_id' => $cuentaId,
                'debe' => $debe,
                'haber' => $haber,
                'observacion' => $observacion === '' ? null : $observacion,
            ];
            $totalDebe = $this->sumarEscalados($totalDebe, $debe);
            $totalHaber = $this->sumarEscalados($totalHaber, $haber);
        }
        if ($totalDebe !== $totalHaber) {
            throw new InvalidArgumentException('El borrador debe estar balanceado: debe y haber no coinciden.');
        }

        $entradaCanonica = [
            'movimiento_id' => $movimientoId,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'nodo_zetti_id' => $nodoId,
            'fecha_contable' => $fechaContable,
            'modelo' => $modelo,
            'lineas' => $lineas,
        ];
        return $this->ejecutor->ejecutar(
            'movimiento.crear-borrador',
            $claveIdempotencia,
            $usuarioId,
            $entradaCanonica,
            [
                'accion' => 'MOVIMIENTO_CREAR_BORRADOR',
                'recurso_tipo' => 'MOVIMIENTO_EXTRACTO',
                'recurso_id' => (string) $movimientoId,
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'nodo_zetti_id' => $nodoId,
                    'fecha_contable' => $fechaContable,
                    'modelo' => $modelo,
                    'cantidad_lineas' => count($lineas),
                    'total_debe' => $totalDebe,
                    'total_haber' => $totalHaber,
                ],
            ],
            function () use ($entradaCanonica, $usuarioId, $claveIdempotencia) {
                $respuesta = $this->movimientos->crearBorrador(
                    $entradaCanonica['movimiento_id'],
                    $entradaCanonica['cuenta_bancaria_id'],
                    $entradaCanonica['inicio_periodo'],
                    $entradaCanonica['nodo_zetti_id'],
                    $entradaCanonica['fecha_contable'],
                    $entradaCanonica['modelo'],
                    $entradaCanonica['lineas'],
                    $usuarioId,
                    $claveIdempotencia
                );
                return [
                    'codigo_http' => 201,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'borrador_id' => $respuesta['borrador_id'],
                        'reserva_id' => $respuesta['reserva_id'],
                        'asociacion_id' => $respuesta['asociacion_id'],
                    ],
                ];
            }
        );
    }

    private function enteroPositivo($valor, $campo)
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        if ($entero === false || $entero <= 0) {
            throw new InvalidArgumentException($campo . ' debe ser un entero positivo.');
        }
        return (int) $entero;
    }

    private function fechaValida($valor, $campo, $primerDia)
    {
        $valor = trim((string) $valor);
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor || ($primerDia && $fecha->format('d') !== '01')) {
            throw new InvalidArgumentException($campo . ' no es una fecha valida.');
        }
        return $valor;
    }

    private function importe($valor, $campo)
    {
        $valor = str_replace(',', '.', trim((string) $valor));
        if (!preg_match('/^(0|[1-9][0-9]{0,14})(?:\.([0-9]{1,5}))?$/', $valor, $partes)) {
            throw new InvalidArgumentException($campo . ' debe ser un importe positivo con hasta 5 decimales.');
        }
        return $partes[1] . '.' . str_pad($partes[2] ?? '', 5, '0');
    }

    private function sumarEscalados($acumulado, $importe)
    {
        $a = str_replace('.', '', $acumulado);
        $b = str_replace('.', '', $importe);
        $largo = max(strlen($a), strlen($b));
        $a = str_pad($a, $largo, '0', STR_PAD_LEFT);
        $b = str_pad($b, $largo, '0', STR_PAD_LEFT);
        $resultado = '';
        $acarreo = 0;
        for ($indice = $largo - 1; $indice >= 0; $indice--) {
            $suma = (int) $a[$indice] + (int) $b[$indice] + $acarreo;
            $resultado = (string) ($suma % 10) . $resultado;
            $acarreo = intdiv($suma, 10);
        }
        if ($acarreo > 0) {
            $resultado = (string) $acarreo . $resultado;
        }
        $resultado = str_pad(ltrim($resultado, '0'), 6, '0', STR_PAD_LEFT);
        return substr($resultado, 0, -5) . '.' . substr($resultado, -5);
    }
}
