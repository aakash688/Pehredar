<?php
require_once __DIR__ . '/../config.php';
$cfg = require __DIR__ . '/../config.php';
$baseUrl = rtrim($cfg['base_url'] ?? '', '/');
?>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Activity List</h1>

    <!-- Search and Filter Controls -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-grow">
            <input type="text" id="search-input" placeholder="Search by title..." class="w-full bg-gray-700 p-3 pl-10 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <div class="flex-shrink-0">
            <select id="society-filter" class="w-full sm:w-auto bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
                <option value="">All Societies</option>
                <!-- Society options will be populated here -->
            </select>
        </div>
        <div class="flex-shrink-0">
            <select id="status-filter" class="w-full sm:w-auto bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
                <option value="">All Statuses</option>
                <option value="Upcoming">Upcoming</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Completed">Completed</option>
            </select>
        </div>
    </div>
    
    <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-700">
                <tr>
                    <th class="py-3 px-6 text-left">Title</th>
                    <th class="py-3 px-6 text-left">Society</th>
                    <th class="py-3 px-6 text-left">Scheduled Date</th>
                    <th class="py-3 px-6 text-left">Status</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="activity-table-body">
                <tr><td colspan="5" class="text-center p-8">Loading activities...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div id="pagination-controls" class="flex justify-between items-center mt-6 text-white">
        <!-- Pagination will be rendered here -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('activity-table-body');
    const searchInput = document.getElementById('search-input');
    const societyFilter = document.getElementById('society-filter');
    const statusFilter = document.getElementById('status-filter');
    let searchTimeout;

    async function fetchActivities(search = '', societyId = '', status = '', page = 1) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-8">Loading activities...</td></tr>';
        try {
            const params = new URLSearchParams({
                action: 'get_activities',
                search: search,
                society_id: societyId,
                status: status,
                page: page
            });
            
            const response = await fetch(`index.php?${params.toString()}`);

            if (!response.ok) {
                if (response.status === 401) window.location.href = 'index.php?page=login';
                const errorData = await response.json().catch(() => ({ error: 'An unknown error occurred' }));
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.activities) {
                renderTable(result.activities);
                renderPagination(result.pagination);
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-8">Error: ${result.error || 'Could not fetch activities.'}</td></tr>`;
            }
        } catch (error) {
            console.error('Error fetching activities:', error);
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center p-8">${error.message}</td></tr>`;
        }
    }

    function renderTable(activities) {
        tableBody.innerHTML = '';
        if (activities.length > 0) {
            activities.forEach(activity => {
                const row = `
                    <tr class="border-b border-gray-700 hover:bg-gray-600">
                        <td class="py-4 px-6">${activity.title}</td>
                        <td class="py-4 px-6">${activity.society_name}</td>
                        <td class="py-4 px-6">${new Date(activity.scheduled_date).toLocaleString()}</td>
                        <td class="py-4 px-6"><span class="px-2 py-1 font-semibold leading-tight text-sm rounded-full bg-gray-600 text-gray-200">${activity.status}</span></td>
                        <td class="py-4 px-6 text-center" data-activity-id="${activity.id}">
                            <a href="index.php?page=view-activity&id=${activity.id}" class="view-btn text-green-400 hover:text-green-300 mr-3" title="View"><i class="fas fa-eye"></i></a>
                            <a href="index.php?page=edit-activity&id=${activity.id}" class="edit-btn text-blue-400 hover:text-blue-300 mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="delete-btn text-red-500 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-8">No activities found matching your criteria.</td></tr>';
        }
    }

    function renderPagination(pagination) {
        const { total_pages, current_page } = pagination;
        const container = document.getElementById('pagination-controls');
        if (total_pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let buttonsHTML = '';
        const search = searchInput.value;
        const societyId = societyFilter.value;
        const status = statusFilter.value;
        
        buttonsHTML += `<button class="px-4 py-2 bg-gray-600 rounded-lg hover:bg-indigo-600 disabled:bg-gray-700 disabled:cursor-not-allowed" ${current_page === 1 ? 'disabled' : ''} onclick="fetchActivities('${search}', '${societyId}', '${status}', ${current_page - 1})">Previous</button>`;
        
        let pageNumbersHTML = `<div class="flex items-center space-x-2"><span class="px-4 py-2 bg-gray-700 rounded-lg">Page ${current_page} of ${total_pages}</span></div>`;
        
        buttonsHTML += pageNumbersHTML;

        buttonsHTML += `<button class="px-4 py-2 bg-gray-600 rounded-lg hover:bg-indigo-600 disabled:bg-gray-700 disabled:cursor-not-allowed" ${current_page === total_pages ? 'disabled' : ''} onclick="fetchActivities('${search}', '${societyId}', '${status}', ${current_page + 1})">Next</button>`;

        container.innerHTML = buttonsHTML;
    }

    async function populateSocietyFilter() {
        // We can reuse the `get_societies` action if it exists, or create one.
        // Let's assume there's a simple getter for all societies for admins.
        try {
            const response = await fetch('index.php?action=get_societies');
            const result = await response.json();
            if (result.success && result.societies) {
                result.societies.forEach(society => {
                    const option = document.createElement('option');
                    option.value = society.id;
                    option.textContent = society.society_name;
                    societyFilter.appendChild(option);
                });
            }
        } catch(e) { console.error('Could not populate societies filter', e); }
    }
    
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchActivities(searchInput.value, societyFilter.value, statusFilter.value);
        }, 300);
    });

    societyFilter.addEventListener('change', () => {
        fetchActivities(searchInput.value, societyFilter.value, statusFilter.value);
    });

    statusFilter.addEventListener('change', () => {
        fetchActivities(searchInput.value, societyFilter.value, statusFilter.value);
    });

    // --- Event Delegation for Actions ---
    tableBody.addEventListener('click', e => {
        const button = e.target.closest('button');
        if (!button || !button.classList.contains('delete-btn')) return;
        
        const activityId = button.closest('td').dataset.activityId;
        handleDeleteActivity(activityId, button.closest('tr'));
    });

    function handleDeleteActivity(id, row) {
        if (!confirm('Are you sure you want to delete this activity? This action cannot be undone.')) return;

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_activity', id: id })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                alert('Activity deleted successfully.');
                row.remove();
            } else {
                alert(`Error: ${result.message}`);
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred while deleting the activity.');
        });
    }

    // Initial Data Load
    populateSocietyFilter();
    fetchActivities();
});
</script> 