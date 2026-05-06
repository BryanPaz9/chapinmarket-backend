<?php
require_once __DIR__ . '/../models/Calificacion.php';
require_once __DIR__ . '/../core/Response.php';

class CalificacionController
{
    private $model;

    public function __construct()
    {
        $this->model = new Calificacion();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getRating($productoId)
    {
        try {
            $usuarioId = $_SESSION['usuario_id'] ?? null;

            $info = $this->model->getInfo(
                (int)$productoId,
                $usuarioId ? (int)$usuarioId : null
            );

            Response::success($info, "Información de calificación");
        } catch (Exception $e) {
            error_log("Error en getRating: " . $e->getMessage());
            Response::error("Error al obtener calificación: " . $e->getMessage(), 500);
        }
    }

    public function saveRating($productoId)
    {
        if (!isset($_SESSION['usuario_id'])) {
            Response::error("Debes iniciar sesión", 401);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['calificacion'])) {
            Response::error("Calificación requerida", 400);
        }

        try {
            $this->model->saveOrUpdate(
                (int)$_SESSION['usuario_id'],
                (int)$productoId,
                (int)$data['calificacion']
            );

            Response::success(null, "Calificación guardada");
        } catch (Exception $e) {
            error_log("Error en saveRating: " . $e->getMessage());
            Response::error("Error al guardar calificación: " . $e->getMessage(), 500);
        }
    }
}
