<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

// Statistics queries
$totalClients = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data')->fetch()['cnt'];

// Clients onboarded this month
$startOfMonth = date('Y-m-01');
$clientsThisMonth = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data WHERE onboarding_date >= ?', [$startOfMonth])->fetch()['cnt'];

// Contracts expiring in next 90 days
$today = date('Y-m-d');
$ninetyDaysLater = date('Y-m-d', strtotime('+90 days'));
$expiringContracts = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data WHERE contract_expiry_date BETWEEN ? AND ?', [$today, $ninetyDaysLater])->fetch()['cnt'];

// Expired contracts
$expiredContracts = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data WHERE contract_expiry_date < ?', [$today])->fetch()['cnt'];

// Get client types for filter
$clientTypes = $db->query('SELECT id, type_name FROM client_types ORDER BY type_name ASC')->fetchAll();
?>

<h1 class="text-3xl font-bold mb-6">Client List</h1>

<!-- Statistics Cards -->
<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Clients Card -->
    <div class="bg-gradient-to-br from-blue-600 to-blue-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-blue-100 mb-1">Total Clients</p>
                <h3 class="text-2xl font-bold"><?php echo number_format($totalClients); ?></h3>
            </div>
            <div class="bg-blue-500/20 p-2 rounded-full">
                <i class="fas fa-users text-lg"></i>
            </div>
        </div>
        <div class="mt-2 flex items-center text-xs text-blue-100 opacity-90">
            <i class="fas fa-chart-line mr-1"></i>
            <span>All clients</span>
        </div>
    </div>

    <!-- This Month's Onboarding Card -->
    <div class="bg-gradient-to-br from-green-600 to-green-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-green-100 mb-1">This Month</p>
                <h3 class="text-2xl font-bold"><?php echo number_format($clientsThisMonth); ?></h3>
            </div>
            <div class="bg-green-500/20 p-2 rounded-full">
                <i class="fas fa-user-plus text-lg"></i>
            </div>
        </div>
        <div class="mt-2 flex items-center text-xs text-green-100 opacity-90">
            <i class="fas fa-calendar-alt mr-1"></i>
            <span><?php echo date('M Y'); ?></span>
        </div>
    </div>

    <!-- Expiring Contracts Card -->
    <div class="bg-gradient-to-br from-amber-600 to-amber-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-100 mb-1">Expiring Soon</p>
                <h3 class="text-2xl font-bold"><?php echo number_format($expiringContracts); ?></h3>
            </div>
            <div class="bg-amber-500/20 p-2 rounded-full">
                <i class="fas fa-clock text-lg"></i>
            </div>
        </div>
        <div class="mt-2 flex items-center text-xs text-amber-100 opacity-90">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <span>90 days</span>
        </div>
    </div>

    <!-- Expired Contracts Card -->
    <div class="bg-gradient-to-br from-red-600 to-red-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-red-100 mb-1">Expired</p>
                <h3 class="text-2xl font-bold"><?php echo number_format($expiredContracts); ?></h3>
            </div>
            <div class="bg-red-500/20 p-2 rounded-full">
                <i class="fas fa-exclamation-circle text-lg"></i>
            </div>
        </div>
        <div class="mt-2 flex items-center text-xs text-red-100 opacity-90">
            <i class="fas fa-calendar-times mr-1"></i>
            <span>Attention needed</span>
        </div>
    </div>
</div>

