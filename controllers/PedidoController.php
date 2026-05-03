<?php
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../core/Response.php';

class PedidoController
{
    private $model;

    public function __construct()
    {
        $this->model = new Pedido();
    }

    public function index()
    {
        try {
            $usuarioId = $this->getUsuarioId();
            $pedidos = $this->model->getByUsuario($usuarioId);

            Response::success($pedidos, "Pedidos obtenidos");
        } catch (Exception $e) {
            error_log("Error en PedidoController::index: " . $e->getMessage());
            Response::error("No se pudieron obtener los pedidos", 500);
        }
    }

    public function show($id)
    {
        try {
            $usuarioId = $this->getUsuarioId();
            $pedidoId = (int)$id;

            if ($pedidoId <= 0) {
                Response::error("Pedido inválido", 400);
            }

            $pedido = $this->model->getDetalleById($pedidoId, $usuarioId);
            if (!$pedido) {
                Response::error("Pedido no encontrado", 404);
            }

            Response::success($pedido, "Detalle de pedido obtenido");
        } catch (Exception $e) {
            error_log("Error en PedidoController::show: " . $e->getMessage());
            Response::error("No se pudo obtener el detalle del pedido", 500);
        }
    }

    private function getUsuarioId()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['usuario']['id'])) {
            return (int)$_SESSION['usuario']['id'];
        }

        if (isset($_SESSION['usuario_id'])) {
            return (int)$_SESSION['usuario_id'];
        }

        if (isset($_GET['uid'])) {
            return (int)$_GET['uid'];
        }

        Response::error("Usuario no autenticado", 401);
    }
}
