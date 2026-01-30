<?php
/**
 * Payment Checkout Page
 */

use App\Services\PaymentGateway;

$token = $_GET['token'] ?? '';
$gateway = new PaymentGateway();
$session = $gateway->getSession($token);

if (!$session) {
    // Invalid or expired session
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hatalı Ödeme - TurkPay</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="/banka/public/assets/css/style.css">
    </head>
    <body>
        <div class="auth-container">
            <div class="card text-center" style="max-width: 400px; padding: var(--space-2xl);">
                <div style="font-size: 4rem; margin-bottom: var(--space-lg);">❌</div>
                <h1 style="font-size: 1.5rem; margin-bottom: var(--space-md);">Geçersiz İşlem</h1>
                <p class="text-muted">Ödeme oturumu bulunamadı veya süresi dolmuş.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$currencySymbol = match($session['currency']) {
    'TRY' => '₺',
    'USD' => '$',
    'EUR' => '€',
    default => $session['currency']
};

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = str_replace(' ', '', $_POST['card_number'] ?? '');
    $cardHolder = trim($_POST['card_holder'] ?? '');
    $expiryMonth = $_POST['expiry_month'] ?? '';
    $expiryYear = $_POST['expiry_year'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    
    if (empty($cardNumber) || strlen($cardNumber) !== 16) {
        $error = 'Geçerli bir kart numarası girin.';
    } elseif (empty($cardHolder)) {
        $error = 'Kart sahibi adı gereklidir.';
    } elseif (empty($expiryMonth) || empty($expiryYear)) {
        $error = 'Geçerlilik tarihi gereklidir.';
    } elseif (strlen($cvv) < 3) {
        $error = 'Geçerli bir CVV girin.';
    } else {
        $result = $gateway->processCardPayment(
            $token,
            $cardNumber,
            $cardHolder,
            $expiryMonth,
            $expiryYear,
            $cvv
        );
        
        if ($result['success'] && !empty($result['requires_3d'])) {
            // Redirect to 3D Secure page
            header('Location: /banka/public/checkout/' . $token . '/3d?otp=' . ($result['data']['otp_demo'] ?? ''));
            exit;
        } elseif ($result['success']) {
            // Direct success (shouldn't happen normally)
            header('Location: ' . $result['data']['return_url']);
            exit;
        } else {
            $error = $result['error'] ?? 'Ödeme işlemi başarısız.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme - <?= htmlspecialchars($session['merchant_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
</head>
<body>
    <div class="checkout-container">
        <!-- Left - Form -->
        <div class="checkout-left">
            <div class="nav-brand" style="margin-bottom: var(--space-2xl);">
                <div class="nav-logo">₺</div>
                <span class="nav-title">TurkPay</span>
                <span class="badge badge-success" style="margin-left: var(--space-md);">Güvenli Ödeme</span>
            </div>
            
            <h1 style="font-size: 1.5rem; margin-bottom: var(--space-sm);">Ödeme Bilgileri</h1>
            <p class="text-muted mb-xl">Kart bilgilerinizi güvenli bir şekilde girin</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mb-xl">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Kart Numarası</label>
                    <input 
                        type="text" 
                        name="card_number" 
                        class="form-input font-mono" 
                        placeholder="0000 0000 0000 0000"
                        maxlength="19"
                        id="cardNumberInput"
                        autocomplete="cc-number"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kart Üzerindeki İsim</label>
                    <input 
                        type="text" 
                        name="card_holder" 
                        class="form-input" 
                        placeholder="AD SOYAD"
                        style="text-transform: uppercase;"
                        autocomplete="cc-name"
                        required
                    >
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg);">
                    <div class="form-group">
                        <label class="form-label">Son Kullanma Tarihi</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm);">
                            <select name="expiry_month" class="form-select" required>
                                <option value="">Ay</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>">
                                        <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="expiry_year" class="form-select" required>
                                <option value="">Yıl</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                    <option value="<?= substr($i, -2) ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">CVV</label>
                        <input 
                            type="text" 
                            name="cvv" 
                            class="form-input font-mono text-center" 
                            placeholder="•••"
                            maxlength="4"
                            autocomplete="cc-csc"
                            required
                        >
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-full mt-lg">
                    <i class="fas fa-lock"></i>
                    <?= $currencySymbol . number_format((float) $session['amount'], 2) ?> Öde
                </button>
            </form>
            
            <div style="margin-top: var(--space-2xl); display: flex; justify-content: center; gap: var(--space-lg); color: var(--text-muted); font-size: 0.8125rem;">
                <span><i class="fas fa-lock"></i> 256-bit SSL</span>
                <span><i class="fas fa-shield-halved"></i> 3D Secure</span>
                <span><i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i></span>
            </div>
            
            <!-- Demo Card Info -->
            <div style="margin-top: var(--space-xl); padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: var(--space-sm);">
                    <i class="fas fa-info-circle"></i> Demo için TurkPay sanal kartınızı kullanın veya:
                </p>
                <p style="font-size: 0.8125rem; font-family: var(--font-mono);">
                    4532 0123 4567 8901 | 12/28 | CVV: 123
                </p>
            </div>
        </div>
        
        <!-- Right - Summary -->
        <div class="checkout-right">
            <div class="checkout-summary">
                <?php if ($session['merchant_logo']): ?>
                    <img src="<?= htmlspecialchars($session['merchant_logo']) ?>" alt="<?= htmlspecialchars($session['merchant_name']) ?>" style="height: 40px; margin-bottom: var(--space-lg);">
                <?php endif; ?>
                
                <h2 style="font-size: 1rem; color: var(--text-secondary); margin-bottom: var(--space-sm);">
                    <?= htmlspecialchars($session['merchant_name']) ?>
                </h2>
                
                <div class="checkout-amount">
                    <?= $currencySymbol . number_format((float) $session['amount'], 2) ?>
                </div>
                
                <p class="checkout-merchant">Sipariş #<?= htmlspecialchars($session['order_id']) ?></p>
                
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: var(--space-xl) 0;">
                
                <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Tutar</span>
                        <span><?= $currencySymbol . number_format((float) $session['amount'], 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Komisyon</span>
                        <span class="text-success">₺0.00</span>
                    </div>
                    <hr style="border: none; border-top: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; font-weight: 600;">
                        <span>Toplam</span>
                        <span><?= $currencySymbol . number_format((float) $session['amount'], 2) ?></span>
                    </div>
                </div>
                
                <?php if ($session['customer_email']): ?>
                    <div style="margin-top: var(--space-xl); padding: var(--space-md); background: var(--bg-glass); border-radius: var(--radius-md);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: var(--space-xs);">Müşteri</div>
                        <div style="font-size: 0.875rem;"><?= htmlspecialchars($session['customer_email']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Format card number
        document.getElementById('cardNumberInput').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
    </script>
</body>
</html>
