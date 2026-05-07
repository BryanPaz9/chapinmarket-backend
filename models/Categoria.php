<?php
require_once 'BaseModel.php';

class Categoria extends BaseModel
{

    public function getAll()
    {
        $sql = "SELECT * FROM categorias ORDER BY NVL(padre_id, 0), nombre";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM categorias WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function create($data)
    {
        $sql = "INSERT INTO categorias (nombre, padre_id, icono)
                VALUES (:nombre, :padre_id, :icono)";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":padre_id" => $data['padreId'] ?? $data['padre_id'] ?? null,
            ":icono" => $data['icono'] ?? null
        ]);

        return true;
    }

    public function update($id, $data)
    {
        $sql = "UPDATE categorias
                SET nombre = :nombre,
                    padre_id = :padre_id,
                    icono = :icono
                WHERE id = :id";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":padre_id" => $data['padreId'] ?? $data['padre_id'] ?? null,
            ":icono" => $data['icono'] ?? null,
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id)
    {
        $sql = "DELETE FROM categorias WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }

    public function getRandomCategories($limit = 6)
    {
        $sql = "SELECT id, nombre, icono, padre_id
                FROM (
                    SELECT id, nombre, icono, padre_id
                    FROM categorias
                    ORDER BY DBMS_RANDOM.VALUE
                )
                WHERE ROWNUM <= :limit";

        $stid = $this->execute($sql, [":limit" => $limit]);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = [
                'id'      => (int)$row['ID'],
                'nombre'  => $row['NOMBRE'],
                'icono'   => $row['ICONO'] ?? null,
                'padreId' => isset($row['PADRE_ID']) ? (int)$row['PADRE_ID'] : null
            ];
        }
        return $data;
    }
}
