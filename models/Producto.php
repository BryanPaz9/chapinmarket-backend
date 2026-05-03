<?php
require_once 'BaseModel.php';

class Producto extends BaseModel
{

    public function getAll()
    {
        $sql = "SELECT * FROM productos";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            if (isset($row['IMAGENES'])) {
                if (is_object($row['IMAGENES']) && method_exists($row['IMAGENES'], 'load')) {
                    $row['IMAGENES'] = $row['IMAGENES']->load();
                } elseif (is_resource($row['IMAGENES'])) {
                    $clob = $row['IMAGENES'];
                    $row['IMAGENES'] = stream_get_contents($clob);
                }

                if (is_string($row['IMAGENES']) && !empty($row['IMAGENES'])) {
                    $cleaned = trim($row['IMAGENES']);
                    if (strpos($cleaned, '["') === 0 && substr($cleaned, -2) === '"]') {
                        preg_match('/\[\"(.*?)\"\]/', $cleaned, $matches);
                        if (isset($matches[1])) {
                            $row['IMAGENES'] = $matches[1];
                        }
                    }
                    if (substr($cleaned, 0, 1) === '[' && substr($cleaned, -1) === ']') {
                        $decoded = json_decode($cleaned, true);
                        if (is_array($decoded) && !empty($decoded) && is_string($decoded[0])) {
                            $row['IMAGENES'] = $decoded[0];
                        }
                    }
                }
            }
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM productos WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);
        if ($result && isset($result['IMAGENES'])) {
            if (is_object($result['IMAGENES']) && method_exists($result['IMAGENES'], 'load')) {
                $result['IMAGENES'] = $result['IMAGENES']->load();
            } elseif (is_resource($result['IMAGENES'])) {
                $result['IMAGENES'] = stream_get_contents($result['IMAGENES']);
            }

            if (is_string($result['IMAGENES']) && !empty($result['IMAGENES'])) {
                $cleaned = trim($result['IMAGENES']);
                if (strpos($cleaned, '["') === 0 && substr($cleaned, -2) === '"]') {
                    preg_match('/\[\"(.*?)\"\]/', $cleaned, $matches);
                    if (isset($matches[1])) {
                        $result['IMAGENES'] = $matches[1];
                    }
                } elseif (substr($cleaned, 0, 1) === '[' && substr($cleaned, -1) === ']') {
                    $decoded = json_decode($cleaned, true);
                    if (is_array($decoded) && !empty($decoded) && is_string($decoded[0])) {
                        $result['IMAGENES'] = $decoded[0];
                    }
                }
            }
        }

        return $result ?: null;
    }

    public function create($data)
    {
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

    public function update($id, $data)
    {
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

    public function delete($id)
    {
        $sql = "DELETE FROM productos WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}
