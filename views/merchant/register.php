<?php
/**
 * Merchant Registration Page
 */

$pageTitle = 'İşletme Başvurusu';
include dirname(dirname(__DIR__)) . '/views/layouts/header.php';

use App\Models\User;
use App\Models\Merchant;

$user = User::find($_SESSION['user_id']);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $businessType = $_POST['business_type'] ?? 'individual';
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($businessName)) {
        $error = 'İşletme adı zorunludur.';
    } else {
        try {
            $apiKey = 'pk_' . ($businessType === 'company' ? 'live' : 'test') . '_' . bin2hex(random_bytes(16));
            $apiSecret = 'sk_' . ($businessType === 'company' ? 'live' : 'test') . '_' . bin2hex(random_bytes(24));
            
            Merchant::create([
                'user_id' => $user->id,
                'business_name' => $businessName,
                'business_type' => $businessType,
                'website' => $website ?: null,
                'description' => $description ?: null,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'is_sandbox' => 1,
                'status' => 'pending'
            ]);
            
            $success = 'İşletme başvurunuz alındı! Onaylandıktan sonra API anahtarlarınıza erişebileceksiniz.';
            
        } catch (\Exception $e) {
            $error = 'Başvuru sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">İşletme Başvurusu</h1>
    <p class="page-subtitle">TurkPay ödeme sistemini web sitenize veya uygulamanıza entegre edin.</p>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
    <div class="card">
        <h2 class="card-title" style="margin-bottom: var(--space-xl);">
            <i class="fas fa-store"></i> Başvuru Formu
        </h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger mb-lg">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success mb-lg">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php else: ?>
            <form method="POST" action="/banka/public/merchant/register">
                <div class="form-group">
                    <label class="form-label">İşletme Adı *</label>
                    <input type="text" name="business_name" class="form-input" placeholder="Örn: Demo E-Ticaret Mağazası" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">İşletme Tipi</label>
                    <select name="business_type" class="form-input">
                        <option value="individual">Bireysel / Şahıs Şirketi</option>
                        <option value="company">Tüzel Kişi (Şirket)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Web Sitesi</label>
                    <input type="url" name="website" class="form-input" placeholder="https://ornek.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Açıklama</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="İşletmeniz hakkında kısa bilgi"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Başvuruyu Gönder
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h3 class="card-title" style="margin-bottom: var(--space-lg);">
            <i class="fas fa-gift"></i> Avantajlar
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: var(--space-md);">
            <div style="display: flex; gap: var(--space-md); align-items: start;">
                <i class="fas fa-check-circle text-success"></i>
                <span>Kolay API entegrasyonu</span>
            </div>
            <div style="display: flex; gap: var(--space-md); align-items: start;">
                <i class="fas fa-check-circle text-success"></i>
                <span>3D Secure desteği</span>
            </div>
            <div style="display: flex; gap: var(--space-md); align-items: start;">
                <i class="fas fa-check-circle text-success"></i>
                <span>Webhook bildirimleri</span>
            </div>
            <div style="display: flex; gap: var(--space-md); align-items: start;">
                <i class="fas fa-check-circle text-success"></i>
                <span>Gerçek zamanlı raporlama</span>
            </div>
            <div style="display: flex; gap: var(--space-md); align-items: start;">
                <i class="fas fa-check-circle text-success"></i>
                <span>%2.5 komisyon oranı</span>
            </div>
        </div>
    </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/views/layouts/footer.php'; ?>
