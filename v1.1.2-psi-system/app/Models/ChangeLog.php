<?php
namespace App\Models;

use App\Core\Model;

/**
 * 变更日志模型 - 记录所有表的变更历史
 */
class ChangeLog extends Model
{
    protected static string $table = 'change_logs';
    protected static array $fillable = ['table_name', 'record_id', 'action', 'old_data', 'new_data', 'user_id'];
    protected static bool $compressData = true; // 是否压缩数据（只记录变更字段）

    /**
     * 记录变更日志
     * 
     * @param string $tableName 表名
     * @param int $recordId 记录ID
     * @param string $action 操作类型：create|update|delete
     * @param array|null $oldData 变更前数据
     * @param array|null $newData 变更后数据
     * @param int|null $userId 操作用户ID
     * @return int 日志ID
     */
    public static function log(
        string $tableName,
        int $recordId,
        string $action,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null
    ): int {
        // 如果没有指定用户ID，尝试从会话中获取
        if ($userId === null) {
            $user = \App\Core\Auth::user();
            $userId = $user['id'] ?? null;
        }
        
        // 压缩数据（如果启用）
        if (self::$compressData && $action === 'update' && $oldData && $newData) {
            $oldData = self::compressData($oldData, $newData);
            $newData = self::compressData($newData, $oldData);
        }

        return self::create([
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'action'     => $action,
            'old_data'   => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data'   => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'user_id'    => $userId,
        ]);
    }

    /**
     * 获取指定记录的变更历史
     * 
     * @param string $tableName 表名
     * @param int $recordId 记录ID
     * @param int $limit 限制数量
     * @return array 变更日志列表
     */
    public static function getHistory(string $tableName, int $recordId, int $limit = 50): array
    {
        $sql = "SELECT cl.*, u.name as user_name 
                FROM change_logs cl 
                LEFT JOIN users u ON u.id = cl.user_id 
                WHERE cl.table_name = ? AND cl.record_id = ? 
                ORDER BY cl.created_at DESC 
                LIMIT ?";
        
        return self::raw($sql, [$tableName, $recordId, $limit]);
    }

    /**
     * 获取指定表的最新变更
     * 
     * @param string $tableName 表名
     * @param int $limit 限制数量
     * @return array 变更日志列表
     */
    public static function getRecentChanges(string $tableName, int $limit = 20): array
    {
        $sql = "SELECT cl.*, u.name as user_name 
                FROM change_logs cl 
                LEFT JOIN users u ON u.id = cl.user_id 
                WHERE cl.table_name = ? 
                ORDER BY cl.created_at DESC 
                LIMIT ?";
        
        return self::raw($sql, [$tableName, $limit]);
    }

    /**
     * 获取所有表的最新变更
     * 
     * @param int $limit 限制数量
     * @return array 变更日志列表
     */
    public static function getAllRecentChanges(int $limit = 50): array
    {
        $sql = "SELECT cl.*, u.name as user_name 
                FROM change_logs cl 
                LEFT JOIN users u ON u.id = cl.user_id 
                ORDER BY cl.created_at DESC 
                LIMIT ?";
        
        return self::raw($sql, [$limit]);
    }

    /** Paginated version of getAllRecentChanges. */
    public static function getAllPaginated(int $page = 1, int $perPage = 20): array
    {
        $countStmt = self::db()->prepare('SELECT COUNT(*) FROM change_logs');
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $dataStmt = self::db()->prepare(
            'SELECT cl.*, u.name as user_name
             FROM change_logs cl
             LEFT JOIN users u ON u.id = cl.user_id
             ORDER BY cl.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    /** Paginated version of getRecentChanges (by table). */
    public static function getRecentChangesPaginated(string $tableName, int $page = 1, int $perPage = 20): array
    {
        $countStmt = self::db()->prepare('SELECT COUNT(*) FROM change_logs WHERE table_name = ?');
        $countStmt->execute([$tableName]);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $dataStmt = self::db()->prepare(
            'SELECT cl.*, u.name as user_name
             FROM change_logs cl
             LEFT JOIN users u ON u.id = cl.user_id
             WHERE cl.table_name = ?
             ORDER BY cl.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $dataStmt->bindValue(1, $tableName);
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages];
    }

    /**
     * 解析JSON数据
     * 
     * @param string|null $json JSON字符串
     * @return array|null 解析后的数组
     */
    public static function parseJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }
        
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 获取变更差异
     * 
     * @param array|null $oldData 旧数据
     * @param array|null $newData 新数据
     * @return array 变更的字段列表
     */
    public static function getChanges(?array $oldData, ?array $newData): array
    {
        if ($oldData === null || $newData === null) {
            return [];
        }

        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[] = [
                    'field' => $key,
                    'old'   => $oldData[$key],
                    'new'   => $value,
                ];
            }
        }

        return $changes;
    }
    
    /**
     * 压缩数据 - 只保留变更的字段
     * 
     * @param array $data 原始数据
     * @param array $compareData 比较数据
     * @return array 压缩后的数据
     */
    private static function compressData(array $data, array $compareData): array
    {
        $compressed = [];
        foreach ($data as $key => $value) {
            // 只保留与比较数据不同的字段
            if (!isset($compareData[$key]) || $compareData[$key] != $value) {
                $compressed[$key] = $value;
            }
        }
        return $compressed;
    }
}