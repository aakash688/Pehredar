<?php
require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

$totalTypes = $db->query('SELECT COUNT(*) as cnt FROM client_types')->fetch()['cnt'];
$totalClients = $db->query('SELECT COUNT(*) as cnt FROM society_onboarding_data')->fetch()['cnt'];
$avgClientsPerType = $db->query('SELECT AVG(cnt) as avg_cnt FROM (SELECT COUNT(*) as cnt FROM society_onboarding_data GROUP BY client_type_id) as sub')->fetch()['avg_cnt'] ?? 0;
$avgClientsPerType = $avgClientsPerType ? round($avgClientsPerType) : '0';

// Initial data load - first page only
$limit = 10;
$clientTypes = $db->query('SELECT * FROM client_types ORDER BY id DESC LIMIT ?', [$limit])->fetchAll();
$totalItems = $db->query('SELECT COUNT(*) as total FROM client_types')->fetch()['total'];
$totalPages = ceil($totalItems / $limit);
?>
<!-- Include shared components -->
<script src="UI/assets/js/shared-components.js"></script>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg">
    <!-- Toast Notification Container -->
    <div id="gaurd-toast-container" class="fixed top-4 right-4 z-50"></div>
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <h1 class="text-2xl md:text-3xl font-bold text-white">Client Types</h1>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg transition-all duration-300 flex items-center gap-2">
            <i class="fas fa-plus"></i> Add New Type
        </button>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col items-center border border-gray-700 transition-all duration-300 hover:shadow-lg hover:border-gray-600">
            <div class="text-3xl font-bold text-white mb-1" id="stat-total-types"><?php echo $totalTypes; ?></div>
            <div class="text-gray-400">Total Types</div>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col items-center border border-gray-700 transition-all duration-300 hover:shadow-lg hover:border-gray-600">
            <div class="text-3xl font-bold text-white mb-1" id="stat-avg-clients"><?php echo $avgClientsPerType; ?></div>
            <div class="text-gray-400">Avg Clients per Type</div>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col items-center border border-gray-700 transition-all duration-300 hover:shadow-lg hover:border-gray-600">
            <div class="text-3xl font-bold text-white mb-1" id="stat-total-clients"><?php echo $totalClients; ?></div>
            <div class="text-gray-400">Total Clients</div>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="flex flex-col md:flex-row justify-between gap-4 mb-4">
        <div class="relative flex-grow">
            <input 
                type="text" 
                id="search-input" 
                placeholder="Search by name or description..." 
                class="w-full px-4 py-2 pl-10 rounded bg-gray-900 text-white border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <div class="absolute left-3 top-2.5 text-gray-400">
                <i class="fas fa-search"></i>
            </div>
        </div>
        
        <div class="flex items-center">
            <span class="text-gray-400 mr-2">Items per page:</span>
            <select id="items-per-page" class="bg-gray-900 text-white border border-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>
    
    <!-- Main Table -->
    <div id="table-container" class="bg-gray-900 rounded-lg overflow-hidden">
        <!-- Table Content -->
        <div id="table-content" class="min-h-[400px] relative">
            <table class="min-w-full text-sm text-left text-gray-300">
                <thead class="text-xs text-gray-400 uppercase bg-gray-900 border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4 w-16">ID</th>
                        <th class="px-6 py-4">TYPE NAME</th>
                        <th class="px-6 py-4">DESCRIPTION</th>
                        <th class="px-6 py-4 text-right">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="client-types-tbody">
                    <?php foreach ($clientTypes as $ct): ?>
                        <tr class="border-b border-gray-800 hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($ct['id']); ?></td>
                            <td class="px-6 py-4 font-semibold text-white"><?php echo htmlspecialchars($ct['type_name']); ?></td>
                            <td class="px-6 py-4 text-gray-300"><?php echo htmlspecialchars($ct['description']); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditModal(<?php echo $ct['id']; ?>, '<?php echo htmlspecialchars(addslashes($ct['type_name'])); ?>', '<?php echo htmlspecialchars(addslashes($ct['description'])); ?>')" 
                                        class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded transition-all duration-300">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $ct['id']; ?>, '<?php echo htmlspecialchars(addslashes($ct['type_name'])); ?>')" 
                                        class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-all duration-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clientTypes)): ?>
                        <tr><td colspan="4" class="py-8 text-center text-gray-500">No client types found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Table Overlay Loader (Hidden by default) -->
            <div id="table-overlay-loader" class="hidden absolute inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 rounded-full border-t-transparent border-blue-500"></div>
                    <p class="text-white mt-2">Loading data...</p>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="bg-gray-900 px-6 py-3 border-t border-gray-800">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <div class="text-sm text-gray-400 mb-2 sm:mb-0">
                    Showing <span id="pagination-start">1</span> to <span id="pagination-end"><?php echo min($limit, $totalItems); ?></span> of <span id="pagination-total"><?php echo $totalItems; ?></span> entries
                </div>
                <div class="flex items-center space-x-1">
                    <button id="pagination-prev" class="px-3 py-1 rounded bg-gray-800 text-gray-300 hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div id="pagination-numbers" class="flex items-center space-x-1">
                        <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                            <button class="pagination-number px-3 py-1 rounded <?php echo $i === 1 ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>" data-page="<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <button id="pagination-next" class="px-3 py-1 rounded bg-gray-800 text-gray-300 hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $totalPages <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="clientTypeModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 rounded-xl shadow-xl p-8 w-full max-w-md relative border border-gray-700 transform transition-all duration-300">
        <button onclick="closeModal()" class="absolute top-3 right-3 text-gray-400 hover:text-white transition-colors duration-300">
            <i class="fas fa-times fa-lg"></i>
        </button>
        <h2 id="modalTitle" class="text-xl font-bold text-white mb-6">Add New Client Type</h2>
        <form id="clientTypeForm" class="space-y-6">
            <input type="hidden" name="sub_action" id="modalAction" value="add">
            <input type="hidden" name="id" id="modalId">
            <div>
                <label class="block text-gray-300 mb-1 font-semibold">Type Name <span class="text-red-500">*</span></label>
                <input type="text" name="type_name" id="modalTypeName" class="w-full px-4 py-3 rounded bg-gray-800 text-white border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold transition-all duration-300" required>
            </div>
            <div>
                <label class="block text-gray-300 mb-1 font-semibold">Description</label>
                <input type="text" name="description" id="modalDescription" class="w-full px-4 py-3 rounded bg-gray-800 text-white border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-5 py-2 rounded-md bg-gray-700 text-gray-300 hover:bg-gray-600 font-semibold transition-all duration-300 hover:shadow-md">Cancel</button>
                <button type="submit" id="form-submit-btn" class="px-6 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 font-semibold transition-all duration-300 hover:shadow-md">
                    <span>Save</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Global state
