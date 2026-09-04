<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class FollowUp extends Model
{
    protected string $table = 'follow_ups';

    /** Get all follow-ups for a customer */
    public function byCustomer(int $customerId): array
    {
        return $this->db()->query(
            "SELECT f.*, u.name AS user_name
             FROM follow_ups f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.customer_id = :id
             ORDER BY f.created_at DESC"
        )->bind(':id', $customerId)->resultSet();
    }

    /** Add a new follow-up record */
    public function addFollowUp(int $customerId, ?int $userId, array $data): int
    {
        return $this->db()->query(
            "INSERT INTO follow_ups (customer_id, user_id, type, title, description, next_action, next_date)
             VALUES (:cid, :uid, :type, :title, :desc, :next_action, :next_date)"
        )->bind(':cid', $customerId)
         ->bind(':uid', $userId)
         ->bind(':type', $data['type'] ?? 'price_comparison')
         ->bind(':title', $data['title'])
         ->bind(':desc', $data['description'] ?? null)
         ->bind(':next_action', $data['next_action'] ?? null)
         ->bind(':next_date', $data['next_date'] ?: null)
         ->execute() ? (int) $this->db()->lastInsertId() : 0;
    }
}
