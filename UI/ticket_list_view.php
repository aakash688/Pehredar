<?php
// UI/ticket_list_view.php
global $is_admin, $all_societies;
?>
<div class="bg-gray-800 p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Ticket Management</h1>
        <div class="flex items-center space-x-4">
            <a href="index.php?page=create-ticket" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                <i class="fas fa-plus-circle mr-2"></i>Create New Ticket
            </a>
        </div>
    </div>

    <!-- Analytics Section -->
    <div id="analytics-section" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6 text-white">
        <!-- Cards will be injected here by JS -->
    </div>

    <!-- Search and Filters -->
    <div class="bg-gray-700 p-4 rounded-lg mb-6">
        <form id="filter-form">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <input type="text" id="search" name="search" placeholder="Search by title..." 
                       class="bg-gray-800 text-white p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                
                <select id="status" name="status" class="bg-gray-800 text-white p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="Open">Open</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Closed">Closed</option>
                    <option value="On Hold">On Hold</option>
                </select>

                <select id="priority" name="priority" class="bg-gray-800 text-white p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Priorities</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
                
                <select id="society_id" name="society_id" class="bg-gray-800 text-white p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Societies</option>
                    <?php foreach ($all_societies as $society): ?>
                        <option value="<?php echo $society['id']; ?>"><?php echo htmlspecialchars($society['society_name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 col-span-1 md:col-span-1 lg:col-span-1">
                    <i class="fas fa-filter mr-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="overflow-x-auto bg-gray-700 rounded-lg">
        <table class="min-w-full text-sm text-left text-gray-300">
            <thead class="text-xs text-white uppercase bg-gray-900">
                <tr>
                    <th scope="col" class="px-6 py-3">Ticket ID</th>
                    <th scope="col" class="px-6 py-3">Title</th>
                    <th scope="col" class="px-6 py-3">Society</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3">Priority</th>
                    <th scope="col" class="px-6 py-3">Created At</th>
                    <th scope="col" class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="ticket-table-body">
                <tr><td colspan="7" class="text-center p-6"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div id="pagination-controls" class="flex justify-between items-center mt-6 text-white">
        <!-- Pagination will be rendered here -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filter-form');
    
    // Parse URL parameters and set form values
    const urlParams = new URLSearchParams(window.location.search);
    
    // Set form values from URL parameters
    document.getElementById('search').value = urlParams.get('search') || '';
    document.getElementById('status').value = urlParams.get('status') || '';
    document.getElementById('priority').value = urlParams.get('priority') || '';
    if (document.getElementById('society_id')) {
        document.getElementById('society_id').value = urlParams.get('society_id') || '';
    }
    
    // Get the current page number, but don't confuse with the page name parameter
    let currentPageNum = 1;
    // Check if there's a pageNum parameter specifically for pagination
    if (urlParams.has('pageNum')) {
        currentPageNum = parseInt(urlParams.get('pageNum'), 10) || 1;
    }

    function updateData() {
        fetchTickets(currentPageNum);
        fetchAnalytics();
    }

    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        // Reset to page 1 when filters change
        fetchTickets(1);
        fetchAnalytics();
    });

    // Handle browser back/forward navigation
    window.addEventListener('popstate', function() {
        // Re-parse URL parameters when navigation happens
        const newParams = new URLSearchParams(window.location.search);
        
        // Update form fields
        document.getElementById('search').value = newParams.get('search') || '';
        document.getElementById('status').value = newParams.get('status') || '';
        document.getElementById('priority').value = newParams.get('priority') || '';
    if (document.getElementById('society_id')) {
            document.getElementById('society_id').value = newParams.get('society_id') || '';
        }
        
        // Get the current page number from URL
        let pageNum = 1;
        if (newParams.has('pageNum')) {
            pageNum = parseInt(newParams.get('pageNum'), 10) || 1;
        }
        
        // Fetch data based on new URL
        fetchTickets(pageNum);
        fetchAnalytics();
    });
    
    // Initial data load
    updateData();
});

