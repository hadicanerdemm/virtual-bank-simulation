<?php
/**
 * Admin - Pending Approvals
 */

$pageTitle = 'Bekleyen Onaylar';
include dirname(__DIR__) . '/layouts/header.php';

use App\Config\Database;

$db = Database::getInstance();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = $_POST['transaction_id'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($txId && in_array($action, ['approve', 'reject'])) {
        $newStatus = $action === 'approve' ? 'completed' : 'cancelled';
        $db->update('transactions', ['status' => $newStatus], 'id = ?', [$txId]);
        
        if ($action === 'approve') {
            // Process the actual transfer
            $tx = $db->fetchOne("SELECT * FROM transactions WHERE id = ?", [$txId]);
            if ($tx) {
                // Debit source wallet
                $db->query(
                    "UPDATE wallets SET balance = balance - ?, available_balance = available_balance - ? WHERE id = ?",
                    [$tx['amount'], $tx['amount'], $tx['source_wallet_id']]
                );
                // Credit destination wallet
                $db->query(
                    "UPDATE wallets SET balance = balance + ?, available_balance = available_balance + ? WHERE id = ?",
                    [$tx['amount'], $tx['amount'], $tx['destination_wallet_id']]
                );
            }
        }
        
        header('Location: /banka/public/admin/approvals?success=' . $action);
        exit;
    }
}

$success = $_GET['success'] ?? '';

// Get pending transactions
$pendingList = $db->fetchAll(
    "SELECT t.*, 
            sender.first_name as sender_first, sender.last_name as sender_last, sender.email as sender_email,
            receiver.first_name as receiver_first, receiver.last_name as receiver_last, receiver.email as receiver_email
     FROM transactions t
     LEFT JOIN users sender ON t.source_user_id = sender.id
     LEFT JOIN users receiver ON t.destination_user_id = receiver.id
     WHERE t.status = 'requires_approval'
     ORDER BY t.created_at ASC"
);
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Bekleyen Onaylar</h1>
            <p class="page-subtitle"><?= count($pendingList) ?> işlem onay bekliyor</p>
        </div>
        <a href="/banka/public/admin/dashboard" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Panel
        </a>
    </div>
</div>

<?php if ($success === 'approve'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span>İşlem başarıyla onaylandı ve gerçekleştirildi.</span>
    </div>
<?php elseif ($success === 'reject'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-times-circle"></i>
        <span>İşlem reddedildi.</span>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($pendingList)): ?>
        <div class="empty-state" style="padding: var(--space-3xl);">
            <div style="font-size: 4rem; margin-bottom: var(--space-lg);">✅</div>
            <h3 class="empty-state-title">Bekleyen onay yok</h3>
            <p class="empty-state-text">Tüm işlemler güncel durumda.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Gönderen</th>
                        <th>Alıcı</th>
                        <th>Tutar</th>
                        <th>Tip</th>
                        <th>Açıklama</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingList as $tx): ?>
                        <tr>
                            <td>
                                <div><?= date('d.m.Y', strtotime($tx['created_at'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i:s', strtotime($tx['created_at'])) ?></div>
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
                                <span class="font-mono" style="font-weight: 600; font-size: 1.1rem;">
                                    <?= $tx['currency'] === 'TRY' ? '₺' : ($tx['currency'] === 'USD' ? '$' : '€') ?><?= number_format((float) $tx['amount'], 2, ',', '.') ?>
                                </span>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($tx['type']) ?></span></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($tx['description'] ?? '-') ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: var(--space-sm);">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="transaction_id" value="<?= $tx['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Bu işlemi onaylamak istediğinize emin misiniz?')">
                                            <i class="fas fa-check"></i> Onayla
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="transaction_id" value="<?= $tx['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Bu işlemi reddetmek istediğinize emin misiniz?')">
                                            <i class="fas fa-times"></i> Reddet
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

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
