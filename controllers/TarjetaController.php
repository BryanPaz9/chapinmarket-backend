<?php
require_once __DIR__ . '/../models/Tarjeta.php';
require_once __DIR__ . '/../core/Response.php';

class TarjetaController {

    private $model;

    public function __construct() {
        $this->model = new Tarjeta();
    }

    private function usuarioAutenticado() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['usuario']['id'])) {
            Response::error("Debes iniciar sesion para acceder a tus tarjetas", 401);
        }

        return $_SESSION['usuario'];
    }

    private function leerJson() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            Response::error("Datos invalidos", 400);
        }

        return $data;
    }

    private function normalizarTarjeta($data, $existing = []) {
        $numero = $data['numero'] ?? $data['numero_tarjeta'] ?? $data['numero_enmascarado'] ?? null;

        $tarjeta = [
            "titular" => $data['titular'] ?? $existing['TITULAR'] ?? null,
            "tipo" => $data['tipo'] ?? $existing['TIPO'] ?? null,
            "vencimiento" => $data['vencimiento'] ?? $data['fecha_vencimiento'] ?? $existing['VENCIMIENTO'] ?? null,
            "numero_enmascarado" => $existing['NUMERO_ENMASCARADO'] ?? null
        ];

        if ($numero !== null) {
            $tarjeta["numero_enmascarado"] = $this->enmascararNumero($numero);

            if (empty($tarjeta["tipo"])) {
                $tarjeta["tipo"] = $this->detectarTipo($numero);
            }
        }

        return $tarjeta;
    }

    private function enmascararNumero($numero) {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        if (strlen($digitos) < 4 || strlen($digitos) > 19) {
            Response::error("Numero de tarjeta invalido", 400);
        }

        return "**** **** **** " . substr($digitos, -4);
    }

    private function detectarTipo($numero) {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        if (preg_match('/^4/', $digitos)) {
            return "Visa";
        }

        if (preg_match('/^(5[1-5]|2[2-7])/', $digitos)) {
            return "Mastercard";
        }

        if (preg_match('/^3[47]/', $digitos)) {
            return "American Express";
        }

        return "Desconocida";
    }

    public function index() {
        $usuario = $this->usuarioAutenticado();
        $data = $this->model->getAllByUsuario($usuario['id']);

        Response::success($data, "Tarjetas obtenidas");
    }

    public function show($id) {
        $usuario = $this->usuarioAutenticado();
        $data = $this->model->getByIdForUsuario($id, $usuario['id']);

        if (!$data) {
            Response::error("Tarjeta no encontrada", 404);
        }

        Response::success($data, "Tarjeta obtenida");
    }

    public function store() {
        $usuario = $this->usuarioAutenticado();
        $data = $this->leerJson();
        $tarjeta = $this->normalizarTarjeta($data);

        if (
            empty($tarjeta['titular']) || 
        empty($tarjeta['numero_enmascarado']) || 
        empty($tarjeta['tipo']) || 
        empty($tarjeta['vencimiento'])) {
            Response::error("Datos invalidos", 400);
        }

        $this->model->create($tarjeta, $usuario['id']);

        Response::success(null, "Tarjeta creada", 201);
    }

    public function update($id) {
        $usuario = $this->usuarioAutenticado();
        $actual = $this->model->getByIdForUsuario($id, $usuario['id']);

        if (!$actual) {
            Response::error("Tarjeta no encontrada", 404);
        }

        $data = $this->leerJson();
        $tarjeta = $this->normalizarTarjeta($data, $actual);

        if (empty($tarjeta['titular']) || empty($tarjeta['numero_enmascarado']) || empty($tarjeta['tipo']) || empty($tarjeta['vencimiento'])) {
            Response::error("Datos invalidos", 400);
        }

        $this->model->updateForUsuario($id, $usuario['id'], $tarjeta);

        Response::success(null, "Tarjeta actualizada");
    }

    public function delete($id) {
        $usuario = $this->usuarioAutenticado();
        $eliminada = $this->model->deleteForUsuario($id, $usuario['id']);

        if (!$eliminada) {
            Response::error("Tarjeta no encontrada", 404);
        }

        Response::success(null, "Tarjeta eliminada");
    }
}
