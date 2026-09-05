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

    /**
     * 字段语义注册表（稀疏）。结构看 schema.sql；这里补 label/type/required/范围/唯一。
     * max 对文本列是字符上限、对数值列是大小上限；数值上限 100000000 = Ai::MAX_AMOUNT。
     */
    protected static array $fields = [
        'name'        => ['label' => '商品名称', 'type' => 'string', 'searchable' => true,
                          'required' => true, 'requiredMsg' => '商品名称必填。', 'max' => 150,
                          'csv' => ['label' => '名称', 'aliases' => ['商品名称', '商品', '产品名称', '品名', 'name']]],
        'public_code' => ['label' => '编号', 'writable' => false, 'csv' => true],
        'sku'         => ['label' => 'SKU', 'type' => 'string', 'searchable' => true,
                          'unique' => true, 'max' => 60, 'csv' => true],
        'category'    => ['label' => '分类', 'searchable' => true, 'csv' => true],
        'brand'       => ['label' => '品牌', 'searchable' => true, 'csv' => true],
        'spec'        => ['label' => '规格', 'searchable' => true, 'csv' => true],
        'unit'        => ['label' => '单位', 'type' => 'enum', 'default' => '件', 'strict' => true, 'csv' => true],
        'price'       => ['label' => '单价', 'type' => 'number', 'required' => true,
                          'requiredMsg' => '单价必须填数字（没有价格就填 0，别留空）。',
                          'min' => 0, 'max' => 100000000, 'csv' => true],
        'cost'        => ['label' => '参考价', 'type' => 'number', 'min' => 0, 'max' => 100000000, 'csv' => true],
        'status'      => ['label' => '状态', 'type' => 'enum', 'default' => 'active', 'csv' => true],
        'notes'       => ['label' => '备注', 'type' => 'text', 'searchable' => true, 'csv' => true],
    ];

    /** 单位可选值不在数据库里（订单明细共用同一套，见 OrderItem::unitOptions） */
    public function fieldEnumOptions(string $field): ?array
    {
        return $field === 'unit' ? OrderItem::unitOptions() : null;
    }

    /** SKU 唯一性的查库实现（selfId 用于“改自己时不算冲突”） */
    protected function fieldUniqueTaken(string $field, string $value, int $selfId): bool
    {
        return $field === 'sku' && $this->skuTaken($value, $selfId);
    }

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

    // ------------------------------------------------------------ CSV 导入/导出

    /** 单次导入的行数上限：防手滑把几十万行的库存表一次性灌进来 */
    public const MAX_IMPORT_ROWS = 2000;

    /**
     * CSV 列定义：字段 => 中文表头（顺序即导出列序）。
     * 从 static::$fields 里带 csv 语义的列派生 —— 加导出列只改注册表一处。
     */
    public static function csvColumns(): array
    {
        $out = [];
        foreach (static::$fields as $name => $meta) {
            if (empty($meta['csv'])) {
                continue;
            }
            $label = is_array($meta['csv']) ? ($meta['csv']['label'] ?? $meta['label'] ?? $name)
                                           : ($meta['label'] ?? $name);
            $out[$name] = (string) $label;
        }
        return $out;
    }

    /** CSV 里的数字：整数不带小数点，小数最多两位，不补多余零 */
    public static function csvNumber(float $n): string
    {
        if (abs($n - round($n)) < 1e-9) {
            return (string) (int) round($n);
        }
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /**
     * 导出（按当前列表筛选条件，与列表同序）。行值是给人看的可读文本：
     * 编号已回填、价格去尾零、状态给中文，Excel 打开即可读，也能再导回去。
     *
     * @return array<int,array<string,string>> 行，键与 csvColumns() 一一对应
     */
    public function exportRows(string $search = '', string $status = '', string $category = ''): array
    {
        [$sql, $params] = $this->buildWhere('SELECT * FROM products p', 'p', $search, $status, $category);
        $sql .= ' ORDER BY p.status ASC, p.name ASC';
        $stmt = $this->db()->query($sql);
        foreach ($params as $k => $v) {
            $stmt->bind($k, $v);
        }

        $rows = [];
        foreach ($stmt->resultSet() as $p) {
            $row = [];
            foreach (self::csvColumns() as $field => $label) {
                $row[$field] = self::csvCell($p, $field);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** 导出行单元格：数值列去尾零、状态给中文、编号回填，其余原样文本 */
    private function csvCell(array $p, string $field): string
    {
        $type = (string) ((static::$fields[$field]['type'] ?? '') ?: '');
        $meta = static::$fields[$field] ?? [];
        if ($field === 'public_code') {
            return $this->codeOf($p);
        }
        if ($type === 'number' || $type === 'money') {
            $v = $p[$field] ?? null;
            return $v === null ? '' : self::csvNumber((float) $v);
        }
        if ($field === 'status') {
            return self::statusLabel((string) ($p['status'] ?? 'active'));
        }
        if ($field === 'unit') {
            return (string) ($p['unit'] ?? '件');
        }
        if (isset($meta['csv']) && is_array($meta['csv']) && ($meta['csv']['format'] ?? null) === null) {
            // 预留：今后 csv.format 自定义
        }
        $v = $p[$field] ?? '';
        return $v === null ? '' : (string) $v;
    }

    /** 字节串 → UTF-8；无法识别返回 null。先剥 BOM，再验 UTF-8，最后尝试 GBK 系（Excel 中文另存常用） */
    public static function decodeToUtf8(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }
        if (preg_match('//u', $bytes)) {
            return $bytes;                                   // 已是合法 UTF-8
        }
        if (function_exists('iconv')) {
            $out = @iconv('GB18030', 'UTF-8//IGNORE', $bytes);
            if ($out !== false && preg_match('//u', $out)) {
                return $out;
            }
        }
        return null;
    }

    /**
     * 从 CSV 文件导入/更新商品（幂等：同一份文件导两遍不会多出商品）。
     *
     * 身份规则：行带非空 SKU 且库里已有同 SKU → 更新那条；否则名称在库里恰好
     * 唯一命中 → 更新；两者都没有 → 新建。名称撞多条时跳过并提示用 SKU 区分。
     *
     * 更新只写 CSV 里真实存在的列，缺失列保留库中原值（归属人、编号永远不动），
     * 所以“只导名称/SKU 两列”也能安全地修一批商品名。不合规的行整行跳过并记原因。
     *
     * @return array{created:int, updated:int, skipped:int, errors:array<int,string>}
     */
    public function importCsvFile(string $path, int $ownerId): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException('无法读取上传的 CSV 文件。');
        }
        $utf8 = self::decodeToUtf8((string) file_get_contents($path));
        if ($utf8 === null || $utf8 === '') {
            throw new RuntimeException('CSV 文件是空的，或编码无法识别（不是 UTF-8 也不是 GBK）。请另存为 UTF-8 后再试。');
        }
        $utf8 = str_replace(["\r\n", "\r"], "\n", $utf8);   // 规整换行：好数行号、fgetcsv 好解析

        // 表头行的字段分隔符最常见，按它猜整份文件的分隔符（Excel 区域设置可能导出 ; ）
        $nl = strpos($utf8, "\n");
        $firstLine = $nl === false ? $utf8 : substr($utf8, 0, $nl);
        $counts = [
            ';' => substr_count($firstLine, ';'),
            ',' => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
        ];
        $delim = (int) max($counts) === 0 ? ',' : (string) array_search(max($counts), $counts, true);

        $h = fopen('php://temp', 'r+');
        if ($h === false) {
            throw new RuntimeException('无法处理上传内容。');
        }
        fwrite($h, $utf8);
        rewind($h);

        $header = fgetcsv($h, 0, $delim);
        if (!is_array($header)) {
            fclose($h);
            throw new RuntimeException('表头行解析失败：第一行应是列名（如 名称,SKU,单价）。');
        }
        $map = self::mapCsvHeader(array_map(static fn($c) => (string) $c, $header));

        $stat = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $present = array_diff(array_keys($map), ['public_code']);   // 文件真实带了的列
        $line = 1;                                                   // 表头占第 1 行
        while (($cells = fgetcsv($h, 0, $delim)) !== false) {
            $line++;
            if ($line - 1 > self::MAX_IMPORT_ROWS) {
                fclose($h);
                throw new RuntimeException('一次最多导入 ' . self::MAX_IMPORT_ROWS . ' 行商品，请分批导入。');
            }

            $input = [];
            $any = false;
            $statusProblem = '';
            foreach ($map as $field => $idx) {
                $v = trim((string) ($cells[$idx] ?? ''));
                if ($field === 'status' && $v !== '') {
                    $ok = self::parseStatusValue($v);
                    if ($ok === null) {
                        $statusProblem = $v;
                        continue;
                    }
                    $v = $ok;
                }
                if ($v !== '') {
                    $any = true;
                }
                $input[$field] = $v;
            }
            if ($statusProblem !== '') {
                $stat['skipped']++;
                $stat['errors'][] = "第 {$line} 行：状态「{$statusProblem}」不认识，请用 在售/停用（或 active/inactive）。";
                continue;
            }
            if (!$any) {
                continue;                                        // 完全空行
            }
            unset($input['public_code']);                        // 编号只读，永不写

            $selfId = $this->findImportTarget((string) ($input['sku'] ?? ''), (string) ($input['name'] ?? ''));
            if ($selfId === -1) {
                $stat['skipped']++;
                $stat['errors'][] = '第 ' . $line . ' 行：名称「' . (string) ($input['name'] ?? '') . '」在库里对应多个商品，无法确定更新哪个，请补上 SKU。';
                continue;
            }

            if ($selfId > 0) {
                // 更新：缺失列用库中原值补齐再走同一套业务校验，落库只写文件里有的列
                $existing = (array) $this->find($selfId);
                $merged = $input;
                foreach (['name', 'sku', 'category', 'brand', 'spec', 'unit', 'price', 'cost', 'status', 'notes'] as $f) {
                    if (($merged[$f] ?? '') === '') {
                        $old = $existing[$f] ?? '';
                        $merged[$f] = $old === null ? '' : (string) $old;
                    }
                }
                [$data, $errors] = self::validateInput($merged, $selfId);
                if ($errors) {
                    $stat['skipped']++;
                    $stat['errors'][] = '第 ' . $line . ' 行：' . $errors[0];
                    continue;
                }
                $this->update($selfId, array_intersect_key($data, array_flip($present)));
                $stat['updated']++;
            } else {
                [$data, $errors] = self::validateInput($input, 0);
                if ($errors) {
                    $stat['skipped']++;
                    $stat['errors'][] = '第 ' . $line . ' 行：' . $errors[0];
                    continue;
                }
                $data['owner_id'] = $ownerId;
                $this->create($data);
                $stat['created']++;
            }
        }
        fclose($h);
        return $stat;
    }

    /** 行内 SKU/名称 → 库里已有商品 id；0 = 应新建，-1 = 名称歧义不能猜 */
    private function findImportTarget(string $sku, string $name): int
    {
        if ($sku !== '') {
            $row = $this->db()->query('SELECT id FROM products WHERE LOWER(sku) = LOWER(:s) ORDER BY id LIMIT 1')
                ->bind(':s', $sku)->single();
            if ($row) {
                return (int) $row['id'];
            }
        }
        if ($name === '') {
            return 0;
        }
        $rows = $this->db()->query('SELECT id FROM products WHERE LOWER(name) = LOWER(:n) ORDER BY id LIMIT 2')
            ->bind(':n', $name)->resultSet();
        return count($rows) === 1 ? (int) $rows[0]['id'] : (count($rows) > 1 ? -1 : 0);
    }

    /** 状态列的可读值 → 库值；不认识返回 null */
    private static function parseStatusValue(string $v): ?string
    {
        return match (strtolower($v)) {
            'active', '在售', '上架', '1' => 'active',
            'inactive', '停用', '下架', '0' => 'inactive',
            default => null,
        };
    }

    /**
     * 表头单元格 → 标准字段；找不到「名称」列直接抛错（没有名字的导入一定建不出合规商品）。
     * 可识别写法 = CSV 元数据（label/aliases）+ 英文字段名 + 历史兼容别名。
     */
    private static function mapCsvHeader(array $header): array
    {
        // 历史兼容别名：表头用英文/俗名也要认（csv 元数据之外补一层，不影响字段派生）
        $extraAliases = [
            'name'        => ['商品名称', '商品', '产品名称', '品名', 'name'],
            'public_code' => ['public_code', 'public code'],
            'sku'         => ['货号'],
            'category'    => ['category'],
            'brand'       => ['brand'],
            'spec'        => ['型号'],
            'unit'        => ['unit'],
            'price'       => ['售价', '价格', 'price'],
            'cost'        => ['成本'],
            'status'      => ['status'],
            'notes'       => ['说明'],
        ];
        $aliases = [];
        foreach (static::$fields as $name => $meta) {
            if (empty($meta['csv'])) {
                continue;
            }
            $csvMeta = is_array($meta['csv']) ? $meta['csv'] : [];
            $headerName = is_array($meta['csv'])
                ? ($csvMeta['label'] ?? $meta['label'] ?? $name)
                : ($meta['label'] ?? $name);
            $aliases[$name] = array_values(array_unique(array_merge(
                [$headerName, $name],
                (array) ($csvMeta['aliases'] ?? []),
                (array) ($extraAliases[$name] ?? [])
            )));
        }

        $map = [];
        foreach ($aliases as $field => $tokens) {
            foreach ($header as $i => $cell) {
                $c = strtolower(trim($cell));
                if ($c === '') {
                    continue;
                }
                foreach ($tokens as $token) {
                    if ($c === strtolower(trim($token))) {
                        $map[$field] = $i;
                        break 2;
                    }
                }
            }
        }
        if (!isset($map['name'])) {
            throw new RuntimeException('表头里没找到「名称」列。请保留表头行，列名可用中文（名称/商品名称）或 name。');
        }
        return $map;
    }

    /**
     * 校验新增/编辑表单，返回 [data, errors]（薄封装：规则见 static::$fields + hooks）
     */
    public static function validateInput(array $input, int $selfId = 0): array
    {
        return (new self())->sanitizeInput($input, ['selfId' => $selfId]);
    }
}
