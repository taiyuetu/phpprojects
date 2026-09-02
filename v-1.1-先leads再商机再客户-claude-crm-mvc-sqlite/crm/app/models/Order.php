<?php

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

    /** Generate next order number. */
    public function generateOrderNumber(): string
    {
        $year = date('Y');
        $prefix = "ORD-{$year}-";

        $lastOrder = $this->db()->query(
            "SELECT order_number FROM orders WHERE order_number LIKE :prefix ORDER BY id DESC LIMIT 1"
        )->bind(':prefix', $prefix . '%')->single();

        if ($lastOrder) {
            $lastNum = (int) substr($lastOrder['order_number'], -3);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
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
