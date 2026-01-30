<?php
use App\Models\User;
use App\Models\AuditLog;
use App\Services\FraudDetectionService;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'E-posta ve şifre gereklidir.';
    } else {
        // Check fraud detection
        $fraudService = new FraudDetectionService();
        $fraudCheck = $fraudService->checkLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        
        if (!$fraudCheck['allowed']) {
            $error = $fraudCheck['reason'];
        } else {
            $user = User::authenticate($email, $password);
            
            if ($user) {
                // Log successful login
                $fraudService->logLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', true);
                AuditLog::logLogin($user->id, true);
                
                // Set session
                $_SESSION['user_id'] = $user->id;
                $_SESSION['user_name'] = $user->getFullName();
                $_SESSION['user_email'] = $user->email;
                $_SESSION['user_role'] = $user->role;
                
                // Redirect based on role
                if ($user->isAdmin()) {
                    header('Location: /banka/public/admin/dashboard');
                } else {
                    header('Location: /banka/public/dashboard');
                }
                exit;
            } else {
                // Log failed login
                $fraudService->logLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', false, 'Invalid credentials');
                $error = 'E-posta veya şifre hatalı.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - TurkPay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card fade-in">
            <div class="auth-logo">
                <a href="/banka/public/" class="nav-brand" style="justify-content: center;">
                    <div class="nav-logo">₺</div>
                    <span class="nav-title">TurkPay</span>
                </a>
            </div>
            
            <div class="auth-title">
                <h1 style="font-size: 1.75rem; margin-bottom: var(--space-sm);">Hoş Geldiniz!</h1>
                <p class="text-muted">Hesabınıza giriş yapın</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Hesabınız oluşturuldu! Şimdi giriş yapabilirsiniz.</span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/banka/public/login">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">E-posta Adresi</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="ornek@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">
                        Şifre
                        <a href="#" style="float: right; font-weight: 400;">Şifremi Unuttum</a>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                    >
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: var(--space-sm);">
                    <input type="checkbox" id="remember" name="remember" style="width: 18px; height: 18px;">
                    <label for="remember" style="font-size: 0.875rem; color: var(--text-secondary);">
                        Beni hatırla
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Giriş Yap
                </button>
            </form>
            
            <div class="auth-divider">veya</div>
            
            <div style="display: flex; gap: var(--space-md);">
                <button class="btn btn-secondary w-full" disabled>
                    <i class="fab fa-google"></i>
                    Google
                </button>
                <button class="btn btn-secondary w-full" disabled>
                    <i class="fab fa-apple"></i>
                    Apple
                </button>
            </div>
            
            <div class="auth-footer">
                Hesabınız yok mu? 
                <a href="/banka/public/register">Ücretsiz kayıt olun</a>
            </div>
            
            <!-- Demo Login Info -->
            <div style="margin-top: var(--space-xl); padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-sm);">
                    <i class="fas fa-info-circle"></i> Demo Giriş Bilgileri:
                </p>
                <p style="font-size: 0.8125rem; font-family: var(--font-mono);">
                    Admin: admin@turkpay.local / admin123
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-fill demo credentials on double-click
        document.querySelector('.auth-card').addEventListener('dblclick', (e) => {
            if (e.target.closest('.form-group')) return;
            document.getElementById('email').value = 'admin@turkpay.local';
            document.getElementById('password').value = 'admin123';
        });
    </script>
</body>
</html>