const state = {
    currentPage: 1,
    itemsPerPage: 10,
    search: '',
    totalPages: <?php echo $totalPages; ?>,
    totalItems: <?php echo $totalItems; ?>,
    debounceTimer: null
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set up event listeners
    document.getElementById('search-input').addEventListener('input', handleSearchInput);
    document.getElementById('items-per-page').addEventListener('change', handleItemsPerPageChange);
    document.getElementById('pagination-prev').addEventListener('click', handlePrevPage);
    document.getElementById('pagination-next').addEventListener('click', handleNextPage);
    
    // Set up pagination number clicks
    document.querySelectorAll('.pagination-number').forEach(btn => {
        btn.addEventListener('click', function() {
            goToPage(parseInt(this.dataset.page));
        });
    });
    
    // Position the table overlay loader correctly
    positionTableOverlayLoader();
    
    // Set initial state
    state.itemsPerPage = parseInt(document.getElementById('items-per-page').value);
});

// Make sure the overlay loader is positioned correctly
function positionTableOverlayLoader() {
    const tableContainer = document.getElementById('table-content');
    const overlayLoader = document.getElementById('table-overlay-loader');
    
    if (tableContainer && overlayLoader) {
        tableContainer.style.position = 'relative';
        overlayLoader.style.position = 'absolute';
    }
}

// Handle search input with debounce
function handleSearchInput(e) {
    clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(() => {
        state.search = e.target.value.trim();
        state.currentPage = 1; // Reset to first page on search
        loadData();
    }, 500); // 500ms debounce
}

