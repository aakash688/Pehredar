<?php
// actions/salary_disbursement_controller.php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'disburse_single':
            $recordId = (int)($_GET['id'] ?? 0);
            if (!$recordId) {
                throw new Exception('Invalid record ID');
            }
            
            $db = new Database();
            
            // Ensure required columns exist in salary_records table
            $checkColumnsQuery = "
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'salary_records' 
                AND COLUMN_NAME IN ('disbursed_by', 'disbursed_at', 'updated_at')
            ";
            $existingColumns = $db->query($checkColumnsQuery)->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('disbursed_by', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN disbursed_by INT NULL");
            }
            if (!in_array('disbursed_at', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN disbursed_at TIMESTAMP NULL");
            }
            if (!in_array('updated_at', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
            
            // Get record details
            $record = $db->query("
                SELECT sr.*, u.first_name, u.surname 
                FROM salary_records sr 
                LEFT JOIN users u ON sr.user_id = u.id 
                WHERE sr.id = ? AND sr.disbursement_status = 'pending'
            ", [$recordId])->fetch();
            
            if (!$record) {
                throw new Exception('Record not found or already disbursed');
            }
            
            // Update disbursement status
            $updateQuery = "
                UPDATE salary_records 
                SET 
                    disbursement_status = 'disbursed',
                    disbursed_by = ?,
                    disbursed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND disbursement_status = 'pending'
            ";
            
            // For now, using a placeholder user ID (you should get this from session)
            $disbursedBy = 1; // TODO: Get from session
            
            $result = $db->query($updateQuery, [$disbursedBy, $recordId]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = "Salary disbursed successfully for {$record['first_name']} {$record['surname']}";
                $response['data'] = [
                    'record_id' => $recordId,
                    'employee_name' => $record['first_name'] . ' ' . $record['surname'],
                    'amount' => $record['final_salary'],
                    'disbursed_at' => date('Y-m-d H:i:s')
                ];
            } else {
                throw new Exception('Failed to update disbursement status');
            }
            break;
            
        case 'disburse_bulk':
            // Get POST data for bulk disbursement
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['record_ids']) || !is_array($input['record_ids'])) {
                throw new Exception('Invalid input data - record IDs required');
            }
            
            $recordIds = array_map('intval', $input['record_ids']);
            $monthYear = $input['month_year'] ?? null;
            
            if (empty($recordIds)) {
                throw new Exception('No records selected for disbursement');
            }
            
            $db = new Database();
            
            // Ensure required columns exist in salary_records table
            $checkColumnsQuery = "
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'salary_records' 
                AND COLUMN_NAME IN ('disbursed_by', 'disbursed_at', 'updated_at')
            ";
            $existingColumns = $db->query($checkColumnsQuery)->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('disbursed_by', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN disbursed_by INT NULL");
            }
            if (!in_array('disbursed_at', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN disbursed_at TIMESTAMP NULL");
            }
            if (!in_array('updated_at', $existingColumns)) {
                $db->query("ALTER TABLE salary_records ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
            
            // Build query to get pending records
            $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
            $whereClause = "sr.id IN ($placeholders) AND sr.disbursement_status = 'pending'";
            $params = $recordIds;
            
            // Add month/year filter if provided
            if ($monthYear) {
                list($year, $month) = explode('-', $monthYear);
                $whereClause .= " AND sr.year = ? AND sr.month = ?";
                $params[] = $year;
                $params[] = $month;
            }
            
            // Get records to disburse
            $records = $db->query("
                SELECT sr.id, sr.final_salary, u.first_name, u.surname 
                FROM salary_records sr 
                LEFT JOIN users u ON sr.user_id = u.id 
                WHERE $whereClause
            ", $params)->fetchAll();
            
            if (empty($records)) {
                throw new Exception('No eligible records found for disbursement');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            $disbursedCount = 0;
            $totalAmount = 0;
            $disbursedBy = 1; // TODO: Get from session
            
            foreach ($records as $record) {
                $updateResult = $db->query("
                    UPDATE salary_records 
                    SET 
                        disbursement_status = 'disbursed',
                        disbursed_by = ?,
                        disbursed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND disbursement_status = 'pending'
                ", [$disbursedBy, $record['id']]);
                
                if ($updateResult) {
                    $disbursedCount++;
                    $totalAmount += $record['final_salary'];
                }
            }
            
            // Commit transaction
            $db->commit();
            
            $response['success'] = true;
            $response['message'] = "Successfully disbursed {$disbursedCount} salary records";
            $response['data'] = [
                'disbursed_count' => $disbursedCount,
                'total_amount' => $totalAmount,
                'disbursed_at' => date('Y-m-d H:i:s')
            ];
            break;
            
        case 'get_pending_records':
            $monthYear = $_GET['month_year'] ?? null;
            
            $db = new Database();
            
            $whereClause = "sr.disbursement_status = 'pending'";
            $params = [];
            
            if ($monthYear) {
                list($year, $month) = explode('-', $monthYear);
                $whereClause .= " AND sr.year = ? AND sr.month = ?";
                $params[] = $year;
                $params[] = $month;
            }
            
            $records = $db->query("
                SELECT sr.id, sr.final_salary, sr.month, sr.year, u.first_name, u.surname 
                FROM salary_records sr 
                LEFT JOIN users u ON sr.user_id = u.id 
                WHERE $whereClause
                ORDER BY u.first_name, u.surname
            ", $params)->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $records;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Salary disbursement error: " . $e->getMessage());
}

echo json_encode($response);
?>