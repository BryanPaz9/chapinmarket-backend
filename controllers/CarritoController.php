<?php
require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../core/Response.php';

class CarritoController
{
    private $model;

    public function __construct()
    {
        $this->model = new Carrito();

        // Iniciar sesión para carritos anónimos
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * GET /carrito - Obtener todos los items del carrito
     */
    public function index()
    {
        try {
            $usuarioId = $this->getCurrentUserId();
            $carrito = $this->model->getOrCreateCart($usuarioId);
            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary,
                'carrito_id' => $carrito['id']
            ], "Carrito obtenido correctamente");
        } catch (Exception $e) {
            error_log("Error en CarritoController::index: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /carrito - Agregar producto al carrito
     */
    public function store()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data || !isset($data['productoId']) || !isset($data['cantidad'])) {
                Response::error("Datos inválidos: se requiere productoId y cantidad", 400);
            }

            $productoId = (int)$data['productoId'];
            $cantidad = max(1, (int)$data['cantidad']);
            $usuarioId = $data['usuarioId'] ?? $this->getCurrentUserId();

            $carrito = $this->model->getOrCreateCart($usuarioId);
            $this->model->addOrUpdateItem($carrito['id'], $productoId, $cantidad);

            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary
            ], "Producto agregado al carrito", 201);
        } catch (Exception $e) {
            error_log("Error en CarritoController::store: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * PUT /carrito - Actualizar cantidad o selección de un producto
     */
    public function update()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data || !isset($data['productoId'])) {
                Response::error("Datos inválidos: se requiere productoId", 400);
            }

            $productoId = (int)$data['productoId'];
            $usuarioId = $data['usuarioId'] ?? $this->getCurrentUserId();
            $carrito = $this->model->getOrCreateCart($usuarioId);

            // Determinar qué actualizar
            if (isset($data['cantidad'])) {
                $cantidad = (int)$data['cantidad'];
                if ($cantidad <= 0) {
                    // Si la cantidad es 0 o negativa, eliminar el producto
                    $this->model->removeItem($carrito['id'], $productoId);
                } else {
                    $this->model->updateItemQuantity($carrito['id'], $productoId, $cantidad);
                }
            }

            if (isset($data['seleccionado'])) {
                $seleccionado = (bool)$data['seleccionado'];
                $this->model->updateItemSelection($carrito['id'], $productoId, $seleccionado);
            }

            // Retornar carrito actualizado
            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary
            ], "Carrito actualizado correctamente");
        } catch (Exception $e) {
            error_log("Error en CarritoController::update: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /carrito - Eliminar items del carrito
     */
    public function delete()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $usuarioId = $data['usuarioId'] ?? $this->getCurrentUserId();
            $carrito = $this->model->getOrCreateCart($usuarioId);

            // Si se especifica productoId, eliminar solo ese producto
            if (isset($data['productoId'])) {
                $productoId = (int)$data['productoId'];
                $this->model->removeItem($carrito['id'], $productoId);
                $message = "Producto eliminado del carrito";
            }
            // Si soloSeleccionados es true, eliminar solo los seleccionados
            else if (isset($data['soloSeleccionados']) && $data['soloSeleccionados'] === true) {
                $this->model->clearSelectedItems($carrito['id']);
                $message = "Productos seleccionados eliminados del carrito";
            }
            // Si no, vaciar todo el carrito
            else {
                $this->model->clearCart($carrito['id']);
                $message = "Carrito vaciado correctamente";
            }

            // Retornar carrito actualizado
            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary
            ], $message);
        } catch (Exception $e) {
            error_log("Error en CarritoController::delete: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /carrito/migrate - Migrar carrito anónimo a usuario logueado
     */
    public function migrate()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (!$data || !isset($data['usuarioId'])) {
                Response::error("Se requiere usuarioId para migrar el carrito", 400);
            }

            $usuarioId = (int)$data['usuarioId'];

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $sessionId = session_id();

            $this->model->migrateAnonymousCart($sessionId, $usuarioId);

            // Obtener el carrito migrado
            $carrito = $this->model->getOrCreateCart($usuarioId);
            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary
            ], "Carrito migrado correctamente");
        } catch (Exception $e) {
            error_log("Error en CarritoController::migrate: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /carrito/resumen - Obtener solo el resumen del carrito
     */
    public function getSummary()
    {
        try {
            $usuarioId = $this->getCurrentUserId();
            $carrito = $this->model->getOrCreateCart($usuarioId);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success($summary, "Resumen del carrito obtenido");
        } catch (Exception $e) {
            error_log("Error en CarritoController::getSummary: " . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el ID del usuario actual desde la sesión/token
     */
    private function getCurrentUserId()
    {
        // Intentar obtener desde sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si hay usuario en sesión, retornar su ID
        if (isset($_SESSION['usuario_id'])) {
            return (int)$_SESSION['usuario_id'];
        }

        // Si no hay usuario logueado, retornar null para carrito anónimo
        return null;
    }

    /**
     * PUT /carrito/decrementar/{productoId} - Disminuir cantidad de un producto en 1
     */
    public function decrementar($productoId)
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $usuarioId = $data['usuarioId'] ?? $this->getCurrentUserId();

            $carrito = $this->model->getOrCreateCart($usuarioId);
            $this->model->decrementItemQuantity($carrito['id'], (int)$productoId);

            $items = $this->model->getCartItems($carrito['id']);
            $summary = $this->model->getCartSummary($carrito['id']);

            Response::success([
                'items' => $items,
                'resumen' => $summary
            ], "Cantidad disminuida correctamente");
        } catch (Exception $e) {
            error_log("Error en CarritoController::decrementar: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }
}
