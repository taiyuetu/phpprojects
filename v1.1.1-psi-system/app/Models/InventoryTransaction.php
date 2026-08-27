<?php
namespace App\Models;

use App\Core\Model;

class InventoryTransaction extends Model
{
    protected static string $table = 'inventory_transactions';

    public static function forProduct(int $productId): array
    {
        return self::raw(
            'SELECT * FROM inventory_transactions WHERE product_id = ? ORDER BY created_at DESC, id DESC',
            [$productId]
        );
    }

    public static function recent(int $limit = 20): array
    {
        return self::raw(
            'SELECT it.*, p.name AS product_name, p.sku
             FROM inventory_transactions it
             JOIN products p ON p.id = it.product_id
             ORDER BY it.created_at DESC, it.id DESC
             LIMIT ' . (int)$limit
        );
    }
}
