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

    public function codificar(
        array $movimiento,
        $cuentaBancariaId,
        $inicioPeriodo,
        $pagina = null,
        $inicio = null,
        array $filtros = []
    )
    {
        $fecha = array_key_exists('fecha_operacion', $movimiento) && $movimiento['fecha_operacion'] !== null
            ? $this->fecha($movimiento['fecha_operacion'], 'fecha_operacion')
            : null;
        $id = $this->enteroPositivo($movimiento['id'] ?? null, 'id');
        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuenta_bancaria_id');
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicio_periodo');
        if (($pagina === null) !== ($inicio === null)) {
            throw new InvalidArgumentException('pagina e inicio deben informarse juntos.');
        }
        $pagina = $pagina === null ? null : $this->enteroPositivo($pagina, 'pagina');
        $inicio = $inicio === null ? null : $this->enteroPositivo($inicio, 'inicio');
        $contenido = json_encode([
            'v' => 3,
            'f' => $fecha,
            'i' => $id,
            'c' => $cuentaBancariaId,
            'p' => $inicioPeriodo,
            'n' => $pagina,
            'o' => $inicio,
            'x' => $this->firmaFiltros($filtros),
        ]);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo codificar el cursor.');
        }

        $token = rtrim(strtr(base64_encode($contenido), '+/', '-_'), '=');
        return $token . '.' . hash_hmac('sha256', $token, $this->secreto);
    }

    public function decodificar($cursor, $cuentaBancariaId, $inicioPeriodo, array $filtros = [])
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
        $version = is_array($datos) ? ($datos['v'] ?? null) : null;
        if ($version !== 1 && $version !== 2 && $version !== 3) {
            throw new InvalidArgumentException('cursor no es válido.');
        }

        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuenta_bancaria_id');
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicio_periodo');
        if (($datos['c'] ?? null) !== $cuentaBancariaId || ($datos['p'] ?? null) !== $inicioPeriodo) {
            throw new InvalidArgumentException('cursor no corresponde a la cuenta y período solicitados.');
        }
        if ($version >= 2 && (($datos['n'] ?? null) === null) !== (($datos['o'] ?? null) === null)) {
            throw new InvalidArgumentException('cursor no es válido.');
        }
        $firmaFiltros = $this->firmaFiltros($filtros);
        if (($version === 3 && !hash_equals($firmaFiltros, (string) ($datos['x'] ?? '')))
            || ($version < 3 && $firmaFiltros !== $this->firmaFiltros([]))
        ) {
            throw new InvalidArgumentException('cursor no corresponde a los filtros solicitados.');
        }

        return [
            'fecha' => ($datos['f'] ?? null) === null
                ? null
                : $this->fecha($datos['f'], 'cursor.fecha'),
            'id' => $this->enteroPositivo($datos['i'] ?? null, 'cursor.id'),
            'pagina' => $version >= 2 && ($datos['n'] ?? null) !== null
                ? $this->enteroPositivo($datos['n'], 'cursor.pagina')
                : null,
            'inicio' => $version >= 2 && ($datos['o'] ?? null) !== null
                ? $this->enteroPositivo($datos['o'], 'cursor.inicio')
                : null,
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

    private function firmaFiltros(array $filtros)
    {
        $normalizados = [
            'estado' => $filtros['estado'] ?? null,
            'responsable_id' => isset($filtros['responsable_id']) ? (int) $filtros['responsable_id'] : null,
            'asociacion' => $filtros['asociacion'] ?? null,
            'mensajes' => $filtros['mensajes'] ?? null,
        ];
        return hash('sha256', json_encode($normalizados));
    }
}
