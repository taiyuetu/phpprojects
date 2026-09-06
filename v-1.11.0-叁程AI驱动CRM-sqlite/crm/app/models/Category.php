<?php

/**
 * 商品分类模型
 *
 * 独立管理商品分类，支持排序和状态控制。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class Category extends Model
{
    protected string $table = 'categories';

    /**
     * 字段语义注册表
     */
    protected static array $fields = [
        'name'       => ['label' => '分类名称', 'type' => 'string', 'searchable' => true,
                         'required' => true, 'requiredMsg' => '分类名称不能为空。', 'max' => 60,
                         'unique' => true],
        'sort_order' => ['label' => '排序', 'type' => 'int', 'default' => 0,
                         'form' => ['hint' => '数字越小越靠前，默认为 0。']],
        'status'     => ['label' => '状态', 'type' => 'enum', 'default' => 'active'],
    ];

    /** 分类名称唯一性检查 */
    protected function fieldUniqueTaken(string $field, string $value, int $selfId): bool
    {
        if ($field !== 'name') {
            return false;
        }
        $row = $this->db()->query('SELECT id FROM categories WHERE name = :n AND id <> :id LIMIT 1')
            ->bind(':n', trim($value))
            ->bind(':id', $selfId, PDO::PARAM_INT)
            ->single();
        return (bool) $row;
    }

    /**
     * sort_order 是真正的整数列，0 是合法值（默认排序）。
     * 但通用清洗把 int 的 0 当成“不关联的外键”置成 NULL（为 deal_id 等设计），
     * 这里把空值/0 统一归零，避免撞上 NOT NULL 约束。
     */
    public function sanitizeInput(array $input, array $ctx = []): array
    {
        [$data, $errors] = parent::sanitizeInput($input, $ctx);
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }
        return [$data, $errors];
    }

    public static function statusOptions(): array
    {
        return ['active' => '启用', 'inactive' => '停用'];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
    }

    /**
     * 所有启用的分类（用于下拉选择），按排序字段、名称排序。
     * 静态方法里拿不到 Model 实例的 $db 包装，直接用 PDO。
     */
    public static function activeOptions(): array
    {
        static $cache = null;                 // 每请求只查一次（表单/筛选都会反复调用）
        if ($cache !== null) {
            return $cache;
        }
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT id, name FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
        );
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $cache = $out;
    }

    /**
     * 下拉候选（activeOptions 基础上，保证当前值也在选项里）。
     * 正在编辑的商品如果挂在已停用的分类下，下拉也要能看到、不能悄悄变成空选。
     */
    public static function optionsFor(?int $keepId, string $keepLabel = ''): array
    {
        $options = self::activeOptions();
        if ($keepId !== null && $keepId > 0 && !array_key_exists($keepId, $options)) {
            $options = [$keepId => ($keepLabel !== '' ? $keepLabel : '分类#' . $keepId) . '（已停用）'] + $options;
        }
        return $options;
    }

    /**
     * 所有分类（带商品数量），用于管理页面
     */
    public function allWithCount(): array
    {
        return $this->db()->query(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        )->resultSet();
    }

    /**
     * 某个分类下有多少商品
     */
    public function productCount(int $id): int
    {
        $row = $this->db()->query('SELECT COUNT(*) AS c FROM products WHERE category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->single();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * 删除分类时，将关联的商品的 category_id 设为 NULL
     */
    public function delete(int $id): bool
    {
        // 先解除关联
        $this->db()->query('UPDATE products SET category_id = NULL WHERE category_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)
            ->execute();

        // 再删除分类
        return parent::delete($id);
    }
}
