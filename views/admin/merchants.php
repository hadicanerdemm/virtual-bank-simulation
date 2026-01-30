<?php
/**
 * Admin - Merchant Management
 */

$pageTitle = 'İşletme Yönetimi';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $merchantId = $_POST['merchant_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    
    if ($merchantId && in_array($newStatus, ['active', 'suspended', 'pending'])) {
        $db->update('merchants', ['status' => $newStatus], 'id = ?', [$merchantId]);
        header('Location: /banka/public/admin/merchants?success=1');
        exit;
    }
}

$success = isset($_GET['success']);

// Get merchants with user info
$merchants = $db->fetchAll(
    "SELECT m.*, u.first_name, u.last_name, u.email as user_email
     FROM merchants m
     JOIN users u ON m.user_id = u.id
     ORDER BY m.created_at DESC"
);

// Stats
$activeMerchants = $db->fetchColumn("SELECT COUNT(*) FROM merchants WHERE status = 'active'");
$pendingMerchants = $db->fetchColumn("SELECT COUNT(*) FROM merchants WHERE status = 'pending'");
$totalVolume = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE merchant_id IS NOT NULL AND status = 'completed'");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">İşletme Yönetimi</h1>
            <p class="page-subtitle">Toplam <?= count($merchants) ?> işletme</p>
        </div>
        <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mb-lg">
        <i class="fas fa-check-circle"></i>
        <span>İşletme durumu güncellendi.</span>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid mb-xl">
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-store"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($activeMerchants) ?></div>
            <div class="stat-label">Aktif İşletme</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($pendingMerchants) ?></div>
            <div class="stat-label">Onay Bekleyen</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-content">
            <div class="stat-value">₺<?= number_format((float) $totalVolume, 0, ',', '.') ?></div>
            <div class="stat-label">Toplam Hacim</div>
        </div>
    </div>
</div>

<!-- Merchants Table -->
<div class="card">
    <?php if (empty($merchants)): ?>
        <div class="empty-state" style="padding: var(--space-3xl);">
            <div style="font-size: 4rem; margin-bottom: var(--space-lg);">🏪</div>
            <h3 class="empty-state-title">Henüz işletme yok</h3>
            <p class="empty-state-text">Kayıtlı işletmeler burada görüntülenecek.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>İşletme</th>
                        <th>Sahip</th>
                        <th>Website</th>
                        <th>Mod</th>
                        <th>Durum</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($merchants as $merchant): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($merchant['business_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($merchant['business_type']) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($merchant['first_name'] . ' ' . $merchant['last_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($merchant['user_email']) ?></div>
                            </td>
                            <td>
                                <?php if ($merchant['website']): ?>
                                    <a href="<?= htmlspecialchars($merchant['website']) ?>" target="_blank" class="text-muted">
                                        <?= parse_url($merchant['website'], PHP_URL_HOST) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $merchant['is_sandbox'] ? 'warning' : 'success' ?>">
                                    <?= $merchant['is_sandbox'] ? 'Test' : 'Canlı' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= match($merchant['status']) {
                                    'active' => 'success',
                                    'suspended' => 'danger',
                                    default => 'warning'
                                } ?>">
                                    <?= $merchant['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y', strtotime($merchant['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: var(--space-xs);">
                                    <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
                                    <?php if ($merchant['status'] !== 'active'): ?>
                                        <button type="submit" name="status" value="active" class="btn btn-success btn-sm" title="Aktifleştir">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($merchant['status'] !== 'suspended'): ?>
                                        <button type="submit" name="status" value="suspended" class="btn btn-danger btn-sm" title="Askıya Al">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
