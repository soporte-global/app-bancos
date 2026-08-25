<?php
namespace GlobalApps\Core\Acceso;

use InvalidArgumentException;

final class PermisoAcceso
{
    private $aplicacionId;
    private $nombre;
    private $url;
    private $imagen;
    private $nivel;
    private $nombreNivel;
    private $permisosApp;

    public function __construct(
        $aplicacionId,
        $nombre,
        $url,
        $imagen,
        $nivel,
        $nombreNivel,
        array $permisosApp
    ) {
        foreach ($permisosApp as $permisoApp) {
            if (!$permisoApp instanceof PermisoApp) {
                throw new InvalidArgumentException('El permiso de acceso contiene un permiso de app invalido.');
            }
        }

        $this->aplicacionId = (int) $aplicacionId;
        $this->nombre = (string) $nombre;
        $this->url = $url === null ? null : (string) $url;
        $this->imagen = $imagen === null ? null : (string) $imagen;
        $this->nivel = (int) $nivel;
        $this->nombreNivel = (string) $nombreNivel;
        $this->permisosApp = array_values($permisosApp);
    }

    public function aplicacionId()
    {
        return $this->aplicacionId;
    }

    public function nombre()
    {
        return $this->nombre;
    }

    public function url()
    {
        return $this->url;
    }

    public function imagen()
    {
        return $this->imagen;
    }

    public function nivel()
    {
        return $this->nivel;
    }

    public function nombreNivel()
    {
        return $this->nombreNivel;
    }

    public function permisosApp()
    {
        return $this->permisosApp;
    }
}
