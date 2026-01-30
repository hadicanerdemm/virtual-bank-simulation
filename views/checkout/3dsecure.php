<?php
/**
 * 3D Secure OTP Verification Page
 */

use App\Services\PaymentGateway;

$token = $_GET['token'] ?? '';
$otpDemo = $_GET['otp'] ?? '';

$gateway = new PaymentGateway();
$session = $gateway->getSession($token);

if (!$session || $session['status'] !== 'pending_3d') {
    header('Location: /banka/public/checkout/' . $token);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect OTP digits
    $otp = '';
    for ($i = 1; $i <= 6; $i++) {
        $otp .= $_POST['otp' . $i] ?? '';
    }
    
    $result = $gateway->verify3DSecure($token, $otp);
    
    if ($result['success']) {
        header('Location: ' . $result['data']['return_url']);
        exit;
    } else {
        $error = $result['error'] ?? 'Doğrulama başarısız.';
    }
}

$currencySymbol = match($session['currency']) {
    'TRY' => '₺',
    'USD' => '$',
    'EUR' => '€',
    default => $session['currency']
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Secure Doğrulama - TurkPay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
</head>
<body>
    <div class="secure-container">
        <div class="secure-card card fade-in" style="padding: var(--space-2xl);">
            <div style="width: 80px; height: 80px; background: var(--gradient-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-xl); font-size: 2rem; color: white;">
                <i class="fas fa-shield-halved"></i>
            </div>
            
            <h1 style="font-size: 1.5rem; margin-bottom: var(--space-sm);">3D Secure Doğrulama</h1>
            <p class="text-muted mb-lg">
                •••• <?= htmlspecialchars($session['card_last_four']) ?> numaralı kartınıza bağlı 
                telefona gönderilen 6 haneli kodu girin
            </p>
            
            <div style="padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md); margin-bottom: var(--space-xl);">
                <div style="font-size: 0.875rem; color: var(--text-muted);">Tutar</div>
                <div style="font-size: 1.5rem; font-weight: 600;">
                    <?= $currencySymbol . number_format((float) $session['amount'], 2) ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: var(--space-xs);">
                    <?= htmlspecialchars($session['merchant_name']) ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mb-lg">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="otpForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="otp-input-group">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input 
                            type="text" 
                            name="otp<?= $i ?>" 
                            class="otp-input" 
                            maxlength="1"
                            pattern="\d"
                            inputmode="numeric"
                            required
                            <?= $i === 1 ? 'autofocus' : '' ?>
                        >
                    <?php endfor; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <i class="fas fa-check"></i>
                    Doğrula ve Öde
                </button>
            </form>
            
            <p style="margin-top: var(--space-xl); font-size: 0.8125rem; color: var(--text-muted);">
                Kod gelmedi mi? <a href="#">Tekrar Gönder</a>
            </p>
            
            <!-- Demo OTP Display -->
            <?php if ($otpDemo): ?>
                <div style="margin-top: var(--space-xl); padding: var(--space-md); background: var(--warning-bg); border-radius: var(--radius-md); border: 1px solid rgba(245, 158, 11, 0.2);">
                    <p style="font-size: 0.8125rem; color: var(--warning);">
                        <i class="fas fa-info-circle"></i> Demo Modu - OTP Kodu: 
                        <strong class="font-mono"><?= htmlspecialchars($otpDemo) ?></strong>
                    </p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: var(--space-xl); display: flex; justify-content: center; gap: var(--space-md);">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/Visa.svg/200px-Visa.svg.png" alt="Visa Secure" style="height: 24px; filter: grayscale(100%) brightness(200%);">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b7/MasterCard_Logo.svg/200px-MasterCard_Logo.svg.png" alt="Mastercard ID Check" style="height: 24px; filter: grayscale(100%) brightness(200%);">
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus next input
        const inputs = document.querySelectorAll('.otp-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            
            // Only allow numbers
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });
        
        // Auto-fill demo OTP on double-click
        document.body.addEventListener('dblclick', () => {
            const demoOtp = '<?= $otpDemo ?>';
            if (demoOtp) {
                demoOtp.split('').forEach((digit, i) => {
                    if (inputs[i]) inputs[i].value = digit;
                });
            }
        });
    </script>
</body>
</html>
