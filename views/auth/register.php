<?php
use App\Models\User;

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($firstName)) {
        $errors['first_name'] = 'Ad gereklidir.';
    }
    
    if (empty($lastName)) {
        $errors['last_name'] = 'Soyad gereklidir.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'E-posta gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Geçerli bir e-posta adresi girin.';
    } elseif (User::exists('email', $email)) {
        $errors['email'] = 'Bu e-posta adresi zaten kayıtlı.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Şifre gereklidir.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Şifre en az 8 karakter olmalıdır.';
    }
    
    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Şifreler eşleşmiyor.';
    }
    
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Kullanım koşullarını kabul etmelisiniz.';
    }
    
    if (empty($errors)) {
        try {
            $user = User::register([
                'email' => $email,
                'password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone
            ]);
            
            // Add welcome bonus
            $user->addBonusBalance(1000, 'TRY', 'Hoş geldin bonusu');
            
            header('Location: /banka/public/login?registered=1');
            exit;
        } catch (\Exception $e) {
            $error = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - TurkPay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card fade-in" style="max-width: 500px;">
            <div class="auth-logo">
                <a href="/banka/public/" class="nav-brand" style="justify-content: center;">
                    <div class="nav-logo">₺</div>
                    <span class="nav-title">TurkPay</span>
                </a>
            </div>
            
            <div class="auth-title">
                <h1 style="font-size: 1.75rem; margin-bottom: var(--space-sm);">Hesap Oluştur</h1>
                <p class="text-muted">Hemen ücretsiz kayıt olun ve 1.000₺ hoş geldin bonusu kazanın!</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/banka/public/register">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                    <div class="form-group">
                        <label class="form-label" for="first_name">Ad</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-input <?= isset($errors['first_name']) ? 'error' : '' ?>" 
                            placeholder="Ahmet"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            required
                        >
                        <?php if (isset($errors['first_name'])): ?>
                            <div class="form-error"><?= $errors['first_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="last_name">Soyad</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-input <?= isset($errors['last_name']) ? 'error' : '' ?>" 
                            placeholder="Yılmaz"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            required
                        >
                        <?php if (isset($errors['last_name'])): ?>
                            <div class="form-error"><?= $errors['last_name'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">E-posta Adresi</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input <?= isset($errors['email']) ? 'error' : '' ?>" 
                        placeholder="ornek@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                    <?php if (isset($errors['email'])): ?>
                        <div class="form-error"><?= $errors['email'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">Telefon (Opsiyonel)</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-input" 
                        placeholder="+90 5XX XXX XX XX"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Şifre</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input <?= isset($errors['password']) ? 'error' : '' ?>" 
                        placeholder="En az 8 karakter"
                        required
                    >
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?= $errors['password'] ?></div>
                    <?php else: ?>
                        <div class="form-hint">En az 8 karakter olmalıdır.</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Şifre Tekrar</label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        class="form-input <?= isset($errors['password_confirm']) ? 'error' : '' ?>" 
                        placeholder="Şifrenizi tekrar girin"
                        required
                    >
                    <?php if (isset($errors['password_confirm'])): ?>
                        <div class="form-error"><?= $errors['password_confirm'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-start; gap: var(--space-sm);">
                    <input 
                        type="checkbox" 
                        id="terms" 
                        name="terms" 
                        style="width: 18px; height: 18px; margin-top: 2px;"
                        <?= !empty($_POST['terms']) ? 'checked' : '' ?>
                    >
                    <label for="terms" style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.4;">
                        <a href="#">Kullanım Koşulları</a> ve <a href="#">Gizlilik Politikası</a>'nı 
                        okudum, kabul ediyorum.
                    </label>
                </div>
                <?php if (isset($errors['terms'])): ?>
                    <div class="form-error" style="margin-top: -10px; margin-bottom: 15px;"><?= $errors['terms'] ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <i class="fas fa-user-plus"></i>
                    Hesap Oluştur
                </button>
            </form>
            
            <div class="auth-footer">
                Zaten hesabınız var mı? 
                <a href="/banka/public/login">Giriş yapın</a>
            </div>
            
            <!-- Bonus Info -->
            <div style="margin-top: var(--space-xl); padding: var(--space-md); background: var(--success-bg); border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2);">
                <p style="font-size: 0.875rem; color: var(--success); display: flex; align-items: center; gap: var(--space-sm);">
                    <i class="fas fa-gift"></i>
                    <span>Kayıt olduğunuzda hesabınıza <strong>1.000₺</strong> hoş geldin bonusu yüklenir!</span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
