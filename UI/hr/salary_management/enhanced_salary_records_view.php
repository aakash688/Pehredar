<?php
// Enhanced Salary Records View with advanced features
require_once __DIR__ . '/../../../helpers/database.php';
require_once __DIR__ . '/../../../helpers/AdvanceTracker.php';

// Initialize components
$db = new Database();
$advanceTracker = new \Helpers\AdvanceTracker();

// Get employees with advance status for visual categorization
$employeesWithAdvanceStatus = $advanceTracker->getEmployeesWithAdvanceStatus();

// Get enhanced salary records with all related data
$enhancedSalaryRecordsQuery = "
    SELECT 
        sr.*,
        u.first_name,
        u.surname,
        u.user_type,
        u.salary as base_employee_salary,
        ase.id as advance_id,
        ase.total_advance_amount,
        ase.remaining_balance as advance_remaining,
        ase.monthly_deduction_amount,
        ase.status as advance_status,
        ase.priority_level as advance_priority,
        ase.emergency_advance,
        -- Calculate advance progress
        CASE 
            WHEN ase.total_advance_amount > 0 THEN 
                ROUND(((ase.total_advance_amount - ase.remaining_balance) / ase.total_advance_amount) * 100, 1)
            ELSE 0 
        END as advance_progress,
        -- Check if advance is overdue
        CASE 
            WHEN ase.status = 'active' AND ase.expected_completion_date < CURDATE() THEN TRUE 
            ELSE FALSE 
        END as advance_overdue,
        -- Get bonus count for this month
        (SELECT COUNT(*) FROM bonus_records br WHERE br.user_id = sr.user_id AND br.month = sr.month) as bonus_count,
        -- Get deduction count for this month
        (SELECT COUNT(*) FROM employee_deductions ed WHERE ed.user_id = sr.user_id AND ed.month = sr.month AND ed.status = 'applied') as deduction_count
    FROM 
        salary_records sr
    LEFT JOIN 
        users u ON sr.user_id = u.id
    LEFT JOIN 
        advance_salary_enhanced ase ON u.id = ase.user_id AND ase.status = 'active'
    ORDER BY 
        sr.created_at DESC, sr.year DESC, sr.month DESC
";

$enhancedSalaryRecords = $db->query($enhancedSalaryRecordsQuery)->fetchAll();

// Calculate summary statistics
$totalRecords = count($enhancedSalaryRecords);
$pendingRecords = array_filter($enhancedSalaryRecords, function($record) {
    return ($record['disbursement_status'] ?? 'pending') === 'pending';
});
$disbursedRecords = array_filter($enhancedSalaryRecords, function($record) {
    return ($record['disbursement_status'] ?? 'pending') === 'disbursed';
});

