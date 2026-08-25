<?php
namespace GlobalApps\Core\Acceso;

use GlobalApps\Core\Identidad\Sesion;
final class PoliticaAcceso
{
    private $aplicacionId;
    private $libre;
    private $requiereEmpleado;
    private $requiereZweb;
    private $requiereCliente;

    public function __construct($aplicacionId, array $opciones)
    {
        $this->aplicacionId = (int) $aplicacionId;
        $this->libre = (bool) ($opciones['libre'] ?? false);
        $this->requiereEmpleado = (bool) ($opciones['requiere_empleado'] ?? false);
        $this->requiereZweb = (bool) ($opciones['requiere_zweb'] ?? false);
        $this->requiereCliente = (bool) ($opciones['requiere_cliente'] ?? false);
    }

    public function validar(Sesion $sesion)
    {
        if ($this->requiereEmpleado && $sesion->empleado() === null) {
            throw new AccesoDenegadoException('El acceso a esta aplicacion requiere un empleado asociado.');
        }
        if ($this->requiereZweb && $sesion->usuarioZweb() === null) {
            throw new AccesoDenegadoException('El acceso a esta aplicacion requiere un usuario Zweb.');
        }
        if ($this->requiereCliente && $sesion->cliente() === null) {
            throw new AccesoDenegadoException('El acceso a esta aplicacion requiere un cliente asociado.');
        }
        if (!$this->libre && $this->permisoAcceso($sesion) === null) {
            throw new AccesoDenegadoException('La cuenta no tiene acceso a esta aplicacion.');
        }
    }

    public function permisoAcceso(Sesion $sesion)
    {
        return $sesion->permisoAccesoDe($this->aplicacionId);
    }

    public function permisosApp(Sesion $sesion)
    {
        return $sesion->permisosAppDe($this->aplicacionId);
    }
}
