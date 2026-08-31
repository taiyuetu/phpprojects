<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Auth;

class PurchaseArrival extends Model
{
    protected static string $table = 'purchase_arrivals';
    protected static array $fillable = ['purchase_id', 'arrival_date', 'qty', 'notes', 'created_by'];

    /** Get all arrivals for a purchase, ordered by date */
    public static function byPurchase(int $purchaseId): array
    {
        return self::raw(
            'SELECT pa.*, u.name AS created_by_name
             FROM purchase_arrivals pa
             LEFT JOIN users u ON u.id = pa.created_by
             WHERE pa.purchase_id = ?
             ORDER BY pa.arrival_date ASC, pa.id ASC',
            [$purchaseId]
        );
    }

    /** Get total arrived qty for a purchase */
    public static function totalArrivedQty(int $purchaseId): int
    {
        $result = self::raw(
            'SELECT COALESCE(SUM(qty), 0) AS total FROM purchase_arrivals WHERE purchase_id = ?',
            [$purchaseId]
        );
        return (int)($result[0]['total'] ?? 0);
    }

    /** Record a new arrival and update product stock */
    public static function recordArrival(int $purchaseId, string $arrivalDate, int $qty, string $notes = ''): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            // Get purchase info
            $purchase = Purchase::find($purchaseId);
            if (!$purchase) throw new \Exception('Purchase not found');

            // Get purchase items to distribute qty
            $items = PurchaseItem::byPurchase($purchaseId);
            if (empty($items)) throw new \Exception('No items in this purchase');

            // Create arrival record
            $arrivalId = self::create([
                'purchase_id'  => $purchaseId,
                'arrival_date' => $arrivalDate,
                'qty'          => $qty,
                'notes'        => $notes,
                'created_by'   => Auth::user()['id'] ?? null,
            ]);

            // Distribute qty across items proportionally
            $totalOrdered = 0;
            foreach ($items as $item) {
                $totalOrdered += (int)$item['qty'];
            }

            if ($totalOrdered > 0) {
                foreach ($items as $item) {
                    $itemRatio = (int)$item['qty'] / $totalOrdered;
                    $itemActualQty = (int)round($qty * $itemRatio);

                    if ($itemActualQty > 0) {
                        Product::increaseStock(
                            (int)$item['product_id'],
                            $itemActualQty,
                            'purchase_arrival',
                            $purchase['invoice_no'],
                            'Purchase Arrival #' . $purchase['invoice_no'] . ' (Batch)'
                        );
                    }
                }
            }

            $db->commit();
            return $arrivalId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
