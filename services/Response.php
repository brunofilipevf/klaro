<?php

namespace Services;

class Response
{
    public function send($content, $statusCode = 200)
    {
        $content = (string) $content;

        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8', true);
        header('Content-Length: ' . strlen($content), true);

        echo $content;
    }

    public function redirect($path, $statusCode = 302)
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            throw new RuntimeException('Redirecionamento externo não permitido');
        }

        if (str_contains($path, '//')) {
            throw new RuntimeException('Path com barras duplas não permitido');
        }

        $url = rtrim(APP_URL, '/') . '/' . ltrim($path, '/');

        http_response_code($statusCode);
        header('Location: ' . $url, true);
        header('Content-Length: 0', true);

        exit;
    }
}
