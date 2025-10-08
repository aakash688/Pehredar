<?php
// Salary Calculation Controller
require_once 'helpers/database.php';

class SalaryController {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Calculate salary for a specific employee for a given month
     * @param int $user_id Employee ID
     * @param string $month Month in YYYY-MM format
     * @return array Salary breakdown
     */
    public function calculateMonthlySalary($user_id, $month) {
        // Fetch employee details
        $employee = $this->db->query(
            "SELECT * FROM users WHERE id = ?", 
            [$user_id]
        )->fetch();

        if (!$employee) {
            throw new Exception("Employee not found");
        }

        // Fetch HR settings for multipliers
        $hrSettings = $this->db->query(
            "SELECT * FROM hr_settings LIMIT 1"
        )->fetch();

        // Fetch attendance for the month
        $attendanceRecords = $this->db->query(
            "SELECT * FROM attendance 
             WHERE user_id = ? 
             AND DATE_FORMAT(date, '%Y-%m') = ?
             ORDER BY date",
            [$user_id, $month]
        )->fetchAll();

        // Calculate salary components
        $salaryCalculation = [
            'base_salary' => $employee['salary'],
            'total_working_days' => 0,
            'total_present_days' => 0,
            'total_overtime_hours' => 0,
            'holiday_work_days' => 0,
            'base_salary_earned' => 0,
            'overtime_salary' => 0,
            'holiday_salary' => 0,
            'total_salary' => 0
        ];

        // Standard work hours per day (assuming 8 hours)
        $standardWorkHours = 8;

        foreach ($attendanceRecords as $record) {
            switch ($record['status']) {
                case 'Present':
                    $salaryCalculation['total_present_days']++;
                    $salaryCalculation['total_working_days']++;

                    // Calculate overtime
                    $workDuration = $this->calculateWorkDuration($record['check_in_time'], $record['check_out_time']);
                    if ($workDuration > $standardWorkHours) {
                        $overtimeHours = $workDuration - $standardWorkHours;
                        $salaryCalculation['total_overtime_hours'] += $overtimeHours;
                    }
                    break;
                case 'Paid Leave':
                    $salaryCalculation['total_working_days']++;
                    $salaryCalculation['total_present_days']++;
                    break;
                case 'Unpaid Leave':
                    // No salary for unpaid leave
                    break;
            }
        }

        // Calculate base salary
        $dailyRate = $employee['salary'] / 30; // Assuming 30 days in a month
        $salaryCalculation['base_salary_earned'] = $dailyRate * $salaryCalculation['total_present_days'];

        // Calculate overtime salary
        $salaryCalculation['overtime_salary'] = 
            $dailyRate * 
            $salaryCalculation['total_overtime_hours'] * 
            $hrSettings['overtime_multiplier'];

        // Calculate total salary
        $salaryCalculation['total_salary'] = 
            $salaryCalculation['base_salary_earned'] + 
            $salaryCalculation['overtime_salary'];

        return $salaryCalculation;
    }

    /**
     * Calculate work duration in hours
     * @param string $checkIn Check-in datetime
     * @param string $checkOut Check-out datetime
     * @return float Work duration in hours
     */
    private function calculateWorkDuration($checkIn, $checkOut) {
        if (!$checkIn || !$checkOut) {
            return 0;
        }

        $checkInTime = strtotime($checkIn);
        $checkOutTime = strtotime($checkOut);

        $durationSeconds = $checkOutTime - $checkInTime;
        return $durationSeconds / 3600; // Convert to hours
    }

