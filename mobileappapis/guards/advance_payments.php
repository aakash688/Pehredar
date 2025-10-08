<?php
// mobileappapis/guards/advance_payments.php
// Guard-facing API: view advance balance and request a new advance

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

require_once '../../vendor/autoload.php';
require_once '../../config.php';
require_once __DIR__ . '/../shared/optimized_guard_helper.php';
require_once __DIR__ . '/../../helpers/database.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $config = require '../../config.php';
    $jwt = getOptimizedBearerToken();
    if (!$jwt) { sendOptimizedGuardError('Unauthorized', 401); }
    $decoded = JWT::decode($jwt, new Key($config['jwt']['secret'], 'HS256'));
    $userId = (int)($decoded->data->id ?? 0);
    $userType = $decoded->data->user_type ?? $decoded->data->role ?? null;
    if ($userId <= 0 || !in_array($userType, ['Guard','guard'], true)) { sendOptimizedGuardError('Forbidden', 403); }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = ConnectionPool::getConnection();

    if ($method === 'GET') {
        // Return summary and recent advances for the guard
        // Outstanding balance from new system
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_balance),0) FROM advance_payments WHERE employee_id = ? AND status IN ('active','approved')");
        $stmt->execute([$userId]);
        $outstanding = (float)$stmt->fetchColumn();

        // Use enhanced advance system for better skip handling
        require_once '../../helpers/AdvanceSkipSystemEnhanced.php';
        $skipSystem = new \Helpers\AdvanceSkipSystemEnhanced();
        $advances = $skipSystem->getAdvanceDetailsForMobile($userId);
        
        // Ensure numeric fields are properly typed
        foreach ($advances as &$advance) {
            $advance['amount'] = (float)$advance['amount'];
            $advance['monthly_deduction'] = (float)$advance['monthly_deduction'];
            $advance['remaining_balance'] = (float)$advance['remaining_balance'];
            $advance['installment_count'] = (int)$advance['installment_count'];
            $advance['paid_installments'] = (int)$advance['paid_installments'];
            $advance['total_skips'] = (int)$advance['total_skips'];
            $advance['progress_percentage'] = (float)$advance['progress_percentage'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'outstanding_balance' => $outstanding,
                'advances' => $advances
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        // Create a new advance request
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $amount = (float)($input['amount'] ?? 0);
        $purpose = trim((string)($input['purpose'] ?? ''));
        $installments = max(1, min(12, (int)($input['installment_count'] ?? 1)));
        $priority = $input['priority'] ?? 'normal'; // low|normal|high|urgent
        $isEmergency = !empty($input['is_emergency']) ? 1 : 0;

        if ($amount <= 0) { sendOptimizedGuardError('Invalid amount', 400); }
        if ($purpose === '') { sendOptimizedGuardError('Purpose is required', 400); }
        if ($installments < 1 || $installments > 12) { sendOptimizedGuardError('Installments must be between 1 and 12 months', 400); }
        if (!in_array($priority, ['low','normal','high','urgent'], true)) { sendOptimizedGuardError('Invalid priority', 400); }

        // Ensure no active/approved advance
        $check = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ? AND status IN ('active','approved','pending')");
        $check->execute([$userId]);
        if ((int)$check->fetchColumn() > 0) {
            sendOptimizedGuardError('You already have an ongoing or pending advance request', 409);
        }

        // Calculate monthly deduction
        $monthly = round($amount / $installments, 2);

        // Create request number
        $requestNumber = 'AP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmtRN = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE request_number = ?");
        while (true) {
            $stmtRN->execute([$requestNumber]);
            if ((int)$stmtRN->fetchColumn() === 0) break;
            $requestNumber = 'AP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Insert
        $ins = $pdo->prepare("INSERT INTO advance_payments (employee_id, request_number, amount, purpose, priority, is_emergency, monthly_deduction, remaining_balance, installment_count, requested_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $ins->execute([$userId, $requestNumber, $amount, $purpose, $priority, $isEmergency, $monthly, $amount, $installments, $userId]);

        echo json_encode(['success' => true, 'message' => 'Advance request submitted', 'request_number' => $requestNumber]);
        exit;
    }

    sendOptimizedGuardError('Method not allowed', 405);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'details' => $e->getMessage()]);
}
?>


