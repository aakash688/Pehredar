<?php
// UI/hr/salary_management/salary_records_view.php
global $page, $company_settings;
require_once 'UI/dashboard_layout.php';
require_once __DIR__ . '/../../../helpers/database.php';

// Initialize database connection
$db = new Database();

// Get salary records with employee information
$salaryRecordsQuery = "
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

$salaryRecords = $db->query($salaryRecordsQuery)->fetchAll();

// Calculate summary stats
$totalRecords = count($salaryRecords);
$pendingDisbursement = array_filter($salaryRecords, function($record) {
    return $record['disbursement_status'] === 'pending';
});
$totalPendingAmount = array_sum(array_column($pendingDisbursement, 'final_salary'));

// Current month and previous month for filtering
                    $currentMonth = date('n');
$currentYear = date('Y');
$previousMonth = date('n', strtotime('-1 month'));
$previousMonthYear = date('Y', strtotime('-1 month'));

// Get distinct months/years from records for filter dropdowns
$monthYears = [];
foreach ($salaryRecords as $record) {
    // Normalize month/year; DB stores month as int and year as int
    $yearVal = isset($record['year']) ? (int)$record['year'] : (int)date('Y');
    $monthVal = isset($record['month']) ? (int)$record['month'] : (int)date('n');
    if ($monthVal < 1 || $monthVal > 12) { $monthVal = (int)date('n'); }
    $key = sprintf('%04d-%02d', $yearVal, $monthVal);
    if (!in_array($key, $monthYears, true)) {
        $monthYears[] = $key;
    }
}
rsort($monthYears, SORT_STRING);
?>

