<?php
/**
 * My Wallets Page
 */

$pageTitle = 'Cüzdanlarım';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Models\Wallet;
use App\Services\ExchangeRateService;

$user = User::find($_SESSION['user_id']);
$wallets = $user->wallets();

$exchangeService = new ExchangeRateService();
$totalBalanceTRY = 0;
foreach ($wallets as $wallet) {
    $amount = (float) $wallet->balance;
    if ($wallet->currency !== 'TRY') {
        $amount = $exchangeService->convert($amount, $wallet->currency, 'TRY');
    }
    $totalBalanceTRY += $amount;
}
?>

<div class="page-header">
    <h1 class="page-title">Cüzdanlarım</h1>
    <p class="page-subtitle">Tüm hesaplarınızı ve bakiyelerinizi buradan yönetebilirsiniz.</p>
</div>

<!-- Total Balance Card -->
<div class="card mb-xl">
    <div style="text-align: center; padding: var(--space-xl);">
        <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: var(--space-sm);">
            Toplam Varlık (TRY)
        </div>
        <div style="font-size: 2.5rem; font-weight: 700; background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            ₺<?= number_format($totalBalanceTRY, 2, ',', '.') ?>
        </div>
    </div>
</div>

<!-- Wallets Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-xl);">
    <?php foreach ($wallets as $wallet): ?>
        <?php 
        $cardClass = match($wallet->currency) {
            'TRY' => 'try',
            'USD' => 'usd',
            'EUR' => 'eur',
            default => ''
        };
        $flag = match($wallet->currency) {
            'TRY' => '🇹🇷',
            'USD' => '🇺🇸',
            'EUR' => '🇪🇺',
            default => '💰'
        };
        ?>
        <div class="card">
            <div class="wallet-card <?= $cardClass ?>" style="margin-bottom: var(--space-lg);">
                <div class="wallet-currency"><?= $flag ?> <?= $wallet->currency ?></div>
                <div class="wallet-balance"><?= $wallet->getFormattedBalance() ?></div>
                <div style="font-size: 0.875rem; opacity: 0.7;">
                    <?= $wallet->currency === 'TRY' ? 'Türk Lirası' : ($wallet->currency === 'USD' ? 'ABD Doları' : 'Euro') ?>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--space-md);">
                <a href="/banka/public/transfer?currency=<?= $wallet->currency ?>" class="btn btn-primary flex-1">
                    <i class="fas fa-paper-plane"></i> Gönder
                </a>
                <a href="/banka/public/exchange?from=<?= $wallet->currency ?>" class="btn btn-secondary flex-1">
                    <i class="fas fa-exchange-alt"></i> Çevir
                </a>
            </div>
            
            <?php if ((float) $wallet->pending_balance > 0): ?>
                <div style="margin-top: var(--space-md); padding: var(--space-sm); background: var(--warning-bg); border-radius: var(--radius-sm); font-size: 0.8125rem; color: var(--warning);">
                    <i class="fas fa-clock"></i>
                    Beklemede: <?= $wallet->getCurrencySymbol() . number_format((float) $wallet->pending_balance, 2) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
