<?php
namespace GlobalApps\Core\Identidad;

use PDO;
use RuntimeException;

final class UsuarioZwebRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buscarPorId($usuarioId)
    {
        $prepare = $this->pdo->prepare(
            file_get_contents(__DIR__ . '/sql/usuario-zweb.sql')
        );
        $prepare->execute([':usuario' => (int) $usuarioId]);
        $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) > 1) {
            throw new RuntimeException('El id identifica mas de un usuario Zweb activo.');
        }
        if (count($filas) === 0) {
            return null;
        }

        $fila = $filas[0];
        return new UsuarioZweb(
            $fila['id'],
            $fila['alias'],
            $fila['nombre'],
            $fila['mail'],
            $this->decodificarMapa($fila['nodos'], 'nodos'),
            $this->decodificarMapa($fila['grupos'], 'grupos')
        );
    }

    private function decodificarMapa($valor, $nombre)
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        $elementos = is_array($valor) ? $valor : json_decode($valor, true);
        if (!is_array($elementos)) {
            throw new RuntimeException('Zweb devolvio una lista de ' . $nombre . ' invalida.');
        }

        $mapa = [];
        foreach ($elementos as $elemento) {
            if (!is_array($elemento)
                || !array_key_exists('id', $elemento)
                || !array_key_exists('nombre', $elemento)
            ) {
                throw new RuntimeException('Zweb devolvio un elemento de ' . $nombre . ' invalido.');
            }
            $mapa[(string) $elemento['id']] = (string) $elemento['nombre'];
        }
        return $mapa;
    }
}