// Handle items per page change
function handleItemsPerPageChange(e) {
    state.itemsPerPage = parseInt(e.target.value);
    state.currentPage = 1; // Reset to first page
    loadData();
}

// Handle previous page
function handlePrevPage() {
    if (state.currentPage > 1) {
        goToPage(state.currentPage - 1);
    }
}

// Handle next page
function handleNextPage() {
    if (state.currentPage < state.totalPages) {
        goToPage(state.currentPage + 1);
    }
}

// Go to specific page
function goToPage(page) {
    if (page >= 1 && page <= state.totalPages) {
        state.currentPage = page;
        loadData();
    }
}

// Form handling
document.getElementById('clientTypeForm').addEventListener('submit', handleFormSubmit);

async function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = document.getElementById('form-submit-btn');
    const originalBtnContent = submitBtn.innerHTML;
    
    // Disable button and show spinner
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="flex items-center"><div class="spinner-border animate-spin inline-block w-4 h-4 border-2 rounded-full border-t-transparent mr-2"></div><span>Saving...</span></div>';
    
    // Show global loader
    GaurdUI.showLoader('Saving changes...');
    
    try {
        const response = await fetch('index.php?action=manage_client_type', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            closeModal();
            GaurdUI.showToast(result.message, 'success');
            loadData();
        } else {
            GaurdUI.showToast(result.message || 'An error occurred.', 'error');
        }
    } catch (error) {
        GaurdUI.showToast('A network error occurred. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnContent;
        GaurdUI.hideLoader();
    }
}

// Delete client type with confirmation
function confirmDelete(id, typeName) {
    GaurdUI.confirm(
        'Confirm Deletion',
        `Are you sure you want to delete <span class="font-semibold text-white">${typeName}</span>? This action cannot be undone.`,
        () => deleteClientType(id),
        null,
        { confirmText: 'Delete', danger: true }
    );
}

async function deleteClientType(id) {
    GaurdUI.showLoader('Deleting...');
    
    try {
        const response = await fetch('index.php?action=manage_client_type', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sub_action: 'delete', id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            GaurdUI.showToast(result.message, 'success');
            loadData();
        } else {
            GaurdUI.showToast(result.message || 'An error occurred.', 'error');
        }
    } catch (error) {
        GaurdUI.showToast('A network error occurred.', 'error');
    } finally {
        GaurdUI.hideLoader();
    }
}

// Load data with pagination and search
async function loadData() {
    // Show overlay loader instead of shimmer
    showTableOverlayLoader();
    
    try {
        const response = await fetch('index.php?action=get_client_types', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                page: state.currentPage,
                limit: state.itemsPerPage,
                search: state.search
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update state with pagination info
            state.totalPages = result.pagination.totalPages;
            state.totalItems = result.pagination.totalItems;
            
            // Update data
            updateTable(result.data);
            updateStats(result.stats);
            updatePagination(result.pagination);
        } else {
            GaurdUI.showToast('Failed to load data.', 'error');
        }
    } catch (error) {
        GaurdUI.showToast('A network error occurred while loading data.', 'error');
    } finally {
        hideTableOverlayLoader();
    }
}

// Show table overlay loader
function showTableOverlayLoader() {
    const overlayLoader = document.getElementById('table-overlay-loader');
    if (overlayLoader) {
        overlayLoader.classList.remove('hidden');
    }
}

// Hide table overlay loader
function hideTableOverlayLoader() {
    const overlayLoader = document.getElementById('table-overlay-loader');
    if (overlayLoader) {
        overlayLoader.classList.add('hidden');
    }
}

