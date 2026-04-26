<?php

require_once __DIR__ . '/Response.php';

class Router {

    private $routes = [];

    public function add($method, $path, $callback) {
        // Convertir {param} → regex
        $pattern = preg_replace('#\{[\w]+\}#', '([\w-]+)', $path);

        // Guardar nombres de parámetros
        preg_match_all('#\{([\w]+)\}#', $path, $paramNames);

        $this->routes[] = [
            'method' => $method,
            'path' => "#^" . $pattern . "$#",
            'callback' => $callback,
            'params' => $paramNames[1]
        ];
    }

    public function dispatch($method, $uri) {

        foreach ($this->routes as $route) {

            if ($route['method'] !== $method) continue;

            if (preg_match($route['path'], $uri, $matches)) {

                array_shift($matches); // quitar match completo

                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }

                return call_user_func_array($route['callback'], $params);
            }
        }

        Response::json(["error" => "Ruta no encontrada"], 404);
    }
}