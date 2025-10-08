<?php
// UI/hr/salary_management/deduction_master_view.php
global $page, $company_settings;
require_once __DIR__ . '/../../../helpers/database.php';

// Initialize database connection
$db = new Database();

// Pagination and search parameters
$page_num = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$per_page = in_array($per_page, [5, 10, 25, 50, 100]) ? $per_page : 10; // Validate per_page values
$offset = ($page_num - 1) * $per_page;

// Build the query with search and filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(dm.deduction_name LIKE ? OR dm.deduction_code LIKE ? OR dm.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "dm.is_active = ?";
    $params[] = ($status_filter === 'active') ? 1 : 0;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM deduction_master dm
    LEFT JOIN users u ON dm.created_by = u.id
    {$where_clause}
";
$totalResult = $db->query($countQuery, $params)->fetch();
$total_records = $totalResult['total'];
$total_pages = ceil($total_records / $per_page);

// Get deduction types with pagination
$deductionTypesQuery = "
    SELECT 
        dm.*,
        u.first_name,
        u.surname
    FROM deduction_master dm
    LEFT JOIN users u ON dm.created_by = u.id
    {$where_clause}
    ORDER BY dm.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$deductionTypes = $db->query($deductionTypesQuery, $params)->fetchAll();

// Get stats for all records (not just current page)
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM deduction_master
";
$stats = $db->query($statsQuery)->fetch();
?>

