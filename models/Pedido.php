<?php
require_once 'BaseModel.php';

class Pedido extends BaseModel
{

    public function getByUsuario($usuarioId)
    {
        $sql = "SELECT p.id, p.fecha, p.total, p.estado, p.direccion_envio,
                       (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) AS cantidad_items
                FROM pedidos p 
                WHERE p.usuario_id = :usuario_id 
                ORDER BY p.fecha DESC";
        $stid = $this->execute($sql, [":usuario_id" => $usuarioId]);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = [
                'id' => (int)$row['ID'],
                'fecha' => $row['FECHA'],
                'total' => (float)$row['TOTAL'],
                'estado' => $row['ESTADO'],
                'direccionEnvio' => $row['DIRECCION_ENVIO'] ?? '',
                'cantidadItems' => (int)$row['CANTIDAD_ITEMS']
            ];
        }
        return $data;
    }

    public function getDetalleById($pedidoId, $usuarioId)
    {
        $sqlPedido = "SELECT p.id, p.usuario_id, p.fecha, p.total, p.estado, p.direccion_envio,
                             (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) AS cantidad_items,
                             (SELECT NVL(SUM(pi.cantidad), 0) FROM pedido_items pi WHERE pi.pedido_id = p.id) AS total_unidades
                      FROM pedidos p
                      WHERE p.id = :pedido_id
                        AND p.usuario_id = :usuario_id";

        $stidPedido = $this->execute($sqlPedido, [
            ":pedido_id" => $pedidoId,
            ":usuario_id" => $usuarioId
        ]);

        $pedidoRow = oci_fetch_assoc($stidPedido);
        if (!$pedidoRow) {
            return null;
        }

        $sqlItems = "SELECT pi.id, pi.pedido_id, pi.producto_id, pi.cantidad, pi.precio_unitario,
                            (pi.cantidad * pi.precio_unitario) AS subtotal,
                            p.nombre, p.descripcion, p.imagenes
                     FROM pedido_items pi
                     JOIN productos p ON p.id = pi.producto_id
                     WHERE pi.pedido_id = :pedido_id
                     ORDER BY pi.id ASC";

        $stidItems = $this->execute($sqlItems, [":pedido_id" => $pedidoId]);

        $items = [];
        $subtotalItems = 0;
        while ($row = oci_fetch_assoc($stidItems)) {
            $subtotal = (float)$row['SUBTOTAL'];
            $subtotalItems += $subtotal;
            $imagenUrl = $this->extractFirstImage($row['IMAGENES'] ?? null);

            $items[] = [
                'id' => (int)$row['ID'],
                'pedidoId' => (int)$row['PEDIDO_ID'],
                'productoId' => (int)$row['PRODUCTO_ID'],
                'cantidad' => (int)$row['CANTIDAD'],
                'precioUnitario' => (float)$row['PRECIO_UNITARIO'],
                'subtotal' => $subtotal,
                'producto' => [
                    'id' => (int)$row['PRODUCTO_ID'],
                    'nombre' => $row['NOMBRE'],
                    'descripcion' => $row['DESCRIPCION'] ?? '',
                    'imagen' => $imagenUrl,
                    'imagenes' => $imagenUrl ? [$imagenUrl] : []
                ]
            ];
        }

        return [
            'id' => (int)$pedidoRow['ID'],
            'usuarioId' => (int)$pedidoRow['USUARIO_ID'],
            'fecha' => $pedidoRow['FECHA'],
            'total' => (float)$pedidoRow['TOTAL'],
            'subtotalItems' => $subtotalItems,
            'estado' => $pedidoRow['ESTADO'] ?? 'pendiente',
            'metodoPago' => 'Tarjeta',
            'direccionEnvio' => $pedidoRow['DIRECCION_ENVIO'] ?? '',
            'cantidadItems' => (int)$pedidoRow['CANTIDAD_ITEMS'],
            'totalUnidades' => (int)$pedidoRow['TOTAL_UNIDADES'],
            'items' => $items
        ];
    }

    private function extractFirstImage($raw)
    {
        if (is_object($raw) && method_exists($raw, 'load')) {
            $raw = $raw->load();
        } elseif (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        $clean = trim($raw);
        $decoded = json_decode($clean, true);
        if (is_array($decoded) && !empty($decoded)) {
            return trim((string)$decoded[0]);
        }

        return $clean;
    }
}
