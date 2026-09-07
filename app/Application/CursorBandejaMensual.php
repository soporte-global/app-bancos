<?php
namespace AppBancos\Application;

use InvalidArgumentException;
use RuntimeException;

final class CursorBandejaMensual
{
    private $secreto;

    public function __construct($secreto)
    {
        if (!is_string($secreto) || $secreto === '') {
            throw new InvalidArgumentException('El secreto del cursor no puede estar vacío.');
        }
        $this->secreto = $secreto;
    }

    public function codificar(array $movimiento, $cuentaBancariaId, $inicioPeriodo)
    {
        $fecha = array_key_exists('fecha_operacion', $movimiento) && $movimiento['fecha_operacion'] !== null
            ? $this->fecha($movimiento['fecha_operacion'], 'fecha_operacion')
            : null;
        $id = $this->enteroPositivo($movimiento['id'] ?? null, 'id');
        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuenta_bancaria_id');
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicio_periodo');
        $contenido = json_encode([
            'v' => 1,
            'f' => $fecha,
            'i' => $id,
            'c' => $cuentaBancariaId,
            'p' => $inicioPeriodo,
        ]);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo codificar el cursor.');
        }

        $token = rtrim(strtr(base64_encode($contenido), '+/', '-_'), '=');
        return $token . '.' . hash_hmac('sha256', $token, $this->secreto);
    }

    public function decodificar($cursor, $cuentaBancariaId, $inicioPeriodo)
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        if (!is_string($cursor) || substr_count($cursor, '.') !== 1) {
            throw new InvalidArgumentException('cursor no es válido.');
        }

        list($token, $firma) = explode('.', $cursor, 2);
        $firmaEsperada = hash_hmac('sha256', $token, $this->secreto);
        if (!hash_equals($firmaEsperada, $firma)) {
            throw new InvalidArgumentException('cursor no es válido.');
        }

        $relleno = str_repeat('=', (4 - strlen($token) % 4) % 4);
        $contenido = base64_decode(strtr($token . $relleno, '-_', '+/'), true);
        $datos = $contenido === false ? null : json_decode($contenido, true);
        if (!is_array($datos) || ($datos['v'] ?? null) !== 1) {
            throw new InvalidArgumentException('cursor no es válido.');
        }

        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuenta_bancaria_id');
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicio_periodo');
        if (($datos['c'] ?? null) !== $cuentaBancariaId || ($datos['p'] ?? null) !== $inicioPeriodo) {
            throw new InvalidArgumentException('cursor no corresponde a la cuenta y período solicitados.');
        }

        return [
            'fecha' => ($datos['f'] ?? null) === null
                ? null
                : $this->fecha($datos['f'], 'cursor.fecha'),
            'id' => $this->enteroPositivo($datos['i'] ?? null, 'cursor.id'),
        ];
    }

    private function enteroPositivo($valor, $nombre)
    {
        if (filter_var($valor, FILTER_VALIDATE_INT) === false || (int) $valor <= 0) {
            throw new InvalidArgumentException($nombre . ' debe ser un entero positivo.');
        }
        return (int) $valor;
    }

    private function fecha($valor, $nombre)
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw new InvalidArgumentException($nombre . ' debe usar el formato YYYY-MM-DD.');
        }
        return $valor;
    }
}
