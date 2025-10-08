<?php
// Salary Slips View
global $page, $company_settings;
require_once 'UI/dashboard_layout.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-gray-800 rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <div class="flex space-x-4">
                <select id="month-select" class="bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Select Month</option>
                    <?php 
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 
                        4 => 'April', 5 => 'May', 6 => 'June', 
                        7 => 'July', 8 => 'August', 9 => 'September', 
                        10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    $currentMonth = date('n');
                    foreach ($months as $num => $name) {
                        echo "<option value='{$num}'>{$name}</option>";
                    }
                    ?>
                </select>
                
                <select id="year-select" class="bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">Select Year</option>
                    <?php 
                    $currentYear = date('Y');
                    for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                        echo "<option value='{$year}'>{$year}</option>";
                    }
                    ?>
                </select>
                
                <button id="generate-slips" class="bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Filter
                </button>
            </div>
            
            <div>
                <input type="text" id="search-employees" 
                       class="bg-gray-700 text-white px-4 py-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none" 
                       placeholder="Search employees...">
            </div>
        </div>

        <div id="alert-container" class="mb-4 hidden">
            <!-- Alert messages will be inserted here -->
        </div>

        <div class="bg-gray-900 rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Employee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Month</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Year</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Base Salary</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Net Salary</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="salary-slips-body" class="bg-gray-900 divide-y divide-gray-700">
                    <tr>
                        <td colspan="6" class="text-center p-8 text-gray-400">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Waiting to generate salary slips...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="pagination-container" class="flex items-center justify-between mt-4 hidden">
            <div id="pagination-summary" class="text-gray-400 text-sm"></div>
            <div class="flex items-center gap-2">
                <button id="prev-page" class="px-3 py-2 bg-gray-700 text-white rounded disabled:opacity-50">Prev</button>
                <span id="current-page" class="text-gray-300 text-sm"></span>
                <button id="next-page" class="px-3 py-2 bg-gray-700 text-white rounded disabled:opacity-50">Next</button>
                <select id="page-size" class="ml-2 bg-gray-700 text-white p-2 rounded border border-gray-600">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const generateSlipsButton = document.getElementById('generate-slips');
    const searchInput = document.getElementById('search-employees');
    const salarySlipsBody = document.getElementById('salary-slips-body');
    const alertContainer = document.getElementById('alert-container');
    const paginationContainer = document.getElementById('pagination-container');
    const paginationSummary = document.getElementById('pagination-summary');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const currentPageLabel = document.getElementById('current-page');
    const pageSizeSelect = document.getElementById('page-size');
    const bulkEmailButton = document.getElementById('bulk-email');

    let allRecords = [];
    let filteredRecords = [];
    let currentPage = 1;
    let pageSize = parseInt(pageSizeSelect ? pageSizeSelect.value : 10);

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

    function loadSalarySlips(month, year) {
        const isAll = !month || !year;

        // Show loading state
        salarySlipsBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center p-8 text-gray-400">
                    <div class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        ${isAll ? 'Loading all salary slips...' : 'Loading salary slips...'}
                    </div>
                </td>
            </tr>
        `;

        // Build URL for API
        const url = isAll
            ? 'actions/salary_slips_controller.php'
            : `actions/salary_slips_controller.php?month=${month}&year=${year}`;

        fetch(url)
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data.length > 0) {
                    allRecords = result.data;
                    filteredRecords = allRecords.slice();
                    currentPage = 1;
                    renderPage();
                } else {
                    allRecords = [];
                    filteredRecords = [];
                    salarySlipsBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center p-8 text-gray-400">
                                ${isAll ? 'No salary records found' : `No salary records found for ${months[month]} ${year}`}
                            </td>
                        </tr>
                    `;
                    paginationContainer.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Error loading salary slips:', error);
                salarySlipsBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center p-8 text-red-400">
                            Error loading salary slips. Please try again.
                        </td>
                    </tr>
                `;
                paginationContainer.classList.add('hidden');
            });
    }

    function displaySalarySlips(records) {
        const rows = records.map(record => {
            let monthName = 'N/A';
            let year = '';
            if (record.month && record.month.includes('-')) {
                const [y, m] = record.month.split('-');
                year = y;
                monthName = months[parseInt(m)];
            } else if (record.year && record.month) {
                year = String(record.year);
                monthName = months[parseInt(record.month)];
            }
            
            return `
                <tr class="hover:bg-gray-800">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <div class="flex items-center">
                            <span>${record.full_name}</span>
                            ${record.advance_salary_deducted > 0 ? 
                                '<span class="ml-2 px-2 py-1 text-xs rounded bg-orange-600 text-orange-200">Advance Deducted</span>' : ''
                            }
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">${monthName || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">${year || ''}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">₹${record.base_salary.toLocaleString()}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <div class="flex flex-col">
                            <span class="font-medium">₹${record.final_salary.toLocaleString()}</span>
                            ${record.advance_salary_deducted > 0 ? 
                                `<span class="text-xs text-orange-400">-₹${record.advance_salary_deducted.toLocaleString()} advance</span>` : ''
                            }
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex justify-center space-x-2">
                            <button onclick="downloadPDF(${record.id})" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm" title="Download PDF">
                                <i class="fas fa-download mr-1"></i>Download
                            </button>
                            <button onclick="previewPDF(${record.id})" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm ml-2" title="Preview PDF">
                                <i class="fas fa-eye mr-1"></i>Preview
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        salarySlipsBody.innerHTML = rows;
    }

    generateSlipsButton.addEventListener('click', () => {
        const month = monthSelect.value;
        const year = yearSelect.value;
        
        if (!month || !year) {
            showAlert('Please select both month and year', 'error');
            return;
        }
        
        // Simulate loading state
        salarySlipsBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center p-8 text-gray-400">
                    <div class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Generating salary slips...
                    </div>
                </td>
            </tr>
        `;

        // Load actual salary slips
        loadSalarySlips(month, year);
        showAlert(`Loading salary slips for ${months[month]} ${year}`, 'info');
    });
    
    if (bulkEmailButton) {
        bulkEmailButton.addEventListener('click', () => {
            const month = monthSelect.value;
            const year = yearSelect.value;
            
            if (!month || !year) {
                showAlert('Please select both month and year', 'error');
                return;
            }
            
            // TODO: Implement AJAX call to email all salary slips
            showAlert(`Emailing salary slips for ${months[month]} ${year}`, 'success');
        });
    }
    
    // Months object for reference
    const months = {
        1: 'January', 2: 'February', 3: 'March', 
        4: 'April', 5: 'May', 6: 'June', 
        7: 'July', 8: 'August', 9: 'September', 
        10: 'October', 11: 'November', 12: 'December'
    };

    // Load slips when month or year changes
    monthSelect.addEventListener('change', () => {
        if (monthSelect.value && yearSelect.value) {
            loadSalarySlips(monthSelect.value, yearSelect.value);
        }
    });

    yearSelect.addEventListener('change', () => {
        if (monthSelect.value && yearSelect.value) {
            loadSalarySlips(monthSelect.value, yearSelect.value);
        }
    });

    // Search functionality with pagination
    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        if (allRecords.length === 0) { return; }
        filteredRecords = allRecords.filter(r => r.full_name.toLowerCase().includes(term));
        currentPage = 1;
        renderPage();
    });

    // Pagination handlers
    if (pageSizeSelect) {
        pageSizeSelect.addEventListener('change', () => {
            pageSize = parseInt(pageSizeSelect.value);
            currentPage = 1;
            renderPage();
        });
    }
    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderPage();
            }
        });
    }
    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil((filteredRecords.length || 0) / pageSize));
            if (currentPage < totalPages) {
                currentPage++;
                renderPage();
            }
        });
    }

    function renderPage() {
        const total = filteredRecords.length;
        if (total === 0) {
            salarySlipsBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center p-8 text-gray-400">No records</td>
                </tr>
            `;
            paginationContainer.classList.add('hidden');
            return;
        }
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        const pageSlice = filteredRecords.slice(start, end);
        displaySalarySlips(pageSlice);
        paginationContainer.classList.remove('hidden');
        paginationSummary.textContent = `Showing ${start + 1}-${end} of ${total}`;
        currentPageLabel.textContent = `Page ${currentPage} / ${totalPages}`;
        prevPageBtn.disabled = currentPage === 1;
        nextPageBtn.disabled = currentPage === totalPages;
    }

    // Global functions for action buttons
    window.downloadPDF = function(recordId) {
        window.open(`actions/salary_slip_controller.php?action=download&id=${recordId}`, '_blank');
    };

    window.previewPDF = function(recordId) {
        // Open PDF in a new window for preview
        const previewWindow = window.open(`actions/salary_slip_controller.php?action=download&id=${recordId}`, '_blank');
        if (!previewWindow) {
            showAlert('Please allow popups to preview the PDF', 'warning');
        }
    };
    // Auto-select last month and load existing slips by default
    (function initDefaultPeriodAndLoad() {
        // Load all slips by default; user can narrow with month/year
        loadSalarySlips('', '');
    })();
});
</script>

<?php require_once 'UI/dashboard_layout_footer.php'; ?> 