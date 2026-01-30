<?php
/**
 * Admin - Audit Logs / Security Logs
 */

$pageTitle = 'Güvenlik Logları';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$riskLevel = $_GET['risk'] ?? '';
$action = $_GET['action'] ?? '';

$where = [];
$params = [];

if ($riskLevel) {
    $where[] = "al.risk_level = ?";
    $params[] = $riskLevel;
}
if ($action) {
    $where[] = "al.action LIKE ?";
    $params[] = "%$action%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$logs = $db->fetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.email 
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.id
     $whereClause
     ORDER BY al.created_at DESC 
     LIMIT ? OFFSET ?",
    [...$params, $perPage, $offset]
);

$totalCount = $db->fetchColumn("SELECT COUNT(*) FROM audit_logs al $whereClause", $params);
$totalPages = ceil($totalCount / $perPage);

// Risk level stats
$criticalCount = $db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE risk_level = 'critical' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$highCount = $db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE risk_level = 'high' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// Get unique actions for filter
$actions = $db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Güvenlik Logları</h1>
            <p class="page-subtitle">Sistem aktivite ve güvenlik kayıtları</p>
        </div>
        <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<!-- Risk Alerts -->
<?php if ($criticalCount > 0 || $highCount > 0): ?>
    <div class="alert alert-danger mb-lg">
        <i class="fas fa-exclamation-triangle"></i>
        <span>
            Son 24 saatte <?= $criticalCount ?> kritik ve <?= $highCount ?> yüksek riskli aktivite tespit edildi.
        </span>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-lg">
    <form method="GET" style="display: flex; gap: var(--space-md); flex-wrap: wrap; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Risk Seviyesi</label>
            <select name="risk" class="form-input">
                <option value="">Tümü</option>
                <option value="critical" <?= $riskLevel === 'critical' ? 'selected' : '' ?>>🔴 Kritik</option>
                <option value="high" <?= $riskLevel === 'high' ? 'selected' : '' ?>>🟠 Yüksek</option>
                <option value="medium" <?= $riskLevel === 'medium' ? 'selected' : '' ?>>🟡 Orta</option>
                <option value="low" <?= $riskLevel === 'low' ? 'selected' : '' ?>>🟢 Düşük</option>
                <option value="info" <?= $riskLevel === 'info' ? 'selected' : '' ?>>🔵 Bilgi</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Aksiyon</label>
            <select name="action" class="form-input">
                <option value="">Tümü</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= htmlspecialchars($a['action']) ?>" <?= $action === $a['action'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['action']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filtrele
        </button>
    </form>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Tarih/Saat</th>
                    <th>Risk</th>
                    <th>Kullanıcı</th>
                    <th>Aksiyon</th>
                    <th>IP Adresi</th>
                    <th>Detaylar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: var(--space-xl); color: var(--text-muted);">
                            Log kaydı bulunamadı
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <div><?= date('d.m.Y', strtotime($log['created_at'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= match($log['risk_level']) {
                                    'critical' => 'danger',
                                    'high' => 'warning',
                                    'medium' => 'info',
                                    default => 'secondary'
                                } ?>">
                                    <?= match($log['risk_level']) {
                                        'critical' => '🔴 Kritik',
                                        'high' => '🟠 Yüksek',
                                        'medium' => '🟡 Orta',
                                        'low' => '🟢 Düşük',
                                        default => '🔵 Bilgi'
                                    } ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['email']): ?>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($log['email']) ?></div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Anonim</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="font-size: 0.8125rem;"><?= htmlspecialchars($log['action']) ?></code>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code>
                            </td>
                            <td style="max-width: 250px;">
                                <?php 
                                $details = json_decode($log['details'] ?? '{}', true);
                                if ($details): 
                                ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE)) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: var(--space-sm); padding: var(--space-lg);">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&risk=<?= urlencode($riskLevel) ?>&action=<?= urlencode($action) ?>" class="btn btn-ghost btn-sm">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            <span style="padding: var(--space-sm) var(--space-md); color: var(--text-muted);">
                Sayfa <?= $page ?> / <?= $totalPages ?>
            </span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&risk=<?= urlencode($riskLevel) ?>&action=<?= urlencode($action) ?>" class="btn btn-ghost btn-sm">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
