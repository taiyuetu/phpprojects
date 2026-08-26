<?php

class Deal extends Model
{
    protected string $table = 'deals';

    public function allWithCustomer(): array
    {
        return $this->db()->query(
            "SELECT d.*, c.name AS customer_name
             FROM deals d
             LEFT JOIN customers c ON c.id = d.customer_id
             ORDER BY d.created_at DESC"
        )->resultSet();
    }

    public function sumValueByStage(string $stage): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(value),0) AS total FROM deals WHERE stage = :stage"
        )->bind(':stage', $stage)->single();
        return (float) ($row['total'] ?? 0);
    }

    public function openPipelineValue(): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(value),0) AS total FROM deals WHERE stage NOT IN ('closed_won','closed_lost')"
        )->single();
        return (float) ($row['total'] ?? 0);
    }
}