<!-- Search and Filter Controls -->
<div class="mb-6 flex flex-col sm:flex-row gap-4">
    <div class="relative flex-grow">
        <input type="text" id="search-input" placeholder="Search by name, address, or ID..." class="w-full bg-gray-700 p-3 pl-10 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
    </div>
    <div class="flex flex-col sm:flex-row gap-2">
        <!-- Status Filter -->
        <select id="status-filter" class="bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
            <option value="">All Contract Statuses</option>
            <option value="ongoing">Ongoing</option>
            <option value="expiring">Expiring Soon</option>
            <option value="expired">Expired</option>
        </select>
        
        <!-- Compliance Filter -->
        <select id="compliance-filter" class="bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
            <option value="">All Compliance</option>
            <option value="1">Compliant</option>
            <option value="0">Non-Compliant</option>
        </select>

        <!-- Client Type Filter -->
        <select id="type-filter" class="bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
            <option value="">All Client Types</option>
            <?php foreach ($clientTypes as $type): ?>
                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-700">
            <tr>
                <th class="py-3 px-6 text-left">ID</th>
                <th class="py-3 px-6 text-left">Client name</th>
                <th class="py-3 px-6 text-left">Address</th>
                <th class="py-3 px-6 text-left">Onboarded</th>
                <th class="py-3 px-6 text-left">Contract Status</th>
                <th class="py-3 px-6 text-left">Compliance</th>
                <th class="py-3 px-6 text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="society-table-body">
            <!-- Rows will be inserted here by JavaScript -->
            <tr><td colspan="7" class="text-center p-8">Loading clients...</td></tr>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<div class="flex flex-col md:flex-row justify-between items-center mt-6 text-white">
    <div class="text-sm text-gray-400 mb-2 sm:mb-0">
        Showing <span id="pagination-start">0</span> to <span id="pagination-end">0</span> of <span id="pagination-total">0</span> entries
    </div>
    <div class="flex items-center space-x-2" id="pagination-buttons">
        <button id="prev-page" class="px-3 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-chevron-left"></i>
        </button>
        <div id="page-numbers" class="flex items-center space-x-1"></div>
        <button id="next-page" class="px-3 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('society-table-body');
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');
    const typeFilter = document.getElementById('type-filter');
    const complianceFilter = document.getElementById('compliance-filter');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageNumbers = document.getElementById('page-numbers');
    const paginationStart = document.getElementById('pagination-start');
    const paginationEnd = document.getElementById('pagination-end');
    const paginationTotal = document.getElementById('pagination-total');
    
    let currentPage = 1;
    const itemsPerPage = 10;
    let totalPages = 1;
    let totalItems = 0;
    let searchTerm = '';
    let statusValue = '';
    let typeValue = '';
    let complianceValue = '';
    let debounceTimer;
    
    // Today's date for status calculations
    const today = new Date();

    // Fetch societies data
    async function fetchSocieties(page = 1, search = '', status = '', type = '', compliance = '') {
        try {
            // Build the URL with parameters
            let url = `index.php?action=get_societies&page=${page}&per_page=${itemsPerPage}`;
            
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;
            if (type) url += `&client_type_id=${encodeURIComponent(type)}`;
            if (compliance !== '') url += `&compliance=${encodeURIComponent(compliance)}`;
            
            console.log('Fetching societies with URL:', url);
            
            const response = await fetch(url);
            const data = await response.json();
            
            console.log('API Response:', data);
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.error || 'Failed to fetch societies');
            }
        } catch (error) {
            console.error('Error fetching societies:', error);
            if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
            GaurdUI.showToast('Failed to load societies: ' + error.message, 'error');
            } else {
                alert('Failed to load societies: ' + error.message);
            }
            return { societies: [], pagination: { total: 0, current_page: 1, last_page: 1 } };
        }
    }

    // Calculate contract status
    function getStatus(expiryDate) {
        if (!expiryDate) {
            return { text: 'N/A', class: 'bg-gray-500', key: 'unknown' };
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const expiry = new Date(expiryDate);
        const ninetyDays = new Date();
        ninetyDays.setDate(today.getDate() + 90);

        if (expiry < today) {
            return { text: 'Expired', class: 'bg-red-600', key: 'expired' };
        } else if (expiry <= ninetyDays) {
            return { text: 'Expiring Soon', class: 'bg-yellow-500', key: 'expiring' };
        } else {
            return { text: 'Ongoing', class: 'bg-green-600', key: 'ongoing' };
        }
    }

    function getComplianceStatus(status) {
        if (status == 1) {
            return { text: 'Compliant', class: 'bg-green-600', key: '1' };
        }
        return { text: 'Non-Compliant', class: 'bg-red-600', key: '0' };
    }

    // Filter by status on the client side if server doesn't support status filtering
    function filterSocietiesByStatus(societies, status) {
        if (!status) return societies;
        return societies.filter(society => {
            const contractStatus = getStatus(society.contract_expiry_date);
            // Normalize both to lowercase for comparison
            return contractStatus.key === status.toLowerCase();
        });
    }

    function filterSocietiesByCompliance(societies, compliance) {
        if (!compliance && compliance !== 0) return societies;
        return societies.filter(society => {
            const complianceStatus = getComplianceStatus(society.compliance_status);
            // Compare as string for strict match
            return complianceStatus.key === String(compliance);
        });
    }

    // Render societies table
    function renderSocieties(societies) {
        if (!societies || societies.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center p-8 text-gray-400">No clients found matching your criteria.</td></tr>`;
            return;
        }

        console.log(`Rendering ${societies.length} societies`);
        
        // Add debugging - check contract dates
        societies.forEach(society => {
            if (society.contract_expiry_date) {
                console.log(`Society ${society.id}: Contract expiry date from DB: ${society.contract_expiry_date}`);
            } else {
                console.log(`Society ${society.id}: No contract expiry date in DB`);
            }
        });

        let rows = '';
        societies.forEach(s => {
            const status = getStatus(s.contract_expiry_date);
            const compliance = getComplianceStatus(s.compliance_status);
            const address = `${s.street_address}, ${s.city}, ${s.state} - ${s.pin_code}`;

            rows += `
                <tr class="hover:bg-gray-700/50 transition duration-150">
                    <td class="py-3 px-6 text-sm text-gray-300">${s.id}</td>
                    <td class="py-3 px-6 text-sm font-medium text-white">${s.society_name}</td>
                    <td class="py-3 px-6 text-sm text-gray-400 max-w-xs truncate">${address}</td>
                    <td class="py-3 px-6 text-sm text-gray-300">${new Date(s.onboarding_date).toLocaleDateString()}</td>
                    <td class="py-3 px-6 text-sm">
                        <span class="px-2 py-1 text-xs font-semibold text-white rounded-full ${status.class}">
                            ${status.text}
                        </span>
                    </td>
                    <td class="py-3 px-6 text-sm">
                        <span class="px-2 py-1 text-xs font-semibold text-white rounded-full ${compliance.class}">
                            ${compliance.text}
                        </span>
                </td>
                    <td class="py-3 px-6 text-center">
                        <a href="index.php?page=edit-society&id=${s.id}" class="text-blue-400 hover:text-blue-300 mr-3"><i class="fas fa-pencil-alt"></i></a>
                        <a href="index.php?page=view-society&id=${s.id}" class="text-green-400 hover:text-green-300"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            `;
        });
        tableBody.innerHTML = rows;
    }

    // Update pagination controls
    function updatePagination(current, total, items) {
        totalItems = items;
        totalPages = total;
        
        // Update pagination info
        const start = items === 0 ? 0 : ((current - 1) * itemsPerPage) + 1;
        const end = Math.min(current * itemsPerPage, items);
        
        paginationStart.textContent = start;
        paginationEnd.textContent = end;
        paginationTotal.textContent = items;
        
        // Update pagination buttons
        prevPageBtn.disabled = current <= 1;
        nextPageBtn.disabled = current >= totalPages;
        
        // Generate page numbers
        pageNumbers.innerHTML = '';
        const maxButtons = 5;
        let startPage = Math.max(1, current - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        
        if (endPage - startPage + 1 < maxButtons) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `px-3 py-1 rounded ${i === current ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => loadPage(i);
            pageNumbers.appendChild(pageBtn);
        }
    }

    // Load a specific page
    async function loadPage(page) {
        if (page < 1 || (totalPages > 0 && page > totalPages)) return;
        
        currentPage = page;
        tableBody.innerHTML = `<tr><td colspan="7" class="text-center p-8 text-gray-400">Loading...</td></tr>`;
        
        const data = await fetchSocieties(page, searchTerm, statusValue, typeValue, complianceValue);
        
        if (!data || !data.societies) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center p-8 text-red-400">Failed to load data. Please try again.</td></tr>`;
            return;
        }
        
        // If server doesn't handle status filtering, do it client-side
        let societies = data.societies;
        if (statusValue) {
            societies = filterSocietiesByStatus(societies, statusValue);
        }
        if (complianceValue) {
            societies = filterSocietiesByCompliance(societies, complianceValue);
        }
        
        renderSocieties(societies);
        
        // Check if pagination data exists in the response
        if (data.pagination) {
            updatePagination(
                data.pagination.current_page || 1, 
                data.pagination.last_page || data.pagination.total_pages || 1, 
                data.pagination.total || data.pagination.total_records || societies.length
            );
        } else {
            updatePagination(1, 1, societies.length);
        }
    }

    // Initialize the page
    async function init() {
        try {
        await loadPage(1);
        
        // Search input handler with debounce
            searchInput.addEventListener('keyup', (e) => {
            clearTimeout(debounceTimer);
            searchTerm = e.target.value.trim();
            debounceTimer = setTimeout(() => loadPage(1), 500);
        });
            
            // Status filter handler
            statusFilter.addEventListener('change', (e) => {
                statusValue = e.target.value;
                loadPage(1);
            });
            
            // Type filter handler
            typeFilter.addEventListener('change', (e) => {
                typeValue = e.target.value;
                loadPage(1);
            });

            // Compliance filter handler
            complianceFilter.addEventListener('change', (e) => {
                complianceValue = e.target.value;
                loadPage(1);
            });
        
        // Pagination button handlers
        prevPageBtn.addEventListener('click', () => loadPage(currentPage - 1));
        nextPageBtn.addEventListener('click', () => loadPage(currentPage + 1));
        } catch (error) {
            console.error('Initialization error:', error);
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center p-8 text-red-400">Failed to initialize: ` + error.message + `</td></tr>`;
        }
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize the page
    init();
});

// Global functions for action buttons
function viewSociety(id) {
    window.location.href = `index.php?page=view-society&id=${id}`;
}

function editSociety(id) {
    window.location.href = `index.php?page=edit-society&id=${id}`;
}

function deleteSociety(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        fetch(`index.php?action=delete_society`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
                GaurdUI.showToast('Society deleted successfully', 'success');
                } else {
                    alert('Society deleted successfully');
                }
                // Reload the current page
                window.location.reload();
            } else {
                throw new Error(data.error || 'Failed to delete Client');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
            GaurdUI.showToast('Failed to delete Client: ' + error.message, 'error');
            } else {
                alert('Failed to delete Client: ' + error.message);
            }
        });
    }
}
</script>