function buildQueryString(page = 1) {
    const form = document.getElementById('filter-form');
    const formData = new FormData(form);
    const params = new URLSearchParams();

    // Add form fields, ensuring they match backend expectations
    const fields = ['search', 'status', 'priority', 'society_id'];
    fields.forEach(field => {
        const value = formData.get(field);
        if (value) {
            params.set(field, value);
        }
    });
    
    // Use pageNum instead of page to avoid conflict with the page name parameter
    params.set('pageNum', page);

    return params.toString();
}


function fetchAnalytics() {
    // Create a copy of the current form parameters
    const form = document.getElementById('filter-form');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    // Add only filter parameters, not pagination
    ['search', 'status', 'priority', 'society_id'].forEach(field => {
        const value = formData.get(field);
        if (value) {
            params.set(field, value);
        }
    });
    
    fetch(`index.php?action=get_ticket_analytics&${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if(data.success) {
                renderAnalytics(data.analytics);
            } else {
                console.error("Failed to fetch analytics", data.message);
            }
        })
        .catch(error => console.error('Error fetching analytics:', error));
}

function renderAnalytics(analytics) {
    const container = document.getElementById('analytics-section');
    container.innerHTML = `
        <div class="bg-gray-900 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.total_tickets}</p>
            <p class="text-sm text-gray-400">Total Tickets</p>
        </div>
        <div class="bg-green-700 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.open_tickets}</p>
            <p class="text-sm text-gray-200">Open Tickets</p>
        </div>
        <div class="bg-red-700 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.closed_tickets}</p>
            <p class="text-sm text-gray-200">Closed Tickets</p>
        </div>
        <div class="bg-yellow-600 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.high_priority}</p>
            <p class="text-sm text-gray-200">High Priority</p>
        </div>
        <div class="bg-blue-600 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.older_1_day}</p>
            <p class="text-sm text-gray-200">> 1 Day Old</p>
        </div>
        <div class="bg-purple-600 p-4 rounded-lg text-center">
            <p class="text-2xl font-bold">${analytics.older_1_week}</p>
            <p class="text-sm text-gray-200">> 1 Week Old</p>
        </div>
    `;
}


function fetchTickets(page = 1) {
    const tbody = document.getElementById('ticket-table-body');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center p-6"><i class="fas fa-spinner fa-spin"></i> Loading tickets...</td></tr>';
    
    const params = buildQueryString(page);
    
    // Update URL for bookmarking/sharing - use correct format to avoid duplicate page parameter
    const url = new URL(window.location.href);
    
    // Clear existing query parameters and set page=ticket-list
    url.search = '';
    url.searchParams.set('page', 'ticket-list');
    
    // Add all other parameters from the form
    const formParams = new URLSearchParams(params);
    formParams.forEach((value, key) => {
        url.searchParams.set(key, value);
    });
    
    // Update browser URL without reloading the page
    history.pushState(null, '', url.toString());

    // Create a separate URL for the API call
    fetch(`index.php?action=get_tickets&${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.tickets.length > 0) {
                data.tickets.forEach(ticket => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-gray-800 hover:bg-gray-600';
                    row.innerHTML = `
                        <td class="px-6 py-4 font-medium text-white">#TKT-${ticket.id.toString().padStart(6, '0')}</td>
                        <td class="px-6 py-4">${escapeHTML(ticket.title)}</td>
                        <td class="px-6 py-4">${escapeHTML(ticket.society_name)}</td>
                        <td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-xs font-semibold ${getStatusClass(ticket.status)}">${escapeHTML(ticket.status)}</span></td>
                        <td class="px-6 py-4"><span class="font-bold" style="color: ${getPriorityColor(ticket.priority)}">${escapeHTML(ticket.priority)}</span></td>
                        <td class="px-6 py-4">${new Date(ticket.created_at).toLocaleString()}</td>
                        <td class="px-6 py-4 text-center">
                            <a href="index.php?page=ticket-details&id=${ticket.id}" class="text-indigo-400 hover:text-indigo-300 mr-4"><i class="fas fa-eye"></i> View</a>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                renderPagination(data.pagination);
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center px-6 py-10">No tickets found.</td></tr>';
                document.getElementById('pagination-controls').innerHTML = ''; // Clear pagination if no results
            }
        })
        .catch(error => {
            console.error('Error fetching tickets:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center px-6 py-10 text-red-400">Failed to load tickets: ' + error.message + '</td></tr>';
        });
}

function renderPagination(pagination) {
    const { total_pages, current_page, total_tickets, per_page } = pagination;
    const container = document.getElementById('pagination-controls');
    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let buttonsHTML = '';
    
    // Previous button
    buttonsHTML += `<button class="px-4 py-2 bg-gray-600 rounded-lg hover:bg-indigo-600 disabled:bg-gray-700 disabled:cursor-not-allowed" ${current_page === 1 ? 'disabled' : ''} onclick="fetchTickets(${current_page - 1})">
        <i class="fas fa-arrow-left mr-2"></i> Previous
    </button>`;

    // Page number buttons
    let pageNumbersHTML = '<div class="flex items-center space-x-2">';
    
    // Show page numbers with ellipsis for large page counts
    const maxVisiblePages = 5;
    const halfVisible = Math.floor(maxVisiblePages / 2);
    
    let startPage = Math.max(1, current_page - halfVisible);
    let endPage = Math.min(total_pages, startPage + maxVisiblePages - 1);
    
    // Adjust start page if we're near the end
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // First page + ellipsis
    if (startPage > 1) {
        pageNumbersHTML += `<button class="px-3 py-1 bg-gray-600 rounded-lg hover:bg-indigo-600" onclick="fetchTickets(1)">1</button>`;
        if (startPage > 2) {
            pageNumbersHTML += `<span class="px-2">...</span>`;
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        if (i === current_page) {
            pageNumbersHTML += `<button class="px-3 py-1 bg-indigo-600 rounded-lg" disabled>${i}</button>`;
        } else {
            pageNumbersHTML += `<button class="px-3 py-1 bg-gray-600 rounded-lg hover:bg-indigo-600" onclick="fetchTickets(${i})">${i}</button>`;
        }
    }
    
    // Last page + ellipsis
    if (endPage < total_pages) {
        if (endPage < total_pages - 1) {
            pageNumbersHTML += `<span class="px-2">...</span>`;
        }
        pageNumbersHTML += `<button class="px-3 py-1 bg-gray-600 rounded-lg hover:bg-indigo-600" onclick="fetchTickets(${total_pages})">${total_pages}</button>`;
    }
    
    pageNumbersHTML += '</div>';

    buttonsHTML += pageNumbersHTML;

    // Next button
    buttonsHTML += `<button class="px-4 py-2 bg-gray-600 rounded-lg hover:bg-indigo-600 disabled:bg-gray-700 disabled:cursor-not-allowed" ${current_page === total_pages ? 'disabled' : ''} onclick="fetchTickets(${current_page + 1})">
        Next <i class="fas fa-arrow-right ml-2"></i>
    </button>`;

    // Add page info
    buttonsHTML += `<div class="text-sm text-gray-400">Showing ${Math.min(total_tickets, (current_page - 1) * per_page + 1)}-${Math.min(total_tickets, current_page * per_page)} of ${total_tickets} tickets</div>`;

    container.innerHTML = buttonsHTML;
}

function getStatusClass(status) {
    switch(status) {
        case 'Open': return 'bg-green-600 text-green-100';
        case 'In Progress': return 'bg-yellow-600 text-yellow-100';
        case 'Closed': return 'bg-red-600 text-red-100';
        case 'On Hold': return 'bg-gray-500 text-gray-100';
        default: return 'bg-gray-600 text-gray-100';
    }
}

function getPriorityColor(priority) {
    switch(priority) {
        case 'Low': return '#34D399'; // Emerald-400
        case 'Medium': return '#FBBF24'; // Amber-400
        case 'High': return '#F87171'; // Red-400
        case 'Critical': return '#DC2626'; // Red-600
        default: return '#9CA3AF'; // Gray-400
    }
}

function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}
</script> 