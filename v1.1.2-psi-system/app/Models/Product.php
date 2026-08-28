<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;

class Product extends Model
{
    use HasCustomFields;

    protected static string $table = 'products';
    protected static array $fillable = [
        'sku', 'name', 'category_id', 'unit', 'cost_price',
        'sale_price', 'quantity', 'reorder_level', 'gallery', 'attributes',
    ];

    /**
     * Product custom fields, stored as JSON in the `attributes` column.
     * Add/edit an entry here to get it in the form, list, filter, and CSV.
     */
    protected static function customFieldDefinitions(): array
    {
        return [
            'brand'    => ['label' => 'Brand',    'type' => 'text',   'filterable' => true],
            'color'    => ['label' => 'Color',    'type' => 'select', 'filterable' => true, 'options' => ['black', 'red', 'blue', 'silver', 'green']],
            'material' => ['label' => 'Material', 'type' => 'text',   'filterable' => true],
        ];
    }

    /** Products joined with category name, for listing screens. */
    public static function allWithCategory(): array
    {
        return self::filter([]);
    }

    /** Search products by sku, name, or category name. */
    public static function searchWithCategory(string $query): array
    {
        return self::filter(['q' => $query]);
    }

    /**
     * Filter products by search text, category, and stock status.
     * Supported filters: q (text), category_id (int), status ('in'|'low').
     */
    public static function filter(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (p.sku LIKE :q OR p.name LIKE :q OR c.name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['status'] ?? '') === 'low') {
            $sql .= ' AND p.quantity <= p.reorder_level';
        } elseif (($filters['status'] ?? '') === 'in') {
            $sql .= ' AND p.quantity > p.reorder_level';
        }

        // Custom (JSON) fields — filter via json_extract on the attributes column
        $cf = self::customFieldFilterSql($filters, 'p');
        $sql .= $cf['sql'];
        $params = array_merge($params, $cf['params']);

        $sql .= ' ORDER BY p.name';

        return self::raw($sql, $params);
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
