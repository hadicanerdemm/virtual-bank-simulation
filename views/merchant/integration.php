<?php
/**
 * Merchant Integration Guide
 */

$pageTitle = 'Entegrasyon Rehberi';
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

$baseUrl = 'http://localhost/banka/public';
?>

<div class="page-header">
    <h1 class="page-title">Entegrasyon Rehberi</h1>
    <p class="page-subtitle">TurkPay ödeme API'sini sitenize entegre edin.</p>
</div>

<div class="card mb-xl">
    <h2 class="card-title"><i class="fas fa-rocket"></i> Hızlı Başlangıç</h2>
    
    <h3>1. Ödeme Oturumu Oluşturma</h3>
    <pre class="code-block"><code>POST <?= $baseUrl ?>/api/v1/payments/create

Headers:
X-API-Key: <?= htmlspecialchars($merchant->api_key) ?>
X-API-Secret: YOUR_SECRET_KEY
Content-Type: application/json

Body:
{
    "amount": 100.00,
    "currency": "TRY",
    "order_id": "ORD-12345",
    "customer_email": "musteri@ornek.com",
    "return_url": "https://siteniz.com/odeme/basarili",
    "cancel_url": "https://siteniz.com/odeme/iptal"
}</code></pre>
    
    <h3>2. Müşteriyi Ödeme Sayfasına Yönlendirin</h3>
    <pre class="code-block"><code>// API'den dönen cevap:
{
    "success": true,
    "data": {
        "session_id": "ps_abc123...",
        "checkout_url": "<?= $baseUrl ?>/checkout/TOKEN",
        "expires_at": "2024-01-01T12:00:00Z"
    }
}

// checkout_url'i müşteriye yönlendirin</code></pre>

    <h3>3. Webhook ile Sonucu Alın</h3>
    <pre class="code-block"><code>// Webhook endpoint'inize gelen POST isteği:
{
    "event": "payment.completed",
    "data": {
        "transaction_id": "TRX2024...",
        "order_id": "ORD-12345",
        "amount": 100.00,
        "status": "completed"
    },
    "signature": "sha256_hmac..."
}</code></pre>
</div>

<div class="card mb-xl">
    <h2 class="card-title"><i class="fas fa-code"></i> PHP Örneği</h2>
    
    <pre class="code-block"><code>&lt;?php
$apiKey = '<?= htmlspecialchars($merchant->api_key) ?>';
$apiSecret = 'YOUR_SECRET_KEY';

$data = [
    'amount' => 150.00,
    'currency' => 'TRY',
    'order_id' => 'ORDER-' . time(),
    'customer_email' => $_POST['email'],
    'return_url' => 'https://siteniz.com/odeme/tamamlandi',
    'cancel_url' => 'https://siteniz.com/odeme/iptal'
];

$ch = curl_init('<?= $baseUrl ?>/api/v1/payments/create');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
        'X-API-Secret: ' . $apiSecret
    ]
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($response['success']) {
    header('Location: ' . $response['data']['checkout_url']);
    exit;
} else {
    echo 'Hata: ' . $response['error']['message'];
}</code></pre>
</div>

<div class="card">
    <h2 class="card-title"><i class="fas fa-shield-alt"></i> Webhook Doğrulama</h2>
    
    <pre class="code-block"><code>&lt;?php
$webhookSecret = 'YOUR_WEBHOOK_SECRET';
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_TURKPAY_SIGNATURE'] ?? '';

$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

if (hash_equals($expectedSignature, $signature)) {
    $event = json_decode($payload, true);
    
    switch ($event['event']) {
        case 'payment.completed':
            // Siparişi tamamla
            break;
        case 'payment.failed':
            // Siparişi iptal et
            break;
    }
    
    http_response_code(200);
} else {
    http_response_code(401);
    echo 'Invalid signature';
}</code></pre>
</div>

<style>
.code-block {
    background: #1a1a2e;
    border-radius: var(--radius-md);
    padding: var(--space-lg);
    overflow-x: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: #e0e0e0;
    margin: var(--space-lg) 0;
}
</style>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
