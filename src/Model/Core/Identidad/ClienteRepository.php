<?php
namespace GlobalApps\Core\Identidad;

use PDO;
use RuntimeException;

final class ClienteRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buscarPorId($clienteId)
    {
        $prepare = $this->pdo->prepare(
            file_get_contents(__DIR__ . '/sql/cliente.sql')
        );
        $prepare->execute([':cliente' => (int) $clienteId]);
        $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) > 1) {
            throw new RuntimeException('El id identifica mas de un cliente.');
        }
        if (count($filas) === 0) {
            return null;
        }

        $fila = $filas[0];
        return new Cliente(
            $fila['id'],
            $fila['documento'],
            $fila['codigo_entidad'],
            $fila['nombre'],
            $fila['apellido'],
            $fila['entidad_agrupadora_id'],
            $fila['entidad_agrupadora'],
            $fila['cuenta_corriente_general'],
            $this->decodificarExcepciones($fila['excepciones_cuenta_corriente'])
        );
    }

    private function decodificarExcepciones($valor)
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        $excepciones = is_array($valor) ? $valor : json_decode($valor, true);
        if (!is_array($excepciones)) {
            throw new RuntimeException('Zweb devolvio excepciones de cuenta corriente invalidas.');
        }

        $porNodo = [];
        foreach ($excepciones as $excepcion) {
            if (!is_array($excepcion)
                || !isset($excepcion['nodo_id'])
                || !array_key_exists('codigo', $excepcion)
            ) {
                throw new RuntimeException('Zweb devolvio una excepcion de cuenta corriente invalida.');
            }
            $porNodo[(int) $excepcion['nodo_id']] = (int) $excepcion['codigo'];
        }
        return $porNodo;
    }
}
