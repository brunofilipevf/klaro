<?php

namespace Services;

use DateTime;
use RuntimeException;

class View
{
    private $data = [];
    private $vars = [];

    public function render($path, $data = [])
    {
        $data = array_merge($this->data, $data);
        return $this->resolve($path, $data);
    }

    private function resolve($path, $data)
    {
        $path = preg_replace('/[^a-z0-9\/_-]/i', '', $path);
        $file = ABSPATH . '/views/' . ltrim($path, '/') . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("View {$path} não encontrada");
        }

        extract($data, EXTR_SKIP);

        $include = function ($path) use ($data) {
            echo $this->includePart($path, $data);
        };

        $set = function ($key, $value) {
            return $this->setVar($key, $value);
        };

        $get = function ($key) {
            echo $this->escape($this->getVar($key));
        };

        $url = function ($path) {
            echo $this->escape($this->getUrl($path));
        };

        $now = function ($format) {
            echo $this->escape($this->getNow($format));
        };

        $e = function ($value, $filters = []) {
            echo $this->escape($value, $filters);
        };

        ob_start();
        require $file;
        return ob_get_clean();
    }

    private function includePart($path, $data)
    {
        return $this->resolve($path, $data);
    }

    private function setVar($key, $value)
    {
        $this->vars[$key] = $value;
    }

    private function getVar($key)
    {
        if ($key === 'app_name') {
            return APP_NAME;
        }

        if ($key === 'app_description') {
            return APP_DESC;
        }

        if (isset($this->vars[$key])) {
            return $this->vars[$key];
        }

        return null;
    }

    private function getUrl($path)
    {
        return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    }

    private function getNow($format)
    {
        if (!is_string($format)) {
            return '';
        }

        if ($format === '') {
            return '';
        }

        $date = new DateTime();
        $result = $date->format($format);

        if ($result === false) {
            return '';
        }

        return $result;
    }

    private function escape($value, $filters = [])
    {
        if ($value === null) {
            $value = '';
        }

        if (is_array($value)) {
            $value = '';
        }

        if (is_object($value)) {
            $value = '';
        }

        if (is_resource($value)) {
            $value = '';
        }

        $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ((array) $filters as $filter) {
            $method = 'filter' . ucfirst($filter);

            if (method_exists($this, $method)) {
                $value = $this->$method($value);
            }
        }

        return $value;
    }

    private function filterLineBreaks($value)
    {
        return str_replace(["\r\n", "\r", "\n"], '<br>', $value);
    }

    private function filterUppercase($value)
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    private function filterLowercase($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }

    private function filterDate($value)
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        if ($date === false) {
            return $value;
        }

        if ($date->format('Y-m-d') !== $value) {
            return $value;
        }

        return $date->format('d/m/Y');
    }

    private function filterDateTime($value)
    {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);

        if ($date === false) {
            return $value;
        }

        if ($date->format('Y-m-d H:i:s') !== $value) {
            return $value;
        }

        return $date->format('d/m/Y H:i:s');
    }

    private function filterDecimal($value)
    {
        if (!is_numeric($value)) {
            return $value;
        }

        return number_format((float) $value, 2, ',', '.');
    }

    private function filterCpf($value)
    {
        $digits = preg_replace('/\D/', '', $value);
        $length = strlen($digits);

        if ($length === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        if ($length === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        return $value;
    }

    private function filterDash($value)
    {
        if ($value === '') {
            return '—';
        }

        return $value;
    }
}
