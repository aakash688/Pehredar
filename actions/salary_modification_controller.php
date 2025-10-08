<?php
// actions/salary_modification_controller.php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_record':
            $recordId = (int)($_GET['id'] ?? 0);
            if (!$recordId) {
                throw new Exception('Invalid record ID');
            }
            
            $db = new Database();
            $query = "
                SELECT 
                    sr.*,
                    u.first_name,
                    u.surname,
                    u.user_type,
                    CONCAT(u.first_name, ' ', u.surname) as full_name,
                    COALESCE(apt.net_advance_deducted, sr.advance_salary_deducted, 0) as advance_salary_deducted
                FROM salary_records sr
                LEFT JOIN users u ON sr.user_id = u.id
                LEFT JOIN (
                    SELECT 
                        salary_record_id, 
                        SUM(CASE 
                            WHEN transaction_type = 'deduction' THEN amount 
                            WHEN transaction_type = 'reversal' THEN -amount 
                            ELSE 0 
                        END) as net_advance_deducted
                    FROM advance_payment_transactions 
                    GROUP BY salary_record_id
                ) apt ON apt.salary_record_id = sr.id
                WHERE sr.id = ?
            ";
            
            $record = $db->query($query, [$recordId])->fetch();
            
            if (!$record) {
                throw new Exception('Record not found');
            }
            
            // Debug logging for advance deduction
            error_log("📊 Salary Record Debug - Record ID: {$recordId}");
            error_log("📊 Advance Salary Deducted (returned): " . ($record['advance_salary_deducted'] ?? 'NULL'));
            error_log("📊 Calculated Salary: " . ($record['calculated_salary'] ?? 'NULL'));
            error_log("📊 Final Salary: " . ($record['final_salary'] ?? 'NULL'));
            
            // Get deduction information for this salary record
            $deductionQuery = "
                SELECT 
                    sd.deduction_master_id,
                    sd.deduction_amount,
                    dm.deduction_name,
                    dm.deduction_code
                FROM salary_deductions sd
                JOIN deduction_master dm ON sd.deduction_master_id = dm.id
                WHERE sd.salary_record_id = ?
            ";
            $deductions = $db->query($deductionQuery, [$recordId])->fetchAll();
            $record['deductions_detail'] = $deductions;
            
        // Check if this salary record has ACTIVE skip requests for advance deductions
        $salaryMonth = $record['year'] . '-' . str_pad($record['month'], 2, '0', STR_PAD_LEFT);
        
        // Get ACTIVE skip records for this employee and month (only non-cancelled)
        $skipRecords = $db->query("
            SELECT sr.*, req.reason, req.approval_notes
            FROM advance_skip_records sr
            JOIN advance_skip_requests req ON sr.skip_request_id = req.id
            JOIN advance_payments ap ON sr.advance_payment_id = ap.id
            WHERE ap.employee_id = ? AND sr.skip_month = ? AND req.status = 'approved'
            ORDER BY sr.created_at DESC
        ", [$record['user_id'], $salaryMonth])->fetchAll();
            
        // Add skip information to the record
        $record['has_skip_request'] = !empty($skipRecords);
        $record['skip_records'] = $skipRecords;
        
        if (!empty($skipRecords)) {
            // Get the most recent skip record
            $latestSkip = $skipRecords[0];
            $record['skip_reason'] = $latestSkip['reason'];
            $record['skip_approval_notes'] = $latestSkip['approval_notes'];
            $record['skip_created_at'] = $latestSkip['created_at'];
        }
            
            $response['success'] = true;
            $response['data'] = $record;
            break;
            
        case 'update_record':
            try {
                // Get POST data
                $input = json_decode(file_get_contents('php://input'), true);
                
                // Debug logging
                error_log("Salary Modification Debug - Input received: " . print_r($input, true));
                
                if (!$input || !isset($input['id'])) {
                    throw new Exception('Invalid input data');
                }
            
            $recordId = (int)$input['id'];
            $additionalBonuses = (float)($input['additional_bonuses'] ?? 0);
            $deductions = (float)($input['deductions'] ?? 0);
            $advanceSalaryDeducted = (float)($input['advance_salary_deducted'] ?? 0);
            $deductionAmounts = $input['deduction_amounts'] ?? []; // New: array of deduction amounts
            $notes = $input['notes'] ?? '';
            $skipAdvanceDeduction = (bool)($input['skip_advance_deduction'] ?? false);
            $skipReason = $input['skip_reason'] ?? '';
            $removeSkip = (bool)($input['remove_skip'] ?? false);
            
            if ($recordId <= 0) {
                throw new Exception('Invalid record ID');
            }
            
            $db = new Database();
            
            // Ensure required columns exist in salary_records table
            $checkColumnsQuery = "
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'salary_records' 
                AND COLUMN_NAME IN ('modified_by', 'updated_at')
            ";
            $existingColumns = $db->query($checkColumnsQuery)->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('modified_by', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN modified_by INT NULL");
            }
            if (!in_array('updated_at', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
            
            // Get current record
            $currentRecord = $db->query("SELECT * FROM salary_records WHERE id = ? AND disbursement_status = 'pending'", [$recordId])->fetch();
            
            if (!$currentRecord) {
                throw new Exception('Record not found or already disbursed');
            }
            
            // Calculate total deduction amounts from specific deduction types
            $totalDeductionAmounts = 0;
            if (!empty($deductionAmounts)) {
                foreach ($deductionAmounts as $deductionId => $amount) {
                    $totalDeductionAmounts += (float)$amount;
                }
            }
            
            // Calculate new final salary (including specific deduction amounts)
            $newFinalSalary = $currentRecord['calculated_salary'] + $additionalBonuses - $deductions - $advanceSalaryDeducted - $totalDeductionAmounts;
            
            if ($newFinalSalary < 0) {
                throw new Exception('Final salary cannot be negative');
            }
            
            // Update record
            $updateQuery = "
                UPDATE salary_records 
                SET 
                    additional_bonuses = ?,
                    deductions = ?,
                    advance_salary_deducted = ?,
                    final_salary = ?,
                    manually_modified = TRUE,
                    modified_by = ?,
                    updated_at = NOW()
                WHERE id = ? AND disbursement_status = 'pending'
            ";
            
            // For now, using a placeholder user ID (you should get this from session)
            $modifiedBy = 1; // TODO: Get from session
            
            $result = $db->query($updateQuery, [
                $additionalBonuses,
                $deductions,
                $advanceSalaryDeducted,
                $newFinalSalary,
                $modifiedBy,
                $recordId
            ]);
            
            if ($result) {
                
                // Handle deduction amounts
                if (!empty($deductionAmounts)) {
                    // First, remove existing deduction records for this salary record
                    $db->query("DELETE FROM salary_deductions WHERE salary_record_id = ?", [$recordId]);
                    
                    // Insert new deduction amounts
                    foreach ($deductionAmounts as $deductionId => $amount) {
                        $deductionId = (int)$deductionId;
                        $amount = (float)$amount;
                        
                        if ($deductionId > 0 && $amount > 0) {
                            $db->query("
                                INSERT INTO salary_deductions (salary_record_id, deduction_master_id, deduction_amount) 
                                VALUES (?, ?, ?)
                            ", [$recordId, $deductionId, $amount]);
                        }
                    }
                }
                
                // Handle advance deduction modifications and sync with advance payment system
                // IMPORTANT: Skip this if we're processing skip/unskip, as those already handle transactions
                $skipAdvanceSync = $skipAdvanceDeduction || $removeSkip;
                
                if (!$skipAdvanceSync) {
                    error_log("💰 Processing advance deduction sync (not a skip/unskip operation)");
                    
                try {
                    // Determine the original advance deduction based on existing transactions for this salary record
                    $txSumRow = $db->query("
                        SELECT COALESCE(SUM(CASE 
                            WHEN transaction_type = 'deduction' THEN amount 
                            WHEN transaction_type = 'reversal' THEN -amount 
                            ELSE 0 END), 0) AS net_amount
                        FROM advance_payment_transactions
                        WHERE salary_record_id = ?
                    ", [$recordId])->fetch();
                    $oldAdvanceDeduction = (float)($txSumRow['net_amount'] ?? 0);
                    $newAdvanceDeduction = (float)$advanceSalaryDeducted;
                    
                    error_log("💰 Old advance deduction: ₹{$oldAdvanceDeduction}, New: ₹{$newAdvanceDeduction}");
                    
                    if ($oldAdvanceDeduction != $newAdvanceDeduction) {
                        // Get all active advances for this employee
                        $activeAdvances = $db->query("
                            SELECT id, remaining_balance, monthly_deduction, status 
                            FROM advance_payments 
                            WHERE employee_id = ? AND status IN ('approved', 'active')
                            ORDER BY created_at ASC
                        ", [$currentRecord['user_id']])->fetchAll();
                        
                        if (!empty($activeAdvances)) {
                            $delta = $newAdvanceDeduction - $oldAdvanceDeduction;
                            
                            if ($delta > 0) {
                                // Additional deduction needed (only for the delta)
                                $remainingDelta = $delta;
                                foreach ($activeAdvances as $advance) {
                                    if ($remainingDelta <= 0) break;
                                    
                                    $deductionAmount = min($remainingDelta, (float)$advance['remaining_balance']);
                                    if ($deductionAmount > 0) {
                                        // Record additional deduction transaction only for the delta
                                        $db->query("
                                            INSERT INTO advance_payment_transactions 
                                            (advance_payment_id, transaction_type, amount, installment_number, payment_date, salary_record_id, notes, processed_by) 
                                            VALUES (?, 'deduction', ?, 1, CURDATE(), ?, ?, ?)
                                        ", [
                                            $advance['id'],
                                            $deductionAmount,
                                            $recordId,
                                            'Manual salary edit - additional deduction',
                                            $modifiedBy
                                        ]);
                                        
                                        // Increment installments using floor(amount / monthly_deduction)
                                        $incrementBy = 0;
                                        $monthly = max(0.00001, (float)$advance['monthly_deduction']);
                                        $incrementBy = (int)floor(($deductionAmount + 1e-6) / $monthly);
                                        
                                        // Update advance payment remaining balance
                                        $db->query("
                                            UPDATE advance_payments 
                                            SET remaining_balance = remaining_balance - ?, 
                                                paid_installments = paid_installments + ?,
                                                status = CASE WHEN (remaining_balance - ?) <= 0 THEN 'completed' ELSE status END,
                                                completion_date = CASE WHEN (remaining_balance - ?) <= 0 THEN CURDATE() ELSE completion_date END,
                                                updated_at = NOW()
                                            WHERE id = ?
                                        ", [$deductionAmount, $incrementBy, $deductionAmount, $deductionAmount, $advance['id']]);
                                        
                                        $remainingDelta -= $deductionAmount;
                                    }
                                }
                            } elseif ($delta < 0) {
                                // Reduction in deduction (reversal) for the absolute delta only
                                $reversalAmount = abs($delta);
                                
                                // Find the most recent deduction transactions for this salary record
                                $deductionTransactions = $db->query("
                                    SELECT apt.*, ap.remaining_balance, ap.monthly_deduction
                                    FROM advance_payment_transactions apt
                                    JOIN advance_payments ap ON apt.advance_payment_id = ap.id
                                    WHERE apt.salary_record_id = ? AND apt.transaction_type = 'deduction'
                                    ORDER BY apt.id DESC
                                ", [$recordId])->fetchAll();
                                
                                $remainingReversal = $reversalAmount;
                                foreach ($deductionTransactions as $transaction) {
                                    if ($remainingReversal <= 0) break;
                                    
                                    $reversalForThisAdvance = min($remainingReversal, (float)$transaction['amount']);
                                    
                                    // Record reversal transaction
                                    $db->query("
                                        INSERT INTO advance_payment_transactions 
                                        (advance_payment_id, transaction_type, amount, installment_number, payment_date, salary_record_id, notes, processed_by) 
                                        VALUES (?, 'reversal', ?, 0, CURDATE(), ?, ?, ?)
                                    ", [
                                        $transaction['advance_payment_id'],
                                        $reversalForThisAdvance,
                                        $recordId,
                                        'Manual salary edit - deduction reversal',
                                        $modifiedBy
                                    ]);
                                    
                                    // Determine decrement count based on monthly_deduction threshold
                                    $monthly = max(0.00001, (float)$transaction['monthly_deduction']);
                                    $decrementBy = (int)floor(($reversalForThisAdvance + 1e-6) / $monthly);
                                    
                                    // Update advance payment remaining balance (increase it back)
                                    $db->query("
                                        UPDATE advance_payments 
                                        SET remaining_balance = remaining_balance + ?, 
                                            paid_installments = GREATEST(0, paid_installments - ?),
                                            status = CASE WHEN status = 'completed' THEN 'active' ELSE status END,
                                            completion_date = CASE WHEN status = 'completed' THEN NULL ELSE completion_date END,
                                            updated_at = NOW()
                                        WHERE id = ?
                                    ", [$reversalForThisAdvance, $decrementBy, $transaction['advance_payment_id']]);
                                    
                                    $remainingReversal -= $reversalForThisAdvance;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Failed to sync advance deduction modifications: ' . $e->getMessage());
                    // Don't fail the entire operation, but log the error
                }
                } else {
                    error_log("💰 Skipping advance deduction sync (skip or unskip operation in progress)");
                }

                // Handle skip advance deduction request using enhanced system
                if ($skipAdvanceDeduction && !empty($skipReason)) {
                    error_log("Processing skip advance deduction - User ID: {$currentRecord['user_id']}, Reason: {$skipReason}");
                    try {
                        require_once __DIR__ . '/../helpers/AdvanceSkipSystemEnhanced.php';
                        $skipSystem = new \Helpers\AdvanceSkipSystemEnhanced();
                        
                        // Get the salary month for the record
                        $salaryMonth = $currentRecord['year'] . '-' . str_pad($currentRecord['month'], 2, '0', STR_PAD_LEFT);
                        
                        // Get active advances for this employee
                        $activeAdvances = $db->query("
                            SELECT id FROM advance_payments 
                            WHERE employee_id = ? AND status IN ('approved', 'active')
                            ORDER BY created_at ASC
                        ", [$currentRecord['user_id']])->fetchAll();
                        
                        if (!empty($activeAdvances)) {
                            foreach ($activeAdvances as $advance) {
                                $result = $skipSystem->processSkip($advance['id'], $salaryMonth, $skipReason, $modifiedBy);
                                error_log("Skip processed for advance {$advance['id']}: " . json_encode($result));
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Failed to process skip advance deduction: ' . $e->getMessage());
                        // Don't fail the entire operation, but log the error
                    }
                }
                
                // Handle removing skip (unskip) using enhanced system
                if ($removeSkip && !$skipAdvanceDeduction) {
                    error_log("🔴 UNSKIP REQUESTED - User ID: {$currentRecord['user_id']}");
                    error_log("🔴 Remove Skip Flag: " . ($removeSkip ? 'TRUE' : 'FALSE'));
                    error_log("🔴 Skip Advance Deduction: " . ($skipAdvanceDeduction ? 'TRUE' : 'FALSE'));
                    
                    try {
                        require_once __DIR__ . '/../helpers/AdvanceSkipSystemEnhanced.php';
                        $skipSystem = new \Helpers\AdvanceSkipSystemEnhanced();
                        
                        $salaryMonth = $currentRecord['year'] . '-' . str_pad($currentRecord['month'], 2, '0', STR_PAD_LEFT);
                        error_log("🔴 Salary Month: {$salaryMonth}");
                        
                        // Get active advances for this employee
                        $activeAdvances = $db->query("
                            SELECT id FROM advance_payments 
                            WHERE employee_id = ? AND status IN ('approved', 'active')
                            ORDER BY created_at ASC
                        ", [$currentRecord['user_id']])->fetchAll();
                        
                        error_log("🔴 Active advances found: " . count($activeAdvances));
                        
                        if (!empty($activeAdvances)) {
                            foreach ($activeAdvances as $advance) {
                                error_log("🔴 Processing unskip for advance ID: {$advance['id']}");
                                $result = $skipSystem->processUnskip($advance['id'], $salaryMonth, $modifiedBy);
                                error_log("🔴 Unskip result: " . json_encode($result));
                            }
                        } else {
                            error_log("🔴 NO ACTIVE ADVANCES FOUND - This might be why unskip is not working");
                        }
                    } catch (Exception $e) {
                        error_log('🔴 UNSKIP FAILED: ' . $e->getMessage());
                        error_log('🔴 Stack trace: ' . $e->getTraceAsString());
                    }
                } else {
                    error_log("🔴 UNSKIP SKIPPED - removeSkip: " . ($removeSkip ? 'TRUE' : 'FALSE') . ", skipAdvanceDeduction: " . ($skipAdvanceDeduction ? 'TRUE' : 'FALSE'));
                }

                // Log the modification
                $logQuery = "
                    INSERT INTO salary_modification_log 
                    (salary_record_id, old_final_salary, new_final_salary, changes_made, modified_by, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ";
                
            $changes = [
                'additional_bonuses' => $additionalBonuses,
                'deductions' => $deductions,
                'advance_salary_deducted' => $advanceSalaryDeducted,
                'notes' => $notes,
                'skip_advance_deduction' => $skipAdvanceDeduction,
                'skip_reason' => $skipReason,
                'remove_skip' => $removeSkip
            ];
                
                // Create modification log table if it doesn't exist
                $createLogTable = "
                    CREATE TABLE IF NOT EXISTS salary_modification_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        salary_record_id INT NOT NULL,
                        old_final_salary DECIMAL(10,2) NOT NULL,
                        new_final_salary DECIMAL(10,2) NOT NULL,
                        changes_made JSON,
                        modified_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (salary_record_id) REFERENCES salary_records(id) ON DELETE CASCADE,
                        FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL
                    )
                ";
                
                try {
                    $db->query($createLogTable);
                    $db->query($logQuery, [
                        $recordId,
                        $currentRecord['final_salary'],
                        $newFinalSalary,
                        json_encode($changes),
                        $modifiedBy
                    ]);
                } catch (Exception $e) {
                    // Log creation failed, but salary update succeeded
                    error_log("Failed to create modification log: " . $e->getMessage());
                }
                
                $response['success'] = true;
                $response['message'] = 'Salary record updated successfully';
                $response['data'] = [
                    'new_final_salary' => $newFinalSalary,
                    'additional_bonuses' => $additionalBonuses,
                    'deductions' => $deductions,
                    'advance_salary_deducted' => $advanceSalaryDeducted,
                    'total_deduction_amounts' => $totalDeductionAmounts,
                    'unskip_processed' => $removeSkip && !$skipAdvanceDeduction,
                    'skip_processed' => $skipAdvanceDeduction && !empty($skipReason),
                    'skip_reason' => $skipReason
                ];
            } else {
                throw new Exception('Failed to update salary record');
            }
            } catch (Exception $e) {
                error_log("Salary modification update_record error: " . $e->getMessage());
                throw $e; // Re-throw to be caught by outer catch
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Salary modification error: " . $e->getMessage());
}

echo json_encode($response);
?>