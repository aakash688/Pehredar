<?php
// actions/bulk_deduction_controller.php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = new Database();

try {
    switch ($action) {
        case 'get_employees':
            getEmployeesForBulkDeduction($db);
            break;
        case 'apply_bulk_deduction':
            applyBulkDeduction($db);
            break;
        default:
            sendJsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}

function getEmployeesForBulkDeduction($db) {
    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';
    
    if (empty($month) || empty($year)) {
        sendJsonResponse(false, 'Month and year are required');
        return;
    }
    
    $query = "
        SELECT 
            sr.id as salary_record_id,
            sr.user_id,
            u.first_name,
            u.surname,
            u.user_type,
            sr.final_salary,
            sr.disbursement_status,
            CASE 
                WHEN sr.disbursement_status = 'pending' THEN 'Pending'
                WHEN sr.disbursement_status = 'disbursed' THEN 'Disbursed'
                ELSE 'Unknown'
            END as status_label
        FROM salary_records sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.month = ? AND sr.year = ?
        ORDER BY sr.disbursement_status, u.first_name, u.surname
    ";
    
    $employees = $db->query($query, [$month, $year])->fetchAll();
    sendJsonResponse(true, 'Employees retrieved successfully', $employees);
}

function applyBulkDeduction($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, 'Invalid JSON data');
        return;
    }
    
    $salaryRecordIds = $input['salary_record_ids'] ?? [];
    $deductionMasterId = (int)($input['deduction_master_id'] ?? 0);
    $deductionAmount = (float)($input['deduction_amount'] ?? 0);
    $notes = $input['notes'] ?? '';
    
    if (empty($salaryRecordIds)) {
        sendJsonResponse(false, 'No salary records selected');
        return;
    }
    
    if ($deductionMasterId <= 0) {
        sendJsonResponse(false, 'Invalid deduction type');
        return;
    }
    
    if ($deductionAmount <= 0) {
        sendJsonResponse(false, 'Deduction amount must be greater than 0');
        return;
    }
    
    // Verify deduction type exists and is active
    $deductionQuery = "SELECT id, deduction_name FROM deduction_master WHERE id = ? AND is_active = 1";
    $deduction = $db->query($deductionQuery, [$deductionMasterId])->fetch();
    
    if (!$deduction) {
        sendJsonResponse(false, 'Deduction type not found or inactive');
        return;
    }
    
    $successCount = 0;
    $errors = [];
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        foreach ($salaryRecordIds as $salaryRecordId) {
            $salaryRecordId = (int)$salaryRecordId;
            
            // Check if salary record exists
            $salaryQuery = "SELECT id, final_salary, disbursement_status FROM salary_records WHERE id = ?";
            $salaryRecord = $db->query($salaryQuery, [$salaryRecordId])->fetch();
            
            if (!$salaryRecord) {
                $errors[] = "Salary record {$salaryRecordId} not found";
                continue;
            }
            
            // Check if deduction already exists for this salary record
            $existingQuery = "SELECT id FROM salary_deductions WHERE salary_record_id = ? AND deduction_master_id = ?";
            $existing = $db->query($existingQuery, [$salaryRecordId, $deductionMasterId])->fetch();
            
            if ($existing) {
                // Update existing deduction
                $updateQuery = "UPDATE salary_deductions SET deduction_amount = ? WHERE id = ?";
                $db->query($updateQuery, [$deductionAmount, $existing['id']]);
            } else {
                // Insert new deduction
                $insertQuery = "INSERT INTO salary_deductions (salary_record_id, deduction_master_id, deduction_amount) VALUES (?, ?, ?)";
                $db->query($insertQuery, [$salaryRecordId, $deductionMasterId, $deductionAmount]);
            }
            
            // Update final salary
            $newFinalSalary = $salaryRecord['final_salary'] - $deductionAmount;
            if ($newFinalSalary < 0) {
                $errors[] = "Salary record {$salaryRecordId} would result in negative salary";
                continue;
            }
            
            $updateSalaryQuery = "UPDATE salary_records SET final_salary = ?, manually_modified = TRUE WHERE id = ?";
            $db->query($updateSalaryQuery, [$newFinalSalary, $salaryRecordId]);
            
            $successCount++;
        }
        
        // Log bulk deduction
        $logQuery = "
            INSERT INTO bulk_deduction_log 
            (deduction_master_id, deduction_amount, salary_record_count, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        // Create log table if it doesn't exist
        $createLogTable = "
            CREATE TABLE IF NOT EXISTS bulk_deduction_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                deduction_master_id INT NOT NULL,
                deduction_amount DECIMAL(10,2) NOT NULL,
                salary_record_count INT NOT NULL,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (deduction_master_id) REFERENCES deduction_master(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        try {
            $db->query($createLogTable);
            $db->query($logQuery, [$deductionMasterId, $deductionAmount, $successCount, $notes, 1]); // TODO: Get from session
        } catch (Exception $e) {
            // Log creation failed, but continue
            error_log("Failed to create bulk deduction log: " . $e->getMessage());
        }
        
        $db->commit();
        
        $message = "Bulk deduction applied successfully to {$successCount} employee(s)";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        
        sendJsonResponse(true, $message, [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
