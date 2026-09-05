<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Deal extends Model
{
    /** 商机编号：DEAL-000007（自动生成，供 AI 与人工稳定引用） */
    protected ?string $publicCodePrefix = 'DEAL';

    protected string $table = 'deals';

    /** 未归档的商机（看板/列表用） */
    public function allWithCustomer(): array
    {
        return $this->db()->query(
            "SELECT d.*, c.name AS customer_name
             FROM deals d
             LEFT JOIN customers c ON c.id = d.customer_id
             WHERE d.archived = 0
             ORDER BY d.created_at DESC"
        )->resultSet();
    }

    /** 已归档的商机 */
    public function allArchived(): array
    {
        return $this->db()->query(
            "SELECT d.*, c.name AS customer_name
             FROM deals d
             LEFT JOIN customers c ON c.id = d.customer_id
             WHERE d.archived = 1
             ORDER BY d.archived_at DESC"
        )->resultSet();
    }

    public function sumValueByStage(string $stage): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(value),0) AS total FROM deals WHERE stage = :stage AND archived = 0"
        )->bind(':stage', $stage)->single();
        return (float) ($row['total'] ?? 0);
    }

    public function openPipelineValue(): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(value),0) AS total FROM deals WHERE stage NOT IN ('closed_won','closed_lost') AND archived = 0"
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

    /** 归档商机 */
    public function archive(int $id): bool
    {
        return $this->update($id, [
            'archived'    => 1,
            'archived_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 取消归档：恢复为“进行中”(open)，清除丢单标记与归档信息 */
    public function unarchive(int $id): bool
    {
        return $this->update($id, [
            'archived'             => 0,
            'archived_at'          => null,
            'stage'                => 'open',
            'stage_open_at'        => date('Y-m-d H:i:s'),
            'stage_closed_lost_at' => null,
        ]);
    }
}
