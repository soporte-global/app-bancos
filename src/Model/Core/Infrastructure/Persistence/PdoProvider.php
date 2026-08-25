<?php
namespace GlobalApps\Core\Infrastructure\Persistence;

use InvalidArgumentException;
use PDO;

final class PdoProvider
{
    private $configuraciones;
    private $conexiones = [];

    public function __construct(array $configuraciones)
    {
        $this->configuraciones = $configuraciones;
    }

    public function ftweb()
    {
        return $this->connection('ftweb');
    }

    public function rrhhLegacy()
    {
        return $this->connection('rrhh_legacy');
    }

    public function connection($nombre)
    {
        $nombre = (string) $nombre;
        if (isset($this->conexiones[$nombre])) {
            return $this->conexiones[$nombre];
        }
        if (!isset($this->configuraciones[$nombre]) || !is_array($this->configuraciones[$nombre])) {
            throw new InvalidArgumentException('No existe configuración para la conexión ' . $nombre . '.');
        }

        $configuracion = $this->configuraciones[$nombre];
        if (empty($configuracion['dsn'])) {
            throw new InvalidArgumentException('La conexión ' . $nombre . ' no tiene DSN.');
        }
        $options = isset($configuracion['options']) && is_array($configuracion['options'])
            ? $configuracion['options']
            : [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        $this->conexiones[$nombre] = new PDO(
            $configuracion['dsn'],
            $configuracion['user'] ?? null,
            $configuracion['password'] ?? null,
            $options
        );
        return $this->conexiones[$nombre];
    }
}
