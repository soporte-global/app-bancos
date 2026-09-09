<?php
namespace AppBancos\Security;

use AppBancos\Http\ApiException;
use GlobalApps\Core\Identidad\Sesion;

final class AutorizadorAccion
{
    private $aplicacionId;

    public function __construct($aplicacionId)
    {
        $this->aplicacionId = (int) $aplicacionId;
    }

    public function exigir(Sesion $sesion, $permisoInterno)
    {
        $acceso = $sesion->permisoAccesoDe($this->aplicacionId);
        if ($acceso === null) {
            throw new ApiException(403, 'ACCESO_DENEGADO', 'La cuenta no tiene acceso a la aplicacion.');
        }

        // El nivel 1 del Hub representa al administrador de la aplicacion.
        if ($acceso->nivel() === 1) {
            return;
        }

        $permisoInterno = trim((string) $permisoInterno);
        foreach ($acceso->permisosApp() as $permiso) {
            if ($permisoInterno !== '' && $permiso->nombreInterno() === $permisoInterno) {
                return;
            }
        }

        throw new ApiException(403, 'PERMISO_INSUFICIENTE', 'La cuenta no tiene permiso para esta accion.');
    }
}
