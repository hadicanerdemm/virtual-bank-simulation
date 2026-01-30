<?php
/**
 * Merchant Dashboard
 */

$pageTitle = 'İşletme Paneli';
include dirname(dirname(__DIR__)) . '/views/layouts/header.php';

use App\Models\User;
use App\Models\Merchant;

$user = User::find($_SESSION['user_id']);
$merchants = Merchant::where('user_id', $user->id);
$merchant = $merchants[0] ?? null;

if (!$merchant) {
    header('Location: /banka/public/merchant/register');
    exit;
}

$stats = $merchant->getStatistics();
?>

<div class="page-header">
    <h1 class="page-title">İşletme Paneli</h1>
    <p class="page-subtitle"><?= htmlspecialchars($merchant->business_name) ?></p>
</div>

<!-- Status Card -->
<div class="card mb-xl">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: var(--space-lg);">
            <div style="width: 60px; height: 60px; background: var(--gradient-primary); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                🏪
            </div>
            <div>
                <h2 style="margin: 0;"><?= htmlspecialchars($merchant->business_name) ?></h2>
                <p style="margin: 0; color: var(--text-muted);"><?= $merchant->is_sandbox ? 'Test Modu' : 'Canlı Mod' ?></p>
            </div>
        </div>
        <span class="badge badge-<?= $merchant->status === 'active' ? 'success' : 'warning' ?>">
            <?= $merchant->getStatusLabel() ?>
        </span>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($stats['total_transactions']) ?></div>
            <div class="stat-label">Toplam İşlem</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <div class="stat-value">₺<?= number_format($stats['total_volume'], 2, ',', '.') ?></div>
            <div class="stat-label">Toplam Hacim</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-percentage"></i></div>
        <div class="stat-content">
            <div class="stat-value">%<?= number_format($stats['success_rate'], 1) ?></div>
            <div class="stat-label">Başarı Oranı</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-content">
            <div class="stat-value">₺<?= number_format($stats['daily_total'], 2, ',', '.') ?></div>
            <div class="stat-label">Bugün</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-xl); margin-top: var(--space-xl);">
    <a href="/banka/public/merchant/api-keys" class="card" style="text-decoration: none; text-align: center; padding: var(--space-xl);">
        <i class="fas fa-key" style="font-size: 2rem; color: var(--primary); margin-bottom: var(--space-md);"></i>
        <h3 style="margin: 0;">API Anahtarları</h3>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.875rem;">Anahtarlarınızı görüntüleyin</p>
    </a>
    
    <a href="/banka/public/merchant/integration" class="card" style="text-decoration: none; text-align: center; padding: var(--space-xl);">
        <i class="fas fa-code" style="font-size: 2rem; color: var(--primary); margin-bottom: var(--space-md);"></i>
        <h3 style="margin: 0;">Entegrasyon</h3>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.875rem;">Kod örnekleri ve döküman</p>
    </a>
    
    <a href="/banka/public/merchant/transactions" class="card" style="text-decoration: none; text-align: center; padding: var(--space-xl);">
        <i class="fas fa-history" style="font-size: 2rem; color: var(--primary); margin-bottom: var(--space-md);"></i>
        <h3 style="margin: 0;">İşlemler</h3>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.875rem;">Tüm ödemeleri görüntüle</p>
    </a>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
