<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class OrderItem extends Model
{
    protected string $table = 'order_items';

    /** Get all items for an order. */
    public function byOrder(int $orderId): array
    {
        return $this->db()->query(
            "SELECT * FROM order_items WHERE order_id = :id ORDER BY sort_order ASC, id ASC"
        )->bind(':id', $orderId)->resultSet();
    }

    /** Delete all items for an order. */
    public function deleteByOrder(int $orderId): bool
    {
        return $this->db()->query("DELETE FROM order_items WHERE order_id = :id")
            ->bind(':id', $orderId)->execute();
    }

    /** Calculate total for an order's items. */
    public function totalByOrder(int $orderId): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(subtotal),0) AS total FROM order_items WHERE order_id = :id"
        )->bind(':id', $orderId)->single();
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Sync items for an order: delete existing, insert new ones, update order amount.
     * $items = [ ['product_id'=>1, 'product_name'=>'...', 'sku'=>'...', 'quantity'=>1, 'unit_price'=>100, 'unit'=>'件', 'notes'=>'...'], ... ]
     *
     * product_id 是“来源”，名称/价格仍是快照：商品今天改价，不能把已经签出去的订单跟着改。
     */
    public function syncItems(int $orderId, array $items): void
    {
        $this->deleteByOrder($orderId);

        $orderTotal = 0;
        foreach ($items as $i => $item) {
            $qty = max(0, (float) ($item['quantity'] ?? 0));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            $subtotal = round($qty * $price, 2);
            $orderTotal += $subtotal;

            $productId = isset($item['product_id']) && (int) $item['product_id'] > 0 ? (int) $item['product_id'] : null;
            $this->create([
                'order_id'     => $orderId,
                'product_id'   => $productId,
                'product_name' => trim($item['product_name'] ?? ''),
                'sku'          => trim($item['sku'] ?? '') ?: null,
                'quantity'     => $qty,
                'unit_price'   => $price,
                'subtotal'     => $subtotal,
                'unit'         => trim($item['unit'] ?? '件') ?: '件',
                'notes'        => trim($item['notes'] ?? '') ?: null,
                'sort_order'   => $i + 1,
            ]);
        }

        // Update order total amount
        $this->db()->query("UPDATE orders SET amount = :amount WHERE id = :id")
            ->bind(':amount', $orderTotal)
            ->bind(':id', $orderId)
            ->execute();
    }

    /** Get unit options. */
    public static function unitOptions(): array
    {
        return ['件', '套', '台', '个', '箱', '吨', '千克', '米', '平米', '次', '天', '月', '年', '批', '组'];
    }

    /**
     * 把表单（或 AI）给的明细行洗成可直接落库的行，并把商品锁到商品库上。
     *
     * 规则：
     *   1. 空白行（没名称也没商品）直接丢弃，页面默认留着一行空行用于新增；
     *   2. 默认**必须从商品库选**：因为手挨名字就是这张表存在的理由；
     *   3. 升级前就存在的行（product_id 为 NULL）允许不改：表单会带一个 data-legacy
     *      的回传项，只有“原名称与库里完全一致且没改价”时才放行，想改就得选商品；
     *   4. 选中商品后，名称/SKU/单位/单价以商品库为底，用户在行内手改的以手改为准
     *      （实际业务里“同一个商品今天给个折扣价”很常见）。
     *
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,string>}
     */
    public static function normalizeRows(array $rows, bool $requireProduct = true): array
    {
        $items = [];
        $errors = [];
        $products = new Product();

        foreach (array_values($rows) as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $label = '第 ' . ($i + 1) . ' 行';
            // 引用商品有四种写法（页面给 product_id，AI 可能给编号 / SKU / 精确名称），
            // 这里必须与 Ai::checkItems() 完全同源，否则会出现“校验放过、落库悄悄丢行”
            $name = trim((string) ($raw['product_name'] ?? ''));
            $legacy = trim((string) ($raw['legacy_name'] ?? ''));
            // 只有“没动过的历史行”可以继续不关联商品（名称与价格都和库里一样）。
            // 这个判定必须走在引用解析之前：历史行没有 product_id，它的名称会被当成引用去查商品库，
            // 一查不中就变成“非法引用”，结果升级前的老单子反而保存不了。
            $untouchedLegacy = $legacy !== '' && $name === $legacy
                && (!isset($raw['unit_price'])
                    || (float) $raw['unit_price'] === (float) ($raw['legacy_price'] ?? -1));
            $ref = '';
            $refKeys = ['product_id', 'product_code', 'sku'];
            if ($requireProduct && !$untouchedLegacy) {
                // 只有强制模式下名称才算一种引用写法；开关关掉时它只是快照字段，不是查找键
                $refKeys[] = 'product_name';
            }
            foreach ($refKeys as $k) {
                $cand = trim((string) ($raw[$k] ?? ''));
                if ($cand !== '') {
                    $ref = $cand;
                    break;
                }
            }

            if ($ref === '' && $legacy === '') {
                continue;                                  // 空行
            }
            if ($untouchedLegacy && $ref === '') {
                $items[] = [
                    'product_id'   => null,
                    'product_name' => $name,
                    'sku'          => trim((string) ($raw['sku'] ?? '')),
                    'quantity'     => max(0, (float) ($raw['quantity'] ?? 1)),
                    'unit_price'   => max(0, (float) ($raw['unit_price'] ?? 0)),
                    'unit'         => trim((string) ($raw['unit'] ?? '件')) ?: '件',
                    'notes'        => trim((string) ($raw['notes'] ?? '')),
                ];
                continue;
            }

            if ($ref !== '') {
                $product = $products->resolve($ref);
                if (!$product) {
                    // 填了却对不上必须报错，不能静默丢行：少一行等于少一笔钱
                    $cands = array_map(static fn($c) => (string) ($c['public_code'] ?? '') . '（'
                        . (string) $c['name'] . '）', $products->candidatesByName($ref));
                    $errors[] = $label . '：商品「' . textClip($ref, 30) . '」不在商品库里'
                        . ($cands ? '，同名候选：' . implode('、', $cands) : '')
                        . '。请从商品库里选一个（可搜名称或 SKU），或先到 商品 新建它。';
                    continue;
                }
                $items[] = [
                    'product_id'   => (int) $product['id'],
                    'product_name' => $name !== '' ? $name : (string) $product['name'],
                    'sku'          => trim((string) ($raw['sku'] ?? '')) !== '' ? trim((string) $raw['sku']) : (string) ($product['sku'] ?? ''),
                    'quantity'     => max(0, (float) ($raw['quantity'] ?? 1)),
                    'unit_price'   => isset($raw['unit_price']) && is_numeric($raw['unit_price'])
                                        && trim((string) $raw['unit_price']) !== ''
                                        ? max(0, (float) $raw['unit_price']) : (float) $product['price'],
                    'unit'         => trim((string) ($raw['unit'] ?? '')) !== '' ? trim((string) $raw['unit']) : (string) $product['unit'],
                    'notes'        => trim((string) ($raw['notes'] ?? '')),
                ];
                continue;
            }

            // 走到这里：既不是可原样保留的历史行，也没给出能对上商品库的引用
            $errors[] = $label . '：请从商品库中选择商品（可用上方搜索框输入名称或 SKU，也可直接下拉选）。';
        }

        return ['items' => $items, 'errors' => $errors];
    }
}
