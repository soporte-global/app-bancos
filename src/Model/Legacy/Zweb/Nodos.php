<?php
namespace HClasses\Zweb\Nodos;

use Exception;

/**
 * detalle liviano de un nodo; no abre conexiones ni decide cómo buscarlo.
 */
class Nodo
{
    public $id;
    public $codigo_jerarquico;
    public $nombre;
    public $hash;
    public $error;

    public function __construct($datos)
    {
        try {
            if (is_object($datos)) {
                $datos = get_object_vars($datos);
            }
            if (!is_array($datos)) {
                throw new Exception('Los datos proporcionados para el nodo no son válidos.');
            }
            foreach (['id', 'nombre', 'codigo_jerarquico'] as $campo) {
                if (!array_key_exists($campo, $datos)) {
                    throw new Exception('Falta el campo requerido: ' . $campo . '.');
                }
            }

            // hash no pertenece a public.nodo, pero se conserva para los listados que lo agregan
            $this->id = (int) $datos['id'];
            $this->nombre = $datos['nombre'];
            $this->codigo_jerarquico = (string) $datos['codigo_jerarquico'];
            $this->hash = $datos['hash'] ?? null;
            $this->error = false;
        } catch (Exception $error) {
            $this->error = $error;
        }
    }
}
