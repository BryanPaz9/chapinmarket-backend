<?php
require_once __DIR__ . '/../models/Temporada.php';
require_once __DIR__ . '/../core/Response.php';

class TemporadaController {

    private $model;

    public function __construct() {
        $this->model = new Temporada();
    }

    public function index() {
        $data = $this->model->getAll();

        Response::success($data, "Temporadas obtenidas");
    }

    public function show($id) {
        $data = $this->model->getById($id);

        if (!$data) {
            Response::error("Temporada no encontrada", 404);
        }

        Response::success($data, "Temporada obtenida");
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'])) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->create($data);

        Response::success(null, "Temporada creada", 201);
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Temporada actualizada");
    }

    public function delete($id) {
        $this->model->delete($id);

        Response::success(null, "Temporada eliminada");
    }
}