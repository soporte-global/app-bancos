<?php
namespace GlobalApps\Core\Diagnostics;

use Throwable;

final class DiagnosticSuite
{
    private $resultados = [];

    public function probar($nombre, callable $prueba)
    {
        $inicio = microtime(true);
        try {
            $detalle = $prueba();
            $this->agregar($nombre, 'ok', $detalle, $inicio);
        } catch (DiagnosticWarning $advertencia) {
            $this->agregar($nombre, 'warning', $advertencia->getMessage(), $inicio);
        } catch (Throwable $error) {
            $this->agregar(
                $nombre,
                'error',
                get_class($error) . ': ' . $error->getMessage(),
                $inicio
            );
        }
    }

    public function resultados()
    {
        return $this->resultados;
    }

    private function agregar($nombre, $estado, $detalle, $inicio)
    {
        $this->resultados[] = [
            'nombre' => (string) $nombre,
            'estado' => (string) $estado,
            'detalle' => is_string($detalle) ? $detalle : 'prueba completada',
            'duracion_ms' => round((microtime(true) - $inicio) * 1000, 2),
        ];
    }
}
