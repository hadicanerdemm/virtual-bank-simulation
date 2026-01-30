<?php
/**
 * API: Refund Payment
 * POST /api/v1/payments/refund
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

if (empty($input['transaction_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'MISSING_TRANSACTION_ID', 'message' => 'Transaction ID is required']
    ]);
    exit;
}

$gateway = new PaymentGateway();
$result = $gateway->refund(
    $input['transaction_id'],
    isset($input['amount']) ? (float) $input['amount'] : null,
    $input['reason'] ?? ''
);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'data' => $result['data']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'REFUND_FAILED',
            'message' => $result['error']
        ]
    ]);
}