// Update the table with new data
function updateTable(clientTypes) {
    const tbody = document.getElementById('client-types-tbody');
    tbody.innerHTML = '';
    
    if (clientTypes.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="4" class="py-8 text-center text-gray-500">No client types found.</td>';
        tbody.appendChild(tr);
        return;
    }
    
    clientTypes.forEach(ct => {
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-800 hover:bg-gray-800 transition-all duration-200';
        
        tr.innerHTML = `
            <td class="px-6 py-4 font-medium">${ct.id}</td>
            <td class="px-6 py-4 font-semibold text-white">${escapeHtml(ct.type_name)}</td>
            <td class="px-6 py-4 text-gray-300">${escapeHtml(ct.description)}</td>
            <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                    <button onclick="openEditModal(${ct.id}, '${escapeHtml(ct.type_name.replace(/'/g, "\\'"))}', '${escapeHtml(ct.description.replace(/'/g, "\\'"))}')" 
                        class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded transition-all duration-300">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="confirmDelete(${ct.id}, '${escapeHtml(ct.type_name.replace(/'/g, "\\'"))}')" 
                        class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-all duration-300">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(tr);
    });
}

// Update statistics
function updateStats(stats) {
    if (stats) {
        document.getElementById('stat-total-types').textContent = stats.totalTypes;
        document.getElementById('stat-avg-clients').textContent = stats.avgClientsPerType;
        document.getElementById('stat-total-clients').textContent = stats.totalClients;
    }
}

// Update pagination UI
function updatePagination(pagination) {
    // Update pagination text
    document.getElementById('pagination-start').textContent = ((pagination.currentPage - 1) * pagination.itemsPerPage) + 1;
    document.getElementById('pagination-end').textContent = Math.min(pagination.currentPage * pagination.itemsPerPage, pagination.totalItems);
    document.getElementById('pagination-total').textContent = pagination.totalItems;
    
    // Update prev/next buttons
    document.getElementById('pagination-prev').disabled = !pagination.hasPrevPage;
    document.getElementById('pagination-next').disabled = !pagination.hasNextPage;
    
    // Generate pagination numbers
    const paginationNumbers = document.getElementById('pagination-numbers');
    paginationNumbers.innerHTML = '';
    
    // Calculate range of page numbers to show
    let startPage = Math.max(1, pagination.currentPage - 2);
    let endPage = Math.min(pagination.totalPages, startPage + 4);
    
    // Adjust if we're near the end
    if (endPage - startPage < 4 && startPage > 1) {
        startPage = Math.max(1, endPage - 4);
    }
    
    // Add first page button if not in range
    if (startPage > 1) {
        const firstBtn = createPaginationButton(1, pagination.currentPage === 1);
        paginationNumbers.appendChild(firstBtn);
        
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 text-gray-400';
            ellipsis.textContent = '...';
            paginationNumbers.appendChild(ellipsis);
        }
    }
    
    // Add page number buttons
    for (let i = startPage; i <= endPage; i++) {
        const btn = createPaginationButton(i, pagination.currentPage === i);
        paginationNumbers.appendChild(btn);
    }
    
    // Add last page button if not in range
    if (endPage < pagination.totalPages) {
        if (endPage < pagination.totalPages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 text-gray-400';
            ellipsis.textContent = '...';
            paginationNumbers.appendChild(ellipsis);
        }
        
        const lastBtn = createPaginationButton(pagination.totalPages, pagination.currentPage === pagination.totalPages);
        paginationNumbers.appendChild(lastBtn);
    }
}

// Create pagination button
function createPaginationButton(page, isActive) {
    const btn = document.createElement('button');
    btn.className = `pagination-number px-3 py-1 rounded ${isActive ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'}`;
    btn.textContent = page;
    btn.dataset.page = page;
    btn.addEventListener('click', () => goToPage(page));
    return btn;
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Client Type';
    document.getElementById('clientTypeForm').reset();
    document.getElementById('modalAction').value = 'add';
    document.getElementById('clientTypeModal').classList.remove('hidden');
    document.getElementById('modalTypeName').focus();
}

function openEditModal(id, typeName, description) {
    document.getElementById('modalTitle').textContent = 'Edit Client Type';
    document.getElementById('clientTypeForm').reset();
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = id;
    document.getElementById('modalTypeName').value = typeName;
    document.getElementById('modalDescription').value = description;
    document.getElementById('clientTypeModal').classList.remove('hidden');
    document.getElementById('modalTypeName').focus();
}

function closeModal() {
    document.getElementById('clientTypeModal').classList.add('hidden');
}

// Add keyboard event listeners for modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (!document.getElementById('clientTypeModal').classList.contains('hidden')) {
            closeModal();
        }
    }
});

// Add animation to modal
document.getElementById('clientTypeModal').addEventListener('transitionend', function(e) {
    if (this.classList.contains('hidden')) {
        document.getElementById('clientTypeForm').reset();
    }
});
</script> 