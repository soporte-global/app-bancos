<?php
namespace GlobalApps\Core\Identidad;

final class Cliente
{
    public const CUENTA_CORRIENTE_NUNCA = 0;
    public const CUENTA_CORRIENTE_SIEMPRE = 1;
    public const CUENTA_CORRIENTE_PREGUNTAR = 2;

    private $id;
    private $documento;
    private $codigoEntidad;
    private $nombre;
    private $apellido;
    private $entidadAgrupadoraId;
    private $entidadAgrupadora;
    private $cuentaCorrienteGeneral;
    private $excepcionesCuentaCorriente;

    public function __construct(
        $id,
        $documento,
        $codigoEntidad,
        $nombre,
        $apellido,
        $entidadAgrupadoraId,
        $entidadAgrupadora,
        $cuentaCorrienteGeneral,
        array $excepcionesCuentaCorriente
    ) {
        $this->id = (int) $id;
        $this->documento = $documento === null ? null : (string) $documento;
        $this->codigoEntidad = $codigoEntidad === null ? null : (string) $codigoEntidad;
        $this->nombre = $nombre === null ? null : (string) $nombre;
        $this->apellido = $apellido === null ? null : (string) $apellido;
        $this->entidadAgrupadoraId = (int) $entidadAgrupadoraId;
        $this->entidadAgrupadora = (string) $entidadAgrupadora;
        $this->cuentaCorrienteGeneral = $cuentaCorrienteGeneral === null
            ? null
            : (int) $cuentaCorrienteGeneral;
        $this->excepcionesCuentaCorriente = $excepcionesCuentaCorriente;
    }

    public function id()
    {
        return $this->id;
    }

    public function documento()
    {
        return $this->documento;
    }

    public function codigoEntidad()
    {
        return $this->codigoEntidad;
    }

    public function nombre()
    {
        return $this->nombre;
    }

    public function apellido()
    {
        return $this->apellido;
    }

    public function entidadAgrupadoraId()
    {
        return $this->entidadAgrupadoraId;
    }

    public function entidadAgrupadora()
    {
        return $this->entidadAgrupadora;
    }

    public function cuentaCorrienteGeneral()
    {
        return $this->cuentaCorrienteGeneral;
    }

    public function excepcionesCuentaCorriente()
    {
        return $this->excepcionesCuentaCorriente;
    }

    public function cuentaCorriente($nodoId)
    {
        $nodoId = (int) $nodoId;
        return array_key_exists($nodoId, $this->excepcionesCuentaCorriente)
            ? $this->excepcionesCuentaCorriente[$nodoId]
            : $this->cuentaCorrienteGeneral;
    }

    public function modoCuentaCorriente($nodoId)
    {
        $codigo = $this->cuentaCorriente($nodoId);
        $modos = self::modosCuentaCorriente();
        return $codigo === null ? null : ($modos[$codigo] ?? null);
    }

    public static function modosCuentaCorriente()
    {
        return [
            self::CUENTA_CORRIENTE_NUNCA => 'nunca',
            self::CUENTA_CORRIENTE_SIEMPRE => 'siempre',
            self::CUENTA_CORRIENTE_PREGUNTAR => 'preguntar',
        ];
    }
}
