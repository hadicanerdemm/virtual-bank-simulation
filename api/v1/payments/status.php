<?php
/**
 * API: Get Payment Status
 * GET /api/v1/payments/status/{token}
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

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'MISSING_TOKEN', 'message' => 'Session token is required']
    ]);
    exit;
}

$gateway = new PaymentGateway();
$result = $gateway->getStatus($token);

if ($result['success']) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SESSION_NOT_FOUND',
            'message' => $result['error']
        ]
    ]);
}
