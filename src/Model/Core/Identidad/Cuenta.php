<?php
namespace GlobalApps\Core\Identidad;

use InvalidArgumentException;

final class Cuenta
{
    private $id;
    private $username;
    private $alias;

    public function __construct($id, $username, $alias)
    {
        $id = (int) $id;
        $username = trim((string) $username);
        $alias = trim((string) $alias);
        if ($id <= 0 || $username === '' || $alias === '') {
            throw new InvalidArgumentException('La cuenta no tiene una identidad valida.');
        }

        $this->id = $id;
        $this->username = $username;
        $this->alias = $alias;
    }

    public function id()
    {
        return $this->id;
    }

    public function username()
    {
        return $this->username;
    }

    public function alias()
    {
        return $this->alias;
    }
}
