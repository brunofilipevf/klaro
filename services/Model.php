<?php

namespace Services;

abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $columns = ['*'];
    protected $fillable = [];
    protected $hidden = [];
    protected $parts = [];
    protected $params = [];
    protected $attributes = [];

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        if ($name === 'db') {
            return Container::get(Database::class);
        }

        return null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function fill($attributes)
    {
        if (empty($this->fillable)) {
            $this->attributes = $attributes;
            return $this;
        }

        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function select($columns = ['*'])
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $validated = [];

        foreach ($columns as $col) {
            if ($col === '*') {
                $validated[] = '*';
                continue;
            }

            if (preg_match('/^[a-zA-Z0-9_]+\.\*$/', $col)) {
                $validated[] = $col;
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $col)) {
                throw new RuntimeException("Coluna inválida: {$col}");
            }

            $validated[] = $col;
        }

        $this->parts['select'] = implode(', ', $validated);
        return $this;
    }

    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new RuntimeException("Nome de tabela inválido: {$table}");
        }

        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $first)) {
            throw new RuntimeException("Coluna inválida: {$first}");
        }

        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $second)) {
            throw new RuntimeException("Coluna inválida: {$second}");
        }

        if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>='])) {
            throw new RuntimeException("Operador inválido: {$operator}");
        }

        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'])) {
            throw new RuntimeException("Tipo de join inválido: {$type}");
        }

        $this->parts['join'][] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function innerJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'INNER');
    }

    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new RuntimeException("Coluna inválida: {$column}");
        }

        if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>='], true)) {
            throw new RuntimeException("Operador inválido: {$operator}");
        }

        $placeholder = ':w' . count($this->params);

        $this->parts['where'][] = "{$column} {$operator} {$placeholder}";
        $this->params[$placeholder] = $value;

        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new RuntimeException("Coluna inválida: {$column}");
        }

        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new RuntimeException("Direção inválida: {$direction}");
        }

        $this->parts['order'][] = "{$column} {$direction}";
        return $this;
    }

    public function limit($limit)
    {
        $this->parts['limit'] = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->parts['offset'] = $offset;
        return $this;
    }

    public function find($id)
    {
        return $this->where($this->primaryKey, $id)->first();
    }

    public function first()
    {
        $this->limit(1);

        $stmt = $this->execute();
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return (new static)->fill($data);
    }

    public function get()
    {
        $stmt = $this->execute();
        $results = $stmt->fetchAll();

        return array_map(fn($item) => (new static)->fill($item), $results);
    }

    public function all()
    {
        return $this->get();
    }

    public function count()
    {
        $original = $this->parts;

        $this->parts['select'] = 'COUNT(*) as total';

        unset($this->parts['order'], $this->parts['limit'], $this->parts['offset']);

        $stmt = $this->execute();
        $result = $stmt->fetch();

        $this->parts = $original;

        return (int) $result['total'];
    }

    public function paginate($page = 1, $perPage = 15)
    {
        if ($page < 1) {
            $page = 1;
        }

        $total = $this->count();
        $pages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $this->limit($perPage);
        $this->offset($offset);

        return [
            'data' => $this->get(),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $pages,
            ],
        ];
    }

    public function create($data)
    {
        $model = new static;
        $model->fill($data);
        $attributes = $model->attributes;

        if (empty($attributes)) {
            return null;
        }

        $columns = implode(', ', array_keys($attributes));
        $values = ':' . implode(', :', array_keys($attributes));

        $sql = "INSERT INTO {$this->getTable()} ({$columns}) VALUES ({$values})";

        $stmt = $this->db->prepare($sql);

        foreach ($attributes as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();

        $id = $this->db->lastInsertId();

        return $this->find($id);
    }

    public function update($id, $data)
    {
        if (is_array($id)) {
            $data = $id;
            $id = $this->attributes[$this->primaryKey] ?? null;
        }

        $model = new static;
        $model->fill($data);
        $attributes = $model->attributes;

        if (empty($attributes)) {
            return false;
        }

        $set = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($attributes)));

        $sql = "UPDATE {$this->getTable()} SET {$set} WHERE {$this->primaryKey} = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);

        foreach ($attributes as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        return $stmt->execute();
    }

    public function delete($id = null)
    {
        if ($id === null) {
            $id = $this->attributes[$this->primaryKey] ?? null;
        }

        $sql = "DELETE FROM {$this->getTable()} WHERE {$this->primaryKey} = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);

        return $stmt->execute();
    }

    public function toArray()
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }

        return $attributes;
    }

    protected function execute()
    {
        $query = $this->buildQuery();
        $stmt = $this->db->prepare($query);
        $stmt->execute($this->params);

        return $stmt;
    }

    protected function buildQuery()
    {
        $select = $this->parts['select'] ?? implode(', ', $this->columns);

        $query = ["SELECT {$select} FROM {$this->getTable()}"];

        if (isset($this->parts['join'])) {
            $query[] = implode(' ', $this->parts['join']);
        }

        if (isset($this->parts['where'])) {
            $query[] = 'WHERE ' . implode(' AND ', $this->parts['where']);
        }

        if (isset($this->parts['order'])) {
            $query[] = 'ORDER BY ' . implode(', ', $this->parts['order']);
        }

        if (isset($this->parts['limit'])) {
            $query[] = "LIMIT {$this->parts['limit']}";
        }

        if (isset($this->parts['offset'])) {
            $query[] = "OFFSET {$this->parts['offset']}";
        }

        return implode(' ', $query);
    }

    protected function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        $class = explode('\\', static::class);
        return strtolower(end($class)) . 's';
    }
}
