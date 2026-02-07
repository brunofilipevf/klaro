<?php

namespace Services;

use RuntimeException;

class Router
{
    private $routes = [];

    public function get($path, $handler, $middlewares = [])
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post($path, $handler, $middlewares = [])
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    private function add($method, $path, $handler, $middlewares)
    {
        $pattern = '#^' . str_replace('{id}', '([1-9][0-9]*)', $path) . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => (array) $middlewares,
        ];
    }

    private function runMiddlewares($middlewares)
    {
        foreach ($middlewares as $middleware) {
            if ($middleware === '') {
                continue;
            }

            $class = 'App\\Middlewares\\' . $middleware;
            $instance = Container::get($class);

            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException("Middleware {$class} sem método handle");
            }

            $instance->handle();
        }
    }

    private function runController($handler, $params)
    {
        if (!str_contains($handler, '@')) {
            throw new RuntimeException("Handler inválido: {$handler}. Formato esperado: Controller@method");
        }

        [$controller, $method] = explode('@', $handler, 2);

        if ($controller === '' || $method === '') {
            throw new RuntimeException("Handler inválido: {$handler}. Controller ou método ausente");
        }

        $class = 'App\\Controllers\\' . $controller;
        $instance = Container::get($class);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Método {$method} não encontrado em {$class}");
        }

        $instance->$method(...$params);
    }

    public function dispatch()
    {
        $request = Container::get(Request::class);
        $response = Container::get(Response::class);

        $method = $request->getMethod();
        $uri = $request->getUri();

        if (!isset($this->routes[$method])) {
            return $response->send('Método HTTP não suportado', 405);
        }

        foreach ($this->routes[$method] as $route) {
            if (!preg_match($route['pattern'], $uri, $params)) {
                continue;
            }

            array_shift($params);
            $this->runMiddlewares($route['middlewares']);
            $this->runController($route['handler'], $params);
            return;
        }

        return $response->send('Página não encontrada', 404);
    }
}
