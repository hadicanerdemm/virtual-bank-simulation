<?php
/**
 * Admin - Job Queue Status
 */

$pageTitle = 'Kuyruk Durumu';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Get queue stats
$pendingJobs = $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'pending'");
$processingJobs = $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'processing'");
$completedJobs = $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$failedJobs = $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// Get recent jobs
$recentJobs = $db->fetchAll(
    "SELECT * FROM jobs ORDER BY created_at DESC LIMIT 50"
);
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Kuyruk Durumu</h1>
            <p class="page-subtitle">Arkaplan işleri ve webhook kuyruğu</p>
        </div>
        <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-xl">
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($pendingJobs) ?></div>
            <div class="stat-label">Bekleyen</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($processingJobs) ?></div>
            <div class="stat-label">İşleniyor</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($completedJobs) ?></div>
            <div class="stat-label">Tamamlanan (24s)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-times"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($failedJobs) ?></div>
            <div class="stat-label">Başarısız (24s)</div>
        </div>
    </div>
</div>

<!-- Jobs Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Son İşler</h2>
    </div>
    
    <?php if (empty($recentJobs)): ?>
        <div class="empty-state" style="padding: var(--space-3xl);">
            <div style="font-size: 4rem; margin-bottom: var(--space-lg);">📭</div>
            <h3 class="empty-state-title">Kuyrukta iş yok</h3>
            <p class="empty-state-text">Arkaplan işleri burada görüntülenecek.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tip</th>
                        <th>Durum</th>
                        <th>Denemeler</th>
                        <th>Oluşturulma</th>
                        <th>Sonraki Deneme</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentJobs as $job): ?>
                        <tr>
                            <td><code><?= substr($job['id'], 0, 8) ?>...</code></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($job['type']) ?></span></td>
                            <td>
                                <span class="badge badge-<?= match($job['status']) {
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'processing' => 'info',
                                    default => 'warning'
                                } ?>">
                                    <?= $job['status'] ?>
                                </span>
                            </td>
                            <td><?= $job['attempts'] ?> / <?= $job['max_attempts'] ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($job['created_at'])) ?></td>
                            <td>
                                <?= $job['next_retry_at'] ? date('d.m.Y H:i', strtotime($job['next_retry_at'])) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
