<?php
namespace App\Models;

use App\Core\Model;
use RuntimeException;

class Sale extends Model
{
    protected static string $table = 'sales';
    protected static array $fillable = ['invoice_no', 'customer_id', 'sale_date', 'total', 'created_by'];

    public static function allWithCustomer(): array
    {
        return self::raw(
            'SELECT sa.*, c.name AS customer_name
             FROM sales sa
             LEFT JOIN customers c ON c.id = sa.customer_id
             ORDER BY sa.sale_date DESC, sa.id DESC'
        );
    }

    /** Search sales by invoice_no or customer name. */
    public static function searchWithCustomer(string $query): array
    {
        return self::raw(
            'SELECT sa.*, c.name AS customer_name
             FROM sales sa
             LEFT JOIN customers c ON c.id = sa.customer_id
             WHERE sa.invoice_no LIKE :q OR c.name LIKE :q
             ORDER BY sa.sale_date DESC, sa.id DESC',
            ['q' => '%' . $query . '%']
        );
    }

    public static function withItems(int $id): ?array
    {
        $sale = self::raw(
            'SELECT sa.*, c.name AS customer_name, c.phone AS customer_phone
             FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id
             WHERE sa.id = ?',
            [$id]
        );
        if (!$sale) return null;

        $items = self::raw(
            'SELECT si.*, p.name AS product_name, p.sku
             FROM sale_items si JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = ?',
            [$id]
        );

        $sale = $sale[0];
        $sale['items'] = $items;
        return $sale;
    }

    /**
     * Create a sale with its line items atomically. Validates stock
     * availability for every line BEFORE writing anything, so a sale
     * never partially commits and never drives stock negative.
     * $items = [['product_id'=>.., 'qty'=>.., 'unit_price'=>..], ...]
     */
    public static function createWithItems(array $header, array $items): int
    {
        $db = self::db();

        // Validate stock first (fail fast, before touching the DB)
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                throw new RuntimeException('Product not found.');
            }
            if ($product['quantity'] < $item['qty']) {
                throw new RuntimeException(
                    "Not enough stock for \"{$product['name']}\" (available: {$product['quantity']}, requested: {$item['qty']})."
                );
            }
        }

        $db->beginTransaction();
        try {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['qty'] * $item['unit_price'];
            }
            $header['total'] = $total;

            $saleId = self::create($header);

            foreach ($items as $item) {
                $subtotal = $item['qty'] * $item['unit_price'];

                self::db()->prepare(
                    'INSERT INTO sale_items (sale_id, product_id, qty, unit_price, subtotal)
                     VALUES (?,?,?,?,?)'
                )->execute([$saleId, $item['product_id'], $item['qty'], $item['unit_price'], $subtotal]);

                Product::decreaseStock(
                    $item['product_id'],
                    $item['qty'],
                    'sale',
                    $header['invoice_no'],
                    'Sale #' . $header['invoice_no']
                );
            }

            $db->commit();
            return $saleId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
