<?php
/**
 * Merchant Transactions
 */

$pageTitle = 'İşletme İşlemleri';
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

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$transactions = $db->fetchAll(
    "SELECT * FROM transactions WHERE merchant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$merchant->id, $perPage, $offset]
);

$totalCount = $db->fetchColumn("SELECT COUNT(*) FROM transactions WHERE merchant_id = ?", [$merchant->id]);
$totalPages = ceil($totalCount / $perPage);
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">İşlemler</h1>
            <p class="page-subtitle">Toplam <?= number_format($totalCount) ?> ödeme işlemi</p>
        </div>
        <a href="/banka/public/merchant/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state" style="padding: var(--space-3xl);">
            <div style="font-size: 4rem; margin-bottom: var(--space-lg);">💳</div>
            <h3 class="empty-state-title">Henüz işlem yok</h3>
            <p class="empty-state-text">API üzerinden yapılan ödemeler burada görüntülenecek.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Referans</th>
                        <th>Tutar</th>
                        <th>Tip</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <div><?= date('d.m.Y', strtotime($tx['created_at'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i:s', strtotime($tx['created_at'])) ?></div>
                            </td>
                            <td><code><?= htmlspecialchars(substr($tx['reference_id'] ?? $tx['id'], 0, 16)) ?>...</code></td>
                            <td>
                                <span class="font-mono" style="font-weight: 600;">
                                    <?= $tx['currency'] === 'TRY' ? '₺' : ($tx['currency'] === 'USD' ? '$' : '€') ?><?= number_format((float) $tx['amount'], 2, ',', '.') ?>
                                </span>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($tx['type']) ?></span></td>
                            <td>
                                <span class="badge badge-<?= match($tx['status']) {
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    default => 'secondary'
                                } ?>">
                                    <?= $tx['status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: var(--space-sm); padding: var(--space-lg);">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                <span style="padding: var(--space-sm) var(--space-md); color: var(--text-muted);">
                    Sayfa <?= $page ?> / <?= $totalPages ?>
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
