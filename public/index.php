<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

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