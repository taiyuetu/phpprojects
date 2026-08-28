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

    /** Paginated version of recent / searchRecent. */
    public static function filterPaginated(string $query = '', int $page = 1, int $perPage = 20): array
    {
        $where = '';
        $params = [];

        if ($query !== '') {
            $where = ' WHERE p.name LIKE :q OR p.sku LIKE :q OR it.reference LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $join = 'FROM inventory_transactions it JOIN products p ON p.id = it.product_id';

        $countStmt = self::db()->prepare('SELECT COUNT(*) ' . $join . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $dataSql = 'SELECT it.*, p.name AS product_name, p.sku ' . $join . $where
                   . ' ORDER BY it.created_at DESC, it.id DESC LIMIT :limit OFFSET :offset';
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
}
