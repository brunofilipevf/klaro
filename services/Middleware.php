<?php

namespace Services;

use RuntimeException;

abstract class Middleware
{
    public function __get($name)
    {
        $services = [
            'request' => Request::class,
            'response' => Response::class,
            'session' => Session::class,
        ];

        if (isset($services[$name])) {
            return Container::get($services[$name]);
        }

        throw new RuntimeException("Propriedade {$name} não encontrada no Middleware");
    }
}
