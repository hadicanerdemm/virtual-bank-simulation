<?php
/**
 * API: Initiate Payment
 * POST /api/v1/payments/initiate
 */

use App\Services\PaymentGateway;

header('Content-Type: application/json');

$merchant = $_REQUEST['authenticated_merchant'] ?? null;

if (!$merchant) {
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Merchant authentication required']
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['amount', 'currency', 'order_id', 'return_url'];
$errors = [];

foreach ($required as $field) {
    if (empty($input[$field])) {
        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Missing required fields',
            'details' => $errors
        ]
    ]);
    exit;
}

// Validate amount
$amount = (float) $input['amount'];
if ($amount <= 0) {
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INVALID_AMOUNT', 'message' => 'Amount must be greater than 0']
    ]);
    exit;
}

// Validate currency
$validCurrencies = ['TRY', 'USD', 'EUR'];
if (!in_array(strtoupper($input['currency']), $validCurrencies)) {
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INVALID_CURRENCY', 'message' => 'Currency must be one of: ' . implode(', ', $validCurrencies)]
    ]);
    exit;
}

// Initialize payment
$gateway = new PaymentGateway();
$result = $gateway->initiate(
    $merchant,
    $amount,
    strtoupper($input['currency']),
    $input['order_id'],
    $input['return_url'],
    $input['cancel_url'] ?? null,
    $input['callback_url'] ?? null,
    [
        'email' => $input['customer_email'] ?? null,
        'name' => $input['customer_name'] ?? null
    ]
);

if ($result['success']) {
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => $result['data']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'PAYMENT_INITIATION_FAILED',
            'message' => $result['error']
        ]
    ]);
}
