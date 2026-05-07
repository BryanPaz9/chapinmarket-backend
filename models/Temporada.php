<?php
require_once 'BaseModel.php';

class Temporada extends BaseModel
{
    public function getAll()
    {
        $sql = "SELECT id,
                       nombre,
                       TO_CHAR(fecha_inicio, 'YYYY-MM-DD') AS fecha_inicio,
                       TO_CHAR(fecha_fin, 'YYYY-MM-DD') AS fecha_fin,
                       descripcion
                FROM temporadas
                ORDER BY fecha_inicio DESC, id DESC";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }

        return $data;
    }

    public function getById($id)
    {
        $sql = "SELECT id,
                       nombre,
                       TO_CHAR(fecha_inicio, 'YYYY-MM-DD') AS fecha_inicio,
                       TO_CHAR(fecha_fin, 'YYYY-MM-DD') AS fecha_fin,
                       descripcion
                FROM temporadas
                WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function create($data)
    {
        $sql = "INSERT INTO temporadas (nombre, fecha_inicio, fecha_fin, descripcion)
                VALUES (:nombre, TO_DATE(:fecha_inicio, 'YYYY-MM-DD'), TO_DATE(:fecha_fin, 'YYYY-MM-DD'), :descripcion)";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":fecha_inicio" => $data['fechaInicio'] ?? $data['fecha_inicio'],
            ":fecha_fin" => $data['fechaFin'] ?? $data['fecha_fin'],
            ":descripcion" => $data['descripcion'] ?? null
        ]);

        return true;
    }

    public function update($id, $data)
    {
        $sql = "UPDATE temporadas
                SET nombre = :nombre,
                    fecha_inicio = TO_DATE(:fecha_inicio, 'YYYY-MM-DD'),
                    fecha_fin = TO_DATE(:fecha_fin, 'YYYY-MM-DD'),
                    descripcion = :descripcion
                WHERE id = :id";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":fecha_inicio" => $data['fechaInicio'] ?? $data['fecha_inicio'],
            ":fecha_fin" => $data['fechaFin'] ?? $data['fecha_fin'],
            ":descripcion" => $data['descripcion'] ?? null,
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id)
    {
        $this->execute("DELETE FROM producto_temporadas WHERE temporada_id = :id", [":id" => $id]);
        $sql = "DELETE FROM temporadas WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}
