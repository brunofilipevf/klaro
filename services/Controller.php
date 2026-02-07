<?php

namespace Services;

use RuntimeException;

abstract class Controller
{
    public function __get($name)
    {
        $services = [
            'request' => Request::class,
            'response' => Response::class,
            'session' => Session::class,
            'validator' => Validator::class,
            'view' => View::class,
        ];

        if (isset($services[$name])) {
            return Container::get($services[$name]);
        }

        throw new RuntimeException("Propriedade {$name} não encontrada no Controller");
    }

    public function render($path, $data = [])
    {
        $data['flash'] = $this->session->getFlash();
        $data['csrf'] = $this->session->getCsrf();
        return $this->view->render($path, $data);
    }
}
