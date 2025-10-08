<?php
/**
 * Advance Skip Request Controller
 * Handles skip request operations via API
 */

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/AdvanceSkipManager.php';

class AdvanceSkipController {
    private $db;
    private $skipManager;

    public function __construct() {
        $this->db = new Database();
        $this->skipManager = new \Helpers\AdvanceSkipManager();
    }

    /**
     * Handle different skip request operations
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        switch ($action) {
            case 'request_skip':
                return $this->requestSkip();
            case 'approve_skip':
                return $this->approveSkip();
            case 'reject_skip':
                return $this->rejectSkip();
            case 'get_skip_requests':
                return $this->getSkipRequests();
            case 'get_pending_requests':
                return $this->getPendingRequests();
            case 'check_skip_status':
                return $this->checkSkipStatus();
            default:
                return $this->errorResponse('Invalid action');
        }
    }

    /**
     * Request to skip deduction for a month
     */
    private function requestSkip() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }

            $required = ['advance_id', 'skip_month', 'reason'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return $this->errorResponse("Missing required field: $field");
                }
            }

            $advanceId = (int)$input['advance_id'];
            $skipMonth = $input['skip_month'];
            $reason = $input['reason'];
            $requestedBy = $_SESSION['user_id'] ?? 1;

            // Validate skip month format (YYYY-MM)
            if (!preg_match('/^\d{4}-\d{2}$/', $skipMonth)) {
                return $this->errorResponse('Invalid month format. Use YYYY-MM');
            }

            // Validate that skip month is not in the past
            $currentMonth = date('Y-m');
            if ($skipMonth < $currentMonth) {
                return $this->errorResponse('Cannot skip deductions for past months');
            }

            $result = $this->skipManager->requestSkipDeduction($advanceId, $skipMonth, $reason, $requestedBy);
            
            return $this->jsonResponse($result);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Approve skip request
     */
    private function approveSkip() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }

            if (empty($input['skip_request_id'])) {
                return $this->errorResponse('Skip request ID is required');
            }

            $skipRequestId = (int)$input['skip_request_id'];
            $notes = $input['notes'] ?? '';
            $approvedBy = $_SESSION['user_id'] ?? 1;

            $result = $this->skipManager->approveSkipRequest($skipRequestId, $approvedBy, $notes);
            
            // Update monthly deduction after approval
            if ($result['success']) {
                $skipRequest = $this->db->query("
                    SELECT advance_payment_id FROM advance_skip_requests WHERE id = ?
                ", [$skipRequestId])->fetch();
                
                if ($skipRequest) {
                    $this->skipManager->updateMonthlyDeduction($skipRequest['advance_payment_id']);
                }
            }
            
            return $this->jsonResponse($result);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Reject skip request
     */
    private function rejectSkip() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }

            if (empty($input['skip_request_id']) || empty($input['reason'])) {
                return $this->errorResponse('Skip request ID and rejection reason are required');
            }

            $skipRequestId = (int)$input['skip_request_id'];
            $reason = $input['reason'];
            $rejectedBy = $_SESSION['user_id'] ?? 1;

            $result = $this->skipManager->rejectSkipRequest($skipRequestId, $rejectedBy, $reason);
            
            return $this->jsonResponse($result);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get skip requests for a specific advance
     */
    private function getSkipRequests() {
        try {
            $advanceId = (int)($_GET['advance_id'] ?? 0);
            
            if ($advanceId <= 0) {
                return $this->errorResponse('Valid advance ID is required');
            }

            $requests = $this->skipManager->getAdvanceSkipRequests($advanceId);
            
            return $this->jsonResponse([
                'success' => true,
                'requests' => $requests
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get all pending skip requests for admin
     */
    private function getPendingRequests() {
        try {
            $requests = $this->skipManager->getPendingSkipRequests();
            
            return $this->jsonResponse([
                'success' => true,
                'requests' => $requests
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Check if deduction should be skipped for a month
     */
    private function checkSkipStatus() {
        try {
            $advanceId = (int)($_GET['advance_id'] ?? 0);
            $salaryMonth = $_GET['salary_month'] ?? date('Y-m');
            
            if ($advanceId <= 0) {
                return $this->errorResponse('Valid advance ID is required');
            }

            $shouldSkip = $this->skipManager->shouldSkipDeduction($advanceId, $salaryMonth);
            
            return $this->jsonResponse([
                'success' => true,
                'should_skip' => $shouldSkip,
                'advance_id' => $advanceId,
                'salary_month' => $salaryMonth
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Send JSON response
     */
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function errorResponse($message) {
        return $this->jsonResponse([
            'success' => false,
            'message' => $message
        ]);
    }
}

// Handle the request if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'advance_skip_controller.php') {
    $controller = new AdvanceSkipController();
    $controller->handleRequest();
}
?>

