<?php
/**
 * Advance Deduction Verifier API
 * Endpoint to verify and fix advance deduction processing
 */

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/AdvanceDeductionVerifier.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if (empty($action)) {
        throw new Exception('Action parameter is required');
    }

    $verifier = new \Helpers\AdvanceDeductionVerifier();

    switch ($action) {
        case 'verify_salary_record':
            $salaryRecordId = (int)($_GET['salary_record_id'] ?? 0);
            if ($salaryRecordId <= 0) {
                throw new Exception('Valid salary record ID is required');
            }
            $result = $verifier->verifySalaryRecordDeductions($salaryRecordId);
            break;

        case 'get_advance_history':
            $userId = (int)($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Valid user ID is required');
            }
            $result = $verifier->getAdvancePaymentHistory($userId);
            break;

        case 'check_month':
            $month = (int)($_GET['month'] ?? 0);
            $year = (int)($_GET['year'] ?? 0);
            if ($month < 1 || $month > 12 || $year < 2020) {
                throw new Exception('Valid month and year are required');
            }
            $result = $verifier->checkMonthAdvanceDeductions($month, $year);
            break;

        case 'fix_missing_transactions':
            $salaryRecordId = (int)($_POST['salary_record_id'] ?? 0);
            if ($salaryRecordId <= 0) {
                throw new Exception('Valid salary record ID is required');
            }
            $result = $verifier->fixMissingTransactions($salaryRecordId);
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

