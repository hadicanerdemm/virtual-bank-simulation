<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Base Model with common CRUD operations
 */
abstract class Model
{
    protected Database $db;
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $useUpdatedAt = true;  // Set to false for tables without updated_at
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $hidden = ['password', 'api_secret', 'cvv', 'two_factor_secret'];

    public function __construct(array $attributes = [])
    {
        $this->db = Database::getInstance();
        $this->fill($attributes);
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        $data = $this->attributes;
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Find by primary key
     */
    public static function find(string $id): ?static
    {
        $db = Database::getInstance();
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $row = $db->fetchOne("SELECT * FROM {$table} WHERE {$pk} = ?", [$id]);
        
        if ($row === null) {
            return null;
        }
        
        return new static($row);
    }

    /**
     * Find by primary key or throw exception
     */
    public static function findOrFail(string $id): static
    {
        $model = static::find($id);
        
        if ($model === null) {
            throw new \Exception(static::class . " not found with ID: {$id}");
        }
        
        return $model;
    }

    /**
     * Find by specific column
     */
    public static function findBy(string $column, mixed $value): ?static
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $row = $db->fetchOne("SELECT * FROM {$table} WHERE {$column} = ?", [$value]);
        
        if ($row === null) {
            return null;
        }
        
        return new static($row);
    }

    /**
     * Get all records
     */
    public static function all(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $rows = $db->fetchAll("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT ? OFFSET ?", [$limit, $offset]);
        
        return array_map(fn($row) => new static($row), $rows);
    }

    /**
     * Find with conditions
     */
    public static function where(string $column, mixed $value, string $operator = '='): array
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $rows = $db->fetchAll("SELECT * FROM {$table} WHERE {$column} {$operator} ?", [$value]);
        
        return array_map(fn($row) => new static($row), $rows);
    }

    /**
     * Count records
     */
    public static function count(string $where = '1', array $params = []): int
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
    }

    /**
     * Create new record
     */
    public static function create(array $data): static
    {
        $db = Database::getInstance();
        $table = static::$table;
        $pk = static::$primaryKey;
        
        // Generate UUID if not provided
        if (!isset($data[$pk])) {
            $data[$pk] = Database::generateUUID();
        }
        
        // Add timestamps
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        if (static::$useUpdatedAt) {
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $db->query("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", array_values($data));
        
        return new static($data);
    }

    /**
     * Update record
     */
    public function save(): bool
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        
        $data = $this->attributes;
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        unset($data[$pk]);
        unset($data['created_at']);
        
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$pk} = ?";
        $params = [...array_values($data), $this->attributes[$pk]];
        
        $this->db->query($sql, $params);
        
        return true;
    }

    /**
     * Delete record (soft delete if supported)
     */
    public function delete(): bool
    {
        $table = static::$table;
        $pk = static::$primaryKey;
        
        // For banking, we don't actually delete - we just mark as deleted
        if (isset($this->attributes['status'])) {
            $this->attributes['status'] = 'deleted';
            return $this->save();
        }
        
        $this->db->query("DELETE FROM {$table} WHERE {$pk} = ?", [$this->attributes[$pk]]);
        return true;
    }

    /**
     * Check if record exists
     */
    public static function exists(string $column, mixed $value): bool
    {
        $db = Database::getInstance();
        $table = static::$table;
        
        $count = (int) $db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$value]);
        
        return $count > 0;
    }
}
