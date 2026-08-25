<?php
namespace GlobalApps\Core\Identidad;

final class SesionCache
{
    private const VERSION = 3;

    private $clave;
    private $ttl;

    public function __construct($aplicacion, $ttl)
    {
        $this->clave = (string) $aplicacion;
        $this->ttl = max(0, (int) $ttl);
    }

    public function obtener()
    {
        $cache = $_SESSION[$this->clave]['sesion_cache'] ?? null;
        if ($this->ttl === 0
            || !is_array($cache)
            || !isset($cache['sesion'])
            || !$cache['sesion'] instanceof Sesion
            || ($cache['version'] ?? null) !== self::VERSION
            || !isset($cache['vence'])
            || (int) $cache['vence'] < time()
        ) {
            $this->limpiar();
            return null;
        }
        return $cache['sesion'];
    }

    public function guardar(Sesion $sesion)
    {
        if ($this->ttl === 0) {
            return;
        }
        $_SESSION[$this->clave]['sesion_cache'] = [
            'version' => self::VERSION,
            'sesion' => $sesion,
            'vence' => time() + $this->ttl,
        ];
    }

    public function limpiar()
    {
        unset($_SESSION[$this->clave]['sesion_cache']);
    }
}
