<?php
namespace AppBancos\Infrastructure;

use InvalidArgumentException;

/**
 * Centraliza la elección de esquemas de BANCOS. Las lecturas ERP siempre se
 * hacen en public; en debug, sólo las mutaciones ERP se redirigen a global_temp.
 */
final class EsquemaBancos
{
    private $operativo;
    private $lecturaErp;
    private $escrituraErp;
    private $debug;

    private function __construct($operativo, $lecturaErp, $escrituraErp, $debug)
    {
        $this->operativo = $operativo;
        $this->lecturaErp = $lecturaErp;
        $this->escrituraErp = $escrituraErp;
        $this->debug = $debug;
    }

    public static function desdeConfiguracion(array $configuracion)
    {
        $debug = (bool) ($configuracion['bancos_debug'] ?? false);
        return new self(
            $debug ? 'global_temp' : 'global_prod',
            'public',
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

    public function tablaLecturaErp($tabla)
    {
        return $this->lecturaErp . '.' . $this->validarTabla($tabla);
    }

    public function tablaEscrituraErp($tabla)
    {
        return $this->escrituraErp . '.' . $this->validarTabla($tabla);
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
