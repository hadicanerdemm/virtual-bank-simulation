<?php
/**
 * Transfer Page
 */

$pageTitle = 'Para Transferi';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionEngine;

$user = User::find($_SESSION['user_id']);
$wallets = $user->wallets();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceWalletId = $_POST['source_wallet'] ?? '';
    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $idempotencyKey = $_POST['idempotency_key'] ?? null;
    
    // Validate
    if (empty($sourceWalletId)) {
        $error = 'Kaynak cüzdan seçiniz.';
    } elseif (empty($recipientEmail)) {
        $error = 'Alıcı e-posta adresi gereklidir.';
    } elseif ($amount <= 0) {
        $error = 'Geçerli bir tutar giriniz.';
    } else {
        // Find recipient
        $recipient = User::findBy('email', $recipientEmail);
        
        if (!$recipient) {
            $error = 'Alıcı bulunamadı.';
        } elseif ($recipient->id === $user->id) {
            $error = 'Kendinize transfer yapamazsınız.';
        } else {
            // Find source wallet
            $sourceWallet = Wallet::find($sourceWalletId);
            
            if (!$sourceWallet || $sourceWallet->user_id !== $user->id) {
                $error = 'Geçersiz kaynak cüzdan.';
            } else {
                // Find recipient's wallet with same currency
                $recipientWallets = $recipient->wallets();
                $destWallet = null;
                foreach ($recipientWallets as $w) {
                    if ($w->currency === $sourceWallet->currency) {
                        $destWallet = $w;
                        break;
                    }
                }
                
                if (!$destWallet) {
                    $error = 'Alıcının bu para biriminde cüzdanı yok.';
                } else {
                    // Process transfer
                    $engine = new TransactionEngine();
                    $result = $engine->transfer(
                        $sourceWalletId,
                        $destWallet->id,
                        $amount,
                        $description ?: 'Para transferi',
                        $idempotencyKey
                    );
                    
                    if ($result['success']) {
                        $success = $result['message'] ?? 'Transfer başarılı!';
                        if (!empty($result['requires_approval'])) {
                            $success = 'Transfer admin onayına gönderildi.';
                        }
                        // Refresh wallets
                        $wallets = $user->wallets();
                    } else {
                        $error = $result['error'] ?? 'Transfer sırasında hata oluştu.';
                    }
                }
            }
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Para Transferi</h1>
    <p class="page-subtitle">Hızlı ve güvenli para gönderin</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
    <!-- Transfer Form -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">
            <i class="fas fa-paper-plane" style="color: var(--accent-primary);"></i>
            Transfer Bilgileri
        </h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="transferForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(16)) ?>">
            
            <div class="form-group">
                <label class="form-label">Kaynak Cüzdan</label>
                <select name="source_wallet" class="form-select" required id="sourceWallet">
                    <option value="">Cüzdan seçin...</option>
                    <?php foreach ($wallets as $wallet): ?>
                        <option value="<?= $wallet->id ?>" 
                            data-currency="<?= $wallet->currency ?>"
                            data-balance="<?= $wallet->available_balance ?>">
                            <?= $wallet->currency ?> - <?= $wallet->getFormattedBalance() ?> 
                            (Kullanılabilir: <?= $wallet->getCurrencySymbol() . number_format((float) $wallet->available_balance, 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Alıcı E-posta Adresi</label>
                <input 
                    type="email" 
                    name="recipient_email" 
                    class="form-input" 
                    placeholder="alici@email.com"
                    value="<?= htmlspecialchars($_POST['recipient_email'] ?? '') ?>"
                    required
                >
                <div class="form-hint">Alıcının TurkPay'e kayıtlı e-posta adresi</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tutar</label>
                <div style="position: relative;">
                    <input 
                        type="number" 
                        name="amount" 
                        class="form-input" 
                        placeholder="0.00"
                        step="0.01"
                        min="0.01"
                        max="50000"
                        id="amountInput"
                        value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                        required
                        style="padding-right: 60px;"
                    >
                    <span id="currencyLabel" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">TRY</span>
                </div>
                <div class="form-hint" id="balanceHint">Maksimum: -</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Açıklama (Opsiyonel)</label>
                <input 
                    type="text" 
                    name="description" 
                    class="form-input" 
                    placeholder="Transfer açıklaması..."
                    maxlength="255"
                    value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
                >
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-full">
                <i class="fas fa-paper-plane"></i>
                Para Gönder
            </button>
        </form>
    </div>
    
    <!-- Info Panel -->
    <div>
        <!-- Transfer Limits -->
        <div class="card mb-xl">
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">
                <i class="fas fa-info-circle" style="color: var(--accent-cyan);"></i>
                Transfer Limitleri
            </h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span>Tek seferde maksimum</span>
                    <span class="font-mono text-success">₺50.000</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span>Günlük limit</span>
                    <span class="font-mono text-success">₺200.000</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                    <span>Admin onayı gerektiren</span>
                    <span class="font-mono text-warning">&gt;₺25.000</span>
                </div>
            </div>
        </div>
        
        <!-- Security Info -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">
                <i class="fas fa-shield-halved" style="color: var(--success);"></i>
                Güvenlik
            </h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <div style="display: flex; align-items: flex-start; gap: var(--space-md);">
                    <i class="fas fa-check-circle" style="color: var(--success); margin-top: 3px;"></i>
                    <div>
                        <div style="font-weight: 500;">256-bit SSL Şifreleme</div>
                        <div style="font-size: 0.8125rem; color: var(--text-secondary);">Tüm işlemler şifrelenir</div>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: var(--space-md);">
                    <i class="fas fa-check-circle" style="color: var(--success); margin-top: 3px;"></i>
                    <div>
                        <div style="font-weight: 500;">Fraud Koruması</div>
                        <div style="font-size: 0.8125rem; color: var(--text-secondary);">Şüpheli işlemler engellenir</div>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: var(--space-md);">
                    <i class="fas fa-check-circle" style="color: var(--success); margin-top: 3px;"></i>
                    <div>
                        <div style="font-weight: 500;">Çift Giriş Muhasebe</div>
                        <div style="font-size: 0.8125rem; color: var(--text-secondary);">Tüm işlemler kayıt altında</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const sourceWallet = document.getElementById('sourceWallet');
    const currencyLabel = document.getElementById('currencyLabel');
    const balanceHint = document.getElementById('balanceHint');
    const amountInput = document.getElementById('amountInput');
    
    sourceWallet.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            const currency = selected.dataset.currency;
            const balance = parseFloat(selected.dataset.balance);
            currencyLabel.textContent = currency;
            const symbol = currency === 'TRY' ? '₺' : (currency === 'USD' ? '$' : '€');
            balanceHint.textContent = `Kullanılabilir bakiye: ${symbol}${balance.toLocaleString('tr-TR', {minimumFractionDigits: 2})}`;
            amountInput.max = balance;
        } else {
            currencyLabel.textContent = 'TRY';
            balanceHint.textContent = 'Maksimum: -';
        }
    });
</script>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
