<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Transaction Model - Double-Entry Bookkeeping
 */
class Transaction extends Model
{
    protected static string $table = 'transactions';
    
    protected array $fillable = [
        'reference_id', 'idempotency_key', 'type', 'status',
        'source_wallet_id', 'destination_wallet_id',
        'source_user_id', 'destination_user_id', 'merchant_id',
        'amount', 'fee', 'currency', 'exchange_rate',
        'converted_amount', 'converted_currency',
        'description', 'metadata', 'ip_address', 'user_agent'
    ];

    /**
     * Generate unique reference ID
     */
    public static function generateReferenceId(): string
    {
        return strtoupper('TRX' . date('Ymd') . bin2hex(random_bytes(8)));
    }

    /**
     * Check if idempotency key already exists
     */
    public static function idempotencyKeyExists(string $key): bool
    {
        return self::exists('idempotency_key', $key);
    }

    /**
     * Find by idempotency key
     */
    public static function findByIdempotencyKey(string $key): ?self
    {
        return self::findBy('idempotency_key', $key);
    }

    /**
     * Find by reference ID
     */
    public static function findByReference(string $referenceId): ?self
    {
        return self::findBy('reference_id', $referenceId);
    }

    /**
     * Get transactions by user
     */
    public static function getByUser(string $userId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM transactions 
                WHERE source_user_id = ? OR destination_user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $rows = $db->fetchAll($sql, [$userId, $userId, $limit, $offset]);
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Get pending transactions requiring approval
     */
    public static function getPendingApprovals(int $limit = 50): array
    {
        $db = Database::getInstance();
        
        $rows = $db->fetchAll(
            "SELECT * FROM transactions WHERE status = 'requires_approval' ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
        
        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Check if transaction requires admin approval
     */
    public static function requiresApproval(float $amount): bool
    {
        $threshold = (float) ($_ENV['ADMIN_APPROVAL_THRESHOLD'] ?? 50000);
        return $amount >= $threshold;
    }

    /**
     * Mark transaction as completed
     */
    public function complete(): bool
    {
        $this->status = 'completed';
        $this->completed_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Mark transaction as failed
     */
    public function fail(string $reason): bool
    {
        $this->status = 'failed';
        $this->failed_at = date('Y-m-d H:i:s');
        $this->failure_reason = $reason;
        return $this->save();
    }

    /**
     * Mark transaction as cancelled
     */
    public function cancel(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Approve transaction (admin)
     */
    public function approve(string $adminId): bool
    {
        $this->approved_by = $adminId;
        $this->approved_at = date('Y-m-d H:i:s');
        $this->status = 'processing';
        return $this->save();
    }

    /**
     * Create ledger entries for double-entry bookkeeping
     */
    public function createLedgerEntries(
        string $debitWalletId, 
        float $debitBalanceBefore, 
        float $debitBalanceAfter,
        string $creditWalletId, 
        float $creditBalanceBefore, 
        float $creditBalanceAfter
    ): void {
        $db = Database::getInstance();
        
        // Debit entry (money leaving)
        $db->query(
            "INSERT INTO ledger_entries (id, transaction_id, wallet_id, entry_type, amount, balance_before, balance_after, description) VALUES (?, ?, ?, 'debit', ?, ?, ?, ?)",
            [
                Database::generateUUID(),
                $this->id,
                $debitWalletId,
                $this->amount,
                $debitBalanceBefore,
                $debitBalanceAfter,
                'Para çıkışı'
            ]
        );
        
        // Credit entry (money entering)
        $db->query(
            "INSERT INTO ledger_entries (id, transaction_id, wallet_id, entry_type, amount, balance_before, balance_after, description) VALUES (?, ?, ?, 'credit', ?, ?, ?, ?)",
            [
                Database::generateUUID(),
                $this->id,
                $creditWalletId,
                $this->converted_amount ?? $this->amount,
                $creditBalanceBefore,
                $creditBalanceAfter,
                'Para girişi'
            ]
        );
    }

    /**
     * Get ledger entries
     */
    public function ledgerEntries(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM ledger_entries WHERE transaction_id = ? ORDER BY created_at",
            [$this->id]
        );
    }

    /**
     * Get source wallet
     */
    public function sourceWallet(): ?Wallet
    {
        return $this->source_wallet_id ? Wallet::find($this->source_wallet_id) : null;
    }

    /**
     * Get destination wallet
     */
    public function destinationWallet(): ?Wallet
    {
        return $this->destination_wallet_id ? Wallet::find($this->destination_wallet_id) : null;
    }

    /**
     * Get source user
     */
    public function sourceUser(): ?User
    {
        return $this->source_user_id ? User::find($this->source_user_id) : null;
    }

    /**
     * Get destination user
     */
    public function destinationUser(): ?User
    {
        return $this->destination_user_id ? User::find($this->destination_user_id) : null;
    }

    /**
     * Get transaction direction relative to user
     */
    public function getDirection(string $userId): string
    {
        if ($this->source_user_id === $userId) {
            return 'outgoing';
        }
        if ($this->destination_user_id === $userId) {
            return 'incoming';
        }
        return 'unknown';
    }

    /**
     * Get formatted amount
     */
    public function formatAmount(): string
    {
        $symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€'];
        $symbol = $symbols[$this->currency] ?? $this->currency;
        return $symbol . number_format((float) $this->amount, 2, ',', '.');
    }

    /**
     * Get transaction type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'transfer' => 'Para Transferi',
            'deposit' => 'Para Yatırma',
            'withdrawal' => 'Para Çekme',
            'payment' => 'Ödeme',
            'refund' => 'İade',
            'fee' => 'Komisyon',
            'exchange' => 'Döviz Çevirme',
            'reversal' => 'İptal',
            default => ucfirst($this->type)
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Beklemede',
            'processing' => 'İşleniyor',
            'completed' => 'Tamamlandı',
            'failed' => 'Başarısız',
            'cancelled' => 'İptal Edildi',
            'requires_approval' => 'Onay Bekliyor',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get status color class
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'failed', 'cancelled' => 'danger',
            'pending', 'requires_approval' => 'warning',
            'processing' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Alias for formatAmount
     */
    public function getFormattedAmount(): string
    {
        return $this->formatAmount();
    }

    /**
     * Get status badge class
     */
    public function getStatusBadge(): string
    {
        return 'badge-' . $this->getStatusColor();
    }

    /**
     * Get daily transaction sum for user
     */
    public static function getDailySum(string $userId): float
    {
        $db = Database::getInstance();
        
        $sum = $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE source_user_id = ? 
             AND status IN ('completed', 'processing', 'pending') 
             AND DATE(created_at) = CURDATE()",
            [$userId]
        );
        
        return (float) $sum;
    }

    /**
     * Get monthly statistics for user
     */
    public static function getMonthlyStats(string $userId): array
    {
        $db = Database::getInstance();
        
        $incoming = $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE destination_user_id = ? AND status = 'completed' 
             AND MONTH(created_at) = MONTH(CURDATE()) 
             AND YEAR(created_at) = YEAR(CURDATE())",
            [$userId]
        );
        
        $outgoing = $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE source_user_id = ? AND status = 'completed' 
             AND MONTH(created_at) = MONTH(CURDATE()) 
             AND YEAR(created_at) = YEAR(CURDATE())",
            [$userId]
        );
        
        return [
            'incoming' => (float) $incoming,
            'outgoing' => (float) $outgoing,
            'net' => (float) $incoming - (float) $outgoing
        ];
    }
}
