<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =============================================
// CONFIGURACIÓN DE SESIÓN — DEBE IR ANTES DE session_start()
// =============================================
ini_set('session.cookie_samesite', 'Lax');    // Permite cookies en requests same-origin
ini_set('session.cookie_secure', '0');         // 0 porque usamos HTTP en local (poner 1 en producción con HTTPS)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '0');
ini_set('session.cookie_path', '/');
// session.name por defecto es PHPSESSID — está bien así

// =============================================
// CORS — ANTES de cualquier output
// =============================================
$allowedOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost';

// Lista blanca de orígenes permitidos
$allowedOrigins = [
    'http://localhost',
    'http://localhost:80',
    'http://127.0.0.1',
    'http://127.0.0.1:80',
];

if (in_array($allowedOrigin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
} else {
    header("Access-Control-Allow-Origin: http://localhost");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");   // ✅ CRÍTICO para cookies de sesión
header("Content-Type: application/json; charset=utf-8");

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar sesión SIEMPRE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar Response ANTES del manejador de excepciones
require_once __DIR__ . '/../core/Response.php';

// Manejador de excepciones global
set_exception_handler(function ($e) {
    error_log("Excepción no capturada: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    Response::error("Error interno del servidor: " . $e->getMessage(), 500);
});

// Manejador de errores fatales
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
