<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Connection Handler
 * Singleton pattern with transaction support
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private int $transactionLevel = 0;
    private array $queryLog = [];
    private bool $loggingEnabled = false;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void
    {
        $env = Environment::getInstance();
        
        $host = $env->getString('DB_HOST', 'localhost');
        $port = $env->getString('DB_PORT', '3306');
        $database = $env->getString('DB_DATABASE', 'turkpay');
        $username = $env->getString('DB_USERNAME', 'root');
        $password = $env->getString('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->loggingEnabled = $env->isDebug();
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Execute a query with prepared statements
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            if ($this->loggingEnabled) {
                $this->queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
                ];
            }
            
            return $stmt;
        } catch (PDOException $e) {
            throw new PDOException("Query failed: " . $e->getMessage() . "\nSQL: " . $sql);
        }
    }

    /**
     * Fetch single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    /**
     * Insert and return last insert ID
     */
    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return $this->connection->lastInsertId();
    }

    /**
     * Update rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $stmt = $this->query($sql, [...array_values($data), ...$whereParams]);
        
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $result = $this->connection->beginTransaction();
        } else {
            $this->connection->exec("SAVEPOINT trans_{$this->transactionLevel}");
            $result = true;
        }
        
        $this->transactionLevel++;
        return $result;
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->connection->commit();
        }
        
        return true;
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->connection->rollBack();
        }
        
        $this->connection->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactionLevel}");
        return true;
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Execute callback in transaction
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Select with row locking (FOR UPDATE)
     */
    public function selectForUpdate(string $sql, array $params = []): ?array
    {
        $sql = rtrim($sql, ';') . ' FOR UPDATE';
        return $this->fetchOne($sql, $params);
    }

    /**
     * Generate UUID v4
     */
    public static function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get query log (debug only)
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
