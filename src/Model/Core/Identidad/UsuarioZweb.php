<?php
namespace GlobalApps\Core\Identidad;

final class UsuarioZweb
{
    private $id;
    private $alias;
    private $nombre;
    private $mail;
    private $nodos;
    private $grupos;

    public function __construct($id, $alias, $nombre, $mail, array $nodos, array $grupos)
    {
        $this->id = (int) $id;
        $this->alias = (string) $alias;
        $this->nombre = (string) $nombre;
        $this->mail = $mail === null ? null : (string) $mail;
        $this->nodos = $nodos;
        $this->grupos = $grupos;
    }

    public function id()
    {
        return $this->id;
    }

    public function alias()
    {
        return $this->alias;
    }

    public function nombre()
    {
        return $this->nombre;
    }

    public function mail()
    {
        return $this->mail;
    }

    public function nodos()
    {
        return $this->nodos;
    }

    public function grupos()
    {
        return $this->grupos;
    }
}
