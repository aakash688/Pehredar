<?php
// helpers/BulkOperationManager.php - Manager for bulk payroll operations

namespace Helpers;

require_once __DIR__ . '/database.php';

class BulkOperationManager {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Apply bulk bonuses to multiple employees
     */
    public function applyBulkBonus($operationData) {
        try {
            $this->db->beginTransaction();

            $bulkOperationId = 'BULK-BONUS-' . date('YmdHis') . '-' . rand(1000, 9999);
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($operationData['employees'] as $employeeId) {
                try {
                    // Get employee data for calculations
                    $employee = $this->getEmployeeData($employeeId);
                    if (!$employee) {
                        $errors[] = "Employee ID $employeeId not found";
                        $errorCount++;
                        continue;
                    }

                    // Calculate bonus amount
                    $bonusAmount = $this->calculateBonusAmount(
                        $operationData['bonus_category'],
                        $operationData['amount'],
                        $operationData['percentage'] ?? null,
                        $employee['salary']
                    );

                    // Insert bonus record
                    $bonusQuery = "
                        INSERT INTO bonus_records (
                            user_id, bonus_type, bonus_category, amount, percentage,
                            base_amount, month, year, description, is_bulk_applied,
                            bulk_operation_id, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?)
                    ";

                    $bonusParams = [
                        $employeeId,
                        $operationData['bonus_type'],
                        $operationData['bonus_category'],
                        $bonusAmount,
                        $operationData['percentage'] ?? null,
                        $employee['salary'],
                        $operationData['month'],
                        $operationData['year'],
                        $operationData['description'],
                        $bulkOperationId,
                        $operationData['created_by']
                    ];

                    $this->db->query($bonusQuery, $bonusParams);
                    $successCount++;

                    // Update salary record if exists for the month
                    $this->updateSalaryRecordWithBonus($employeeId, $operationData['month'], $bonusAmount);

                } catch (Exception $e) {
                    $errors[] = "Employee ID $employeeId: " . $e->getMessage();
                    $errorCount++;
                }
            }

            if ($successCount > 0) {
                $this->db->commit();
                
                // Log audit trail
                $this->logBulkOperation('BULK_BONUS_APPLY', $bulkOperationId, $operationData, $successCount, $errorCount, $operationData['created_by']);

                return [
                    'success' => true,
                    'bulk_operation_id' => $bulkOperationId,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                    'message' => "Bulk bonus applied successfully to $successCount employees"
                ];
            } else {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'No bonuses were applied successfully',
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("BulkOperationManager::applyBulkBonus error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk bonus operation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Apply bulk deductions to multiple employees
     */
    public function applyBulkDeduction($operationData) {
        try {
            $this->db->beginTransaction();

            $bulkOperationId = 'BULK-DEDUCTION-' . date('YmdHis') . '-' . rand(1000, 9999);
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($operationData['employees'] as $employeeId) {
                try {
                    // Get employee data for calculations
                    $employee = $this->getEmployeeData($employeeId);
                    if (!$employee) {
                        $errors[] = "Employee ID $employeeId not found";
                        $errorCount++;
                        continue;
                    }

                    // Calculate deduction amount
                    $deductionAmount = $this->calculateDeductionAmount(
                        $operationData['deduction_category'],
                        $operationData['amount'],
                        $operationData['percentage'] ?? null,
                        $employee['salary']
                    );

                    // Check if deduction would exceed salary
                    if ($deductionAmount > $employee['salary'] * 0.8) {
                        $errors[] = "Employee ID $employeeId: Deduction amount exceeds 80% of salary";
                        $errorCount++;
                        continue;
                    }

                    // Insert deduction record
                    $deductionQuery = "
                        INSERT INTO employee_deductions (
                            user_id, deduction_type_id, amount, percentage, base_amount,
                            month, year, reason, is_bulk_applied, bulk_operation_id,
                            status, created_by, approved_by, approved_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, 'applied', ?, ?, NOW())
                    ";

                    $deductionParams = [
                        $employeeId,
                        $operationData['deduction_type_id'],
                        $deductionAmount,
                        $operationData['percentage'] ?? null,
                        $employee['salary'],
                        $operationData['month'],
                        $operationData['year'],
                        $operationData['reason'],
                        $bulkOperationId,
                        $operationData['created_by'],
                        $operationData['approved_by'] ?? $operationData['created_by']
                    ];

                    $this->db->query($deductionQuery, $deductionParams);
                    $successCount++;

                    // Update salary record if exists for the month
                    $this->updateSalaryRecordWithDeduction($employeeId, $operationData['month'], $deductionAmount);

                } catch (Exception $e) {
                    $errors[] = "Employee ID $employeeId: " . $e->getMessage();
                    $errorCount++;
                }
            }

            if ($successCount > 0) {
                $this->db->commit();
                
                // Log audit trail
                $this->logBulkOperation('BULK_DEDUCTION_APPLY', $bulkOperationId, $operationData, $successCount, $errorCount, $operationData['created_by']);

                return [
                    'success' => true,
                    'bulk_operation_id' => $bulkOperationId,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                    'message' => "Bulk deduction applied successfully to $successCount employees"
                ];
            } else {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'No deductions were applied successfully',
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("BulkOperationManager::applyBulkDeduction error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk deduction operation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Bulk adjust advance salary repayments
     */
    public function bulkAdjustAdvances($operationData) {
        try {
            $this->db->beginTransaction();

            $bulkOperationId = 'BULK-ADVANCE-ADJ-' . date('YmdHis') . '-' . rand(1000, 9999);
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($operationData['advance_ids'] as $advanceId) {
                try {
                    // Get current advance data
                    $advance = $this->getAdvanceData($advanceId);
                    if (!$advance) {
                        $errors[] = "Advance ID $advanceId not found";
                        $errorCount++;
                        continue;
                    }

                    if ($advance['status'] !== 'active') {
                        $errors[] = "Advance ID $advanceId is not active";
                        $errorCount++;
                        continue;
                    }

                    $oldValues = $advance;
                    $updates = [];
                    $params = [];

                    // Build update query based on operation type
                    if ($operationData['adjustment_type'] === 'monthly_amount') {
                        $newMonthlyAmount = $operationData['new_monthly_amount'];
                        $updates[] = "monthly_deduction_amount = ?";
                        $params[] = $newMonthlyAmount;

                        // Recalculate expected completion date
                        if ($newMonthlyAmount > 0) {
                            $remainingMonths = ceil($advance['remaining_balance'] / $newMonthlyAmount);
                            $newCompletionDate = date('Y-m-d', strtotime($advance['start_date'] . " + $remainingMonths months"));
                            $updates[] = "expected_completion_date = ?";
                            $params[] = $newCompletionDate;
                        }
                    } elseif ($operationData['adjustment_type'] === 'suspend') {
                        $updates[] = "status = 'suspended', suspended_at = NOW(), suspension_reason = ?";
                        $params[] = $operationData['suspension_reason'];
                    } elseif ($operationData['adjustment_type'] === 'reactivate') {
                        $updates[] = "status = 'active', suspended_at = NULL, suspension_reason = NULL";
                    }

                    if (!empty($updates)) {
                        $updateQuery = "UPDATE advance_salary_enhanced SET " . implode(", ", $updates) . " WHERE id = ?";
                        $params[] = $advanceId;
                        
                        $this->db->query($updateQuery, $params);
                        $successCount++;

                        // Log individual adjustment
                        $this->logAdvanceAdjustment($advanceId, $oldValues, $operationData, $bulkOperationId, $operationData['created_by']);
                    }

                } catch (Exception $e) {
                    $errors[] = "Advance ID $advanceId: " . $e->getMessage();
                    $errorCount++;
                }
            }

            if ($successCount > 0) {
                $this->db->commit();
                
                // Log bulk operation
                $this->logBulkOperation('BULK_ADVANCE_ADJUST', $bulkOperationId, $operationData, $successCount, $errorCount, $operationData['created_by']);

                return [
                    'success' => true,
                    'bulk_operation_id' => $bulkOperationId,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                    'message' => "Bulk advance adjustment applied to $successCount advances"
                ];
            } else {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'No advances were adjusted successfully',
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("BulkOperationManager::bulkAdjustAdvances error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk advance adjustment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Preview bulk operation before applying
     */
    public function previewBulkOperation($operationData) {
        try {
            $preview = [
                'operation_type' => $operationData['operation_type'],
                'total_employees' => 0,
                'estimated_total_amount' => 0,
                'employees_preview' => [],
                'warnings' => [],
                'errors' => []
            ];

            if ($operationData['operation_type'] === 'bonus') {
                $preview = $this->previewBulkBonus($operationData, $preview);
            } elseif ($operationData['operation_type'] === 'deduction') {
                $preview = $this->previewBulkDeduction($operationData, $preview);
            } elseif ($operationData['operation_type'] === 'advance_adjustment') {
                $preview = $this->previewBulkAdvanceAdjustment($operationData, $preview);
            }

            return [
                'success' => true,
                'preview' => $preview
            ];

        } catch (Exception $e) {
            error_log("BulkOperationManager::previewBulkOperation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Helper methods
     */
    private function getEmployeeData($employeeId) {
        $query = "SELECT id, first_name, surname, salary, user_type FROM users WHERE id = ?";
        return $this->db->query($query, [$employeeId])->fetch();
    }

    private function getAdvanceData($advanceId) {
        $query = "SELECT * FROM advance_salary_enhanced WHERE id = ?";
        return $this->db->query($query, [$advanceId])->fetch();
    }

    private function calculateBonusAmount($category, $amount, $percentage, $baseSalary) {
        if ($category === 'fixed') {
            return (float)$amount;
        } elseif ($category === 'percentage') {
            return (float)$baseSalary * ((float)$percentage / 100);
        }
        return 0;
    }

    private function calculateDeductionAmount($category, $amount, $percentage, $baseSalary) {
        if ($category === 'fixed') {
            return (float)$amount;
        } elseif ($category === 'percentage') {
            return (float)$baseSalary * ((float)$percentage / 100);
        }
        return 0;
    }

    private function updateSalaryRecordWithBonus($employeeId, $month, $bonusAmount) {
        try {
            $updateQuery = "
                UPDATE salary_records 
                SET additional_bonuses = additional_bonuses + ?,
                    net_salary = net_salary + ?,
                    bonus_details = JSON_SET(
                        COALESCE(bonus_details, JSON_OBJECT()),
                        '$.bulk_bonus',
                        COALESCE(JSON_EXTRACT(bonus_details, '$.bulk_bonus'), 0) + ?
                    )
                WHERE user_id = ? AND month = ?
            ";
            $this->db->query($updateQuery, [$bonusAmount, $bonusAmount, $bonusAmount, $employeeId, $month]);
        } catch (Exception $e) {
            // Salary record might not exist yet, ignore
        }
    }

    private function updateSalaryRecordWithDeduction($employeeId, $month, $deductionAmount) {
        try {
            $updateQuery = "
                UPDATE salary_records 
                SET total_deductions = total_deductions + ?,
                    net_salary = net_salary - ?,
                    deduction_details = JSON_SET(
                        COALESCE(deduction_details, JSON_OBJECT()),
                        '$.bulk_deduction',
                        COALESCE(JSON_EXTRACT(deduction_details, '$.bulk_deduction'), 0) + ?
                    )
                WHERE user_id = ? AND month = ?
            ";
            $this->db->query($updateQuery, [$deductionAmount, $deductionAmount, $deductionAmount, $employeeId, $month]);
        } catch (Exception $e) {
            // Salary record might not exist yet, ignore
        }
    }

    private function previewBulkBonus($operationData, $preview) {
        foreach ($operationData['employees'] as $employeeId) {
            $employee = $this->getEmployeeData($employeeId);
            if ($employee) {
                $bonusAmount = $this->calculateBonusAmount(
                    $operationData['bonus_category'],
                    $operationData['amount'],
                    $operationData['percentage'] ?? null,
                    $employee['salary']
                );

                $preview['employees_preview'][] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee['first_name'] . ' ' . $employee['surname'],
                    'base_salary' => $employee['salary'],
                    'calculated_bonus' => $bonusAmount
                ];

                $preview['estimated_total_amount'] += $bonusAmount;
                $preview['total_employees']++;
            } else {
                $preview['errors'][] = "Employee ID $employeeId not found";
            }
        }
        return $preview;
    }

    private function previewBulkDeduction($operationData, $preview) {
        foreach ($operationData['employees'] as $employeeId) {
            $employee = $this->getEmployeeData($employeeId);
            if ($employee) {
                $deductionAmount = $this->calculateDeductionAmount(
                    $operationData['deduction_category'],
                    $operationData['amount'],
                    $operationData['percentage'] ?? null,
                    $employee['salary']
                );

                // Check for warnings
                if ($deductionAmount > $employee['salary'] * 0.8) {
                    $preview['warnings'][] = "Employee {$employee['first_name']} {$employee['surname']}: Deduction exceeds 80% of salary";
                }

                $preview['employees_preview'][] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee['first_name'] . ' ' . $employee['surname'],
                    'base_salary' => $employee['salary'],
                    'calculated_deduction' => $deductionAmount
                ];

                $preview['estimated_total_amount'] += $deductionAmount;
                $preview['total_employees']++;
            } else {
                $preview['errors'][] = "Employee ID $employeeId not found";
            }
        }
        return $preview;
    }

    private function previewBulkAdvanceAdjustment($operationData, $preview) {
        foreach ($operationData['advance_ids'] as $advanceId) {
            $advance = $this->getAdvanceData($advanceId);
            if ($advance) {
                $employee = $this->getEmployeeData($advance['user_id']);
                
                $preview['employees_preview'][] = [
                    'advance_id' => $advanceId,
                    'employee_name' => $employee ? $employee['first_name'] . ' ' . $employee['surname'] : 'Unknown',
                    'current_monthly_deduction' => $advance['monthly_deduction_amount'],
                    'remaining_balance' => $advance['remaining_balance'],
                    'status' => $advance['status']
                ];

                $preview['total_employees']++;
                
                if ($advance['status'] !== 'active') {
                    $preview['warnings'][] = "Advance ID $advanceId is not active";
                }
            } else {
                $preview['errors'][] = "Advance ID $advanceId not found";
            }
        }
        return $preview;
    }

    private function logBulkOperation($operationType, $bulkOperationId, $operationData, $successCount, $errorCount, $userId) {
        try {
            $description = "Bulk operation: $operationType - ID: $bulkOperationId - Success: $successCount - Errors: $errorCount";
            
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action_type, table_name, description, module, 
                    severity, additional_data, success
                ) VALUES (?, ?, 'multiple', ?, 'bulk_operations', 'high', ?, TRUE)
            ";

            $this->db->query($auditQuery, [
                $userId,
                $operationType,
                $description,
                json_encode([
                    'bulk_operation_id' => $bulkOperationId,
                    'operation_data' => $operationData,
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ])
            ]);
        } catch (Exception $e) {
            error_log("BulkOperationManager::logBulkOperation error: " . $e->getMessage());
        }
    }

    private function logAdvanceAdjustment($advanceId, $oldValues, $adjustmentData, $bulkOperationId, $userId) {
        try {
            $description = "Advance adjustment - ID: $advanceId - Bulk Operation: $bulkOperationId";
            
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action_type, table_name, record_id, old_values, new_values,
                    description, module, severity, success
                ) VALUES (?, 'ADVANCE_ADJUST', 'advance_salary_enhanced', ?, ?, ?, ?, 'advance_management', 'medium', TRUE)
            ";

            $this->db->query($auditQuery, [
                $userId,
                $advanceId,
                json_encode($oldValues),
                json_encode($adjustmentData),
                $description
            ]);
        } catch (Exception $e) {
            error_log("BulkOperationManager::logAdvanceAdjustment error: " . $e->getMessage());
        }
    }
}
?>