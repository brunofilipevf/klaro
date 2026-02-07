<?php

use Services\Container;
use Services\Router;

define('ABSPATH', dirname(__DIR__));

try {
    require ABSPATH . '/boot/app.php';

    $router = Container::get(Router::class);

    require ABSPATH . '/routes/web.php';

    $router->dispatch();
} catch (Throwable $e) {
    error_log((string) $e);

    $content = 'Erro interno no servidor';

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8', true);
    header('Content-Length: ' . strlen($content), true);

    echo $content;
}
