<?php
namespace AppBancos\Documentation;

final class MarkdownSeguro
{
    public static function renderizar($markdown)
    {
        $lineas = preg_split('/\r\n|\r|\n/', (string) $markdown);
        $html = [];
        $indice = [];
        $parrafo = [];
        $lista = null;
        $codigo = false;
        $numeroSeccion = 0;

        $cerrarParrafo = static function () use (&$parrafo, &$html) {
            if ($parrafo) {
                $html[] = '<p>' . self::enLinea(implode(' ', $parrafo)) . '</p>';
                $parrafo = [];
            }
        };
        $cerrarLista = static function () use (&$lista, &$html) {
            if ($lista !== null) {
                $html[] = '</' . $lista . '>';
                $lista = null;
            }
        };

        foreach ($lineas as $linea) {
            if (preg_match('/^```(?:[A-Za-z0-9_-]+)?\s*$/', $linea)) {
                $cerrarParrafo();
                $cerrarLista();
                $html[] = $codigo ? '</code></pre>' : '<pre><code>';
                $codigo = !$codigo;
                continue;
            }
            if ($codigo) {
                $html[] = htmlspecialchars($linea, ENT_QUOTES, 'UTF-8') . "\n";
                continue;
            }
            $linea = trim($linea);
            if ($linea === '') {
                $cerrarParrafo();
                $cerrarLista();
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.+)$/u', $linea, $coincidencia)) {
                $cerrarParrafo();
                $cerrarLista();
                $nivel = strlen($coincidencia[1]);
                $numeroSeccion++;
                $id = 'seccion-' . $numeroSeccion;
                $titulo = trim($coincidencia[2]);
                $html[] = '<h' . $nivel . ' id="' . $id . '">' . self::enLinea($titulo) . '</h' . $nivel . '>';
                if ($nivel === 2) {
                    $indice[] = ['id' => $id, 'titulo' => $titulo];
                }
                continue;
            }
            if (preg_match('/^(?:-\s+|\d+\.\s+)(.+)$/u', $linea, $coincidencia)) {
                $cerrarParrafo();
                $tipo = preg_match('/^\d+\./', $linea) ? 'ol' : 'ul';
                if ($lista !== $tipo) {
                    $cerrarLista();
                    $html[] = '<' . $tipo . '>';
                    $lista = $tipo;
                }
                $html[] = '<li>' . self::enLinea($coincidencia[1]) . '</li>';
                continue;
            }
            $cerrarLista();
            $parrafo[] = $linea;
        }
        $cerrarParrafo();
        $cerrarLista();
        if ($codigo) {
            $html[] = '</code></pre>';
        }

        return ['html' => implode("\n", $html), 'indice' => $indice];
    }

    private static function enLinea($texto)
    {
        $partes = preg_split('/(`[^`]+`|\*\*[^*]+\*\*)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
        $resultado = '';
        foreach ($partes as $parte) {
            if (strlen($parte) >= 2 && $parte[0] === '`' && substr($parte, -1) === '`') {
                $resultado .= '<code>' . htmlspecialchars(substr($parte, 1, -1), ENT_QUOTES, 'UTF-8') . '</code>';
            } elseif (strlen($parte) >= 4 && substr($parte, 0, 2) === '**' && substr($parte, -2) === '**') {
                $resultado .= '<strong>' . htmlspecialchars(substr($parte, 2, -2), ENT_QUOTES, 'UTF-8') . '</strong>';
            } else {
                $resultado .= htmlspecialchars($parte, ENT_QUOTES, 'UTF-8');
            }
        }
        return $resultado;
    }
}
