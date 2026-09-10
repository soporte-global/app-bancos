<?php
namespace AppBancos\Application;

use AppBancos\Repository\ImportacionExtractoRepository;
use InvalidArgumentException;

final class ConfirmarImportacionExtracto
{
    private const VERSION_ORIGEN = 'CSV-TSV-V1';

    private $parser;
    private $importaciones;
    private $ejecutor;
    private $clasificador;

    public function __construct(
        ParserExtractoDelimitado $parser,
        ImportacionExtractoRepository $importaciones,
        ClasificadorImportacionExtracto $clasificador,
        EjecutorComandoIdempotente $ejecutor
    ) {
        $this->parser = $parser;
        $this->importaciones = $importaciones;
        $this->clasificador = $clasificador;
        $this->ejecutor = $ejecutor;
    }

    public function ejecutar(array $entrada, array $archivo, $operadorId, $claveIdempotencia)
    {
        $cuentaId = $this->identificador($entrada['cuenta_bancaria_id'] ?? null, 'cuenta_bancaria_id');
        $configuracionId = $this->identificador($entrada['configuracion_id'] ?? null, 'configuracion_id');
        $periodo = trim((string) ($entrada['inicio_periodo'] ?? ''));
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo);
        if ($fecha === false || $fecha->format('Y-m-d') !== $periodo || $fecha->format('d') !== '01') {
            throw new InvalidArgumentException('inicio_periodo debe ser el primer dia de un mes valido.');
        }
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se recibio un archivo valido.');
        }

        $analisis = $this->parser->analizar(
            (string) ($archivo['tmp_name'] ?? ''),
            (string) ($archivo['name'] ?? ''),
            $periodo,
            true
        );
        if (!$analisis['valido']) {
            throw new InvalidArgumentException(
                'El archivo contiene errores. Corregilos y volve a previsualizar antes de confirmar.'
            );
        }
        $clasificacion = $this->clasificador->clasificar(
            $analisis['filas_normalizadas'],
            $this->importaciones->obtenerReglasClasificacion($configuracionId)
        );
        $filas = $clasificacion['filas'];
        $canonica = [
            'cuenta_bancaria_id' => $cuentaId,
            'configuracion_id' => $configuracionId,
            'inicio_periodo' => $periodo,
            'archivo' => $analisis['archivo'],
            'hash_sha256' => $analisis['hash_sha256'],
            'version_origen' => self::VERSION_ORIGEN,
        ];

        return $this->ejecutor->ejecutar(
            'importacion.confirmar',
            $claveIdempotencia,
            $operadorId,
            $canonica,
            [
                'accion' => 'IMPORTACION_CONFIRMAR',
                'recurso_tipo' => 'IMPORTACION_EXTRACTO',
                'resultado' => 'EXITOSA',
                'detalles' => [
                    'cuenta_bancaria_id' => $cuentaId,
                    'configuracion_id' => $configuracionId,
                    'inicio_periodo' => $periodo,
                    'hash_sha256' => $analisis['hash_sha256'],
                ],
            ],
            function () use ($canonica, $filas, $analisis, $clasificacion, $operadorId, $claveIdempotencia) {
                $respuesta = $this->importaciones->crearLote(
                    $canonica['configuracion_id'],
                    $canonica['cuenta_bancaria_id'],
                    $canonica['inicio_periodo'],
                    $canonica['archivo'],
                    $canonica['hash_sha256'],
                    $canonica['version_origen'],
                    $filas,
                    $operadorId,
                    $claveIdempotencia
                );
                $respuesta['clasificacion'] = $clasificacion['resumen'];
                return [
                    'codigo_http' => 201,
                    'respuesta' => $respuesta,
                    'detalles_auditoria' => [
                        'importacion_id' => $respuesta['importacion_id'],
                        'total_movimientos' => $respuesta['total_movimientos'],
                        'credito_total' => $analisis['credito_total'],
                        'debito_total' => $analisis['debito_total'],
                        'clasificacion' => $clasificacion['resumen'],
                    ],
                ];
            }
        );
    }

    private function identificador($valor, $campo)
    {
        $valor = trim((string) $valor);
        if (!preg_match('/^[0-9]{1,20}$/', $valor)) {
            throw new InvalidArgumentException($campo . ' es invalido.');
        }
        return $valor;
    }
}
