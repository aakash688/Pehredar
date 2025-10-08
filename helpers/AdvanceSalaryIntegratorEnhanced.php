<?php
// helpers/AdvanceSalaryIntegratorEnhanced.php - Enhanced advance salary system with skip support

namespace Helpers;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/AdvanceSkipManager.php';

class AdvanceSalaryIntegratorEnhanced {
    private $db;
    private $skipManager;

    public function __construct() {
        $this->db = new \Database();
        $this->skipManager = new \Helpers\AdvanceSkipManager();
    }

    /**
     * Process advance deduction during salary calculation with skip support
     */
    public function processAdvanceDeduction($userId, $calculatedSalary, $salaryMonth) {
        try {
            $this->db->beginTransaction();

            // Get active advances for the employee
            $advancesResult = $this->getEmployeeActiveAdvances($userId);
            if (!$advancesResult['success'] || empty($advancesResult['advances'])) {
                $this->db->commit();
                return [
                    'success' => true,
                    'total_deduction' => 0,
                    'deductions' => [],
                    'message' => 'No active advances found'
                ];
            }

            $totalDeduction = 0;
            $deductions = [];
            $maxDeductionAllowed = $calculatedSalary * 0.5; // Maximum 50% of salary

            foreach ($advancesResult['advances'] as $advance) {
                // Check if advance is eligible for deduction in this salary month
                if (!empty($advance['start_date'])) {
                    $startDate = new \DateTime($advance['start_date']);
                    $startMonth = $startDate->format('Y-m');
                    
                    // Allow deductions to start from the same month as start_date
                    // or from the month after start_date if start_date is in the middle of the month
                    $firstEligibleMonth = $startMonth;
                    
                    // If start_date is after the 15th of the month, start deductions from next month
                    if ($startDate->format('d') > 15) {
                        $firstEligibleMonth = date('Y-m', strtotime($startMonth . '-01 +1 month'));
                    }
                    
                    // Debug logging
                    error_log("Advance Deduction Debug - User ID: $userId, Advance ID: {$advance['id']}");
                    error_log("Start Date: {$advance['start_date']}, Start Month: $startMonth, First Eligible Month: $firstEligibleMonth, Salary Month: $salaryMonth");
                    
                    if (strcmp($salaryMonth, $firstEligibleMonth) < 0) {
                        // Not eligible yet; skip this advance for this salaryMonth
                        error_log("Advance not eligible yet - skipping");
                        continue;
                    }
                    
                    error_log("Advance is eligible for deduction");
                }

                // NEW: Check if this month should be skipped
                if ($this->skipManager->shouldSkipDeduction($advance['id'], $salaryMonth)) {
                    error_log("Advance deduction skipped for month $salaryMonth due to approved skip request");
                    $deductions[] = [
                        'advance_id' => $advance['id'],
                        'source' => $advance['source'],
                        'deduction_amount' => 0,
                        'remaining_balance_before' => $advance['remaining_balance'],
                        'remaining_balance_after' => $advance['remaining_balance'],
                        'is_completed' => false,
                        'is_skipped' => true,
                        'skip_reason' => 'Approved skip request'
                    ];
                    continue;
                }

                $monthlyDeduction = (float)$advance['monthly_deduction_amount'];
                $remainingBalance = (float)$advance['remaining_balance'];
                
                error_log("Monthly Deduction: $monthlyDeduction, Remaining Balance: $remainingBalance, Max Allowed: $maxDeductionAllowed, Current Total: $totalDeduction");
                
                // Calculate actual deduction amount
                $actualDeduction = min(
                    $monthlyDeduction,
                    $remainingBalance,
                    $maxDeductionAllowed - $totalDeduction
                );

                error_log("Calculated Actual Deduction: $actualDeduction");

                if ($actualDeduction <= 0) {
                    error_log("Actual deduction is 0 or negative - skipping");
                    continue;
                }

                // Update advance balance
                $newBalance = $remainingBalance - $actualDeduction;
                $advanceId = $advance['id'];
                $source = $advance['source'];

                $deductions[] = [
                    'advance_id' => $advanceId,
                    'source' => $source,
                    'deduction_amount' => $actualDeduction,
                    'remaining_balance_before' => $remainingBalance,
                    'remaining_balance_after' => $newBalance,
                    'is_completed' => $newBalance <= 0,
                    'is_skipped' => false
                ];

                $totalDeduction += $actualDeduction;

                // Break if we've reached the maximum deduction limit
                if ($totalDeduction >= $maxDeductionAllowed) {
                    break;
                }
            }

            error_log("Final Advance Deduction Result - User ID: $userId, Total Deduction: $totalDeduction, Deductions Count: " . count($deductions));
            
            return [
                'success' => true,
                'total_deduction' => $totalDeduction,
                'deductions' => $deductions,
                'message' => "Processed advance deductions totaling ₹" . number_format($totalDeduction, 2)
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("AdvanceSalaryIntegratorEnhanced::processAdvanceDeduction error: " . $e->getMessage());
            return [
                'success' => false,
                'total_deduction' => 0,
                'deductions' => [],
                'message' => 'Failed to process advance deduction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all active advances for an employee (from both systems)
     */
    public function getEmployeeActiveAdvances($userId) {
        try {
            // New system: advance_payments (approved or active should be considered for deduction)
            $newQuery = "
                SELECT 
                    id,
                    employee_id AS user_id,
                    request_number AS advance_request_id,
                    amount AS total_advance_amount,
                    monthly_deduction AS monthly_deduction_amount,
                    remaining_balance,
                    status,
                    start_date,
                    completion_date AS expected_completion_date,
                    is_emergency AS emergency_advance,
                    priority AS priority_level,
                    created_at,
                    'new' AS source
                FROM advance_payments
                WHERE employee_id = ? AND status IN ('approved','active')
            ";
            $allAdvances = $this->db->query($newQuery, [$userId])->fetchAll();

            // Calculate progress for each advance
            foreach ($allAdvances as &$advance) {
                $totalAmount = (float)$advance['total_advance_amount'];
                $remainingBalance = (float)$advance['remaining_balance'];
                $progress = $totalAmount > 0 ? (($totalAmount - $remainingBalance) / $totalAmount) * 100 : 0;
                $advance['progress_percentage'] = round($progress, 2);
                $advance['is_overdue'] = false; // Calculate based on expected completion date
                
                if (isset($advance['expected_completion_date']) && $advance['expected_completion_date']) {
                    $advance['is_overdue'] = strtotime($advance['expected_completion_date']) < time();
                }
            }

            return [
                'success' => true,
                'advances' => $allAdvances,
                'total_advances' => count($allAdvances),
                'total_outstanding' => array_sum(array_column($allAdvances, 'remaining_balance'))
            ];

        } catch (\Exception $e) {
            error_log("AdvanceSalaryIntegratorEnhanced::getEmployeeActiveAdvances error: " . $e->getMessage());
            return [
                'success' => false,
                'advances' => [],
                'message' => 'Failed to fetch advances: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get employee advance status for visual indicators
     */
    public function getEmployeeAdvanceStatus($userId) {
        $advancesResult = $this->getEmployeeActiveAdvances($userId);
        
        if (!$advancesResult['success'] || empty($advancesResult['advances'])) {
            return [
                'has_advance' => false,
                'status' => 'normal',
                'visual_class' => '',
                'indicator_class' => '',
                'badge_text' => '',
                'total_outstanding' => 0,
                'advance_count' => 0
            ];
        }

        $advances = $advancesResult['advances'];
        $totalOutstanding = $advancesResult['total_outstanding'];
        
        // Determine visual status based on advance characteristics
        $hasOverdue = false;
        $hasEmergency = false;
        $hasHighPriority = false;
        $nearCompletion = false;

        foreach ($advances as $advance) {
            if ($advance['is_overdue']) $hasOverdue = true;
            if (isset($advance['emergency_advance']) && $advance['emergency_advance']) $hasEmergency = true;
            if (isset($advance['priority_level']) && in_array($advance['priority_level'], ['high', 'urgent'])) $hasHighPriority = true;
            if ($advance['progress_percentage'] >= 75) $nearCompletion = true;
        }

        // Determine visual class and status
        // Dark theme friendly, subtle backgrounds (no bright/white tints)
        if ($hasOverdue) {
            $status = 'overdue';
            $visualClass = 'bg-gray-800 bg-opacity-30';
            $indicatorClass = 'bg-red-500';
            $badgeText = 'Overdue';
        } elseif ($hasEmergency) {
            $status = 'emergency';
            $visualClass = 'bg-gray-800 bg-opacity-30';
            $indicatorClass = 'bg-orange-500';
            $badgeText = 'Emergency';
        } elseif ($hasHighPriority) {
            $status = 'high_priority';
            $visualClass = 'bg-gray-800 bg-opacity-30';
            $indicatorClass = 'bg-yellow-500';
            $badgeText = 'High Priority';
        } elseif ($nearCompletion) {
            $status = 'near_completion';
            $visualClass = 'bg-gray-800 bg-opacity-30';
            $indicatorClass = 'bg-green-500';
            $badgeText = 'Near Complete';
        } else {
            $status = 'active';
            $visualClass = 'bg-gray-800 bg-opacity-30';
            $indicatorClass = 'bg-indigo-500';
            $badgeText = 'Active Advance';
        }

        return [
            'has_advance' => true,
            'status' => $status,
            'visual_class' => $visualClass,
            'indicator_class' => $indicatorClass,
            'badge_text' => $badgeText,
            'total_outstanding' => $totalOutstanding,
            'advance_count' => count($advances),
            'advances' => $advances
        ];
    }
}
?>