<div class="bg-gray-800 rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white mb-4 md:mb-0">Deduction Master</h1>
        <button id="add-deduction-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
            <i class="fas fa-plus"></i> Add New Deduction
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Total Deductions</span>
            <span class="text-3xl font-bold text-white"><?php echo $stats['total']; ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Active Deductions</span>
            <span class="text-3xl font-bold text-white"><?php echo $stats['active']; ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Inactive Deductions</span>
            <span class="text-3xl font-bold text-white"><?php echo $stats['inactive']; ?></span>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="bg-gray-900 rounded-lg p-4 mb-6">
        <form id="search-form" method="GET" class="flex flex-col md:flex-row gap-4">
            <input type="hidden" name="page" value="deduction-master">
            
            <!-- Search Input -->
            <div class="flex-1">
                <label for="search-input" class="block text-sm font-medium text-gray-300 mb-2">Search</label>
                <div class="relative">
                    <input type="text" 
                           id="search-input" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by name, code, or description..."
                           class="w-full bg-gray-700 text-white px-4 py-2 pl-10 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </div>
            
            <!-- Status Filter -->
            <div class="md:w-48">
                <label for="status-filter" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select id="status-filter" 
                        name="status" 
                        class="w-full bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                </select>
            </div>
            
            <!-- Per Page Selector -->
            <div class="md:w-32">
                <label for="per-page-select" class="block text-sm font-medium text-gray-300 mb-2">Per Page</label>
                <select id="per-page-select" 
                        name="per_page" 
                        class="w-full bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="5" <?php echo $per_page === 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            
            <!-- Search Button -->
            <div class="flex items-end">
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
            
            <!-- Clear Button -->
            <?php if (!empty($search) || $status_filter !== 'all'): ?>
            <div class="flex items-end">
                <a href="index.php?page=deduction-master" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Deduction Types Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-gray-900 rounded-lg overflow-hidden">
            <thead class="bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Deduction Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Created By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Created At</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                <?php if (empty($deductionTypes)) : ?>
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-gray-400">
                            <?php if (!empty($search) || $status_filter !== 'all'): ?>
                                No deduction types found matching your search criteria.
                                <br>
                                <a href="index.php?page=deduction-master" class="text-blue-400 hover:text-blue-300 underline mt-2 inline-block">
                                    Clear filters to see all deductions
                                </a>
                            <?php else: ?>
                                No deduction types found. Add your first deduction type.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($deductionTypes as $deduction) : ?>
                        <tr class="hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-white">
                                    <?php echo htmlspecialchars($deduction['deduction_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php echo htmlspecialchars($deduction['deduction_code']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300 max-w-xs truncate">
                                    <?php echo htmlspecialchars($deduction['description'] ?: 'No description'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $deduction['is_active'] ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'; ?>">
                                    <?php echo $deduction['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php echo htmlspecialchars(($deduction['first_name'] ?? '') . ' ' . ($deduction['surname'] ?? '')); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php echo date('M d, Y', strtotime($deduction['created_at'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick="editDeduction(<?php echo $deduction['id']; ?>)" 
                                            class="text-blue-400 hover:text-blue-300 px-2 py-1" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleDeductionStatus(<?php echo $deduction['id']; ?>, <?php echo $deduction['is_active'] ? 'false' : 'true'; ?>)" 
                                            class="text-yellow-400 hover:text-yellow-300 px-2 py-1" 
                                            title="<?php echo $deduction['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $deduction['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                    <button onclick="deleteDeduction(<?php echo $deduction['id']; ?>)" 
                                            class="text-red-400 hover:text-red-300 px-2 py-1" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center">
        <!-- Results Info -->
        <div class="text-sm text-gray-400 mb-4 sm:mb-0">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
        </div>
        
        <!-- Pagination Buttons -->
        <div class="flex items-center space-x-2">
            <!-- Previous Button -->
            <?php if ($page_num > 1): ?>
                <a href="?page=deduction-master&page_num=<?php echo $page_num - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&per_page=<?php echo $per_page; ?>" 
                   class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="px-3 py-2 bg-gray-800 text-gray-500 rounded-lg cursor-not-allowed">
                    <i class="fas fa-chevron-left"></i>
                </span>
            <?php endif; ?>
            
            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $page_num - 2);
            $end_page = min($total_pages, $page_num + 2);
            
            if ($start_page > 1): ?>
                <a href="?page=deduction-master&page_num=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&per_page=<?php echo $per_page; ?>" 
                   class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">1</a>
                <?php if ($start_page > 2): ?>
                    <span class="px-2 text-gray-400">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page_num): ?>
                    <span class="px-3 py-2 bg-blue-600 text-white rounded-lg font-semibold"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=deduction-master&page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&per_page=<?php echo $per_page; ?>" 
                       class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-2 text-gray-400">...</span>
                <?php endif; ?>
                <a href="?page=deduction-master&page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&per_page=<?php echo $per_page; ?>" 
                   class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors"><?php echo $total_pages; ?></a>
            <?php endif; ?>
            
            <!-- Next Button -->
            <?php if ($page_num < $total_pages): ?>
                <a href="?page=deduction-master&page_num=<?php echo $page_num + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&per_page=<?php echo $per_page; ?>" 
                   class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="px-3 py-2 bg-gray-800 text-gray-500 rounded-lg cursor-not-allowed">
                    <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Deduction Modal -->
<div id="deduction-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modal-title" class="text-xl font-bold text-white">Add New Deduction</h2>
                <button onclick="closeDeductionModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="deduction-form">
                <input type="hidden" id="deduction-id">
                
                <div class="mb-4">
                    <label for="deduction-name" class="block text-sm font-medium text-gray-300 mb-2">Deduction Name *</label>
                    <input type="text" id="deduction-name" required
                           class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., VCS, Uniform, Shoes">
                </div>
                
                <div class="mb-4">
                    <label for="deduction-code" class="block text-sm font-medium text-gray-300 mb-2">Deduction Code *</label>
                    <input type="text" id="deduction-code" required
                           class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., VCS, UNIF, SHOES">
                </div>
                
                <div class="mb-4">
                    <label for="deduction-description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                    <textarea id="deduction-description" rows="3"
                              class="bg-gray-700 text-white w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Optional description..."></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="deduction-active" checked
                               class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-300">Active</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeductionModal()" 
                            class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Message Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('add-deduction-btn');
    const modal = document.getElementById('deduction-modal');
    const form = document.getElementById('deduction-form');
    const modalTitle = document.getElementById('modal-title');
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');
    const perPageSelect = document.getElementById('per-page-select');
    
    // Auto-submit search form on status filter change
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Auto-submit search form on per-page change
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Debounced search functionality
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                // Only auto-search if there are at least 2 characters or empty
                if (searchInput.value.length >= 2 || searchInput.value.length === 0) {
                    searchForm.submit();
                }
            }, 500);
        });
    }
    
    // Add loading state to search form
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const submitBtn = searchForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Searching...';
            }
        });
    }
    
    // Add new deduction
    addBtn.addEventListener('click', function() {
        modalTitle.textContent = 'Add New Deduction';
        form.reset();
        document.getElementById('deduction-id').value = '';
        document.getElementById('deduction-active').checked = true;
        modal.classList.remove('hidden');
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const id = document.getElementById('deduction-id').value;
        const data = {
            deduction_name: document.getElementById('deduction-name').value.trim(),
            deduction_code: document.getElementById('deduction-code').value.trim().toUpperCase(),
            description: document.getElementById('deduction-description').value.trim(),
            is_active: document.getElementById('deduction-active').checked
        };
        
        if (!data.deduction_name || !data.deduction_code) {
            showToast('Please fill in all required fields', 'error');
            return;
        }
        
        const url = id ? 'actions/deduction_master_controller.php?action=update' : 'actions/deduction_master_controller.php?action=create';
        if (id) data.id = parseInt(id);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                closeDeductionModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(result.message || 'Operation failed', 'error');
            }
        })
        .catch(error => {
            showToast('An error occurred', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save';
        });
    });
    
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
    
    // Global functions
    window.closeDeductionModal = function() {
        modal.classList.add('hidden');
        form.reset();
    };
    
    window.editDeduction = function(id) {
        fetch(`actions/deduction_master_controller.php?action=get_all`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const deduction = result.data.find(d => d.id == id);
                    if (deduction) {
                        modalTitle.textContent = 'Edit Deduction';
                        document.getElementById('deduction-id').value = deduction.id;
                        document.getElementById('deduction-name').value = deduction.deduction_name;
                        document.getElementById('deduction-code').value = deduction.deduction_code;
                        document.getElementById('deduction-description').value = deduction.description || '';
                        document.getElementById('deduction-active').checked = deduction.is_active;
                        modal.classList.remove('hidden');
                    }
                } else {
                    showToast('Failed to load deduction details', 'error');
                }
            })
            .catch(error => {
                showToast('Error loading deduction details', 'error');
                console.error('Error:', error);
            });
    };
    
    window.toggleDeductionStatus = function(id, newStatus) {
        // Convert string to boolean if needed
        const isActive = newStatus === true || newStatus === 'true' || newStatus === 1;
        const action = isActive ? 'activate' : 'deactivate';
        if (confirm(`Are you sure you want to ${action} this deduction?`)) {
            fetch('actions/deduction_master_controller.php?action=toggle_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id), is_active: isActive })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Operation failed', 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred', 'error');
                console.error('Error:', error);
            });
        }
    };
    
    window.deleteDeduction = function(id) {
        if (confirm('Are you sure you want to delete this deduction? This action cannot be undone.')) {
            fetch('actions/deduction_master_controller.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id) })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Operation failed', 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred', 'error');
                console.error('Error:', error);
            });
        }
    };
});
</script>
