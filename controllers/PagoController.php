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

    public function procesar()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data) {
                Response::error("Datos invalidos", 400);
            }

            $esInvitado = !empty($data['invitado']) || !empty($data['guest']);

            if ($esInvitado) {
                $pagoInvitado = [
                    'carrito_id' => $data['carritoId'] ?? $data['carrito_id'] ?? null,
                    'nombre_contacto' => trim($data['nombreContacto'] ?? $data['nombre_contacto'] ?? ''),
                    'correo_contacto' => trim($data['correoContacto'] ?? $data['correo_contacto'] ?? ''),
                    'telefono_contacto' => trim($data['telefonoContacto'] ?? $data['telefono_contacto'] ?? ''),
                    'direccion_envio' => trim($data['direccionEnvio'] ?? $data['direccion_envio'] ?? ''),
                    'titular' => $data['titular'] ?? null,
                    'numero_tarjeta' => $data['numeroTarjeta'] ?? $data['numero_tarjeta'] ?? null,
                    'vencimiento' => $data['vencimiento'] ?? null,
                    'envio' => $data['envio'] ?? 25
                ];

                $camposInvitado = [
                    'carrito_id',
                    'nombre_contacto',
                    'correo_contacto',
                    'telefono_contacto',
                    'direccion_envio',
                    'titular',
                    'numero_tarjeta',
                    'vencimiento'
                ];

                foreach ($camposInvitado as $campo) {
                    if (!isset($pagoInvitado[$campo]) || $pagoInvitado[$campo] === '') {
                        Response::error("Datos invalidos: se requiere $campo", 400);
                    }
                }

                if (!filter_var($pagoInvitado['correo_contacto'], FILTER_VALIDATE_EMAIL)) {
                    Response::error("Datos invalidos: correo de contacto no valido", 400);
                }

                $pedido = $this->model->procesarPagoInvitado($pagoInvitado);
                Response::success($pedido, "Pedido confirmado correctamente", 201);
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
                    Response::error("Datos invalidos: se requiere $campo", 400);
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
