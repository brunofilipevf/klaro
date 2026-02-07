<?php

namespace Services;

use PDO;
use RuntimeException;

class Database
{
    private $connection;

    private function connect()
    {
        if ($this->connection) {
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    public function beginTransaction()
    {
        $this->connect();
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        $this->connect();
        return $this->connection->commit();
    }

    public function rollBack()
    {
        $this->connect();
        return $this->connection->rollBack();
    }

    public function query($sql)
    {
        throw new RuntimeException('Acesso ao recurso query() proibido');
    }

    public function exec($sql)
    {
        throw new RuntimeException('Acesso ao recurso exec() proibido');
    }

    public function prepare($sql)
    {
        $this->connect();
        return $this->connection->prepare($sql);
    }

    public function lastInsertId()
    {
        $this->connect();
        return $this->connection->lastInsertId();
    }
}
