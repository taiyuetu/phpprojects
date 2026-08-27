<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Database — thin PDO singleton.
 * Reads driver settings from config/config.php so switching between
 * SQLite (zero-config, great for dev/demo) and MySQL (production) is a
 * one-line change.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/../../config/config.php';
        $db = $config['db'];

        try {
            if ($db['driver'] === 'sqlite') {
                $dsn = 'sqlite:' . $db['sqlite_path'];
                self::$instance = new PDO($dsn);
                self::$instance->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
                self::$instance = new PDO($dsn, $db['user'], $db['pass']);
            }

            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }
}
