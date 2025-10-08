<?php
// actions/salary_data_loader.php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/database.php';

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if ($month < 1 || $month > 12 || $year < 2020) {
        throw new Exception('Invalid month or year');
    }
    
    // Month formatted string only for display/joins with monthly transaction dates
    $monthFormatted = sprintf('%04d-%02d', $year, $month);
    
    // Initialize database
    $db = new Database();
    
    // Get existing salary records for the specified month/year
    $query = "
        SELECT 
            sr.id,
            sr.user_id,
            sr.month,
            sr.year,
            sr.base_salary,
            sr.calculated_salary,
            sr.deductions,
            sr.final_salary,
            sr.total_working_days,
            sr.attendance_present_days,
            sr.attendance_absent_days,
            sr.attendance_holiday_days,
            sr.attendance_double_shift_days,
            sr.attendance_multiplier_total,
            sr.auto_generated,
            sr.manually_modified,
            sr.disbursement_status,
            sr.created_at,
            u.first_name,
            u.surname,
            COALESCE(apt.adv_ded, 0) AS advance_deducted
        FROM salary_records sr
        LEFT JOIN users u ON sr.user_id = u.id
        LEFT JOIN (
            SELECT salary_record_id, SUM(amount) AS adv_ded
            FROM advance_payment_transactions
            WHERE transaction_type = 'deduction'
            GROUP BY salary_record_id
        ) apt ON apt.salary_record_id = sr.id
        WHERE sr.month = ? AND sr.year = ?
        ORDER BY u.first_name, u.surname
    ";
    
    $records = $db->query($query, [$month, $year])->fetchAll();

    // Build attendance breakdown from raw attendance for accuracy (e.g., DBL, HL, P)
    $attendanceByUser = [];
    if (!empty($records)) {
        $userIds = array_column($records, 'user_id');
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $attQuery = "
                SELECT a.user_id, COALESCE(am.code, 'UNK') AS code, COUNT(a.id) AS cnt
                FROM attendance a
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                WHERE a.user_id IN ($placeholders)
                  AND MONTH(a.attendance_date) = ?
                  AND YEAR(a.attendance_date) = ?
                GROUP BY a.user_id, am.code
            ";
            $params = array_merge($userIds, [$month, $year]);
            $attRows = $db->query($attQuery, $params)->fetchAll();
            foreach ($attRows as $row) {
                $uid = (int)$row['user_id'];
                $code = $row['code'] ?: 'UNK';
                if (!isset($attendanceByUser[$uid])) {
                    $attendanceByUser[$uid] = [];
                }
                $attendanceByUser[$uid][$code] = (int)$row['cnt'];
            }
        }
    }

    // Format the data to match the expected structure
    $formattedData = [];
    foreach ($records as $record) {
        // Prefer detailed breakdown from attendance tables; fallback to persisted P/A/H/DS columns
        $attendanceTypes = $attendanceByUser[$record['user_id']] ?? [
            'P' => $record['attendance_present_days'] ?: 0,
            'A' => $record['attendance_absent_days'] ?: 0,
            'H' => $record['attendance_holiday_days'] ?: 0,
            'DS' => $record['attendance_double_shift_days'] ?: 0
        ];

        // Use persisted multiplier from record
        $totalMultiplier = (float)($record['attendance_multiplier_total'] ?? 0);

        // Compute fallback advance deduction if no transaction rows exist
        $advDed = isset($record['advance_deducted']) && $record['advance_deducted'] > 0
            ? (float)$record['advance_deducted']
            : max(0.0, (float)$record['calculated_salary'] - (float)$record['final_salary']);
        
        // Debug logging for advance deduction
        error_log("Salary Data Loader Debug - User ID: {$record['user_id']}, Month: {$monthFormatted}");
        error_log("Advance Deducted from Transactions: " . ($record['advance_deducted'] ?? 'NULL'));
        error_log("Calculated Salary: " . ($record['calculated_salary'] ?? 'NULL'));
        error_log("Final Salary: " . ($record['final_salary'] ?? 'NULL'));
        error_log("Computed Advance Deduction: " . $advDed);
        
        $formattedData[] = [
            'id' => $record['id'],
            'user_id' => $record['user_id'],
            'full_name' => $record['first_name'] . ' ' . $record['surname'],
            'base_salary' => (float)$record['base_salary'],
            'calculated_salary' => (float)$record['calculated_salary'],
            'final_salary' => (float)$record['final_salary'],
            'total_multiplier' => round($totalMultiplier, 2),
            'attendance_types' => $attendanceTypes,
            'additional_bonuses' => 0.0,
            'deductions' => (float)($record['deductions'] ?? 0.0),
            'statutory_total' => (float)($record['deductions'] ?? 0.0),
            'advance_salary_deducted' => $advDed,
            'auto_generated' => (bool)$record['auto_generated'],
            'manually_modified' => (bool)$record['manually_modified'],
            'disbursement_status' => $record['disbursement_status'] ?? 'pending',
            'created_at' => $record['created_at']
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $formattedData;
    $response['count'] = count($formattedData);
    $response['month'] = $month;
    $response['year'] = $year;
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Salary data loader error: " . $e->getMessage());
}

echo json_encode($response);
?>