<div class="bg-gray-800 rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white mb-4 md:mb-0">Salary Records Dashboard</h1>
        <div class="flex gap-2">
            <button id="generate-current-month-salaries" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
                <i class="fas fa-calculator"></i> Generate for <?php echo date('F Y', strtotime('-1 month')); ?>
            </button>
            <button id="bulk-disburse-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
                <i class="fas fa-money-bill-wave"></i> Bulk Disburse
                </button>
            <button id="bulk-deduction-btn" class="bg-orange-600 hover:bg-orange-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
                <i class="fas fa-minus-circle"></i> Bulk Deduction
            </button>
            </div>
        </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Total Records</span>
            <span class="text-3xl font-bold text-white"><?php echo $totalRecords; ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Pending Disbursement</span>
            <span class="text-3xl font-bold text-white"><?php echo count($pendingDisbursement); ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Pending Amount</span>
            <span class="text-3xl font-bold text-white">₹<?php echo number_format($totalPendingAmount, 2); ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">This Month Generated</span>
            <span class="text-3xl font-bold text-white">
                <?php 
                $currentMonthFormatted = sprintf('%04d-%02d', $currentYear, $currentMonth);
                $thisMonthCount = count(array_filter($salaryRecords, function($record) use ($currentMonthFormatted) {
                    return $record['month'] == $currentMonthFormatted;
                }));
                echo $thisMonthCount;
                ?>
            </span>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div>
            <select id="month-year-filter" class="bg-gray-700 text-white w-full pl-3 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Months</option>
                <?php foreach ($monthYears as $monthYear): ?>
                    <?php 
                    $parts = explode('-', $monthYear);
                    $year = isset($parts[0]) ? (int)$parts[0] : (int)date('Y');
                    $month = isset($parts[1]) ? (int)$parts[1] : (int)date('n');
                    $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
                    ?>
                    <option value="<?php echo $monthYear; ?>"><?php echo $monthName; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select id="status-filter" class="bg-gray-700 text-white w-full pl-3 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="pending">Pending Disbursement</option>
                <option value="disbursed">Disbursed</option>
            </select>
        </div>
        <div>
            <select id="type-filter" class="bg-gray-700 text-white w-full pl-3 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="auto_generated">Auto Generated</option>
                <option value="manually_modified">Manually Modified</option>
                </select>
            </div>
            <div>
            <input type="text" id="search-employees" 
                   class="bg-gray-700 text-white w-full pl-3 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                   placeholder="Search employees...">
        </div>
        </div>

    <!-- Records Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-gray-900 rounded-lg overflow-hidden">
                <thead class="bg-gray-800">
                    <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Base Salary</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Final Amount</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
            <tbody id="records-table-body" class="divide-y divide-gray-800">
                <?php if (empty($salaryRecords)) : ?>
                    <tr id="no-records-message">
                        <td colspan="7" class="px-6 py-10 text-center text-gray-400">
                            No salary records found. Generate salaries first.
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($salaryRecords as $record) : ?>
                        <?php 
                        // Create proper month-year format for filtering
                        $periodYear = isset($record['year']) ? (int)$record['year'] : (int)date('Y');
                        $periodMonth = isset($record['month']) ? (int)$record['month'] : (int)date('n');
                        $monthYearKey = sprintf('%04d-%02d', $periodYear, $periodMonth);
                        ?>
                        <tr class="record-row hover:bg-gray-800" 
                            data-month-year="<?php echo $monthYearKey; ?>"
                            data-month="<?php echo $periodMonth; ?>"
                            data-year="<?php echo $periodYear; ?>"
                            data-status="<?php echo $record['disbursement_status']; ?>"
                            data-type="<?php echo $record['manually_modified'] ? 'manually_modified' : 'auto_generated'; ?>"
                            data-employee="<?php echo strtolower(htmlspecialchars($record['first_name'] . ' ' . $record['surname'])); ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-white">
                                    <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['surname']); ?>
                                </div>
                                <div class="text-sm text-gray-400">
                                    <?php echo htmlspecialchars($record['user_type'] ?? 'No Role'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php 
                                    // Build period from numeric month/year values
                                    $periodYear = isset($record['year']) ? (int)$record['year'] : (int)date('Y');
                                    $periodMonth = isset($record['month']) ? (int)$record['month'] : (int)date('n');
                                    echo date('F Y', mktime(0, 0, 0, $periodMonth, 1, $periodYear)); 
                                    ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-semibold text-gray-300">
                                    ₹<?php echo number_format($record['base_salary'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-semibold text-white">
                                    ₹<?php echo number_format($record['final_salary'], 2); ?>
                                </div>
                                <?php if ($record['additional_bonuses'] > 0 || $record['deductions'] > 0 || $record['advance_salary_deducted'] > 0): ?>
                                    <div class="text-xs text-gray-400">
                                        <?php if ($record['additional_bonuses'] > 0): ?>
                                            +<?php echo number_format($record['additional_bonuses'], 2); ?>
                                        <?php endif; ?>
                                        <?php if ($record['deductions'] > 0): ?>
                                            -<?php echo number_format($record['deductions'], 2); ?>
                                        <?php endif; ?>
                                        <?php if ($record['advance_salary_deducted'] > 0): ?>
                                            (Adv: -<?php echo number_format($record['advance_salary_deducted'], 2); ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $record['manually_modified'] ? 'bg-orange-900 text-orange-300' : 'bg-blue-900 text-blue-300'; ?>">
                                    <?php echo $record['manually_modified'] ? 'Modified' : 'Auto Gen'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($record['disbursement_status'] === 'disbursed') : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-300">
                                        Disbursed
                                    </span>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?php echo $record['disbursed_at'] ? date('M d', strtotime($record['disbursed_at'])) : ''; ?>
                                    </div>
                                <?php else : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-300">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-left">
                                <div class="flex justify-start space-x-2">
                                    <button onclick="viewRecord(<?php echo $record['id']; ?>)" 
                                            class="text-blue-400 hover:text-blue-300 px-2 py-1" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($record['disbursement_status'] === 'pending') : ?>
                                        <button onclick="editRecord(<?php echo $record['id']; ?>)" 
                                                class="text-green-400 hover:text-green-300 px-2 py-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="disburseRecord(<?php echo $record['id']; ?>)" 
                                                class="text-purple-400 hover:text-purple-300 px-2 py-1" title="Disburse">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="downloadSlip(<?php echo $record['id']; ?>)" 
                                            class="text-indigo-400 hover:text-indigo-300 px-2 py-1" title="Download Slip">
                                        <i class="fas fa-download"></i>
                                    </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Dynamic no results message -->
                <tr id="no-results-message" class="hidden">
                    <td colspan="7" class="px-6 py-10 text-center text-gray-400">
                        <div class="flex flex-col items-center space-y-2">
                            <i class="fas fa-search text-4xl text-gray-600"></i>
                            <div class="text-lg font-medium">No records found</div>
                            <div class="text-sm">Try adjusting your filters or search terms</div>
                            <button onclick="clearAllFilters()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                Clear All Filters
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-gray-800 rounded-lg p-4">
        <div class="flex items-center space-x-4 mb-4 sm:mb-0">
            <div class="flex items-center space-x-2">
                <label for="records-per-page" class="text-sm text-gray-300">Records per page:</label>
                <select id="records-per-page" class="bg-gray-700 text-white px-3 py-1 rounded text-sm">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="text-sm text-gray-300">
                Showing <span id="showing-start">1</span> to <span id="showing-end">25</span> of <span id="total-records"><?php echo count($salaryRecords); ?></span> records
            </div>
        </div>
        
        <div class="flex items-center space-x-2">
            <button id="prev-page" class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            
            <div id="page-numbers" class="flex space-x-1">
                <!-- Page numbers will be generated by JavaScript -->
            </div>
            
            <button id="next-page" class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Bulk Disbursement Modal -->
<div id="bulk-disburse-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">Bulk Salary Disbursement</h2>
                <button onclick="closeBulkDisburseModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Select Month/Year</label>
                <select id="bulk-month-year" class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Pending Records</option>
                    <?php foreach ($monthYears as $monthYear): ?>
                        <?php 
                        $parts2 = explode('-', $monthYear);
                        $year = isset($parts2[0]) ? (int)$parts2[0] : (int)date('Y');
                        $month = isset($parts2[1]) ? (int)$parts2[1] : (int)date('n');
                        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
                        ?>
                        <option value="<?php echo $monthYear; ?>"><?php echo $monthName; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="bulk-records-container" class="mb-4 max-h-64 overflow-y-auto">
                <div class="text-center text-gray-400 py-4">
                    Select a month/year to view pending records
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <div id="bulk-summary" class="text-sm text-gray-300"></div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closeBulkDisburseModal()" 
                            class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button id="confirm-bulk-disburse" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors disabled:opacity-50" 
                            disabled>
                        Disburse Selected
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Salary Modal -->
<div id="edit-salary-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-700">
                <h2 class="text-2xl font-bold text-white">Edit Salary Record</h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6">
            <form id="edit-salary-form">
                <input type="hidden" id="edit-record-id">
                
                    <!-- Employee Info Section -->
                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Employee Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Employee</label>
                                <div id="edit-employee-name" class="text-white font-semibold text-lg"></div>
                    <div id="edit-period" class="text-sm text-gray-400"></div>
                </div>
                            <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Calculated Salary</label>
                                <div id="edit-calculated-salary" class="text-white font-semibold text-lg text-green-400"></div>
                            </div>
                        </div>
                </div>
                
                    <!-- Salary Adjustments Section -->
                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Salary Adjustments</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="edit-bonuses" class="block text-sm font-medium text-gray-300 mb-2">Additional Bonuses</label>
                        <input type="number" id="edit-bonuses" step="0.01" min="0" 
                               class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                                <label for="edit-deductions" class="block text-sm font-medium text-gray-300 mb-2">General Deductions</label>
                        <input type="number" id="edit-deductions" step="0.01" min="0" 
                               class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                            <div>
                    <label for="edit-advance-deducted" class="block text-sm font-medium text-gray-300 mb-2">Advance Salary Deducted</label>
                    <input type="number" id="edit-advance-deducted" step="0.01" min="0" 
                           class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            </div>
                        </div>
                        
                        <!-- Skip Deduction Option -->
                        <div class="mt-4 p-4 bg-yellow-900 bg-opacity-30 border border-yellow-600 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <input type="checkbox" id="skip-advance-deduction" class="w-4 h-4 text-yellow-600 bg-gray-700 border-gray-600 rounded focus:ring-yellow-500 focus:ring-2">
                                    <label for="skip-advance-deduction" class="text-yellow-300 font-medium">Skip Advance Deduction for This Month</label>
                                </div>
                                <div class="text-sm text-yellow-400">
                                    <i class="fas fa-info-circle"></i>
                                    <span class="ml-1">This will extend the repayment period by 1 month</span>
                                </div>
                            </div>
                            <div id="skip-reason-container" class="mt-3 hidden">
                                <label for="skip-reason" class="block text-sm font-medium text-yellow-300 mb-2">Reason for Skip</label>
                                <textarea id="skip-reason" rows="2" 
                                          class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                          placeholder="Please provide a reason for skipping this month's advance deduction..."></textarea>
                            </div>
                            
                            <!-- Skip Status Indicator -->
                            <div id="skip-status-indicator" class="mt-3 hidden">
                                <div class="bg-green-900 bg-opacity-30 border border-green-600 rounded-lg p-3">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                        <span class="text-green-300 font-medium">Previously Skipped</span>
                                    </div>
                                    <div class="mt-2 text-sm text-green-200">
                                        <div id="skip-status-reason"></div>
                                        <div id="skip-status-date" class="text-xs text-green-300 mt-1"></div>
                                    </div>
                                    <div class="mt-2 flex space-x-2">
                                        <button type="button" id="unskip-button" 
                                                class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">
                                            Remove Skip
                                        </button>
                                        <button type="button" id="close-modal-after-unskip" 
                                                class="text-xs bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded hidden">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden field to track skip removal state -->
                            <input type="hidden" id="skip-removal-requested" value="false">
                        </div>
                </div>
                
                    <!-- Deduction Types Section -->
                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Specific Deduction Types</h3>
                        <div id="deduction-types-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Deduction types will be loaded here -->
                        </div>
                </div>
                
                    <!-- Final Salary Section -->
                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Final Calculation</h3>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-medium text-gray-300">Final Salary:</span>
                            <div id="edit-final-salary" class="text-white font-bold text-2xl text-green-400"></div>
                        </div>
                    </div>
                    
                    <!-- Notes Section -->
                    <div class="bg-gray-900 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Additional Notes</h3>
                        <div>
                    <label for="edit-notes" class="block text-sm font-medium text-gray-300 mb-2">Notes (Optional)</label>
                            <textarea id="edit-notes" rows="4" 
                              class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Add any notes about this modification..."></textarea>
                        </div>
                    </div>
                </form>
                </div>
                
            <!-- Modal Footer -->
            <div class="flex justify-end space-x-3 p-6 border-t border-gray-700">
                    <button type="button" onclick="closeEditModal()" 
                        class="px-6 py-2 text-gray-400 hover:text-white transition-colors">
                        Cancel
                    </button>
                <button type="button" id="save-salary-changes"
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors">
                        Save Changes
                    </button>
                </div>
        </div>
    </div>
</div>

<!-- Bulk Deduction Modal -->
<div id="bulk-deduction-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-700">
                <h2 class="text-2xl font-bold text-white">Bulk Deduction</h2>
                <button onclick="closeBulkDeductionModal()" class="text-gray-400 hover:text-white text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left side: Employee selection -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-white mb-6">Select Employees</h3>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Select Month/Year</label>
                            <select id="bulk-deduction-month-year" class="bg-gray-700 text-white w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                <option value="">Select Month/Year</option>
                                <?php foreach ($monthYears as $monthYear): ?>
                                    <?php 
                                    $parts = explode('-', $monthYear);
                                    $year = isset($parts[0]) ? (int)$parts[0] : (int)date('Y');
                                    $month = isset($parts[1]) ? (int)$parts[1] : (int)date('n');
                                    $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
                                    ?>
                                    <option value="<?php echo $monthYear; ?>"><?php echo $monthName; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-300">Available Employees</label>
                                <div class="flex items-center space-x-2">
                                    <button id="select-all-employees" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                                        Select All
                                    </button>
                                    <button id="deselect-all-employees" class="text-xs bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded">
                                        Deselect All
                                    </button>
                                </div>
                            </div>
                            <div id="bulk-deduction-employees" class="max-h-80 overflow-y-auto border border-gray-600 rounded-lg p-4 bg-gray-800">
                                <div class="text-center text-gray-400 py-8">
                                    <i class="fas fa-users text-4xl mb-2"></i>
                                    <p>Select a month/year to view employees</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right side: Deduction details -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-white mb-6">Deduction Details</h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Deduction Type *</label>
                                <select id="bulk-deduction-type" class="bg-gray-700 text-white w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                    <option value="">Select Deduction Type</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Deduction Amount *</label>
                                <input type="number" id="bulk-deduction-amount" step="0.01" min="0.01" 
                                       class="bg-gray-700 text-white w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                                       placeholder="Enter amount">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Notes (Optional)</label>
                                <textarea id="bulk-deduction-notes" rows="4" 
                                          class="bg-gray-700 text-white w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                                          placeholder="Add notes about this bulk deduction..."></textarea>
                            </div>
                            
                            <div class="bg-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-white mb-3">Summary</h4>
                                <div id="bulk-deduction-summary" class="text-sm text-gray-300">
                                    <div class="flex items-center text-gray-400">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No employees selected
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end space-x-3 p-6 border-t border-gray-700">
                <button type="button" onclick="closeBulkDeductionModal()" 
                        class="px-6 py-2 text-gray-400 hover:text-white transition-colors">
                    Cancel
                </button>
                <button id="confirm-bulk-deduction" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg transition-colors disabled:opacity-50" 
                        disabled>
                    Apply Deduction
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Message Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthYearFilter = document.getElementById('month-year-filter');
    const statusFilter = document.getElementById('status-filter');
    const typeFilter = document.getElementById('type-filter');
    const searchInput = document.getElementById('search-employees');
    const recordsPerPageSelect = document.getElementById('records-per-page');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageNumbersContainer = document.getElementById('page-numbers');
    const showingStart = document.getElementById('showing-start');
    const showingEnd = document.getElementById('showing-end');
    const totalRecordsSpan = document.getElementById('total-records');
    const noResultsMessage = document.getElementById('no-results-message');
    const noRecordsMessage = document.getElementById('no-records-message');
    
    let allRecordRows = Array.from(document.querySelectorAll('.record-row'));
    let filteredRecords = [...allRecordRows];
    let currentPage = 1;
    let recordsPerPage = 25;
    
    // Initialize pagination
    function initializePagination() {
        updatePagination();
        showPage(1);
    }
    
    // Filter functionality
    function applyFilters() {
        const monthYear = monthYearFilter.value;
        const status = statusFilter.value;
        const type = typeFilter.value;
        const search = searchInput.value.toLowerCase().trim();
        
        filteredRecords = allRecordRows.filter(row => {
            const rowMonthYear = row.dataset.monthYear;
            const rowStatus = row.dataset.status;
            const rowType = row.dataset.type;
            const rowEmployee = row.dataset.employee;
            
            let show = true;
            
            if (monthYear && rowMonthYear !== monthYear) show = false;
            if (status && rowStatus !== status) show = false;
            if (type && rowType !== type) show = false;
            if (search && !rowEmployee.includes(search)) show = false;
            
            return show;
        });
        
        // Reset to first page when filtering
        currentPage = 1;
        updatePagination();
        showPage(1);
    }
    
    // Update pagination controls
    function updatePagination() {
        const totalFiltered = filteredRecords.length;
        const totalPages = Math.ceil(totalFiltered / recordsPerPage);
        
        // Update showing info
        const start = totalFiltered === 0 ? 0 : ((currentPage - 1) * recordsPerPage) + 1;
        const end = Math.min(currentPage * recordsPerPage, totalFiltered);
        
        showingStart.textContent = start;
        showingEnd.textContent = end;
        totalRecordsSpan.textContent = totalFiltered;
        
        // Update buttons
        prevPageBtn.disabled = currentPage === 1;
        nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        // Generate page numbers
        generatePageNumbers(totalPages);
    }
    
    // Generate page number buttons
    function generatePageNumbers(totalPages) {
        pageNumbersContainer.innerHTML = '';
        
        if (totalPages === 0) return;
        
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        // Adjust start page if we're near the end
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // Add first page and ellipsis if needed
        if (startPage > 1) {
            addPageButton(1, currentPage === 1);
            if (startPage > 2) {
                addEllipsis();
            }
        }
        
        // Add visible page numbers
        for (let i = startPage; i <= endPage; i++) {
            addPageButton(i, currentPage === i);
        }
        
        // Add last page and ellipsis if needed
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                addEllipsis();
            }
            addPageButton(totalPages, currentPage === totalPages);
        }
    }
    
    // Add page button
    function addPageButton(pageNum, isActive) {
        const button = document.createElement('button');
        button.textContent = pageNum;
        button.className = `px-3 py-2 rounded text-sm ${
            isActive 
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
        }`;
        button.addEventListener('click', () => showPage(pageNum));
        pageNumbersContainer.appendChild(button);
    }
    
    // Add ellipsis
    function addEllipsis() {
        const ellipsis = document.createElement('span');
        ellipsis.textContent = '...';
        ellipsis.className = 'px-2 py-2 text-gray-400';
        pageNumbersContainer.appendChild(ellipsis);
    }
    
    // Show specific page
    function showPage(page) {
        currentPage = page;
        
        // Hide all rows first
        allRecordRows.forEach(row => row.style.display = 'none');
        
        // Hide no results message
        if (noResultsMessage) noResultsMessage.classList.add('hidden');
        if (noRecordsMessage) noRecordsMessage.classList.add('hidden');
        
        // Show rows for current page
        const startIndex = (page - 1) * recordsPerPage;
        const endIndex = startIndex + recordsPerPage;
        
        if (filteredRecords.length === 0) {
            // Show no results message
            if (noResultsMessage) noResultsMessage.classList.remove('hidden');
        } else {
            for (let i = startIndex; i < endIndex && i < filteredRecords.length; i++) {
                filteredRecords[i].style.display = '';
            }
        }
        
        updatePagination();
    }
    
    // Event listeners
    monthYearFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    
    recordsPerPageSelect.addEventListener('change', function() {
        recordsPerPage = parseInt(this.value);
        currentPage = 1;
        updatePagination();
        showPage(1);
    });
    
    prevPageBtn.addEventListener('click', function() {
        if (currentPage > 1) {
            showPage(currentPage - 1);
        }
    });
    
    nextPageBtn.addEventListener('click', function() {
        const totalPages = Math.ceil(filteredRecords.length / recordsPerPage);
        if (currentPage < totalPages) {
            showPage(currentPage + 1);
        }
    });
    
    // Initialize on page load
    initializePagination();
    
    // Clear all filters function
    window.clearAllFilters = function() {
        monthYearFilter.value = '';
        statusFilter.value = '';
        typeFilter.value = '';
        searchInput.value = '';
        applyFilters();
    };
    
    // Generate current month salaries
    document.getElementById('generate-current-month-salaries').addEventListener('click', function() {
        window.location.href = 'index.php?page=salary-calculation';
    });
    
    // Bulk disburse
    const bulkDisburseModal = document.getElementById('bulk-disburse-modal');
    const bulkMonthYear = document.getElementById('bulk-month-year');
    const bulkRecordsContainer = document.getElementById('bulk-records-container');
    const bulkSummary = document.getElementById('bulk-summary');
    const confirmBulkDisburse = document.getElementById('confirm-bulk-disburse');
    
    let selectedRecords = [];
    
    document.getElementById('bulk-disburse-btn').addEventListener('click', function() {
        bulkDisburseModal.classList.remove('hidden');
        loadBulkRecords('');
    });
    
    bulkMonthYear.addEventListener('change', function() {
        loadBulkRecords(this.value);
    });
    
    function loadBulkRecords(monthYear) {
        const url = `actions/salary_disbursement_controller.php?action=get_pending_records${monthYear ? '&month_year=' + monthYear : ''}`;
        
        bulkRecordsContainer.innerHTML = '<div class="text-center text-gray-400 py-4">Loading...</div>';
        
        fetch(url)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const records = result.data;
                    selectedRecords = [];
                    
                    if (records.length === 0) {
                        bulkRecordsContainer.innerHTML = '<div class="text-center text-gray-400 py-4">No pending records found</div>';
                        updateBulkSummary();
                        return;
                    }
                    
                    let html = '<div class="space-y-2">';
                    records.forEach(record => {
                        // Create period text from separate month and year fields
                        const periodText = new Date(record.year, record.month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                        html += `
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-700 rounded cursor-pointer">
                                <input type="checkbox" 
                                       class="bulk-record-checkbox" 
                                       data-id="${record.id}"
                                       data-amount="${record.final_salary}"
                                       onchange="updateSelectedRecords()">
                                <div class="flex-1">
                                    <div class="text-white font-medium">${record.first_name} ${record.surname}</div>
                                    <div class="text-sm text-gray-400">${periodText} - ₹${parseFloat(record.final_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                </div>
                            </label>
                        `;
                    });
                    html += '</div>';
                    
                    bulkRecordsContainer.innerHTML = html;
                    updateBulkSummary();
                } else {
                    bulkRecordsContainer.innerHTML = '<div class="text-center text-red-400 py-4">Error loading records</div>';
                }
            })
            .catch(error => {
                bulkRecordsContainer.innerHTML = '<div class="text-center text-red-400 py-4">Error loading records</div>';
                console.error('Error:', error);
            });
    }
    
    window.updateSelectedRecords = function() {
        selectedRecords = [];
        const checkboxes = document.querySelectorAll('.bulk-record-checkbox:checked');
        checkboxes.forEach(checkbox => {
            selectedRecords.push({
                id: parseInt(checkbox.dataset.id),
                amount: parseFloat(checkbox.dataset.amount)
            });
        });
        updateBulkSummary();
    };
    
    function updateBulkSummary() {
        const count = selectedRecords.length;
        const totalAmount = selectedRecords.reduce((sum, record) => sum + record.amount, 0);
        
        if (count === 0) {
            bulkSummary.textContent = 'No records selected';
            confirmBulkDisburse.disabled = true;
        } else {
            bulkSummary.textContent = `${count} record(s) selected - Total: ₹${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
            confirmBulkDisburse.disabled = false;
        }
    }
    
    confirmBulkDisburse.addEventListener('click', function() {
        if (selectedRecords.length === 0) {
            showToast('No records selected', 'error');
            return;
        }
        
        const count = selectedRecords.length;
        const totalAmount = selectedRecords.reduce((sum, record) => sum + record.amount, 0);
        
        if (confirm(`Are you sure you want to disburse ${count} salary record(s) totaling ₹${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}? This action cannot be undone.`)) {
            
            confirmBulkDisburse.disabled = true;
            confirmBulkDisburse.textContent = 'Disbursing...';
            
            const data = {
                record_ids: selectedRecords.map(r => r.id),
                month_year: bulkMonthYear.value
            };
            
            fetch('actions/salary_disbursement_controller.php?action=disburse_bulk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                    closeBulkDisburseModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to disburse salaries', 'error');
                }
            })
            .catch(error => {
                showToast('Error disbursing salaries', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                confirmBulkDisburse.disabled = false;
                confirmBulkDisburse.textContent = 'Disburse Selected';
            });
        }
    });
    
    window.closeBulkDisburseModal = function() {
        bulkDisburseModal.classList.add('hidden');
        selectedRecords = [];
        bulkMonthYear.value = '';
        bulkRecordsContainer.innerHTML = '<div class="text-center text-gray-400 py-4">Select a month/year to view pending records</div>';
        updateBulkSummary();
    };
    
    // Bulk Deduction functionality
    const bulkDeductionModal = document.getElementById('bulk-deduction-modal');
    const bulkDeductionMonthYear = document.getElementById('bulk-deduction-month-year');
    const bulkDeductionEmployees = document.getElementById('bulk-deduction-employees');
    const bulkDeductionType = document.getElementById('bulk-deduction-type');
    const bulkDeductionAmount = document.getElementById('bulk-deduction-amount');
    const bulkDeductionNotes = document.getElementById('bulk-deduction-notes');
    const bulkDeductionSummary = document.getElementById('bulk-deduction-summary');
    const confirmBulkDeduction = document.getElementById('confirm-bulk-deduction');
    
    let selectedEmployees = [];
    
    document.getElementById('bulk-deduction-btn').addEventListener('click', function() {
        bulkDeductionModal.classList.remove('hidden');
        loadDeductionTypesForBulk();
    });
    
    bulkDeductionMonthYear.addEventListener('change', function() {
        loadEmployeesForBulkDeduction(this.value);
    });
    
    // Select All / Deselect All functionality
    document.getElementById('select-all-employees').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.bulk-deduction-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedEmployees();
    });
    
    document.getElementById('deselect-all-employees').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.bulk-deduction-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedEmployees();
    });
    
    function loadDeductionTypesForBulk() {
        fetch('actions/deduction_master_controller.php?action=get_active')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    bulkDeductionType.innerHTML = '<option value="">Select Deduction Type</option>';
                    result.data.forEach(deduction => {
                        const option = document.createElement('option');
                        option.value = deduction.id;
                        option.textContent = `${deduction.deduction_name} (${deduction.deduction_code})`;
                        bulkDeductionType.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading deduction types:', error);
            });
    }
    
    function loadEmployeesForBulkDeduction(monthYear) {
        console.log('loadEmployeesForBulkDeduction called with:', monthYear);
        
        if (!monthYear) {
            bulkDeductionEmployees.innerHTML = `
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-users text-4xl mb-2"></i>
                    <p>Select a month/year to view employees</p>
                </div>
            `;
            selectedEmployees = [];
            updateBulkDeductionSummary();
            return;
        }
        
        const [year, month] = monthYear.split('-');
        // Remove leading zero from month (e.g., "08" -> "8")
        const monthNumber = parseInt(month, 10);
        const url = `actions/bulk_deduction_controller.php?action=get_employees&month=${monthNumber}&year=${year}`;
        
        console.log('Fetching URL:', url);
        
        bulkDeductionEmployees.innerHTML = `
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-spinner fa-spin text-4xl mb-2"></i>
                <p>Loading employees...</p>
            </div>
        `;
        
        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                console.log('API Response:', result);
                if (result.success) {
                    const employees = result.data;
                    selectedEmployees = [];
                    
                    console.log('Employees found:', employees.length);
                    
                    if (employees.length === 0) {
                        bulkDeductionEmployees.innerHTML = `
                            <div class="text-center text-gray-400 py-8">
                                <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                                <p>No employees found for this month</p>
                                <p class="text-sm mt-2">Make sure salary records have been generated for ${formatMonth(monthYear)}</p>
                            </div>
                        `;
                        updateBulkDeductionSummary();
                        return;
                    }
                    
                    let html = '<div class="space-y-2">';
                    employees.forEach((employee, index) => {
                        const statusColor = employee.disbursement_status === 'pending' ? 'text-yellow-400' : 'text-green-400';
                        const statusBg = employee.disbursement_status === 'pending' ? 'bg-yellow-900' : 'bg-green-900';
                        
                        html += `
                            <label class="flex items-center space-x-3 p-3 hover:bg-gray-700 rounded-lg cursor-pointer transition-colors">
                                <input type="checkbox" 
                                       class="bulk-deduction-checkbox rounded" 
                                       data-salary-record-id="${employee.salary_record_id}"
                                       data-employee-name="${employee.first_name} ${employee.surname}"
                                       data-salary="${employee.final_salary}"
                                       data-status="${employee.disbursement_status}"
                                       onchange="updateSelectedEmployees()">
                                <div class="flex-1">
                                    <div class="text-white font-medium">${employee.first_name} ${employee.surname}</div>
                                    <div class="text-sm text-gray-400">${employee.user_type} - ₹${parseFloat(employee.final_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                </div>
                                <div class="flex flex-col items-end space-y-1">
                                    <div class="text-xs text-gray-500">#${index + 1}</div>
                                    <div class="text-xs px-2 py-1 rounded ${statusBg} ${statusColor}">
                                        ${employee.status_label || employee.disbursement_status}
                                    </div>
                                </div>
                            </label>
                        `;
                    });
                    html += '</div>';
                    
                    bulkDeductionEmployees.innerHTML = html;
                    updateBulkDeductionSummary();
                } else {
                    bulkDeductionEmployees.innerHTML = `
                        <div class="text-center text-red-400 py-8">
                            <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                            <p>Error: ${result.message || 'Failed to load employees'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading employees:', error);
                bulkDeductionEmployees.innerHTML = `
                    <div class="text-center text-red-400 py-8">
                        <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                        <p>Error loading employees: ${error.message}</p>
                    </div>
                `;
            });
    }
    
    window.updateSelectedEmployees = function() {
        selectedEmployees = [];
        const checkboxes = document.querySelectorAll('.bulk-deduction-checkbox:checked');
        checkboxes.forEach(checkbox => {
            selectedEmployees.push({
                salary_record_id: parseInt(checkbox.dataset.salaryRecordId),
                employee_name: checkbox.dataset.employeeName,
                salary: parseFloat(checkbox.dataset.salary),
                status: checkbox.dataset.status
            });
        });
        updateBulkDeductionSummary();
    };
    
    function updateBulkDeductionSummary() {
        const count = selectedEmployees.length;
        const deductionType = bulkDeductionType.value;
        const amount = parseFloat(bulkDeductionAmount.value) || 0;
        
        if (count === 0) {
            bulkDeductionSummary.innerHTML = `
                <div class="flex items-center text-gray-400">
                    <i class="fas fa-info-circle mr-2"></i>
                    No employees selected
                </div>
            `;
            confirmBulkDeduction.disabled = true;
        } else if (!deductionType || amount <= 0) {
            bulkDeductionSummary.innerHTML = `
                <div class="space-y-2">
                    <div class="flex items-center text-yellow-400">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        ${count} employee(s) selected
                    </div>
                    <div class="text-sm text-gray-400">
                        Please select deduction type and enter amount
                    </div>
                </div>
            `;
            confirmBulkDeduction.disabled = true;
        } else {
            const totalDeduction = count * amount;
            const deductionTypeName = bulkDeductionType.options[bulkDeductionType.selectedIndex].text;
            
            // Count pending vs disbursed employees
            const pendingCount = selectedEmployees.filter(emp => emp.status === 'pending').length;
            const disbursedCount = selectedEmployees.filter(emp => emp.status === 'disbursed').length;
            
            let statusWarning = '';
            if (disbursedCount > 0) {
                statusWarning = `
                    <div class="text-sm text-yellow-400 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        ${disbursedCount} employee(s) have already been disbursed. This will modify their final salary.
                    </div>
                `;
            }
            
            bulkDeductionSummary.innerHTML = `
                <div class="space-y-2">
                    <div class="flex items-center text-green-400">
                        <i class="fas fa-check-circle mr-2"></i>
                        ${count} employee(s) selected
                    </div>
                    <div class="text-sm">
                        <div class="text-gray-300">Type: <span class="text-white font-medium">${deductionTypeName}</span></div>
                        <div class="text-gray-300">Amount: <span class="text-white font-medium">₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2})} each</span></div>
                        <div class="text-orange-400 font-semibold text-lg">
                            Total: ₹${totalDeduction.toLocaleString('en-IN', {minimumFractionDigits: 2})}
                        </div>
                        ${statusWarning}
                    </div>
                </div>
            `;
            confirmBulkDeduction.disabled = false;
        }
    }
    
    bulkDeductionType.addEventListener('change', updateBulkDeductionSummary);
    bulkDeductionAmount.addEventListener('input', updateBulkDeductionSummary);
    
    confirmBulkDeduction.addEventListener('click', function() {
        if (selectedEmployees.length === 0) {
            showToast('No employees selected', 'error');
            return;
        }
        
        const deductionType = bulkDeductionType.value;
        const amount = parseFloat(bulkDeductionAmount.value);
        
        if (!deductionType || amount <= 0) {
            showToast('Please select deduction type and enter valid amount', 'error');
            return;
        }
        
        const count = selectedEmployees.length;
        const totalDeduction = count * amount;
        
        if (confirm(`Are you sure you want to apply ₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2})} deduction to ${count} employee(s) (Total: ₹${totalDeduction.toLocaleString('en-IN', {minimumFractionDigits: 2})})?`)) {
            
            confirmBulkDeduction.disabled = true;
            confirmBulkDeduction.textContent = 'Applying...';
            
            const data = {
                salary_record_ids: selectedEmployees.map(e => e.salary_record_id),
                deduction_master_id: parseInt(deductionType),
                deduction_amount: amount,
                notes: bulkDeductionNotes.value
            };
            
            fetch('actions/bulk_deduction_controller.php?action=apply_bulk_deduction', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                    closeBulkDeductionModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to apply bulk deduction', 'error');
                }
            })
            .catch(error => {
                showToast('Error applying bulk deduction', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                confirmBulkDeduction.disabled = false;
                confirmBulkDeduction.textContent = 'Apply Deduction';
            });
        }
    });
    
    window.closeBulkDeductionModal = function() {
        bulkDeductionModal.classList.add('hidden');
        selectedEmployees = [];
        bulkDeductionMonthYear.value = '';
        bulkDeductionType.value = '';
        bulkDeductionAmount.value = '';
        bulkDeductionNotes.value = '';
        bulkDeductionEmployees.innerHTML = '<div class="text-center text-gray-400 py-4">Select a month/year to view employees</div>';
        updateBulkDeductionSummary();
    };
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container');
        
        const toast = document.createElement('div');
        toast.className = `flex items-center p-4 mb-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-800 text-green-200' : type === 'error' ? 'bg-red-800 text-red-200' : 'bg-blue-800 text-blue-200'}`;
        
        const icon = document.createElement('i');
        icon.className = `mr-2 ${type === 'success' ? 'fas fa-check-circle' : type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle'}`;
        
        toast.appendChild(icon);
        toast.appendChild(document.createTextNode(message));
        
        toastContainer.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Edit salary form functionality
    const editForm = document.getElementById('edit-salary-form');
    const editModal = document.getElementById('edit-salary-modal');
    
    // Auto-calculate final salary when inputs change
    function updateFinalSalary() {
        const calculatedSalary = parseFloat(document.getElementById('edit-calculated-salary').textContent.replace(/[₹,]/g, '')) || 0;
        const bonuses = parseFloat(document.getElementById('edit-bonuses').value) || 0;
        const deductions = parseFloat(document.getElementById('edit-deductions').value) || 0;
        const advanceDeducted = parseFloat(document.getElementById('edit-advance-deducted').value) || 0;
        const skipAdvanceDeduction = document.getElementById('skip-advance-deduction').checked;
        
        // Calculate deduction amounts from deduction types
        let deductionAmounts = 0;
        const deductionInputs = document.querySelectorAll('.deduction-amount-input');
        deductionInputs.forEach(input => {
            deductionAmounts += parseFloat(input.value) || 0;
        });
        
        // If skip is selected, advance deduction is 0
        const actualAdvanceDeducted = skipAdvanceDeduction ? 0 : advanceDeducted;
        
        const finalSalary = calculatedSalary + bonuses - deductions - actualAdvanceDeducted - deductionAmounts;
        
        // Show skip indicator if skip is selected
        if (skipAdvanceDeduction) {
            document.getElementById('edit-final-salary').innerHTML = '₹' + finalSalary.toLocaleString('en-IN', {minimumFractionDigits: 2}) + 
                ' <span class="text-yellow-400 text-sm">(Advance Deduction Skipped)</span>';
        } else {
            document.getElementById('edit-final-salary').textContent = '₹' + finalSalary.toLocaleString('en-IN', {minimumFractionDigits: 2});
        }
        
        // Add visual feedback for negative salary
        if (finalSalary < 0) {
            document.getElementById('edit-final-salary').classList.add('text-red-400');
        } else {
            document.getElementById('edit-final-salary').classList.remove('text-red-400');
        }
    }

    // Function to show skip indicator
    function showSkipIndicator(reason, createdAt) {
        document.getElementById('skip-status-reason').textContent = `Reason: ${reason}`;
        document.getElementById('skip-status-date').textContent = `Skipped on: ${new Date(createdAt).toLocaleDateString()}`;
        document.getElementById('skip-status-indicator').classList.remove('hidden');
    }

    // Function to hide skip indicator
    function hideSkipIndicator() {
        document.getElementById('skip-status-indicator').classList.add('hidden');
    }

    // Function to handle unskip
    function handleUnskip() {
        if (confirm('Are you sure you want to remove the skip for this month? This will restore the advance deduction.')) {
            // Set removal flag to true
            document.getElementById('skip-removal-requested').value = 'true';
            
            // Uncheck the skip checkbox
            document.getElementById('skip-advance-deduction').checked = false;
            
            // Hide skip indicator
            hideSkipIndicator();
            
            // Reset advance deduction to original amount
            const originalAdvanceAmount = document.getElementById('edit-advance-deducted').getAttribute('data-original-amount') || '0';
            document.getElementById('edit-advance-deducted').value = originalAdvanceAmount;
            document.getElementById('edit-advance-deducted').disabled = false;
            document.getElementById('edit-advance-deducted').classList.remove('bg-gray-600', 'text-gray-400');
            
            // Clear skip reason
            document.getElementById('skip-reason').value = '';
            document.getElementById('skip-reason-container').classList.add('hidden');
            
            // Update final salary
            updateFinalSalary();
            
            // Show success message
            showToast('Skip removed from form. Please click "Save Changes" to permanently remove the skip.', 'warning');
        }
    }
    
    document.getElementById('edit-bonuses').addEventListener('input', updateFinalSalary);
    document.getElementById('edit-deductions').addEventListener('input', updateFinalSalary);
    document.getElementById('edit-advance-deducted').addEventListener('input', updateFinalSalary);
    document.getElementById('skip-advance-deduction').addEventListener('change', updateFinalSalary);
    
    // Add event listener for unskip button
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'unskip-button') {
            handleUnskip();
        }
        if (e.target && e.target.id === 'close-modal-after-unskip') {
            closeEditModal();
            setTimeout(() => window.location.reload(), 500);
        }
    });
    
    // Skip deduction functionality
    document.getElementById('skip-advance-deduction').addEventListener('change', function() {
        const skipContainer = document.getElementById('skip-reason-container');
        const advanceInput = document.getElementById('edit-advance-deducted');
        
        if (this.checked) {
            skipContainer.classList.remove('hidden');
            advanceInput.value = '0';
            advanceInput.disabled = true;
            advanceInput.classList.add('bg-gray-600', 'text-gray-400');
            updateFinalSalary();
        } else {
            skipContainer.classList.add('hidden');
            advanceInput.disabled = false;
            advanceInput.classList.remove('bg-gray-600', 'text-gray-400');
            updateFinalSalary();
        }
    });
    
    // Load deduction types
    function loadDeductionTypes() {
        fetch('actions/deduction_master_controller.php?action=get_active')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const container = document.getElementById('deduction-types-container');
                    container.innerHTML = '';
                    
                    result.data.forEach(deduction => {
                        const deductionDiv = document.createElement('div');
                        deductionDiv.className = 'flex items-center space-x-3 p-2 bg-gray-700 rounded-lg';
                        deductionDiv.innerHTML = `
                            <div class="flex-1">
                                <label class="text-sm text-gray-300">${deduction.deduction_name} (${deduction.deduction_code})</label>
                            </div>
                            <div class="w-24">
                                <input type="number" 
                                       class="deduction-amount-input bg-gray-600 text-white w-full px-2 py-1 rounded text-sm" 
                                       step="0.01" min="0" 
                                       data-deduction-id="${deduction.id}"
                                       placeholder="0.00">
                            </div>
                        `;
                        container.appendChild(deductionDiv);
                    });
                    
                    // Add event listeners to deduction inputs
                    container.querySelectorAll('.deduction-amount-input').forEach(input => {
                        input.addEventListener('input', updateFinalSalary);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading deduction types:', error);
            });
    }
    
    // Edit form submission
    function submitEditForm() {
        console.log('Form submission started');
        
        const recordId = document.getElementById('edit-record-id').value;
        const finalSalaryText = document.getElementById('edit-final-salary').textContent;
        const finalSalary = parseFloat(finalSalaryText.replace(/[₹,]/g, ''));
        
        console.log('Record ID:', recordId);
        console.log('Final Salary:', finalSalary);
        
        if (finalSalary < 0) {
            showToast('Final salary cannot be negative', 'error');
            return;
        }
        
        // Validate skip reason if skip is selected
        const skipAdvanceDeduction = document.getElementById('skip-advance-deduction').checked;
        const skipReason = document.getElementById('skip-reason').value.trim();
        
        if (skipAdvanceDeduction && !skipReason) {
            showToast('Please provide a reason for skipping the advance deduction', 'error');
            return;
        }
        
        // Collect deduction amounts
        const deductionAmounts = {};
        document.querySelectorAll('.deduction-amount-input').forEach(input => {
            const deductionId = input.dataset.deductionId;
            const amount = parseFloat(input.value) || 0;
            if (amount > 0) {
                deductionAmounts[deductionId] = amount;
            }
        });
        
            // Check if skip removal was requested
            const skipRemovalRequested = document.getElementById('skip-removal-requested').value === 'true';
            
            const data = {
                id: parseInt(recordId),
                additional_bonuses: parseFloat(document.getElementById('edit-bonuses').value) || 0,
                deductions: parseFloat(document.getElementById('edit-deductions').value) || 0,
                advance_salary_deducted: parseFloat(document.getElementById('edit-advance-deducted').value) || 0,
                deduction_amounts: deductionAmounts,
                notes: document.getElementById('edit-notes').value,
                skip_advance_deduction: document.getElementById('skip-advance-deduction').checked,
                skip_reason: document.getElementById('skip-reason').value || '',
                remove_skip: skipRemovalRequested
            };
        
        console.log('Data to send:', data);
        
        // Disable button
        const submitBtn = document.getElementById('save-salary-changes');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch('actions/salary_modification_controller.php?action=update_record', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text(); // Get as text first to debug
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const result = JSON.parse(text);
                console.log('Parsed response:', result);
                
            if (result.success) {
                showToast('Salary record updated successfully!', 'success');
                
                // If unskip was processed, update the form state
                if (result.data && result.data.unskip_processed) {
                    // Hide skip indicator
                    hideSkipIndicator();
                    
                    // Reset form state
                    document.getElementById('skip-advance-deduction').checked = false;
                    document.getElementById('skip-reason').value = '';
                    document.getElementById('skip-reason-container').classList.add('hidden');
                    document.getElementById('edit-advance-deducted').disabled = false;
                    document.getElementById('edit-advance-deducted').classList.remove('bg-gray-600', 'text-gray-400');
                    
                    // Reset skip removal flag
                    document.getElementById('skip-removal-requested').value = 'false';
                    
                    // Restore original advance amount
                    const originalAmount = document.getElementById('edit-advance-deducted').getAttribute('data-original-amount') || '0';
                    document.getElementById('edit-advance-deducted').value = originalAmount;
                    
                    // Update final salary
                    updateFinalSalary();
                    
                    // Show success message for unskip
                    showToast('Skip removed successfully! Advance deduction restored. The form will close automatically.', 'success');
                    
                    // Close modal and reload after 2 seconds
                    setTimeout(() => {
                        closeEditModal();
                        window.location.reload();
                    }, 2000);
                }
                
                // If skip was processed, show skip indicator
                if (result.data && result.data.skip_processed) {
                    // Show skip indicator
                    showSkipIndicator(result.data.skip_reason || 'Skip processed', new Date().toISOString());
                    
                    // Show success message for skip
                    showToast('Advance deduction skipped successfully!', 'success');
                }
                
                // Only close modal if not processing skip/unskip
                if (!result.data || (!result.data.unskip_processed && !result.data.skip_processed)) {
                    closeEditModal();
                }
                
                // Update the specific row in the table with new final salary
                updateSalaryRecordInTable(recordId, result.data.new_final_salary);
                
                // Only reload if not processing skip/unskip
                if (!result.data || (!result.data.unskip_processed && !result.data.skip_processed)) {
                    setTimeout(() => window.location.reload(), 2000);
                }
            } else {
                showToast(result.message || 'Failed to update salary record', 'error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                showToast('Invalid response from server', 'error');
            }
        })
        .catch(error => {
            showToast('An error occurred while updating the record', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        });
    }
    
    // Add event listener to save button
    document.getElementById('save-salary-changes').addEventListener('click', submitEditForm);
    
    // Function to update salary record in the table
    function updateSalaryRecordInTable(recordId, newFinalSalary) {
        // Find the row with the matching record ID
        const rows = document.querySelectorAll('.record-row');
        rows.forEach(row => {
            const editBtn = row.querySelector(`[onclick*="editRecord(${recordId})"]`);
            if (editBtn) {
                // Update the final amount in the table
                const finalAmountCell = row.querySelector('td:nth-child(3)'); // Assuming final amount is in 3rd column
                if (finalAmountCell) {
                    finalAmountCell.textContent = '₹' + parseFloat(newFinalSalary).toLocaleString('en-IN', {minimumFractionDigits: 2});
                    
                    // Add visual feedback
                    finalAmountCell.style.backgroundColor = '#10b981';
                    finalAmountCell.style.color = 'white';
                    finalAmountCell.style.transition = 'all 0.3s ease';
                    
                    // Remove highlight after 3 seconds
                    setTimeout(() => {
                        finalAmountCell.style.backgroundColor = '';
                        finalAmountCell.style.color = '';
                    }, 3000);
                }
            }
        });
    }
    
    // Global functions for action buttons
    window.viewRecord = function(id) {
        // Fetch record data
        fetch(`actions/salary_modification_controller.php?action=get_record&id=${id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showViewRecordModal(result.data);
                } else {
                    showToast(result.message || 'Failed to fetch record details', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record:', error);
                showToast('Error fetching record details', 'error');
            });
    };

    function showViewRecordModal(record) {
        // Create modal HTML
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
        modal.innerHTML = `
            <div class="bg-gray-800 rounded-lg w-full max-w-5xl max-h-screen overflow-hidden">
                <div class="flex justify-between items-center p-6 border-b border-gray-700">
                    <h3 class="text-xl font-semibold text-white">View Salary Record</h3>
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto max-h-96">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Employee Information -->
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-400"></i>
                                Employee Information
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Name:</span>
                                    <span class="text-white">${record.full_name}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Employee ID:</span>
                                    <span class="text-white">${record.user_id}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Department:</span>
                                    <span class="text-white">${record.user_type || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Period Information -->
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-calendar mr-2 text-green-400"></i>
                                Period Information
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Pay Period:</span>
                                    <span class="text-white">${formatMonth(record.month)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Generated:</span>
                                    <span class="text-white">${record.auto_generated ? 'Auto Generated' : 'Manual'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Status:</span>
                                    <span class="px-2 py-1 rounded text-xs ${record.disbursement_status === 'disbursed' ? 'bg-green-600 text-green-200' : 'bg-yellow-600 text-yellow-200'}">
                                        ${record.disbursement_status.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Salary Breakdown -->
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-calculator mr-2 text-purple-400"></i>
                                Salary Breakdown
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Base Salary:</span>
                                    <span class="text-white">₹${parseFloat(record.base_salary || 0).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Calculated Salary:</span>
                                    <span class="text-white">₹${parseFloat(record.calculated_salary || 0).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Additional Bonuses:</span>
                                    <span class="text-green-400">+₹${parseFloat(record.additional_bonuses || 0).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Deductions:</span>
                                    <span class="text-red-400">-₹${parseFloat(record.deductions || 0).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Advance Deducted:</span>
                                    <span class="text-orange-400">-₹${parseFloat(record.advance_salary_deducted || 0).toLocaleString()}</span>
                                </div>
                                ${record.deductions_detail && record.deductions_detail.length > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Specific Deductions:</span>
                                    <span class="text-red-400">-₹${record.deductions_detail.reduce((sum, deduction) => sum + parseFloat(deduction.deduction_amount), 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                </div>
                                ` : ''}
                                <hr class="border-gray-600">
                                <div class="flex justify-between font-semibold">
                                    <span class="text-white">Final Salary:</span>
                                    <span class="text-green-400 text-lg">₹${parseFloat(record.final_salary || 0).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Specific Deductions -->
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-minus-circle mr-2 text-red-400"></i>
                                Specific Deductions
                            </h4>
                            <div class="space-y-2 text-sm">
                                ${record.deductions_detail && record.deductions_detail.length > 0 ? 
                                    record.deductions_detail.map(deduction => `
                                        <div class="flex justify-between items-center p-2 bg-gray-600 rounded">
                                            <div>
                                                <div class="text-white font-medium">${deduction.deduction_name}</div>
                                                <div class="text-gray-400 text-xs">${deduction.deduction_code}</div>
                                            </div>
                                            <div class="text-red-400 font-semibold">
                                                -₹${parseFloat(deduction.deduction_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<div class="text-center text-gray-400 py-4">No specific deductions applied</div>'
                                }
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div class="bg-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-yellow-400"></i>
                                Additional Details
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Record ID:</span>
                                    <span class="text-white">#${String(record.id).padStart(6, '0')}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Created At:</span>
                                    <span class="text-white">${new Date(record.created_at).toLocaleDateString()}</span>
                                </div>
                                ${record.disbursed_at ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Disbursed At:</span>
                                    <span class="text-white">${new Date(record.disbursed_at).toLocaleDateString()}</span>
                                </div>
                                ` : ''}
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Modified:</span>
                                    <span class="text-white">${record.manually_modified ? 'Yes' : 'No'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 p-6 border-t border-gray-700">
                    <button onclick="downloadSalarySlip(${record.id})" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-500 transition duration-200">
                        <i class="fas fa-download mr-2"></i>Download PDF
                    </button>
                    ${record.disbursement_status !== 'disbursed' ? `
                    <button onclick="this.parentElement.parentElement.parentElement.remove(); editRecord(${record.id})" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-500 transition duration-200">
                        <i class="fas fa-edit mr-2"></i>Edit Record
                    </button>
                    ` : ''}
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-500 transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }

    function formatMonth(monthStr) {
        if (!monthStr) return 'N/A';
        const [year, month] = monthStr.split('-');
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        return `${monthNames[parseInt(month) - 1]} ${year}`;
    }

    window.downloadSalarySlip = function(recordId) {
        window.open(`actions/salary_slip_controller.php?action=download&id=${recordId}`, '_blank');
    };
    
    // Function to completely reset the edit form
    function resetEditForm() {
        // Reset all form fields
        document.getElementById('edit-bonuses').value = '';
        document.getElementById('edit-deductions').value = '';
        document.getElementById('edit-advance-deducted').value = '';
        document.getElementById('edit-notes').value = '';
        
        // Reset skip form
        document.getElementById('skip-advance-deduction').checked = false;
        document.getElementById('skip-reason').value = '';
        document.getElementById('skip-reason-container').classList.add('hidden');
        document.getElementById('edit-advance-deducted').disabled = false;
        document.getElementById('edit-advance-deducted').classList.remove('bg-gray-600', 'text-gray-400');
        
        // Reset skip removal flag
        document.getElementById('skip-removal-requested').value = 'false';
        
        // Hide skip indicator
        hideSkipIndicator();
        document.getElementById('skip-status-indicator').classList.add('hidden');
        document.getElementById('close-modal-after-unskip').classList.add('hidden');
        
        // Reset deduction types
        document.querySelectorAll('.deduction-amount-input').forEach(input => {
            input.value = '';
        });
        
        // Update final salary
        updateFinalSalary();
    }

    window.editRecord = function(id) {
        // Reset form completely first
        resetEditForm();
        
        // Load deduction types first
        loadDeductionTypes();
        
        // Fetch record data
        fetch(`actions/salary_modification_controller.php?action=get_record&id=${id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const record = result.data;
                    
                    // Populate modal
                    document.getElementById('edit-record-id').value = record.id;
                    document.getElementById('edit-employee-name').textContent = record.first_name + ' ' + record.surname;
                    // Parse month from 'YYYY-MM' format
                    const [year, month] = record.month.split('-');
                    document.getElementById('edit-period').textContent = new Date(year, month - 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    document.getElementById('edit-calculated-salary').textContent = '₹' + parseFloat(record.calculated_salary).toLocaleString('en-IN', {minimumFractionDigits: 2});
                    document.getElementById('edit-bonuses').value = record.additional_bonuses || 0;
                    document.getElementById('edit-deductions').value = record.deductions || 0;
                    document.getElementById('edit-advance-deducted').value = record.advance_salary_deducted || 0;
                    document.getElementById('edit-notes').value = '';
                    
                    // Store original advance amount for potential unskip
                    document.getElementById('edit-advance-deducted').setAttribute('data-original-amount', record.advance_salary_deducted || 0);
                    
                    // Hide close button initially
                    document.getElementById('close-modal-after-unskip').classList.add('hidden');
                    
                    // Handle skip form based on record data
                    console.log('Skip request data:', {
                        has_skip_request: record.has_skip_request,
                        skip_reason: record.skip_reason,
                        skip_created_at: record.skip_created_at
                    });
                    
                    if (record.has_skip_request && record.skip_reason) {
                        // Record has skip request - show skip as selected
                        document.getElementById('skip-advance-deduction').checked = true;
                        document.getElementById('skip-reason').value = record.skip_reason || '';
                        document.getElementById('skip-reason-container').classList.remove('hidden');
                        document.getElementById('edit-advance-deducted').value = '0';
                        document.getElementById('edit-advance-deducted').disabled = true;
                        document.getElementById('edit-advance-deducted').classList.add('bg-gray-600', 'text-gray-400');
                        
                        // Show skip indicator
                        showSkipIndicator(record.skip_reason, record.skip_created_at);
                    } else {
                        // No skip request - reset form completely
                        document.getElementById('skip-advance-deduction').checked = false;
                        document.getElementById('skip-reason').value = '';
                        document.getElementById('skip-reason-container').classList.add('hidden');
                        document.getElementById('edit-advance-deducted').disabled = false;
                        document.getElementById('edit-advance-deducted').classList.remove('bg-gray-600', 'text-gray-400');
                        hideSkipIndicator();
                        
                        // Ensure skip indicator is completely hidden
                        document.getElementById('skip-status-indicator').classList.add('hidden');
                    }
                    
                    // Populate deduction amounts
                    if (record.deductions_detail) {
                        record.deductions_detail.forEach(deduction => {
                            const input = document.querySelector(`[data-deduction-id="${deduction.deduction_master_id}"]`);
                            if (input) {
                                input.value = deduction.deduction_amount;
                            }
                        });
                    }
                    
                    // Update final salary
                    updateFinalSalary();
                    
                    // Show modal
                    editModal.classList.remove('hidden');
                } else {
                    showToast(result.message || 'Failed to fetch record details', 'error');
                }
            })
            .catch(error => {
                showToast('Error fetching record details', 'error');
                console.error('Error:', error);
            });
    };
    
    window.closeEditModal = function() {
        editModal.classList.add('hidden');
        editForm.reset();
    };
    
    window.disburseRecord = function(id) {
        if (confirm('Are you sure you want to disburse this salary? This action cannot be undone.')) {
            fetch(`actions/salary_disbursement_controller.php?action=disburse_single&id=${id}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showToast(result.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(result.message || 'Failed to disburse salary', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error disbursing salary', 'error');
                    console.error('Error:', error);
                });
        }
    };
    
    window.downloadSlip = function(id) {
        // Create a temporary link to trigger download
        const downloadUrl = `actions/salary_slip_controller.php?action=download&id=${id}`;
        
        // Create temporary anchor element for download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.target = '_blank';
        link.style.display = 'none';
        
        // Add to DOM, click, and remove
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('Downloading salary slip...', 'info');
    };
});
</script>

<?php require_once 'UI/dashboard_layout_footer.php'; ?> 