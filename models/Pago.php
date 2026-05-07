<?php
require_once 'BaseModel.php';

class Pago extends BaseModel
{
    public function procesarPagoCarrito($data)
    {
        $sql = "BEGIN
                    PROCESAR_PAGO_CARRITO(
                        p_usuario_id      => :p_usuario_id,
                        p_carrito_id      => :p_carrito_id,
                        p_direccion_id    => :p_direccion_id,
                        p_titular         => :p_titular,
                        p_numero_tarjeta  => :p_numero_tarjeta,
                        p_vencimiento     => :p_vencimiento,
                        p_pedido_id       => :p_pedido_id
                    );
                END;";

        $stid = oci_parse($this->conn, $sql);
        if (!$stid) {
            $e = oci_error($this->conn);
            throw new Exception("Error al preparar el pago: " . ($e['message'] ?? 'Error desconocido'));
        }

        $usuarioId = (int)$data['usuario_id'];
        $carritoId = (int)$data['carrito_id'];
        $direccionId = (int)$data['direccion_id'];
        $titular = $data['titular'];
        $numeroTarjeta = $data['numero_tarjeta'];
        $vencimiento = $data['vencimiento'];
        $pedidoId = null;

        oci_bind_by_name($stid, ':p_usuario_id', $usuarioId);
        oci_bind_by_name($stid, ':p_carrito_id', $carritoId);
        oci_bind_by_name($stid, ':p_direccion_id', $direccionId);
        oci_bind_by_name($stid, ':p_titular', $titular);
        oci_bind_by_name($stid, ':p_numero_tarjeta', $numeroTarjeta);
        oci_bind_by_name($stid, ':p_vencimiento', $vencimiento);
        oci_bind_by_name($stid, ':p_pedido_id', $pedidoId, 32);

        $result = oci_execute($stid, OCI_NO_AUTO_COMMIT);
        if (!$result) {
            $e = oci_error($stid);
            oci_rollback($this->conn);
            $errorMsg = "Error al procesar el pago: " . ($e['message'] ?? 'Error desconocido') . " | Código: " . ($e['code'] ?? 'N/A');
            error_log($errorMsg . " | SQL: $sql");
            throw new Exception($errorMsg);
        }

        oci_commit($this->conn);

        $resumenRestante = $this->getResumenCarritoRestante($carritoId);

        return [
            'pedido_id' => (int)$pedidoId,
            'carrito_restante' => $resumenRestante
        ];
    }

    public function procesarPagoInvitado($data)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = session_id();
        $carritoId = (int)$data['carrito_id'];
        $envio = isset($data['envio']) ? (float)$data['envio'] : 25.0;

        $sqlItems = "SELECT ci.producto_id, ci.cantidad, p.precio, p.stock
                     FROM carrito_items ci
                     JOIN productos p ON p.id = ci.producto_id
                     JOIN carritos c ON c.id = ci.carrito_id
                     WHERE ci.carrito_id = :carrito_id
                       AND ci.seleccionado = 1
                       AND c.usuario_id IS NULL
                       AND c.session_id = :session_id
                     ORDER BY ci.id ASC";
        $stidItems = $this->execute($sqlItems, [
            ":carrito_id" => $carritoId,
            ":session_id" => $sessionId
        ]);

        $items = [];
        $subtotal = 0;
        while ($row = oci_fetch_assoc($stidItems)) {
            $cantidad = (int)$row['CANTIDAD'];
            $stock = (int)$row['STOCK'];

            if ($cantidad <= 0) {
                continue;
            }

            if ($stock < $cantidad) {
                throw new Exception("Stock insuficiente para uno de los productos seleccionados");
            }

            $precio = (float)$row['PRECIO'];
            $subtotal += $cantidad * $precio;
            $items[] = [
                'producto_id' => (int)$row['PRODUCTO_ID'],
                'cantidad' => $cantidad,
                'precio' => $precio
            ];
        }

        if (empty($items)) {
            throw new Exception("No hay productos seleccionados para procesar el pedido");
        }

        $total = $subtotal + $envio;

