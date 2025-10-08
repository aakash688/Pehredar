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

// Pagination and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $perPage;

// Build query with search and pagination
$searchCondition = $search ? "AND (u.first_name LIKE ? OR u.surname LIKE ? OR u.employee_id LIKE ?)" : "";
$searchParams = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$countQuery = "
    SELECT COUNT(*) as total 
    FROM salary_advances sa
    JOIN users u ON sa.user_id = u.id
    WHERE 1=1 $searchCondition
";

$listQuery = "
    SELECT 
        sa.*, 
        u.first_name, 
        u.surname, 
        u.employee_id
    FROM 
        salary_advances sa
    JOIN 
        users u ON sa.user_id = u.id
    WHERE 
        1=1 $searchCondition
    ORDER BY 
        sa.created_at DESC
    LIMIT ? OFFSET ?
";

// Prepare and execute count query
$countStmt = $db->query($countQuery, $searchParams);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Prepare and execute list query
$queryParams = array_merge($searchParams, [$perPage, $offset]);
$advanceSalaries = $db->query($listQuery, $queryParams)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advance Salary Records</title>
    <link rel="stylesheet" href="../../assets/css/attendance-management.css">
    <script src="../../assets/js/shared-components.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Advance Salary Records</h1>
        
        <!-- Search Form -->
        <form method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by name, employee ID" value="<?= htmlspecialchars($search) ?>">
                <select name="per_page" class="form-control" style="max-width: 100px;">
                    <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                </select>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>

        <!-- Advance Salary Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Employee ID</th>
                    <th>Amount</th>
                    <th>Remaining Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advanceSalaries as $advance): ?>
                    <tr>
                        <td><?= htmlspecialchars($advance['first_name'] . ' ' . $advance['surname']) ?></td>
                        <td><?= htmlspecialchars($advance['employee_id']) ?></td>
                        <td>₹<?= number_format($advance['amount'], 2) ?></td>
                        <td>₹<?= number_format($advance['remaining_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($advance['status']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($advance['created_at'])) ?></td>
                        <td>
                            <button 
                                class="btn btn-sm btn-info" 
                                onclick="loadAdvanceSalaryDetails(<?= $advance['id'] ?>)"
                            >
                                View Details
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $perPage ?>&search=<?= urlencode($search) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Include the advance salary details modal -->
    <?php include 'advance_salary_view_modal.php'; ?>
</body>
</html>