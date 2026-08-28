<?php
namespace App\Models;

use App\Core\Model;

class InventoryTransaction extends Model
{
    protected static string $table = 'inventory_transactions';
    protected static array $fillable = ['product_id', 'type', 'qty_change', 'balance_after', 'reference', 'notes'];

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

    /** Search transactions by product name, sku, or reference. */
    public static function searchRecent(string $query, int $limit = 200): array
    {
        return self::raw(
            'SELECT it.*, p.name AS product_name, p.sku
             FROM inventory_transactions it
             JOIN products p ON p.id = it.product_id
             WHERE p.name LIKE :q OR p.sku LIKE :q OR it.reference LIKE :q
             ORDER BY it.created_at DESC, it.id DESC
             LIMIT ' . (int)$limit,
            ['q' => '%' . $query . '%']
        );
    }
}