        $sqlPedido = "INSERT INTO pedidos (
                          usuario_id,
                          total,
                          estado,
                          direccion_envio,
                          nombre_contacto,
                          correo_contacto,
                          telefono_contacto,
                          session_id
                      ) VALUES (
                          NULL,
                          :total,
                          'enviado',
                          :direccion_envio,
                          :nombre_contacto,
                          :correo_contacto,
                          :telefono_contacto,
                          :session_id
                      ) RETURNING id INTO :pedido_id";

        $stidPedido = oci_parse($this->conn, $sqlPedido);
        if (!$stidPedido) {
            $e = oci_error($this->conn);
            throw new Exception("Error al preparar el pedido invitado: " . ($e['message'] ?? 'Error desconocido'));
        }

        $pedidoId = null;
        $direccionEnvio = $data['direccion_envio'];
        $nombreContacto = $data['nombre_contacto'];
        $correoContacto = $data['correo_contacto'];
        $telefonoContacto = $data['telefono_contacto'] ?? null;

        oci_bind_by_name($stidPedido, ":total", $total);
        oci_bind_by_name($stidPedido, ":direccion_envio", $direccionEnvio);
        oci_bind_by_name($stidPedido, ":nombre_contacto", $nombreContacto);
        oci_bind_by_name($stidPedido, ":correo_contacto", $correoContacto);
        oci_bind_by_name($stidPedido, ":telefono_contacto", $telefonoContacto);
        oci_bind_by_name($stidPedido, ":session_id", $sessionId);
        oci_bind_by_name($stidPedido, ":pedido_id", $pedidoId, 32);

        if (!oci_execute($stidPedido, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stidPedido);
            oci_rollback($this->conn);
            throw new Exception("Error al crear el pedido invitado: " . ($e['message'] ?? 'Error desconocido'));
        }

        $sqlPedidoItem = "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario)
                          VALUES (:pedido_id, :producto_id, :cantidad, :precio_unitario)";
        $sqlStock = "UPDATE productos
                     SET stock = stock - :cantidad
                     WHERE id = :producto_id
                       AND stock >= :cantidad";

        foreach ($items as $item) {
            $stidPedidoItem = oci_parse($this->conn, $sqlPedidoItem);
            oci_bind_by_name($stidPedidoItem, ":pedido_id", $pedidoId);
            oci_bind_by_name($stidPedidoItem, ":producto_id", $item['producto_id']);
            oci_bind_by_name($stidPedidoItem, ":cantidad", $item['cantidad']);
            oci_bind_by_name($stidPedidoItem, ":precio_unitario", $item['precio']);

            if (!oci_execute($stidPedidoItem, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($stidPedidoItem);
                oci_rollback($this->conn);
                throw new Exception("Error al registrar items del pedido: " . ($e['message'] ?? 'Error desconocido'));
            }

            $stidStock = oci_parse($this->conn, $sqlStock);
            oci_bind_by_name($stidStock, ":cantidad", $item['cantidad']);
            oci_bind_by_name($stidStock, ":producto_id", $item['producto_id']);

            if (!oci_execute($stidStock, OCI_NO_AUTO_COMMIT) || oci_num_rows($stidStock) < 1) {
                oci_rollback($this->conn);
                throw new Exception("No se pudo actualizar el stock de uno de los productos");
            }
        }

        $sqlClear = "DELETE FROM carrito_items WHERE carrito_id = :carrito_id AND seleccionado = 1";
        $stidClear = oci_parse($this->conn, $sqlClear);
        oci_bind_by_name($stidClear, ":carrito_id", $carritoId);
        if (!oci_execute($stidClear, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stidClear);
            oci_rollback($this->conn);
            throw new Exception("Error al limpiar el carrito: " . ($e['message'] ?? 'Error desconocido'));
        }

        oci_commit($this->conn);

        $resumenRestante = $this->getResumenCarritoRestante($carritoId);

        return [
            'pedido_id' => (int)$pedidoId,
            'total' => $total,
            'correo_contacto' => $correoContacto,
            'carrito_restante' => $resumenRestante
        ];
    }

    private function getResumenCarritoRestante($carritoId)
    {
        $sql = "SELECT COUNT(*) AS total_items,
                       NVL(SUM(cantidad), 0) AS total_unidades
                FROM carrito_items
                WHERE carrito_id = :carrito_id";
        $stid = $this->execute($sql, [":carrito_id" => $carritoId]);
        $row = oci_fetch_assoc($stid);

        return [
            'totalItems' => (int)($row['TOTAL_ITEMS'] ?? 0),
            'totalUnidades' => (int)($row['TOTAL_UNIDADES'] ?? 0)
        ];
    }
}
