<?php
require_once __DIR__ . '/../models/Pago.php';
require_once __DIR__ . '/../core/Response.php';

class PagoController
{
    private $model;

    public function __construct()
    {
        $this->model = new Pago();
    }

    /**
     * POST /pago - Procesar pago del carrito y crear pedido
     */
    public function procesar()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                Response::error("Datos inválidos", 400);
            }

            $pagoData = [
                'usuario_id' => $data['usuarioId'] ?? $data['usuario_id'] ?? $data['p_usuario_id'] ?? null,
                'carrito_id' => $data['carritoId'] ?? $data['carrito_id'] ?? $data['p_carrito_id'] ?? null,
                'direccion_id' => $data['direccionId'] ?? $data['direccion_id'] ?? $data['p_direccion_id'] ?? null,
                'titular' => $data['titular'] ?? $data['p_titular'] ?? null,
                'numero_tarjeta' => $data['numeroTarjeta'] ?? $data['numero_tarjeta'] ?? $data['p_numero_tarjeta'] ?? null,
                'vencimiento' => $data['vencimiento'] ?? $data['p_vencimiento'] ?? null,
            ];

            $camposRequeridos = [
                'usuario_id',
                'carrito_id',
                'direccion_id',
                'titular',
                'numero_tarjeta',
                'vencimiento'
            ];

            foreach ($camposRequeridos as $campo) {
                if (!isset($pagoData[$campo]) || $pagoData[$campo] === '') {
                    Response::error("Datos inválidos: se requiere $campo", 400);
                }
            }

            $pedido = $this->model->procesarPagoCarrito($pagoData);

            Response::success($pedido, "Pago procesado correctamente", 201);
        } catch (Exception $e) {
            error_log("Error en PagoController::procesar: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
