<?php
// Salary Calculation Controller
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/AdvanceSalaryIntegrator.php';

class SalaryCalculationController {
    private $db;
    private $advanceIntegrator;

    public function __construct() {
        $this->db = new Database();
        $this->advanceIntegrator = new \Helpers\AdvanceSalaryIntegrator();
    }

    /**
     * Retrieve attendance data for salary calculation
     * @param int $month Month to calculate salary for
     * @param int $year Year to calculate salary for
     * @return array Attendance data with multipliers
     */
    public function getAttendanceData($month, $year) {
        $query = "
            SELECT 
                u.id AS user_id,
                u.first_name,
                u.surname,
                u.salary,
                am.code,
                am.multiplier,
                COUNT(a.id) AS attendance_count
            FROM 
                users u
            LEFT JOIN 
                attendance a ON u.id = a.user_id 
                AND MONTH(a.attendance_date) = ? 
                AND YEAR(a.attendance_date) = ?
            LEFT JOIN 
                attendance_master am ON a.attendance_master_id = am.id
            WHERE 
                u.salary > 0
            GROUP BY 
                u.id, u.first_name, u.surname, u.salary, am.code, am.multiplier
            HAVING 
                attendance_count > 0
        ";

        try {
            $stmt = $this->db->query($query, [$month, $year]);
            $results = $stmt->fetchAll();
            
            // Debug logging
            error_log("Salary Calculation Debug:");
            error_log("Month: $month, Year: $year");
            error_log("Raw Attendance Data: " . json_encode($results));
            
            return $results;
        } catch (PDOException $e) {
            error_log("Salary Calculation Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate salary based on attendance data
     * @param array $attendanceData Raw attendance data
     * @return array Calculated salary for each employee
     */
    public function calculateSalary($attendanceData, $salaryMonth = null) {
        $salaryResults = [];
        $employeeAttendance = [];

        // If no month provided, use current month
        if (!$salaryMonth) {
            $salaryMonth = date('Y-m');
        }

        // Group attendance by user
        foreach ($attendanceData as $record) {
            $userId = $record['user_id'];
            if (!isset($employeeAttendance[$userId])) {
                $employeeAttendance[$userId] = [
                    'user_id' => $userId,
                    'first_name' => $record['first_name'],
                    'surname' => $record['surname'],
                    'salary' => $record['salary'],
                    'attendance_types' => [],
                    'total_multiplier' => 0
                ];
            }

            if ($record['code']) {
                $employeeAttendance[$userId]['attendance_types'][$record['code']] = 
                    ($employeeAttendance[$userId]['attendance_types'][$record['code']] ?? 0) + $record['attendance_count'];
                $employeeAttendance[$userId]['total_multiplier'] += 
                    $record['attendance_count'] * $record['multiplier'];
            }
        }

        // Calculate final salary with advance deductions
        foreach ($employeeAttendance as $employee) {
            $dailySalary = $employee['salary'] / 30; // Assuming 30 days per month
            $calculatedSalary = $dailySalary * $employee['total_multiplier'];
            $userId = $employee['user_id'];

            // Get employee advance status for visual indicators
            $advanceStatus = $this->advanceIntegrator->getEmployeeAdvanceStatus($userId);
            
            // Calculate advance deduction if employee has active advances
            $advanceDeduction = 0;
            $advanceDetails = [];
            
            if ($advanceStatus['has_advance']) {
                $deductionResult = $this->advanceIntegrator->processAdvanceDeduction(
                    $userId, 
                    $calculatedSalary, 
                    $salaryMonth
                );
                
                if ($deductionResult['success']) {
                    $advanceDeduction = $deductionResult['total_deduction'];
                    $advanceDetails = $deductionResult['deductions'];
                }
            }

            // Statutory deductions (PT, ESIC, PF etc.) active for salaryMonth
            // Modified to be more flexible for retroactive calculations
            $statutory = $this->db->query("
                SELECT name, is_percentage, value, affects_net, scope
                FROM statutory_deductions
                WHERE is_active = 1 AND (
                    active_from_month <= ? OR 
                    active_from_month <= DATE_FORMAT(CURDATE(), '%Y-%m')
                )
            ", [$salaryMonth])->fetchAll();
            // Sum all active statutory items into deductions (percentage is applied on calculated salary)
            $statutoryTotal = 0.0;
            $statutoryBreakdown = [];
            foreach ($statutory as $s) {
                $amt = $s['is_percentage'] ? ($calculatedSalary * ((float)$s['value'] / 100.0)) : (float)$s['value'];
                $amt = round($amt, 2);
                $statutoryBreakdown[] = [
                    'name' => $s['name'],
                    'amount' => $amt,
                    'affects_net' => (bool)$s['affects_net'],
                    'scope' => $s['scope']
                ];
                // Only add to total if it affects net salary (employee deductions)
                if ((bool)$s['affects_net']) {
                    $statutoryTotal += $amt;
                }
            }
            
            // Calculate final salary after statutory and advance deduction (all active statutory items reduce net)
            $finalSalary = $calculatedSalary - $statutoryTotal - $advanceDeduction;

            $salaryResults[] = [
                'user_id' => $userId,
                'full_name' => $employee['first_name'] . ' ' . $employee['surname'],
                'base_salary' => $employee['salary'],
                'attendance_types' => $employee['attendance_types'],
                'total_multiplier' => $employee['total_multiplier'],
                'calculated_salary' => round($calculatedSalary, 2),
                'statutory_deductions' => $statutoryBreakdown,
                'statutory_total' => round($statutoryTotal, 2),
                'advance_deduction' => round($advanceDeduction, 2),
                'final_salary' => round($finalSalary, 2),
                // Advance status for visual indicators
                'has_advance' => $advanceStatus['has_advance'],
                'advance_status' => $advanceStatus['status'],
                'advance_visual_class' => $advanceStatus['visual_class'],
                'advance_indicator_class' => $advanceStatus['indicator_class'],
                'advance_badge_text' => $advanceStatus['badge_text'],
                'advance_count' => $advanceStatus['advance_count'],
                'total_outstanding' => $advanceStatus['total_outstanding'],
                'advance_details' => $advanceDetails,
                // Mark as auto-generated for salary records
                'auto_generated' => true,
                'manually_modified' => false
            ];
        }

        // Debug logging
        error_log("Enhanced Salary Calculation Results with Advance Deductions:");
        error_log("Month: $salaryMonth");
        error_log("Results Count: " . count($salaryResults));

        return $salaryResults;
    }

    /**
     * Main method to generate salary for a specific month and year
     * @param int $month Month to calculate salary for
     * @param int $year Year to calculate salary for
     * @return array Salary calculation results
     */
    public function generateSalary($month, $year) {
        $attendanceData = $this->getAttendanceData($month, $year);
        
        if (empty($attendanceData)) {
            // Additional debugging for empty results
            $this->debugEmptyResults($month, $year);
        }
        
        // Filter out users who already have salary records for this month/year
        $filteredAttendanceData = $this->filterExistingRecords($attendanceData, $month, $year);
        
        // Format month for salary calculation (YYYY-MM format)
        $salaryMonth = sprintf('%04d-%02d', $year, $month);
        
        return $this->calculateSalary($filteredAttendanceData, $salaryMonth);
    }

    /**
     * Filter out users who already have salary records for the given month/year
     * @param array $attendanceData Raw attendance data
     * @param int $month Month to check
     * @param int $year Year to check
     * @return array Filtered attendance data
     */
    private function filterExistingRecords($attendanceData, $month, $year) {
        // Format month as YYYY-MM for database query
        $monthFormatted = sprintf('%04d-%02d', $year, $month);
        
        // Get users who already have salary records for this month
        $existingQuery = "SELECT DISTINCT user_id FROM salary_records WHERE month = ? AND year = ?";
        $existingStmt = $this->db->query($existingQuery, [$month, $year]);
        $existingUsers = $existingStmt->fetchAll();
        
        // Create array of existing user IDs
        $existingUserIds = array_column($existingUsers, 'user_id');
        
        // Filter out attendance data for existing users
        $filteredData = [];
        $skippedCount = 0;
        
        foreach ($attendanceData as $record) {
            if (!in_array($record['user_id'], $existingUserIds)) {
                $filteredData[] = $record;
            } else {
                $skippedCount++;
            }
        }
        
        // Log the filtering results
        error_log("Filtered attendance data: " . count($filteredData) . " records remaining, " . $skippedCount . " records skipped (already have salary records)");
        
        return $filteredData;
    }

    /**
     * Debug method to investigate empty results
     * @param int $month Month to investigate
     * @param int $year Year to investigate
     */
    private function debugEmptyResults($month, $year) {
        // Check users
        $userQuery = "SELECT COUNT(*) as user_count FROM users WHERE salary > 0";
        $userStmt = $this->db->query($userQuery);
        $userCount = $userStmt->fetch()['user_count'];
        error_log("Total Users with Salary: $userCount");

        // Check attendance records for the specific month and year
        $attendanceQuery = "
            SELECT 
                COUNT(*) as attendance_count,
                COUNT(DISTINCT user_id) as unique_users
            FROM 
                attendance 
            WHERE 
                MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
        ";
        $attendanceStmt = $this->db->query($attendanceQuery, [$month, $year]);
        $attendanceData = $attendanceStmt->fetch();
        
        error_log("Attendance Records for $month/$year:");
        error_log("Total Attendance Records: " . $attendanceData['attendance_count']);
        error_log("Unique Users with Attendance: " . $attendanceData['unique_users']);

        // Check attendance master
        $attendanceMasterQuery = "SELECT * FROM attendance_master";
        $attendanceMasterStmt = $this->db->query($attendanceMasterQuery);
        $attendanceMasterData = $attendanceMasterStmt->fetchAll();
        error_log("Attendance Master Data: " . json_encode($attendanceMasterData));
    }
}

// Handle API requests if this file is directly accessed
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');

    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $controller = new SalaryCalculationController();
        $salaryData = $controller->generateSalary($month, $year);

        echo json_encode([
            'success' => true,
            'month' => $month,
            'year' => $year,
            'data' => $salaryData
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
} 