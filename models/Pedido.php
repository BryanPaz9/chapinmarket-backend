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
}
