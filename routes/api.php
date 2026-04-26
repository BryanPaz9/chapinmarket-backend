<?php
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../controllers/CategoriaController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/ProductoController.php';
require_once __DIR__ . '/../controllers/TarjetaController.php';
require_once __DIR__ . '/../controllers/TemporadaController.php';

$router = new Router();

$categoria = new CategoriaController();
$usuario = new UsuarioController();
$producto = new ProductoController();
$tarjeta = new TarjetaController();
$temporada = new TemporadaController();

/**     CATEGORIAS      */
$router->add('GET', '/categorias', fn() => $categoria->index());
$router->add('GET', '/categorias/{id}', fn($id) => $categoria->show($id));
$router->add('POST', '/categorias', fn() => $categoria->store());
$router->add('PUT', '/categorias/{id}', fn($id) => $categoria->update($id));
$router->add('DELETE', '/categorias/{id}', fn($id) => $categoria->delete($id));

/**     USUARIOS        */
$router->add('GET', '/usuarios', fn() => $usuario->index());
$router->add('GET', '/usuarios/{id}', fn($id) => $usuario->show($id));


/**     PRODUCTOS       */
$router->add('GET', '/productos', fn() => $producto->index());
$router->add('GET', '/productos/{id}', fn($id) => $producto->show($id));
$router->add('POST', '/productos', fn() => $producto->store());
$router->add('PUT', '/productos/{id}', fn($id) => $producto->update($id));
$router->add('DELETE', '/productos/{id}', fn($id) => $producto->delete($id));
/**     TARJETAS        */
$router->add('GET', '/tarjetas', fn() => $tarjeta->index());
$router->add('GET', '/tarjetas/{id}', fn($id) => $tarjeta->show($id));
/**     Temporadas      */
$router->add('GET', '/temporadas', fn() => $temporada->index());
$router->add('GET', '/temporadas/{id}', fn($id) => $tarjeta->show($id));
/**     Carrito         */
return $router;