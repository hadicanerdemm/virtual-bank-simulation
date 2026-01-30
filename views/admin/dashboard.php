<?php
/**
 * Admin Dashboard
 */

$pageTitle = 'Admin Panel';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Job;

$db = Database::getInstance();

// Get statistics
$totalUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalMerchants = $db->fetchColumn("SELECT COUNT(*) FROM merchants");
$totalTransactions = $db->fetchColumn("SELECT COUNT(*) FROM transactions");
$pendingApprovals = $db->fetchColumn("SELECT COUNT(*) FROM transactions WHERE status = 'requires_approval'");
$queuedJobs = $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'pending'");

// Today's stats
$todayTransactions = $db->fetchColumn("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE()");
$todayAmount = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed' AND currency = 'TRY'");
$todayNewUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");

// Last 7 days transaction data for chart
$chartData = $db->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
     FROM transactions 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
     GROUP BY DATE(created_at) 
     ORDER BY date"
);

// Recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.email 
     FROM audit_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Pending approvals list
$pendingList = $db->fetchAll(
    "SELECT t.*, u.first_name, u.last_name 
     FROM transactions t 
     JOIN users u ON t.source_user_id = u.id 
     WHERE t.status = 'requires_approval' 
     ORDER BY t.created_at DESC 
     LIMIT 5"
);
?>

<div class="page-header">
    <h1 class="page-title">Admin Panel</h1>
    <p class="page-subtitle">Sistem genel durumu ve yönetim</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <a href="/banka/public/admin/users" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Toplam Kullanıcı</div>
            <div class="stat-change positive">+<?= $todayNewUsers ?> bugün</div>
        </div>
    </a>
    
    <a href="/banka/public/admin/activities" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon success">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalTransactions) ?></div>
            <div class="stat-label">Toplam İşlem</div>
            <div class="stat-change positive">+<?= $todayTransactions ?> bugün</div>
        </div>
    </a>
    
    <a href="/banka/public/admin/approvals" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($pendingApprovals) ?></div>
            <div class="stat-label">Bekleyen Onay</div>
            <?php if ($pendingApprovals > 0): ?>
                <div class="stat-change negative">Acil işlem bekliyor</div>
            <?php endif; ?>
        </div>
    </a>
    
    <a href="/banka/public/admin/activities" class="stat-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon danger">
            <i class="fas fa-lira-sign"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">₺<?= number_format((float) $todayAmount, 0, ',', '.') ?></div>
            <div class="stat-label">Bugünün Hacmi</div>
        </div>
    </a>
</div>

<!-- Main Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
    <!-- Left Column -->
    <div>
        <!-- Transaction Chart -->
        <div class="card mb-xl">
            <div class="card-header">
                <h2 class="card-title">İşlem Grafiği (Son 7 Gün)</h2>
            </div>
            <div class="card-body">
                <canvas id="transactionChart" height="120"></canvas>
            </div>
        </div>
        
        <!-- Pending Approvals -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Bekleyen Onaylar</h2>
                <a href="/banka/public/admin/approvals" class="btn btn-ghost btn-sm">
                    Tümünü Gör <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($pendingList)): ?>
                <div class="empty-state" style="padding: var(--space-xl);">
                    <div style="font-size: 3rem; margin-bottom: var(--space-md);">✅</div>
                    <h3 class="empty-state-title">Bekleyen onay yok</h3>
                    <p class="empty-state-text">Tüm işlemler güncel</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>İşlem</th>
                                <th>Tutar</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingList as $tx): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($tx['type']) ?></td>
                                    <td class="font-mono">₺<?= number_format((float) $tx['amount'], 2) ?></td>
                                    <td><?= date('d.m H:i', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: var(--space-sm);">
                                            <form method="POST" action="/banka/public/admin/approvals/<?= $tx['id'] ?>/approve" style="display: inline;">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="/banka/public/admin/approvals/<?= $tx['id'] ?>/reject" style="display: inline;">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                <a href="/banka/public/admin/users" class="btn btn-secondary w-full">
                    <i class="fas fa-users"></i>
                    Kullanıcı Yönetimi
                </a>
                <a href="/banka/public/admin/merchants" class="btn btn-secondary w-full">
                    <i class="fas fa-store"></i>
                    İşletme Yönetimi
                </a>
                <a href="/banka/public/admin/audit-logs" class="btn btn-secondary w-full">
                    <i class="fas fa-shield-alt"></i>
                    Güvenlik Logları
                </a>
                <a href="/banka/public/admin/queue" class="btn btn-secondary w-full">
                    <i class="fas fa-layer-group"></i>
                    Kuyruk Durumu (<?= $queuedJobs ?>)
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">Son Aktiviteler</h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md); max-height: 400px; overflow-y: auto;">
                <?php foreach ($recentActivities as $activity): ?>
                    <div style="display: flex; align-items: flex-start; gap: var(--space-md); padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                        <div style="width: 32px; height: 32px; background: var(--<?= match($activity['risk_level']) {
                            'critical' => 'danger-bg',
                            'high' => 'warning-bg',
                            default => 'info-bg'
                        } ?>); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--<?= match($activity['risk_level']) {
                            'critical' => 'danger',
                            'high' => 'warning',
                            default => 'info'
                        } ?>); font-size: 0.875rem;">
                            <i class="fas fa-<?= match($activity['action']) {
                                'login' => 'sign-in-alt',
                                'logout' => 'sign-out-alt',
                                'transaction' => 'exchange-alt',
                                default => 'circle'
                            } ?>"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($activity['action']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <?= htmlspecialchars($activity['first_name'] ?? 'Sistem') ?> • 
                                <?= date('H:i', strtotime($activity['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Transaction Chart
    const ctx = document.getElementById('transactionChart').getContext('2d');
    const chartData = <?= json_encode($chartData) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.date),
            datasets: [{
                label: 'İşlem Sayısı',
                data: chartData.map(d => d.count),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#6b6b80' }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#6b6b80' },
                    beginAtZero: true
                }
            }
        }
    });
</script>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
