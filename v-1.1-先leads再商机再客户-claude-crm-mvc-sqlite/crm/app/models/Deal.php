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

    /** Orders belonging to this deal. */
    public function orders(int $dealId): array
    {
        return $this->db()->query(
            "SELECT o.*, c.name AS customer_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.deal_id = :id
             ORDER BY o.created_at DESC"
        )->bind(':id', $dealId)->resultSet();
    }
}
