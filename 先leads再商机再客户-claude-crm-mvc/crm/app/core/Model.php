<?php

/**
 * Base Model
 *
 * Child models set $table (and optionally $primaryKey) and get
 * simple, ready-to-use CRUD helpers on top of the Database wrapper.
 */
abstract class Model
{
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = new Database();
    }

    public function all(string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        return $this->db->query($sql)->resultSet();
    }

    public function find(int $id)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id")
            ->bind(':id', $id)
            ->single();
    }

    public function findBy(string $column, $value)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :value")
            ->bind(':value', $value)
            ->single();
    }

    public function where(string $column, $value, string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = :value";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        return $this->db->query($sql)->bind(':value', $value)->resultSet();
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->query($sql);
        foreach ($data as $key => $value) {
            $stmt->bind(':' . $key, $value);
        }
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = :id";

        $stmt = $this->db->query($sql);
        foreach ($data as $key => $value) {
            $stmt->bind(':' . $key, $value);
        }
        $stmt->bind(':id', $id);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        return $this->db->query("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id")
            ->bind(':id', $id)
            ->execute();
    }

    public function count(string $where = null, array $params = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $stmt = $this->db->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
    }

    /** Escape hatch for custom queries within a model. */
    protected function db(): Database
    {
        return $this->db;
    }
}
