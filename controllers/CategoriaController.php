<?php
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../core/Response.php';

class CategoriaController
{

    private $model;

    public function __construct()
    {
        $this->model = new Categoria();
    }

    public function index()
    {
        $data = $this->model->getAll();

        Response::success($data, "Categorías obtenidas");
    }

    public function show($id)
    {
        $data = $this->model->getById($id);

        if (!$data) {
            Response::error("Categoría no encontrada", 404);
        }

        Response::success($data, "Categoría obtenida");
    }

    public function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'])) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->create($data);

        Response::success(null, "Categoría creada", 201);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Categoría actualizada");
    }

    public function delete($id)
    {
        $this->model->delete($id);

        Response::success(null, "Categoría eliminada");
    }

    public function destacadas()
    {
        $data = $this->model->getRandomCategories(6);
        Response::success($data, "Categorías destacadas obtenidas");
    }
}
