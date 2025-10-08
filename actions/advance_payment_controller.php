<?php
/**
 * Advance Payment Management Controller
 * Brand new implementation with proper CRUD operations
 */

require_once __DIR__ . '/../helpers/database.php';

class AdvancePaymentController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * List all advance payments with search and pagination
     */
    public function listAdvancePayments($search = '', $page = 1, $perPage = 10, array $filters = []) {
        try {
            $offset = ($page - 1) * $perPage;
            
            // Build WHERE clause
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (ap.request_number LIKE ? OR u.first_name LIKE ? OR u.surname LIKE ? OR ap.purpose LIKE ? OR ap.amount LIKE ?)";
                $searchParam = "%$search%";
                $params = array_fill(0, 5, $searchParam);
            }

            // Normalize filters
            $normalizedFilters = [
                'status' => isset($filters['status']) ? trim((string)$filters['status']) : '',
                'priority' => isset($filters['priority']) ? trim((string)$filters['priority']) : (isset($filters['type']) ? trim((string)$filters['type']) : ''),
                'date_from' => isset($filters['date_from']) ? trim((string)$filters['date_from']) : '',
                'date_to' => isset($filters['date_to']) ? trim((string)$filters['date_to']) : '',
            ];

            if ($normalizedFilters['status'] !== '') {
                $whereClause .= " AND ap.status = ?";
                $params[] = $normalizedFilters['status'];
            }
            if ($normalizedFilters['priority'] !== '') {
                $whereClause .= " AND ap.priority = ?";
                $params[] = $normalizedFilters['priority'];
            }
            if ($normalizedFilters['date_from'] !== '') {
                $whereClause .= " AND DATE(ap.created_at) >= ?";
                $params[] = $normalizedFilters['date_from'];
            }
            if ($normalizedFilters['date_to'] !== '') {
                $whereClause .= " AND DATE(ap.created_at) <= ?";
                $params[] = $normalizedFilters['date_to'];
            }
            
            // Get total count
            $countQuery = "
                SELECT COUNT(*) as total 
                FROM advance_payments ap 
                JOIN users u ON ap.employee_id = u.id 
                $whereClause
            ";
            $totalResult = $this->db->query($countQuery, $params)->fetch();
            $totalRecords = $totalResult['total'];
            
            // Get paginated data with skip information
            $dataQuery = "
                SELECT 
                    ap.*,
                    CONCAT(u.first_name, ' ', u.surname) as employee_name,
                    u.user_type as employee_type,
                    u.salary as employee_salary,
                    requester.first_name as requested_by_name,
                    approver.first_name as approved_by_name,
                    DATEDIFF(CURDATE(), ap.start_date) as days_active,
                    ROUND(((ap.amount - ap.remaining_balance) / ap.amount) * 100, 1) as progress_percentage,
                    (SELECT COUNT(*) FROM advance_skip_records sr 
                     JOIN advance_skip_requests req ON sr.skip_request_id = req.id 
                     WHERE sr.advance_payment_id = ap.id AND req.status = 'approved') as total_skips,
                    COALESCE(ap.original_installment_count, ap.installment_count) as original_installment_count
                FROM advance_payments ap
                JOIN users u ON ap.employee_id = u.id
                LEFT JOIN users requester ON ap.requested_by = requester.id
                LEFT JOIN users approver ON ap.approved_by = approver.id
                $whereClause
                ORDER BY ap.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $perPage;
            $params[] = $offset;
            
            $payments = $this->db->query($dataQuery, $params)->fetchAll();
            
            // Get statistics
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_advances,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                    COUNT(CASE WHEN is_emergency = 1 THEN 1 END) as emergency_requests,
                    COALESCE(SUM(CASE WHEN status IN ('active', 'completed') THEN amount ELSE 0 END), 0) as total_amount_disbursed,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN remaining_balance ELSE 0 END), 0) as total_outstanding,
                    COALESCE(AVG(CASE WHEN status = 'active' THEN monthly_deduction ELSE NULL END), 0) as avg_monthly_deduction
                FROM advance_payments
            ";
            
            $stats = $this->db->query($statsQuery)->fetch();
            
            return [
                'success' => true,
                'payments' => $payments,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $perPage)
                ],
                'stats' => $stats,
                'search' => $search,
                'filters' => $normalizedFilters
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::listAdvancePayments Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch advance payment records'
            ];
        }
    }
    
    /**
     * Get detailed information about a specific advance payment
     */
    public function getPaymentDetails($paymentId) {
        try {
            $paymentId = (int)$paymentId;
            if ($paymentId <= 0) {
                throw new Exception('Invalid payment ID');
            }
            
            $query = "
                SELECT 
                    ap.*,
                    CONCAT(u.first_name, ' ', u.surname) as employee_name,
                    u.user_type as employee_type,
                    u.salary as employee_salary,
                    u.mobile_number as employee_mobile,
                    u.email_id as employee_email,
                    CONCAT(requester.first_name, ' ', requester.surname) as requested_by_name,
                    CONCAT(approver.first_name, ' ', approver.surname) as approved_by_name,
                    DATEDIFF(CURDATE(), ap.start_date) as days_active,
                    ROUND(((ap.amount - ap.remaining_balance) / ap.amount) * 100, 1) as progress_percentage,
                    CASE 
                        WHEN ap.status = 'active' AND ap.remaining_balance > 0 
                        THEN CEIL(ap.remaining_balance / ap.monthly_deduction)
                        ELSE 0 
                    END as remaining_installments
                FROM advance_payments ap
                JOIN users u ON ap.employee_id = u.id
                LEFT JOIN users requester ON ap.requested_by = requester.id
                LEFT JOIN users approver ON ap.approved_by = approver.id
                WHERE ap.id = ?
            ";
            
            $payment = $this->db->query($query, [$paymentId])->fetch();
            
            if (!$payment) {
                throw new Exception('Advance payment not found');
            }
            
            // Get transaction history
            $transactionQuery = "
                SELECT 
                    apt.*,
                    CONCAT(u.first_name, ' ', u.surname) as processed_by_name
                FROM advance_payment_transactions apt
                LEFT JOIN users u ON apt.processed_by = u.id
                WHERE apt.advance_payment_id = ?
                ORDER BY apt.payment_date DESC, apt.created_at DESC
            ";
            
            $transactions = $this->db->query($transactionQuery, [$paymentId])->fetchAll();
            
            return [
                'success' => true,
                'payment' => $payment,
                'transactions' => $transactions
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::getPaymentDetails Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new advance payment request
     */
    public function createAdvancePayment($data) {
        try {
            // Validate input data
            $employeeId = (int)($data['employee_id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $purpose = trim($data['purpose'] ?? '');
            $priority = $data['priority'] ?? 'normal';
            $isEmergency = (bool)($data['is_emergency'] ?? false);
            $installmentCount = (int)($data['installment_count'] ?? 1);
            
            // Get current user
            $requestedBy = $_SESSION['user_id'] ?? 1;
            
            // Validation
            if ($employeeId <= 0) {
                throw new Exception('Please select a valid employee');
            }
            
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            
            if (empty($purpose)) {
                throw new Exception('Purpose is required');
            }
            
            if ($installmentCount < 1 || $installmentCount > 24) {
                throw new Exception('Installment count must be between 1 and 24');
            }
            
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                throw new Exception('Invalid priority level');
            }
            
            // Check if employee exists
            $employeeQuery = "SELECT * FROM users WHERE id = ? AND user_type IN ('Guard', 'Supervisor', 'Site Supervisor')";
            $employee = $this->db->query($employeeQuery, [$employeeId])->fetch();
            if (!$employee) {
                throw new Exception('Invalid employee selected');
            }
            
            // Check for existing active advance
            $existingQuery = "SELECT COUNT(*) as count FROM advance_payments WHERE employee_id = ? AND status IN ('active', 'approved')";
            $existingCount = $this->db->query($existingQuery, [$employeeId])->fetch()['count'];
            if ($existingCount > 0) {
                throw new Exception('Employee already has an active or approved advance payment');
            }
            
            // Calculate monthly deduction
            $monthlyDeduction = $amount / $installmentCount;
            
            // Generate request number
            $requestNumber = 'AP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if request number already exists
            $checkRequestNumber = "SELECT COUNT(*) as count FROM advance_payments WHERE request_number = ?";
            while ($this->db->query($checkRequestNumber, [$requestNumber])->fetch()['count'] > 0) {
                $requestNumber = 'AP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Insert new advance payment
            $insertQuery = "
                INSERT INTO advance_payments (
                    employee_id, request_number, amount, purpose, priority, 
                    is_emergency, monthly_deduction, remaining_balance, 
                    installment_count, original_installment_count, requested_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ";
            
            $this->db->query($insertQuery, [
                $employeeId, $requestNumber, $amount, $purpose, $priority,
                $isEmergency ? 1 : 0, $monthlyDeduction, $amount,
                $installmentCount, $installmentCount, $requestedBy
            ]);
            
            $paymentId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Advance payment request created successfully',
                'payment_id' => $paymentId,
                'request_number' => $requestNumber
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::createAdvancePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an existing advance payment
     */
    public function updateAdvancePayment($data) {
        try {
            $paymentId = (int)($data['id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $purpose = trim($data['purpose'] ?? '');
            $priority = $data['priority'] ?? 'normal';
            $isEmergency = (bool)($data['is_emergency'] ?? false);
            $status = $data['status'] ?? '';
            $monthlyDeduction = (float)($data['monthly_deduction'] ?? 0);
            $installmentCount = (int)($data['installment_count'] ?? 1);
            
            // Validation
            if ($paymentId <= 0) {
                throw new Exception('Invalid payment ID');
            }
            
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            
            if (empty($purpose)) {
                throw new Exception('Purpose is required');
            }
            
            if (!in_array($status, ['pending', 'approved', 'active', 'completed', 'cancelled'])) {
                throw new Exception('Invalid status');
            }
            
            // Check if payment exists
            $existingQuery = "SELECT * FROM advance_payments WHERE id = ?";
            $existing = $this->db->query($existingQuery, [$paymentId])->fetch();
            if (!$existing) {
                throw new Exception('Advance payment not found');
            }
            
            // Recompute paid and remaining from transaction history to avoid drift
            $txRow = $this->db->query("
                SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'deduction' THEN amount ELSE 0 END),0) AS total_deducted,
                    COALESCE(SUM(CASE WHEN transaction_type = 'reversal' THEN amount ELSE 0 END),0) AS total_reversed
                FROM advance_payment_transactions
                WHERE advance_payment_id = ?
            ", [$paymentId])->fetch();
            $netDeducted = (float)($txRow['total_deducted'] ?? 0) - (float)($txRow['total_reversed'] ?? 0);
            $remainingBalance = max(0, $amount - $netDeducted);
            
            // Determine status based on remaining balance unless explicitly cancelling
            $nextStatus = $status;
            $completionDate = null;
            if ($remainingBalance <= 0) {
                $nextStatus = 'completed';
                $completionDate = date('Y-m-d');
                $remainingBalance = 0;
            } elseif ($status === 'cancelled') {
                $nextStatus = 'cancelled';
            } elseif (in_array($status, ['pending', 'approved'], true)) {
                $nextStatus = $status; // keep explicit pending/approved
            } else {
                // Force active if there is still balance to be repaid
                $nextStatus = 'active';
            }
            
            // Update the record
            $updateQuery = "
                UPDATE advance_payments 
                SET amount = ?, purpose = ?, priority = ?, is_emergency = ?, 
                    status = ?, monthly_deduction = ?, remaining_balance = ?, 
                    installment_count = ?, completion_date = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            $this->db->query($updateQuery, [
                $amount, $purpose, $priority, $isEmergency ? 1 : 0,
                $nextStatus, $monthlyDeduction, $remainingBalance,
                $installmentCount, $completionDate, $paymentId
            ]);
            
            return [
                'success' => true,
                'message' => 'Advance payment updated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::updateAdvancePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Approve an advance payment request
     */
    public function approveAdvancePayment($paymentId) {
        try {
            $paymentId = (int)$paymentId;
            if ($paymentId <= 0) {
                throw new Exception('Invalid payment ID');
            }
            
            $approvedBy = $_SESSION['user_id'] ?? 1;
            
            // Check if payment exists and is pending
            $existingQuery = "SELECT * FROM advance_payments WHERE id = ? AND status = 'pending'";
            $existing = $this->db->query($existingQuery, [$paymentId])->fetch();
            if (!$existing) {
                throw new Exception('Payment not found or not in pending status');
            }
            
            // Update status to approved
            $updateQuery = "
                UPDATE advance_payments 
                SET status = 'approved', approved_by = ?, approved_at = NOW(), 
                    start_date = CURDATE(), updated_at = NOW()
                WHERE id = ?
            ";
            
            $this->db->query($updateQuery, [$approvedBy, $paymentId]);
            
            return [
                'success' => true,
                'message' => 'Advance payment approved successfully'
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::approveAdvancePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Activate an approved advance payment
     */
    public function activateAdvancePayment($paymentId) {
        try {
            $paymentId = (int)$paymentId;
            if ($paymentId <= 0) {
                throw new Exception('Invalid payment ID');
            }
            
            // Check if payment exists and is approved
            $existingQuery = "SELECT * FROM advance_payments WHERE id = ? AND status = 'approved'";
            $existing = $this->db->query($existingQuery, [$paymentId])->fetch();
            if (!$existing) {
                throw new Exception('Payment not found or not in approved status');
            }
            
            // Update status to active
            $updateQuery = "UPDATE advance_payments SET status = 'active', updated_at = NOW() WHERE id = ?";
            $this->db->query($updateQuery, [$paymentId]);
            
            return [
                'success' => true,
                'message' => 'Advance payment activated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::activateAdvancePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel an advance payment
     */
    public function cancelAdvancePayment($paymentId, $reason = '') {
        try {
            $paymentId = (int)$paymentId;
            if ($paymentId <= 0) {
                throw new Exception('Invalid payment ID');
            }
            
            // Check if payment exists
            $existingQuery = "SELECT * FROM advance_payments WHERE id = ?";
            $existing = $this->db->query($existingQuery, [$paymentId])->fetch();
            if (!$existing) {
                throw new Exception('Advance payment not found');
            }
            
            if ($existing['status'] === 'completed') {
                throw new Exception('Cannot cancel a completed advance payment');
            }
            
            // Update status to cancelled with reason and timestamp
            $updateQuery = "UPDATE advance_payments SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ?";
            $this->db->query($updateQuery, [trim((string)$reason), $paymentId]);
            
            return [
                'success' => true,
                'message' => 'Advance payment cancelled successfully'
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::cancelAdvancePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of employees for dropdown
     */
    public function getEmployeesList() {
        try {
            $query = "
                SELECT 
                    id,
                    CONCAT(first_name, ' ', surname) as name,
                    user_type,
                    salary
                FROM users 
                WHERE user_type IN ('Guard', 'Supervisor', 'Site Supervisor')
                ORDER BY first_name, surname
            ";
            
            $employees = $this->db->query($query)->fetchAll();
            
            return [
                'success' => true,
                'employees' => $employees
            ];
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::getEmployeesList Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch employees list'
            ];
        }
    }
    
    /**
     * Process monthly deduction for an active advance
     */
    public function processMonthlyDeduction($paymentId, $deductionAmount, $salaryRecordId = null) {
        try {
            $paymentId = (int)$paymentId;
            $deductionAmount = (float)$deductionAmount;
            
            if ($paymentId <= 0 || $deductionAmount <= 0) {
                throw new Exception('Invalid payment ID or deduction amount');
            }
            
            // Get payment details
            $paymentQuery = "SELECT * FROM advance_payments WHERE id = ? AND status IN ('active','approved')";
            $payment = $this->db->query($paymentQuery, [$paymentId])->fetch();
            if (!$payment) {
                throw new Exception('Active/Approved advance payment not found');
            }
            
            // If approved but not active, switch to active on first deduction
            if ($payment['status'] === 'approved') {
                $this->db->query("UPDATE advance_payments SET status='active', start_date = COALESCE(start_date, CURDATE()) WHERE id = ?", [$paymentId]);
                $payment['status'] = 'active';
            }
            
            // Calculate new remaining balance
            $newRemainingBalance = (float)$payment['remaining_balance'] - $deductionAmount;
            $monthly = max(0.00001, (float)$payment['monthly_deduction']);
            $incrementInstallmentsBy = (int)floor(($deductionAmount + 1e-6) / $monthly);
            $newPaidInstallments = (int)$payment['paid_installments'] + $incrementInstallmentsBy;
            
            // Start transaction
            $this->db->beginTransaction();
            
            try {
                // Record the transaction
                $transactionQuery = "
                    INSERT INTO advance_payment_transactions 
                    (advance_payment_id, transaction_type, amount, installment_number, payment_date, salary_record_id, processed_by) 
                    VALUES (?, 'deduction', ?, ?, CURDATE(), ?, ?)
                ";
                
                $this->db->query($transactionQuery, [
                    $paymentId, $deductionAmount, $newPaidInstallments, $salaryRecordId, $_SESSION['user_id'] ?? 1
                ]);
                
                // Update payment status
                $status = ($newRemainingBalance <= 0) ? 'completed' : 'active';
                $completionDate = ($status === 'completed') ? date('Y-m-d') : null;
                
                $updateQuery = "
                    UPDATE advance_payments 
                    SET remaining_balance = ?, paid_installments = ?, status = ?, 
                        completion_date = ?, updated_at = NOW() 
                    WHERE id = ?
                ";
                
                $this->db->query($updateQuery, [
                    max(0, $newRemainingBalance), $newPaidInstallments, $status, $completionDate, $paymentId
                ]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => 'Monthly deduction processed successfully',
                    'remaining_balance' => max(0, $newRemainingBalance),
                    'status' => $status
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("AdvancePaymentController::processMonthlyDeduction Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Export advance payments as an array of rows for CSV generation
     */
    public function exportAdvancePayments($search = '', array $filters = []) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            if (!empty($search)) {
                $whereClause .= " AND (ap.request_number LIKE ? OR u.first_name LIKE ? OR u.surname LIKE ? OR ap.purpose LIKE ? OR ap.amount LIKE ?)";
                $searchParam = "%$search%";
                $params = array_fill(0, 5, $searchParam);
            }

            $normalizedFilters = [
                'status' => isset($filters['status']) ? trim((string)$filters['status']) : '',
                'priority' => isset($filters['priority']) ? trim((string)$filters['priority']) : (isset($filters['type']) ? trim((string)$filters['type']) : ''),
                'date_from' => isset($filters['date_from']) ? trim((string)$filters['date_from']) : '',
                'date_to' => isset($filters['date_to']) ? trim((string)$filters['date_to']) : '',
            ];
            if ($normalizedFilters['status'] !== '') { $whereClause .= " AND ap.status = ?"; $params[] = $normalizedFilters['status']; }
            if ($normalizedFilters['priority'] !== '') { $whereClause .= " AND ap.priority = ?"; $params[] = $normalizedFilters['priority']; }
            if ($normalizedFilters['date_from'] !== '') { $whereClause .= " AND DATE(ap.created_at) >= ?"; $params[] = $normalizedFilters['date_from']; }
            if ($normalizedFilters['date_to'] !== '') { $whereClause .= " AND DATE(ap.created_at) <= ?"; $params[] = $normalizedFilters['date_to']; }

            $query = "
                SELECT 
                    ap.id,
                    ap.request_number,
                    CONCAT(u.first_name, ' ', u.surname) as employee_name,
                    u.user_type as employee_type,
                    ap.amount,
                    ap.monthly_deduction,
                    ap.remaining_balance,
                    ap.installment_count,
                    ap.paid_installments,
                    ap.priority,
                    ap.is_emergency,
                    ap.status,
                    ap.start_date,
                    ap.completion_date,
                    ap.cancel_reason,
                    ap.cancelled_at,
                    ap.created_at
                FROM advance_payments ap
                JOIN users u ON ap.employee_id = u.id
                $whereClause
                ORDER BY ap.created_at DESC
            ";

            $rows = $this->db->query($query, $params)->fetchAll();

            return [
                'success' => true,
                'rows' => $rows
            ];
        } catch (Exception $e) {
            error_log("AdvancePaymentController::exportAdvancePayments Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to export advance payment records'
            ];
        }
    }
}
?>