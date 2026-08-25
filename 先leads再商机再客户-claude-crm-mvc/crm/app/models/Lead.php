<?php

class Lead extends Model
{
    protected string $table = 'leads';

    /** All leads, newest first, optional status filter. */
    public function allLeads(string $status = ''): array
    {
        $sql = "SELECT * FROM leads";
        $params = [];

        if ($status !== '') {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

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
