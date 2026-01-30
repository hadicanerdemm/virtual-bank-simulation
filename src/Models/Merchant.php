<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Merchant Model - Payment Gateway Merchants
 */
class Merchant extends Model
{
    protected static string $table = 'merchants';
    
    protected array $fillable = [
        'user_id', 'business_name', 'business_type', 'tax_number',
        'website', 'logo', 'description', 'webhook_url', 'webhook_secret',
        'is_sandbox', 'status', 'daily_limit', 'monthly_limit', 'commission_rate'
    ];

    /**
     * Generate secure API key
     */
    public static function generateApiKey(): string
    {
        return 'pk_' . (($_ENV['APP_ENV'] ?? 'development') === 'production' ? 'live_' : 'test_') . bin2hex(random_bytes(24));
    }

    /**
     * Generate secure API secret
     */
    public static function generateApiSecret(): string
    {
        return 'sk_' . (($_ENV['APP_ENV'] ?? 'development') === 'production' ? 'live_' : 'test_') . bin2hex(random_bytes(48));
    }

    /**
     * Create new merchant
     */
    public static function register(string $userId, array $data): self
    {
        $data['user_id'] = $userId;
        $data['api_key'] = self::generateApiKey();
        $data['api_secret'] = self::generateApiSecret();
        $data['webhook_secret'] = bin2hex(random_bytes(32));
        $data['status'] = 'pending';
        $data['is_sandbox'] = 1;
        
        return self::create($data);
    }

    /**
     * Find by API key
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        return self::findBy('api_key', $apiKey);
    }

    /**
     * Authenticate merchant by API key and secret
     */
    public static function authenticate(string $apiKey, string $apiSecret): ?self
    {
        $merchant = self::findByApiKey($apiKey);
        
        if ($merchant === null) {
            return null;
        }
        
        if ($merchant->api_secret !== $apiSecret) {
            return null;
        }
        
        if ($merchant->status !== 'active') {
            return null;
        }
        
        return $merchant;
    }

    /**
     * Regenerate API keys
     */
    public function regenerateKeys(): array
    {
        $this->api_key = self::generateApiKey();
        $this->api_secret = self::generateApiSecret();
        $this->save();
        
        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret
        ];
    }

    /**
     * Get owner user
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get merchant's transactions
     */
    public function transactions(int $limit = 50, int $offset = 0): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM transactions WHERE merchant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$this->id, $limit, $offset]
        );
        
        return array_map(fn($row) => new Transaction($row), $rows);
    }

    /**
     * Get today's transaction total
     */
    public function getDailyTotal(): float
    {
        $sum = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE merchant_id = ? AND status = 'completed' 
             AND DATE(created_at) = CURDATE()",
            [$this->id]
        );
        
        return (float) $sum;
    }

    /**
     * Get monthly transaction total
     */
    public function getMonthlyTotal(): float
    {
        $sum = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE merchant_id = ? AND status = 'completed' 
             AND MONTH(created_at) = MONTH(CURDATE()) 
             AND YEAR(created_at) = YEAR(CURDATE())",
            [$this->id]
        );
        
        return (float) $sum;
    }

    /**
     * Check if within daily limit
     */
    public function isWithinDailyLimit(float $amount): bool
    {
        $dailyTotal = $this->getDailyTotal();
        return ($dailyTotal + $amount) <= (float) $this->daily_limit;
    }

    /**
     * Check if within monthly limit
     */
    public function isWithinMonthlyLimit(float $amount): bool
    {
        $monthlyTotal = $this->getMonthlyTotal();
        return ($monthlyTotal + $amount) <= (float) $this->monthly_limit;
    }

    /**
     * Calculate commission for amount
     */
    public function calculateCommission(float $amount): float
    {
        return round($amount * (float) $this->commission_rate, 2);
    }

    /**
     * Get webhooks
     */
    public function webhooks(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE merchant_id = ? ORDER BY created_at DESC",
            [$this->id]
        );
    }

    /**
     * Get webhook logs
     */
    public function webhookLogs(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT wl.* FROM webhook_logs wl 
             JOIN webhooks w ON wl.webhook_id = w.id 
             WHERE w.merchant_id = ? 
             ORDER BY wl.created_at DESC 
             LIMIT ?",
            [$this->id, $limit]
        );
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $db = $this->db;
        
        $totalTransactions = $db->fetchColumn(
            "SELECT COUNT(*) FROM transactions WHERE merchant_id = ?",
            [$this->id]
        );
        
        $totalVolume = $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE merchant_id = ? AND status = 'completed'",
            [$this->id]
        );
        
        $successRate = $db->fetchColumn(
            "SELECT ROUND(
                (SELECT COUNT(*) FROM transactions WHERE merchant_id = ? AND status = 'completed') * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM transactions WHERE merchant_id = ?), 0)
            , 2)",
            [$this->id, $this->id]
        );
        
        return [
            'total_transactions' => (int) $totalTransactions,
            'total_volume' => (float) $totalVolume,
            'success_rate' => (float) ($successRate ?? 0),
            'daily_total' => $this->getDailyTotal(),
            'monthly_total' => $this->getMonthlyTotal()
        ];
    }

    /**
     * Switch to production mode
     */
    public function goLive(): bool
    {
        $this->is_sandbox = 0;
        $this->api_key = self::generateApiKey();
        $this->api_secret = self::generateApiSecret();
        return $this->save();
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'active' => 'Aktif',
            'suspended' => 'Askıya Alındı',
            'pending' => 'Onay Bekliyor',
            'rejected' => 'Reddedildi',
            default => ucfirst($this->status)
        };
    }
}
