<?php
/**
 * Demo Shop Checkout Handler
 * Creates a payment session and redirects to TurkPay checkout
 */

use App\Config\Database;
use App\Services\PaymentGateway;
use App\Models\Merchant;

$productName = $_POST['product_name'] ?? '';
$amount = (float) ($_POST['amount'] ?? 0);
$email = $_POST['email'] ?? '';

if (empty($productName) || $amount <= 0) {
    header('Location: /banka/public/demo/shop');
    exit;
}

// Get demo merchant (or create one)
$db = Database::getInstance();
$demoMerchant = $db->fetchOne("SELECT * FROM merchants WHERE business_name = 'TechShop Demo'");

if (!$demoMerchant) {
    // Create demo merchant
    $merchantId = Database::generateUUID();
    $apiKey = Merchant::generateApiKey();
    $apiSecret = Merchant::generateApiSecret();
    
    $db->query(
        "INSERT INTO merchants (id, user_id, business_name, api_key, api_secret, webhook_url, status, created_at, updated_at) 
         VALUES (?, (SELECT id FROM users WHERE role = 'super_admin' LIMIT 1), 'TechShop Demo', ?, ?, ?, 'active', NOW(), NOW())",
        [$merchantId, $apiKey, password_hash($apiSecret, PASSWORD_BCRYPT), 'http://localhost/banka/public/demo/webhook-test']
    );
    
    $demoMerchant = $db->fetchOne("SELECT * FROM merchants WHERE id = ?", [$merchantId]);
}

$merchant = new Merchant($demoMerchant);

// Create payment session
$gateway = new PaymentGateway();
$orderId = 'DEMO-' . strtoupper(bin2hex(random_bytes(4)));

$result = $gateway->initiate(
    $merchant,
    $amount,
    'TRY',
    $orderId,
    'http://localhost/banka/public/demo/shop?success=1&order=' . $orderId,
    'http://localhost/banka/public/demo/shop?cancelled=1',
    null,
    ['email' => $email, 'name' => 'Demo Müşteri']
);

if ($result['success']) {
    header('Location: ' . $result['data']['checkout_url']);
    exit;
} else {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hata - TechShop</title>
        <link rel="stylesheet" href="/banka/public/assets/css/style.css">
    </head>
    <body>
        <div class="auth-container">
            <div class="card text-center" style="max-width: 400px; padding: var(--space-2xl);">
                <div style="font-size: 4rem; margin-bottom: var(--space-lg);">❌</div>
                <h1 style="font-size: 1.5rem; margin-bottom: var(--space-md);">Ödeme Başlatılamadı</h1>
                <p class="text-muted mb-lg"><?= htmlspecialchars($result['error'] ?? 'Bilinmeyen hata') ?></p>
                <a href="/banka/public/demo/shop" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Mağazaya Dön
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
