<?php
namespace App\Core;

use PDO;
use App\Models\ChangeLog;

/**
 * Base Model.
 * Every model extends this and sets $table + $fillable.
 * Gives every model consistent, predictable CRUD + query-building
 * without needing a heavy ORM dependency.
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static bool $logChanges = true; // 是否记录变更日志

    /**
     * Whitelist of columns that may be written via create()/update().
     * An empty array means "allow all" (backward compatible). Defining it
     * is strongly recommended to prevent accidental mass-assignment.
     */
    protected static array $fillable = [];

    public static function db(): PDO
    {
        return Database::connect();
    }

    public static function all(string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::$table;
        if ($orderBy) $sql .= ' ORDER BY ' . $orderBy;
        return static::db()->query($sql)->fetchAll();
    }

    public static function find($id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBy(string $column, $value): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM ' . static::$table . " WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function where(string $column, $value, string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::$table . " WHERE {$column} = ?";
        if ($orderBy) $sql .= ' ORDER BY ' . $orderBy;
        $stmt = static::db()->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    /** Check whether a value already exists in a column (optionally excluding a row id). */
    public static function exists(string $column, $value, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . static::$table . " WHERE {$column} = ?";
        $params = [$value];
        if ($excludeId !== null) {
            $sql .= ' AND ' . static::$primaryKey . ' != ?';
            $params[] = $excludeId;
        }
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Keep only columns whitelisted in $fillable (no-op when $fillable is empty). */
    protected static function filterFillable(array $data): array
    {
        if (empty(static::$fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }

    public static function create(array $data): int
    {
        $data = static::filterFillable($data);
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = static::db()->prepare($sql);
        $stmt->execute($data);

        $id = (int) static::db()->lastInsertId();
        
        // 记录变更日志
        if (static::$logChanges && static::$table !== 'change_logs') {
            ChangeLog::log(static::$table, $id, 'create', null, $data);
        }
        
        return $id;
    }

    public static function update($id, array $data): bool
    {
        $data = static::filterFillable($data);

        // 获取旧数据用于日志记录
        $oldData = null;
        if (static::$logChanges && static::$table !== 'change_logs') {
            $oldData = self::find($id);
        }
        
        $set = implode(',', array_map(fn($c) => "{$c} = :{$c}", array_keys($data)));
        $sql = 'UPDATE ' . static::$table . " SET {$set} WHERE " . static::$primaryKey . ' = :id';
        $data['id'] = $id;
        $stmt = static::db()->prepare($sql);
        $result = $stmt->execute($data);
        
        // 记录变更日志
        if ($result && static::$logChanges && static::$table !== 'change_logs') {
            ChangeLog::log(static::$table, $id, 'update', $oldData, $data);
        }
        
        return $result;
    }

    public static function delete($id): bool
    {
        // 获取旧数据用于日志记录
        $oldData = null;
        if (static::$logChanges && static::$table !== 'change_logs') {
            $oldData = self::find($id);
        }
        
        $stmt = static::db()->prepare('DELETE FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?');
        $result = $stmt->execute([$id]);
        
        // 记录变更日志
        if ($result && static::$logChanges && static::$table !== 'change_logs') {
            ChangeLog::log(static::$table, $id, 'delete', $oldData, null);
        }
        
        return $result;
    }

    public static function count(): int
    {
        return (int) static::db()->query('SELECT COUNT(*) FROM ' . static::$table)->fetchColumn();
    }

    /** Run raw SQL when a model needs a custom query (joins, aggregates, etc). */
    public static function raw(string $sql, array $params = []): array
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Simple LIKE search across one or more columns.
     * Usage: Product::search('foo', ['sku', 'name'])
     */
    public static function search(string $query, array $columns, string $orderBy = null): array
    {
        if ($query === '' || empty($columns)) {
            return static::all($orderBy);
        }

        $conditions = [];
        foreach ($columns as $col) {
            $conditions[] = "{$col} LIKE :q";
        }
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE ' . implode(' OR ', $conditions);
        if ($orderBy) $sql .= ' ORDER BY ' . $orderBy;

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['q' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }
}
