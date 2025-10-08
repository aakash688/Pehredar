<?php
global $page, $company_settings;
require_once 'UI/dashboard_layout.php';

// Fetch attendance master codes for reference
require_once __DIR__ . '/../../../helpers/database.php';
$db = new Database();
$attendanceMasterQuery = "SELECT code, name, multiplier FROM attendance_master";
$attendanceMasterCodes = $db->query($attendanceMasterQuery)->fetchAll();

// Fetch users with salary for reference
$usersQuery = "SELECT id, first_name, surname, salary FROM users WHERE salary > 0 ORDER BY first_name, surname";
$usersWithSalary = $db->query($usersQuery)->fetchAll();
?>

<div class="container mx-auto px-4 py-8 overflow-x-hidden">
    <div class="bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="flex-grow">
                <select id="month-select" class="w-full bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Select Month</option>
                    <?php 
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 
                        4 => 'April', 5 => 'May', 6 => 'June', 
                        7 => 'July', 8 => 'August', 9 => 'September', 
                        10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    // Default to previous month for salary calculation
                    $previousMonth = date('n', strtotime('-1 month'));
                    foreach ($months as $num => $name) {
                        echo "<option value='{$num}'" . ($num == $previousMonth ? ' selected' : '') . ">{$name}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="flex-shrink-0">
                <select id="year-select" class="w-full bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Select Year</option>
                    <?php 
                    $currentYear = date('Y');
                    // Default to year of previous month for salary calculation
                    $previousMonthYear = date('Y', strtotime('-1 month'));
                    for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                        echo "<option value='{$year}'" . ($year == $previousMonthYear ? ' selected' : '') . ">{$year}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="flex-shrink-0 flex gap-2">
                <button id="calculate-salaries" class="bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 whitespace-nowrap">
                    Calculate Salaries
                </button>
                <button id="save-salaries" class="bg-green-600 text-white p-3 rounded-lg hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Save Salaries
                </button>
                <button id="show-employees-btn" class="bg-gray-700 text-white p-3 rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 whitespace-nowrap">
                    Employees With Salary
                </button>
                <button id="show-codes-btn" class="bg-gray-700 text-white p-3 rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 whitespace-nowrap">
                    Attendance Codes
                </button>
            </div>
        </div>

        <div id="alert-container" class="mb-4 hidden">
            <!-- Alert messages will be inserted here -->
        </div>

        <div class="bg-gray-900 rounded-xl overflow-x-auto">
            <table class="min-w-full table-auto divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider whitespace-nowrap">Employee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider whitespace-nowrap">Attendance Types</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Monthly Salary</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Total Multiplier</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Calculated Salary</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-red-300 uppercase tracking-wider">Other Deductions</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-orange-300 uppercase tracking-wider">Advance Deduction</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-green-300 uppercase tracking-wider">Final Salary</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="salary-calculation-body" class="bg-gray-900 divide-y divide-gray-700">
                    <tr>
                        <td colspan="6" class="text-center p-8 text-gray-400">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Waiting to calculate salaries...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between mt-3">
            <div id="salary-pagination-info" class="text-sm text-gray-400">Showing 0 of 0</div>
            <div class="flex items-center gap-2">
                <button id="salary-prev" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Prev</button>
                <button id="salary-next" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Next</button>
            </div>
        </div>

        <div class="mt-6 bg-gray-800 rounded-xl p-4 hidden">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-300 mb-4">Attendance Codes Reference</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($attendanceMasterCodes as $code): ?>
                            <div class="bg-gray-700 p-3 rounded-lg">
                                <div class="font-bold text-gray-300"><?= htmlspecialchars($code['code']) ?></div>
                                <div class="text-sm text-gray-400"><?= htmlspecialchars($code['name']) ?></div>
                                <div class="text-xs text-gray-500">Multiplier: <?= htmlspecialchars($code['multiplier']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div></div>
            </div>
        </div>
    </div>
</div>

<!-- Employees With Salary Modal -->
<div id="employees-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-3xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">Employees With Salary</h2>
                <button id="close-employees-modal" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <div class="mb-3 flex items-center gap-3">
                <input id="employees-search" type="text" placeholder="Search employees..." class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="max-h-96 overflow-auto rounded border border-gray-700">
                <table class="w-full text-sm text-gray-300">
                    <thead class="bg-gray-700 text-gray-200">
                        <tr>
                            <th class="py-2 px-3 text-left">Name</th>
                            <th class="py-2 px-3 text-right">Monthly Salary</th>
                        </tr>
                    </thead>
                    <tbody id="employees-table-body"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div id="employees-pagination-info" class="text-sm text-gray-400">Showing 0 of 0</div>
                <div class="flex items-center gap-2">
                    <button id="employees-prev" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Prev</button>
                    <button id="employees-next" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Codes Modal -->
<div id="codes-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-3xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">Attendance Codes Reference</h2>
                <button id="close-codes-modal" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <div class="mb-3 flex items-center gap-3">
                <input id="codes-search" type="text" placeholder="Search codes or names..." class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="max-h-96 overflow-auto rounded border border-gray-700">
                <table class="w-full text-sm text-gray-300">
                    <thead class="bg-gray-700 text-gray-200">
                        <tr>
                            <th class="py-2 px-3 text-left">Code</th>
                            <th class="py-2 px-3 text-left">Name</th>
                            <th class="py-2 px-3 text-right">Multiplier</th>
                        </tr>
                    </thead>
                    <tbody id="codes-table-body"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div id="codes-pagination-info" class="text-sm text-gray-400">Showing 0 of 0</div>
                <div class="flex items-center gap-2">
                    <button id="codes-prev" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Prev</button>
                    <button id="codes-next" class="px-3 py-1 rounded bg-gray-700 text-white disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const calculateButton = document.getElementById('calculate-salaries');
    const saveButton = document.getElementById('save-salaries');
    const showEmployeesBtn = document.getElementById('show-employees-btn');
    const showCodesBtn = document.getElementById('show-codes-btn');
    const employeesModal = document.getElementById('employees-modal');
    const closeEmployeesModal = document.getElementById('close-employees-modal');
    const employeesSearch = document.getElementById('employees-search');
    const employeesTableBody = document.getElementById('employees-table-body');
    const employeesPrev = document.getElementById('employees-prev');
    const employeesNext = document.getElementById('employees-next');
    const employeesPaginationInfo = document.getElementById('employees-pagination-info');
    const salaryPrev = document.getElementById('salary-prev');
    const salaryNext = document.getElementById('salary-next');
    const salaryPaginationInfo = document.getElementById('salary-pagination-info');
    const salaryCalculationBody = document.getElementById('salary-calculation-body');
    const alertContainer = document.getElementById('alert-container');
    
    let currentSalaryData = null;
    // Employees overlay state
    const allEmployees = <?php echo json_encode($usersWithSalary, JSON_UNESCAPED_UNICODE); ?>;
    let filteredEmployees = allEmployees.slice();
    let employeesPage = 1;
    const employeesPageSize = 10;
    // Codes modal state
    const allCodes = <?php echo json_encode($attendanceMasterCodes, JSON_UNESCAPED_UNICODE); ?>;
    let filteredCodes = allCodes.slice();
    let codesPage = 1;
    const codesPageSize = 10;
    // Salary table pagination state
    let salaryPage = 1;
    const salaryPageSize = 10;

    // Function to load existing salary data for selected month/year
    function loadExistingSalaryData(month, year, showLoadingMessage = true) {
        if (!month || !year) {
            salaryCalculationBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center p-8 text-gray-400">
                        <div class="inline-flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Select month and year to view data...
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        if (showLoadingMessage) {
            salaryCalculationBody.innerHTML = `
                                    <tr>
                        <td colspan="8" class="text-center p-8 text-gray-400">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading existing salary data...
                            </div>
                        </td>
                    </tr>
            `;
        }

        // Check for existing salary data
        fetch(`actions/salary_data_loader.php?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    if (result.data.length > 0) {
                        // Display existing salary data
                        displaySalaryData(result.data, true);
                        currentSalaryData = { salaryData: result.data, month, year };
                        saveButton.disabled = true;
                        saveButton.innerHTML = '<i class="fas fa-check mr-2"></i> Already Saved';
                        showAlert(`Found ${result.data.length} existing salary records for ${month}/${year}`, 'info');
                    } else {
                        // No existing data, show empty state
                        salaryCalculationBody.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center p-8 text-gray-400">
                                    No salary records found for ${month}/${year}. Click "Calculate Salaries" to generate new records.
                                </td>
                            </tr>
                        `;
                        saveButton.disabled = true;
                        saveButton.innerHTML = 'Save Salaries';
                    }
                } else {
                    salaryCalculationBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center p-8 text-gray-400">
                                Error loading existing data. Click "Calculate Salaries" to generate new records.
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading existing data:', error);
                salaryCalculationBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center p-8 text-gray-400">
                            Error loading existing data. Click "Calculate Salaries" to generate new records.
                        </td>
                    </tr>
                `;
            });
    }

    function displaySalaryData(salaryData, isExisting = false) {
        // paginate
        const total = salaryData.length;
        const totalPages = Math.max(1, Math.ceil(total / salaryPageSize));
        if (salaryPage > totalPages) salaryPage = totalPages;
        const startIdx = (salaryPage - 1) * salaryPageSize;
        const pageItems = salaryData.slice(startIdx, startIdx + salaryPageSize);

        const salaryRows = pageItems.map(employee => {
            // Determine visual class based on advance status
            const hasAdvance = employee.has_advance || false;
            const advanceVisualClass = employee.advance_visual_class || '';
            const advanceIndicatorClass = employee.advance_indicator_class || '';
            const advanceBadgeText = employee.advance_badge_text || '';
            const advanceDeduction = (typeof employee.advance_deduction !== 'undefined')
                ? employee.advance_deduction
                : (typeof employee.advance_salary_deducted !== 'undefined' ? employee.advance_salary_deducted : 0);
            const otherDeductions = (typeof employee.statutory_total !== 'undefined')
                ? employee.statutory_total
                : (typeof employee.deductions !== 'undefined' ? employee.deductions : 0);
            const finalSalary = employee.final_salary || employee.calculated_salary;
            
            // Row class for visual categorization
            let rowClass = isExisting ? 'bg-green-900 bg-opacity-20' : '';
            if (hasAdvance && !isExisting) {
                rowClass = advanceVisualClass;
            }
            
            return `
            <tr class="${rowClass}">
                
                <!-- Employee Name with Advance Indicators -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    <div class="flex items-center">
                        <span>${employee.full_name}</span>
                        ${isExisting ? '<span class="ml-2 px-2 py-1 text-xs rounded bg-green-800 text-green-200">Saved</span>' : ''}
                        ${hasAdvance && !isExisting ? `<span class="ml-2 px-2 py-1 text-xs rounded bg-indigo-600 text-indigo-100">${advanceBadgeText}</span>` : ''}
                    </div>
                    ${hasAdvance && employee.total_outstanding ? 
                        `<div class="text-xs text-gray-500 mt-1">Outstanding: ₹${employee.total_outstanding.toLocaleString()}</div>` : ''
                    }
                </td>
                
                <!-- Attendance Types -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    ${Object.entries(employee.attendance_types || {})
                        .map(([type, count]) => `${type}: ${count}`)
                        .join(', ')}
                </td>
                
                <!-- Base Salary -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">₹${employee.base_salary.toLocaleString()}</td>
                
                <!-- Multiplier -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">${employee.total_multiplier.toFixed(2)}</td>
                
                <!-- Calculated Salary -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">₹${employee.calculated_salary.toLocaleString()}</td>
                
                <!-- Other Deductions (Statutory etc.) -->
                <td class="px-6 py-4 whitespace-nowrap text-sm ${otherDeductions > 0 ? 'text-red-400' : 'text-gray-300'}">
                    ${otherDeductions > 0 ? `-₹${otherDeductions.toLocaleString()}` : '₹0'}
                </td>
                
                <!-- Advance Deduction -->
                <td class="px-6 py-4 whitespace-nowrap text-sm ${advanceDeduction > 0 ? 'text-orange-400' : 'text-gray-300'}">
                    ${advanceDeduction > 0 ? `-₹${advanceDeduction.toLocaleString()}` : '₹0'}
                    ${hasAdvance && employee.advance_count ? 
                        `<div class="text-xs text-gray-500">${employee.advance_count} advance(s)</div>` : ''
                    }
                </td>
                
                <!-- Final Salary -->
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium ${advanceDeduction > 0 ? 'text-green-400' : 'text-gray-300'}">
                    ₹${finalSalary.toLocaleString()}
                </td>
                
                <!-- Actions -->
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                    ${isExisting ? 
                        '<span class="text-green-400">Already Saved</span>' : 
                        `<div class="flex space-x-2 justify-center">
                            <button class="text-blue-500 hover:text-blue-400" onclick="viewEmployeeDetails('${employee.user_id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${hasAdvance ? 
                                `<button class="text-orange-500 hover:text-orange-400" onclick="viewAdvanceDetails('${employee.user_id}')" title="View Advance Details">
                                    <i class="fas fa-credit-card"></i>
                                </button>` : ''
                            }
                         </div>`
                    }
                </td>
            </tr>
        `}).join('');

        salaryCalculationBody.innerHTML = salaryRows;
        // update pagination controls
        salaryPrev.disabled = salaryPage <= 1;
        salaryNext.disabled = salaryPage >= totalPages;
        salaryPaginationInfo.textContent = `Showing ${Math.min(total, startIdx + 1)}-${Math.min(total, startIdx + pageItems.length)} of ${total}`;
        
        // Add advance progress bars for employees with advances
        if (!isExisting) {
            addAdvanceProgressBars(salaryData);
        }
    }

    // Salary pagination handlers
    salaryPrev?.addEventListener('click', () => { if (salaryPage > 1 && currentSalaryData) { salaryPage--; displaySalaryData(currentSalaryData.salaryData, false); } });
    salaryNext?.addEventListener('click', () => { if (currentSalaryData) { const totalPages = Math.ceil(currentSalaryData.salaryData.length / salaryPageSize); if (salaryPage < totalPages) { salaryPage++; displaySalaryData(currentSalaryData.salaryData, false); } } });
    
    // Add advance progress bars to employees with advances
    function addAdvanceProgressBars(salaryData) {
        salaryData.forEach((employee, index) => {
            if (employee.has_advance && employee.advance_details && employee.advance_details.length > 0) {
                const row = salaryCalculationBody.children[index];
                const nameCell = row.children[0];
                
                employee.advance_details.forEach(advance => {
                    const progressPercentage = advance.remaining_balance_before > 0 ? 
                        ((advance.remaining_balance_before - advance.remaining_balance_after) / advance.remaining_balance_before) * 100 : 0;
                    
                    const progressBar = document.createElement('div');
                    progressBar.className = 'mt-2';
                    progressBar.innerHTML = `
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>Advance Progress</span>
                            <span>${progressPercentage.toFixed(1)}%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" style="width: ${progressPercentage}%"></div>
                        </div>
                    `;
                    nameCell.appendChild(progressBar);
                });
            }
        });
    }

    function showAlert(message, type = 'info') {
        const alertTypes = {
            'info': 'bg-blue-500',
            'success': 'bg-green-500',
            'error': 'bg-red-500',
            'warning': 'bg-yellow-500'
        };

        alertContainer.innerHTML = `
            <div class="p-4 rounded-lg ${alertTypes[type] || alertTypes['info']} text-white">
                ${message}
            </div>
        `;
        alertContainer.classList.remove('hidden');

        // Auto-hide after 5 seconds
        setTimeout(() => {
            alertContainer.classList.add('hidden');
        }, 5000);
    }

    // Load existing data when month or year changes
    monthSelect.addEventListener('change', () => {
        loadExistingSalaryData(monthSelect.value, yearSelect.value);
    });

    yearSelect.addEventListener('change', () => {
        loadExistingSalaryData(monthSelect.value, yearSelect.value);
    });

    // Load existing data on page load
    window.addEventListener('load', () => {
        loadExistingSalaryData(monthSelect.value, yearSelect.value, false);
    });

    calculateButton.addEventListener('click', () => {
        const month = monthSelect.value;
        const year = yearSelect.value;

        if (!month || !year) {
            showAlert('Please select both month and year', 'error');
            return;
        }

        // Simulate loading state
        salaryCalculationBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center p-8 text-gray-400">
                    <div class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Calculating salaries...
                    </div>
                </td>
            </tr>
        `;

        // Fetch salary calculation data
        fetch(`actions/salary_calculation_controller.php?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const salaryData = result.data;
                    currentSalaryData = { salaryData, month, year };
                    
                    if (salaryData.length === 0) {
                        showAlert('No salary data found for the selected month and year. Check your attendance records.', 'warning');
                        salaryCalculationBody.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center p-8 text-gray-400">
                                    <div class="text-yellow-500">
                                        <p class="text-lg mb-4">No salary data available</p>
                                        <p class="text-sm text-gray-500">Possible reasons:</p>
                                        <ul class="text-sm text-gray-500 list-disc list-inside">
                                            <li>No attendance records for the selected month</li>
                                            <li>No employees with monthly salary</li>
                                            <li>Incorrect attendance master configuration</li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        `;
                        saveButton.disabled = true;
                        return;
                    }

                    displaySalaryData(salaryData, false);
                    saveButton.disabled = false;
                    showAlert(`Salary calculated for ${result.month}/${result.year}. Click "Save Salaries" to save to database.`, 'success');
                } else {
                    showAlert(result.error || 'Failed to calculate salaries', 'error');
                    saveButton.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while calculating salaries', 'error');
                salaryCalculationBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center p-8 text-gray-400">
                            Failed to load salary data
                        </td>
                    </tr>
                `;
            });
    });
    
    // Save salaries button event listener
        saveButton.addEventListener('click', () => {
        if (!currentSalaryData) {
            showAlert('No salary data to save. Please calculate salaries first.', 'error');
            return;
        }
        
        // Show loading state
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
        
        // Save salary data
        fetch('actions/salary_save_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(currentSalaryData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert(`Salaries saved successfully for ${currentSalaryData.month}/${currentSalaryData.year}!`, 'success');
                saveButton.innerHTML = '<i class="fas fa-check mr-2"></i> Saved';
                // Keep disabled to prevent duplicate saves
            } else {
                const errMsg = result.message || result.error || 'Failed to save salaries';
                showAlert(errMsg, 'error');
                saveButton.disabled = false;
                saveButton.innerHTML = 'Save Salaries';
            }
        })
        .then(() => {
            // On success, refresh the page to show persisted states
            setTimeout(() => { window.location.reload(); }, 500);
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while saving salaries', 'error');
            saveButton.disabled = false;
            saveButton.innerHTML = 'Save Salaries';
        });
    });
    
    // Functions for viewing employee and advance details
        window.viewEmployeeDetails = function(userId) {
        window.location.href = `index.php?page=view-employee&id=${userId}`;
    };
    
        window.viewAdvanceDetails = function(userId) {
        showAdvanceDetailsModal(userId);
    };
    
    function showAdvanceDetailsModal(userId) {
        // Create advance details modal
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-96 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">Advance Details</h3>
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="advance-details-content" class="text-white">
                    <div class="flex items-center justify-center py-8">
                        <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="ml-2">Loading advance details...</span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Load advance details
        // Use new advance_payments source
        fetch(`actions/advance/advance_dashboard_controller.php?action=advance_history&user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                const content = document.getElementById('advance-details-content');
                if (data.success && data.data.advances && data.data.advances.length > 0) {
                    const advances = data.data.advances;
                    content.innerHTML = advances.map(advance => `
                        <div class="bg-gray-700 rounded-lg p-4 mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-medium">Advance ID: ${advance.advance_request_id || advance.id}</h4>
                                <span class="px-2 py-1 text-xs rounded ${advance.status === 'active' ? 'bg-blue-600' : 'bg-green-600'} text-white">
                                    ${advance.status}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-400">Total Amount:</span>
                                    <span class="text-white">₹${advance.total_advance_amount.toLocaleString()}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Remaining:</span>
                                    <span class="text-orange-400">₹${advance.remaining_balance.toLocaleString()}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Monthly Deduction:</span>
                                    <span class="text-white">₹${advance.monthly_deduction_amount.toLocaleString()}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Progress:</span>
                                    <span class="text-green-400">${advance.progress_percentage || 0}%</span>
                                </div>
                            </div>
                            ${advance.status === 'active' ? `
                                <div class="mt-3">
                                    <div class="w-full bg-gray-600 rounded-full h-2">
                                        <div class="bg-orange-500 h-2 rounded-full" style="width: ${advance.progress_percentage || 0}%"></div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    content.innerHTML = '<div class="text-center text-gray-400 py-8">No advance details found</div>';
                }
            })
            .catch(error => {
                console.error('Error loading advance details:', error);
                document.getElementById('advance-details-content').innerHTML = 
                    '<div class="text-center text-red-400 py-8">Error loading advance details</div>';
            });
    }

    // Employees Modal logic
    function renderEmployees() {
        const total = filteredEmployees.length;
        const totalPages = Math.max(1, Math.ceil(total / employeesPageSize));
        if (employeesPage > totalPages) employeesPage = totalPages;
        const startIdx = (employeesPage - 1) * employeesPageSize;
        const pageItems = filteredEmployees.slice(startIdx, startIdx + employeesPageSize);
        employeesTableBody.innerHTML = pageItems.map(u => `
            <tr class="border-b border-gray-700">
                <td class="py-2 px-3">${(u.first_name || '') + ' ' + (u.surname || '')}</td>
                <td class="py-2 px-3 text-right">₹${parseFloat(u.salary || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
            </tr>
        `).join('');
        employeesPrev.disabled = employeesPage <= 1;
        employeesNext.disabled = employeesPage >= totalPages;
        employeesPaginationInfo.textContent = `Showing ${Math.min(total, startIdx + 1)}-${Math.min(total, startIdx + pageItems.length)} of ${total}`;
    }

    showEmployeesBtn?.addEventListener('click', () => {
        employeesPage = 1;
        filteredEmployees = allEmployees.slice();
        employeesSearch.value = '';
        renderEmployees();
        employeesModal.classList.remove('hidden');
    });
    closeEmployeesModal?.addEventListener('click', () => employeesModal.classList.add('hidden'));
    employeesPrev?.addEventListener('click', () => { if (employeesPage > 1) { employeesPage--; renderEmployees(); } });
    employeesNext?.addEventListener('click', () => { const totalPages = Math.ceil(filteredEmployees.length / employeesPageSize); if (employeesPage < totalPages) { employeesPage++; renderEmployees(); } });
    employeesSearch?.addEventListener('input', () => {
        const q = employeesSearch.value.trim().toLowerCase();
        filteredEmployees = allEmployees.filter(u => (`${u.first_name || ''} ${u.surname || ''}`).toLowerCase().includes(q));
        employeesPage = 1;
        renderEmployees();
    });

    // Codes Modal logic
    const codesModal = document.getElementById('codes-modal');
    const closeCodesModal = document.getElementById('close-codes-modal');
    const codesSearch = document.getElementById('codes-search');
    const codesTableBody = document.getElementById('codes-table-body');
    const codesPrev = document.getElementById('codes-prev');
    const codesNext = document.getElementById('codes-next');
    const codesPaginationInfo = document.getElementById('codes-pagination-info');

    function renderCodes() {
        const total = filteredCodes.length;
        const totalPages = Math.max(1, Math.ceil(total / codesPageSize));
        if (codesPage > totalPages) codesPage = totalPages;
        const startIdx = (codesPage - 1) * codesPageSize;
        const pageItems = filteredCodes.slice(startIdx, startIdx + codesPageSize);
        codesTableBody.innerHTML = pageItems.map(c => `
            <tr class=\"border-b border-gray-700\">
                <td class=\"py-2 px-3\">${c.code}</td>
                <td class=\"py-2 px-3\">${c.name}</td>
                <td class=\"py-2 px-3 text-right\">${c.multiplier}</td>
            </tr>
        `).join('');
        codesPrev.disabled = codesPage <= 1;
        codesNext.disabled = codesPage >= totalPages;
        codesPaginationInfo.textContent = `Showing ${Math.min(total, startIdx + 1)}-${Math.min(total, startIdx + pageItems.length)} of ${total}`;
    }

    showCodesBtn?.addEventListener('click', () => {
        codesPage = 1;
        filteredCodes = allCodes.slice();
        codesSearch.value = '';
        renderCodes();
        codesModal.classList.remove('hidden');
    });
    closeCodesModal?.addEventListener('click', () => codesModal.classList.add('hidden'));
    codesPrev?.addEventListener('click', () => { if (codesPage > 1) { codesPage--; renderCodes(); } });
    codesNext?.addEventListener('click', () => { const totalPages = Math.ceil(filteredCodes.length / codesPageSize); if (codesPage < totalPages) { codesPage++; renderCodes(); } });
    codesSearch?.addEventListener('input', () => {
        const q = codesSearch.value.trim().toLowerCase();
        filteredCodes = allCodes.filter(c => (c.code || '').toLowerCase().includes(q) || (c.name || '').toLowerCase().includes(q));
        codesPage = 1;
        renderCodes();
    });
});
</script>

<?php require_once 'UI/dashboard_layout_footer.php'; ?> 