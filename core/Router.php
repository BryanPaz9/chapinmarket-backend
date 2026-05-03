<?php
// core/Router.php - Versión corregida
require_once __DIR__ . '/Response.php';   // Asegura que Response esté disponible sin redeclarar

class Router
{
    private $routes = [];

    public function add($method, $path, $callback)
    {
        $pattern = preg_replace('#\{[\w]+\}#', '([\w-]+)', $path);
        preg_match_all('#\{([\w]+)\}#', $path, $paramNames);

        $this->routes[] = [
            'method'   => $method,
            'path'     => "#^" . $pattern . "$#",
            'callback' => $callback,
            'params'   => $paramNames[1]
        ];
    }

    public function dispatch($method, $uri)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['path'], $uri, $matches)) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }
                return call_user_func_array($route['callback'], $params);
            }
        }

        // Response ya está definida en Response.php
        Response::error("Ruta no encontrada", 404);
    }
}
