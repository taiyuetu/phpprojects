<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class Lead extends Model
{
    /** 线索编号：LEAD-000007（自动生成，供 AI 与人工稳定引用） */
    protected ?string $publicCodePrefix = 'LEAD';

    protected string $table = 'leads';

    /**
     * 字段语义注册表（稀疏）：结构看 schema.sql，这里只补语义。
     * searchable 列与改动前 $searchable 的清单一致 → 搜索行为不变。
     */
    protected static array $fields = [
        'title'       => ['label' => '线索标题', 'type' => 'string', 'searchable' => true,
                          'required' => true, 'requiredMsg' => '线索标题不能为空。'],
        'company'     => ['label' => '公司', 'searchable' => true],
        'contact_name'    => ['label' => '联系人', 'searchable' => true],
        'contact_email'   => ['label' => '联系邮箱', 'type' => 'email', 'searchable' => true],
        'lead_time'   => ['label' => '线索时间', 'type' => 'datetime'],
        'whatsapp'    => ['label' => 'WhatsApp', 'searchable' => true],
        'phone'       => ['label' => '电话', 'searchable' => true],
        'facebook'    => ['label' => 'Facebook 主页'],
        'tiktok'      => ['label' => 'TikTok 频道'],
        'website'     => ['label' => '官方网站'],
        'source'      => ['label' => '来源', 'searchable' => true],
        'source_country' => ['label' => '来源国家', 'searchable' => true],
        'source_city'    => ['label' => '来源城市', 'searchable' => true],
        'address'     => ['label' => '地址'],
        'status'      => ['label' => '状态', 'type' => 'enum', 'default' => 'new'],
        'lost_reason' => ['label' => '流失原因', 'type' => 'enum', 'writable' => false],
        'value'       => ['label' => '预估金额', 'type' => 'number', 'default' => '0'],
        'first_purchase_from_china' => ['label' => '是否首次从中国采购', 'type' => 'bool'],
        'has_import_capability'     => ['label' => '是否有进口能力', 'type' => 'bool'],
        'notes'       => ['label' => '备注', 'type' => 'text', 'searchable' => true],
    ];

    /**
     * All leads, newest first. $status = 状态精确筛选，$search = 跨列关键词。
     * （$search 放在参数末位：老调用 allLeads($status,$page,$perPage) 不受影响）
     */
    public function allLeads(string $status = '', int $page = 1, int $perPage = 15, string $search = ''): array
    {
        $sql = "SELECT l.*, u.name AS owner_name FROM leads l LEFT JOIN users u ON u.id = l.owner_id";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'l');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        $stmt->bind(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bind(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        return $stmt->resultSet();
    }

    /** Count leads matching optional status filter and/or keyword. */
    public function countLeads(string $status = '', string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS total FROM leads l";
        $params = [];

        $where = [];
        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            [$bits, $sparams] = $this->searchWhere($search, 'l');
            $where[] = $bits;
            $params = array_merge($params, $sparams);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db()->query($sql);
        foreach ($params as $key => $value) {
            $stmt->bind($key, $value);
        }
        return (int) ($stmt->single()['total'] ?? 0);
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
