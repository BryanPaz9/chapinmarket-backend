<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE])) {
        return true; 
    }
    return false;
});

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '0');
ini_set('session.cookie_path', '/');

$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
$allowedOrigins = ['http://localhost', 'http://localhost:80', 'http://127.0.0.1', 'http://127.0.0.1:80'];
if (in_array($allowedOrigin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
} else {
    header("Access-Control-Allow-Origin: http://localhost");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Response.php';

set_exception_handler(function ($e) {
    error_log("Excepción no capturada: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    Response::error("Error interno del servidor: " . $e->getMessage(), 500);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Error fatal: " . $error['message'] . " en " . $error['file'] . ":" . $error['line']);
        Response::error("Error fatal del servidor: " . $error['message'], 500);
    }
});

$router = require __DIR__ . '/../routes/api.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/chapinmarket-backend/public';
$uri    = str_replace($basePath, '', $uri);
if ($uri === '') $uri = '/';

$router->dispatch($method, $uri);
