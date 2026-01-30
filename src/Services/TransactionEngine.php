<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Job;

/**
 * Transaction Engine - Core banking logic
 * Handles ACID compliant transfers with double-entry bookkeeping
 */
class TransactionEngine
{
    private Database $db;
    private ExchangeRateService $exchangeService;
    private FraudDetectionService $fraudService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->exchangeService = new ExchangeRateService();
        $this->fraudService = new FraudDetectionService();
    }

    /**
     * Transfer money between wallets
     * ACID compliant with race condition protection
     */
    public function transfer(
        string $sourceWalletId,
        string $destinationWalletId,
        float $amount,
        string $description = '',
        ?string $idempotencyKey = null
    ): array {
        // Check idempotency
        if ($idempotencyKey !== null) {
            $existing = Transaction::findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return [
                    'success' => true,
                    'message' => 'Transaction already processed',
                    'transaction' => $existing,
                    'duplicate' => true
                ];
            }
        }

        // Validate amount
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Geçersiz tutar'];
        }

        // Load wallets
        $sourceWallet = Wallet::find($sourceWalletId);
        $destWallet = Wallet::find($destinationWalletId);

        if (!$sourceWallet || !$destWallet) {
            return ['success' => false, 'error' => 'Cüzdan bulunamadı'];
        }

        if ($sourceWallet->status !== 'active' || $destWallet->status !== 'active') {
            return ['success' => false, 'error' => 'Cüzdanlardan biri aktif değil'];
        }

        // Check source user
        $sourceUser = User::find($sourceWallet->user_id);
        if (!$sourceUser || $sourceUser->status !== 'active') {
            return ['success' => false, 'error' => 'Gönderici hesap aktif değil'];
        }

        // Fraud checks
        $fraudCheck = $this->fraudService->checkTransfer($sourceUser->id, $amount);
        if (!$fraudCheck['allowed']) {
            return ['success' => false, 'error' => $fraudCheck['reason']];
        }

        // Calculate exchange rate if currencies differ
        $exchangeRate = null;
        $convertedAmount = $amount;
        if ($sourceWallet->currency !== $destWallet->currency) {
            $exchangeRate = $this->exchangeService->getRate(
                $sourceWallet->currency,
                $destWallet->currency
            );
            $convertedAmount = round($amount * $exchangeRate, 2);
        }

        // Check if requires admin approval
        $requiresApproval = Transaction::requiresApproval($amount);

        // Begin database transaction
        $this->db->beginTransaction();

        try {
            // Lock source wallet row (race condition protection)
            $lockedSource = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$sourceWalletId]
            );

            if (!$lockedSource) {
                throw new \Exception('Kaynak cüzdan bulunamadı');
            }

            $sourceBalanceBefore = (float) $lockedSource['available_balance'];

            // Check sufficient balance
            if ($sourceBalanceBefore < $amount) {
                throw new \Exception('Yetersiz bakiye');
            }

            // Lock destination wallet row
            $lockedDest = $this->db->selectForUpdate(
                "SELECT * FROM wallets WHERE id = ?",
                [$destinationWalletId]
            );

            if (!$lockedDest) {
                throw new \Exception('Hedef cüzdan bulunamadı');
            }

            $destBalanceBefore = (float) $lockedDest['balance'];

            // Create transaction record
            $transaction = Transaction::create([
                'reference_id' => Transaction::generateReferenceId(),
                'idempotency_key' => $idempotencyKey,
                'type' => 'transfer',
                'status' => $requiresApproval ? 'requires_approval' : 'processing',
                'source_wallet_id' => $sourceWalletId,
                'destination_wallet_id' => $destinationWalletId,
                'source_user_id' => $sourceWallet->user_id,
                'destination_user_id' => $destWallet->user_id,
                'amount' => $amount,
                'fee' => 0,
                'currency' => $sourceWallet->currency,
                'exchange_rate' => $exchangeRate,
                'converted_amount' => $exchangeRate ? $convertedAmount : null,
                'converted_currency' => $exchangeRate ? $destWallet->currency : null,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // If requires approval, don't process yet
            if ($requiresApproval) {
                $this->db->commit();
                
                // Notify admin
                Job::dispatchNotification(
                    $this->getAdminId(),
                    'transaction',
                    'Onay Bekleyen İşlem',
                    "₺" . number_format($amount, 2) . " tutarında transfer onay bekliyor."
                );
                
                return [
                    'success' => true,
                    'message' => 'İşlem admin onayı bekliyor',
                    'transaction' => $transaction,
                    'requires_approval' => true
                ];
            }

            // Debit source wallet
            $sourceBalanceAfter = $sourceBalanceBefore - $amount;
            $this->db->query(
                "UPDATE wallets SET balance = balance - ?, available_balance = available_balance - ?, updated_at = NOW() WHERE id = ?",
                [$amount, $amount, $sourceWalletId]
            );

            // Credit destination wallet
            $destBalanceAfter = $destBalanceBefore + $convertedAmount;
            $this->db->query(
                "UPDATE wallets SET balance = balance + ?, available_balance = available_balance + ?, updated_at = NOW() WHERE id = ?",
                [$convertedAmount, $convertedAmount, $destinationWalletId]
            );

            // Create ledger entries (double-entry bookkeeping)
            $transaction->createLedgerEntries(
                $sourceWalletId,
                $sourceBalanceBefore,
                $sourceBalanceAfter,
                $destinationWalletId,
                $destBalanceBefore,
                $destBalanceAfter
            );

            // Mark transaction as completed
            $transaction->complete();

            $this->db->commit();

            // Audit log
            AuditLog::logTransaction($sourceWallet->user_id, $transaction->id, 'transfer', $amount);

            // Notify users
            $this->notifyTransfer($transaction, $sourceWallet, $destWallet, $convertedAmount);

            return [
                'success' => true,
                'message' => 'Transfer başarılı',
                'transaction' => $transaction
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();

            // Log failed transaction
            AuditLog::logSuspiciousActivity(
                $sourceWallet->user_id ?? null,
                'Transfer failed: ' . $e->getMessage(),
                ['source_wallet' => $sourceWalletId, 'amount' => $amount]
            );

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process approved transaction
     */
    public function processApproved(string $transactionId, string $adminId): array
    {
        $transaction = Transaction::find($transactionId);
        
        if (!$transaction) {
            return ['success' => false, 'error' => 'İşlem bulunamadı'];
        }
        
        if ($transaction->status !== 'requires_approval') {
            return ['success' => false, 'error' => 'İşlem onay bekliyor durumunda değil'];
        }

        // Approve and process
        $transaction->approve($adminId);

        // Execute the actual transfer
        return $this->transfer(
            $transaction->source_wallet_id,
            $transaction->destination_wallet_id,
            (float) $transaction->amount,
            $transaction->description,
            $transaction->idempotency_key . '_approved'
        );
    }

    /**
     * Reject pending transaction
     */
    public function rejectTransaction(string $transactionId, string $adminId, string $reason): array
    {
        $transaction = Transaction::find($transactionId);
        
        if (!$transaction) {
            return ['success' => false, 'error' => 'İşlem bulunamadı'];
        }
        
        $transaction->fail($reason);
        
        // Notify user
        Job::dispatchNotification(
            $transaction->source_user_id,
            'transaction',
            'İşlem Reddedildi',
            "Transfer işleminiz reddedildi: " . $reason
        );
        
        return ['success' => true, 'message' => 'İşlem reddedildi'];
    }

    /**
     * Deposit money to wallet (from system vault)
     */
    public function deposit(string $walletId, float $amount, string $description = 'Para yatırma'): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Geçersiz tutar'];
        }

        $wallet = Wallet::find($walletId);
        if (!$wallet) {
            return ['success' => false, 'error' => 'Cüzdan bulunamadı'];
        }

        $this->db->beginTransaction();

        try {
            $balanceBefore = (float) $wallet->balance;

            // Credit wallet
            $wallet->credit($amount);

            // Debit system vault
            $this->debitSystemVault($amount, $wallet->currency);

            // Create transaction
            $transaction = Transaction::create([
                'reference_id' => Transaction::generateReferenceId(),
                'type' => 'deposit',
                'status' => 'completed',
                'destination_wallet_id' => $walletId,
                'destination_user_id' => $wallet->user_id,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'description' => $description,
                'completed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Para yatırma başarılı',
                'transaction' => $transaction
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Currency exchange between wallets
     */
    public function exchange(
        string $sourceWalletId,
        string $destWalletId,
        float $amount
    ): array {
        $sourceWallet = Wallet::find($sourceWalletId);
        $destWallet = Wallet::find($destWalletId);

        if (!$sourceWallet || !$destWallet) {
            return ['success' => false, 'error' => 'Cüzdan bulunamadı'];
        }

        if ($sourceWallet->user_id !== $destWallet->user_id) {
            return ['success' => false, 'error' => 'Cüzdanlar aynı kullanıcıya ait değil'];
        }

        if ($sourceWallet->currency === $destWallet->currency) {
            return ['success' => false, 'error' => 'Aynı para birimi'];
        }

        $rate = $this->exchangeService->getRate($sourceWallet->currency, $destWallet->currency);
        $convertedAmount = round($amount * $rate, 2);

        return $this->transfer(
            $sourceWalletId,
            $destWalletId,
            $amount,
            sprintf(
                'Döviz çevirme: %s %s → %s %s',
                $sourceWallet->currency,
                number_format($amount, 2),
                $destWallet->currency,
                number_format($convertedAmount, 2)
            )
        );
    }

    /**
     * Debit system vault (internal accounting)
     */
    private function debitSystemVault(float $amount, string $currency): void
    {
        $this->db->query(
            "UPDATE system_vault SET balance = balance - ? WHERE type = 'main' AND currency = ?",
            [$amount, $currency]
        );
    }

    /**
     * Credit system vault (internal accounting)
     */
    private function creditSystemVault(float $amount, string $currency): void
    {
        $this->db->query(
            "UPDATE system_vault SET balance = balance + ? WHERE type = 'main' AND currency = ?",
            [$amount, $currency]
        );
    }

    /**
     * Get admin user ID
     */
    private function getAdminId(): string
    {
        $admin = $this->db->fetchOne("SELECT id FROM users WHERE role = 'super_admin' LIMIT 1");
        return $admin['id'] ?? '';
    }

    /**
     * Notify users about transfer
     */
    private function notifyTransfer(
        Transaction $transaction,
        Wallet $sourceWallet,
        Wallet $destWallet,
        float $convertedAmount
    ): void {
        // Notify sender
        Job::dispatchNotification(
            $sourceWallet->user_id,
            'transaction',
            'Para Gönderildi',
            sprintf(
                '%s tutarında para transferi gerçekleşti. Referans: %s',
                $sourceWallet->getCurrencySymbol() . number_format((float) $transaction->amount, 2),
                $transaction->reference_id
            )
        );

        // Notify receiver
        Job::dispatchNotification(
            $destWallet->user_id,
            'transaction',
            'Para Alındı',
            sprintf(
                '%s tutarında para transferi alındı. Referans: %s',
                $destWallet->getCurrencySymbol() . number_format($convertedAmount, 2),
                $transaction->reference_id
            )
        );
    }
}
