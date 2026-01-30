<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Audit Log Model - Security and Compliance
 */
class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected static bool $useUpdatedAt = false;  // audit_logs only has created_at
    
    protected array $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
        'session_id', 'risk_level', 'metadata'
    ];

    /**
     * Risk levels
     */
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?string $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $riskLevel = self::RISK_LOW,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id() ?: null,
            'risk_level' => $riskLevel,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }

    /**
     * Quick log methods
     */
    public static function logLogin(string $userId, bool $success): self
    {
        return self::log(
            $success ? 'login_success' : 'login_failed',
            $userId,
            'user',
            $userId,
            null,
            null,
            $success ? self::RISK_LOW : self::RISK_MEDIUM
        );
    }

    public static function logLogout(string $userId): self
    {
        return self::log('logout', $userId, 'user', $userId);
    }

    public static function logTransaction(string $userId, string $transactionId, string $type, float $amount): self
    {
        return self::log(
            "transaction_{$type}",
            $userId,
            'transaction',
            $transactionId,
            null,
            ['amount' => $amount],
            $amount >= 10000 ? self::RISK_HIGH : self::RISK_LOW
        );
    }

    public static function logPasswordChange(string $userId): self
    {
        return self::log(
            'password_change',
            $userId,
            'user',
            $userId,
            null,
            null,
            self::RISK_HIGH
        );
    }

    public static function logApiAccess(string $merchantId, string $endpoint, string $method): self
    {
        return self::log(
            'api_access',
            null,
            'merchant',
            $merchantId,
            null,
            null,
            self::RISK_LOW,
            ['endpoint' => $endpoint, 'method' => $method]
        );
    }

    public static function logSuspiciousActivity(
        ?string $userId,
        string $description,
        array $details = []
    ): self {
        return self::log(
            'suspicious_activity',
            $userId,
            'security',
            null,
            null,
            null,
            self::RISK_CRITICAL,
            ['description' => $description, 'details' => $details]
        );
    }

    /**
     * Get logs by user
     */
    public static function getByUser(string $userId, int $limit = 100): array
    {
        $db = Database::getInstance();
        
        $rows = $db->fetchAll(
            "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Get high risk logs
     */
    public static function getHighRiskLogs(int $limit = 100): array
    {
        $db = Database::getInstance();
        
        $rows = $db->fetchAll(
            "SELECT * FROM audit_logs WHERE risk_level IN ('high', 'critical') ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Get logs by action type
     */
    public static function getByAction(string $action, int $limit = 100): array
    {
        $db = Database::getInstance();
        
        $rows = $db->fetchAll(
            "SELECT * FROM audit_logs WHERE action = ? ORDER BY created_at DESC LIMIT ?",
            [$action, $limit]
        );
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Search logs
     */
    public static function search(array $filters, int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['risk_level'])) {
            $where[] = 'risk_level = ?';
            $params[] = $filters['risk_level'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to_date'];
        }
        
        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = ?';
            $params[] = $filters['ip_address'];
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT * FROM audit_logs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $rows = $db->fetchAll($sql, $params);
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Get action label
     */
    public function getActionLabel(): string
    {
        return match($this->action) {
            'login_success' => 'Başarılı Giriş',
            'login_failed' => 'Başarısız Giriş',
            'logout' => 'Çıkış',
            'password_change' => 'Şifre Değişikliği',
            'transaction_transfer' => 'Para Transferi',
            'transaction_payment' => 'Ödeme',
            'transaction_deposit' => 'Para Yatırma',
            'transaction_withdrawal' => 'Para Çekme',
            'api_access' => 'API Erişimi',
            'suspicious_activity' => 'Şüpheli Aktivite',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }

    /**
     * Get risk level color
     */
    public function getRiskColor(): string
    {
        return match($this->risk_level) {
            self::RISK_LOW => 'success',
            self::RISK_MEDIUM => 'warning',
            self::RISK_HIGH => 'orange',
            self::RISK_CRITICAL => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get old values as array
     */
    public function getOldValues(): ?array
    {
        return $this->old_values ? json_decode($this->old_values, true) : null;
    }

    /**
     * Get new values as array
     */
    public function getNewValues(): ?array
    {
        return $this->new_values ? json_decode($this->new_values, true) : null;
    }

    /**
     * Get metadata as array
     */
    public function getMetadata(): ?array
    {
        return $this->metadata ? json_decode($this->metadata, true) : null;
    }
}
