<?php
namespace App\Models;

use App\Core\Model;

class Purchase extends Model
{
    protected static string $table = 'purchases';
    protected static array $fillable = ['invoice_no', 'supplier_id', 'purchase_date', 'total', 'created_by'];

    public static function allWithSupplier(): array
    {
        return self::raw(
            'SELECT pu.*, s.name AS supplier_name
             FROM purchases pu
             JOIN suppliers s ON s.id = pu.supplier_id
             ORDER BY pu.purchase_date DESC, pu.id DESC'
        );
    }

    /** Search purchases by invoice_no or supplier name, with optional date range. */
    public static function searchWithSupplier(string $query, string $dateFrom = '', string $dateTo = ''): array
    {
        $sql = 'SELECT pu.*, s.name AS supplier_name
                FROM purchases pu
                JOIN suppliers s ON s.id = pu.supplier_id
                WHERE (pu.invoice_no LIKE :q OR s.name LIKE :q)';
        $params = ['q' => '%' . $query . '%'];

        if ($dateFrom !== '') {
            $sql .= ' AND pu.purchase_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND pu.purchase_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY pu.purchase_date DESC, pu.id DESC';
        return self::raw($sql, $params);
    }

    /** All purchases with supplier, filtered by optional date range. */
    public static function allWithSupplierFiltered(string $dateFrom = '', string $dateTo = ''): array
    {
        $sql = 'SELECT pu.*, s.name AS supplier_name
                FROM purchases pu
                JOIN suppliers s ON s.id = pu.supplier_id
                WHERE 1 = 1';
        $params = [];

        if ($dateFrom !== '') {
            $sql .= ' AND pu.purchase_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND pu.purchase_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY pu.purchase_date DESC, pu.id DESC';
        return self::raw($sql, $params);
    }

    /** Paginated version of searchWithSupplier / allWithSupplierFiltered. */
    public static function filterPaginated(string $query = '', string $dateFrom = '', string $dateTo = '', int $page = 1, int $perPage = 20): array
    {
        $where = ' WHERE 1 = 1';
        $params = [];

        if ($query !== '') {
            $where .= ' AND (pu.invoice_no LIKE :q OR s.name LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        if ($dateFrom !== '') {
            $where .= ' AND pu.purchase_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where .= ' AND pu.purchase_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $join = 'FROM purchases pu JOIN suppliers s ON s.id = pu.supplier_id';

        $countStmt = self::db()->prepare('SELECT COUNT(*) ' . $join . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $dataSql = 'SELECT pu.*, s.name AS supplier_name ' . $join . $where
                   . ' ORDER BY pu.purchase_date DESC, pu.id DESC LIMIT :limit OFFSET :offset';
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
        $purchase = self::raw(
            'SELECT pu.*, s.name AS supplier_name, s.phone AS supplier_phone
             FROM purchases pu JOIN suppliers s ON s.id = pu.supplier_id
             WHERE pu.id = ?',
            [$id]
        );
        if (!$purchase) return null;

        $items = self::raw(
            'SELECT pi.*, p.name AS product_name, p.sku
             FROM purchase_items pi JOIN products p ON p.id = pi.product_id
             WHERE pi.purchase_id = ?',
            [$id]
        );

        $purchase = $purchase[0];
        $purchase['items'] = $items;
        return $purchase;
    }

    /**
     * Create a purchase with its line items, in one atomic operation:
     * insert header, insert each item, and bump product stock for each.
     * $items = [['product_id'=>.., 'qty'=>.., 'unit_cost'=>..], ...]
     */
    public static function createWithItems(array $header, array $items): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['qty'] * $item['unit_cost'];
            }
            $header['total'] = $total;

            $purchaseId = self::create($header);

            foreach ($items as $item) {
                $subtotal = $item['qty'] * $item['unit_cost'];

                self::db()->prepare(
                    'INSERT INTO purchase_items (purchase_id, product_id, qty, unit_cost, subtotal)
                     VALUES (?,?,?,?,?)'
                )->execute([$purchaseId, $item['product_id'], $item['qty'], $item['unit_cost'], $subtotal]);

                Product::increaseStock(
                    $item['product_id'],
                    $item['qty'],
                    'purchase',
                    $header['invoice_no'],
                    'Purchase #' . $header['invoice_no']
                );
            }

            $db->commit();
            return $purchaseId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
