<?php
// helpers/AdvanceSalaryIntegrator.php - Bridge between existing and enhanced advance systems

namespace Helpers;

require_once __DIR__ . '/database.php';

class AdvanceSalaryIntegrator {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Migrate existing salary_advances data to advance_salary_enhanced
     */
    public function migrateExistingAdvances() {
        try {

            // Get all existing advances that haven't been migrated
            $existingAdvancesQuery = "
                SELECT sa.* FROM salary_advances sa
                LEFT JOIN advance_salary_enhanced ase ON sa.user_id = ase.user_id 
                    AND sa.amount = ase.total_advance_amount 
                    AND sa.created_at = ase.created_at
                WHERE ase.id IS NULL AND sa.status = 'Active'
            ";
            
            $existingAdvances = $this->db->query($existingAdvancesQuery)->fetchAll();

            $migratedCount = 0;
            foreach ($existingAdvances as $advance) {
                // Calculate monthly deduction (default to 1/3 of remaining amount over 3 months)
                $remainingAmount = (float)$advance['remaining_amount'];
                $monthlyDeduction = $remainingAmount > 0 ? $remainingAmount / 3 : 0;

                // Generate unique advance request ID
                $advanceRequestId = 'ADV-' . date('Y') . '-' . str_pad($advance['id'], 5, '0', STR_PAD_LEFT);

                // Calculate expected completion date (3 months from now)
                $startDate = date('Y-m-d');
                $expectedCompletion = date('Y-m-d', strtotime('+3 months'));

                // Insert into enhanced table
                $insertQuery = "
                    INSERT INTO advance_salary_enhanced (
                        user_id, advance_request_id, total_advance_amount, 
                        monthly_deduction_amount, remaining_balance, total_deducted,
                        start_date, expected_completion_date, status, grant_reason,
                        repayment_months, approved_by, approved_at, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $status = $advance['status'] === 'Active' ? 'active' : 
                         ($advance['status'] === 'Completed' ? 'completed' : 'cancelled');

                $totalDeducted = (float)$advance['amount'] - $remainingAmount;

                $this->db->query($insertQuery, [
                    $advance['user_id'],
                    $advanceRequestId,
                    $advance['amount'],
                    $monthlyDeduction,
                    $advance['remaining_amount'],
                    $totalDeducted,
                    $startDate,
                    $expectedCompletion,
                    $status,
                    $advance['notes'] ?? 'Migrated from legacy system',
                    3, // Default 3 months repayment
                    $advance['created_by'],
                    $advance['created_at'],
                    $advance['created_by'],
                    $advance['created_at']
                ]);

                $migratedCount++;
            }

            $this->db->commit();

            return [
                'success' => true,
                'migrated_count' => $migratedCount,
                'message' => "Successfully migrated $migratedCount advance records"
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("AdvanceSalaryIntegrator::migrateExistingAdvances error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync new advance from legacy system to enhanced system
     */
    public function syncAdvanceToEnhanced($legacyAdvanceId) {
        try {
            // Get advance from legacy system
            $legacyAdvance = $this->getLegacyAdvance($legacyAdvanceId);
            if (!$legacyAdvance) {
                throw new \Exception('Legacy advance not found');
            }

            // Check if already synced
            $existingQuery = "
                SELECT id FROM advance_salary_enhanced 
                WHERE user_id = ? AND total_advance_amount = ? AND created_at = ?
            ";
            $existing = $this->db->query($existingQuery, [
                $legacyAdvance['user_id'],
                $legacyAdvance['amount'],
                $legacyAdvance['created_at']
            ])->fetch();

            if ($existing) {
                return ['success' => true, 'message' => 'Already synced', 'enhanced_id' => $existing['id']];
            }

            // Create enhanced record
            $advanceRequestId = 'ADV-' . date('Y') . '-' . str_pad($legacyAdvanceId, 5, '0', STR_PAD_LEFT);
            $remainingAmount = (float)$legacyAdvance['remaining_amount'];
            $monthlyDeduction = $remainingAmount > 0 ? $remainingAmount / 3 : 0;

            $insertQuery = "
                INSERT INTO advance_salary_enhanced (
                    user_id, advance_request_id, total_advance_amount, 
                    monthly_deduction_amount, remaining_balance, total_deducted,
                    start_date, expected_completion_date, status, grant_reason,
                    repayment_months, approved_by, approved_at, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $this->db->query($insertQuery, [
                $legacyAdvance['user_id'],
                $advanceRequestId,
                $legacyAdvance['amount'],
                $monthlyDeduction,
                $legacyAdvance['remaining_amount'],
                (float)$legacyAdvance['amount'] - $remainingAmount,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+3 months')),
                $legacyAdvance['status'] === 'Active' ? 'active' : 'completed',
                $legacyAdvance['notes'] ?? 'Synced from advance-salary interface',
                3,
                $legacyAdvance['created_by'],
                $legacyAdvance['created_at'],
                $legacyAdvance['created_by'],
                $legacyAdvance['created_at']
            ]);

            $enhancedId = $this->db->lastInsertId();

            return [
                'success' => true,
                'enhanced_id' => $enhancedId,
                'message' => 'Successfully synced to enhanced system'
            ];

        } catch (\Exception $e) {
            error_log("AdvanceSalaryIntegrator::syncAdvanceToEnhanced error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
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
            error_log("AdvanceSalaryIntegrator::getEmployeeActiveAdvances error: " . $e->getMessage());
            return [
                'success' => false,
                'advances' => [],
                'message' => 'Failed to fetch advances: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process advance deduction during salary calculation
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
                    'is_completed' => $newBalance <= 0
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
            error_log("AdvanceSalaryIntegrator::processAdvanceDeduction error: " . $e->getMessage());
            return [
                'success' => false,
                'total_deduction' => 0,
                'deductions' => [],
                'message' => 'Failed to process advance deduction: ' . $e->getMessage()
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

    /**
     * Helper method to get legacy advance
     */
    private function getLegacyAdvance($advanceId) {
        $query = "SELECT * FROM salary_advances WHERE id = ?";
        return $this->db->query($query, [$advanceId])->fetch();
    }

    /**
     * Get comprehensive advance summary for dashboard
     */
    public function getAdvanceSummary() {
        try {
            // Get data from both systems
            $enhancedQuery = "
                SELECT 
                    COUNT(*) as enhanced_count,
                    SUM(remaining_balance) as enhanced_outstanding,
                    SUM(monthly_deduction_amount) as enhanced_monthly_recovery
                FROM advance_salary_enhanced 
                WHERE status = 'active'
            ";
            $enhancedData = $this->db->query($enhancedQuery)->fetch();

            $legacyQuery = "
                SELECT 
                    COUNT(*) as legacy_count,
                    SUM(remaining_amount) as legacy_outstanding
                FROM salary_advances sa
                LEFT JOIN advance_salary_enhanced ase ON sa.user_id = ase.user_id 
                    AND sa.amount = ase.total_advance_amount
                WHERE sa.status = 'Active' AND ase.id IS NULL
            ";
            $legacyData = $this->db->query($legacyQuery)->fetch();

            return [
                'total_active_advances' => ($enhancedData['enhanced_count'] ?? 0) + ($legacyData['legacy_count'] ?? 0),
                'total_outstanding_amount' => ($enhancedData['enhanced_outstanding'] ?? 0) + ($legacyData['legacy_outstanding'] ?? 0),
                'monthly_recovery_projection' => $enhancedData['enhanced_monthly_recovery'] ?? 0,
                'enhanced_system_count' => $enhancedData['enhanced_count'] ?? 0,
                'legacy_system_count' => $legacyData['legacy_count'] ?? 0
            ];

        } catch (\Exception $e) {
            error_log("AdvanceSalaryIntegrator::getAdvanceSummary error: " . $e->getMessage());
            return [
                'total_active_advances' => 0,
                'total_outstanding_amount' => 0,
                'monthly_recovery_projection' => 0,
                'enhanced_system_count' => 0,
                'legacy_system_count' => 0
            ];
        }
    }
}
?>