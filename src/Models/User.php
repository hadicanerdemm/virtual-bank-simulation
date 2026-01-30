<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * User Model - Bank Customers
 */
class User extends Model
{
    protected static string $table = 'users';
    
    protected array $fillable = [
        'email', 'password', 'first_name', 'last_name', 
        'phone', 'identity_number', 'date_of_birth', 'avatar',
        'status', 'role'
    ];

    /**
     * Create new user with password hashing
     */
    public static function register(array $data): self
    {
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $data['status'] = 'active';
        $data['role'] = 'user';
        
        $user = self::create($data);
        
        // Create default wallets
        Wallet::createDefaultWallets($user->id);
        
        return $user;
    }

    /**
     * Authenticate user
     */
    public static function authenticate(string $email, string $password): ?self
    {
        $user = self::findBy('email', $email);
        
        if ($user === null) {
            return null;
        }
        
        // Check if account is locked
        if ($user->isLocked()) {
            return null;
        }
        
        // Verify password
        if (!password_verify($password, $user->password)) {
            $user->incrementFailedAttempts();
            return null;
        }
        
        // Reset failed attempts and update login info
        $user->resetFailedAttempts();
        $user->updateLoginInfo();
        
        return $user;
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        if ($this->status === 'locked') {
            return true;
        }
        
        if ($this->locked_until !== null) {
            $lockedUntil = strtotime($this->locked_until);
            if ($lockedUntil > time()) {
                return true;
            }
            // Lock expired, reset
            $this->resetFailedAttempts();
        }
        
        return false;
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts(): void
    {
        $this->failed_login_attempts = ($this->failed_login_attempts ?? 0) + 1;
        
        // Lock after 3 failed attempts for 15 minutes
        if ($this->failed_login_attempts >= 3) {
            $this->locked_until = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        }
        
        $this->save();
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    /**
     * Update login info
     */
    public function updateLoginInfo(): void
    {
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->last_login_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->save();
    }

    /**
     * Get user's full name
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get user's wallets
     */
    public function wallets(): array
    {
        return Wallet::where('user_id', $this->id);
    }

    /**
     * Get user's default wallet
     */
    public function defaultWallet(): ?Wallet
    {
        $wallets = Wallet::where('user_id', $this->id);
        foreach ($wallets as $wallet) {
            if ($wallet->is_default) {
                return $wallet;
            }
        }
        return $wallets[0] ?? null;
    }

    /**
     * Get user's virtual cards
     */
    public function virtualCards(): array
    {
        return VirtualCard::where('user_id', $this->id);
    }

    /**
     * Get user's transactions
     */
    public function transactions(int $limit = 50): array
    {
        $sql = "SELECT * FROM transactions 
                WHERE source_user_id = ? OR destination_user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $rows = $this->db->fetchAll($sql, [$this->id, $this->id, $limit]);
        
        return array_map(fn($row) => new Transaction($row), $rows);
    }

    /**
     * Get user's notifications
     */
    public function notifications(bool $unreadOnly = false, int $limit = 20): array
    {
        $where = "user_id = ?";
        if ($unreadOnly) {
            $where .= " AND is_read = 0";
        }
        
        $sql = "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT ?";
        
        return $this->db->fetchAll($sql, [$this->id, $limit]);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Generate and save session
     */
    public function createSession(): string
    {
        $sessionId = bin2hex(random_bytes(64));
        
        $this->db->query(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity) VALUES (?, ?, ?, ?, ?, ?)",
            [
                $sessionId,
                $this->id,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode(['user_id' => $this->id]),
                time()
            ]
        );
        
        return $sessionId;
    }

    /**
     * Verify session and get user
     */
    public static function fromSession(string $sessionId): ?self
    {
        $db = Database::getInstance();
        
        $session = $db->fetchOne(
            "SELECT * FROM sessions WHERE id = ? AND last_activity > ?",
            [$sessionId, time() - 7200] // 2 hours
        );
        
        if ($session === null || !isset($session['user_id'])) {
            return null;
        }
        
        // Update last activity
        $db->query("UPDATE sessions SET last_activity = ? WHERE id = ?", [time(), $sessionId]);
        
        return self::find($session['user_id']);
    }

    /**
     * Add balance from bonus/promotion
     */
    public function addBonusBalance(float $amount, string $currency = 'TRY', string $description = 'Hoş geldin bonusu'): void
    {
        $wallet = null;
        foreach ($this->wallets() as $w) {
            if ($w->currency === $currency) {
                $wallet = $w;
                break;
            }
        }
        
        if ($wallet) {
            $wallet->credit($amount, $description);
        }
    }
}
