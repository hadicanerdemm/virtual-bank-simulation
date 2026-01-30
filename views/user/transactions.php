<?php
/**
 * Transaction History Page
 */

$pageTitle = 'İşlem Geçmişi';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Models\Transaction;

$user = User::find($_SESSION['user_id']);
$transactions = $user->transactions(50);
?>

<div class="page-header">
    <h1 class="page-title">İşlem Geçmişi</h1>
    <p class="page-subtitle">Tüm hesap hareketlerinizi buradan görüntüleyebilirsiniz.</p>
</div>

<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3 class="empty-state-title">Henüz işlem yok</h3>
            <p class="empty-state-text">İlk transferinizi yapın veya alışveriş yapın.</p>
            <a href="/banka/public/transfer" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Transfer Yap
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem</th>
                        <th>Açıklama</th>
                        <th>Durum</th>
                        <th style="text-align: right;">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <?php
                        $isIncoming = $tx->destination_user_id === $user->id;
                        $amountClass = $isIncoming ? 'text-success' : 'text-danger';
                        $amountSign = $isIncoming ? '+' : '-';
                        ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <div style="font-weight: 500;"><?= date('d.m.Y', strtotime($tx->created_at)) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('H:i', strtotime($tx->created_at)) ?></div>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: var(--space-sm);">
                                    <i class="fas fa-<?= $isIncoming ? 'arrow-down text-success' : 'arrow-up text-danger' ?>"></i>
                                    <?= $tx->getTypeLabel() ?>
                                </span>
                            </td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($tx->description ?: '-') ?>
                            </td>
                            <td>
                                <span class="badge <?= $tx->getStatusBadge() ?>"><?= $tx->getStatusLabel() ?></span>
                            </td>
                            <td style="text-align: right; font-weight: 600;" class="<?= $amountClass ?>">
                                <?= $amountSign . $tx->getFormattedAmount() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
