<?php
$user_schema = include 'schema/users.php';
$user_type_enums = [];
if (preg_match("/ENUM\((.*?)\)/", $user_schema['users']['columns']['user_type'], $matches)) {
    $user_type_enums = array_map(fn($item) => trim($item, " '"), explode(',', $matches[1]));
}

// Debug flag to control the debug panel visibility.
// 'always': Always show the panel, ignoring user preference.
// 'never': Always hide the panel, ignoring user preference.
// 'user': Let the user control it (default state is hidden, saved in localStorage).
$debug_mode = 'never'; // Options: 'always', 'never', 'user'

$show_debug = ($debug_mode === 'always');
$is_user_controlled = ($debug_mode === 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List</title>
    
    
    <!-- Centralized CSS -->
    <link rel="stylesheet" href="UI/assets/css/main.css">
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto">
        <h1 class="text-3xl font-bold mb-6">Employee List</h1>

        <!-- Search and Filter Controls -->
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-grow">
                <input type="text" id="search-input" placeholder="Search by name, email, or ID..." 
                    class="w-full bg-gray-700 text-white p-3 pl-10 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            <div class="flex-shrink-0">
                <select id="role-filter" class="w-full md:w-auto bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">All Roles</option>
                    <?php foreach ($user_type_enums as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-shrink-0">
                <input type="text" id="pin-filter" placeholder="Filter by PIN" 
                    class="w-full md:w-auto bg-gray-700 text-white p-3 rounded-lg border border-gray-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>

        <!-- Debug Controls -->
        <?php if ($debug_mode !== 'never'): ?>
        <div class="mb-4 flex justify-between items-center">
            <?php if ($is_user_controlled): ?>
            <div class="flex items-center">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="debug-toggle" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-300">Show Debug Panel</span>
                </label>
                <div class="ml-4 text-xs text-gray-500">(Keyboard shortcut: Ctrl+D)</div>
            </div>
            <?php else: ?>
            <div class="text-sm text-gray-500">
                Debug panel is <?php echo $debug_mode === 'always' ? 'always shown' : 'hidden'; ?> by configuration.
            </div>
            <?php endif; ?>
            <button id="clear-debug" class="px-3 py-1 bg-gray-700 text-gray-300 text-xs rounded hover:bg-gray-600">Clear Debug Log</button>
        </div>
        <?php endif; ?>

        <!-- Debug Panel -->
        <div id="debug-panel" class="mb-4 p-4 bg-gray-900 border border-gray-700 rounded-lg <?php if ($debug_mode !== 'always') echo 'hidden'; ?>">
            <h3 class="text-sm font-semibold text-gray-400 mb-2">Debug Information</h3>
            <div id="debug-content" class="text-xs text-gray-500 max-h-60 overflow-y-auto"></div>
        </div>

        <!-- Alert Message Container (for success/error messages) -->
        <div id="alert-container" class="mb-4 hidden">
            <!-- Alert messages will be inserted here -->
        </div>
        
        <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Mobile</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="employee-table-body" class="bg-gray-800 divide-y divide-gray-700">
                    <!-- Table rows will be inserted here -->
                    <tr>
                        <td colspan="7" class="text-center p-8 text-gray-400">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading employees...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div id="pagination-controls" class="flex justify-between items-center mt-6 text-white">
            <!-- Pagination will be rendered here -->
        </div>
    </div>

    <!-- Activation Modal -->
    <div id="activation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4 text-white">Activate User Access</h3>
            <p class="text-gray-400 mb-6">Select the access types to enable for this user.</p>
            <form id="activation-form">
                <input type="hidden" id="modal-user-id">
                <div class="space-y-4">
                    <label class="flex items-center p-3 rounded-lg bg-gray-700 hover:bg-gray-600 cursor-pointer">
                        <input type="checkbox" id="modal-web-access" class="h-5 w-5 rounded bg-gray-900 border-gray-500 text-blue-500 focus:ring-blue-500">
                        <span class="ml-3 text-white">Web Access</span>
                    </label>
                    <label class="flex items-center p-3 rounded-lg bg-gray-700 hover:bg-gray-600 cursor-pointer">
                        <input type="checkbox" id="modal-mobile-access" class="h-5 w-5 rounded bg-gray-900 border-gray-500 text-blue-500 focus:ring-blue-500">
                        <span class="ml-3 text-white">Mobile Access</span>
                    </label>
                </div>
                <div class="mt-8 flex justify-end gap-4">
                    <button type="button" id="modal-cancel-btn" class="px-4 py-2 rounded bg-gray-600 hover:bg-gray-500 text-white">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-500 text-white">Activate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Define global variables for functions
        let fetchEmployees;
        let refreshToken;
        
        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('employee-table-body');
            const searchInput = document.getElementById('search-input');
            const roleFilter = document.getElementById('role-filter');
            const pinFilter = document.getElementById('pin-filter');
            const paginationControls = document.getElementById('pagination-controls');
            const alertContainer = document.getElementById('alert-container');
            const debugPanel = document.getElementById('debug-panel');
            const debugContent = document.getElementById('debug-content');
            const debugToggle = document.getElementById('debug-toggle');
            const clearDebugBtn = document.getElementById('clear-debug');
            const activationModal = document.getElementById('activation-modal');
            const activationForm = document.getElementById('activation-form');
            const modalCancelBtn = document.getElementById('modal-cancel-btn');
            const isUserControlled = <?php echo json_encode($is_user_controlled); ?>;
            const debugMode = <?php echo json_encode($debug_mode); ?>;
            let searchTimeout;
            let pinTimeout;

            // Debug function to help troubleshoot issues
            function debug(message, data = null) {
                const timestamp = new Date().toLocaleTimeString();
                let debugMsg = `<div class="border-b border-gray-800 py-1">[${timestamp}] ${message}</div>`;
                
                if (data) {
                    debugMsg += `<div class="pl-4 my-1 pb-2 font-mono">${JSON.stringify(data)}</div>`;
                }
                
                debugContent.innerHTML += debugMsg;
                debugContent.scrollTop = debugContent.scrollHeight; // Auto-scroll to bottom
                
                // Log to console as well
                console.log(`[${timestamp}] ${message}`, data || '');
            }
            
            if (debugMode !== 'never') {
                // Clear debug log
                clearDebugBtn.addEventListener('click', () => {
                    debugContent.innerHTML = '<div class="text-blue-400 pb-2">Debug log cleared.</div>';
                });
            }

            // Toggle debug panel visibility with checkbox
            if (isUserControlled) {
                debugToggle.addEventListener('change', () => {
                    debugPanel.classList.toggle('hidden', !debugToggle.checked);
                    
                    // Save preference to localStorage
                    localStorage.setItem('employeeListDebug', debugToggle.checked ? 'true' : 'false');
                });
                
                // Load debug preference from localStorage
                const savedDebugPref = localStorage.getItem('employeeListDebug');
                if (savedDebugPref !== null) {
                    const showDebug = savedDebugPref === 'true';
                    debugToggle.checked = showDebug;
                    debugPanel.classList.toggle('hidden', !showDebug);
                }
                
                // Toggle debug panel visibility with keyboard shortcut (Ctrl+D)
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'd') {
                        e.preventDefault();
                        debugToggle.checked = !debugToggle.checked;
                        debugPanel.classList.toggle('hidden', !debugToggle.checked);
                        localStorage.setItem('employeeListDebug', debugToggle.checked ? 'true' : 'false');
                    }
                });
            } else {
                 // When not user-controlled, ensure panel visibility matches config
                 debugPanel.classList.toggle('hidden', debugMode !== 'always');
            }

            // Show alert message
            function showAlert(message, type = 'success') {
                alertContainer.innerHTML = `
                    <div class="p-4 rounded-lg ${type === 'success' ? 'bg-green-700' : 'bg-red-700'} flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-3"></i>
                            <span>${message}</span>
                        </div>
                        <button type="button" class="text-gray-300 hover:text-white" onclick="this.parentElement.parentElement.classList.add('hidden')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                alertContainer.classList.remove('hidden');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alertContainer.classList.add('hidden');
                }, 5000);
            }

            // Function to refresh the JWT token
            refreshToken = async function() {
                try {
                    debug('Attempting to refresh token');
                    
                    const response = await fetch('index.php?action=refresh_token', {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        }
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        debug('Token refresh successful', result);
                        return true;
                    } else {
                        debug('Token refresh failed', { status: response.status });
                        return false;
                    }
                } catch (error) {
                    console.error('Error refreshing token:', error);
                    debug('Error refreshing token', { error: error.message });
                    return false;
                }
            };

            // Main function to fetch employees with search, filtering and pagination
            fetchEmployees = async function(search = '', role = '', pin = '', page = 1) {
                // Show loading indicator
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center p-8 text-gray-400">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Loading employees...
                            </div>
                        </td>
                    </tr>
                `;
                
                try {
                    // Try to refresh the token first
                    await refreshToken();
                    
                    // Construct URL with query parameters for GET request
                    const params = new URLSearchParams();
                    params.append('action', 'get_users');
                    
                    if (search) params.append('search', search);
                    if (role) params.append('role', role);
                    if (pin) params.append('pin', pin);
                    if (page) params.append('page', page);
                    
                    const url = `index.php?${params.toString()}`;
                    debug('Fetching employees', { url, params: { search, role, pin, page } });
                    
                    // Make the fetch request
                    const response = await fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        }
                    });
                    
                    debug('Fetch response', { 
                        status: response.status, 
                        statusText: response.statusText,
                        headers: Object.fromEntries([...response.headers])
                    });
                    
                    // Handle non-OK responses
                    if (!response.ok) {
                        let errorMessage = 'An unknown error occurred';
                        let errorData = null;
                        
                        try {
                            errorData = await response.json();
                            errorMessage = errorData.error || `Error: ${response.status}`;
                            debug('Error response data', errorData);
                        } catch (e) {
                            debug('Failed to parse error response', { error: e.message });
                        }
                        
                        // Special handling for 401 Unauthorized
                        if (response.status === 401) {
                            debug('Authentication error, attempting to recover');
                            
                            // Try refreshing token and retry once more
                            const refreshed = await refreshToken();
                            debug('Token refresh attempt for retry', { success: refreshed });
                            
                            if (refreshed) {
                                // If token refresh succeeded, retry the fetch
                                debug('Retrying fetch after token refresh');
                                
                                const retryResponse = await fetch(url, {
                                    method: 'GET',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Cache-Control': 'no-cache',
                                        'Pragma': 'no-cache'
                                    }
                                });
                                
                                debug('Retry response', { 
                                    status: retryResponse.status, 
                                    statusText: retryResponse.statusText
                                });
                                
                                if (retryResponse.ok) {
                                    const retryData = await retryResponse.json();
                                    debug('Retry successful', { records: retryData.users?.length || 0 });
                                    
                                    if (retryData.success && retryData.users) {
                                        renderEmployeeTable(retryData.users);
                                        renderPaginationControls(retryData.pagination);
                                        return;
                                    }
                                } else {
                                    debug('Retry failed', { status: retryResponse.status });
                                }
                            }
                            
                            // If retry failed, show authentication error
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="7" class="text-center p-8 text-red-300">
                                        <div class="flex flex-col items-center gap-3">
                                            <div><i class="fas fa-exclamation-triangle text-3xl text-red-400"></i></div>
                                            <div>Session expired. Please login again or refresh the page.</div>
                                            <div class="flex gap-3 mt-2">
                                                <a href="index.php?page=login" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                                    Login
                                                </a>
                                                <button class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700" onclick="location.reload()">
                                                    Refresh Page
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            return;
                        }
                        
                        // Handle other errors
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center p-8 text-red-300">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-exclamation-circle text-2xl"></i>
                                        <span>${errorMessage}</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    // Process successful response
                    const result = await response.json();
                    debug('Fetch successful', { 
                        success: result.success, 
                        count: result.users?.length || 0,
                        pagination: result.pagination
                    });

                    if (result.success && result.users) {
                        renderEmployeeTable(result.users);
                        renderPaginationControls(result.pagination);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center p-8 text-yellow-300">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                                        <span>${result.error || 'Could not fetch employees'}</span>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                } catch (error) {
                    console.error('Error fetching employees:', error);
                    debug('Exception during fetch', { error: error.message, stack: error.stack });
                    
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center p-8 text-red-300">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-times-circle text-2xl"></i>
                                    <span>Error: ${error.message || 'Failed to load employees'}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            };

            // Function to render the employee table
            function renderEmployeeTable(employees) {
                tableBody.innerHTML = ''; // Clear the table
                
                if (employees.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center p-8 text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-search text-2xl"></i>
                                    <span>No employees found matching your criteria.</span>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                // Create a row for each employee
                employees.forEach(employee => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-gray-700 transition-colors';
                    
                    // Format the date
                    const joinedDate = new Date(employee.created_at).toLocaleDateString();
                    
                    const avatar = employee.profile_photo 
                        ? `<img class="h-10 w-10 rounded-full object-cover" src="${employee.profile_photo}" alt="Profile photo">`
                        : `<div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-gray-400">
                               <i class="fas fa-user"></i>
                           </div>`;

                    const isActive = parseInt(employee.web_access) || parseInt(employee.mobile_access);

                    const statusBadge = isActive 
                        ? `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-800 text-green-100">Active</span>`
                        : `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-800 text-red-100">Inactive</span>`;

                    const statusToggleButton = isActive
                        ? `<button class="status-toggle-btn text-yellow-400 hover:text-yellow-300 mx-2" title="Deactivate"><i class="fas fa-power-off"></i></button>`
                        : `<button class="status-toggle-btn text-green-400 hover:text-green-300 mx-2" title="Activate"><i class="fas fa-power-off"></i></button>`;

                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-100">${employee.id}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    ${avatar}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-white">${employee.first_name} ${employee.surname}</div>
                                    <div class="text-sm text-gray-400 truncate max-w-xs" title="${employee.email_id}">${employee.email_id}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            ${employee.mobile_number || 'N/A'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-800 text-blue-100">
                                ${employee.user_type}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            ${statusBadge}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium" 
                            data-user-id="${employee.id}" 
                            data-is-active="${isActive ? 1 : 0}">
                            ${statusToggleButton}
                            <a href="index.php?page=view-employee&id=${employee.id}" class="text-blue-400 hover:text-blue-300 mx-2" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="index.php?page=edit-employee&id=${employee.id}" class="text-indigo-400 hover:text-indigo-300 mx-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="delete-btn text-red-400 hover:text-red-300 mx-2" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            }

            // Function to render pagination controls
            function renderPaginationControls(pagination) {
                if (!pagination || pagination.total_pages <= 1) {
                    paginationControls.innerHTML = '';
                    return;
                }

                const { total_pages, current_page } = pagination;
                const search = searchInput.value;
                const role = roleFilter.value;
                const pin = pinFilter.value;
                
                let html = '';
                
                // Previous button
                html += `
                    <button class="px-4 py-2 bg-gray-700 rounded-lg hover:bg-blue-600 disabled:bg-gray-800 disabled:text-gray-600 disabled:cursor-not-allowed transition-colors" 
                            ${current_page === 1 ? 'disabled' : ''} 
                            onclick="fetchEmployees('${search}', '${role}', '${pin}', ${current_page - 1})">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </button>
                `;
                
                // Page indicator
                html += `
                    <div class="flex items-center space-x-2">
                        <span class="px-4 py-2 bg-gray-800 rounded-lg">Page ${current_page} of ${total_pages}</span>
                    </div>
                `;
                
                // Next button
                html += `
                    <button class="px-4 py-2 bg-gray-700 rounded-lg hover:bg-blue-600 disabled:bg-gray-800 disabled:text-gray-600 disabled:cursor-not-allowed transition-colors" 
                            ${current_page === total_pages ? 'disabled' : ''} 
                            onclick="fetchEmployees('${search}', '${role}', '${pin}', ${current_page + 1})">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                `;
                
                paginationControls.innerHTML = html;
            }
            
            // Set up event listeners for search and filter
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    debug('Search initiated', { 
                        term: searchInput.value, 
                        role: roleFilter.value,
                        pin: pinFilter.value
                    });
                    fetchEmployees(searchInput.value, roleFilter.value, pinFilter.value, 1);
                }, 300); // Debounce the search input
            });

            roleFilter.addEventListener('change', () => {
                debug('Filter changed', { 
                    role: roleFilter.value, 
                    term: searchInput.value,
                    pin: pinFilter.value
                });
                fetchEmployees(searchInput.value, roleFilter.value, pinFilter.value, 1);
            });
            
            pinFilter.addEventListener('input', () => {
                clearTimeout(pinTimeout);
                pinTimeout = setTimeout(() => {
                    debug('PIN filter applied', { 
                        pin: pinFilter.value,
                        term: searchInput.value, 
                        role: roleFilter.value
                    });
                    fetchEmployees(searchInput.value, roleFilter.value, pinFilter.value, 1);
                }, 300); // Debounce the PIN input
            });
            
            // Event delegation for delete buttons
            tableBody.addEventListener('click', (event) => {
                const target = event.target;
                const row = target.closest('tr');

                // Delete button
                const deleteButton = target.closest('.delete-btn');
                if (deleteButton) {
                    const userId = deleteButton.closest('td').dataset.userId;
                    if (userId) handleDeleteUser(userId, row);
                    return;
                }

                // Status toggle button
                const statusButton = target.closest('.status-toggle-btn');
                if (statusButton) {
                    const cell = statusButton.closest('td');
                    const userId = cell.dataset.userId;
                    const isActive = cell.dataset.isActive == '1';
                    handleStatusUpdate(userId, isActive);
                    return;
                }
            });
            
            // Load employees when page loads
            debug('Page loaded, fetching initial employee list');
            fetchEmployees();
            
            // Function to handle user status update
            async function handleStatusUpdate(userId, isActive) {
                if (isActive) {
                    // Deactivate
                    if (confirm('Are you sure you want to deactivate this user? Their Web and Mobile access will be revoked.')) {
                        await updateUserStatus(userId, false, false);
                    }
                } else {
                    // Activate: show modal
                    document.getElementById('modal-user-id').value = userId;
                    document.getElementById('modal-web-access').checked = true; // Default to web access
                    document.getElementById('modal-mobile-access').checked = false;
                    activationModal.classList.remove('hidden');
                }
            }
            
            // Function to call the API to update status
            async function updateUserStatus(userId, webAccess, mobileAccess) {
                try {
                    debug('Updating user status', { userId, webAccess, mobileAccess });
                    await refreshToken(); // Ensure token is fresh
                    
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Cache-Control': 'no-cache'
                        },
                        body: JSON.stringify({ 
                            action: 'update_user_status', 
                            id: userId,
                            web_access: webAccess,
                            mobile_access: mobileAccess
                        }),
                        credentials: 'same-origin'
                    });

                    const result = await response.json();
                    debug('Update status response', result);

                    if (result.success) {
                        showAlert('User status updated successfully.', 'success');
                        // Find current page to refetch
                        const currentPage = document.querySelector('#pagination-controls span')?.textContent.match(/Page (\d+)/)?.[1] || 1;
                        fetchEmployees(searchInput.value, roleFilter.value, pinFilter.value, currentPage);
                    } else {
                        throw new Error(result.error || 'Failed to update status.');
                    }
                } catch (error) {
                    console.error('Error updating status:', error);
                    debug('Update status error', { message: error.message });
                    showAlert(error.message, 'error');
                } finally {
                    // Hide modal if it was open
                    activationModal.classList.add('hidden');
                }
            }

            // --- Modal Event Listeners ---
            modalCancelBtn.addEventListener('click', () => {
                activationModal.classList.add('hidden');
            });

            activationForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const userId = document.getElementById('modal-user-id').value;
                const webAccess = document.getElementById('modal-web-access').checked;
                const mobileAccess = document.getElementById('modal-mobile-access').checked;
                
                if (!webAccess && !mobileAccess) {
                    showAlert('You must select at least one access type to activate the user.', 'error');
                    return;
                }
                
                updateUserStatus(userId, webAccess, mobileAccess);
            });

            // Function to handle user deletion
            async function handleDeleteUser(id, row) {
                if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                    return;
                }
                
                try {
                    debug('Deleting user', { id });
                    
                    // First refresh the token
                    await refreshToken();
                    
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Cache-Control': 'no-cache'
                        },
                        body: JSON.stringify({ action: 'delete_user', id: id }),
                        credentials: 'same-origin'
                    });
                    
                    debug('Delete response', { status: response.status });
                    
                    if (!response.ok) {
                        // Try to parse error body for details
                        let errorPayload = null;
                        try { errorPayload = await response.json(); } catch (_) {}

                        if (response.status === 401) {
                            // Try refreshing token once more if unauthorized
                            const refreshed = await refreshToken();
                            debug('Token refresh for delete retry', { success: refreshed });
                            
                            if (refreshed) {
                                // Retry the delete request
                                debug('Retrying delete request');
                                
                                const retryResponse = await fetch('index.php', {
                                    method: 'POST',
                                    headers: { 
                                        'Content-Type': 'application/json',
                                        'Cache-Control': 'no-cache'
                                    },
                                    body: JSON.stringify({ action: 'delete_user', id: id }),
                                    credentials: 'same-origin'
                                });
                                
                                debug('Delete retry response', { status: retryResponse.status });
                                
                                if (!retryResponse.ok) {
                                    let retryError = null; try { retryError = await retryResponse.json(); } catch (_) {}
                                    const msg = retryError?.error || `HTTP error! status: ${retryResponse.status}`;
                                    throw new Error(msg);
                                }
                                
                                return await retryResponse.json();
                            } else {
                                throw new Error('Authentication failed');
                            }
                        } else {
                            const msg = errorPayload?.error || `HTTP error! status: ${response.status}`;
                            throw new Error(msg);
                        }
                    }
                    
                    const result = await response.json();
                    debug('Delete result', result);
                    
                    if (result.success) {
                        // Remove the row with animation
                        row.style.transition = 'all 0.5s';
                        row.style.backgroundColor = 'rgba(22, 101, 52, 0.3)';  // Green background
                        row.style.opacity = '0';
                        
                        setTimeout(() => {
                        row.remove();
                            showAlert('User deleted successfully', 'success');
                        }, 500);
                    } else {
                        // If server responded but not success, show the server-provided error
                        throw new Error(result.error || 'Failed to delete user');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    debug('Delete error', { message: error.message, stack: error.stack });
                    
                    if (error.message === 'Authentication failed') {
                        showAlert('Your session has expired. Please refresh the page and try again.', 'error');
                    } else {
                        // Enhanced error handling for detailed dependency information
                        let errorMessage = error.message || 'Failed to delete user';
                        
                        // If the error contains line breaks (indicating detailed dependency info)
                        if (errorMessage.includes('\n') || errorMessage.includes('•')) {
                            // Format for better display in alert
                            errorMessage = errorMessage.replace(/\n/g, '<br>');
                            
                            // Create a custom modal for better formatting
                            const modal = document.createElement('div');
                            modal.style.cssText = `
                                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                                background: rgba(0,0,0,0.5); z-index: 10000; display: flex;
                                align-items: center; justify-content: center;
                            `;
                            
                            modal.innerHTML = `
                                <div style="
                                    background: white; padding: 30px; border-radius: 10px; 
                                    max-width: 500px; max-height: 70vh; overflow-y: auto;
                                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                                ">
                                    <h3 style="color: #e74c3c; margin-bottom: 20px; font-size: 18px;">
                                        ⚠️ Cannot Delete User
                                    </h3>
                                    <div style="
                                        font-size: 14px; line-height: 1.6; color: #333;
                                        white-space: pre-line; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                                    ">${errorMessage}</div>
                                    <button onclick="this.closest('div').parentElement.remove()" 
                                            style="
                                                margin-top: 20px; padding: 10px 20px; 
                                                background: #3498db; color: white; border: none; 
                                                border-radius: 5px; cursor: pointer; font-size: 14px;
                                            ">
                                        OK
                                    </button>
                                </div>
                            `;
                            
                            document.body.appendChild(modal);
                            
                            // Close on background click
                            modal.addEventListener('click', (e) => {
                                if (e.target === modal) {
                                    modal.remove();
                                }
                            });
                        } else {
                            showAlert(`Error: ${errorMessage}`, 'error');
                        }
                    }
                }
            }
        });
    </script>
    <!-- Centralized JavaScript -->
    <script src="UI/assets/js/main.js"></script>
</body>
</html> 