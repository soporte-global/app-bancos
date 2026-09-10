<?php
namespace AppBancos\Application;

use AppBancos\Repository\ImportacionExtractoRepository;
use InvalidArgumentException;

final class PrevisualizarImportacionExtracto
{
    private $parser;
    private $importaciones;
    private $clasificador;

    public function __construct(
        ParserExtractoDelimitado $parser,
        ImportacionExtractoRepository $importaciones,
        ClasificadorImportacionExtracto $clasificador
    )
    {
        $this->parser = $parser;
        $this->importaciones = $importaciones;
        $this->clasificador = $clasificador;
    }

    public function ejecutar(array $entrada, array $archivo, $incluirTodosLosErrores = false)
    {
        $cuentaId = trim((string) ($entrada['cuenta_bancaria_id'] ?? ''));
        $configuracionId = trim((string) ($entrada['configuracion_id'] ?? ''));
        $periodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        if (!preg_match('/^[0-9]{1,20}$/', $cuentaId)) {
            throw new InvalidArgumentException('cuenta_bancaria_id es invalido.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $configuracionId)) {
            throw new InvalidArgumentException('configuracion_id es invalido.');
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $periodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se recibio un archivo valido.');
        }
        $configuraciones = $this->importaciones->validarCuentaConfigurada($cuentaId);
        $this->importaciones->validarConfiguracionCuenta($configuracionId, $cuentaId);
        $resultado = $this->parser->analizar(
            (string) ($archivo['tmp_name'] ?? ''),
            (string) ($archivo['name'] ?? ''),
            $periodo,
            true,
            $incluirTodosLosErrores
        );
        $clasificacion = $this->clasificador->clasificar(
            $resultado['filas_normalizadas'],
            $this->importaciones->obtenerReglasClasificacion($configuracionId)
        );
        $resultado['previsualizacion'] = array_slice($clasificacion['filas'], 0, 100);
        $resultado['clasificacion'] = $clasificacion['resumen'];
        unset($resultado['filas_normalizadas']);
        $resultado['cuenta_bancaria_id'] = $cuentaId;
        $resultado['configuracion_id'] = $configuracionId;
        $resultado['inicio_periodo'] = $periodo;
        $resultado['configuraciones_disponibles'] = $configuraciones;
        return $resultado;
    }
}
