<?php
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../controllers/CategoriaController.php';
require_once __DIR__ . '/../controllers/ProductoController.php';
require_once __DIR__ . '/../controllers/TarjetaController.php';
require_once __DIR__ . '/../controllers/TemporadaController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/CarritoController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PerfilController.php';
require_once __DIR__ . '/../controllers/PagoController.php';
require_once __DIR__ . '/../controllers/PedidoController.php';

$router = new Router();

// ========== AUTENTICACIÓN ==========
$router->add('POST', '/auth/login', function () {
    (new AuthController())->login();
});
$router->add('POST', '/auth/registro', function () {
    (new AuthController())->registro();
});
$router->add('GET', '/auth/me', function () {
    (new AuthController())->me();
});
$router->add('POST', '/auth/logout', function () {
    (new AuthController())->logout();
});
$router->add('POST', '/auth/cambiar-password', function () {
    (new AuthController())->cambiarPassword();
});

// ========== PERFIL DE USUARIO ==========
// Ruta principal que usa el frontend (app.js)
$router->add('GET', '/perfil', function () {
    (new PerfilController())->obtenerDatos();
});
// Rutas alternativas (por si se llaman con /datos)
$router->add('GET', '/perfil/datos', function () {
    (new PerfilController())->obtenerDatos();
});
$router->add('PUT', '/perfil/datos', function () {
    (new PerfilController())->actualizarDatos();
});
$router->add('PUT', '/perfil/password', function () {
    (new PerfilController())->cambiarPassword();
});

// Direcciones
$router->add('GET', '/perfil/direcciones', function () {
    (new PerfilController())->listarDirecciones();
});
$router->add('POST', '/perfil/direcciones', function () {
    (new PerfilController())->agregarDireccion();
});
$router->add('PUT', '/perfil/direcciones/{id}', function ($id) {
    (new PerfilController())->actualizarDireccion($id);
});
$router->add('DELETE', '/perfil/direcciones/{id}', function ($id) {
    (new PerfilController())->eliminarDireccion($id);
});

// Tarjetas
$router->add('GET', '/perfil/tarjetas', function () {
    (new PerfilController())->listarTarjetas();
});
$router->add('POST', '/perfil/tarjetas', function () {
    (new PerfilController())->agregarTarjeta();
});
$router->add('DELETE', '/perfil/tarjetas/{id}', function ($id) {
    (new PerfilController())->eliminarTarjeta($id);
});

// ========== CATEGORÍAS ==========
$router->add('GET', '/categorias', function () {
    (new CategoriaController())->index();
});
$router->add('GET', '/categorias/{id}', function ($id) {
    (new CategoriaController())->show($id);
});
$router->add('POST', '/categorias', function () {
    (new CategoriaController())->store();
});
$router->add('PUT', '/categorias/{id}', function ($id) {
    (new CategoriaController())->update($id);
});
$router->add('DELETE', '/categorias/{id}', function ($id) {
    (new CategoriaController())->delete($id);
});

// ========== PRODUCTOS ==========
$router->add('GET', '/productos', function () {
    (new ProductoController())->index();
});
$router->add('GET', '/productos/{id}', function ($id) {
    (new ProductoController())->show($id);
});
$router->add('POST', '/productos', function () {
    (new ProductoController())->store();
});
$router->add('PUT', '/productos/{id}', function ($id) {
    (new ProductoController())->update($id);
});
$router->add('DELETE', '/productos/{id}', function ($id) {
    (new ProductoController())->delete($id);
});

// ========== TARJETAS (admin) ==========
$router->add('GET', '/tarjetas', function () {
    (new TarjetaController())->index();
});
$router->add('GET', '/tarjetas/{id}', function ($id) {
    (new TarjetaController())->show($id);
});
$router->add('POST', '/tarjetas', function () {
    (new TarjetaController())->store();
});
$router->add('PUT', '/tarjetas/{id}', function ($id) {
    (new TarjetaController())->update($id);
});
$router->add('DELETE', '/tarjetas/{id}', function ($id) {
    (new TarjetaController())->delete($id);
});

// ========== TEMPORADAS ==========
$router->add('GET', '/temporadas', function () {
    (new TemporadaController())->index();
});
$router->add('GET', '/temporadas/{id}', function ($id) {
    (new TemporadaController())->show($id);
});
$router->add('POST', '/temporadas', function () {
    (new TemporadaController())->store();
});
$router->add('PUT', '/temporadas/{id}', function ($id) {
    (new TemporadaController())->update($id);
});
$router->add('DELETE', '/temporadas/{id}', function ($id) {
    (new TemporadaController())->delete($id);
});

// ========== USUARIOS ==========
$router->add('GET', '/usuarios', function () {
    (new UsuarioController())->index();
});
$router->add('GET', '/usuarios/{id}', function ($id) {
    (new UsuarioController())->show($id);
});
$router->add('POST', '/usuarios', function () {
    (new UsuarioController())->store();
});
$router->add('PUT', '/usuarios/{id}', function ($id) {
    (new UsuarioController())->update($id);
});
$router->add('DELETE', '/usuarios/{id}', function ($id) {
    (new UsuarioController())->delete($id);
});

// ========== CARRITO DE COMPRAS ==========
$router->add('GET', '/carrito', function () {
    (new CarritoController())->index();
});
$router->add('POST', '/carrito', function () {
    (new CarritoController())->store();
});
$router->add('PUT', '/carrito', function () {
    (new CarritoController())->update();
});
$router->add('DELETE', '/carrito', function () {
    (new CarritoController())->delete();
});
$router->add('GET', '/carrito/resumen', function () {
    (new CarritoController())->getSummary();
});
$router->add('POST', '/carrito/migrate', function () {
    (new CarritoController())->migrate();
});
$router->add('PUT', '/carrito/decrementar/{id}', function ($id) {
    (new CarritoController())->decrementar($id);
});

// ========== PAGO ==========
$router->add('POST', '/pago', function () {
    (new PagoController())->procesar();
});

// ========== IMÁGENES ==========
$router->add('GET', '/pedidos', function () {
    (new PedidoController())->index();
});

$router->add('GET', '/pedidos/{id}', function ($id) {
    (new PedidoController())->show($id);
});

$router->add('GET', '/imagenes/{filename}', function ($filename) {
    $filePath = __DIR__ . '/../uploads/' . $filename;
    if (file_exists($filePath)) {
        $mime = mime_content_type($filePath);
        header("Content-Type: $mime");
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo "Imagen no encontrada";
});

return $router;
