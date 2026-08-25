<?php

class Customer extends Model
{
    protected string $table = 'customers';

    /** All customers with their owner's name, newest first, optional search. */
    public function allWithOwner(string $search = ''): array
    {
        $sql = "SELECT c.*, u.name AS owner_name
                FROM customers c
                LEFT JOIN users u ON u.id = c.owner_id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE c.name LIKE :search OR c.company LIKE :search OR c.email LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return $stmt->resultSet();
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
