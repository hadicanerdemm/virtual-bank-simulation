<?php
/**
 * Merchant API Keys Page
 */

$pageTitle = 'API Anahtarları';
include dirname(dirname(__DIR__)) . '/views/layouts/header.php';

use App\Models\User;
use App\Models\Merchant;

$user = User::find($_SESSION['user_id']);
$merchants = Merchant::where('user_id', $user->id);
$merchant = $merchants[0] ?? null;

if (!$merchant) {
    header('Location: /banka/public/merchant/register');
    exit;
}
?>

<div class="page-header">
    <h1 class="page-title">API Anahtarları</h1>
    <p class="page-subtitle">TurkPay API'sine erişim için gerekli kimlik bilgileri.</p>
</div>

<div class="card">
    <div class="alert alert-warning mb-lg">
        <i class="fas fa-exclamation-triangle"></i>
        <span><strong>Dikkat:</strong> API Secret'ınızı asla paylaşmayın ve front-end kodlarında kullanmayın!</span>
    </div>
    
    <div class="form-group">
        <label class="form-label">API Key (Public)</label>
        <div style="display: flex; gap: var(--space-md);">
            <input type="text" class="form-input" value="<?= htmlspecialchars($merchant->api_key) ?>" readonly id="apiKey">
            <button class="btn btn-secondary" onclick="copyToClipboard('apiKey')">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        <div class="form-hint">Bu anahtarı ödeme formlarında kullanabilirsiniz.</div>
    </div>
    
    <div class="form-group">
        <label class="form-label">API Secret (Private)</label>
        <div style="display: flex; gap: var(--space-md);">
            <input type="password" class="form-input" value="<?= htmlspecialchars($merchant->api_secret) ?>" readonly id="apiSecret">
            <button class="btn btn-secondary" onclick="toggleSecret()">
                <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
            <button class="btn btn-secondary" onclick="copyToClipboard('apiSecret')">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        <div class="form-hint">Bu anahtarı sadece sunucu tarafında kullanın.</div>
    </div>
    
    <hr style="margin: var(--space-xl) 0; border-color: var(--border-light);">
    
    <h3>Webhook Secret</h3>
    <div class="form-group">
        <div style="display: flex; gap: var(--space-md);">
            <input type="password" class="form-input" value="<?= htmlspecialchars($merchant->webhook_secret ?? '') ?>" readonly id="webhookSecret">
            <button class="btn btn-secondary" onclick="toggleWebhookSecret()">
                <i class="fas fa-eye" id="webhookEyeIcon"></i>
            </button>
        </div>
        <div class="form-hint">Webhook imzalarını doğrulamak için kullanın.</div>
    </div>
    
    <hr style="margin: var(--space-xl) 0; border-color: var(--border-light);">
    
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3>Anahtarları Yenile</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Tüm API anahtarlarını yenilemek için kullanın. Eski anahtarlar çalışmaz hale gelir.</p>
        </div>
        <form method="POST" action="/banka/public/merchant/api-keys/regenerate" onsubmit="return confirm('Tüm anahtarlar yenilenecek. Devam etmek istiyor musunuz?');">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-sync"></i> Anahtarları Yenile
            </button>
        </form>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    const originalType = input.type;
    input.type = 'text';
    input.select();
    document.execCommand('copy');
    input.type = originalType;
    alert('Kopyalandı!');
}

function toggleSecret() {
    const input = document.getElementById('apiSecret');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function toggleWebhookSecret() {
    const input = document.getElementById('webhookSecret');
    const icon = document.getElementById('webhookEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
