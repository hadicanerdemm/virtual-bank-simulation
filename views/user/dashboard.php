<?php
/**
 * User Dashboard
 */

$pageTitle = 'Ana Sayfa';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\VirtualCard;
use App\Services\ExchangeRateService;

$user = User::find($_SESSION['user_id']);
$wallets = $user->wallets();
$cards = $user->virtualCards();
$recentTransactions = $user->transactions(10);

// Calculate total balance in TRY
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
    <h1 class="page-title">Hoş Geldiniz, <?= htmlspecialchars($user->first_name) ?>! 👋</h1>
    <p class="page-subtitle">Hesabınızın genel durumunu buradan takip edebilirsiniz.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">₺<?= number_format($totalBalanceTRY, 2, ',', '.') ?></div>
            <div class="stat-label">Toplam Varlık (TRY)</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-credit-card"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count($cards) ?></div>
            <div class="stat-label">Sanal Kart</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count($recentTransactions) ?></div>
            <div class="stat-label">Son İşlemler</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-coins"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count($wallets) ?></div>
            <div class="stat-label">Aktif Cüzdan</div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
    <!-- Left Column -->
    <div>
        <!-- Wallets Section -->
        <div class="card mb-xl">
            <div class="card-header">
                <h2 class="card-title">Cüzdanlarım</h2>
                <a href="/banka/public/wallets" class="btn btn-ghost btn-sm">
                    Tümünü Gör <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg);">
                <?php foreach ($wallets as $wallet): ?>
                    <?php 
                    $cardClass = match($wallet->currency) {
                        'TRY' => 'try',
                        'USD' => 'usd',
                        'EUR' => 'eur',
                        default => ''
                    };
                    ?>
                    <div class="wallet-card <?= $cardClass ?>" data-wallet-id="<?= $wallet->id ?>">
                        <div class="wallet-currency"><?= $wallet->currency ?></div>
                        <div class="wallet-balance"><?= $wallet->getFormattedBalance() ?></div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.875rem; opacity: 0.7;">
                                <?= $wallet->currency === 'TRY' ? 'Türk Lirası' : ($wallet->currency === 'USD' ? 'ABD Doları' : 'Euro') ?>
                            </span>
                            <?php if ((float) $wallet->hold_balance > 0): ?>
                                <span class="badge badge-warning">
                                    Bloke: <?= $wallet->getCurrencySymbol() . number_format((float) $wallet->hold_balance, 2) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Son İşlemler</h2>
                <a href="/banka/public/transactions" class="btn btn-ghost btn-sm">
                    Tümünü Gör <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($recentTransactions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💳</div>
                    <h3 class="empty-state-title">Henüz işlem yok</h3>
                    <p class="empty-state-text">İlk transferinizi yapın veya sanal kart oluşturun.</p>
                    <a href="/banka/public/transfer" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Transfer Yap
                    </a>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($recentTransactions as $tx): ?>
                        <?php
                        $isIncoming = $tx->destination_user_id === $user->id;
                        $iconClass = $isIncoming ? 'incoming' : 'outgoing';
                        $amountClass = $isIncoming ? 'incoming' : 'outgoing';
                        $amountSign = $isIncoming ? '+' : '-';
                        ?>
                        <div class="transaction-item">
                            <div class="transaction-icon <?= $iconClass ?>">
                                <i class="fas fa-<?= $isIncoming ? 'arrow-down' : 'arrow-up' ?>"></i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-title"><?= htmlspecialchars($tx->description ?: $tx->getTypeLabel()) ?></div>
                                <div class="transaction-meta">
                                    <?= date('d.m.Y H:i', strtotime($tx->created_at)) ?> 
                                    <span class="badge <?= $tx->getStatusBadge() ?>" style="margin-left: 5px;">
                                        <?= $tx->getStatusLabel() ?>
                                    </span>
                                </div>
                            </div>
                            <div class="transaction-amount <?= $amountClass ?>">
                                <?= $amountSign . $tx->getFormattedAmount() ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Right Column -->
    <div>
        <!-- Quick Actions -->
        <div class="card mb-xl">
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">Hızlı İşlemler</h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <a href="/banka/public/transfer" class="btn btn-primary w-full">
                    <i class="fas fa-paper-plane"></i>
                    Para Gönder
                </a>
                <a href="/banka/public/cards" class="btn btn-secondary w-full">
                    <i class="fas fa-plus"></i>
                    Yeni Kart Oluştur
                </a>
                <a href="/banka/public/exchange" class="btn btn-secondary w-full">
                    <i class="fas fa-exchange-alt"></i>
                    Döviz Çevir
                </a>
            </div>
        </div>
        
        <!-- Exchange Rates -->
        <div class="card mb-xl">
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">Döviz Kurları</h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span style="display: flex; align-items: center; gap: var(--space-sm);">
                        <span style="font-size: 1.25rem;">🇺🇸</span>
                        USD/TRY
                    </span>
                    <span class="font-mono" style="color: var(--success);">
                        <?= number_format($exchangeService->getRate('USD', 'TRY'), 4) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span style="display: flex; align-items: center; gap: var(--space-sm);">
                        <span style="font-size: 1.25rem;">🇪🇺</span>
                        EUR/TRY
                    </span>
                    <span class="font-mono" style="color: var(--success);">
                        <?= number_format($exchangeService->getRate('EUR', 'TRY'), 4) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span style="display: flex; align-items: center; gap: var(--space-sm);">
                        <span style="font-size: 1.25rem;">💱</span>
                        EUR/USD
                    </span>
                    <span class="font-mono" style="color: var(--success);">
                        <?= number_format($exchangeService->getRate('EUR', 'USD'), 4) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Virtual Card Preview -->
        <?php if (!empty($cards)): ?>
            <?php $primaryCard = $cards[0]; ?>
            <div class="card">
                <h3 class="card-title" style="margin-bottom: var(--space-lg);">Ana Kartım</h3>
                <div class="virtual-card <?= str_starts_with($primaryCard->card_number, '4') ? 'visa' : 'mastercard' ?>" style="width: 100%; height: auto; aspect-ratio: 1.6;">
                    <div class="card-chip"></div>
                    <div class="card-number" style="font-size: 1.1rem;"><?= $primaryCard->getFormattedNumber() ?></div>
                    <div class="card-info">
                        <div>
                            <div class="card-holder">Kart Sahibi</div>
                            <div class="card-holder-name"><?= strtoupper($user->getFullName()) ?></div>
                        </div>
                        <div>
                            <div class="card-expiry-label">Valid Thru</div>
                            <div class="card-expiry"><?= $primaryCard->expiry_month ?>/<?= $primaryCard->expiry_year ?></div>
                        </div>
                    </div>
                    <div class="card-type-logo"><?= str_starts_with($primaryCard->card_number, '4') ? 'VISA' : 'MC' ?></div>
                </div>
                <a href="/banka/public/cards" class="btn btn-ghost w-full mt-md">
                    Tüm Kartları Gör <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Real-time Balance Updates -->
<script>
(function() {
    let lastBalances = {};
    
    // Poll for balance updates every 3 seconds
    function pollBalances() {
        fetch('/banka/public/api/v1/user/balance')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.data.forEach(wallet => {
                        const element = document.querySelector(`[data-wallet-id="${wallet.id}"]`);
                        if (element) {
                            const oldBalance = lastBalances[wallet.id] || wallet.balance;
                            const balanceEl = element.querySelector('.wallet-balance');
                            
                            if (oldBalance !== wallet.balance && balanceEl) {
                                // Animate balance change
                                balanceEl.style.transition = 'all 0.3s ease';
                                balanceEl.style.transform = 'scale(1.05)';
                                balanceEl.style.color = wallet.balance > oldBalance ? '#10b981' : '#ef4444';
                                
                                balanceEl.textContent = wallet.formatted;
                                
                                setTimeout(() => {
                                    balanceEl.style.transform = 'scale(1)';
                                    balanceEl.style.color = '';
                                }, 500);
                                
                                // Show toast notification
                                const diff = wallet.balance - oldBalance;
                                const sign = diff > 0 ? '+' : '';
                                showToast(`${wallet.currency}: ${sign}${diff.toFixed(2)}`, diff > 0 ? 'success' : 'warning');
                            }
                            lastBalances[wallet.id] = wallet.balance;
                        }
                    });
                }
            })
            .catch(err => console.log('Balance poll error:', err));
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'arrow-up' : 'arrow-down'}"></i> ${message}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? 'var(--success)' : 'var(--warning)'};
            color: white;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Initial load + polling
    pollBalances();
    setInterval(pollBalances, 3000);
})();
</script>

<style>
@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
