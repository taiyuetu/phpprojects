<?php

/**
 * Base Model
 *
 * Child models set $table (and optionally $primaryKey) and get
 * simple, ready-to-use CRUD helpers on top of the Database wrapper.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
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

    /**
     * Stable human/AI-facing code, e.g. CUS-000007.
     *
     * Models that set $publicCodePrefix get one written on create. It is derived
     * from the row id rather than random: unique by construction, reproducible in
     * a migration, readable aloud — and a model that invents a code simply gets
     * "编号不存在" instead of a wrong record.
     */
    protected ?string $publicCodePrefix = null;

    public function publicCode(int $id): string
    {
        return (($this->publicCodePrefix ?? '') ?: strtoupper(substr($this->table, 0, 3))) . '-' . sprintf('%06d', $id);
    }

    /** The row's code, derived when the column is still empty (hand-made rows). */
    public function codeOf($row): string
    {
        if (!is_array($row)) {
            return '';
        }
        $stored = trim((string) ($row['public_code'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }
        $id = (int) ($row['id'] ?? 0);
        return $id > 0 && $this->publicCodePrefix !== null ? $this->publicCode($id) : '';
    }

    /**
     * Turn a reference into a real row id. Accepts CUS-000007, cus 7, CUS000007,
     * #7 or a bare 7 — humans and models both type these, and all of them mean
     * the same row. Returns null when nothing matches (never a guess).
     */
    public function idFromReference(string $ref): ?int
    {
        $t = strtoupper((string) preg_replace('~[^0-9A-Za-z]~', '', $ref));
        if ($t === '') {
            return null;
        }
        $prefix = strtoupper((string) ($this->publicCodePrefix ?? ''));
        if ($prefix !== '' && str_starts_with($t, $prefix)) {
            $t = substr($t, strlen($prefix));
        }
        if ($prefix !== '' && ctype_alpha($t)) {
            return null;                                        // 只有前缀、没有编号数字
        }
        if ($t === '' || !ctype_digit($t)) {
            return null;
        }
        $id = (int) $t;
        if ($id <= 0 || $id > 1e9) {
            return null;
        }
        return $this->find($id) ? $id : null;
    }

    /** Fill in a missing code on an existing row (used when a legacy row is read). */
    public function ensurePublicCode(int $id): string
    {
        $code = $this->publicCode($id);
        if ($this->publicCodePrefix === null) {
            return $code;
        }
        $this->db->query("UPDATE {$this->table} SET public_code = :c WHERE id = :id AND (public_code IS NULL OR public_code = '')")
            ->bind(':c', $code)->bind(':id', $id)->execute();
        return $code;
    }

    public function create(array $data): int
    {
        unset($data['public_code']);                     // generated, never supplied
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->query($sql);
        foreach ($data as $key => $value) {
            $stmt->bind(':' . $key, $value);
        }
        $stmt->execute();

        $id = (int) $this->db->lastInsertId();
        if ($id > 0 && $this->publicCodePrefix !== null) {
            // the id only exists after the INSERT, so the code lands right after it
            $this->db->query("UPDATE {$this->table} SET public_code = :c WHERE id = :id")
                ->bind(':c', $this->publicCode($id))->bind(':id', $id)->execute();
        }
        return $id;
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
