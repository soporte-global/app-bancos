<?php
namespace AppBancos\Infrastructure;

use InvalidArgumentException;

/**
 * Centraliza la elección de esquemas de BANCOS. Las lecturas ERP siempre se
 * hacen en public; en sandbox, sólo las mutaciones ERP se redirigen a global_temp.
 */
final class EsquemaBancos
{
    private $operativo;
    private $lecturaErp;
    private $escrituraErp;
    private $modo;

    private function __construct($operativo, $lecturaErp, $escrituraErp, $modo)
    {
        $this->operativo = $operativo;
        $this->lecturaErp = $lecturaErp;
        $this->escrituraErp = $escrituraErp;
        $this->modo = $modo;
    }

    public static function desdeConfiguracion(array $configuracion)
    {
        $modo = strtolower(trim((string) ($configuracion['bancos_modo_operativo'] ?? '')));
        if ($modo === '') {
            $modo = !empty($configuracion['bancos_debug']) ? 'sandbox' : 'produccion';
        }
        if (!in_array($modo, ['sandbox', 'produccion'], true)) {
            throw new InvalidArgumentException('Modo operativo BANCOS inválido.');
        }
        $sandbox = $modo === 'sandbox';
        return new self(
            $sandbox ? 'global_temp' : 'global_prod',
            'public',
            $sandbox ? 'global_temp' : 'public',
            $modo
        );
    }

    public function esSandbox()
    {
        return $this->modo === 'sandbox';
    }

    public function esDebug()
    {
        return $this->esSandbox();
    }

    public function modo()
    {
        return $this->modo;
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

    public function secuenciaEscrituraErp($secuencia)
    {
        return $this->escrituraErp . '.' . $this->validarTabla($secuencia);
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
