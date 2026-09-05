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

    /**
     * 列表关键词搜索扫哪些列（跨列 OR LIKE，见 searchWhere()）。
     *
     * 普通场景不在这里声明：改在 static::$fields 里给对应列标 searchable，
     * searchableColumns() 会自动收集。这个数组只留给“要搜 JOIN 进来的列”这类
     * 特殊场景做整体覆盖（如 orders/deals 搜客户名：['c.name', 'd.title']）。
     *
     * 两者都为空时按表结构自动推断：文本类列剔除主键/编号/时间戳/布尔/金额/枚举。
     */
    protected array $searchable = [];

    /**
     * 字段语义注册表（稀疏）：只写数据库推不出来的部分。
     *
     *     键 = 列名；值 = ['label' => 中文名, 'type' => 'email', 'searchable' => true, ...]
     *
     * 结构本身仍以 schema.sql / Schema 为权威，这里负责补“中文叫什么、搜不搜、
     * 表单什么控件、CSV 怎么导出”等语义。引擎见 core/Fields.php。
     */
    protected static array $fields = [];

    /** 本模型的字段语义声明（子类覆写 static::$fields） */
    public function fieldDefs(): array
    {
        return static::$fields;
    }

    /** 静态版字段声明：供没有实例的地方（如 Fields::declaredFor）读取 */
    public static function fieldDefsStatic(): array
    {
        return static::$fields;
    }

    /** 注册表里标了 form 的列（供 partials/_fields_auto.php 自动渲染进表单） */
    public function autoFormFields(): array
    {
        return Fields::autoFormFields($this->table, static::$fields);
    }

    /** 本次关键词搜索实际扫的列：$searchable 覆盖 > 注册表 searchable > 自动推断。 */
    public function searchableColumns(): array
    {
        if ($this->searchable !== []) {
            return $this->searchable;
        }
        return Fields::searchableColumns($this->table, static::$fields);
    }

    /**
     * 关键词 → 跨列 LIKE 的 (WHERE 片段, 参数)。
     *
     * 用户输入里的 '%' / '_' 必须先转义：SQLite 的 LIKE 默认不设 escape 字符，
     * 一个 % 就是“匹配整表”；所以每一处 LIKE 都要带 ESCAPE '\\'（与 Ai::likeValue()
     * 同口径）。本方法只生成片段，由调用方并入 WHERE 并合并参数。
     * 片段自带一对圆括号（(... OR ...)），与 archived/status 等其他条件用 AND
     * 拼接时不会被 OR 的优先级破坏（回归：归档页曾把未归档行一起搜出来）。
     *
     * @return array{0:string, 1:array<string,string>} [WHERE 片段, 参数]
     */
    protected function searchWhere(string $search, string $alias = ''): array
    {
        $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $bits = [];
        $params = [];
        foreach ($this->searchableColumns() as $col) {
            $expr = str_contains($col, '.') ? $col : ($alias !== '' ? "{$alias}.{$col}" : $col);
            $key = ':kw_' . count($bits);
            $bits[] = "{$expr} LIKE {$key} ESCAPE '\\'";
            $params[$key] = $term;
        }
        return ['(' . implode(' OR ', $bits) . ')', $params];
    }

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

    /**
     * 通用输入清洗/校验：以 static::$fields 为白名单交给 Fields::sanitize()，
     * 再补上需要查库的唯一性检查。取代各 Controller 手写的 validate()。
     *
     * @param array<string,mixed> $ctx selfId（编辑时排除自身）等上下文
     * @return array{0:array<string,mixed>, 1:array<int,string>} [data, errors]
     */
    public function sanitizeInput(array $input, array $ctx = []): array
    {
        $ctx += [
            'selfId'      => 0,
            'enumOptions' => fn(string $field): ?array => $this->fieldEnumOptions($field),
        ];
        [$data, $errors] = Fields::sanitize($this->table, static::$fields, $input, $ctx);

        foreach (static::$fields as $name => $meta) {
            if (empty($meta['unique'])) {
                continue;
            }
            $v = $data[$name] ?? null;
            if ($v === null || (is_scalar($v) && (string) $v === '')) {
                continue;
            }
            if ($this->fieldUniqueTaken((string) $name, (string) $v, (int) $ctx['selfId'])) {
                $labels = Fields::columns($this->table, static::$fields);
                $label = (string) ($labels[$name]['label'] ?? $name);
                $errors[] = $label . '「' . (string) $v . '」已被其它商品占用，换个编号或直接留空。';
            }
        }
        return [$data, $errors];
    }

    /**
     * 某个字段的值是否已被别的行占用（unique 声明的查库实现）。
     * 默认放行；商品类目用它做 SKU 唯一性（子类覆写）。
     */
    protected function fieldUniqueTaken(string $field, string $value, int $selfId): bool
    {
        return false;
    }

    /**
     * 注册表外补充的可选值（数据库没写 CHECK、选项在 PHP 里的列，如商品单位）。
     * 返回 null 表示该字段没有额外可选项。
     */
    public function fieldEnumOptions(string $field): ?array
    {
        return null;
    }

    /** Escape hatch for custom queries within a model. */
    protected function db(): Database
    {
        return $this->db;
    }
}
