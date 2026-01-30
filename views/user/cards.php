<?php
/**
 * Virtual Cards Page
 */

$pageTitle = 'Sanal Kartlar';
include dirname(__DIR__) . '/layouts/header.php';

use App\Models\User;
use App\Models\Wallet;
use App\Models\VirtualCard;

$user = User::find($_SESSION['user_id']);
$cards = $user->virtualCards();
$wallets = $user->wallets();
$success = '';
$error = '';

// Handle new card creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_card'])) {
    $walletId = $_POST['wallet_id'] ?? '';
    $cardType = $_POST['card_type'] ?? 'debit';
    $label = trim($_POST['label'] ?? '');
    
    $wallet = Wallet::find($walletId);
    if (!$wallet || $wallet->user_id !== $user->id) {
        $error = 'Geçersiz cüzdan.';
    } else {
        try {
            $card = VirtualCard::createCard($user->id, $walletId, $user->getFullName(), 'visa');
            $success = 'Sanal kart başarıyla oluşturuldu!';
            $cards = $user->virtualCards(); // Refresh
        } catch (\Exception $e) {
            $error = 'Kart oluşturulamadı: ' . $e->getMessage();
        }
    }
}

// Handle card toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_card'])) {
    $cardId = $_POST['card_id'] ?? '';
    $card = VirtualCard::find($cardId);
    if ($card) {
        $wallet = Wallet::find($card->wallet_id);
        if ($wallet && $wallet->user_id === $user->id) {
            if ($card->status === 'active') {
                $card->status = 'frozen';
            } else {
                $card->status = 'active';
            }
            $card->save();
            $cards = $user->virtualCards(); // Refresh
        }
    }
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Sanal Kartlar</h1>
            <p class="page-subtitle">Online ödemeler için güvenli sanal kartlarınız</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('createCardModal').classList.add('active')">
            <i class="fas fa-plus"></i>
            Yeni Kart Oluştur
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if (empty($cards)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">💳</div>
            <h3 class="empty-state-title">Henüz sanal kartınız yok</h3>
            <p class="empty-state-text">Online alışverişleriniz için güvenli sanal kart oluşturun.</p>
            <button class="btn btn-primary" onclick="document.getElementById('createCardModal').classList.add('active')">
                <i class="fas fa-plus"></i>
                İlk Kartımı Oluştur
            </button>
        </div>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: var(--space-xl);">
        <?php foreach ($cards as $card): ?>
            <?php 
            $wallet = Wallet::find($card->wallet_id);
            $isVisa = str_starts_with($card->card_number, '4');
            ?>
            <div class="card">
                <!-- Card Visual -->
                <div class="virtual-card <?= $isVisa ? 'visa' : 'mastercard' ?>" style="width: 100%; height: auto; aspect-ratio: 1.6; margin-bottom: var(--space-lg);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div class="card-chip"></div>
                        <?php if ($card->status === 'frozen'): ?>
                            <span class="badge badge-danger">Dondurulmuş</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-number"><?= $card->getFormattedNumber() ?></div>
                    <div class="card-info">
                        <div>
                            <div class="card-holder">Kart Sahibi</div>
                            <div class="card-holder-name"><?= strtoupper($user->getFullName()) ?></div>
                        </div>
                        <div>
                            <div class="card-expiry-label">Valid Thru</div>
                            <div class="card-expiry"><?= $card->expiry_month ?>/<?= $card->expiry_year ?></div>
                        </div>
                    </div>
                    <div class="card-type-logo"><?= $isVisa ? 'VISA' : 'MC' ?></div>
                </div>
                
                <!-- Card Details -->
                <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Etiket</span>
                        <span><?= htmlspecialchars($card->label ?: 'Ana Kart') ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Bağlı Cüzdan</span>
                        <span><?= $wallet->currency ?> - <?= $wallet->getFormattedBalance() ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Kart Tipi</span>
                        <span class="badge <?= $card->type === 'credit' ? 'badge-info' : 'badge-secondary' ?>">
                            <?= $card->type === 'credit' ? 'Kredi' : 'Banka' ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Günlük Limit</span>
                        <span><?= $wallet->getCurrencySymbol() . number_format((float) $card->daily_limit, 0) ?></span>
                    </div>
                </div>
                
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: var(--space-lg) 0;">
                
                <!-- Card Actions -->
                <div style="display: flex; gap: var(--space-md);">
                    <button class="btn btn-ghost btn-sm w-full" onclick="showCardDetails('<?= $card->id ?>', '<?= $card->card_number ?>', '<?= $card->getCVV() ?>')">
                        <i class="fas fa-eye"></i>
                        Detaylar
                    </button>
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="card_id" value="<?= $card->id ?>">
                        <button type="submit" name="toggle_card" class="btn <?= $card->status === 'active' ? 'btn-secondary' : 'btn-success' ?> btn-sm w-full">
                            <i class="fas fa-<?= $card->status === 'active' ? 'snowflake' : 'play' ?>"></i>
                            <?= $card->status === 'active' ? 'Dondur' : 'Aktif Et' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Card Modal -->
<div class="modal-overlay" id="createCardModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Yeni Sanal Kart</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Bağlı Cüzdan</label>
                    <select name="wallet_id" class="form-select" required>
                        <option value="">Cüzdan seçin...</option>
                        <?php foreach ($wallets as $wallet): ?>
                            <option value="<?= $wallet->id ?>">
                                <?= $wallet->currency ?> - <?= $wallet->getFormattedBalance() ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kart Tipi</label>
                    <select name="card_type" class="form-select" required>
                        <option value="debit">Banka Kartı</option>
                        <option value="credit">Kredi Kartı</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Etiket (Opsiyonel)</label>
                    <input type="text" name="label" class="form-input" placeholder="örn: Alışveriş Kartı" maxlength="50">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').classList.remove('active')">
                    İptal
                </button>
                <button type="submit" name="create_card" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Kart Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Card Details Modal -->
<div class="modal-overlay" id="cardDetailsModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Kart Detayları</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Bu bilgileri kimseyle paylaşmayın!</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: var(--space-lg);">
                <div>
                    <label class="form-label">Kart Numarası</label>
                    <div style="display: flex; align-items: center; gap: var(--space-md);">
                        <input type="text" id="fullCardNumber" class="form-input font-mono" readonly style="flex: 1;">
                        <button class="btn btn-ghost btn-icon" onclick="copyToClipboard(document.getElementById('fullCardNumber').value)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="form-label">CVV</label>
                    <div style="display: flex; align-items: center; gap: var(--space-md);">
                        <input type="text" id="cardCVV" class="form-input font-mono" readonly style="width: 100px;">
                        <button class="btn btn-ghost btn-icon" onclick="copyToClipboard(document.getElementById('cardCVV').value)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="this.closest('.modal-overlay').classList.remove('active')">
                Tamam
            </button>
        </div>
    </div>
</div>

<script>
    function showCardDetails(cardId, cardNumber, cvv) {
        document.getElementById('fullCardNumber').value = cardNumber.replace(/(\d{4})/g, '$1 ').trim();
        document.getElementById('cardCVV').value = cvv;
        document.getElementById('cardDetailsModal').classList.add('active');
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text.replace(/\s/g, '')).then(() => {
            showToast('Kopyalandı!', 'success');
        });
    }
</script>

<?php include dirname(__DIR__) . '/layouts/footer.php'; ?>
