<?php
declare(strict_types=1);

/**
 * TurkPay - Virtual Bank & Payment Gateway
 * Main Entry Point
 */

// Custom autoloader (Composer-free)
require_once dirname(__DIR__) . '/autoload.php';

use App\Config\Environment;
use App\Config\Database;
use App\Core\Router;
use App\Core\Request;
use App\Core\Response;

// Load environment
$env = Environment::getInstance();

// Error handling
if ($env->isDebug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session configuration
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'secure' => $env->getBool('SESSION_SECURE', false),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// JSON content type for API routes
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_contains($uri, '/api/')) {
    header('Content-Type: application/json; charset=utf-8');
}

// Set base path for router
Router::setBasePath('/banka/public');

// ==========================================
// MIDDLEWARE REGISTRATION
// ==========================================

Router::middleware('auth', function() {
    if (empty($_SESSION['user_id'])) {
        if (str_contains($_SERVER['REQUEST_URI'], '/api/')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required']]);
            exit;
        }
        header('Location: /banka/public/login');
        exit;
    }
});

Router::middleware('guest', function() {
    if (!empty($_SESSION['user_id'])) {
        header('Location: /banka/public/dashboard');
        exit;
    }
});

Router::middleware('admin', function() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /banka/public/login');
        exit;
    }
    $user = \App\Models\User::find($_SESSION['user_id']);
    if (!$user || !$user->isAdmin()) {
        // For API requests, return JSON error
        if (str_contains($_SERVER['REQUEST_URI'], '/api/')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        // For web pages, redirect to dashboard with error message
        $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz yok. Admin hesabı ile giriş yapmanız gerekiyor.';
        header('Location: /banka/public/dashboard');
        exit;
    }
});

Router::middleware('merchant_api', function() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    $apiSecret = $_SERVER['HTTP_X_API_SECRET'] ?? null;
    
    if (!$apiKey || !$apiSecret) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_CREDENTIALS', 'message' => 'API key and secret required']]);
        exit;
    }
    
    $merchant = \App\Models\Merchant::authenticate($apiKey, $apiSecret);
    if (!$merchant) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Invalid API credentials']]);
        exit;
    }
    
    $_REQUEST['authenticated_merchant'] = $merchant;
});

// ==========================================
// PUBLIC ROUTES
// ==========================================

Router::get('/', function() {
    include dirname(__DIR__) . '/views/home.php';
});

Router::get('/login', function() {
    include dirname(__DIR__) . '/views/auth/login.php';
});

Router::post('/login', function() {
    include dirname(__DIR__) . '/views/auth/login.php';
});

Router::get('/register', function() {
    include dirname(__DIR__) . '/views/auth/register.php';
});

Router::post('/register', function() {
    include dirname(__DIR__) . '/views/auth/register.php';
});

Router::get('/logout', function() {
    session_destroy();
    header('Location: /banka/public/login');
    exit;
});

// Checkout page (for payment gateway)
Router::get('/checkout/{token}', function($token) {
    $_GET['token'] = $token;
    include dirname(__DIR__) . '/views/checkout/index.php';
});

Router::post('/checkout/{token}', function($token) {
    $_POST['token'] = $token;
    include dirname(__DIR__) . '/views/checkout/process.php';
});

Router::get('/checkout/{token}/3d', function($token) {
    $_GET['token'] = $token;
    include dirname(__DIR__) . '/views/checkout/3dsecure.php';
});

Router::post('/checkout/{token}/3d', function($token) {
    $_POST['token'] = $token;
    include dirname(__DIR__) . '/views/checkout/verify3d.php';
});

// ==========================================
// USER PROTECTED ROUTES
// ==========================================

Router::group(['middleware' => 'auth'], function() {
    Router::get('/dashboard', function() {
        include dirname(__DIR__) . '/views/user/dashboard.php';
    });
    
    Router::get('/wallets', function() {
        include dirname(__DIR__) . '/views/user/wallets.php';
    });
    
    Router::get('/transfer', function() {
        include dirname(__DIR__) . '/views/user/transfer.php';
    });
    
    Router::post('/transfer', function() {
        include dirname(__DIR__) . '/views/user/transfer.php';
    });
    
    Router::get('/cards', function() {
        include dirname(__DIR__) . '/views/user/cards.php';
    });
    
    Router::post('/cards', function() {
        include dirname(__DIR__) . '/views/user/cards.php';
    });
    
    Router::post('/cards/create', function() {
        include dirname(__DIR__) . '/views/user/create-card.php';
    });
    
    Router::get('/transactions', function() {
        include dirname(__DIR__) . '/views/user/transactions.php';
    });
    
    Router::get('/profile', function() {
        include dirname(__DIR__) . '/views/user/profile.php';
    });
    
    Router::post('/profile', function() {
        include dirname(__DIR__) . '/views/user/profile.php';
    });
    
    Router::get('/exchange', function() {
        include dirname(__DIR__) . '/views/user/exchange.php';
    });
    
    Router::post('/exchange', function() {
        include dirname(__DIR__) . '/views/user/exchange.php';
    });
});

// ==========================================
// MERCHANT ROUTES
// ==========================================

