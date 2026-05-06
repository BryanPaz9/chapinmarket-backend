<?php
require_once __DIR__ . '/../models/Favorito.php';
require_once __DIR__ . '/../core/Response.php';

class FavoritoController
{
    private $model;

    public function __construct()
    {
        $this->model = new Favorito();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index()
    {
        $usuarioId = $this->getUsuarioId();
        $favoritos = $this->model->getByUsuario($usuarioId);
        Response::success($favoritos, "Favoritos obtenidos");
    }

    public function store()
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['productoId'])) {
            Response::error("productoId es requerido", 400);
        }
        $productoId = (int)$data['productoId'];
        $this->model->add($usuarioId, $productoId);
        Response::success(null, "Producto agregado a favoritos");
    }

    public function destroy($productoId)
    {
        $usuarioId = $this->getUsuarioId();
        $this->model->remove($usuarioId, (int)$productoId);
        Response::success(null, "Producto eliminado de favoritos");
    }

    private function getUsuarioId()
    {
        if (isset($_SESSION['usuario_id'])) {
            return (int)$_SESSION['usuario_id'];
        }
        if (isset($_GET['uid'])) {
            return (int)$_GET['uid'];
        }
        Response::error("Usuario no autenticado", 401);
    }
}
