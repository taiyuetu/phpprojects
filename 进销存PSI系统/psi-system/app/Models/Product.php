<?php
namespace App\Models;

use App\Core\Model;

class Product extends Model
{
    protected static string $table = 'products';

    /** Products joined with category name, for listing screens. */
    public static function allWithCategory(): array
    {
        return self::raw(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.name'
        );
    }

    public static function lowStock(): array
    {
        return self::raw(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.quantity <= p.reorder_level
             ORDER BY p.quantity ASC'
        );
    }

    /** Increase stock and write an audit-trail row. Used by Purchases. */
    public static function increaseStock(int $productId, int $qty, string $type, string $reference, ?string $notes = null): void
    {
        $product = self::find($productId);
        $newQty = $product['quantity'] + $qty;
        self::update($productId, ['quantity' => $newQty]);

        InventoryTransaction::create([
            'product_id'    => $productId,
            'type'          => $type,
            'qty_change'    => $qty,
            'balance_after' => $newQty,
            'reference'     => $reference,
            'notes'         => $notes,
        ]);
    }

    /** Decrease stock and write an audit-trail row. Used by Sales. */
    public static function decreaseStock(int $productId, int $qty, string $type, string $reference, ?string $notes = null): void
    {
        $product = self::find($productId);
        $newQty = $product['quantity'] - $qty;
        self::update($productId, ['quantity' => $newQty]);

        InventoryTransaction::create([
            'product_id'    => $productId,
            'type'          => $type,
            'qty_change'    => -$qty,
            'balance_after' => $newQty,
            'reference'     => $reference,
            'notes'         => $notes,
        ]);
    }
}
