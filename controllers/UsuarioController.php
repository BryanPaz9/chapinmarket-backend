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
        $this->register();
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"), true);
        $contrasena = $data['contrasena'] ?? $data['password'] ?? null;

        if (!$data || empty($data['nombre']) || empty($data['correo']) || empty($contrasena)) {
            Response::error("Datos invalidos", 400);
        }

        if ($this->model->getByCorreo($data['correo'])) {
            Response::error("El correo ya esta registrado", 409);
        }

        $data['contrasena'] = $contrasena;
        $data['direccion'] = $data['direccion'] ?? null;
        $data['es_admin'] = 0;
        $this->model->create($data);

        Response::success(null, "Usuario registrado", 201);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        $contrasena = $data['contrasena'] ?? $data['password'] ?? null;

        if (!$data || empty($data['correo']) || empty($contrasena)) {
            Response::error("Datos invalidos", 400);
        }

        $usuario = $this->model->validarCredenciales($data['correo'], $contrasena);

        if (!$usuario) {
            Response::error("Credenciales invalidas", 401);
        }

        $usuario['DIRECCION'] = $usuario['DIRECCION'] ?? null;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['usuario'] = [
            "id" => $usuario['ID'],
            "correo" => $usuario['CORREO'],
            "direccion" => $usuario['DIRECCION'],
            "es_admin" => $usuario['ES_ADMIN'] ?? 0
        ];

        Response::success($usuario, "Bienvenido " . $usuario['NOMBRE']);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        Response::success(null, "Logout exitoso");
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos invalidos", 400);
        }

        $this->model->update($id, $data);

        Response::success(null, "Usuario actualizado");
    }

    public function delete($id) {
        $this->model->delete($id);

        Response::success(null, "Usuario eliminado");
    }
}
