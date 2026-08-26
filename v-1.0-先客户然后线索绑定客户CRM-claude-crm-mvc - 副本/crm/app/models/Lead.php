<?php

class Lead extends Model
{
    protected string $table = 'leads';

    public function allWithCustomer(string $status = ''): array
    {
        $sql = "SELECT l.*, c.name AS customer_name
                FROM leads l
                LEFT JOIN customers c ON c.id = l.customer_id";
        $params = [];

        if ($status !== '') {
            $sql .= " WHERE l.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY l.created_at DESC";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return $stmt->resultSet();
    }

    public function countByStatus(string $status): int
    {
        return $this->count('status = :status', [':status' => $status]);
    }
}
