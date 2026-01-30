<?php
/**
 * Layout Header - Sidebar Navigation
 */

use App\Models\User;
use App\Models\Merchant;

$currentUser = User::find($_SESSION['user_id'] ?? '');
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if user is merchant
$userMerchant = null;
if ($currentUser) {
    $merchants = Merchant::where('user_id', $currentUser->id);
    $userMerchant = $merchants[0] ?? null;
}

function isActive($path) {
    global $currentPath;
    return str_contains($currentPath, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'TurkPay' ?> - TurkPay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="nav-brand">
                <div class="nav-logo">₺</div>
                <span class="nav-title">TurkPay</span>
            </div>
            
            <!-- User Info -->
            <div style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md); margin-bottom: var(--space-xl);">
                <div style="width: 44px; height: 44px; background: var(--gradient-primary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white;">
                    <?= strtoupper(substr($currentUser->first_name ?? 'U', 0, 1)) ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($currentUser->getFullName() ?? 'Kullanıcı') ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= $currentUser->isAdmin() ? 'Admin' : 'Bireysel Hesap' ?>
                    </div>
                </div>
            </div>
            
            <nav class="nav-menu">
                <!-- Main Menu -->
                <div class="nav-section">
                    <div class="nav-section-title">Ana Menü</div>
                    <ul style="list-style: none;">
                        <li class="nav-item">
                            <a href="/banka/public/dashboard" class="nav-link <?= isActive('/dashboard') ?>">
                                <i class="fas fa-home"></i>
                                Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/wallets" class="nav-link <?= isActive('/wallets') ?>">
                                <i class="fas fa-wallet"></i>
                                Cüzdanlarım
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/transfer" class="nav-link <?= isActive('/transfer') ?>">
                                <i class="fas fa-paper-plane"></i>
                                Para Transferi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/exchange" class="nav-link <?= isActive('/exchange') ?>">
                                <i class="fas fa-exchange-alt"></i>
                                Döviz Çevir
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/cards" class="nav-link <?= isActive('/cards') ?>">
                                <i class="fas fa-credit-card"></i>
                                Sanal Kartlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/transactions" class="nav-link <?= isActive('/transactions') ?>">
                                <i class="fas fa-history"></i>
                                İşlem Geçmişi
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Merchant Menu -->
                <div class="nav-section">
                    <div class="nav-section-title">İşletme</div>
                    <ul style="list-style: none;">
                        <?php if ($userMerchant): ?>
                            <li class="nav-item">
                                <a href="/banka/public/merchant/dashboard" class="nav-link <?= isActive('/merchant/dashboard') ?>">
                                    <i class="fas fa-store"></i>
                                    İşletme Paneli
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/banka/public/merchant/api-keys" class="nav-link <?= isActive('/merchant/api-keys') ?>">
                                    <i class="fas fa-key"></i>
                                    API Anahtarları
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="/banka/public/merchant/integration" class="nav-link <?= isActive('/merchant/integration') ?>">
                                    <i class="fas fa-code"></i>
                                    Entegrasyon
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="/banka/public/merchant/register" class="nav-link <?= isActive('/merchant/register') ?>">
                                    <i class="fas fa-store"></i>
                                    İşletme Başvurusu
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <?php if ($currentUser && $currentUser->isAdmin()): ?>
                <!-- Admin Menu -->
                <div class="nav-section">
                    <div class="nav-section-title">Yönetim</div>
                    <ul style="list-style: none;">
                        <li class="nav-item">
                            <a href="/banka/public/admin/dashboard" class="nav-link <?= isActive('/admin/dashboard') ?>">
                                <i class="fas fa-chart-pie"></i>
                                Admin Panel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/users" class="nav-link <?= isActive('/admin/users') ?>">
                                <i class="fas fa-users"></i>
                                Kullanıcılar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/approvals" class="nav-link <?= isActive('/admin/approvals') ?>">
                                <i class="fas fa-check-circle"></i>
                                Onaylar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/audit-logs" class="nav-link <?= isActive('/admin/audit-logs') ?>">
                                <i class="fas fa-shield-alt"></i>
                                Güvenlik Logları
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/queue" class="nav-link <?= isActive('/admin/queue') ?>">
                                <i class="fas fa-layer-group"></i>
                                Kuyruk
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/activities" class="nav-link <?= isActive('/admin/activities') ?>">
                                <i class="fas fa-history"></i>
                                Tüm Aktiviteler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/admin/merchants" class="nav-link <?= isActive('/admin/merchants') ?>">
                                <i class="fas fa-store"></i>
                                İşletmeler
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Demo & Settings -->
                <div class="nav-section">
                    <div class="nav-section-title">Diğer</div>
                    <ul style="list-style: none;">
                        <li class="nav-item">
                            <a href="/banka/public/demo/shop" class="nav-link <?= isActive('/demo/shop') ?>">
                                <i class="fas fa-shopping-cart"></i>
                                Demo Mağaza
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/profile" class="nav-link <?= isActive('/profile') ?>">
                                <i class="fas fa-user-cog"></i>
                                Profil Ayarları
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/banka/public/logout" class="nav-link" style="color: var(--danger);">
                                <i class="fas fa-sign-out-alt"></i>
                                Çıkış Yap
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