Router::group(['prefix' => 'merchant', 'middleware' => 'auth'], function() {
    Router::get('/dashboard', function() {
        include dirname(__DIR__) . '/views/merchant/dashboard.php';
    });
    
    Router::get('/register', function() {
        include dirname(__DIR__) . '/views/merchant/register.php';
    });
    
    Router::post('/register', function() {
        include dirname(__DIR__) . '/views/merchant/register.php';
    });
    
    Router::get('/api-keys', function() {
        include dirname(__DIR__) . '/views/merchant/api-keys.php';
    });
    
    Router::post('/api-keys/regenerate', function() {
        include dirname(__DIR__) . '/views/merchant/regenerate-keys.php';
    });
    
    Router::get('/transactions', function() {
        include dirname(__DIR__) . '/views/merchant/transactions.php';
    });
    
    Router::get('/webhooks', function() {
        include dirname(__DIR__) . '/views/merchant/webhooks.php';
    });
    
    Router::get('/integration', function() {
        include dirname(__DIR__) . '/views/merchant/integration.php';
    });
});

// ==========================================
// ADMIN ROUTES
// ==========================================

Router::group(['prefix' => 'admin', 'middleware' => 'admin'], function() {
    Router::get('/dashboard', function() {
        include dirname(__DIR__) . '/views/admin/dashboard.php';
    });
    
    Router::get('/users', function() {
        include dirname(__DIR__) . '/views/admin/users.php';
    });
    
    Router::get('/approvals', function() {
        include dirname(__DIR__) . '/views/admin/approvals.php';
    });
    
    Router::post('/approvals', function() {
        include dirname(__DIR__) . '/views/admin/approvals.php';
    });
    
    Router::post('/approvals/{id}/approve', function($id) {
        include dirname(__DIR__) . '/views/admin/approve.php';
    });
    
    Router::post('/approvals/{id}/reject', function($id) {
        include dirname(__DIR__) . '/views/admin/reject.php';
    });
    
    Router::get('/audit-logs', function() {
        include dirname(__DIR__) . '/views/admin/audit-logs.php';
    });
    
    Router::get('/queue', function() {
        include dirname(__DIR__) . '/views/admin/queue.php';
    });
    
    Router::get('/merchants', function() {
        include dirname(__DIR__) . '/views/admin/merchants.php';
    });
    
    Router::get('/activities', function() {
        include dirname(__DIR__) . '/views/admin/activities.php';
    });
});

// ==========================================
// API ROUTES v1
// ==========================================

Router::group(['prefix' => 'api/v1'], function() {
    // Public API endpoints
    Router::get('/health', function() {
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
    });
    
    Router::get('/exchange-rates', function() {
        $service = new \App\Services\ExchangeRateService();
        echo json_encode(['success' => true, 'data' => $service->getAllRates()]);
    });
    
    // User balance API (requires auth)
    Router::group(['middleware' => 'auth'], function() {
        Router::get('/user/balance', function() {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $wallets = $user->wallets();
            $balances = [];
            foreach ($wallets as $wallet) {
                $balances[] = [
                    'id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'balance' => (float) $wallet->balance,
                    'formatted' => $wallet->getFormattedBalance()
                ];
            }
            echo json_encode(['success' => true, 'data' => $balances, 'timestamp' => time()]);
        });
        
        Router::get('/user/transactions/recent', function() {
            $user = \App\Models\User::find($_SESSION['user_id']);
            $transactions = $user->transactions(5);
            $data = [];
            foreach ($transactions as $tx) {
                $data[] = [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                    'currency' => $tx->currency,
                    'status' => $tx->status,
                    'created_at' => $tx->created_at
                ];
            }
            echo json_encode(['success' => true, 'data' => $data]);
        });
    });
    
    // Merchant API endpoints
    Router::group(['middleware' => 'merchant_api'], function() {
        Router::post('/payments/initiate', function() {
            include dirname(__DIR__) . '/api/v1/payments/initiate.php';
        });
        
        Router::get('/payments/status/{token}', function($token) {
            include dirname(__DIR__) . '/api/v1/payments/status.php';
        });
        
        Router::post('/payments/refund', function() {
            include dirname(__DIR__) . '/api/v1/payments/refund.php';
        });
        
        Router::get('/transactions', function() {
            include dirname(__DIR__) . '/api/v1/merchant/transactions.php';
        });
        
        Router::get('/balance', function() {
            include dirname(__DIR__) . '/api/v1/merchant/balance.php';
        });
    });
});

// ==========================================
// DEMO ROUTES
// ==========================================

Router::get('/demo/shop', function() {
    include dirname(__DIR__) . '/views/demo/shop.php';
});

Router::post('/demo/shop/checkout', function() {
    include dirname(__DIR__) . '/views/demo/checkout.php';
});

Router::get('/demo/webhook-test', function() {
    include dirname(__DIR__) . '/views/demo/webhook-test.php';
});

// ==========================================
// API DOCS (Swagger)
// ==========================================

Router::get('/api/docs', function() {
    include dirname(__DIR__) . '/public/docs/index.html';
});

// Dispatch!
Router::dispatch();
