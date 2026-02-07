<?php

namespace Services;

use DateTime;
use RuntimeException;

class Validator
{
    private $errors = [];

    public function add($values, $labels, $patterns)
    {
        foreach ($patterns as $field => $rules) {
            $rules = explode('|', $rules);
            $value = $values[$field] ?? null;
            $label = $labels[$field] ?? $field;

            if ($value !== null) {
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    $this->errors[] = "O campo {$label} possui tipo inválido";
                    continue;
                }
            }

            $errorCount = count($this->errors);

            foreach ($rules as $rule) {
                $parts = explode(':', $rule, 2);
                $methodName = $parts[0];
                $method = 'validate' . ucfirst($methodName);

                if (!method_exists($this, $method)) {
                    throw new RuntimeException("Regra {$methodName} inválida para validação de {$label}");
                }

                if (isset($parts[1])) {
                    $this->$method($value, $label, $parts[1]);
                } else {
                    $this->$method($value, $label);
                }

                if (count($this->errors) > $errorCount) {
                    break;
                }
            }
        }

        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function validateRequired($value, $label)
    {
        if ($value === null) {
            $this->errors[] = "O campo {$label} é obrigatório";
        }
    }

    private function validateString($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;

        if (preg_match('/<[^>]*>/', $value)) {
            $this->errors[] = "O campo {$label} não pode conter HTML";
        }
    }

    private function validateInteger($value, $label)
    {
        if ($value === null) {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[] = "O campo {$label} deve ser um número inteiro";
        }
    }

    private function validateDecimal($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;
        $normalized = str_replace(',', '.', $value);

        if (filter_var($normalized, FILTER_VALIDATE_FLOAT) === false) {
            $this->errors[] = "O campo {$label} deve ser um número decimal";
        }
    }

    private function validateMin($value, $label, $min)
    {
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (mb_strlen($value, 'UTF-8') < $min) {
                $this->errors[] = "O campo {$label} deve ter no mínimo {$min} caracteres";
            }
            return;
        }

        if ($value < $min) {
            $this->errors[] = "O campo {$label} deve ser no mínimo {$min}";
        }
    }

    private function validateMax($value, $label, $max)
    {
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (mb_strlen($value, 'UTF-8') > $max) {
                $this->errors[] = "O campo {$label} deve ter no máximo {$max} caracteres";
            }
            return;
        }

        if ($value > $max) {
            $this->errors[] = "O campo {$label} deve ser no máximo {$max}";
        }
    }

    private function validateIn($value, $label, $content)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;
        $content = (string) $content;

        $list = explode(',', $content);
        $list = array_map('trim', $list);

        if (!in_array($value, $list, true)) {
            $this->errors[] = "O campo {$label} contém um valor inválido";
        }
    }

    private function validateDate($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);

            if ($date) {
                if ($date->format($format) === $value) {
                    return;
                }
            }
        }

        $this->errors[] = "O campo {$label} deve ser uma data válida";
    }

    private function validateCpf($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;
        $digits = preg_replace('/\D/', '', $value);
        $length = strlen($digits);

        if ($length !== 11 && $length !== 14) {
            $this->errors[] = "O campo {$label} deve conter 11 dígitos (CPF) ou 14 dígitos (CNPJ)";
        }
    }

    private function validateUsername($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;

        if (!preg_match('/^(?=.{4,30}$)[a-z]+(\.[a-z]+)*$/', $value)) {
            $this->errors[] = "O campo {$label} deve conter apenas letras minúsculas separadas por ponto e ter entre 4 e 30 caracteres";
        }
    }

    private function validatePassword($value, $label)
    {
        if ($value === null) {
            return;
        }

        $value = (string) $value;

        if (!preg_match('/^(?=.{6,30}$)[A-Za-z0-9]+$/', $value)) {
            $this->errors[] = "O campo {$label} deve conter apenas letras e números, entre 6 e 30 caracteres";
        }
    }
}
