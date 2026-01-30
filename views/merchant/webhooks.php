<?php
/**
 * Merchant Webhooks
 */

$pageTitle = 'Webhook Yönetimi';
include dirname(dirname(__DIR__)) . '/views/layouts/header.php';

use App\Models\User;
use App\Models\Merchant;
use App\Config\Database;

$user = User::find($_SESSION['user_id']);
$merchants = Merchant::where('user_id', $user->id);
$merchant = $merchants[0] ?? null;

if (!$merchant) {
    header('Location: /banka/public/merchant/register');
    exit;
}

$db = Database::getInstance();

// Handle webhook URL update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $webhookUrl = $_POST['webhook_url'] ?? '';
    
    if ($webhookUrl && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        $db->update('merchants', ['webhook_url' => $webhookUrl], 'id = ?', [$merchant->id]);
        $message = 'success';
        $merchant->webhook_url = $webhookUrl;
    } else {
        $message = 'error';
    }
}

// Get webhook logs
$webhookLogs = $db->fetchAll(
    "SELECT * FROM jobs WHERE type = 'webhook' AND payload LIKE ? ORDER BY created_at DESC LIMIT 20",
    ['%' . $merchant->id . '%']
);
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Webhook Yönetimi</h1>
            <p class="page-subtitle">Ödeme bildirimlerini alın</p>
        </div>
        <a href="/banka/public/merchant/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<?php if ($message === 'success'): ?>
    <div class="alert alert-success mb-lg">
        <i class="fas fa-check-circle"></i>
        <span>Webhook URL'i güncellendi.</span>
    </div>
<?php elseif ($message === 'error'): ?>
    <div class="alert alert-danger mb-lg">
        <i class="fas fa-exclamation-circle"></i>
        <span>Geçerli bir URL giriniz.</span>
    </div>
<?php endif; ?>

<!-- Webhook Configuration -->
<div class="card mb-xl">
    <h3 class="card-title" style="margin-bottom: var(--space-lg);">Webhook URL</h3>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Webhook Endpoint URL</label>
            <input type="url" name="webhook_url" class="form-input" 
                   placeholder="https://yourdomain.com/webhook/turkpay" 
                   value="<?= htmlspecialchars($merchant->webhook_url ?? '') ?>">
            <p class="form-hint">Ödeme tamamlandığında bu URL'e POST isteği gönderilir.</p>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Kaydet
        </button>
    </form>
</div>

<!-- Webhook Format -->
<div class="card mb-xl">
    <h3 class="card-title" style="margin-bottom: var(--space-lg);">Webhook Formatı</h3>
    <p style="color: var(--text-muted); margin-bottom: var(--space-lg);">
        Her başarılı ödemede aşağıdaki formatta POST isteği gönderilir:
    </p>
    <pre style="background: var(--bg-glass); padding: var(--space-lg); border-radius: var(--radius-md); overflow-x: auto;"><code>{
    "event": "payment.completed",
    "payment_token": "abc123...",
    "transaction_id": "txn_...",
    "amount": 150.00,
    "currency": "TRY",
    "order_id": "ORDER-123",
    "status": "completed",
    "timestamp": "2024-01-30T12:00:00Z",
    "signature": "sha256_hmac_signature"
}</code></pre>
    <p style="color: var(--text-muted); margin-top: var(--space-lg);">
        <strong>Signature Doğrulama:</strong> HMAC-SHA256 ile API Secret kullanılarak oluşturulur.
    </p>
</div>

<!-- Webhook Logs -->
<div class="card">
    <h3 class="card-title" style="margin-bottom: var(--space-lg);">Son Webhook Gönderileri</h3>
    
    <?php if (empty($webhookLogs)): ?>
        <div class="empty-state" style="padding: var(--space-xl);">
            <div style="font-size: 3rem; margin-bottom: var(--space-md);">📬</div>
            <h4 class="empty-state-title">Henüz webhook gönderimi yok</h4>
            <p class="empty-state-text">Ödemeler tamamlandığında burada görüntülenecek.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>Denemeler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhookLogs as $log): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $log['status'] === 'completed' ? 'success' : ($log['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                    <?= $log['status'] ?>
                                </span>
                            </td>
                            <td><?= $log['attempts'] ?> / <?= $log['max_attempts'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
