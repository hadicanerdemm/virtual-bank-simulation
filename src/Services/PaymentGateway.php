<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\VirtualCard;
use App\Models\Job;
use App\Models\AuditLog;

/**
 * Payment Gateway Service - Iyzico-like payment processing
 */
class PaymentGateway
{
    private Database $db;
    private TransactionEngine $transactionEngine;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->transactionEngine = new TransactionEngine();
    }

    /**
     * Initialize a payment session
     */
    public function initiate(
        Merchant $merchant,
        float $amount,
        string $currency,
        string $orderId,
        string $returnUrl,
        ?string $cancelUrl = null,
        ?string $callbackUrl = null,
        ?array $customerInfo = null
    ): array {
        // Validate merchant
        if ($merchant->status !== 'active') {
            return ['success' => false, 'error' => 'Merchant is not active'];
        }

        // Check limits
        if (!$merchant->isWithinDailyLimit($amount)) {
            return ['success' => false, 'error' => 'Daily limit exceeded'];
        }

        if (!$merchant->isWithinMonthlyLimit($amount)) {
            return ['success' => false, 'error' => 'Monthly limit exceeded'];
        }

        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Create payment session
        $sessionId = Database::generateUUID();
        $this->db->query(
            "INSERT INTO payment_sessions (id, merchant_id, session_token, amount, currency, order_id, customer_email, customer_name, return_url, cancel_url, callback_url, status, expires_at, ip_address, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'created', ?, ?, NOW(), NOW())",
            [
                $sessionId,
                $merchant->id,
                $sessionToken,
                $amount,
                $currency,
                $orderId,
                $customerInfo['email'] ?? null,
                $customerInfo['name'] ?? null,
                $returnUrl,
                $cancelUrl,
                $callbackUrl ?? $merchant->webhook_url,
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]
        );

        // Log API access
        AuditLog::logApiAccess($merchant->id, '/api/v1/payments/initiate', 'POST');

        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost/banka/public';

        return [
            'success' => true,
            'data' => [
                'session_id' => $sessionId,
                'session_token' => $sessionToken,
                'checkout_url' => $baseUrl . '/checkout/' . $sessionToken,
                'expires_at' => $expiresAt,
                'amount' => $amount,
                'currency' => $currency
            ]
        ];
    }

    /**
     * Get payment session
     */
    public function getSession(string $sessionToken): ?array
    {
        return $this->db->fetchOne(
            "SELECT ps.*, m.business_name as merchant_name, m.logo as merchant_logo 
             FROM payment_sessions ps 
             JOIN merchants m ON ps.merchant_id = m.id 
             WHERE ps.session_token = ? AND ps.expires_at > NOW()",
            [$sessionToken]
        );
    }

    /**
     * Process card payment (with 3D Secure simulation)
     */
    public function processCardPayment(
        string $sessionToken,
        string $cardNumber,
        string $cardHolder,
        string $expiryMonth,
        string $expiryYear,
        string $cvv
    ): array {
        // Get session
        $session = $this->getSession($sessionToken);
        
        if (!$session) {
            return ['success' => false, 'error' => 'Invalid or expired session'];
        }

        if ($session['status'] !== 'created') {
            return ['success' => false, 'error' => 'Session already processed'];
        }

        // Validate card with Luhn
        if (!VirtualCard::validateLuhn($cardNumber)) {
            return ['success' => false, 'error' => 'Invalid card number'];
        }

        // Check if it's our virtual card
        $virtualCard = VirtualCard::findBy('card_number', $cardNumber);
        
        if ($virtualCard) {
            // Verify CVV
            if (!$virtualCard->verifyCVV($cvv)) {
                return ['success' => false, 'error' => 'Invalid CVV'];
            }

            // Check if card can make payment
            $canPay = $virtualCard->canMakePayment((float) $session['amount']);
            if (!$canPay['allowed']) {
                return ['success' => false, 'error' => $canPay['reason']];
            }

            // Check expiry
            if ($virtualCard->expiry_month !== $expiryMonth || $virtualCard->expiry_year !== $expiryYear) {
                return ['success' => false, 'error' => 'Invalid expiry date'];
            }
        }

        // Generate OTP for 3D Secure
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Update session
        $this->db->query(
            "UPDATE payment_sessions SET status = 'pending_3d', card_last_four = ?, card_type = ?, otp_code = ?, otp_expires_at = ?, updated_at = NOW() WHERE session_token = ?",
            [
                substr($cardNumber, -4),
                str_starts_with($cardNumber, '4') ? 'visa' : 'mastercard',
                $otpCode,
                $otpExpiry,
                $sessionToken
            ]
        );

        // In a real system, send OTP via SMS. Here we return it for demo
        return [
            'success' => true,
            'requires_3d' => true,
            'data' => [
                'session_token' => $sessionToken,
                'otp_demo' => $otpCode, // For demo only - in production, send via SMS
                'message' => 'Telefonunuza gönderilen 6 haneli kodu girin'
            ]
        ];
    }

    /**
     * Verify 3D OTP and complete payment
     */
    public function verify3DSecure(string $sessionToken, string $otpCode): array
    {
        $session = $this->db->fetchOne(
            "SELECT * FROM payment_sessions WHERE session_token = ?",
            [$sessionToken]
        );

        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }

        if ($session['status'] !== 'pending_3d') {
            return ['success' => false, 'error' => 'Invalid session status'];
        }

        // Check OTP expiry
        if (strtotime($session['otp_expires_at']) < time()) {
            return ['success' => false, 'error' => 'OTP expired'];
        }

        // Check attempts
        if ((int) $session['otp_attempts'] >= 3) {
            $this->db->query(
                "UPDATE payment_sessions SET status = 'failed', updated_at = NOW() WHERE session_token = ?",
                [$sessionToken]
            );
            return ['success' => false, 'error' => 'Too many attempts'];
        }

        // Verify OTP
        if ($session['otp_code'] !== $otpCode) {
            $this->db->query(
                "UPDATE payment_sessions SET otp_attempts = otp_attempts + 1, updated_at = NOW() WHERE session_token = ?",
                [$sessionToken]
            );
            return ['success' => false, 'error' => 'Invalid OTP'];
        }

        // Find virtual card
        $virtualCard = VirtualCard::findBy('card_number', 
            $this->db->fetchColumn(
                "SELECT card_last_four FROM payment_sessions WHERE session_token = ?",
                [$sessionToken]
            ) ? '%' . $session['card_last_four'] : null
        );

        // Actually we need to find by last 4 digits
        $cards = $this->db->fetchAll(
            "SELECT * FROM virtual_cards WHERE card_number LIKE ?",
            ['%' . $session['card_last_four']]
        );

        if (empty($cards)) {
            // External card - simulate success
            return $this->completeExternalPayment($session);
        }

        $virtualCard = new VirtualCard($cards[0]);
        $wallet = Wallet::find($virtualCard->wallet_id);
        $merchant = Merchant::find($session['merchant_id']);

        if (!$wallet || !$merchant) {
            return ['success' => false, 'error' => 'Wallet or merchant not found'];
        }

        // Get merchant's default wallet
        $merchantUser = $merchant->user();
        $merchantWallets = $merchantUser->wallets();
        $merchantWallet = null;
        foreach ($merchantWallets as $mw) {
            if ($mw->currency === $session['currency']) {
                $merchantWallet = $mw;
                break;
            }
        }

        if (!$merchantWallet) {
            return ['success' => false, 'error' => 'Merchant wallet not found'];
        }

        // Calculate commission
        $commission = $merchant->calculateCommission((float) $session['amount']);
        $netAmount = (float) $session['amount'] - $commission;

        // Process transfer
        $this->db->beginTransaction();

        try {
            // Debit customer wallet
            $wallet->debit((float) $session['amount']);

            // Credit merchant wallet (minus commission)
            $merchantWallet->credit($netAmount);

            // Create transaction
            $transaction = Transaction::create([
                'reference_id' => Transaction::generateReferenceId(),
                'type' => 'payment',
                'status' => 'completed',
                'source_wallet_id' => $wallet->id,
                'destination_wallet_id' => $merchantWallet->id,
                'source_user_id' => $wallet->user_id,
                'destination_user_id' => $merchantUser->id,
                'merchant_id' => $merchant->id,
                'amount' => $session['amount'],
                'fee' => $commission,
                'currency' => $session['currency'],
                'description' => 'Online ödeme - ' . $merchant->business_name,
                'metadata' => json_encode(['order_id' => $session['order_id']]),
                'completed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

            // Update payment session
            $this->db->query(
                "UPDATE payment_sessions SET status = 'completed', transaction_id = ?, completed_at = NOW(), updated_at = NOW() WHERE session_token = ?",
                [$transaction->id, $sessionToken]
            );

            // Credit commission to fee vault
            $this->db->query(
                "UPDATE system_vault SET balance = balance + ? WHERE type = 'fee' AND currency = ?",
                [$commission, $session['currency']]
            );

            $this->db->commit();

            // Dispatch webhook
            if ($session['callback_url']) {
                Job::dispatchWebhook(
                    $session['callback_url'],
                    'payment.completed',
                    [
                        'session_id' => $session['id'],
                        'transaction_id' => $transaction->id,
                        'reference_id' => $transaction->reference_id,
                        'order_id' => $session['order_id'],
                        'amount' => $session['amount'],
                        'currency' => $session['currency'],
                        'status' => 'completed'
                    ],
                    $merchant->webhook_secret ?? ''
                );
            }

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'reference_id' => $transaction->reference_id,
                    'amount' => $session['amount'],
                    'currency' => $session['currency'],
                    'status' => 'completed',
                    'return_url' => $session['return_url'] . '?status=success&ref=' . $transaction->reference_id
                ]
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            
            $this->db->query(
                "UPDATE payment_sessions SET status = 'failed', updated_at = NOW() WHERE session_token = ?",
                [$sessionToken]
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Complete external payment (simulated for non-TurkPay cards)
     */
    private function completeExternalPayment(array $session): array
    {
        $merchant = Merchant::find($session['merchant_id']);
        $merchantUser = $merchant->user();
        $merchantWallets = $merchantUser->wallets();
        
        $merchantWallet = null;
        foreach ($merchantWallets as $mw) {
            if ($mw->currency === $session['currency']) {
                $merchantWallet = $mw;
                break;
            }
        }

        if (!$merchantWallet) {
            return ['success' => false, 'error' => 'Merchant wallet not found'];
        }

        $commission = $merchant->calculateCommission((float) $session['amount']);
        $netAmount = (float) $session['amount'] - $commission;

        // Credit merchant wallet from system vault
        $this->db->beginTransaction();

        try {
            // Debit system vault
            $this->db->query(
                "UPDATE system_vault SET balance = balance - ? WHERE type = 'main' AND currency = ?",
                [$session['amount'], $session['currency']]
            );

            // Credit merchant
            $merchantWallet->credit($netAmount);

            // Create transaction
            $transaction = Transaction::create([
                'reference_id' => Transaction::generateReferenceId(),
                'type' => 'payment',
                'status' => 'completed',
                'destination_wallet_id' => $merchantWallet->id,
                'destination_user_id' => $merchantUser->id,
                'merchant_id' => $merchant->id,
                'amount' => $session['amount'],
                'fee' => $commission,
                'currency' => $session['currency'],
                'description' => 'Online ödeme (harici kart) - ' . $merchant->business_name,
                'metadata' => json_encode([
                    'order_id' => $session['order_id'],
                    'card_last_four' => $session['card_last_four']
                ]),
                'completed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

            // Update session
            $this->db->query(
                "UPDATE payment_sessions SET status = 'completed', transaction_id = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?",
                [$transaction->id, $session['id']]
            );

            $this->db->commit();

            // Dispatch webhook
            if ($session['callback_url']) {
                Job::dispatchWebhook(
                    $session['callback_url'],
                    'payment.completed',
                    [
                        'session_id' => $session['id'],
                        'transaction_id' => $transaction->id,
                        'reference_id' => $transaction->reference_id,
                        'order_id' => $session['order_id'],
                        'amount' => $session['amount'],
                        'currency' => $session['currency'],
                        'status' => 'completed'
                    ],
                    $merchant->webhook_secret ?? ''
                );
            }

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'reference_id' => $transaction->reference_id,
                    'amount' => $session['amount'],
                    'currency' => $session['currency'],
                    'status' => 'completed',
                    'return_url' => $session['return_url'] . '?status=success&ref=' . $transaction->reference_id
                ]
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get payment status
     */
    public function getStatus(string $sessionToken): array
    {
        $session = $this->db->fetchOne(
            "SELECT ps.*, t.reference_id as transaction_reference 
             FROM payment_sessions ps 
             LEFT JOIN transactions t ON ps.transaction_id = t.id 
             WHERE ps.session_token = ?",
            [$sessionToken]
        );

        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }

        return [
            'success' => true,
            'data' => [
                'status' => $session['status'],
                'amount' => $session['amount'],
                'currency' => $session['currency'],
                'order_id' => $session['order_id'],
                'transaction_reference' => $session['transaction_reference'],
                'completed_at' => $session['completed_at']
            ]
        ];
    }

    /**
     * Refund payment
     */
    public function refund(string $transactionId, ?float $amount = null, string $reason = ''): array
    {
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }

        if ($transaction->type !== 'payment' || $transaction->status !== 'completed') {
            return ['success' => false, 'error' => 'Cannot refund this transaction'];
        }

        $refundAmount = $amount ?? (float) $transaction->amount;

        if ($refundAmount > (float) $transaction->amount) {
            return ['success' => false, 'error' => 'Refund amount exceeds original'];
        }

        // Create refund transaction
        $this->db->beginTransaction();

        try {
            $sourceWallet = Wallet::find($transaction->destination_wallet_id);
            $destWallet = Wallet::find($transaction->source_wallet_id);

            if (!$sourceWallet || !$destWallet) {
                throw new \Exception('Wallets not found');
            }

            // Debit merchant, credit customer
            $sourceWallet->debit($refundAmount);
            $destWallet->credit($refundAmount);

            $refundTx = Transaction::create([
                'reference_id' => Transaction::generateReferenceId(),
                'type' => 'refund',
                'status' => 'completed',
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $destWallet->id,
                'source_user_id' => $sourceWallet->user_id,
                'destination_user_id' => $destWallet->user_id,
                'merchant_id' => $transaction->merchant_id,
                'amount' => $refundAmount,
                'currency' => $transaction->currency,
                'description' => 'İade: ' . $reason,
                'metadata' => json_encode(['original_transaction' => $transaction->id]),
                'completed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

            $this->db->commit();

            // Dispatch webhook
            $merchant = Merchant::find($transaction->merchant_id);
            if ($merchant && $merchant->webhook_url) {
                Job::dispatchWebhook(
                    $merchant->webhook_url,
                    'payment.refunded',
                    [
                        'original_transaction_id' => $transaction->id,
                        'refund_transaction_id' => $refundTx->id,
                        'amount' => $refundAmount,
                        'currency' => $transaction->currency
                    ],
                    $merchant->webhook_secret ?? ''
                );
            }

            return [
                'success' => true,
                'data' => [
                    'refund_id' => $refundTx->id,
                    'reference_id' => $refundTx->reference_id,
                    'amount' => $refundAmount
                ]
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
