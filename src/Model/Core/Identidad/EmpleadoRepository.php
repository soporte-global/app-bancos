<?php
namespace GlobalApps\Core\Identidad;

use PDO;
use RuntimeException;

final class EmpleadoRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buscarActivoPorCuenta($cuentaId)
    {
        $prepare = $this->pdo->prepare(
            file_get_contents(__DIR__ . '/sql/empleado-activo-por-cuenta.sql')
        );
        $prepare->execute([':cuenta' => (int) $cuentaId]);
        $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) > 1) {
            throw new RuntimeException('La cuenta esta asociada a mas de un empleado activo.');
        }
        return count($filas) === 1 ? $this->crearEmpleado($filas[0]) : null;
    }

    private function crearEmpleado(array $fila)
    {
        return new Empleado(
            $fila['id'],
            $fila['legajo'],
            $fila['nombre'],
            $fila['apellido'],
            $fila['cuil'],
            $fila['dni'],
            $this->valorBooleano($fila['empleado_farmacia']),
            $fila['usuario_zweb'],
            $fila['cliente'],
            $this->crearRelacionesLaborales($fila['relaciones_laborales'])
        );
    }

    private function crearRelacionesLaborales($valor)
    {
        $relaciones = $this->decodificarLista($valor);
        return array_map(function (array $relacion) {
            return new RelacionLaboral(
                $relacion['empresa_id'],
                $relacion['empresa'],
                $relacion['cuit'],
                $relacion['nodo_id'],
                $this->valorBooleano($relacion['liquida']),
                $relacion['numero_legajo_origen'],
                $relacion['fecha_ingreso']
            );
        }, $relaciones);
    }

    private function decodificarLista($valor)
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        $lista = is_array($valor) ? $valor : json_decode($valor, true);
        if (!is_array($lista)) {
            throw new RuntimeException('RRHH devolvio relaciones laborales invalidas.');
        }
        foreach ($lista as $relacion) {
            if (!is_array($relacion)) {
                throw new RuntimeException('RRHH devolvio una relacion laboral invalida.');
            }
        }
        return $lista;
    }

    private function valorBooleano($valor)
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }
}
