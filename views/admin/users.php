<?php
/**
 * Admin - User Management
 */

$pageTitle = 'Kullanıcı Yönetimi';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}
if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $db->fetchAll(
    "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [...$params, $perPage, $offset]
);

$totalCount = $db->fetchColumn("SELECT COUNT(*) FROM users $whereClause", $params);
$totalPages = ceil($totalCount / $perPage);

// Stats
$activeUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'active'");
$pendingUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$lockedUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'locked'");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Kullanıcı Yönetimi</h1>
            <p class="page-subtitle">Toplam <?= number_format($totalCount) ?> kullanıcı</p>
        </div>
        <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-xl">
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-user-check"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($activeUsers) ?></div>
            <div class="stat-label">Aktif</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-user-clock"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($pendingUsers) ?></div>
            <div class="stat-label">Bekleyen</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-user-lock"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($lockedUsers) ?></div>
            <div class="stat-label">Kilitli</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-lg">
    <form method="GET" style="display: flex; gap: var(--space-md); flex-wrap: wrap; align-items: end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
            <label class="form-label">Ara</label>
            <input type="text" name="search" class="form-input" placeholder="E-posta, ad veya soyad..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Durum</label>
            <select name="status" class="form-input">
                <option value="">Tümü</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Bekleyen</option>
                <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Kilitli</option>
                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Askıda</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Rol</label>
            <select name="role" class="form-input">
                <option value="">Tümü</option>
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Kullanıcı</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Filtrele
        </button>
    </form>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>E-posta</th>
                    <th>Telefon</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>Kayıt Tarihi</th>
                    <th>Son Giriş</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: var(--space-xl); color: var(--text-muted);">
                            Kullanıcı bulunamadı
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: var(--space-md);">
                                    <div style="width: 36px; height: 36px; background: var(--gradient-primary); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                        <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">ID: <?= substr($user['id'], 0, 8) ?>...</div>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= htmlspecialchars($user['email']) ?></code></td>
                            <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                    <?= $user['role'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= match($user['status']) {
                                    'active' => 'success',
                                    'pending' => 'warning',
                                    'locked' => 'danger',
                                    default => 'secondary'
                                } ?>">
                                    <?= $user['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                            <td><?= $user['last_login_at'] ? date('d.m.Y H:i', strtotime($user['last_login_at'])) : '-' ?></td>
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
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&role=<?= urlencode($role) ?>" class="btn btn-ghost btn-sm">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            <span style="padding: var(--space-sm) var(--space-md); color: var(--text-muted);">
                Sayfa <?= $page ?> / <?= $totalPages ?>
            </span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&role=<?= urlencode($role) ?>" class="btn btn-ghost btn-sm">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
