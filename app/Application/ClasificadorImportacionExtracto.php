<?php
namespace AppBancos\Application;

final class ClasificadorImportacionExtracto
{
    public function clasificar(array $filas, array $reglas)
    {
        $indice = [];
        foreach ($reglas as $regla) {
            $codigo = $this->normalizarCodigo($regla['codigo_extracto'] ?? null);
            if ($codigo === null) {
                continue;
            }
            $sentido = (string) ($regla['sentido'] ?? '');
            if (!isset($indice[$codigo])) {
                $indice[$codigo] = [];
            }
            $indice[$codigo][] = [
                'subtipo_valor_zetti_id' => (int) $regla['subtipo_valor_zetti_id'],
                'sentido' => $sentido,
                'validar_automaticamente' => $this->booleano($regla['validar_automaticamente'] ?? false),
            ];
        }

        $resumen = [
            'univocas' => 0,
            'multiples' => 0,
            'sin_regla' => 0,
            'sin_codigo' => 0,
            'habilitan_validacion_automatica' => 0,
        ];
        foreach ($filas as &$fila) {
            $codigo = $this->normalizarCodigo($fila['codigo_extracto'] ?? null);
            if ($codigo === null) {
                $fila['clasificacion'] = 'SIN_CODIGO';
                $fila['subtipos_candidatos'] = [];
                $fila['subtipo_valor_zetti_id'] = null;
                $fila['habilita_validacion_automatica'] = false;
                $resumen['sin_codigo']++;
                continue;
            }
            $sentido = $fila['credito'] !== '0.00000' ? 'C' : 'D';
            $candidatos = [];
            $validarAutomaticamente = false;
            foreach ($indice[$codigo] ?? [] as $regla) {
                if ($regla['sentido'] !== 'A' && $regla['sentido'] !== $sentido) {
                    continue;
                }
                $candidatos[$regla['subtipo_valor_zetti_id']] = true;
                $validarAutomaticamente = $validarAutomaticamente || $regla['validar_automaticamente'];
            }
            $ids = array_keys($candidatos);
            sort($ids, SORT_NUMERIC);
            $fila['subtipos_candidatos'] = $ids;
            $fila['habilita_validacion_automatica'] = $validarAutomaticamente;
            if (count($ids) === 0) {
                $fila['clasificacion'] = 'SIN_REGLA';
                $fila['subtipo_valor_zetti_id'] = null;
                $resumen['sin_regla']++;
            } elseif (count($ids) === 1) {
                $fila['clasificacion'] = 'UNIVOCA';
                $fila['subtipo_valor_zetti_id'] = $ids[0];
                $resumen['univocas']++;
            } else {
                $fila['clasificacion'] = 'MULTIPLE';
                $fila['subtipo_valor_zetti_id'] = null;
                $resumen['multiples']++;
            }
            if ($validarAutomaticamente) {
                $resumen['habilitan_validacion_automatica']++;
            }
        }
        unset($fila);

        return ['filas' => $filas, 'resumen' => $resumen];
    }

    private function normalizarCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        return $codigo === '' ? null : mb_strtolower($codigo, 'UTF-8');
    }

    private function booleano($valor)
    {
        return in_array($valor, [true, 1, '1', 't'], true);
    }
}
