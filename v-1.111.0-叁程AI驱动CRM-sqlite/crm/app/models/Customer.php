<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Customer extends Model
{
    /** 客户编号：CUS-000007（自动生成，供 AI 与人工稳定引用） */
    protected ?string $publicCodePrefix = 'CUS';

    protected string $table = 'customers';

    /** All customers with their owner's name, newest first, optional search. */
    public function allWithOwner(string $search = '', int $page = 1, int $perPage = 15): array
    {
        $sql = "SELECT c.*, u.name AS owner_name
                FROM customers c
                LEFT JOIN users u ON u.id = c.owner_id";
        $params = [];

        if ($search !== '') {
            [$where, $sparams] = $this->customerSearchWhere($search);
            $sql .= ' WHERE ' . $where;
            $params = array_merge($params, $sparams);
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        $stmt->bind(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bind(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        return $stmt->resultSet();
    }

    /** Count customers matching optional search. */
    public function countWithOwner(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS total FROM customers c";
        $params = [];

        if ($search !== '') {
            [$where, $sparams] = $this->customerSearchWhere($search);
            $sql .= ' WHERE ' . $where;
            $params = array_merge($params, $sparams);
        }

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
    }

    /**
     * 跨列搜索的 WHERE 片段（c 是 customers 的别名）。
     *
     * 用户输入的 '%' / '_' 必须先转义，否则一个 % 就是“匹配整表”；SQLite 的 LIKE
     * 默认不设 escape 字符，所以每一处 LIKE 都要带 ESCAPE '\'（与 Ai::likeValue() 同口径）。
     *
     * @return array{0:string, 1:array<string,string>} [where 片段, 参数]
     */
    private function customerSearchWhere(string $search): array
    {
        $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $bits = [];
        $params = [];
        foreach (['name', 'company', 'email', 'phone', 'whatsapp', 'wechat', 'source_country', 'notes'] as $col) {
            $key = ':sc_' . $col;
            $bits[] = "c.{$col} LIKE {$key} ESCAPE '\\'";
            $params[$key] = $term;
        }
        return [implode(' OR ', $bits), $params];
    }

    public function findWithOwner(int $id)
    {
        return $this->db()->query(
            "SELECT c.*, u.name AS owner_name
             FROM customers c
             LEFT JOIN users u ON u.id = c.owner_id
             WHERE c.id = :id"
        )->bind(':id', $id)->single();
    }

    /** Deals belonging to this customer. */
    public function deals(int $customerId): array
    {
        return $this->db()->query(
            "SELECT * FROM deals WHERE customer_id = :id ORDER BY created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    /** Leads associated with this customer (converted leads). */
    public function leads(int $customerId): array
    {
        return $this->db()->query(
            "SELECT l.*, u.name AS owner_name
             FROM leads l
             LEFT JOIN users u ON u.id = l.owner_id
             WHERE l.customer_id = :id
             ORDER BY l.created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    /** Get the lead that was converted to create this customer. */
    public function convertedLead(int $customerId): ?array
    {
        $result = $this->db()->query(
            "SELECT l.*, u.name AS owner_name
             FROM leads l
             LEFT JOIN users u ON u.id = l.owner_id
             WHERE l.customer_id = :id AND l.status = 'qualified'
             ORDER BY l.created_at DESC
             LIMIT 1"
        )->bind(':id', $customerId)->single();
        return $result ?: null;
    }

    /** Activity timeline for this customer. */
    public function activities(int $customerId): array
    {
        return $this->db()->query(
            "SELECT a.*, u.name AS user_name
             FROM activities a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.customer_id = :id
             ORDER BY a.created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    public function addActivity(int $customerId, ?int $userId, string $type, string $description): int
    {
        return $this->db()->query(
            "INSERT INTO activities (customer_id, user_id, type, description) VALUES (:cid, :uid, :type, :desc)"
        )->bind(':cid', $customerId)
         ->bind(':uid', $userId)
         ->bind(':type', $type)
         ->bind(':desc', $description)
         ->execute() ? (int) $this->db()->lastInsertId() : 0;
    }
}
