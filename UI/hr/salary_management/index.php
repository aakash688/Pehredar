<?php
session_start();
require_once '../../../helpers/database.php';
require_once '../../../actions/salary_controller.php';

// Check user authentication and permissions
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'HR') {
    header('Location: ../../../login.php');
    exit();
}

$db = new Database();
$salaryController = new SalaryController();

// Handle salary processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'process_salaries') {
        $month = date('Y-m');
        
        // Fetch all active employees
        $employees = $db->query("SELECT id FROM users WHERE status = 'Active'")->fetchAll();
        
        $processedSalaries = [];
        $errors = [];
        
        foreach ($employees as $employee) {
            try {
                $salarySlip = $salaryController->generateSalarySlip($employee['id'], $month);
                $salaryRecordId = $salaryController->saveSalaryRecord($salarySlip);
                $processedSalaries[] = $salarySlip;
            } catch (Exception $e) {
                $errors[] = "Error processing salary for employee ID {$employee['id']}: " . $e->getMessage();
            }
        }
    }
}

// Fetch salary records for the current month
$currentMonth = date('Y-m');
$salaryRecords = $db->query(
    "SELECT sr.*, u.first_name, u.surname 
     FROM salary_records sr 
     JOIN users u ON sr.user_id = u.id 
     WHERE sr.month = ?
     ORDER BY u.first_name, u.surname",
    [$currentMonth]
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Management</title>
    <link rel="stylesheet" href="../../assets/css/attendance-management.css">
    <script src="../../assets/js/shared-components.js"></script>
</head>
<body>
    <div class="container">
        <h1>Salary Management - <?= $currentMonth ?></h1>
        
        <?php if (!empty($processedSalaries)): ?>
            <div class="alert alert-success">
                <p>Successfully processed <?= count($processedSalaries) ?> salary records.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <form method="POST">
                <input type="hidden" name="action" value="process_salaries">
                <button type="submit" class="btn btn-primary">
                    Process Salaries for <?= $currentMonth ?>
                </button>
            </form>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Base Salary</th>
                    <th>Present Days</th>
                    <th>Overtime Hours</th>
                    <th>Total Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salaryRecords as $record): ?>
                    <tr>
                        <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['surname']) ?></td>
                        <td>₹<?= number_format($record['base_salary'], 2) ?></td>
                        <td><?= $record['total_present_days'] ?></td>
                        <td><?= number_format($record['overtime_hours'], 2) ?></td>
                        <td>₹<?= number_format($record['total_salary'], 2) ?></td>
                        <td><?= htmlspecialchars($record['status']) ?></td>
                        <td>
                            <a href="salary_slip.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-info">
                                View Slip
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 