    /**
     * Generate salary slip for an employee
     * @param int $user_id Employee ID
     * @param string $month Month in YYYY-MM format
     * @return array Salary slip details
     */
    public function generateSalarySlip($user_id, $month) {
        $salaryCalculation = $this->calculateMonthlySalary($user_id, $month);
        
        // Fetch additional employee details
        $employee = $this->db->query(
            "SELECT * FROM users WHERE id = ?", 
            [$user_id]
        )->fetch();

        $salarySlip = [
            'employee_name' => $employee['first_name'] . ' ' . $employee['surname'],
            'employee_id' => $user_id,
            'month' => $month,
            'bank_details' => [
                'account_number' => $employee['bank_account_number'],
                'bank_name' => $employee['bank_name'],
                'ifsc_code' => $employee['ifsc_code']
            ],
            'salary_details' => $salaryCalculation
        ];

        return $salarySlip;
    }

    /**
     * Save salary record to database
     * @param array $salarySlip Salary slip details
     * @return int Saved salary record ID
     */
    public function saveSalaryRecord($salarySlip) {
        $insertQuery = "INSERT INTO salary_records (
            user_id, 
            month, 
            base_salary, 
            total_working_days, 
            total_present_days, 
            overtime_hours, 
            base_salary_earned, 
            overtime_salary, 
            total_salary, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $salarySlip['employee_id'],
            $salarySlip['month'],
            $salarySlip['salary_details']['base_salary'],
            $salarySlip['salary_details']['total_working_days'],
            $salarySlip['salary_details']['total_present_days'],
            $salarySlip['salary_details']['total_overtime_hours'],
            $salarySlip['salary_details']['base_salary_earned'],
            $salarySlip['salary_details']['overtime_salary'],
            $salarySlip['salary_details']['total_salary'],
            'Generated'
        ];

        $this->db->query($insertQuery, $params);
        return $this->db->lastInsertId();
    }

    /**
     * View advance salary details
     * @param int $advance_salary_id Advance salary record ID
     * @return array Advance salary details
     */
    public function viewAdvanceSalary($advance_salary_id) {
        // Fetch advance salary details with user and creator information
        $query = "
            SELECT 
                sa.*, 
                u.first_name, 
                u.surname, 
                u.employee_id as emp_id,
                creator.first_name as creator_first_name, 
                creator.surname as creator_surname
            FROM 
                salary_advances sa
            JOIN 
                users u ON sa.user_id = u.id
            JOIN 
                users creator ON sa.created_by = creator.id
            WHERE 
                sa.id = ?
        ";

        $advanceSalary = $this->db->query($query, [$advance_salary_id])->fetch();

        if (!$advanceSalary) {
            throw new Exception("Advance salary record not found");
        }

        return [
            'user_id' => $advanceSalary['emp_id'],
            'employee_name' => $advanceSalary['first_name'] . ' ' . $advanceSalary['surname'],
            'amount' => $advanceSalary['amount'],
            'remaining_amount' => $advanceSalary['remaining_amount'],
            'status' => $advanceSalary['status'],
            'notes' => $advanceSalary['notes'],
            'created_by_name' => $advanceSalary['creator_first_name'] . ' ' . $advanceSalary['creator_surname'],
            'created_at' => $advanceSalary['created_at'],
            'updated_at' => $advanceSalary['updated_at']
        ];
    }
}

// Example usage
if (isset($_GET['action'])) {
    $salaryController = new SalaryController();
    
    switch ($_GET['action']) {
        case 'calculate':
            $userId = $_GET['user_id'] ?? null;
            $month = $_GET['month'] ?? date('Y-m');
            
            try {
                $salaryCalculation = $salaryController->calculateMonthlySalary($userId, $month);
                echo json_encode($salaryCalculation);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
        
        case 'generate_slip':
            $userId = $_GET['user_id'] ?? null;
            $month = $_GET['month'] ?? date('Y-m');
            
            try {
                $salarySlip = $salaryController->generateSalarySlip($userId, $month);
                echo json_encode($salarySlip);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'view_advance_salary':
            $advanceSalaryId = $_GET['advance_salary_id'] ?? null;
            
            try {
                $advanceSalaryDetails = $salaryController->viewAdvanceSalary($advanceSalaryId);
                echo json_encode($advanceSalaryDetails);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
    }
} 