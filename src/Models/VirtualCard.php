<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * Virtual Card Model with Luhn Algorithm
 */
class VirtualCard extends Model
{
    protected static string $table = 'virtual_cards';
    
    protected array $hidden = ['cvv', 'pin_hash'];
    
    protected array $fillable = [
        'user_id', 'wallet_id', 'card_number', 'card_holder_name',
        'expiry_month', 'expiry_year', 'cvv', 'card_type', 'card_brand',
        'spending_limit', 'daily_limit', 'is_active', 'is_online_enabled',
        'is_contactless_enabled', 'pin_hash'
    ];

    /**
     * Generate valid card number using Luhn algorithm
     */
    public static function generateCardNumber(string $type = 'visa'): string
    {
        // Card prefixes by type
        $prefixes = [
            'visa' => '4',
            'mastercard' => '5' . rand(1, 5)
        ];
        
        $prefix = $prefixes[$type] ?? $prefixes['visa'];
        
        // Generate random digits (15 - prefix length to make 15 total)
        $length = 15 - strlen($prefix);
        $number = $prefix;
        
        for ($i = 0; $i < $length; $i++) {
            $number .= rand(0, 9);
        }
        
        // Calculate and append Luhn check digit
        $checkDigit = self::calculateLuhnCheckDigit($number);
        
        return $number . $checkDigit;
    }

    /**
     * Calculate Luhn check digit
     */
    private static function calculateLuhnCheckDigit(string $number): int
    {
        $sum = 0;
        $length = strlen($number);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$length - 1 - $i];
            
            if ($i % 2 === 0) {
                $digit *= 2 ;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Validate card number using Luhn algorithm
     */
    public static function validateLuhn(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number);
        
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        $sum = 0;
        $length = strlen($number);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$length - 1 - $i];
            
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }

    /**
     * Generate CVV
     */
    public static function generateCVV(): string
    {
        return str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate expiry date (3 years from now)
     */
    public static function generateExpiry(): array
    {
        $expiry = strtotime('+3 years');
        
        return [
            'month' => date('m', $expiry),
            'year' => date('Y', $expiry)
        ];
    }

    /**
     * Create new virtual card
     */
    public static function createCard(string $userId, string $walletId, string $holderName, string $type = 'visa'): self
    {
        $expiry = self::generateExpiry();
        $cvv = self::generateCVV();
        
        return self::create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'card_number' => self::generateCardNumber($type),
            'card_holder_name' => strtoupper($holderName),
            'expiry_month' => $expiry['month'],
            'expiry_year' => $expiry['year'],
            'cvv' => password_hash($cvv, PASSWORD_BCRYPT), // Store hashed
            'card_type' => $type,
            'card_brand' => 'debit',
            'spending_limit' => 10000.00,
            'daily_limit' => 5000.00,
            'is_active' => 1,
            'is_online_enabled' => 1,
            'is_contactless_enabled' => 1
        ]);
    }

    /**
     * Get masked card number (for display)
     */
    public function getMaskedNumber(): string
    {
        return substr($this->card_number, 0, 4) . ' **** **** ' . substr($this->card_number, -4);
    }

    /**
     * Get formatted card number
     */
    public function getFormattedNumber(): string
    {
        return chunk_split($this->card_number, 4, ' ');
    }

    /**
     * Get formatted expiry
     */
    public function getFormattedExpiry(): string
    {
        return $this->expiry_month . '/' . substr($this->expiry_year, -2);
    }

    /**
     * Check if card is expired
     */
    public function isExpired(): bool
    {
        $expiry = strtotime($this->expiry_year . '-' . $this->expiry_month . '-01');
        return $expiry < time();
    }

    /**
     * Verify CVV (for payment processing)
     */
    public function verifyCVV(string $cvv): bool
    {
        return password_verify($cvv, $this->cvv);
    }

    /**
     * Set PIN
     */
    public function setPin(string $pin): bool
    {
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            throw new \InvalidArgumentException('PIN must be 4 digits');
        }
        
        $this->pin_hash = password_hash($pin, PASSWORD_BCRYPT);
        return $this->save();
    }

    /**
     * Verify PIN
     */
    public function verifyPin(string $pin): bool
    {
        if ($this->pin_hash === null) {
            return false;
        }
        return password_verify($pin, $this->pin_hash);
    }

    /**
     * Deactivate card
     */
    public function deactivate(): bool
    {
        $this->is_active = 0;
        return $this->save();
    }

    /**
     * Activate card
     */
    public function activate(): bool
    {
        $this->is_active = 1;
        return $this->save();
    }

    /**
     * Toggle online transactions
     */
    public function toggleOnline(bool $enabled): bool
    {
        $this->is_online_enabled = $enabled ? 1 : 0;
        return $this->save();
    }

    /**
     * Toggle contactless transactions
     */
    public function toggleContactless(bool $enabled): bool
    {
        $this->is_contactless_enabled = $enabled ? 1 : 0;
        return $this->save();
    }

    /**
     * Update spending limit
     */
    public function updateSpendingLimit(float $limit): bool
    {
        $this->spending_limit = $limit;
        return $this->save();
    }

    /**
     * Update daily limit
     */
    public function updateDailyLimit(float $limit): bool
    {
        $this->daily_limit = $limit;
        return $this->save();
    }

    /**
     * Get owner user
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * Get linked wallet
     */
    public function wallet(): ?Wallet
    {
        return Wallet::find($this->wallet_id);
    }

    /**
     * Get card transactions
     */
    public function transactions(int $limit = 50): array
    {
        $sql = "SELECT t.* FROM transactions t
                JOIN payment_sessions ps ON t.id = ps.transaction_id
                WHERE ps.card_last_four = ?
                ORDER BY t.created_at DESC
                LIMIT ?";
        
        $rows = $this->db->fetchAll($sql, [substr($this->card_number, -4), $limit]);
        
        return array_map(fn($row) => new Transaction($row), $rows);
    }

    /**
     * Get card type icon/image
     */
    public function getCardTypeIcon(): string
    {
        return match($this->card_type) {
            'visa' => '/assets/images/visa.svg',
            'mastercard' => '/assets/images/mastercard.svg',
            default => '/assets/images/card.svg'
        };
    }

    /**
     * Get card background gradient
     */
    public function getCardGradient(): string
    {
        $gradients = [
            'visa' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'mastercard' => 'linear-gradient(135deg, #ff512f 0%, #f09819 100%)'
        ];
        
        return $gradients[$this->card_type] ?? $gradients['visa'];
    }

    /**
     * Check if can make payment
     */
    public function canMakePayment(float $amount): array
    {
        $result = ['allowed' => true, 'reason' => null];
        
        if (!$this->is_active) {
            return ['allowed' => false, 'reason' => 'Kart aktif değil'];
        }
        
        if ($this->isExpired()) {
            return ['allowed' => false, 'reason' => 'Kartın süresi dolmuş'];
        }
        
        if (!$this->is_online_enabled) {
            return ['allowed' => false, 'reason' => 'Online işlemler kapalı'];
        }
        
        if ($amount > (float) $this->spending_limit) {
            return ['allowed' => false, 'reason' => 'Harcama limiti aşıldı'];
        }
        
        $wallet = $this->wallet();
        if ($wallet && (float) $wallet->available_balance < $amount) {
            return ['allowed' => false, 'reason' => 'Yetersiz bakiye'];
        }
        
        return $result;
    }
}
