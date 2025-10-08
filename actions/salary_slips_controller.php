<?php
// actions/salary_slips_controller.php - API for salary slips management

header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    // Get parameters (optional)
    $month = $_GET['month'] ?? null;
    $year = $_GET['year'] ?? null;

    $params = [];
    if ($month && $year) {
        // Use numeric month/year filters (schema stores month as INT and year as INT)
        $query = "
            SELECT 
                sr.id,
                sr.user_id,
                sr.month,
                sr.year,
                sr.base_salary,
                sr.calculated_salary,
                sr.additional_bonuses,
                sr.deductions,
                sr.advance_salary_deducted,
                sr.final_salary,
                sr.auto_generated,
                sr.manually_modified,
                sr.disbursement_status,
                sr.disbursed_at,
                sr.created_at,
                CONCAT(u.first_name, ' ', u.surname) as full_name,
                u.first_name,
                u.surname,
                u.user_type
            FROM 
                salary_records sr
            LEFT JOIN 
                users u ON sr.user_id = u.id
            WHERE 
                sr.month = ? AND sr.year = ?
            ORDER BY 
                sr.created_at DESC, u.first_name, u.surname
        ";
        $params[] = (int)$month;
        $params[] = (int)$year;
    } else {
        // No month/year provided: return all records
        $query = "
            SELECT 
                sr.id,
                sr.user_id,
                sr.month,
                sr.year,
                sr.base_salary,
                sr.calculated_salary,
                sr.additional_bonuses,
                sr.deductions,
                sr.advance_salary_deducted,
                sr.final_salary,
                sr.auto_generated,
                sr.manually_modified,
                sr.disbursement_status,
                sr.disbursed_at,
                sr.created_at,
                CONCAT(u.first_name, ' ', u.surname) as full_name,
                u.first_name,
                u.surname,
                u.user_type
            FROM 
                salary_records sr
            LEFT JOIN 
                users u ON sr.user_id = u.id
            ORDER BY 
                sr.created_at DESC, sr.year DESC, sr.month DESC
        ";
    }

    $stmt = $db->query($query, $params);
    $records = $stmt->fetchAll();
    
    // Process records for frontend display
    $processedRecords = array_map(function($record) {
        $monthInt = (int)($record['month'] ?? 0);
        $yearInt = (int)($record['year'] ?? 0);
        $monthStr = ($monthInt >= 1 && $monthInt <= 12 && $yearInt > 0)
            ? sprintf('%04d-%02d', $yearInt, $monthInt)
            : (string)($record['month'] ?? '');
        return [
            'id' => $record['id'],
            'user_id' => $record['user_id'],
            'month' => $monthStr,
            'year' => $yearInt,
            'full_name' => $record['full_name'],
            'base_salary' => (float)$record['base_salary'],
            'calculated_salary' => (float)$record['calculated_salary'],
            'additional_bonuses' => (float)$record['additional_bonuses'],
            'deductions' => (float)$record['deductions'],
            'advance_salary_deducted' => (float)$record['advance_salary_deducted'],
            'final_salary' => (float)$record['final_salary'],
            'auto_generated' => (bool)$record['auto_generated'],
            'manually_modified' => (bool)$record['manually_modified'],
            'disbursement_status' => $record['disbursement_status'],
            'disbursed_at' => $record['disbursed_at'],
            'created_at' => $record['created_at']
        ];
    }, $records);
    
    echo json_encode([
        'success' => true,
        'message' => count($records) . ' salary records found',
        'data' => $processedRecords,
        'summary' => [
            'total_records' => count($records),
            'total_base_salary' => array_sum(array_column($processedRecords, 'base_salary')),
            'total_final_salary' => array_sum(array_column($processedRecords, 'final_salary')),
            'total_advance_deducted' => array_sum(array_column($processedRecords, 'advance_salary_deducted')),
            'disbursed_count' => count(array_filter($records, function($r) { return $r['disbursement_status'] === 'disbursed'; })),
            'pending_count' => count(array_filter($records, function($r) { return $r['disbursement_status'] === 'pending'; }))
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Salary Slips Controller Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching salary slips',
        'error' => $e->getMessage(),
        'data' => []
    ]);
}
?>