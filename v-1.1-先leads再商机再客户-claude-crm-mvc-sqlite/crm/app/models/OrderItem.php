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
     * $items = [ ['product_name'=>'...', 'sku'=>'...', 'quantity'=>1, 'unit_price'=>100, 'unit'=>'件', 'notes'=>'...'], ... ]
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

            $this->create([
                'order_id'     => $orderId,
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
}
