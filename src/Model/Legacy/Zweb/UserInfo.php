<?php
namespace HClasses\Zweb\Usuarios;

use Exception;
use PDO;

abstract class UserInfoFactory
{
    public $id_zweb;
    public $alias;
    public $nombre_registrado;
    public $nodos_habilitados = [];
    public $grupos_usuario = [];
    public $error;
}

/**
 * identidad y alcance de un usuario dentro de Zweb.
 */
class UserInfo extends UserInfoFactory
{
    public function __construct($alias, $con, $zweb_predeterminado = false)
    {
        try {
            $this->validarConexion($con);
            $fila = $this->buscar($alias, $zweb_predeterminado, $con);

            if (!$fila) {
                $this->error = false;
                return;
            }

            $this->id_zweb = (int) $fila['id_zweb'];
            $this->alias = $fila['alias'];
            $this->nombre_registrado = $fila['nombre_registrado'];
            $this->nodos_habilitados = $this->decodificarLista($fila['nodos_habilitados']);
            $this->grupos_usuario = $this->decodificarLista($fila['grupos_usuario']);
            $this->error = false;
        } catch (Exception $error) {
            $this->error = $error;
        }
    }

    public function encontrado()
    {
        return $this->id_zweb !== null;
    }

    private function buscar($alias, $zweb_predeterminado, $con)
    {
        $fallback = $zweb_predeterminado === false || $zweb_predeterminado === null
            ? ''
            : trim((string) $zweb_predeterminado);

        $prepare = $con->pdo->prepare(
            file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Usuarios-UserInfo.sql')
        );
        $prepare->execute([
            ':alias_filtro' => trim((string) $alias),
            ':alias_orden' => trim((string) $alias),
            ':fallback_habilitado' => $fallback === '' ? 0 : 1,
            ':fallback_alias' => $fallback,
            ':fallback_id' => $fallback,
        ]);

        return $prepare->fetch(PDO::FETCH_ASSOC);
    }

    private function decodificarLista($valor)
    {
        if (is_array($valor)) {
            return $valor;
        }
        if ($valor === null || $valor === '') {
            return [];
        }

        $lista = json_decode($valor, true);
        if (!is_array($lista)) {
            throw new Exception('Zweb devolvió una lista de permisos inválida.');
        }
        return $lista;
    }

    private function validarConexion($con)
    {
        if (!is_object($con) || !property_exists($con, 'pdo') || !($con->pdo instanceof PDO)) {
            throw new Exception('No se ha pasado una conexión válida a PostgreSQL.');
        }
    }
}
