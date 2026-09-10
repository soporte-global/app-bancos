<?php
namespace AppBancos\Application;

use InvalidArgumentException;
use RuntimeException;

final class GenerarReporteErroresImportacion
{
    private $previsualizacion;

    public function __construct(PrevisualizarImportacionExtracto $previsualizacion)
    {
        $this->previsualizacion = $previsualizacion;
    }

    public function ejecutar(array $entrada, array $archivo)
    {
        $resultado = $this->previsualizacion->ejecutar($entrada, $archivo, true);
        if ((int) $resultado['total_errores'] === 0) {
            throw new InvalidArgumentException('El archivo no contiene errores por fila para descargar.');
        }

        $flujo = fopen('php://temp', 'w+b');
        if ($flujo === false) {
            throw new RuntimeException('No se pudo crear el reporte de errores.');
        }
        fwrite($flujo, "\xEF\xBB\xBF");
        fputcsv($flujo, ['fila', 'error'], ';');
        foreach ($resultado['errores_completos'] as $error) {
            fputcsv($flujo, [(int) $error['fila'], (string) $error['mensaje']], ';');
        }
        rewind($flujo);
        $contenido = stream_get_contents($flujo);
        fclose($flujo);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el reporte de errores.');
        }

        return [
            'nombre_archivo' => $this->nombreReporte($resultado['archivo']),
            'contenido' => $contenido,
            'total_errores' => (int) $resultado['total_errores'],
            'hash_sha256' => $resultado['hash_sha256'],
        ];
    }

    private function nombreReporte($nombreOriginal)
    {
        $base = pathinfo((string) $nombreOriginal, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
        $base = trim((string) $base, '.-_');
        if ($base === '') {
            $base = 'importacion';
        }
        return substr($base, 0, 180) . '-errores.csv';
    }
}
