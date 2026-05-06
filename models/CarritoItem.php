<?php
require_once 'BaseModel.php';

class CarritoItem extends BaseModel {

    public function getById($id) {
        $sql = "SELECT ci.*, p.nombre, p.precio, p.stock
                FROM carrito_items ci
                JOIN productos p ON p.id = ci.producto_id
                WHERE ci.id = :id";
        
        $stid = $this->execute($sql, [":id" => $id]);
        $result = oci_fetch_assoc($stid);
        
        if ($result) {
            return [
                'id' => (int)$result['ID'],
                'carritoId' => (int)$result['CARRITO_ID'],
                'productoId' => (int)$result['PRODUCTO_ID'],
                'cantidad' => (int)$result['CANTIDAD'],
                'seleccionado' => (int)$result['SELECCIONADO'] === 1,
                'producto' => [
                    'nombre' => $result['NOMBRE'],
                    'precio' => (float)$result['PRECIO'],
                    'stock' => (int)$result['STOCK']
                ]
            ];
        }
        
        return null;
    }
    
    public function existsInCart($carritoId, $productoId) {
        $sql = "SELECT COUNT(*) as count FROM carrito_items 
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";
        $stid = $this->execute($sql, [
            ":carrito_id" => $carritoId,
            ":producto_id" => $productoId
        ]);
        $result = oci_fetch_assoc($stid);
        
        return ($result['COUNT'] ?? 0) > 0;
    }
}