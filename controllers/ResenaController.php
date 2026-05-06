<?php
require_once __DIR__ . '/../models/Resena.php';
require_once __DIR__ . '/../core/Response.php';

class ResenaController
{
    private $model;

    public function __construct()
    {
        $this->model = new Resena();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index($productoId)
    {
        try {
            $resenas = $this->model->getByProducto((int)$productoId);
            Response::success($resenas, "Reseñas obtenidas");
        } catch (Exception $e) {
            error_log("Error en index resenas: " . $e->getMessage());
            Response::error("Error al obtener reseñas: " . $e->getMessage(), 500);
        }
    }

    public function store($productoId)
    {
        if (!isset($_SESSION['usuario_id'])) {
            Response::error("Debes iniciar sesión", 401);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || empty(trim($data['comentario'] ?? ''))) {
            Response::error("Comentario requerido", 400);
        }

        try {
            $id = $this->model->create(
                (int)$_SESSION['usuario_id'],
                (int)$productoId,
                trim($data['comentario'])
            );

            Response::success(['id' => $id], "Reseña creada", 201);
        } catch (Exception $e) {
            error_log("Error en store resena: " . $e->getMessage());
            Response::error("Error al crear reseña: " . $e->getMessage(), 500);
        }
    }
}