<?php
/**
 * Regenerate Merchant API Keys
 */

use App\Models\User;
use App\Models\Merchant;
use App\Config\Database;

$user = User::find($_SESSION['user_id']);
$merchants = Merchant::where('user_id', $user->id);
$merchant = $merchants[0] ?? null;

if (!$merchant) {
    header('Location: /banka/public/merchant/register');
    exit;
}

$db = Database::getInstance();

// Generate new keys
$newApiKey = 'pk_' . ($merchant->is_sandbox ? 'test_' : 'live_') . bin2hex(random_bytes(24));
$newApiSecret = 'sk_' . ($merchant->is_sandbox ? 'test_' : 'live_') . bin2hex(random_bytes(32));

// Update in database
$db->update('merchants', [
    'api_key' => $newApiKey,
    'api_secret' => $newApiSecret
], 'id = ?', [$merchant->id]);

// Redirect back with success message
$_SESSION['api_keys_regenerated'] = true;
$_SESSION['new_api_key'] = $newApiKey;
$_SESSION['new_api_secret'] = $newApiSecret;

header('Location: /banka/public/merchant/api-keys');
exit;
