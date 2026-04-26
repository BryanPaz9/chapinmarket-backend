<?php
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../core/Response.php';

class ProductoController {

    private $model;

    public function __construct() {
        $this->model = new Producto();
    }

    public function index() {
        $data = $this->model->getAll();

        Response::success($data, "Productos obtenidos");
    }

    public function show($id) {
        $data = $this->model->getById($id);

        if (!$data) {
            Response::error("Producto no encontrado", 404);
        }

        Response::success($data, "Producto obtenido");
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'])) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->create($data);

        Response::success(null, "Producto creado", 201);
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Producto actualizado");
    }

    public function delete($id) {
        $this->model->delete($id);

        Response::success(null, "Producto eliminado");
    }
}