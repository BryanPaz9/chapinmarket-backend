<?php
require_once 'BaseModel.php';

class Carrito extends BaseModel
{

    public function getOrCreateCart($usuarioId = null)
    {
        if ($usuarioId === null || $usuarioId === 0) {
            return $this->getAnonymousCart();
        }

        $sql = "SELECT id, usuario_id, fecha_ultima_actualizacion 
            FROM carritos 
            WHERE usuario_id = :usuario_id 
            ORDER BY id DESC 
            FETCH FIRST 1 ROWS ONLY";

        $stid = $this->execute($sql, [":usuario_id" => $usuarioId]);
        $cart = oci_fetch_assoc($stid);

        if (!$cart) {
            // 1. Obtener siguiente ID de la secuencia
            $sqlSeq = "SELECT SEQ_CARRITO.NEXTVAL AS id FROM DUAL";
            $stidSeq = oci_parse($this->conn, $sqlSeq);
            if (!oci_execute($stidSeq)) {
                $e = oci_error($stidSeq);
                throw new Exception("Error al obtener secuencia: " . $e['message']);
            }
            $rowSeq = oci_fetch_assoc($stidSeq);
            $newId = $rowSeq['ID'];

            // 2. Insertar con ID explícito
            $sqlInsert = "INSERT INTO carritos (id, usuario_id, fecha_ultima_actualizacion) 
                  VALUES (:id, :usuario_id, SYSDATE)";
            $stidInsert = oci_parse($this->conn, $sqlInsert);
            oci_bind_by_name($stidInsert, ":id", $newId);
            oci_bind_by_name($stidInsert, ":usuario_id", $usuarioId);
            if (!oci_execute($stidInsert)) {
                $e = oci_error($stidInsert);
                throw new Exception("Error al crear carrito: " . $e['message']);
            }

            return ['id' => (int)$newId, 'usuario_id' => $usuarioId];
        }

        return ['id' => (int)$cart['ID'], 'usuario_id' => $cart['USUARIO_ID']];
    }

    private function getAnonymousCart()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionId = session_id();

        $sql = "SELECT id FROM carritos 
            WHERE session_id = :session_id 
            AND usuario_id IS NULL 
            FETCH FIRST 1 ROWS ONLY";

        $stid = $this->execute($sql, [":session_id" => $sessionId]);
        $cart = oci_fetch_assoc($stid);

        if (!$cart) {
            // 1. Obtener siguiente ID de la secuencia
            $sqlSeq = "SELECT SEQ_CARRITO.NEXTVAL AS id FROM DUAL";
            $stidSeq = oci_parse($this->conn, $sqlSeq);
            if (!oci_execute($stidSeq)) {
                $e = oci_error($stidSeq);
                throw new Exception("Error al obtener secuencia: " . $e['message']);
            }
            $rowSeq = oci_fetch_assoc($stidSeq);
            $newId = $rowSeq['ID'];

            // 2. Insertar carrito anónimo con ID explícito
            $sqlInsert = "INSERT INTO carritos (id, session_id, fecha_ultima_actualizacion) 
                  VALUES (:id, :session_id, SYSDATE)";
            $stidInsert = oci_parse($this->conn, $sqlInsert);
            oci_bind_by_name($stidInsert, ":id", $newId);
            oci_bind_by_name($stidInsert, ":session_id", $sessionId);
            if (!oci_execute($stidInsert)) {
                $e = oci_error($stidInsert);
                throw new Exception("Error al crear carrito anónimo: " . $e['message']);
            }

            return ['id' => (int)$newId, 'session_id' => $sessionId];
        }

