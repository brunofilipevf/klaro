<?php

namespace Services;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    private static $instance;
    private $instances = [];
    private $resolving = [];

    public static function get($class)
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance->reflectClass($class);
    }

    public function reflectClass($class)
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Classe {$class} não encontrada");
        }

        if (isset($this->resolving[$class])) {
            throw new RuntimeException("Dependência circular detectada em {$class}");
        }

        $this->resolving[$class] = true;

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            $instance = new $class;
        } else {
            $args = [];

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = self::get($type->getName());
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }

            $instance = $reflection->newInstanceArgs($args);
        }

        $this->instances[$class] = $instance;
        unset($this->resolving[$class]);

        return $instance;
    }
}
