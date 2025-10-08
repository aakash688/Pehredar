<?php
// helpers/AdvanceTracker.php - Advanced tracking and management for employee advances

namespace Helpers;

require_once __DIR__ . '/database.php';

class AdvanceTracker {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Get advance summary for dashboard widget
     */
    public function getAdvanceSummary() {
        try {
            // Get active advances count and total amount
            $activeAdvancesQuery = "
                SELECT 
                    COUNT(*) as total_active_advances,
                    SUM(remaining_balance) as total_outstanding_amount,
                    SUM(monthly_deduction_amount) as monthly_recovery_projection,
                    AVG(remaining_balance) as average_balance
                FROM advance_salary_enhanced 
                WHERE status = 'active'
            ";
            $activeAdvances = $this->db->query($activeAdvancesQuery)->fetch();

            // Get this month's recoveries
            $currentMonth = date('Y-m');
            $monthlyRecoveryQuery = "
                SELECT 
                    COUNT(*) as recoveries_count,
                    SUM(deduction_amount) as total_recovered
                FROM advance_deduction_history 
                WHERE deduction_month = ?
            ";
            $monthlyRecoveries = $this->db->query($monthlyRecoveryQuery, [$currentMonth])->fetch();

            // Get emergency advances count
            $emergencyAdvancesQuery = "
                SELECT COUNT(*) as emergency_count 
                FROM advance_salary_enhanced 
                WHERE emergency_advance = TRUE AND status = 'active'
            ";
            $emergencyAdvances = $this->db->query($emergencyAdvancesQuery)->fetch();

            // Get overdue advances (expected completion date passed)
            $overdueAdvancesQuery = "
                SELECT COUNT(*) as overdue_count 
                FROM advance_salary_enhanced 
                WHERE status = 'active' AND expected_completion_date < CURDATE()
            ";
            $overdueAdvances = $this->db->query($overdueAdvancesQuery)->fetch();

            return [
                'active_advances_count' => (int)($activeAdvances['total_active_advances'] ?? 0),
                'total_outstanding_amount' => (float)($activeAdvances['total_outstanding_amount'] ?? 0),
                'monthly_recovery_projection' => (float)($activeAdvances['monthly_recovery_projection'] ?? 0),
                'average_balance' => (float)($activeAdvances['average_balance'] ?? 0),
                'this_month_recoveries_count' => (int)($monthlyRecoveries['recoveries_count'] ?? 0),
                'this_month_recovered_amount' => (float)($monthlyRecoveries['total_recovered'] ?? 0),
                'emergency_advances_count' => (int)($emergencyAdvances['emergency_count'] ?? 0),
                'overdue_advances_count' => (int)($overdueAdvances['overdue_count'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("AdvanceTracker::getAdvanceSummary error: " . $e->getMessage());
            return [
                'active_advances_count' => 0,
                'total_outstanding_amount' => 0,
                'monthly_recovery_projection' => 0,
                'average_balance' => 0,
                'this_month_recoveries_count' => 0,
                'this_month_recovered_amount' => 0,
                'emergency_advances_count' => 0,
                'overdue_advances_count' => 0
            ];
        }
    }

    /**
     * Get employees with their advance status for visual categorization
     */
    public function getEmployeesWithAdvanceStatus($filters = []) {
        try {
            $whereConditions = [];
            $params = [];

            // Build where conditions based on filters
            if (!empty($filters['advance_status'])) {
                $whereConditions[] = "ase.status = ?";
                $params[] = $filters['advance_status'];
            }

            if (!empty($filters['priority_level'])) {
                $whereConditions[] = "ase.priority_level = ?";
                $params[] = $filters['priority_level'];
            }

            if (!empty($filters['emergency_only']) && $filters['emergency_only']) {
                $whereConditions[] = "ase.emergency_advance = TRUE";
            }

            if (!empty($filters['overdue_only']) && $filters['overdue_only']) {
                $whereConditions[] = "ase.status = 'active' AND ase.expected_completion_date < CURDATE()";
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $query = "
                SELECT 
                    u.id as user_id,
                    u.first_name,
                    u.surname,
                    u.user_type,
                    u.salary,
                    ase.id as advance_id,
                    ase.total_advance_amount,
                    ase.monthly_deduction_amount,
                    ase.remaining_balance,
                    ase.total_deducted,
                    ase.start_date,
                    ase.expected_completion_date,
                    ase.status as advance_status,
                    ase.priority_level,
                    ase.emergency_advance,
                    ase.repayment_months,
                    -- Calculate progress percentage
                    ROUND(((ase.total_advance_amount - ase.remaining_balance) / ase.total_advance_amount) * 100, 2) as repayment_progress,
                    -- Check if overdue
                    CASE 
                        WHEN ase.status = 'active' AND ase.expected_completion_date < CURDATE() THEN TRUE 
                        ELSE FALSE 
                    END as is_overdue,
                    -- Get latest deduction
                    adh.deduction_month as last_deduction_month,
                    adh.deduction_amount as last_deduction_amount
                FROM 
                    users u
                LEFT JOIN 
                    advance_salary_enhanced ase ON u.id = ase.user_id AND ase.status = 'active'
                LEFT JOIN (
                    SELECT 
                        advance_id,
                        deduction_month,
                        deduction_amount,
                        ROW_NUMBER() OVER (PARTITION BY advance_id ORDER BY deduction_month DESC) as rn
                    FROM advance_deduction_history
                ) adh ON ase.id = adh.advance_id AND adh.rn = 1
                $whereClause
                ORDER BY 
                    CASE 
                        WHEN ase.status = 'active' AND ase.expected_completion_date < CURDATE() THEN 1  -- Overdue first
                        WHEN ase.emergency_advance = TRUE THEN 2  -- Emergency advances
                        WHEN ase.priority_level = 'urgent' THEN 3  -- Urgent priority
                        WHEN ase.priority_level = 'high' THEN 4  -- High priority
                        WHEN ase.status = 'active' THEN 5  -- Other active advances
                        ELSE 6  -- No advances
                    END,
                    ase.remaining_balance DESC,
                    u.first_name, u.surname
            ";

            $employees = $this->db->query($query, $params)->fetchAll();

            // Add visual categorization data
            foreach ($employees as &$employee) {
                $employee['visual_category'] = $this->determineVisualCategory($employee);
                $employee['status_indicators'] = $this->getStatusIndicators($employee);
                $employee['progress_data'] = $this->calculateProgressData($employee);
            }

            return $employees;
        } catch (Exception $e) {
            error_log("AdvanceTracker::getEmployeesWithAdvanceStatus error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Determine visual category for employee based on advance status
     */
    private function determineVisualCategory($employee) {
        if (!$employee['advance_id']) {
            return [
                'category' => 'normal',
                'color_class' => 'bg-gray-100 text-gray-800',
                'border_class' => 'border-gray-200',
                'indicator_class' => '',
                'label' => 'No Advance'
            ];
        }

        if ($employee['is_overdue']) {
            return [
                'category' => 'overdue',
                'color_class' => 'bg-red-100 text-red-800',
                'border_class' => 'border-red-300',
                'indicator_class' => 'bg-red-500',
                'label' => 'Overdue Advance'
            ];
        }

        if ($employee['emergency_advance']) {
            return [
                'category' => 'emergency',
                'color_class' => 'bg-orange-100 text-orange-800',
                'border_class' => 'border-orange-300',
                'indicator_class' => 'bg-orange-500',
                'label' => 'Emergency Advance'
            ];
        }

        if ($employee['priority_level'] === 'urgent' || $employee['priority_level'] === 'high') {
            return [
                'category' => 'high_priority',
                'color_class' => 'bg-yellow-100 text-yellow-800',
                'border_class' => 'border-yellow-300',
                'indicator_class' => 'bg-yellow-500',
                'label' => 'High Priority Advance'
            ];
        }

        if ($employee['advance_status'] === 'active') {
            return [
                'category' => 'active',
                'color_class' => 'bg-blue-100 text-blue-800',
                'border_class' => 'border-blue-300',
                'indicator_class' => 'bg-blue-500',
                'label' => 'Active Advance'
            ];
        }

        return [
            'category' => 'normal',
            'color_class' => 'bg-gray-100 text-gray-800',
            'border_class' => 'border-gray-200',
            'indicator_class' => '',
            'label' => 'No Active Advance'
        ];
    }

    /**
     * Get status indicators for employee
     */
    private function getStatusIndicators($employee) {
        $indicators = [];

        if ($employee['advance_id']) {
            if ($employee['is_overdue']) {
                $indicators[] = [
                    'type' => 'overdue',
                    'icon' => '⚠️',
                    'text' => 'Overdue',
                    'class' => 'bg-red-500 text-white'
                ];
            }

            if ($employee['emergency_advance']) {
                $indicators[] = [
                    'type' => 'emergency',
                    'icon' => '🚨',
                    'text' => 'Emergency',
                    'class' => 'bg-orange-500 text-white'
                ];
            }

            if ($employee['priority_level'] === 'urgent') {
                $indicators[] = [
                    'type' => 'urgent',
                    'icon' => '⚡',
                    'text' => 'Urgent',
                    'class' => 'bg-red-600 text-white'
                ];
            } elseif ($employee['priority_level'] === 'high') {
                $indicators[] = [
                    'type' => 'high',
                    'icon' => '🔴',
                    'text' => 'High',
                    'class' => 'bg-yellow-600 text-white'
                ];
            }

            // Progress indicator
            $progress = $employee['repayment_progress'];
            if ($progress >= 75) {
                $indicators[] = [
                    'type' => 'progress',
                    'icon' => '✅',
                    'text' => 'Near Complete',
                    'class' => 'bg-green-500 text-white'
                ];
            } elseif ($progress >= 50) {
                $indicators[] = [
                    'type' => 'progress',
                    'icon' => '🔄',
                    'text' => 'In Progress',
                    'class' => 'bg-blue-500 text-white'
                ];
            }
        }

        return $indicators;
    }

    /**
     * Calculate progress data for visualization
     */
    private function calculateProgressData($employee) {
        if (!$employee['advance_id']) {
            return null;
        }

        $totalAmount = (float)$employee['total_advance_amount'];
        $remainingBalance = (float)$employee['remaining_balance'];
        $totalDeducted = (float)$employee['total_deducted'];
        $monthlyDeduction = (float)$employee['monthly_deduction_amount'];

        $progressPercentage = $totalAmount > 0 ? (($totalAmount - $remainingBalance) / $totalAmount) * 100 : 0;
        $remainingMonths = $monthlyDeduction > 0 ? ceil($remainingBalance / $monthlyDeduction) : 0;

        return [
            'total_amount' => $totalAmount,
            'remaining_balance' => $remainingBalance,
            'total_deducted' => $totalDeducted,
            'monthly_deduction' => $monthlyDeduction,
            'progress_percentage' => round($progressPercentage, 2),
            'remaining_months' => $remainingMonths,
            'completion_date' => $employee['expected_completion_date'],
            'is_on_track' => !$employee['is_overdue']
        ];
    }

    /**
     * Get advance history for a specific employee
     */
    public function getAdvanceHistory($userId) {
        try {
            $query = "
                SELECT 
                    ase.*,
                    u_approved.first_name as approved_by_name,
                    u_approved.surname as approved_by_surname,
                    u_created.first_name as created_by_name,
                    u_created.surname as created_by_surname
                FROM advance_salary_enhanced ase
                LEFT JOIN users u_approved ON ase.approved_by = u_approved.id
                LEFT JOIN users u_created ON ase.created_by = u_created.id
                WHERE ase.user_id = ?
                ORDER BY ase.created_at DESC
            ";

            $advances = $this->db->query($query, [$userId])->fetchAll();

            // Get deduction history for each advance
            foreach ($advances as &$advance) {
                $deductionHistoryQuery = "
                    SELECT 
                        adh.*,
                        sr.month as salary_month,
                        sr.year as salary_year
                    FROM advance_deduction_history adh
                    LEFT JOIN salary_records sr ON adh.salary_record_id = sr.id
                    WHERE adh.advance_id = ?
                    ORDER BY adh.deduction_month DESC
                ";
                
                $advance['deduction_history'] = $this->db->query($deductionHistoryQuery, [$advance['id']])->fetchAll();
                $advance['progress_data'] = $this->calculateProgressData($advance);
            }

            return $advances;
        } catch (Exception $e) {
            error_log("AdvanceTracker::getAdvanceHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new advance request
     */
    public function createAdvanceRequest($data) {
        try {
            $this->db->beginTransaction();

            // Generate unique advance request ID
            $advanceRequestId = 'ADV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

            // Calculate expected completion date
            $startDate = new \DateTime($data['start_date']);
            $expectedCompletion = clone $startDate;
            $expectedCompletion->add(new \DateInterval('P' . $data['repayment_months'] . 'M'));

            $insertQuery = "
                INSERT INTO advance_salary_enhanced (
                    user_id, advance_request_id, total_advance_amount, monthly_deduction_amount,
                    remaining_balance, start_date, expected_completion_date, grant_reason,
                    emergency_advance, repayment_months, priority_level, approved_by, 
                    approved_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ";

            $params = [
                $data['user_id'],
                $advanceRequestId,
                $data['total_advance_amount'],
                $data['monthly_deduction_amount'],
                $data['total_advance_amount'], // Initially remaining balance equals total amount
                $data['start_date'],
                $expectedCompletion->format('Y-m-d'),
                $data['grant_reason'] ?? null,
                $data['emergency_advance'] ?? false,
                $data['repayment_months'],
                $data['priority_level'] ?? 'medium',
                $data['approved_by'],
                $data['created_by']
            ];

            $this->db->query($insertQuery, $params);
            $advanceId = $this->db->lastInsertId();

            $this->db->commit();

            // Log audit trail
            $this->logAuditTrail('CREATE', 'advance_salary_enhanced', $advanceId, [], $data, 
                               "Created new advance request: $advanceRequestId", $data['created_by']);

            return [
                'success' => true,
                'advance_id' => $advanceId,
                'advance_request_id' => $advanceRequestId,
                'message' => 'Advance request created successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("AdvanceTracker::createAdvanceRequest error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create advance request: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log audit trail for advance-related actions
     */
    private function logAuditTrail($action, $table, $recordId, $oldValues, $newValues, $description, $userId) {
        try {
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action_type, table_name, record_id, old_values, new_values,
                    description, module, severity, success
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'advance_management', 'medium', TRUE)
            ";

            $this->db->query($auditQuery, [
                $userId,
                $action,
                $table,
                $recordId,
                json_encode($oldValues),
                json_encode($newValues),
                $description
            ]);
        } catch (Exception $e) {
            error_log("AdvanceTracker::logAuditTrail error: " . $e->getMessage());
        }
    }
}
?>