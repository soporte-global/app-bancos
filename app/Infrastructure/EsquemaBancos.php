<?php
namespace AppBancos\Infrastructure;

use InvalidArgumentException;

/**
 * Centraliza la elección de esquemas de BANCOS. Los repositorios nunca deben
 * interpolar `public` o `global_prod` por su cuenta.
 */
final class EsquemaBancos
{
    private $operativo;
    private $zetti;
    private $debug;

    private function __construct($operativo, $zetti, $debug)
    {
        $this->operativo = $operativo;
        $this->zetti = $zetti;
        $this->debug = $debug;
    }

    public static function desdeConfiguracion(array $configuracion)
    {
        $debug = (bool) ($configuracion['bancos_debug'] ?? false);
        return new self(
            $debug ? 'global_temp' : 'global_prod',
            $debug ? 'global_temp' : 'public',
            $debug
        );
    }

    public function esDebug()
    {
        return $this->debug;
    }

    public function tablaBancos($tabla)
    {
        $tabla = $this->validarTabla($tabla);
        if (strpos($tabla, 'bancos_') !== 0) {
            throw new InvalidArgumentException('La tabla operativa debe comenzar con bancos_.');
        }
        return $this->operativo . '.' . $tabla;
    }

    public function tablaZetti($tabla)
    {
        return $this->zetti . '.' . $this->validarTabla($tabla);
    }

    private function validarTabla($tabla)
    {
        $tabla = (string) $tabla;
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $tabla)) {
            throw new InvalidArgumentException('Nombre de tabla inválido.');
        }
        return $tabla;
    }
}
