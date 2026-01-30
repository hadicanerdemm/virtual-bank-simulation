<?php
/**
 * Currency Exchange Page
 */

$pageTitle = 'Döviz Çevir';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Services\ExchangeRateService;

$user = User::find($_SESSION['user_id']);
$wallets = $user->wallets();
$exchangeService = new ExchangeRateService();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromCurrency = $_POST['from_currency'] ?? '';
    $toCurrency = $_POST['to_currency'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    
    if ($fromCurrency === $toCurrency) {
        $error = 'Aynı para birimini seçemezsiniz.';
    } elseif ($amount <= 0) {
        $error = 'Geçerli bir miktar girin.';
    } else {
        // Find wallets
        $sourceWallet = null;
        $destinationWallet = null;
        foreach ($wallets as $w) {
            if ($w->currency === $fromCurrency) $sourceWallet = $w;
            if ($w->currency === $toCurrency) $destinationWallet = $w;
        }
        
        if (!$sourceWallet || (float)$sourceWallet->available_balance < $amount) {
            $error = 'Yetersiz bakiye.';
        } elseif (!$destinationWallet) {
            $error = 'Hedef cüzdan bulunamadı.';
        } else {
            $convertedAmount = $exchangeService->convert($amount, $fromCurrency, $toCurrency);
            $rate = $exchangeService->getRate($fromCurrency, $toCurrency);
            
            try {
                $sourceWallet->debit($amount, "Döviz çevirme: {$fromCurrency} -> {$toCurrency}");
                $destinationWallet->credit($convertedAmount, "Döviz çevirme: {$fromCurrency} -> {$toCurrency}");
                
                $success = "Başarılı! {$sourceWallet->getCurrencySymbol()}" . number_format($amount, 2) . 
                          " → {$destinationWallet->getCurrencySymbol()}" . number_format($convertedAmount, 2) .
                          " (Kur: " . number_format($rate, 4) . ")";
                
                // Refresh wallets
                $wallets = $user->wallets();
            } catch (\Exception $e) {
                $error = 'İşlem başarısız: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Döviz Çevir</h1>
    <p class="page-subtitle">Cüzdanlarınız arasında anlık kurlarla döviz çevirebilirsiniz.</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
    <!-- Exchange Form -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">Döviz Çevirme</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger mb-lg">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success mb-lg">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/banka/public/exchange">
            <div class="form-group">
                <label class="form-label">Kaynak Para Birimi</label>
                <select name="from_currency" class="form-input" required>
                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?= $wallet->currency ?>" <?= ($_GET['from'] ?? '') === $wallet->currency ? 'selected' : '' ?>>
                            <?= $wallet->currency ?> - <?= $wallet->getFormattedBalance() ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Miktar</label>
                <input type="number" name="amount" class="form-input" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Hedef Para Birimi</label>
                <select name="to_currency" class="form-input" required>
                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?= $wallet->currency ?>">
                            <?= $wallet->currency ?> - <?= $wallet->getFormattedBalance() ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-full">
                <i class="fas fa-exchange-alt"></i> Çevir
            </button>
        </form>
    </div>
    
    <!-- Exchange Rates -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">Güncel Kurlar</h2>
        
        <div style="display: flex; flex-direction: column; gap: var(--space-md);">
            <div style="display: flex; justify-content: space-between; padding: var(--space-lg); background: var(--bg-glass); border-radius: var(--radius-md);">
                <span>🇺🇸 1 USD =</span>
                <span class="font-mono" style="color: var(--success); font-weight: 600;">₺<?= number_format($exchangeService->getRate('USD', 'TRY'), 4) ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; padding: var(--space-lg); background: var(--bg-glass); border-radius: var(--radius-md);">
                <span>🇪🇺 1 EUR =</span>
                <span class="font-mono" style="color: var(--success); font-weight: 600;">₺<?= number_format($exchangeService->getRate('EUR', 'TRY'), 4) ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; padding: var(--space-lg); background: var(--bg-glass); border-radius: var(--radius-md);">
                <span>🇪🇺 1 EUR =</span>
                <span class="font-mono" style="color: var(--success); font-weight: 600;">$<?= number_format($exchangeService->getRate('EUR', 'USD'), 4) ?></span>
            </div>
        </div>
        
        <p style="margin-top: var(--space-lg); font-size: 0.8125rem; color: var(--text-muted);">
            <i class="fas fa-info-circle"></i>
            Kurlar anlık olarak güncellenmektedir. İşlem sırasında değişiklik gösterebilir.
        </p>
    </div>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
