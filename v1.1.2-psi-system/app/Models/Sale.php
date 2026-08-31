<?php
namespace App\Models;

use App\Core\Model;
use App\Core\HasCustomFields;
use RuntimeException;

class Sale extends Model
{
    use HasCustomFields;

    protected static string $table = 'sales';
    protected static array $fillable = ['invoice_no', 'customer_id', 'sale_date', 'total', 'attributes', 'created_by'];

    /**
     * Sale custom fields. Add/edit entries here to get them in the form, list, filter, and CSV.
     * Supported types: text, textarea, select, date, upload.
     * Set 'required' => true to enforce validation on save.
     */
    protected static function customFieldDefinitions(): array
    {
        return [
            'payment_method' => ['label' => 'Payment Method', 'type' => 'select', 'filterable' => true, 'options' => ['Cash', 'Credit Card', 'Bank Transfer', 'Check', 'Other']],
            'shipping_date'  => ['label' => 'Shipping Date',  'type' => 'date',   'filterable' => true],
            'delivery_notes' => ['label' => 'Delivery Notes', 'type' => 'textarea', 'filterable' => false],
        ];
    }

    public static function allWithCustomer(): array
    {
        return self::raw(
            'SELECT sa.*, c.name AS customer_name
             FROM sales sa
             LEFT JOIN customers c ON c.id = sa.customer_id
             ORDER BY sa.sale_date DESC, sa.id DESC'
        );
    }

    /** Search sales by invoice_no or customer name, with optional date range. */
    public static function searchWithCustomer(string $query, string $dateFrom = '', string $dateTo = ''): array
    {
        $sql = 'SELECT sa.*, c.name AS customer_name
                FROM sales sa
                LEFT JOIN customers c ON c.id = sa.customer_id
                WHERE (sa.invoice_no LIKE :q OR c.name LIKE :q)';
        $params = ['q' => '%' . $query . '%'];

        if ($dateFrom !== '') {
            $sql .= ' AND sa.sale_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND sa.sale_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY sa.sale_date DESC, sa.id DESC';
        return self::raw($sql, $params);
    }

    /** All sales with customer, filtered by optional date range. */
    public static function allWithCustomerFiltered(string $dateFrom = '', string $dateTo = ''): array
    {
        $sql = 'SELECT sa.*, c.name AS customer_name
                FROM sales sa
                LEFT JOIN customers c ON c.id = sa.customer_id
                WHERE 1 = 1';
        $params = [];

        if ($dateFrom !== '') {
            $sql .= ' AND sa.sale_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND sa.sale_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY sa.sale_date DESC, sa.id DESC';
        return self::raw($sql, $params);
    }

    /** Paginated version of searchWithCustomer / allWithCustomerFiltered. */
    public static function filterPaginated(string $query = '', string $dateFrom = '', string $dateTo = '', int $page = 1, int $perPage = 20, array $extraFilters = []): array
    {
        $where = ' WHERE 1 = 1';
        $params = [];

        if ($query !== '') {
            $where .= ' AND (sa.invoice_no LIKE :q OR c.name LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        if ($dateFrom !== '') {
            $where .= ' AND sa.sale_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where .= ' AND sa.sale_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        // Custom (JSON) fields — filter via json_extract on the attributes column
        if (!empty($extraFilters)) {
            $cf = self::customFieldFilterSql($extraFilters, 'sa');
            $where .= $cf['sql'];
            $params = array_merge($params, $cf['params']);
        }

        $join = 'FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id';

        $countStmt = self::db()->prepare('SELECT COUNT(*) ' . $join . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $dataSql = 'SELECT sa.*, c.name AS customer_name ' . $join . $where
                   . ' ORDER BY sa.sale_date DESC, sa.id DESC LIMIT :limit OFFSET :offset';
        $dataStmt = self::db()->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue(':' . $k, $v);
        }
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
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
