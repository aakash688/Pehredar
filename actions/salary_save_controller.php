<?php
// actions/salary_save_controller.php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';
@session_start();

// Ensure a JSON error is returned even on fatal errors
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line']
        ]);
    }
});

// Enable error reporting for debugging (can be removed in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Get POST data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    if (!$input || !isset($input['salaryData']) || !isset($input['month']) || !isset($input['year'])) {
        error_log("Input validation failed. Input: " . print_r($input, true));
        throw new Exception('Invalid input data - missing salaryData, month, or year');
    }
    
    $salaryData = $input['salaryData'];
    $month = (int)$input['month'];
    $year = (int)$input['year'];
    
    if (empty($salaryData) || $month < 1 || $month > 12 || $year < 2020) {
        throw new Exception('Invalid salary data, month, or year');
    }
    
    // Month formatted string for related transaction dates; salary_records stores month int and year int
    $monthFormatted = sprintf('%04d-%02d', $year, $month);
    
    // Initialize database
    $db = new Database();
    
    // Detect optional columns to keep compatibility with older schemas
    $hasTotalDaysInMonthCol = false;
    $hasCreatedByCol = false;
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM salary_records LIKE 'total_days_in_month'")->fetch();
        $hasTotalDaysInMonthCol = (bool)$colCheck;
    } catch (Exception $e) {
        // Ignore detection failure; proceed without optional column
        $hasTotalDaysInMonthCol = false;
    }
    try {
        $colCheck2 = $db->query("SHOW COLUMNS FROM salary_records LIKE 'created_by'")->fetch();
        $hasCreatedByCol = (bool)$colCheck2;
    } catch (Exception $e) {
        $hasCreatedByCol = false;
    }
    
    // Start transaction
    if (!$db->beginTransaction()) {
        throw new Exception('Failed to start database transaction');
    }
    
    $savedCount = 0;
    $skippedCount = 0;
    $errors = [];
    
    foreach ($salaryData as $employee) {
        try {
            // Check if salary record already exists for this user/month/year
            $existingQuery = "SELECT id FROM salary_records WHERE user_id = ? AND month = ? AND year = ?";
            $existing = $db->query($existingQuery, [$employee['user_id'], $month, $year])->fetch();
            
            if ($existing) {
                $skippedCount++;
                continue; // Skip if already exists
            }
            
            // Calculate final salary and include advance deduction tag
            $finalSalary = $employee['final_salary'] ?? $employee['calculated_salary'];
            $statutoryTotal = (float)($employee['statutory_total'] ?? 0);
            $advanceDeductTotal = (float)($employee['advance_deduction'] ?? 0);
            
            // RECALCULATE statutory deductions if missing (fallback)
            if ($statutoryTotal == 0 && isset($employee['calculated_salary']) && $employee['calculated_salary'] > 0) {
                $calculatedSalary = (float)$employee['calculated_salary'];
                $salaryMonth = sprintf('%04d-%02d', $year, $month);
                
                // Get statutory deductions for this month  
                // Modified to be more flexible for retroactive calculations
                $statutory = $db->query("
                    SELECT name, is_percentage, value, affects_net, scope
                    FROM statutory_deductions
                    WHERE is_active = 1 AND (
                        active_from_month <= ? OR 
                        active_from_month <= DATE_FORMAT(CURDATE(), '%Y-%m')
                    )
                ", [$salaryMonth])->fetchAll();
                
                $recalculatedStatutory = 0.0;
                foreach ($statutory as $s) {
                    $amt = $s['is_percentage'] ? ($calculatedSalary * ((float)$s['value'] / 100.0)) : (float)$s['value'];
                    $amt = round($amt, 2);
                    
                    // Only add to total if it affects net salary (employee deductions)
                    if ((bool)$s['affects_net']) {
                        $recalculatedStatutory += $amt;
                    }
                }
                
                if ($recalculatedStatutory > 0) {
                    $statutoryTotal = $recalculatedStatutory;
                }
            }
            
            // Insert salary record (optionally include total_days_in_month if column exists)
            $insertQuery = "
                INSERT INTO salary_records (
                    user_id, month, year, base_salary, 
                    total_working_days, attendance_present_days, attendance_absent_days, 
                    attendance_holiday_days, attendance_double_shift_days, 
                    attendance_multiplier_total, calculated_salary, additional_bonuses, deductions, advance_salary_deducted, final_salary" .
                    ($hasTotalDaysInMonthCol ? ", total_days_in_month" : "") .
                    ($hasCreatedByCol ? ", created_by" : "") . ",
                    auto_generated, manually_modified, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?" .
                    ($hasTotalDaysInMonthCol ? ", ?" : "") .
                    ($hasCreatedByCol ? ", ?" : "") .
                    ", TRUE, FALSE, NOW())
            ";
            
            // Count attendance types
            $presentDays = $employee['attendance_types']['P'] ?? 0;
            $absentDays = $employee['attendance_types']['A'] ?? 0;
            $holidayDays = $employee['attendance_types']['H'] ?? 0;
            $doubleShiftDays = $employee['attendance_types']['DS'] ?? 0;
            $totalWorkingDays = $presentDays + $absentDays + $holidayDays + $doubleShiftDays;
            
            $params = [
                $employee['user_id'],
                $month,
                $year,
                $employee['base_salary'],
                $totalWorkingDays,
                $presentDays,
                $absentDays,
                $holidayDays,
                $doubleShiftDays,
                $employee['total_multiplier'],
                $employee['calculated_salary'],
                $statutoryTotal,
                $advanceDeductTotal,
                $finalSalary
            ];
            if ($hasTotalDaysInMonthCol) {
                $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
                $params[] = $daysInMonth;
            }
            if ($hasCreatedByCol) {
                $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
                $params[] = $currentUserId;
            }
            
            $result = $db->query($insertQuery, $params);
            
            if ($result) {
                $savedCount++;
                $salaryRecordId = $db->getPdo()->lastInsertId();

                // ENHANCED: Process advance payment deductions and update advance tables
                if ($advanceDeductTotal > 0) {
                    error_log("🔄 Processing advance deduction for User ID: {$employee['user_id']}, Amount: {$advanceDeductTotal}");
                    
                    try {
                        // Get all active advance payments for this user
                        $activeAdvances = $db->query(
                            "SELECT id, remaining_balance, monthly_deduction, status, amount 
                             FROM advance_payments 
                             WHERE employee_id = ? AND status IN ('active', 'approved') AND remaining_balance > 0 
                             ORDER BY created_at ASC",
                            [$employee['user_id']]
                        )->fetchAll();
                        
                        if (!empty($activeAdvances)) {
                            $remainingDeduction = $advanceDeductTotal;
                            $processedAdvances = [];
                            
                            foreach ($activeAdvances as $advance) {
                                if ($remainingDeduction <= 0) break;
                                
                                // Calculate how much to deduct from this advance
                                $deductionFromThisAdvance = min($remainingDeduction, (float)$advance['remaining_balance']);
                                
                                if ($deductionFromThisAdvance > 0) {
                                    // Check if transaction already exists to avoid duplicates
                                    $existingTx = $db->query(
                                        "SELECT id FROM advance_payment_transactions 
                                         WHERE advance_payment_id = ? AND salary_record_id = ? AND transaction_type = 'deduction'",
                                        [$advance['id'], $salaryRecordId]
                                    )->fetch();
                                    
                                    if (!$existingTx) {
                                        // Prepare notes with month and year information
                                        $notesText = "Salary deduction for " . date('F Y', strtotime($monthFormatted . '-01'));
                                        
                                    // Insert advance payment transaction with proper salary month date
                                    $salaryMonthDate = date('Y-m-d', strtotime($monthFormatted . '-01'));
                                    $insertResult = $db->query(
                                        "INSERT INTO advance_payment_transactions 
                                         (advance_payment_id, transaction_type, amount, installment_number, payment_date, salary_record_id, notes, processed_by) 
                                         VALUES (?, 'deduction', ?, 1, ?, ?, ?, ?)",
                                        [
                                            $advance['id'],
                                            $deductionFromThisAdvance,
                                            $salaryMonthDate,
                                            $salaryRecordId,
                                            $notesText,
                                            $_SESSION['user_id'] ?? 1
                                        ]
                                    );
                                        
                                        if ($insertResult) {
                                            error_log("✅ Advance transaction inserted: Advance ID {$advance['id']}, Amount {$deductionFromThisAdvance}");
                                            
                                            // Calculate new balance and installments
                                            $newBalance = (float)$advance['remaining_balance'] - $deductionFromThisAdvance;
                                            $monthlyAmt = max(0.00001, (float)$advance['monthly_deduction']);
                                            $shouldIncrement = (int)floor(($deductionFromThisAdvance + 1e-6) / $monthlyAmt);
                                            
                                            // Update advance payment with new balance and status
                                            $updateResult = $db->query(
                                                "UPDATE advance_payments 
                                                 SET remaining_balance = ?, 
                                                     paid_installments = paid_installments + ?, 
                                                     status = CASE WHEN ? <= 0 THEN 'completed' ELSE 'active' END, 
                                                     completion_date = CASE WHEN ? <= 0 THEN CURDATE() ELSE completion_date END, 
                                                     updated_at = NOW() 
                                                 WHERE id = ?",
                                                [$newBalance, $shouldIncrement, $newBalance, $newBalance, $advance['id']]
                                            );
                                            
                                            if ($updateResult) {
                                                error_log("✅ Advance payment updated: ID {$advance['id']}, New Balance: {$newBalance}, Status: " . ($newBalance <= 0 ? 'completed' : 'active'));
                                                $processedAdvances[] = [
                                                    'id' => $advance['id'],
                                                    'deducted' => $deductionFromThisAdvance,
                                                    'new_balance' => $newBalance,
                                                    'status' => $newBalance <= 0 ? 'completed' : 'active'
                                                ];
                                            } else {
                                                error_log("❌ Failed to update advance payment for ID {$advance['id']}");
                                            }
                                            
                                            $remainingDeduction -= $deductionFromThisAdvance;
                                        } else {
                                            error_log("❌ Failed to insert advance transaction for Advance ID {$advance['id']}");
                                        }
                                    } else {
                                        error_log("⚠️ Transaction already exists for Advance ID {$advance['id']}, Salary Record ID {$salaryRecordId}");
                                    }
                                }
                            }
                            
                            // Log summary
                            error_log("📊 Advance Deduction Summary for User {$employee['user_id']}:");
                            error_log("   - Total Deduction: {$advanceDeductTotal}");
                            error_log("   - Processed Advances: " . count($processedAdvances));
                            error_log("   - Remaining Unallocated: {$remainingDeduction}");
                            
                            foreach ($processedAdvances as $processed) {
                                error_log("   - Advance ID {$processed['id']}: Deducted {$processed['deducted']}, New Balance: {$processed['new_balance']}, Status: {$processed['status']}");
                            }
                            
                            if ($remainingDeduction > 0) {
                                error_log("⚠️ Warning: {$remainingDeduction} amount could not be allocated to any advance payment");
                            }
                        } else {
                            error_log("❌ No active advance payments found for User ID: {$employee['user_id']} despite advance deduction amount: {$advanceDeductTotal}");
                        }
                    } catch (Exception $e) {
                        error_log("❌ Error processing advance deductions for User {$employee['user_id']}: " . $e->getMessage());
                        // Don't fail the entire salary save, but log the error
                    }
                } else {
                    error_log("ℹ️ No advance deduction for User ID: {$employee['user_id']}");
                }
            } else {
                $errors[] = "Failed to save salary for employee ID: " . $employee['user_id'];
            }
            
        } catch (Exception $e) {
            $errors[] = "Error saving employee ID " . $employee['user_id'] . ": " . $e->getMessage();
        }
    }
    
    // Commit transaction if at least one record was saved
    if ($savedCount > 0) {
        $db->commit();
        
        $message = "Successfully saved {$savedCount} salary records";
        if ($skippedCount > 0) {
            $message .= " (skipped {$skippedCount} existing records)";
        }
        if (!empty($errors)) {
            $message .= ". Some errors occurred: " . implode('; ', array_slice($errors, 0, 3));
        }
        
        $response['success'] = true;
        $response['message'] = $message;
        $response['data'] = [
            'saved' => $savedCount,
            'skipped' => $skippedCount,
            'errors' => count($errors)
        ];
    } else {
        $db->rollBack();
        
        if ($skippedCount > 0) {
            $response['message'] = "All {$skippedCount} salary records already exist for {$monthFormatted}";
        } else {
            $response['message'] = 'No salary records were saved. ' . implode('; ', $errors);
        }
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        try {
            // Check if we're in a transaction before rolling back
            if (method_exists($db, 'getPdo') && $db->getPdo()->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $rollbackException) {
            error_log("Rollback failed: " . $rollbackException->getMessage());
        }
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Salary save error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
}

echo json_encode($response);
?>