<?php
namespace AppBancos\Application;

use InvalidArgumentException;

final class ParserExtractoDelimitado
{
    private const MAX_BYTES = 5242880;
    private const MAX_FILAS = 10000;
    private const MAX_PREVISUALIZACION = 100;
    private const MAX_ERRORES = 100;

    public function analizar(
        $ruta,
        $nombreArchivo,
        $inicioPeriodo,
        $incluirFilasNormalizadas = false,
        $incluirTodosLosErrores = false
    )
    {
        $nombreArchivo = basename(trim((string) $nombreArchivo));
        if ($nombreArchivo === '' || mb_strlen($nombreArchivo, 'UTF-8') > 255) {
            throw new InvalidArgumentException('El nombre del archivo es invalido.');
        }
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'tsv'], true)) {
            throw new InvalidArgumentException('El archivo debe tener extension .csv o .tsv.');
        }
        if (!is_file($ruta) || !is_readable($ruta)) {
            throw new InvalidArgumentException('No se pudo leer el archivo recibido.');
        }
        $tamano = filesize($ruta);
        if ($tamano === false || $tamano <= 0 || $tamano > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo debe pesar entre 1 byte y 5 MB.');
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false || !mb_check_encoding($contenido, 'UTF-8')) {
            throw new InvalidArgumentException('El archivo debe estar codificado en UTF-8.');
        }
        $hash = hash('sha256', $contenido);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $lineas = preg_split('/\R/u', $contenido);
        $cabeceraCruda = array_shift($lineas);
        if ($cabeceraCruda === null || trim($cabeceraCruda) === '') {
            throw new InvalidArgumentException('El archivo no contiene una cabecera.');
        }
        $delimitador = $this->detectarDelimitador($cabeceraCruda);
        $cabecera = $this->normalizarCabecera(str_getcsv($cabeceraCruda, $delimitador));
        foreach (['fecha_operacion', 'descripcion', 'credito', 'debito'] as $obligatoria) {
            if (!array_key_exists($obligatoria, $cabecera)) {
                throw new InvalidArgumentException('Falta la columna obligatoria ' . $obligatoria . '.');
            }
        }

        $filasValidas = [];
        $filasNormalizadas = [];
        $errores = [];
        $erroresCompletos = [];
        $cantidadDatos = 0;
        $cantidadErrores = 0;
        $creditoTotal = '0.00000';
        $debitoTotal = '0.00000';
        foreach ($lineas as $indice => $linea) {
            $numeroFila = $indice + 2;
            if (trim($linea) === '') {
                continue;
            }
            $cantidadDatos++;
            if ($cantidadDatos > self::MAX_FILAS) {
                throw new InvalidArgumentException('El archivo supera el maximo de 10000 movimientos.');
            }
            try {
                $valores = str_getcsv($linea, $delimitador);
                if (count($valores) !== count($cabecera)) {
                    throw new InvalidArgumentException('La cantidad de columnas no coincide con la cabecera.');
                }
                $fila = [];
                foreach ($cabecera as $campo => $posicion) {
                    $fila[$campo] = trim((string) $valores[$posicion]);
                }
                $fecha = $this->fecha($fila['fecha_operacion'], $inicioPeriodo);
                $credito = $this->importe($fila['credito'], 'credito');
                $debito = $this->importe($fila['debito'], 'debito');
                if (($credito !== '0.00000') === ($debito !== '0.00000')) {
                    throw new InvalidArgumentException('Debe existir un importe positivo solo en credito o debito.');
                }
                $descripcion = trim($fila['descripcion']);
                if ($descripcion === '' || mb_strlen($descripcion, 'UTF-8') > 500) {
                    throw new InvalidArgumentException('descripcion debe contener entre 1 y 500 caracteres.');
                }
                $referencia = $this->textoOpcional($fila['referencia'] ?? '', 200, 'referencia');
                $codigo = $this->textoOpcional($fila['codigo_extracto'] ?? '', 60, 'codigo_extracto');
                $normalizada = [
                    'numero_fila_origen' => $numeroFila,
                    'fecha_operacion' => $fecha,
                    'referencia' => $referencia,
                    'descripcion' => $descripcion,
                    'codigo_extracto' => $codigo,
                    'credito' => $credito,
                    'debito' => $debito,
                ];
                if (count($filasValidas) < self::MAX_PREVISUALIZACION) {
                    $filasValidas[] = $normalizada;
                }
                if ($incluirFilasNormalizadas) {
                    $filasNormalizadas[] = $normalizada;
                }
                $creditoTotal = $this->sumar($creditoTotal, $credito);
                $debitoTotal = $this->sumar($debitoTotal, $debito);
            } catch (InvalidArgumentException $error) {
                $cantidadErrores++;
                $detalleError = ['fila' => $numeroFila, 'mensaje' => $error->getMessage()];
                if (count($errores) < self::MAX_ERRORES) {
                    $errores[] = $detalleError;
                }
                if ($incluirTodosLosErrores) {
                    $erroresCompletos[] = $detalleError;
                }
            }
        }
        if ($cantidadDatos === 0) {
            throw new InvalidArgumentException('El archivo no contiene movimientos.');
        }

        $resultado = [
            'archivo' => $nombreArchivo,
            'hash_sha256' => $hash,
            'delimitador' => $delimitador === "\t" ? 'TAB' : $delimitador,
            'total_filas' => $cantidadDatos,
            'filas_validas' => $cantidadDatos - $cantidadErrores,
            'total_errores' => $cantidadErrores,
            'errores_truncados' => $cantidadErrores > self::MAX_ERRORES,
            'credito_total' => $creditoTotal,
            'debito_total' => $debitoTotal,
            'previsualizacion' => $filasValidas,
            'errores' => $errores,
            'valido' => $cantidadErrores === 0,
        ];
        if ($incluirFilasNormalizadas) {
            $resultado['filas_normalizadas'] = $filasNormalizadas;
        }
        if ($incluirTodosLosErrores) {
            $resultado['errores_completos'] = $erroresCompletos;
        }
        return $resultado;
    }

    private function detectarDelimitador($linea)
    {
        $mejor = null;
        $cantidad = 1;
        foreach (["\t", ';', ','] as $candidato) {
            $actual = count(str_getcsv($linea, $candidato));
            if ($actual > $cantidad) {
                $mejor = $candidato;
                $cantidad = $actual;
            }
        }
        if ($mejor === null) {
            throw new InvalidArgumentException('No se pudo detectar el delimitador del archivo.');
        }
        return $mejor;
    }

    private function normalizarCabecera(array $columnas)
    {
        $aliases = [
            'fecha' => 'fecha_operacion', 'fecha_operacion' => 'fecha_operacion',
            'referencia' => 'referencia', 'comprobante' => 'referencia',
            'descripcion' => 'descripcion', 'observacion' => 'descripcion', 'detalle' => 'descripcion',
            'credito' => 'credito', 'creditos' => 'credito',
            'debito' => 'debito', 'debitos' => 'debito',
            'codigo' => 'codigo_extracto', 'codigo_extracto' => 'codigo_extracto',
        ];
        $resultado = [];
        foreach ($columnas as $posicion => $columna) {
            $clave = mb_strtolower(trim((string) $columna), 'UTF-8');
            $clave = strtr($clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
            $clave = preg_replace('/[^a-z0-9]+/', '_', $clave);
            $clave = trim($clave, '_');
            if (!isset($aliases[$clave])) {
                throw new InvalidArgumentException('La columna ' . ($clave === '' ? '(vacia)' : $clave) . ' no esta soportada.');
            }
            $canonica = $aliases[$clave];
            if (isset($resultado[$canonica])) {
                throw new InvalidArgumentException('La columna ' . $canonica . ' esta repetida.');
            }
            $resultado[$canonica] = $posicion;
        }
        return $resultado;
    }

    private function fecha($valor, $inicioPeriodo)
    {
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat($formato, $valor);
            $esperado = substr($formato, 1);
            if ($fecha !== false && $fecha->format($esperado) === $valor) {
                if ($fecha->format('Y-m-01') !== $inicioPeriodo) {
                    throw new InvalidArgumentException('La fecha no pertenece al periodo seleccionado.');
                }
                return $fecha->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('fecha_operacion no tiene un formato valido.');
    }

    private function importe($valor, $campo)
    {
        $valor = str_replace([' ', "\u{00A0}"], '', trim((string) $valor));
        if ($valor === '') {
            return '0.00000';
        }
        if (preg_match('/^[0-9]+(?:[.,][0-9]{1,2})?$/', $valor)) {
            $normalizado = str_replace(',', '.', $valor);
        } elseif (preg_match('/^[0-9]{1,3}(?:\.[0-9]{3})+(?:,[0-9]{1,2})?$/', $valor)) {
            $normalizado = str_replace(',', '.', str_replace('.', '', $valor));
        } elseif (preg_match('/^[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?$/', $valor)) {
            $normalizado = str_replace(',', '', $valor);
        } else {
            throw new InvalidArgumentException($campo . ' debe ser positivo y tener hasta 2 decimales.');
        }
        list($entero, $decimal) = array_pad(explode('.', $normalizado, 2), 2, '');
        $entero = ltrim($entero, '0');
        $entero = $entero === '' ? '0' : $entero;
        if (strlen($entero) > 15) {
            throw new InvalidArgumentException($campo . ' supera el importe maximo admitido.');
        }
        return $entero . '.' . str_pad($decimal, 5, '0');
    }

    private function textoOpcional($valor, $maximo, $campo)
    {
        $valor = trim((string) $valor);
        if (mb_strlen($valor, 'UTF-8') > $maximo) {
            throw new InvalidArgumentException($campo . ' no puede superar ' . $maximo . ' caracteres.');
        }
        return $valor === '' ? null : $valor;
    }

    private function sumar($a, $b)
    {
        $ai = str_replace('.', '', $a);
        $bi = str_replace('.', '', $b);
        $largo = max(strlen($ai), strlen($bi));
        $ai = str_pad($ai, $largo, '0', STR_PAD_LEFT);
        $bi = str_pad($bi, $largo, '0', STR_PAD_LEFT);
        $resultado = '';
        $acarreo = 0;
        for ($i = $largo - 1; $i >= 0; $i--) {
            $suma = (int) $ai[$i] + (int) $bi[$i] + $acarreo;
            $resultado = ($suma % 10) . $resultado;
            $acarreo = intdiv($suma, 10);
        }
        if ($acarreo) {
            $resultado = $acarreo . $resultado;
        }
        $resultado = str_pad(ltrim($resultado, '0'), 6, '0', STR_PAD_LEFT);
        return substr($resultado, 0, -5) . '.' . substr($resultado, -5);
    }
}
