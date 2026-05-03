<?php
require_once __DIR__ . '/../models/Tarjeta.php';
require_once __DIR__ . '/../core/Response.php';

class TarjetaController {

    private $model;

    public function __construct() {
        $this->model = new Tarjeta();
    }

    public function index() {
        $data = $this->model->getAll();

        Response::success($data, "Tarjetas obtenidas");
    }

    public function show($id) {
        $data = $this->model->getById($id);

        if (!$data) {
            Response::error("Tarjeta no encontrada", 404);
        }

        Response::success($data, "Tarjeta obtenida");
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'])) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->create($data);

        Response::success(null, "Tarjeta creada", 201);
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Tarjeta actualizada");
    }

    public function delete($id) {
        $this->model->delete($id);

        Response::success(null, "Tarjeta eliminada");
    }
}