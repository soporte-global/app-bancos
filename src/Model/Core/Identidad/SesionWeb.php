<?php
namespace GlobalApps\Core\Identidad;

use GlobalApps\Core\Acceso\PermisoAcceso;
use GlobalApps\Core\Acceso\PermisoApp;

final class SesionWeb
{
    public static function crear(Sesion $sesion, $aplicacionId, $todasLasAplicaciones = false)
    {
        $permisoAccesoActual = $sesion->permisoAccesoDe($aplicacionId);
        $permisosAcceso = $todasLasAplicaciones
            ? $sesion->permisosAcceso()
            : ($permisoAccesoActual === null ? [] : [$permisoAccesoActual]);

        return [
            'user' => self::user($sesion->cuenta(), $permisosAcceso),
            'empleado' => self::empleado(
                $sesion->empleado(),
                $sesion->cliente(),
                $sesion->usuarioZweb()
            ),
            'nivel_acceso' => $permisoAccesoActual === null ? 2 : $permisoAccesoActual->nivel(),
        ];
    }

    private static function user(Cuenta $cuenta, array $permisosAcceso)
    {
        return [
            'id' => $cuenta->id(),
            'username' => $cuenta->username(),
            'alias' => $cuenta->alias(),
            'permisos' => array_map([self::class, 'permisoAcceso'], $permisosAcceso),
        ];
    }

    private static function empleado(
        Empleado $empleado = null,
        Cliente $cliente = null,
        UsuarioZweb $usuarioZweb = null
    )
    {
        if ($empleado === null) {
            return null;
        }

        return [
            'id' => $empleado->id(),
            'legajo' => $empleado->legajo(),
            'nombre' => $empleado->nombre(),
            'apellido' => $empleado->apellido(),
            'nombre_completo' => $empleado->nombreCompleto(),
            'cuil' => $empleado->cuil(),
            'dni' => $empleado->dni(),
            'empleado_farmacia' => $empleado->empleadoFarmacia(),
            'cliente' => self::cliente($cliente),
            'usuario_zweb' => self::usuarioZweb($usuarioZweb),
            'relaciones_laborales' => array_map(
                [self::class, 'relacionLaboral'],
                $empleado->relacionesLaborales()
            ),
        ];
    }

    private static function relacionLaboral(RelacionLaboral $relacion)
    {
        return [
            'empresa_id' => $relacion->empresaId(),
            'empresa' => $relacion->empresa(),
            'cuit' => $relacion->cuit(),
            'nodo_id' => $relacion->nodoId(),
            'liquida' => $relacion->liquida(),
            'numero_legajo_origen' => $relacion->numeroLegajoOrigen(),
            'fecha_ingreso' => $relacion->fechaIngreso(),
        ];
    }

    private static function cliente(Cliente $cliente = null)
    {
        if ($cliente === null) {
            return null;
        }

        return [
            'id' => $cliente->id(),
            'documento' => $cliente->documento(),
            'codigo_entidad' => $cliente->codigoEntidad(),
            'nombre' => $cliente->nombre(),
            'apellido' => $cliente->apellido(),
            'entidad_agrupadora' => [
                'id' => $cliente->entidadAgrupadoraId(),
                'nombre' => $cliente->entidadAgrupadora(),
            ],
            'cuenta_corriente' => [
                'general' => $cliente->cuentaCorrienteGeneral(),
                'excepciones' => (object) $cliente->excepcionesCuentaCorriente(),
                'modos' => Cliente::modosCuentaCorriente(),
            ],
        ];
    }

    private static function usuarioZweb(UsuarioZweb $usuario = null)
    {
        if ($usuario === null) {
            return null;
        }

        return [
            'id' => $usuario->id(),
            'alias' => $usuario->alias(),
            'nombre' => $usuario->nombre(),
            'mail' => $usuario->mail(),
            'nodos' => (object) $usuario->nodos(),
            'grupos' => (object) $usuario->grupos(),
        ];
    }

    private static function permisoAcceso(PermisoAcceso $permisoAcceso)
    {
        return [
            'aplicacion_id' => $permisoAcceso->aplicacionId(),
            'nombre' => $permisoAcceso->nombre(),
            'url' => $permisoAcceso->url(),
            'imagen' => $permisoAcceso->imagen(),
            'nivel' => $permisoAcceso->nivel(),
            'nombre_nivel' => $permisoAcceso->nombreNivel(),
            'permisos_internos' => array_map(
                [self::class, 'permisoApp'],
                $permisoAcceso->permisosApp()
            ),
        ];
    }

    private static function permisoApp(PermisoApp $permisoApp)
    {
        return [
            'id' => $permisoApp->id(),
            'aplicacion_id' => $permisoApp->aplicacionId(),
            'tipo' => $permisoApp->tipo(),
            'nivel' => $permisoApp->nivel(),
            'descripcion' => $permisoApp->descripcion(),
            'nombre_interno' => $permisoApp->nombreInterno(),
            'icono' => $permisoApp->icono(),
        ];
    }
}
