<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 商品主数据（catalog）。
 *
 * 为什么要有这张表而不是让每笔明细手挨商品名：同一个商品被三个人写成
 * “6206 轴承 / 深沟球轴承6206 / bearing 6206” 时，销量统计、报价、对账全部失真。
 *
 * order_items 里仍然存一份 name/sku/unit/price 的**快照**：商品今天改价，
 * 不能把昨天已经签出去的订单跟着改。所以 product_id 是“来源”，快照是“事实”。
 */
class Product extends Model
{
    /** 商品编号：PROD-000007（自动生成，人工与 AI 引用同一条记录用） */
    protected ?string $publicCodePrefix = 'PROD';

    protected string $table = 'products';

    public static function statusOptions(): array
    {
        return ['active' => '在售', 'inactive' => '停用'];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
    }

    /** 与订单明细共用同一套单位，免得两边选项对不上 */
    public static function unitOptions(): array
    {
        return OrderItem::unitOptions();
    }

    // ------------------------------------------------------------------ 列表

    /** 分页列表：支持关键词（编号/名称/SKU/品牌/规格/分类/备注）与状态、分类筛选 */
    public function allPaged(string $search = '', string $status = '', string $category = '',
                             int $page = 1, int $perPage = 20): array
    {
        [$sql, $params] = $this->buildWhere("SELECT p.*, u.name AS owner_name, "
            . "(SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) AS used_count, "
            . "(SELECT SUM(oi.subtotal) FROM order_items oi WHERE oi.product_id = p.id) AS sold_amount "
            . 'FROM products p LEFT JOIN users u ON u.id = p.owner_id', 'p', $search, $status, $category);
        $sql .= ' ORDER BY p.status ASC, p.name ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->db()->query($sql);
        foreach ($params as $k => $v) {
            $stmt->bind($k, $v);
        }
        $stmt->bind(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bind(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        return $stmt->resultSet();
    }

    public function countAll(string $search = '', string $status = '', string $category = ''): int
    {
        [$sql, $params] = $this->buildWhere('SELECT COUNT(*) AS c FROM products p', 'p', $search, $status, $category);
        $stmt = $this->db()->query($sql);
        foreach ($params as $k => $v) {
            $stmt->bind($k, $v);
        }
        $row = $stmt->single();
        return (int) ($row['c'] ?? 0);
    }

    /** 拼 WHERE：搜索与筛选全部参数绑定，绝不把用户输入拼进 SQL */
    public function buildWhere(string $select, string $alias, string $search, string $status, string $category): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $cols = ['name', 'sku', 'brand', 'spec', 'category', 'notes', 'public_code'];
            $ors = [];
            foreach ($cols as $col) {
                $ors[] = "{$alias}.{$col} LIKE :q_{$col}";
                $params[':q_' . $col] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = "{$alias}.status = :status";
            $params[':status'] = $status;
        }
        if ($category !== '') {
            $where[] = "{$alias}.category = :category";
            $params[':category'] = $category;
        }
        return [$select . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $params];
    }

    /** 分类候选（用于筛选下拉），按商品数倒序 */
    public function categories(): array
    {
        $rows = $this->db()->query(
            "SELECT category, COUNT(*) AS n FROM products
              WHERE category IS NOT NULL AND category <> '' GROUP BY category ORDER BY n DESC, category ASC"
        )->resultSet();
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['category']] = (int) $r['n'];
        }
        return $out;
    }

    // ------------------------------------------------------------ 选择框数据

    /**
     * 给“新增商品”选择框用的全量目录（在售优先，停用的排在后面并标注）。
     *
     * 上限是有意为之：这个数组会随表单渲染进 HTML，也进前端搜索框的候选列表。
     * 真到几千个商品时该换成服务端分页搜索，而不是让一个表单页扛下整张表。
     */
    public function pickList(int $limit = 800): array
    {
        $rows = $this->db()->query(
            "SELECT id, public_code, name, sku, category, brand, spec, unit, price, status
               FROM products
              ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, name ASC
              LIMIT " . max(10, min(2000, $limit))
        )->resultSet();
        $out = [];
        foreach ($rows as $r) {
            $code = (string) ($r['public_code'] ?? '');
            if ($code === '') {
                $code = $this->codeOf($r);
            }
            $out[] = [
                'id'    => (int) $r['id'],
                'code'  => $code,
                'name'  => (string) $r['name'],
                'sku'   => (string) ($r['sku'] ?? ''),
                'unit'  => (string) ($r['unit'] ?? '件'),
                'price' => (float) ($r['price'] ?? 0),
                'spec'  => (string) ($r['spec'] ?? ''),
                'brand' => (string) ($r['brand'] ?? ''),
                'category' => (string) ($r['category'] ?? ''),
                'status'   => (string) ($r['status'] ?? 'active'),
            ];
        }
        return $out;
    }

    /** 搜索框用的“可搜索文本”：编号 名称 SKU 品牌 规格 分类（一次拼好，前端不再拼） */
    public static function haystack(array $p): string
    {
        return textLower(implode(' ', array_filter([
            $p['code'] ?? '', $p['name'] ?? '', $p['sku'] ?? '', $p['brand'] ?? '',
            $p['spec'] ?? '', $p['category'] ?? '',
        ])));
    }

    // -------------------------------------------------------------- 校验/引用

    /** 这条明细引用的商品存在吗？返回行或 null（AI、订单、商机都走这里） */
    public function resolve($ref): ?array
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        $id = $this->idFromReference($ref);
        if ($id) {
            $row = $this->find($id);
            if ($row) {
                return $row;
            }
        }
        // SKU 允许直接写（业务上最常说的话是“给我来 SVC-IMP-001 × 2”），且不分大小写：
        // 抄货号的人不会去记原始大小写，等值匹配会莫名其妙查不到
        $bySku = $this->db()->query('SELECT * FROM products WHERE LOWER(sku) = LOWER(:s) ORDER BY id LIMIT 1')
            ->bind(':s', $ref)->single();
        if ($bySku) {
            return $bySku;
        }
        // 再试一次名称精确匹配：大小写不敏感，避免“crm企业版”查不到
        $rows = $this->db()->query('SELECT * FROM products WHERE LOWER(name) = LOWER(:n) ORDER BY id LIMIT 2')
            ->bind(':n', $ref)->resultSet();
        return count($rows) === 1 ? $rows[0] : null;
    }

    /** 名称有歧义（多条同名）时给出候选，供报错信息用 */
    public function candidatesByName(string $name): array
    {
        return $this->db()->query('SELECT id, public_code, name, sku FROM products
                WHERE LOWER(name) = LOWER(:n) ORDER BY id LIMIT 5')->bind(':n', $name)->resultSet();
    }

    /** 被多少条订单明细引用（删除前必须告诉人会影响什么） */
    public function usage(int $id): array
    {
        $row = $this->db()->query('SELECT COUNT(*) AS rows_count, COUNT(DISTINCT order_id) AS orders_count,
                                          COALESCE(SUM(subtotal), 0) AS amount
                                     FROM order_items WHERE product_id = :id')
            ->bind(':id', $id, PDO::PARAM_INT)->single();
        return [
            'items'  => (int) ($row['rows_count'] ?? 0),
            'orders' => (int) ($row['orders_count'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ];
    }

    /** 这个商品最近卖出去的明细（带单号与客户名），商品详情页用 */
    public function recentSales(int $productId, int $limit = 20): array
    {
        return $this->db()->query(
            'SELECT oi.*, o.order_number, o.order_date, o.status AS order_status, c.name AS customer_name
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id
               LEFT JOIN customers c ON c.id = o.customer_id
              WHERE oi.product_id = :id
              ORDER BY o.order_date DESC, oi.id DESC
              LIMIT ' . max(1, min(100, $limit))
        )->bind(':id', $productId, PDO::PARAM_INT)->resultSet();
    }

    /** SKU 是否已被别的商品占用（自己改自己不算冲突） */
    public function skuTaken(string $sku, int $exceptId = 0): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }
        $row = $this->db()->query('SELECT id FROM products WHERE sku = :s AND id <> :id LIMIT 1')
            ->bind(':s', $sku)->bind(':id', $exceptId, PDO::PARAM_INT)->single();
        return (bool) $row;
    }

    /**
     * 把历史上自由填写、又没关联到商品的明细“收编”成商品。
     *
     * 迁移 010 只在升级时跑一次；之后用户在页面上继续手打新品名的历史脏数据
     * 还会累积，所以这个入口长期有效（幂等：已存在的商品不重复建）。
     *
     * @return array{created:int,linked:int}
     */
    public function importUnlinkedItems(): array
    {
        $before = (int) ($this->db()->query('SELECT COUNT(*) AS c FROM products')->single()['c'] ?? 0);
        $unlinkedBefore = (int) ($this->db()->query('SELECT COUNT(*) AS c FROM order_items WHERE product_id IS NULL')->single()['c'] ?? 0);
        $this->db()->query(
            "INSERT INTO products (name, sku, unit, price, status, notes, owner_id)
             SELECT src.name, src.sku, src.unit, src.price, 'active',
                    '由订单明细收编（' || datetime('now') || '），建议补上规格/分类', src.owner_id
               FROM (
                SELECT oi.product_name AS name,
                       MAX(oi.sku)    AS sku,
                       MAX(oi.unit)   AS unit,
                       (SELECT o2.unit_price FROM order_items o2
                         WHERE o2.product_name = oi.product_name AND IFNULL(o2.sku,'') = IFNULL(oi.sku,'')
                         ORDER BY o2.id DESC LIMIT 1) AS price,
                       (SELECT o3.owner_id FROM order_items o4 JOIN orders o3 ON o3.id = o4.order_id
                         WHERE o4.product_name = oi.product_name AND IFNULL(o4.sku,'') = IFNULL(oi.sku,'')
                         ORDER BY o4.id DESC LIMIT 1) AS owner_id,
                       IFNULL(oi.sku,'') AS sku_key
                  FROM order_items oi
                 WHERE oi.product_id IS NULL
                 GROUP BY oi.product_name, IFNULL(oi.sku,'')
               ) AS src
              WHERE NOT EXISTS (SELECT 1 FROM products p
                                 WHERE p.name = src.name AND IFNULL(p.sku,'') = src.sku_key)"
        )->execute();
        $linked = $this->db()->query(
            'UPDATE order_items
                SET product_id = (SELECT MIN(p.id) FROM products p
                                   WHERE p.name = order_items.product_name
                                     AND IFNULL(p.sku,\'\') = IFNULL(order_items.sku,\'\'))
              WHERE product_id IS NULL
                AND EXISTS (SELECT 1 FROM products p2
                             WHERE p2.name = order_items.product_name
                               AND IFNULL(p2.sku,\'\') = IFNULL(order_items.sku,\'\'))'
        )->execute();
        $after = (int) ($this->db()->query('SELECT COUNT(*) AS c FROM products')->single()['c'] ?? 0);
        $left = (int) ($this->db()->query('SELECT COUNT(*) AS c FROM order_items WHERE product_id IS NULL')->single()['c'] ?? 0);
        return ['created' => max(0, $after - $before), 'linked' => max(0, $unlinkedBefore - $left), 'left' => $left];
    }

    /** 全库还有多少条明细没关联商品（列表页的提示条用） */
    public function unlinkedItemCount(): int
    {
        return (int) ($this->db()->query('SELECT COUNT(*) AS c FROM order_items WHERE product_id IS NULL')->single()['c'] ?? 0);
    }

    /** 校验新增/编辑表单，返回 [data, errors] */
    public static function validateInput(array $input, int $selfId = 0): array
    {
        $errors = [];
        $data = [
            'name'     => trim((string) ($input['name'] ?? '')),
            'sku'      => trim((string) ($input['sku'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'brand'    => trim((string) ($input['brand'] ?? '')),
            'spec'     => trim((string) ($input['spec'] ?? '')),
            'unit'     => trim((string) ($input['unit'] ?? '件')) ?: '件',
            'price'    => is_numeric($input['price'] ?? null) ? (float) $input['price'] : null,
            'cost'     => isset($input['cost']) && $input['cost'] !== '' && is_numeric($input['cost']) ? (float) $input['cost'] : null,
            'status'   => in_array((string) ($input['status'] ?? ''), ['active', 'inactive'], true)
                            ? (string) $input['status'] : 'active',
            'notes'    => trim((string) ($input['notes'] ?? '')),
        ];
        if ($data['name'] === '') {
            $errors[] = '商品名称必填。';
        }
        if (textLength($data['name']) > 150) {
            $errors[] = '商品名称最长 150 字。';
        }
        if (textLength($data['sku']) > 60) {
            $errors[] = 'SKU 最长 60 字。';
        }
        if ($data['price'] === null) {
            $errors[] = '单价必须填数字（没有价格就填 0，别留空）。';
        } elseif ($data['price'] < 0 || $data['price'] > Ai::MAX_AMOUNT) {
            $errors[] = '单价超出合理范围。';
        }
        if ($data['cost'] !== null && ($data['cost'] < 0 || $data['cost'] > Ai::MAX_AMOUNT)) {
            $errors[] = '参考价超出合理范围。';
        }
        if (!in_array($data['unit'], self::unitOptions(), true)) {
            $errors[] = '单位不在可选值里。';
        }
        if ($data['sku'] !== '' && (new self())->skuTaken($data['sku'], $selfId)) {
            $errors[] = 'SKU「' . $data['sku'] . '」已被其它商品占用，换个编号或直接留空。';
        }
        // 空串写 NULL：唯一索引不约束 NULL，留空才能重复
        $data['sku'] = $data['sku'] === '' ? null : $data['sku'];
        foreach (['category', 'brand', 'spec', 'notes'] as $k) {
            $data[$k] = $data[$k] === '' ? null : $data[$k];
        }
        return [$data, $errors];
    }
}
