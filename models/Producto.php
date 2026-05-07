<?php
require_once 'BaseModel.php';

class Producto extends BaseModel
{
    private function cargarImagen($valor)
    {
        if (is_object($valor) && method_exists($valor, 'load')) {
            $valor = $valor->load();
        } elseif (is_resource($valor)) {
            $valor = stream_get_contents($valor);
        }

        if (is_string($valor) && !empty($valor)) {
            $cleaned = trim($valor);
            if (strpos($cleaned, '["') === 0 && substr($cleaned, -2) === '"]') {
                preg_match('/\[\"(.*?)\"\]/', $cleaned, $matches);
                if (isset($matches[1])) {
                    return $matches[1];
                }
            }

            if (substr($cleaned, 0, 1) === '[' && substr($cleaned, -1) === ']') {
                $decoded = json_decode($cleaned, true);
                if (is_array($decoded) && !empty($decoded) && is_string($decoded[0])) {
                    return $decoded[0];
                }
            }
        }

        return $valor;
    }

    private function prepararImagenes($imagenes)
    {
        if (is_array($imagenes)) {
            return json_encode(array_values($imagenes), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $imagenes ?? null;
    }

    private function normalizarIds($ids)
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalizados = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $normalizados, true)) {
                $normalizados[] = $id;
            }
        }

        return $normalizados;
    }

    private function obtenerMapaRelacion($tabla, $columna)
    {
        $sql = "SELECT producto_id, $columna FROM $tabla";
        $stid = $this->execute($sql);
        $mapa = [];

        while ($row = oci_fetch_assoc($stid)) {
            $pid = (int)$row['PRODUCTO_ID'];
            $id = (int)$row[strtoupper($columna)];
            if (!isset($mapa[$pid])) {
                $mapa[$pid] = [];
            }
            $mapa[$pid][] = $id;
        }

        return $mapa;
    }

    private function reemplazarRelaciones($productoId, $tabla, $columna, $ids)
    {
        $this->execute("DELETE FROM $tabla WHERE producto_id = :pid", [":pid" => $productoId]);

        $ids = $this->normalizarIds($ids);
        if (empty($ids)) {
            return;
        }

        $sql = "INSERT INTO $tabla (producto_id, $columna) VALUES (:pid, :rid)";
        foreach ($ids as $relacionId) {
            $stid = oci_parse($this->conn, $sql);
            oci_bind_by_name($stid, ":pid", $productoId);
            oci_bind_by_name($stid, ":rid", $relacionId);
            oci_execute($stid);
        }
    }

    public function getAll()
    {
        $sql = "SELECT * FROM productos ORDER BY id";
        $stid = $this->execute($sql);

        $productos = [];
        while ($row = oci_fetch_assoc($stid)) {
            if (isset($row['IMAGENES'])) {
                $row['IMAGENES'] = $this->cargarImagen($row['IMAGENES']);
            }
            $productos[] = $row;
        }

        $mapaCategorias = $this->obtenerMapaRelacion('producto_categorias', 'categoria_id');
        $mapaTemporadas = $this->obtenerMapaRelacion('producto_temporadas', 'temporada_id');

        $data = [];
        foreach ($productos as $row) {
            $rowId = (int)$row['ID'];
            $row['CATEGORIAIDS'] = $mapaCategorias[$rowId] ?? [];
            $row['TEMPORADAIDS'] = $mapaTemporadas[$rowId] ?? [];
            $data[] = $row;
        }

        return $data;
    }

    public function getById($id)
    {
        $sql = "SELECT p.*,
                       (SELECT LISTAGG(pc.categoria_id, ',') WITHIN GROUP (ORDER BY pc.categoria_id)
                        FROM producto_categorias pc
                        WHERE pc.producto_id = p.id) AS categorias,
                       (SELECT LISTAGG(pt.temporada_id, ',') WITHIN GROUP (ORDER BY pt.temporada_id)
                        FROM producto_temporadas pt
                        WHERE pt.producto_id = p.id) AS temporadas
                FROM productos p
                WHERE p.id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);
        if ($result) {
            if (isset($result['IMAGENES'])) {
                $result['IMAGENES'] = $this->cargarImagen($result['IMAGENES']);
            }

            $categoriasStr = $result['CATEGORIAS'] ?? '';
            $temporadasStr = $result['TEMPORADAS'] ?? '';
            unset($result['CATEGORIAS'], $result['TEMPORADAS']);

            $result['CATEGORIAIDS'] = !empty($categoriasStr)
                ? array_map('intval', explode(',', $categoriasStr))
                : [];
            $result['TEMPORADAIDS'] = !empty($temporadasStr)
                ? array_map('intval', explode(',', $temporadasStr))
                : [];
        }

        return $result ?: null;
    }

    public function create($data)
    {
        $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, imagenes)
                VALUES (:nombre, :descripcion, :precio, :stock, :imagenes)";
        $this->execute($sql, [
            ":nombre"      => $data['nombre'],
            ":descripcion" => $data['descripcion'] ?? null,
            ":precio"      => $data['precio'],
            ":stock"       => $data['stock'],
            ":imagenes"    => $this->prepararImagenes($data['imagenes'] ?? null)
        ]);

        $sqlId = "SELECT seq_producto.CURRVAL AS new_id FROM DUAL";
        $stidId = oci_parse($this->conn, $sqlId);
        oci_execute($stidId);
        $rowId = oci_fetch_assoc($stidId);
        $newId = (int)$rowId['NEW_ID'];

        $this->reemplazarRelaciones($newId, 'producto_categorias', 'categoria_id', $data['categoriaIds'] ?? []);
        $this->reemplazarRelaciones($newId, 'producto_temporadas', 'temporada_id', $data['temporadaIds'] ?? []);

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
            ":nombre"      => $data['nombre'],
            ":descripcion" => $data['descripcion'] ?? null,
            ":precio"      => $data['precio'],
            ":stock"       => $data['stock'],
            ":imagenes"    => $this->prepararImagenes($data['imagenes'] ?? null),
            ":id"          => $id
        ]);

        if (isset($data['categoriaIds']) && is_array($data['categoriaIds'])) {
            $this->reemplazarRelaciones($id, 'producto_categorias', 'categoria_id', $data['categoriaIds']);
        }

        if (isset($data['temporadaIds']) && is_array($data['temporadaIds'])) {
            $this->reemplazarRelaciones($id, 'producto_temporadas', 'temporada_id', $data['temporadaIds']);
        }

        return true;
    }

    public function delete($id)
    {
        $this->execute("DELETE FROM producto_categorias WHERE producto_id = :pid", [":pid" => $id]);
        $this->execute("DELETE FROM producto_temporadas WHERE producto_id = :pid", [":pid" => $id]);
        $sql = "DELETE FROM productos WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}
