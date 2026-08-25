<?php

/**
 * Database
 *
 * Thin PDO wrapper providing prepared-statement query helpers.
 * Uses a singleton connection so the whole request shares one PDO instance.
 */
class Database
{
    private static ?PDO $instance = null;
    private PDO $pdo;
    private $stmt;

    public function __construct()
    {
        $this->pdo = self::connection();
    }

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('Database connection failed. Please try again later.');
            }
        }

        return self::$instance;
    }

    /** Prepare a statement. */
    public function query(string $sql): self
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    /** Bind a single value to the prepared statement. */
    public function bind($param, $value, $type = null): self
    {
        if ($type === null) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    /** Bind an associative array of params at once. */
    public function bindAll(array $params): self
    {
        foreach ($params as $key => $value) {
            $this->bind(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value);
        }
        return $this;
    }

    public function execute(): bool
    {
        return $this->stmt->execute();
    }

    /** Return all rows. */
    public function resultSet(): array
    {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    /** Return a single row (or false). */
    public function single()
    {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
