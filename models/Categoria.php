<?php
require_once 'BaseModel.php';

class Categoria extends BaseModel {

    public function getAll() {
        $sql = "SELECT * FROM categorias";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id) {
        $sql = "SELECT * FROM categorias WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function create($data) {
        $sql = "INSERT INTO categorias (nombre, padre_id)
                VALUES (:nombre, :padre_id)";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":padre_id" => $data['padre_id']
        ]);

        return true;
    }

    public function update($id, $data) {
        $sql = "UPDATE categorias
                SET nombre = :nombre,
                    padre_id = :padre_id
                WHERE id = :id";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":padre_id" => $data['padre_id'],
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id) {
        $sql = "DELETE FROM categorias WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}