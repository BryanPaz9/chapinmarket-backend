<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . $origin);
header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Manejo del preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$router = require_once __DIR__ . '/../routes/api.php';

$method = $_SERVER['REQUEST_METHOD'];

// 👉 ESTA LÍNEA TE FALTABA
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Quitar el nombre del proyecto
$basePath = '/chapinmarket-backend/public';
$uri = str_replace($basePath, '', $uri);

// Si queda vacío
if ($uri === '') {
    $uri = '/';
}

$router->dispatch($method, $uri);
set_exception_handler(function ($e) {
    Response::error("Error inesperado", 500, $e->getMessage());
});
