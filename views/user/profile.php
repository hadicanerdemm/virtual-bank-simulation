<?php
/**
 * User Profile Page
 */

$pageTitle = 'Profil Ayarları';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;

$user = User::find($_SESSION['user_id']);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $user->first_name = trim($_POST['first_name'] ?? '');
        $user->last_name = trim($_POST['last_name'] ?? '');
        $user->phone = trim($_POST['phone'] ?? '');
        $user->save();
        
        $_SESSION['user_name'] = $user->getFullName();
        $success = 'Profil bilgileriniz güncellendi.';
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPassword, $user->password)) {
            $error = 'Mevcut şifre yanlış.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Yeni şifre en az 8 karakter olmalıdır.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $user->save();
            $success = 'Şifreniz başarıyla değiştirildi.';
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Profil Ayarları</h1>
    <p class="page-subtitle">Hesap bilgilerinizi ve güvenlik ayarlarınızı yönetin.</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mb-xl">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mb-xl">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
    <!-- Profile Info -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">
            <i class="fas fa-user"></i> Profil Bilgileri
        </h2>
        
        <form method="POST" action="/banka/public/profile">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label class="form-label">Ad</label>
                <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($user->first_name) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Soyad</label>
                <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($user->last_name) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">E-posta</label>
                <input type="email" class="form-input" value="<?= htmlspecialchars($user->email) ?>" disabled>
                <div class="form-hint">E-posta adresi değiştirilemez.</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Telefon</label>
                <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($user->phone ?? '') ?>" placeholder="+90 5XX XXX XX XX">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Kaydet
            </button>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">
            <i class="fas fa-lock"></i> Şifre Değiştir
        </h2>
        
        <form method="POST" action="/banka/public/profile">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label class="form-label">Mevcut Şifre</label>
                <input type="password" name="current_password" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Yeni Şifre</label>
                <input type="password" name="new_password" class="form-input" placeholder="En az 8 karakter" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Yeni Şifre (Tekrar)</label>
                <input type="password" name="confirm_password" class="form-input" required>
            </div>
            
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-key"></i> Şifreyi Değiştir
            </button>
        </form>
    </div>
</div>

<!-- Account Info -->
<div class="card mt-xl">
    <h2 class="card-title" style="margin-bottom: var(--space-xl);">
        <i class="fas fa-info-circle"></i> Hesap Bilgileri
    </h2>
    
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-xl);">
        <div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-xs);">Hesap Durumu</div>
            <span class="badge badge-success"><?= ucfirst($user->status) ?></span>
        </div>
        <div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-xs);">Hesap Tipi</div>
            <div style="font-weight: 500;"><?= $user->isAdmin() ? 'Yönetici' : 'Bireysel' ?></div>
        </div>
        <div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-xs);">Kayıt Tarihi</div>
            <div style="font-weight: 500;"><?= date('d.m.Y', strtotime($user->created_at)) ?></div>
        </div>
        <div>
            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-xs);">Son Giriş</div>
            <div style="font-weight: 500;"><?= $user->last_login_at ? date('d.m.Y H:i', strtotime($user->last_login_at)) : '-' ?></div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
