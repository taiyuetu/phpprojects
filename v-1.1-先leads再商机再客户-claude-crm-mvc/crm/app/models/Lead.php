<?php

class Lead extends Model
{
    protected string $table = 'leads';

    /** All leads, newest first, optional status filter. */
    public function allLeads(string $status = ''): array
    {
        $sql = "SELECT l.*, u.name AS owner_name FROM leads l LEFT JOIN users u ON u.id = l.owner_id";
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

    /** Mark lead as lost with reason */
    public function markAsLost(int $id, string $reason): bool
    {
        return $this->update($id, [
            'status' => 'lost',
            'lost_reason' => $reason,
            'lost_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Reactivate a lost lead back to contacted status */
    public function reactivate(int $id): bool
    {
        return $this->update($id, [
            'status' => 'contacted',
            'lost_reason' => null,
            'lost_at' => null,
        ]);
    }

    /** Get lost reason options */
    public static function lostReasonOptions(): array
    {
        return [
            'no_need' => '暂无需求',
            'competitor' => '已选竞品',
            'budget' => '预算不足',
            'no_match' => '需求不匹配',
            'no_response' => '长期无响应',
            'project_cancel' => '项目取消',
            'contact_lost' => '联系不上',
            'other' => '其他原因',
        ];
    }

    /** Get lost reason label */
    public static function lostReasonLabel(string $reason): string
    {
        $options = self::lostReasonOptions();
        return $options[$reason] ?? '未知原因';
    }
}
