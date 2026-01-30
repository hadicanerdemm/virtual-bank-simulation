<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Transaction;
use App\Models\AuditLog;

/**
 * Fraud Detection Service
 */
class FraudDetectionService
{
    private Database $db;

    // Configuration
    private float $singleTransactionLimit;
    private float $dailyTransactionLimit;
    private int $maxTransactionsPerMinute;
    private int $maxFailedLoginsBeforeLock;

    public function __construct()
    {
        $this->db = Database::getInstance();
        
        $this->singleTransactionLimit = (float) ($_ENV['MAX_SINGLE_TRANSACTION'] ?? 50000);
        $this->dailyTransactionLimit = (float) ($_ENV['DAILY_TRANSACTION_LIMIT'] ?? 200000);
        $this->maxTransactionsPerMinute = 5;
        $this->maxFailedLoginsBeforeLock = (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 3);
    }

    /**
     * Check if transfer is allowed
     */
    public function checkTransfer(string $userId, float $amount): array
    {
        // Check single transaction limit
        if ($amount > $this->singleTransactionLimit) {
            return [
                'allowed' => false,
                'reason' => 'Tek seferde maksimum transfer limiti aşıldı (₺' . number_format($this->singleTransactionLimit, 0) . ')',
                'risk_level' => 'high'
            ];
        }

        // Check daily limit
        $dailyTotal = Transaction::getDailySum($userId);
        if (($dailyTotal + $amount) > $this->dailyTransactionLimit) {
            return [
                'allowed' => false,
                'reason' => 'Günlük transfer limiti aşıldı (₺' . number_format($this->dailyTransactionLimit, 0) . ')',
                'risk_level' => 'high'
            ];
        }

        // Check transaction velocity (rate limiting)
        if (!$this->checkTransactionVelocity($userId)) {
            AuditLog::logSuspiciousActivity($userId, 'High transaction velocity detected');
            return [
                'allowed' => false,
                'reason' => 'Çok fazla işlem denemesi. Lütfen bekleyin.',
                'risk_level' => 'critical'
            ];
        }

        // Check for suspicious patterns
        $suspiciousCheck = $this->checkSuspiciousPatterns($userId, $amount);
        if (!$suspiciousCheck['allowed']) {
            return $suspiciousCheck;
        }

        return ['allowed' => true, 'risk_level' => 'low'];
    }

    /**
     * Check transaction velocity (rate limiting)
     */
    private function checkTransactionVelocity(string $userId): bool
    {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM transactions 
             WHERE source_user_id = ? 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$userId]
        );

        return (int) $count < $this->maxTransactionsPerMinute;
    }

    /**
     * Check for suspicious patterns
     */
    private function checkSuspiciousPatterns(string $userId, float $amount): array
    {
        // Pattern 1: Multiple small transactions to same recipient
        $recentSameRecipient = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM transactions 
             WHERE source_user_id = ? 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY destination_user_id
             HAVING COUNT(*) > 5",
            [$userId]
        );

        if ($recentSameRecipient && (int) $recentSameRecipient > 0) {
            AuditLog::logSuspiciousActivity($userId, 'Multiple transactions to same recipient');
            return [
                'allowed' => false,
                'reason' => 'Şüpheli işlem paterni tespit edildi',
                'risk_level' => 'high'
            ];
        }

        // Pattern 2: Large transaction after account creation
        $accountAge = $this->db->fetchColumn(
            "SELECT TIMESTAMPDIFF(DAY, created_at, NOW()) FROM users WHERE id = ?",
            [$userId]
        );

        if ((int) $accountAge < 7 && $amount > 10000) {
            return [
                'allowed' => false,
                'reason' => 'Yeni hesaplar için büyük transfer limiti',
                'risk_level' => 'medium'
            ];
        }

        // Pattern 3: Night time large transactions
        $hour = (int) date('H');
        if (($hour >= 0 && $hour < 6) && $amount > 25000) {
            // Allow but flag
            AuditLog::logSuspiciousActivity($userId, 'Large night-time transaction', [
                'amount' => $amount,
                'hour' => $hour
            ]);
        }

        return ['allowed' => true, 'risk_level' => 'low'];
    }

    /**
     * Check login attempt
     */
    public function checkLoginAttempt(string $email, string $ip): array
    {
        // Check failed attempts from this IP
        $ipAttempts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE ip_address = ? 
             AND is_successful = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$ip]
        );

        if ((int) $ipAttempts >= 10) {
            AuditLog::logSuspiciousActivity(null, 'IP blocked due to multiple failed logins', ['ip' => $ip]);
            return [
                'allowed' => false,
                'reason' => 'Bu IP adresinden çok fazla başarısız giriş denemesi yapıldı',
                'lockout_minutes' => 30
            ];
        }

        // Check failed attempts for this email
        $emailAttempts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE email = ? 
             AND is_successful = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$email]
        );

        if ((int) $emailAttempts >= $this->maxFailedLoginsBeforeLock) {
            return [
                'allowed' => false,
                'reason' => 'Hesap geçici olarak kilitlendi. 15 dakika sonra tekrar deneyin.',
                'lockout_minutes' => 15
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Log login attempt
     */
    public function logLoginAttempt(string $email, string $ip, bool $success, ?string $reason = null): void
    {
        $this->db->query(
            "INSERT INTO login_attempts (id, email, ip_address, user_agent, is_successful, failure_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                Database::generateUUID(),
                $email,
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $success ? 1 : 0,
                $reason
            ]
        );
    }

    /**
     * Check API rate limit
     */
    public function checkApiRateLimit(string $apiKey): array
    {
        $limit = (int) ($_ENV['API_RATE_LIMIT'] ?? 100);
        $window = (int) ($_ENV['API_RATE_WINDOW'] ?? 60);

        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs 
             WHERE metadata LIKE ? 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            ['%"api_key":"' . $apiKey . '"%', $window]
        );

        if ((int) $count >= $limit) {
            return [
                'allowed' => false,
                'reason' => 'Rate limit exceeded',
                'retry_after' => $window
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $limit - (int) $count,
            'limit' => $limit
        ];
    }

    /**
     * Get risk score for user
     */
    public function getUserRiskScore(string $userId): array
    {
        $score = 0;
        $factors = [];

        // Failed login attempts
        $failedLogins = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE email = (SELECT email FROM users WHERE id = ?) 
             AND is_successful = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId]
        );
        if ((int) $failedLogins > 0) {
            $score += min((int) $failedLogins * 5, 25);
            $factors[] = 'Başarısız giriş denemeleri: ' . $failedLogins;
        }

        // High value transactions
        $highValueTx = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM transactions 
             WHERE source_user_id = ? 
             AND amount > 25000 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId]
        );
        if ((int) $highValueTx > 3) {
            $score += 20;
            $factors[] = 'Yüksek değerli işlemler: ' . $highValueTx;
        }

        // Suspicious activity logs
        $suspiciousLogs = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs 
             WHERE user_id = ? 
             AND risk_level IN ('high', 'critical') 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$userId]
        );
        $score += (int) $suspiciousLogs * 10;
        if ((int) $suspiciousLogs > 0) {
            $factors[] = 'Şüpheli aktivite kayıtları: ' . $suspiciousLogs;
        }

        // Determine risk level
        $riskLevel = match(true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low'
        };

        return [
            'score' => min($score, 100),
            'level' => $riskLevel,
            'factors' => $factors
        ];
    }
}