// Get unique months for filtering
$monthYears = [];
foreach ($enhancedSalaryRecords as $record) {
    if ($record['month']) {
        $monthYear = $record['month'];
        if (!in_array($monthYear, $monthYears)) {
            $monthYears[] = $monthYear;
        }
    }
}
sort($monthYears);
$monthYears = array_reverse($monthYears);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Salary Records Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .employee-card {
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .status-indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        .notification-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="container mx-auto px-6 py-8">
    <!-- Header Section with Statistics -->
    <div class="mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white">Enhanced Salary Records</h1>
                <p class="text-gray-400">Comprehensive payroll management with advanced features</p>
            </div>
            <div class="flex space-x-4">
                <button id="bulk-operations-btn" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-layer-group mr-2"></i>Bulk Operations
                </button>
                <button id="export-records-btn" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Records</p>
                        <p class="text-2xl font-bold text-white"><?php echo $totalRecords; ?></p>
                    </div>
                    <div class="bg-blue-500 p-3 rounded-full">
                        <i class="fas fa-file-invoice text-white"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Pending Disbursement</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo count($pendingRecords); ?></p>
                    </div>
                    <div class="bg-yellow-500 p-3 rounded-full">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Disbursed</p>
                        <p class="text-2xl font-bold text-green-400"><?php echo count($disbursedRecords); ?></p>
                    </div>
                    <div class="bg-green-500 p-3 rounded-full">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">With Advances</p>
                        <p class="text-2xl font-bold text-orange-400">
                            <?php 
                            $withAdvances = array_filter($enhancedSalaryRecords, function($record) {
                                return !empty($record['advance_id']);
                            });
                            echo count($withAdvances); 
                            ?>
                        </p>
                    </div>
                    <div class="bg-orange-500 p-3 rounded-full">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filtering Section -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border border-gray-700">
        <h3 class="text-lg font-semibold mb-4">Advanced Filters</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Month/Year Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Month/Year</label>
                <select id="month-filter" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    <option value="">All Months</option>
                    <?php foreach ($monthYears as $monthYear): ?>
                        <?php 
                        $parts = explode('-', $monthYear);
                        $displayMonth = DateTime::createFromFormat('!m', $parts[1])->format('F') . ' ' . $parts[0];
                        ?>
                        <option value="<?php echo $monthYear; ?>"><?php echo $displayMonth; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Disbursement Status</label>
                <select id="status-filter" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="disbursed">Disbursed</option>
                </select>
            </div>

            <!-- Employee Type Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Employee Type</label>
                <select id="employee-type-filter" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    <option value="">All Types</option>
                    <option value="Admin">Admin</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="Site Supervisor">Site Supervisor</option>
                    <option value="Guard">Guard</option>
                </select>
            </div>

            <!-- Advance Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Advance Status</label>
                <select id="advance-filter" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    <option value="">All Employees</option>
                    <option value="with_advance">With Active Advance</option>
                    <option value="without_advance">Without Advance</option>
                    <option value="overdue_advance">Overdue Advance</option>
                    <option value="emergency_advance">Emergency Advance</option>
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Search Employee</label>
                <input 
                    type="text" 
                    id="employee-search" 
                    placeholder="Search by name..."
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white placeholder-gray-400"
                >
            </div>
        </div>

        <div class="flex justify-between items-center mt-4">
            <button id="apply-filters" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
            <button id="clear-filters" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                <i class="fas fa-times mr-2"></i>Clear All
            </button>
        </div>
    </div>

    <!-- Enhanced Salary Records Table -->
    <div class="bg-gray-800 rounded-lg border border-gray-700">
        <div class="p-6 border-b border-gray-700">
            <h3 class="text-lg font-semibold">Salary Records</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            <input type="checkbox" id="select-all" class="rounded bg-gray-600">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Month/Year</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Salary Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Advance Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Adjustments</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="salary-records-tbody" class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($enhancedSalaryRecords as $record): ?>
                        <?php
                        // Determine visual category
                        $visualCategory = 'normal';
                        $categoryClasses = 'bg-gray-800 hover:bg-gray-750';
                        $indicatorClass = '';
                        
                        if ($record['advance_id']) {
                            if ($record['advance_overdue']) {
                                $visualCategory = 'overdue';
                                $categoryClasses = 'bg-red-900 bg-opacity-20 hover:bg-red-900 hover:bg-opacity-30 border-l-4 border-red-500';
                                $indicatorClass = 'bg-red-500';
                            } elseif ($record['emergency_advance']) {
                                $visualCategory = 'emergency';
                                $categoryClasses = 'bg-orange-900 bg-opacity-20 hover:bg-orange-900 hover:bg-opacity-30 border-l-4 border-orange-500';
                                $indicatorClass = 'bg-orange-500';
                            } elseif ($record['advance_priority'] === 'urgent' || $record['advance_priority'] === 'high') {
                                $visualCategory = 'high_priority';
                                $categoryClasses = 'bg-yellow-900 bg-opacity-20 hover:bg-yellow-900 hover:bg-opacity-30 border-l-4 border-yellow-500';
                                $indicatorClass = 'bg-yellow-500';
                            } else {
                                $visualCategory = 'active';
                                $categoryClasses = 'bg-blue-900 bg-opacity-20 hover:bg-blue-900 hover:bg-opacity-30 border-l-4 border-blue-500';
                                $indicatorClass = 'bg-blue-500';
                            }
                        }

                        // Parse month for display
                        $monthParts = explode('-', $record['month']);
                        $displayMonth = DateTime::createFromFormat('!m', $monthParts[1])->format('M') . ' ' . $monthParts[0];
                        ?>
                        
                        <tr class="employee-card <?php echo $categoryClasses; ?> relative" 
                            data-employee="<?php echo strtolower($record['first_name'] . ' ' . $record['surname']); ?>"
                            data-month="<?php echo $record['month']; ?>"
                            data-status="<?php echo $record['disbursement_status'] ?? 'pending'; ?>"
                            data-employee-type="<?php echo $record['user_type']; ?>"
                            data-advance-status="<?php echo $record['advance_id'] ? 'with_advance' : 'without_advance'; ?>">
                            
                            <!-- Status Indicator -->
                            <?php if ($indicatorClass): ?>
                                <div class="status-indicator <?php echo $indicatorClass; ?>"></div>
                            <?php endif; ?>

                            <!-- Checkbox -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="record-checkbox rounded bg-gray-600" value="<?php echo $record['id']; ?>">
                            </td>

                            <!-- Employee Info -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-white flex items-center">
                                            <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['surname']); ?>
                                            
                                            <!-- Status Badges -->
                                            <?php if ($record['advance_overdue']): ?>
                                                <span class="ml-2 px-2 py-1 text-xs rounded bg-red-500 text-white notification-badge">
                                                    ⚠️ Overdue
                                                </span>
                                            <?php elseif ($record['emergency_advance']): ?>
                                                <span class="ml-2 px-2 py-1 text-xs rounded bg-orange-500 text-white">
                                                    🚨 Emergency
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-400">
                                            <?php echo htmlspecialchars($record['user_type']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Month/Year -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300"><?php echo $displayMonth; ?></div>
                            </td>

                            <!-- Salary Details -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <div class="text-white font-medium">
                                        Net: ₹<?php echo number_format($record['net_salary'] ?? $record['final_salary'], 2); ?>
                                    </div>
                                    <div class="text-gray-400">
                                        Base: ₹<?php echo number_format($record['base_salary'], 2); ?>
                                    </div>
                                    <?php if (!empty($record['statutory_total'])): ?>
                                        <div class="text-red-300 text-xs">
                                            -₹<?php echo number_format($record['statutory_total'], 2); ?> statutory
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['additional_bonuses'] > 0): ?>
                                        <div class="text-green-400 text-xs">
                                            +₹<?php echo number_format($record['additional_bonuses'], 2); ?> bonus
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['total_deductions'] > 0): ?>
                                        <div class="text-red-400 text-xs">
                                            -₹<?php echo number_format($record['total_deductions'], 2); ?> deductions
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Advance Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($record['advance_id']): ?>
                                    <div class="text-sm">
                                        <div class="text-orange-400 font-medium">
                                            ₹<?php echo number_format($record['advance_remaining'], 2); ?> remaining
                                        </div>
                                        <div class="text-gray-400 text-xs">
                                            Monthly: ₹<?php echo number_format($record['monthly_deduction_amount'], 2); ?>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                                            <div class="progress-bar h-2 rounded-full bg-orange-500" 
                                                 style="width: <?php echo $record['advance_progress']; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?php echo $record['advance_progress']; ?>% complete
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500 text-sm">No advance</span>
                                <?php endif; ?>
                            </td>

                            <!-- Adjustments -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-1">
                                    <?php if ($record['bonus_count'] > 0): ?>
                                        <span class="px-2 py-1 text-xs rounded bg-green-600 text-white">
                                            <?php echo $record['bonus_count']; ?> Bonus
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($record['deduction_count'] > 0): ?>
                                        <span class="px-2 py-1 text-xs rounded bg-red-600 text-white">
                                            <?php echo $record['deduction_count']; ?> Deduction
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($record['has_adjustments']): ?>
                                        <span class="px-2 py-1 text-xs rounded bg-blue-600 text-white">
                                            Modified
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo ($record['disbursement_status'] ?? 'pending') === 'disbursed' 
                                        ? 'bg-green-100 text-green-800' 
                                        : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($record['disbursement_status'] ?? 'pending'); ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <?php if (($record['disbursement_status'] ?? 'pending') === 'pending'): ?>
                                        <button class="text-blue-400 hover:text-blue-300 edit-record" 
                                                data-id="<?php echo $record['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-green-400 hover:text-green-300 disburse-record" 
                                                data-id="<?php echo $record['id']; ?>">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="text-purple-400 hover:text-purple-300 download-slip" 
                                            data-id="<?php echo $record['id']; ?>">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-gray-300 view-details" 
                                            data-id="<?php echo $record['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bulk Actions Bar (Hidden by default) -->
    <div id="bulk-actions-bar" class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 p-4 hidden">
        <div class="container mx-auto flex justify-between items-center">
            <div class="text-white">
                <span id="selected-count">0</span> records selected
            </div>
            <div class="flex space-x-4">
                <button id="bulk-disburse" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-check mr-2"></i>Bulk Disburse
                </button>
                <button id="bulk-export" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-download mr-2"></i>Export Selected
                </button>
                <button id="clear-selection" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i>Clear
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Salary Modal -->
<div id="edit-salary-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-white">Edit Salary Record</h3>
        <form id="edit-salary-form">
            <input type="hidden" id="edit-record-id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Additional Bonuses</label>
                <input type="number" id="edit-bonuses" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Deductions</label>
                <input type="number" id="edit-deductions" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Advance Deduction</label>
                <input type="number" id="edit-advance-deduction" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Final Salary</label>
                <input type="number" id="edit-final-salary" step="0.01" readonly class="w-full bg-gray-600 border border-gray-600 rounded-lg px-3 py-2 text-gray-300">
            </div>

            <div class="flex justify-end space-x-4">
                <button type="button" id="cancel-edit" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg text-white">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Operations Modal -->
