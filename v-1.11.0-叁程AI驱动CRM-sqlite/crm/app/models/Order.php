<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Order extends Model
{
    protected string $table = 'orders';

    /**
     * 字段语义注册表（稀疏）：结构看 schema.sql，这里补语义。
     * 金额 amount 以明细合计为准，表单里的 hidden 只做兜底；状态枚举来自 DB CHECK。
     */
    protected static array $fields = [
        'order_number'    => ['label' => '订单编号', 'type' => 'string',
                              'required' => true, 'requiredMsg' => '订单编号不能为空。'],
        'deal_id'         => ['label' => '关联商机', 'type' => 'int'],
        'customer_id'     => ['label' => '客户', 'type' => 'int',
                              'required' => true, 'requiredMsg' => '请选择一个客户。'],
        'title'           => ['label' => '订单标题', 'type' => 'string', 'searchable' => true,
                              'required' => true, 'requiredMsg' => '订单标题不能为空。'],
        'amount'          => ['label' => '金额', 'type' => 'number', 'default' => '0'],
        'status'          => ['label' => '状态', 'type' => 'enum', 'default' => 'pending'],
        'payment_status'  => ['label' => '付款状态', 'type' => 'enum', 'default' => 'unpaid'],
        'order_date'      => ['label' => '下单日期', 'type' => 'date', 'defaultToday' => true],
        'delivery_date'   => ['label' => '交货日期', 'type' => 'date'],
        'shipping_address' => ['label' => '收货地址', 'type' => 'text'],
        'notes'           => ['label' => '备注', 'type' => 'text', 'searchable' => true],
    ];

    /**
     * 订单列表关键词搜索覆盖的列。c./d./ 带前缀的条目搜索的是 JOIN 进来的
     * 客户与商机，所以 countOrders() 里也要带着 JOIN（见下）。
     */
    protected array $searchable = [
        'order_number', 'title', 'shipping_address', 'notes',
        'c.name', 'c.company', 'd.title',
    ];

    /**
     * All orders with customer and deal info, newest first.
     * $status = 状态精确筛选，$search = 跨列关键词（$search 在参数末位，老调用不受影响）。
     */
    public function allOrders(string $status = '', int $page = 1, int $perPage = 15, string $search = ''): array
    {
        $sql = "SELECT o.*, c.name AS customer_name, c.company AS customer_company,
                       d.title AS deal_title, u.name AS owner_name
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN deals d ON d.id = o.deal_id
                LEFT JOIN users u ON u.id = o.owner_id";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'o');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        $stmt->bind(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bind(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        return $stmt->resultSet();
    }

    /**
     * Count orders matching optional status filter and/or keyword.
     * 搜索列含 c.name / d.title，所以关键词模式必须 JOIN 客户/商机表（id 唯一，不放大行数）。
     */
    public function countOrders(string $status = '', string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS total FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN deals d ON d.id = o.deal_id";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'o');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
    }

    /** Count orders by status. */
    public function countByStatus(string $status): int
    {
        return $this->count('status = :status', [':status' => $status]);
    }

    /** Get orders for a specific customer. */
    public function byCustomer(int $customerId): array
    {
        return $this->db()->query(
            "SELECT o.*, d.title AS deal_title, u.name AS owner_name
             FROM orders o
             LEFT JOIN deals d ON d.id = o.deal_id
             LEFT JOIN users u ON u.id = o.owner_id
             WHERE o.customer_id = :id
             ORDER BY o.created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    /** Get orders for a specific deal. */
    public function byDeal(int $dealId): array
    {
        return $this->db()->query(
            "SELECT o.*, c.name AS customer_name, u.name AS owner_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             LEFT JOIN users u ON u.id = o.owner_id
             WHERE o.deal_id = :id
             ORDER BY o.created_at DESC"
        )->bind(':id', $dealId)->resultSet();
    }

    /** Find order with all related info. */
    public function findWithDetails(int $id): ?array
    {
        $result = $this->db()->query(
            "SELECT o.*, c.name AS customer_name, c.company AS customer_company,
                    c.email AS customer_email, c.phone AS customer_phone,
                    d.title AS deal_title, d.stage AS deal_stage,
                    u.name AS owner_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             LEFT JOIN deals d ON d.id = o.deal_id
             LEFT JOIN users u ON u.id = o.owner_id
             WHERE o.id = :id"
        )->bind(':id', $id)->single();
        return $result ?: null;
    }

    /** Is this order_number already in use by another order? */
    public function numberInUse(string $number, ?int $exceptId = null): bool
    {
        if (trim($number) === '') {
            return false;
        }
        $sql = 'SELECT id FROM orders WHERE order_number = :n';
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
        }
        $stmt = $this->db()->query($sql)->bind(':n', trim($number));
        if ($exceptId !== null) {
            $stmt->bind(':id', $exceptId, PDO::PARAM_INT);
        }
        return (bool) $stmt->single();
    }

    /**
     * Generate next order number.
     *
     * The suffix is derived from the current highest number, but that only gives
     * a *suggestion*: the same second two people open the create page and both
     * submit it, or a client tampered the hidden field to a number that already
     * exists. So after guessing, bump past any number that is actually taken —
     * the DB UNIQUE constraint stays the last line of defence, never the only one.
     */
    public function generateOrderNumber(): string
    {
        $year = date('Y');
        $prefix = "ORD-{$year}-";

        $lastOrder = $this->db()->query(
            "SELECT order_number FROM orders WHERE order_number LIKE :prefix ORDER BY id DESC LIMIT 1"
        )->bind(':prefix', $prefix . '%')->single();

        $nextNum = $lastOrder ? (int) substr($lastOrder['order_number'], -3) + 1 : 1;
        do {
            $candidate = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $nextNum++;
        } while ($this->numberInUse($candidate));

        return $candidate;
    }

    /** Get total order amount. */
    public function totalAmount(?string $status = null): float
    {
        $sql = "SELECT COALESCE(SUM(amount),0) AS total FROM orders";
        $params = [];
        if ($status) {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }
        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        $row = $stmt->single();
        return (float) ($row['total'] ?? 0);
    }

    /** Get order status options. */
    public static function statusOptions(): array
    {
        return [
            'pending'    => '待确认',
            'confirmed'  => '已确认',
            'processing' => '处理中',
            'shipped'    => '已发货',
            'delivered'  => '已送达',
            'completed'  => '已完成',
            'cancelled'  => '已取消',
        ];
    }

    /** Get payment status options. */
    public static function paymentStatusOptions(): array
    {
        return [
            'unpaid'  => '未付款',
            'partial' => '部分付款',
            'paid'    => '已付款',
        ];
    }

    /** Get status label. */
    public static function statusLabel(string $status): string
    {
        $options = self::statusOptions();
        return $options[$status] ?? '未知状态';
    }

    /** Get payment status label. */
    public static function paymentStatusLabel(string $status): string
    {
        $options = self::paymentStatusOptions();
        return $options[$status] ?? '未知状态';
    }
}
