<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Order extends Model
{
    protected string $table = 'orders';

    /** All orders with customer and deal info, newest first, optional status filter. */
    public function allOrders(string $status = '', int $page = 1, int $perPage = 15): array
    {
        $sql = "SELECT o.*, c.name AS customer_name, c.company AS customer_company,
                       d.title AS deal_title, u.name AS owner_name
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                LEFT JOIN deals d ON d.id = o.deal_id
                LEFT JOIN users u ON u.id = o.owner_id";
        $params = [];

        if ($status !== '') {
            $sql .= " WHERE o.status = :status";
            $params[':status'] = $status;
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

    /** Count orders matching optional status filter. */
    public function countOrders(string $status = ''): int
    {
        $sql = "SELECT COUNT(*) AS total FROM orders o";
        $params = [];

        if ($status !== '') {
            $sql .= " WHERE o.status = :status";
            $params[':status'] = $status;
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