<div id="bulk-operations-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
        <h3 class="text-lg font-semibold mb-4 text-white">Bulk Operations</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button class="bulk-operation-btn bg-green-600 hover:bg-green-700 p-4 rounded-lg text-center" data-operation="bonus">
                <i class="fas fa-gift text-2xl mb-2"></i>
                <div class="font-medium">Bulk Bonus</div>
                <div class="text-sm opacity-75">Apply bonuses to multiple employees</div>
            </button>
            
            <button class="bulk-operation-btn bg-red-600 hover:bg-red-700 p-4 rounded-lg text-center" data-operation="deduction">
                <i class="fas fa-minus-circle text-2xl mb-2"></i>
                <div class="font-medium">Bulk Deduction</div>
                <div class="text-sm opacity-75">Apply deductions to multiple employees</div>
            </button>
            
            <button class="bulk-operation-btn bg-blue-600 hover:bg-blue-700 p-4 rounded-lg text-center" data-operation="advance">
                <i class="fas fa-credit-card text-2xl mb-2"></i>
                <div class="font-medium">Advance Management</div>
                <div class="text-sm opacity-75">Manage advance repayments</div>
            </button>
        </div>

        <div class="flex justify-end mt-6">
            <button id="close-bulk-modal" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg text-white">
                Close
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initializeFiltering();
    initializeBulkSelection();
    initializeModals();
    initializeActions();

    function initializeFiltering() {
        const monthFilter = document.getElementById('month-filter');
        const statusFilter = document.getElementById('status-filter');
        const employeeTypeFilter = document.getElementById('employee-type-filter');
        const advanceFilter = document.getElementById('advance-filter');
        const employeeSearch = document.getElementById('employee-search');
        const applyFiltersBtn = document.getElementById('apply-filters');
        const clearFiltersBtn = document.getElementById('clear-filters');

        function applyFilters() {
            const rows = document.querySelectorAll('#salary-records-tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                let visible = true;

                // Month filter
                if (monthFilter.value && row.dataset.month !== monthFilter.value) {
                    visible = false;
                }

                // Status filter
                if (statusFilter.value && row.dataset.status !== statusFilter.value) {
                    visible = false;
                }

                // Employee type filter
                if (employeeTypeFilter.value && row.dataset.employeeType !== employeeTypeFilter.value) {
                    visible = false;
                }

                // Advance filter
                if (advanceFilter.value) {
                    if (advanceFilter.value === 'with_advance' && row.dataset.advanceStatus === 'without_advance') {
                        visible = false;
                    } else if (advanceFilter.value === 'without_advance' && row.dataset.advanceStatus === 'with_advance') {
                        visible = false;
                    }
                    // Add more advance filter logic as needed
                }

                // Search filter
                if (employeeSearch.value && !row.dataset.employee.includes(employeeSearch.value.toLowerCase())) {
                    visible = false;
                }

                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            showToast(`Showing ${visibleCount} records`, 'info');
        }

        applyFiltersBtn.addEventListener('click', applyFilters);
        employeeSearch.addEventListener('input', applyFilters);

        clearFiltersBtn.addEventListener('click', function() {
            monthFilter.value = '';
            statusFilter.value = '';
            employeeTypeFilter.value = '';
            advanceFilter.value = '';
            employeeSearch.value = '';
            applyFilters();
        });
    }

    function initializeBulkSelection() {
        const selectAllCheckbox = document.getElementById('select-all');
        const recordCheckboxes = document.querySelectorAll('.record-checkbox');
        const bulkActionsBar = document.getElementById('bulk-actions-bar');
        const selectedCountSpan = document.getElementById('selected-count');

        function updateBulkActionsBar() {
            const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
            const count = checkedBoxes.length;
            
            selectedCountSpan.textContent = count;
            bulkActionsBar.classList.toggle('hidden', count === 0);
        }

        selectAllCheckbox.addEventListener('change', function() {
            recordCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsBar();
        });

        recordCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsBar);
        });

        document.getElementById('clear-selection').addEventListener('click', function() {
            recordCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
            updateBulkActionsBar();
        });
    }

    function initializeModals() {
        // Edit salary modal
        const editModal = document.getElementById('edit-salary-modal');
        const editForm = document.getElementById('edit-salary-form');
        const cancelEditBtn = document.getElementById('cancel-edit');

        cancelEditBtn.addEventListener('click', function() {
            editModal.classList.add('hidden');
        });

        // Bulk operations modal
        const bulkModal = document.getElementById('bulk-operations-modal');
        const closeBulkModalBtn = document.getElementById('close-bulk-modal');
        const bulkOperationsBtn = document.getElementById('bulk-operations-btn');

        bulkOperationsBtn.addEventListener('click', function() {
            bulkModal.classList.remove('hidden');
        });

        closeBulkModalBtn.addEventListener('click', function() {
            bulkModal.classList.add('hidden');
        });

        // Close modals on outside click
        window.addEventListener('click', function(event) {
            if (event.target === editModal) {
                editModal.classList.add('hidden');
            }
            if (event.target === bulkModal) {
                bulkModal.classList.add('hidden');
            }
        });
    }

    function initializeActions() {
        // Edit record actions
        document.querySelectorAll('.edit-record').forEach(button => {
            button.addEventListener('click', function() {
                const recordId = this.dataset.id;
                openEditModal(recordId);
            });
        });

        // Disburse record actions
        document.querySelectorAll('.disburse-record').forEach(button => {
            button.addEventListener('click', function() {
                const recordId = this.dataset.id;
                disburseRecord(recordId);
            });
        });

        // Download slip actions
        document.querySelectorAll('.download-slip').forEach(button => {
            button.addEventListener('click', function() {
                const recordId = this.dataset.id;
                downloadSlip(recordId);
            });
        });
    }

    function openEditModal(recordId) {
        // Fetch record details and populate modal
        fetch(`actions/salary_modification_controller.php?action=get&id=${recordId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('edit-record-id').value = recordId;
                    document.getElementById('edit-bonuses').value = record.additional_bonuses || 0;
                    document.getElementById('edit-deductions').value = record.total_deductions || 0;
                    document.getElementById('edit-advance-deduction').value = record.advance_deduction_amount || 0;
                    updateFinalSalary();
                    
                    document.getElementById('edit-salary-modal').classList.remove('hidden');
                } else {
                    showToast(data.message, 'error');
                }
            });
    }

    function updateFinalSalary() {
        const bonuses = parseFloat(document.getElementById('edit-bonuses').value) || 0;
        const deductions = parseFloat(document.getElementById('edit-deductions').value) || 0;
        const advanceDeduction = parseFloat(document.getElementById('edit-advance-deduction').value) || 0;
        // You would need to get the base salary from somewhere
        const baseSalary = 50000; // This should be fetched from the record
        
        const finalSalary = baseSalary + bonuses - deductions - advanceDeduction;
        document.getElementById('edit-final-salary').value = finalSalary.toFixed(2);
    }

    // Add event listeners for real-time final salary calculation
    ['edit-bonuses', 'edit-deductions', 'edit-advance-deduction'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateFinalSalary);
    });

    function disburseRecord(recordId) {
        if (confirm('Are you sure you want to disburse this salary? This action cannot be undone.')) {
            fetch('actions/salary_disbursement_controller.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'disburse_single',
                    record_id: recordId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Salary disbursed successfully', 'success');
                    location.reload();
                } else {
                    showToast(data.message, 'error');
                }
            });
        }
    }

    function downloadSlip(recordId) {
        window.open(`actions/salary_slip_controller.php?id=${recordId}`, '_blank');
    }

    function showToast(message, type = 'info') {
        // Create and show toast notification
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-600' :
            type === 'error' ? 'bg-red-600' :
            type === 'warning' ? 'bg-yellow-600' :
            'bg-blue-600'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
});
</script>

</body>
</html>