<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../core/Response.php';

class UsuarioController {

    private $model;

    public function __construct() {
        $this->model = new Usuario();
    }

    public function index() {
        $data = $this->model->getAll();

        Response::success($data, "Usuarios obtenidos");
    }

    public function show($id) {
        $data = $this->model->getById($id);

        if (!$data) {
            Response::error("Usuario no encontrado", 404);
        }

        Response::success($data, "Usuario obtenido");
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre'])) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->create($data);

        Response::success(null, "Usuario creado", 201);
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Usuario actualizado");
    }

    public function delete($id) {
        $this->model->delete($id);

        Response::success(null, "Usuario eliminado");
    }
}