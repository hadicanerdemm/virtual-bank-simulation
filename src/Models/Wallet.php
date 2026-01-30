<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Wallet Model - Multi-currency wallets
 */
class Wallet extends Model
{
    protected static string $table = 'wallets';
    
    protected array $fillable = [
        'user_id', 'currency', 'balance', 'available_balance',
        'pending_balance', 'is_default', 'status'
    ];

    /**
     * Create default wallets for a new user
     */
    public static function createDefaultWallets(string $userId): array
    {
        $currencies = ['TRY', 'USD', 'EUR'];
        $wallets = [];
        
        foreach ($currencies as $index => $currency) {
            $wallets[] = self::create([
                'user_id' => $userId,
                'currency' => $currency,
                'balance' => 0.00,
                'available_balance' => 0.00,
                'pending_balance' => 0.00,
                'is_default' => $index === 0 ? 1 : 0,
                'status' => 'active'
            ]);
        }
        
        return $wallets;
    }

    /**
     * Get wallet by user and currency
     */
    public static function findByUserAndCurrency(string $userId, string $currency): ?self
    {
        $db = Database::getInstance();
        
        $row = $db->fetchOne(
            "SELECT * FROM wallets WHERE user_id = ? AND currency = ?",
            [$userId, $currency]
        );
        
        return $row ? new self($row) : null;
    }

    /**
     * Credit (add) amount to wallet - WITH ROW LOCKING
     */
    public function credit(float $amount, string $description = ''): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }
        
        if ($this->status !== 'active') {
            throw new \Exception('Wallet is not active');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Lock the row for update
            $wallet = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$this->id]
            );
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            $newBalance = (float) $wallet['balance'] + $amount;
            $newAvailable = (float) $wallet['available_balance'] + $amount;
            
            // Update balance
            $this->db->query(
                "UPDATE wallets SET balance = ?, available_balance = ?, updated_at = NOW() WHERE id = ?",
                [$newBalance, $newAvailable, $this->id]
            );
            
            // Update local attributes
            $this->balance = $newBalance;
            $this->available_balance = $newAvailable;
            
            $this->db->commit();
            return true;
            
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Debit (subtract) amount from wallet - WITH ROW LOCKING
     */
    public function debit(float $amount, string $description = ''): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }
        
        if ($this->status !== 'active') {
            throw new \Exception('Wallet is not active');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Lock the row for update - CRITICAL for race condition prevention
            $wallet = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$this->id]
            );
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            $currentAvailable = (float) $wallet['available_balance'];
            
            // Check sufficient balance
            if ($currentAvailable < $amount) {
                throw new \Exception('Insufficient balance');
            }
            
            $newBalance = (float) $wallet['balance'] - $amount;
            $newAvailable = $currentAvailable - $amount;
            
            // Update balance
            $this->db->query(
                "UPDATE wallets SET balance = ?, available_balance = ?, updated_at = NOW() WHERE id = ?",
                [$newBalance, $newAvailable, $this->id]
            );
            
            // Update local attributes
            $this->balance = $newBalance;
            $this->available_balance = $newAvailable;
            
            $this->db->commit();
            return true;
            
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Hold (reserve) amount - moves from available to pending
     */
    public function hold(float $amount): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Hold amount must be positive');
        }
        
        $this->db->beginTransaction();
        
        try {
            $wallet = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$this->id]
            );
            
            if ((float) $wallet['available_balance'] < $amount) {
                throw new \Exception('Insufficient available balance');
            }
            
            $this->db->query(
                "UPDATE wallets SET available_balance = available_balance - ?, pending_balance = pending_balance + ?, updated_at = NOW() WHERE id = ?",
                [$amount, $amount, $this->id]
            );
            
            $this->available_balance = (float) $wallet['available_balance'] - $amount;
            $this->pending_balance = (float) $wallet['pending_balance'] + $amount;
            
            $this->db->commit();
            return true;
            
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Release held amount back to available
     */
    public function releaseHold(float $amount): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Release amount must be positive');
        }
        
        $this->db->beginTransaction();
        
        try {
            $wallet = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$this->id]
            );
            
            $this->db->query(
                "UPDATE wallets SET available_balance = available_balance + ?, pending_balance = pending_balance - ?, updated_at = NOW() WHERE id = ?",
                [$amount, $amount, $this->id]
            );
            
            $this->db->commit();
            return true;
            
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Capture held amount (complete pending transaction)
     */
    public function captureHold(float $amount): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Capture amount must be positive');
        }
        
        $this->db->beginTransaction();
        
        try {
            $wallet = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$this->id]
            );
            
            $this->db->query(
                "UPDATE wallets SET balance = balance - ?, pending_balance = pending_balance - ?, updated_at = NOW() WHERE id = ?",
                [$amount, $amount, $this->id]
            );
            
            $this->db->commit();
            return true;
            
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get owner user
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get virtual cards linked to this wallet
     */
    public function virtualCards(): array
    {
        return VirtualCard::where('wallet_id', $this->id);
    }

    /**
     * Get transactions for this wallet
     */
    public function transactions(int $limit = 50): array
    {
        $sql = "SELECT * FROM transactions 
                WHERE source_wallet_id = ? OR destination_wallet_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $rows = $this->db->fetchAll($sql, [$this->id, $this->id, $limit]);
        
        return array_map(fn($row) => new Transaction($row), $rows);
    }

    /**
     * Freeze wallet
     */
    public function freeze(): bool
    {
        $this->status = 'frozen';
        return $this->save();
    }

    /**
     * Unfreeze wallet
     */
    public function unfreeze(): bool
    {
        $this->status = 'active';
        return $this->save();
    }

    /**
     * Format balance for display
     */
    public function formatBalance(): string
    {
        $symbols = [
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€'
        ];
        
        $symbol = $symbols[$this->currency] ?? $this->currency;
        
        return $symbol . number_format((float) $this->balance, 2, ',', '.');
    }

    /**
     * Alias for formatBalance
     */
    public function getFormattedBalance(): string
    {
        return $this->formatBalance();
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbol(): string
    {
        return match($this->currency) {
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            default => $this->currency
        };
    }
}
