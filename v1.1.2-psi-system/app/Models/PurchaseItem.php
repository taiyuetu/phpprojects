<?php
namespace App\Models;

use App\Core\Model;

class PurchaseItem extends Model
{
    protected static string $table = 'purchase_items';
    protected static array $fillable = ['purchase_id', 'product_id', 'qty', 'unit_cost', 'subtotal'];

    /** Get all items for a purchase */
    public static function byPurchase(int $purchaseId): array
    {
        return self::raw(
            'SELECT pi.*, p.name AS product_name, p.sku
             FROM purchase_items pi
             JOIN products p ON p.id = pi.product_id
             WHERE pi.purchase_id = ?',
            [$purchaseId]
        );
    }
}
