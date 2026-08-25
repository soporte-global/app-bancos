<?php
namespace GlobalApps\Core\Diagnostics;

final class RequestProfiler
{
    private static $inicio;
    private static $activos = [];
    private static $duraciones = [];
    private static $habilitado = false;

    public static function iniciar()
    {
        if (self::$inicio !== null) {
            return;
        }
        self::$inicio = microtime(true);
        register_shutdown_function([self::class, 'finalizar']);
    }

    public static function habilitar($habilitado)
    {
        self::$habilitado = (bool) $habilitado;
    }

    public static function comenzar($nombre)
    {
        self::$activos[(string) $nombre] = microtime(true);
    }

    public static function terminar($nombre)
    {
        $nombre = (string) $nombre;
        if (!isset(self::$activos[$nombre])) {
            return;
        }
        $duracion = (microtime(true) - self::$activos[$nombre]) * 1000;
        self::$duraciones[$nombre] = (self::$duraciones[$nombre] ?? 0) + $duracion;
        unset(self::$activos[$nombre]);
    }

    public static function mediciones()
    {
        $mediciones = [];
        foreach (self::$duraciones as $nombre => $duracion) {
            $mediciones[$nombre] = round($duracion, 2);
        }
        if (self::$inicio !== null) {
            $mediciones['parcial'] = round((microtime(true) - self::$inicio) * 1000, 2);
        }
        return $mediciones;
    }

    public static function finalizar()
    {
        if (!self::$habilitado || self::$inicio === null || headers_sent()) {
            return;
        }
        $mediciones = self::$duraciones;
        $mediciones['total'] = (microtime(true) - self::$inicio) * 1000;
        $metricas = [];
        foreach ($mediciones as $nombre => $duracion) {
            $nombre = preg_replace('/[^a-z0-9_-]/i', '_', $nombre);
            $metricas[] = $nombre . ';dur=' . number_format($duracion, 2, '.', '');
        }
        header('Server-Timing: ' . implode(', ', $metricas));
    }
}
