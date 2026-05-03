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

        return [
            'pedido_id' => (int)$pedidoId
        ];
    }
}