        return ['id' => (int)$cart['ID']];
    }

    public function getCartItems($carritoId)
    {
        $sql = "SELECT ci.id, ci.producto_id, ci.cantidad, ci.seleccionado,
               p.nombre, p.precio, p.stock, p.descripcion, p.imagenes
        FROM carrito_items ci
        JOIN productos p ON p.id = ci.producto_id
        WHERE ci.carrito_id = :carrito_id
        ORDER BY ci.id DESC";

        $stid = $this->execute($sql, [":carrito_id" => $carritoId]);

        $items = [];
        while ($row = oci_fetch_assoc($stid)) {
            $imagenUrl = '';

            // Procesar la imagen correctamente
            if (isset($row['IMAGENES']) && !empty($row['IMAGENES'])) {
                $imagenesRaw = $row['IMAGENES'];

                // Si es un objeto LOB (Oracle), cargar su contenido
                if (is_object($imagenesRaw) && method_exists($imagenesRaw, 'load')) {
                    $imagenContent = $imagenesRaw->load();
                    $imagenUrl = $this->extractFirstImageFromString($imagenContent);
                }
                // Si es un recurso
                elseif (is_resource($imagenesRaw)) {
                    $imagenContent = stream_get_contents($imagenesRaw);
                    $imagenUrl = $this->extractFirstImageFromString($imagenContent);
                }
                // Si es string directamente
                elseif (is_string($imagenesRaw)) {
                    $imagenUrl = $this->extractFirstImageFromString($imagenesRaw);
                }
            }

            // Si no hay imagen válida, usar una imagen por defecto
            if (empty($imagenUrl)) {
                $imagenUrl = 'https://via.placeholder.com/300x300?text=Sin+Imagen';
            }

            $items[] = [
                'id' => (int)$row['ID'],
                'productoId' => (int)$row['PRODUCTO_ID'],
                'cantidad' => (int)$row['CANTIDAD'],
                'seleccionado' => (int)$row['SELECCIONADO'] === 1,
                'producto' => [
                    'id' => (int)$row['PRODUCTO_ID'],
                    'nombre' => $row['NOMBRE'],
                    'precio' => (float)$row['PRECIO'],
                    'stock' => (int)$row['STOCK'],
                    'descripcion' => $row['DESCRIPCION'],
                    'imagen' => $imagenUrl,
                    'imagenes' => [$imagenUrl] // Añadir como array para compatibilidad
                ]
            ];
        }
        return $items;
    }

    /**
     * Extrae la primera imagen de un string que puede ser JSON o texto plano
     */
    private function extractFirstImageFromString($imageString)
    {
        if (empty($imageString) || !is_string($imageString)) {
            return '';
        }

        $cleaned = trim($imageString);

        // Si el string está vacío después de limpiar
        if (empty($cleaned)) {
            return '';
        }

        // Si es formato JSON tipo '["url"]'
        if (strpos($cleaned, '["') === 0 && substr($cleaned, -2) === '"]') {
            preg_match('/\[\"(.*?)\"\]/', $cleaned, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        // Si es un array JSON (válido)
        if (strpos($cleaned, '[') === 0 && substr($cleaned, -1) === ']') {
            $decoded = json_decode($cleaned, true);
            if (is_array($decoded) && !empty($decoded) && is_string($decoded[0])) {
                return $decoded[0];
            }
        }

        // Si es una URL directa (sin comillas ni corchetes)
        if (filter_var($cleaned, FILTER_VALIDATE_URL)) {
            return $cleaned;
        }

        // Si tiene comillas al inicio y final pero no es array JSON
        if ((strpos($cleaned, '"') === 0 && substr($cleaned, -1) === '"') ||
            (strpos($cleaned, "'") === 0 && substr($cleaned, -1) === "'")
        ) {
            $cleaned = trim($cleaned, '"\'');
            if (filter_var($cleaned, FILTER_VALIDATE_URL)) {
                return $cleaned;
            }
        }

        return '';
    }

    public function addOrUpdateItem($carritoId, $productoId, $cantidad)
    {
        // Verificar stock
        if (!$this->checkStock($productoId, $cantidad)) {
            throw new Exception("Stock insuficiente");
        }

        // Verificar si ya existe
        $sqlCheck = "SELECT id, cantidad FROM carrito_items 
                     WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $stidCheck = $this->execute($sqlCheck, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId
        ]);
        $existing = oci_fetch_assoc($stidCheck);

        if ($existing) {
            $nuevaCantidad = $existing['CANTIDAD'] + $cantidad;
            if (!$this->checkStock($productoId, $nuevaCantidad)) {
                throw new Exception("Stock insuficiente");
            }
            $sqlUpdate = "UPDATE carrito_items 
                          SET cantidad = :cantidad, seleccionado = 1
                          WHERE id = :id";
            $this->execute($sqlUpdate, [
                ":cantidad" => $nuevaCantidad,
                ":id" => $existing['ID']
            ]);
        } else {
            $sqlInsert = "INSERT INTO carrito_items (carrito_id, producto_id, cantidad, seleccionado) 
                          VALUES (:carrito_id, :producto_id, :cantidad, 1)";
            $this->execute($sqlInsert, [
                ":carrito_id" => $carritoId,
                ":producto_id" => $productoId,
                ":cantidad" => $cantidad
            ]);
        }

        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function updateItemQuantity($carritoId, $productoId, $cantidad)
    {
        if ($cantidad <= 0) {
            return $this->removeItem($carritoId, $productoId);
        }
        if (!$this->checkStock($productoId, $cantidad)) {
            throw new Exception("Stock insuficiente");
        }
        $sql = "UPDATE carrito_items 
                SET cantidad = :cantidad
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $this->execute($sql, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId,
            ":cantidad" => $cantidad
        ]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    /**
     * Disminuye la cantidad de un producto en 1 unidad
     * Si la cantidad resultante es 0, elimina el producto
     */
    public function decrementItemQuantity($carritoId, $productoId)
    {
        // Obtener la cantidad actual
        $sqlGet = "SELECT id, cantidad FROM carrito_items 
               WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $stidGet = $this->execute($sqlGet, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId
        ]);
        $existing = oci_fetch_assoc($stidGet);

        if (!$existing) {
            return true; // No existe, nada que hacer
        }

        $nuevaCantidad = $existing['CANTIDAD'] - 1;

        if ($nuevaCantidad <= 0) {
            // Eliminar el producto si la cantidad llega a 0
            return $this->removeItem($carritoId, $productoId);
        }

        // Actualizar a la nueva cantidad
        $sql = "UPDATE carrito_items 
            SET cantidad = :cantidad
            WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $this->execute($sql, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId,
            ":cantidad" => $nuevaCantidad
        ]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function updateItemSelection($carritoId, $productoId, $seleccionado)
    {
        $sql = "UPDATE carrito_items 
                SET seleccionado = :seleccionado
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $this->execute($sql, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId,
            ":seleccionado" => $seleccionado ? 1 : 0
        ]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function removeItem($carritoId, $productoId)
    {
        $sql = "DELETE FROM carrito_items 
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $this->execute($sql, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId
        ]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function clearCart($carritoId)
    {
        $sql = "DELETE FROM carrito_items WHERE carrito_id = :carrito_id";
        $this->execute($sql, [":carrito_id" => $carritoId]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function clearSelectedItems($carritoId)
    {
        $sql = "DELETE FROM carrito_items 
                WHERE carrito_id = :carrito_id AND seleccionado = 1";
        $this->execute($sql, [":carrito_id" => $carritoId]);
        $this->updateCartTimestamp($carritoId);
        return true;
    }

    public function getCartSummary($carritoId)
    {
        $sql = "SELECT 
                   COUNT(*) as total_items,
                   SUM(cantidad) as total_cantidad,
                   SUM(CASE WHEN seleccionado = 1 THEN cantidad * p.precio ELSE 0 END) as subtotal_seleccionado,
                   SUM(cantidad * p.precio) as subtotal_total
                FROM carrito_items ci
                JOIN productos p ON p.id = ci.producto_id
                WHERE ci.carrito_id = :carrito_id";
        $stid = $this->execute($sql, [":carrito_id" => $carritoId]);
        $result = oci_fetch_assoc($stid);
        return [
            'total_items' => (int)($result['TOTAL_ITEMS'] ?? 0),
            'total_cantidad' => (int)($result['TOTAL_CANTIDAD'] ?? 0),
            'subtotal_seleccionado' => (float)($result['SUBTOTAL_SELECCIONADO'] ?? 0),
            'subtotal_total' => (float)($result['SUBTOTAL_TOTAL'] ?? 0)
        ];
    }

    private function checkStock($productoId, $cantidadNecesaria)
    {
        $sql = "SELECT stock FROM productos WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $productoId]);
        $result = oci_fetch_assoc($stid);
        // La columna puede ser 'STOCK' o 'stock'
        $stockActual = $result['STOCK'] ?? $result['stock'] ?? 0;
        return $stockActual >= $cantidadNecesaria;
    }

    private function updateCartTimestamp($carritoId)
    {
        $sql = "UPDATE carritos SET fecha_ultima_actualizacion = SYSDATE WHERE id = :id";
        $this->execute($sql, [":id" => $carritoId]);
    }

    public function migrateAnonymousCart($sessionId, $usuarioId)
    {
        $sqlFind = "SELECT id FROM carritos 
                    WHERE session_id = :session_id AND usuario_id IS NULL";
        $stidFind = $this->execute($sqlFind, [":session_id" => $sessionId]);
        $anonCart = oci_fetch_assoc($stidFind);
        if (!$anonCart) return;

        $userCart = $this->getOrCreateCart($usuarioId);

        $sqlMigrate = "UPDATE carrito_items 
                       SET carrito_id = :user_cart_id
                       WHERE carrito_id = :anon_cart_id";
        $this->execute($sqlMigrate, [
            ":user_cart_id" => $userCart['id'],
            ":anon_cart_id" => $anonCart['ID']
        ]);

        $sqlDelete = "DELETE FROM carritos WHERE id = :id";
        $this->execute($sqlDelete, [":id" => $anonCart['ID']]);

        $sqlUpdate = "UPDATE carritos SET usuario_id = :usuario_id WHERE id = :id";
        $this->execute($sqlUpdate, [
            ":usuario_id" => $usuarioId,
            ":id" => $userCart['id']
        ]);
    }
}
