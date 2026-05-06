<?php
require_once 'BaseModel.php';

class Producto extends BaseModel
{
    /**
     * Obtiene todos los productos con sus categorías asociadas.
     */
    public function getAll()
    {
        // 1. Obtener todos los productos
        $sql = "SELECT * FROM productos";
        $stid = $this->execute($sql);

        $productos = [];
        while ($row = oci_fetch_assoc($stid)) {
            // Procesar imagen (existente)
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
            $productos[] = $row;
        }

        // 2. Obtener todas las relaciones producto-categoría
        $sqlMap = "SELECT producto_id, categoria_id FROM producto_categorias";
        $stidMap = $this->execute($sqlMap);
        $mapaCategorias = [];
        while ($rowMap = oci_fetch_assoc($stidMap)) {
            $pid = (int)$rowMap['PRODUCTO_ID'];
            $cid = (int)$rowMap['CATEGORIA_ID'];
            if (!isset($mapaCategorias[$pid])) {
                $mapaCategorias[$pid] = [];
            }
            $mapaCategorias[$pid][] = $cid;
        }

        // 3. Asignar los IDs de categoría a cada producto
        $data = [];
        foreach ($productos as $row) {
            $rowId = (int)$row['ID'];
            $row['CATEGORIAIDS'] = isset($mapaCategorias[$rowId]) ? $mapaCategorias[$rowId] : [];
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Obtiene un producto por ID, incluyendo sus categorías.
     */
    public function getById($id)
    {
        $sql = "SELECT p.*,
                       (SELECT LISTAGG(pc.categoria_id, ',') WITHIN GROUP (ORDER BY pc.categoria_id)
                        FROM producto_categorias pc
                        WHERE pc.producto_id = p.id) AS categorias
                FROM productos p
                WHERE p.id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);
        if ($result) {
            // Procesar imagen
            if (isset($result['IMAGENES'])) {
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

            $categoriasStr = $result['CATEGORIAS'] ?? '';
            unset($result['CATEGORIAS']);
            $result['CATEGORIAIDS'] = !empty($categoriasStr)
                ? array_map('intval', explode(',', $categoriasStr))
                : [];
        }

        return $result ?: null;
    }

    /**
     * Crea un producto con sus relaciones a categorías.
     */
    public function create($data)
    {
        // Insertar producto
        $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, imagenes)
                VALUES (:nombre, :descripcion, :precio, :stock, :imagenes)";
        $this->execute($sql, [
            ":nombre"      => $data['nombre'],
            ":descripcion" => $data['descripcion'],
            ":precio"      => $data['precio'],
            ":stock"       => $data['stock'],
            ":imagenes"    => $data['imagenes']
        ]);

        // Obtener el ID recién generado (asumiendo secuencia SEQ_PRODUCTO)
        $sqlId = "SELECT SEQ_PRODUCTO.CURRVAL AS new_id FROM DUAL";
        $stidId = oci_parse($this->conn, $sqlId);
        oci_execute($stidId);
        $rowId = oci_fetch_assoc($stidId);
        $newId = (int)$rowId['NEW_ID'];

        // Insertar relaciones de categorías si vienen
        if (!empty($data['categoriaIds']) && is_array($data['categoriaIds'])) {
            $sqlCat = "INSERT INTO producto_categorias (producto_id, categoria_id) VALUES (:pid, :cid)";
            foreach ($data['categoriaIds'] as $catId) {
                $stidCat = oci_parse($this->conn, $sqlCat);
                oci_bind_by_name($stidCat, ":pid", $newId);
                oci_bind_by_name($stidCat, ":cid", $catId);
                oci_execute($stidCat);
            }
        }

        return true;
    }

    /**
     * Actualiza producto y sus relaciones de categorías.
     */
    public function update($id, $data)
    {
        $sql = "UPDATE productos
                SET nombre      = :nombre,
                    descripcion = :descripcion,
                    precio      = :precio,
                    stock       = :stock,
                    imagenes    = :imagenes
                WHERE id = :id";
        $this->execute($sql, [
            ":nombre"      => $data['nombre'],
            ":descripcion" => $data['descripcion'],
            ":precio"      => $data['precio'],
            ":stock"       => $data['stock'],
            ":imagenes"    => $data['imagenes'],
            ":id"          => $id
        ]);

        // Reemplazar relaciones de categorías si se envían
        if (isset($data['categoriaIds']) && is_array($data['categoriaIds'])) {
            // Eliminar relaciones actuales
            $this->execute("DELETE FROM producto_categorias WHERE producto_id = :pid", [":pid" => $id]);
            // Insertar las nuevas
            $sqlCat = "INSERT INTO producto_categorias (producto_id, categoria_id) VALUES (:pid, :cid)";
            foreach ($data['categoriaIds'] as $catId) {
                $stidCat = oci_parse($this->conn, $sqlCat);
                oci_bind_by_name($stidCat, ":pid", $id);
                oci_bind_by_name($stidCat, ":cid", $catId);
                oci_execute($stidCat);
            }
        }

        return true;
    }

    public function delete($id)
    {

        $this->execute("DELETE FROM producto_categorias WHERE producto_id = :pid", [":pid" => $id]);
        $sql = "DELETE FROM productos WHERE id = :id";
        $this->execute($sql, [":id" => $id]);
        return true;
    }
}
