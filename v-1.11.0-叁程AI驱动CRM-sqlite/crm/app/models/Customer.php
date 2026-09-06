<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Customer extends Model
{
    /** 客户编号：CUS-000007（自动生成，供 AI 与人工稳定引用） */
    protected ?string $publicCodePrefix = 'CUS';

    protected string $table = 'customers';

    /**
     * 字段语义注册表（稀疏）：结构看 schema.sql，这里只补语义。
     * searchable 列与改动前 $searchable 的清单一致 → 搜索行为不变。
     */
    protected static array $fields = [
        'name'        => ['label' => '姓名', 'type' => 'string', 'searchable' => true,
                          'required' => true, 'requiredMsg' => '客户姓名不能为空。'],
        'company'     => ['label' => '公司', 'searchable' => true],
        'email'       => ['label' => '邮箱', 'type' => 'email', 'emailValidate' => true, 'searchable' => true],
        'phone'       => ['label' => '电话', 'searchable' => true],
        'whatsapp'    => ['label' => 'WhatsApp', 'searchable' => true],
        'wechat'      => ['label' => '微信', 'searchable' => true],
        'facebook'    => ['label' => 'Facebook 主页'],
        'tiktok'      => ['label' => 'TikTok 频道'],
        'website'     => ['label' => '官方网站'],
        'source_country' => ['label' => '来源国家', 'searchable' => true],
        'source_city'    => ['label' => '来源城市'],
        'address'     => ['label' => '地址'],
        'shipping_address' => ['label' => '收货地址', 'type' => 'text'],
        'first_purchase_from_china' => ['label' => '是否首次从中国采购', 'type' => 'bool'],
        'has_import_capability'     => ['label' => '是否有进口能力', 'type' => 'bool'],
        'conversion_time' => ['label' => '转化时间', 'type' => 'datetime'],
        'status'      => ['label' => '状态', 'type' => 'enum', 'default' => 'active'],
        'notes'       => ['label' => '备注', 'type' => 'text', 'searchable' => true],
    ];

    /** All customers with their owner's name, newest first, optional search. */
    public function allWithOwner(string $search = '', int $page = 1, int $perPage = 15): array
    {
        $sql = "SELECT c.*, u.name AS owner_name
                FROM customers c
                LEFT JOIN users u ON u.id = c.owner_id";
        $params = [];

        if ($search !== '') {
            [$where, $sparams] = $this->searchWhere($search, 'c');
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
            [$where, $sparams] = $this->searchWhere($search, 'c');
            $sql .= ' WHERE ' . $where;
            $params = array_merge($params, $sparams);
        }

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
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
