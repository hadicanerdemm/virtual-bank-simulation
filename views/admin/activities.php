<?php
/**
 * Admin Activities Page - View all system activities
 */

$pageTitle = 'Tüm Aktiviteler';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterType = $_GET['type'] ?? '';
$filterUser = $_GET['user'] ?? '';

$where = [];
$params = [];

if ($filterType) {
    $where[] = "t.type = ?";
    $params[] = $filterType;
}

if ($filterUser) {
    $where[] = "(sender.email LIKE ? OR receiver.email LIKE ?)";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get all transactions with sender and receiver info
$queryParams = array_merge($params, [$perPage, $offset]);
$transactions = $db->fetchAll(
    "SELECT t.*, 
            sender.first_name as sender_first, sender.last_name as sender_last, sender.email as sender_email,
            receiver.first_name as receiver_first, receiver.last_name as receiver_last, receiver.email as receiver_email
     FROM transactions t
     LEFT JOIN users sender ON t.source_user_id = sender.id
     LEFT JOIN users receiver ON t.destination_user_id = receiver.id
     $whereClause
     ORDER BY t.created_at DESC
     LIMIT ? OFFSET ?",
    $queryParams
);

$totalCount = $db->fetchColumn("SELECT COUNT(*) FROM transactions t 
     LEFT JOIN users sender ON t.source_user_id = sender.id
     LEFT JOIN users receiver ON t.destination_user_id = receiver.id
     $whereClause", $params);
$totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 1;

// Get recent registrations
$recentRegistrations = $db->fetchAll(
    "SELECT id, email, first_name, last_name, created_at, status, role 
     FROM users 
     ORDER BY created_at DESC 
     LIMIT 20"
);

// Get transaction types for filter
$transactionTypes = $db->fetchAll("SELECT DISTINCT type FROM transactions ORDER BY type");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Sistem Aktiviteleri</h1>
            <p class="page-subtitle">Tüm para transferleri ve hesap hareketleri</p>
        </div>
        <div style="display: flex; gap: var(--space-md);">
            <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
                <i class="fas fa-arrow-left"></i> Panel
            </a>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card mb-xl">
    <div style="display: flex; gap: var(--space-lg); border-bottom: 1px solid var(--border-color); padding-bottom: var(--space-md);">
        <button class="btn btn-ghost active" onclick="showTab('transfers')" id="tab-transfers">
            <i class="fas fa-exchange-alt"></i> Para Transferleri
        </button>
        <button class="btn btn-ghost" onclick="showTab('registrations')" id="tab-registrations">
            <i class="fas fa-user-plus"></i> Yeni Kayıtlar
        </button>
    </div>
</div>

<!-- Transfers Tab -->
<div id="panel-transfers">
    <!-- Filters -->
    <div class="card mb-lg">
        <form method="GET" style="display: flex; gap: var(--space-md); flex-wrap: wrap; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">İşlem Tipi</label>
                <select name="type" class="form-input" style="min-width: 150px;">
                    <option value="">Tümü</option>
                    <?php foreach ($transactionTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type['type']) ?>" <?= $filterType === $type['type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Kullanıcı E-posta</label>
                <input type="text" name="user" class="form-input" placeholder="Ara..." value="<?= htmlspecialchars($filterUser) ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrele
            </button>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Referans</th>
                        <th>Gönderen</th>
                        <th>Alıcı</th>
                        <th>Tutar</th>
                        <th>Tip</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: var(--space-xl); color: var(--text-muted);">
                                Henüz işlem yok
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td>
                                    <div style="font-size: 0.875rem;"><?= date('d.m.Y', strtotime($tx['created_at'])) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i:s', strtotime($tx['created_at'])) ?></div>
                                </td>
                                <td>
                                    <code style="font-size: 0.75rem;"><?= htmlspecialchars(substr($tx['reference_id'] ?? $tx['id'], 0, 12)) ?>...</code>
                                </td>
                                <td>
                                    <?php if ($tx['sender_email']): ?>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($tx['sender_first'] . ' ' . $tx['sender_last']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($tx['sender_email']) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tx['receiver_email']): ?>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($tx['receiver_first'] . ' ' . $tx['receiver_last']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($tx['receiver_email']) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-mono" style="font-weight: 600; color: <?= ($tx['type'] === 'transfer' && $tx['sender_email']) ? 'var(--danger)' : 'var(--success)' ?>;">
                                        <?= $tx['currency'] === 'TRY' ? '₺' : ($tx['currency'] === 'USD' ? '$' : '€') ?><?= number_format((float) $tx['amount'], 2, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($tx['type']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= match($tx['status']) {
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        default => 'secondary'
                                    } ?>">
                                        <?= htmlspecialchars($tx['status']) ?>
                                    </span>
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
                    <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>" class="btn btn-ghost btn-sm">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <span style="padding: var(--space-sm) var(--space-md); color: var(--text-muted);">
                    Sayfa <?= $page ?> / <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>" class="btn btn-ghost btn-sm">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Registrations Tab -->
<div id="panel-registrations" style="display: none;">
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kayıt Tarihi</th>
                        <th>Kullanıcı</th>
                        <th>E-posta</th>
                        <th>Rol</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRegistrations as $user): ?>
                        <tr>
                            <td>
                                <div><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($user['email']) ?></code>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                    <?= $user['role'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                    <?= $user['status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    document.getElementById('panel-transfers').style.display = tabName === 'transfers' ? 'block' : 'none';
    document.getElementById('panel-registrations').style.display = tabName === 'registrations' ? 'block' : 'none';
    
    document.getElementById('tab-transfers').classList.toggle('active', tabName === 'transfers');
    document.getElementById('tab-registrations').classList.toggle('active', tabName === 'registrations');
}
</script>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
