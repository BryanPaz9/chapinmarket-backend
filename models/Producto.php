<?php
require_once 'BaseModel.php';

class Producto extends BaseModel {

    public function getAll() {
        $sql = "SELECT * FROM productos";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
        if (isset($row['IMAGENES']) && is_object($row['IMAGENES'])) {
            $row['IMAGENES'] = $row['IMAGENES']->load();
        }
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id) {
        $sql = "SELECT * FROM productos WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);
        if ($result && isset($result['IMAGENES']) && is_object($result['IMAGENES'])) {
            $result['IMAGENES'] = $result['IMAGENES']->load();
        }

        return $result ?: null; // 👈 aquí
    }

    public function create($data) {
        $sql = "INSERT INTO productos (nombre, descripcion,precio,stock,imagenes)
                VALUES (:nombre, :descripcion,:precio, :stock,:imagenes)";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":descripcion" => $data['descripcion'],
            ":precio" => $data['precio'],
            ":stock" => $data['stock'],
            ":imagenes" => $data['imagenes']
        ]);

        return true;
    }

    public function update($id, $data) {
        $sql = "UPDATE productos
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    precio = :precio,
                    stock = :stock,
                    imagenes = :imagenes
                WHERE id = :id";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":descripcion" => $data['descripcion'],
            ":precio" => $data['precio'],
            ":stock" => $data['stock'],
            ":imagenes" => $data['imagenes'],
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id) {
        $sql = "DELETE FROM productos WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}