<?php
// UI/hr/holiday_management/index.php
require_once __DIR__ . '/../../../helpers/database.php';
$db = new Database();
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-white mb-6">Holiday Management</h1>
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-4">
                <h2 class="text-xl text-white">Holidays for Year</h2>
                <div class="relative">
                    <select id="year-selector" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 pr-8 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php
                        // Show 5 years before and after current year
                        for ($year = $current_year - 5; $year <= $current_year + 5; $year++) {
                            $selected = ($year == $selected_year) ? 'selected' : '';
                            echo "<option value=\"$year\" $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="exportHolidays()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
                <label for="import-file" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded cursor-pointer">
                    <i class="fas fa-file-import"></i> Import CSV
                </label>
                <input type="file" id="import-file" accept=".csv" class="hidden" onchange="importHolidays(this)">
                <button onclick="openModal('add')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-plus"></i> Add New Holiday
                </button>
            </div>
        </div>

        <div id="calendar-container" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Calendar months will be generated here -->
        </div>

        <div class="mt-8">
            <h3 class="text-lg text-white mb-4">Holiday List</h3>
            <table class="min-w-full bg-gray-900 rounded-lg">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left text-gray-300">Date</th>
                        <th class="py-3 px-4 text-left text-gray-300">Name</th>
                        <th class="py-3 px-4 text-left text-gray-300">Description</th>
                        <th class="py-3 px-4 text-left text-gray-300">Status</th>
                        <th class="py-3 px-4 text-right text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody id="holiday-table-body">
                    <!-- Holiday list will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="holiday-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Add Holiday</h3>
                <div id="modal-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>
                <form id="holiday-form" class="mt-4 space-y-4">
                    <input type="hidden" id="holiday-id" name="id">
                    <div>
                        <label for="holiday-date" class="block text-sm font-medium text-gray-300">Date</label>
                        <input type="date" id="holiday-date" name="holiday_date" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="holiday-name" class="block text-sm font-medium text-gray-300">Name</label>
                        <input type="text" id="holiday-name" name="name" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="holiday-description" class="block text-sm font-medium text-gray-300">Description</label>
                        <textarea id="holiday-description" name="description" rows="3" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <label for="is-active" class="block text-sm font-medium text-gray-300">Status</label>
                        <select id="is-active" name="is_active" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="save-button" onclick="saveHoliday()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">
                    <span id="save-button-text">Save</span>
                    <i id="loader" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                </button>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="UI/assets/css/calendar.css">
<script src="UI/assets/js/attendance-management.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadHolidays();
        
        // Add year selector event listener
        document.getElementById('year-selector').addEventListener('change', function() {
            loadHolidays(this.value);
        });
    });
    
    let currentMode = 'add';
    let holidays = [];
    
    async function loadHolidays(year) {
        year = year || document.getElementById('year-selector').value;
        
        try {
            const response = await fetch(`actions/holiday_controller.php?action=get_holidays&year=${year}`);
            const result = await response.json();
            
            if (result.success) {
                holidays = result.data;
                renderHolidayTable();
                renderCalendar(year);
            } else {
                console.error('Error loading holidays:', result.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    function renderHolidayTable() {
        const tableBody = document.getElementById('holiday-table-body');
        tableBody.innerHTML = '';
        
        if (holidays.length === 0) {
            tableBody.innerHTML = `
                <tr class="border-t border-gray-700">
                    <td colspan="5" class="py-4 px-4 text-center text-gray-400">No holidays found for this year.</td>
                </tr>
            `;
            return;
        }
        
        // Sort holidays by date
        holidays.sort((a, b) => new Date(a.holiday_date) - new Date(b.holiday_date));
        
        holidays.forEach(holiday => {
            const formattedDate = new Date(holiday.holiday_date).toLocaleDateString();
            
            tableBody.innerHTML += `
                <tr id="row-${holiday.id}" class="border-t border-gray-700">
                    <td class="py-3 px-4 text-white">${formattedDate}</td>
                    <td class="py-3 px-4 text-white">${holiday.name}</td>
                    <td class="py-3 px-4 text-white">${holiday.description || '-'}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${holiday.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${holiday.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <button onclick='openModal("edit", ${JSON.stringify(holiday)})' class="text-blue-400 hover:text-blue-300 mr-3"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteHoliday(${holiday.id})" class="text-red-500 hover:text-red-400"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });
    }
    
    function renderCalendar(year) {
        const calendarContainer = document.getElementById('calendar-container');
        calendarContainer.innerHTML = '';
        
        // Generate calendar for each month
        for (let month = 0; month < 12; month++) {
            const monthElement = document.createElement('div');
            monthElement.className = 'bg-gray-900 p-4 rounded-lg';
            
            // Month header
            const monthDate = new Date(year, month, 1);
            const monthName = monthDate.toLocaleString('default', { month: 'long' });
            
            monthElement.innerHTML = `
                <h3 class="text-center text-white font-bold mb-2">${monthName}</h3>
                <div class="grid grid-cols-7 gap-1 text-center">
                    <div class="text-gray-500 text-sm">Su</div>
                    <div class="text-gray-500 text-sm">Mo</div>
                    <div class="text-gray-500 text-sm">Tu</div>
                    <div class="text-gray-500 text-sm">We</div>
                    <div class="text-gray-500 text-sm">Th</div>
                    <div class="text-gray-500 text-sm">Fr</div>
                    <div class="text-gray-500 text-sm">Sa</div>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center" id="calendar-days-${month}"></div>
            `;
            
            calendarContainer.appendChild(monthElement);
            
            // Generate days for this month
            const daysContainer = document.getElementById(`calendar-days-${month}`);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDay = new Date(year, month, 1).getDay();
            
            // Add empty cells for days before the first day of month
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'h-8 text-gray-700';
                daysContainer.appendChild(emptyDay);
            }
            
            // Add actual days
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                const date = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                
                // Check if this day is a holiday
                const isHoliday = holidays.some(h => h.holiday_date === date && h.is_active);
                
                dayElement.className = `h-8 flex items-center justify-center text-sm rounded ${isHoliday ? 'bg-red-600 text-white font-bold' : 'text-white'}`;
                dayElement.textContent = day;
                
                // If it's a holiday, add tooltip
                if (isHoliday) {
                    const holiday = holidays.find(h => h.holiday_date === date);
                    dayElement.title = holiday.name;
                    dayElement.style.cursor = 'pointer';
                    dayElement.onclick = () => openModal('edit', holiday);
                }
                
                daysContainer.appendChild(dayElement);
            }
        }
    }
    
    function openModal(mode, data = null) {
        currentMode = mode;
        const modal = document.getElementById('holiday-modal');
        const title = document.getElementById('modal-title');
        const form = document.getElementById('holiday-form');
        form.reset();

        if (mode === 'edit' && data) {
            title.textContent = 'Edit Holiday';
            document.getElementById('holiday-id').value = data.id;
            document.getElementById('holiday-date').value = data.holiday_date;
            document.getElementById('holiday-name').value = data.name;
            document.getElementById('holiday-description').value = data.description || '';
            document.getElementById('is-active').value = data.is_active ? '1' : '0';
        } else {
            title.textContent = 'Add Holiday';
            document.getElementById('is-active').value = '1';
        }

        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = document.getElementById('holiday-modal');
        modal.classList.add('hidden');
    }

    async function saveHoliday() {
        const saveButton = document.getElementById('save-button');
        const buttonText = document.getElementById('save-button-text');
        const loader = document.getElementById('loader');
        const modalFeedback = document.getElementById('modal-feedback');

        saveButton.disabled = true;
        buttonText.textContent = 'Saving...';
        loader.classList.remove('hidden');
        modalFeedback.classList.add('hidden');

        const id = document.getElementById('holiday-id').value;
        const holiday_date = document.getElementById('holiday-date').value;
        const name = document.getElementById('holiday-name').value;
        const description = document.getElementById('holiday-description').value;
        const is_active = document.getElementById('is-active').value === '1';

        const url = currentMode === 'add' ? 'actions/holiday_controller.php?action=add_holiday' : 'actions/holiday_controller.php?action=update_holiday';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, holiday_date, name, description, is_active })
            });

            const result = await response.json();
            if (result.success) {
                modalFeedback.innerHTML = `<div class="bg-green-500 text-white p-3 rounded-lg">${result.message}</div>`;
                modalFeedback.classList.remove('hidden');
                setTimeout(() => {
                    closeModal();
                    loadHolidays();
                }, 1500);
            } else {
                modalFeedback.innerHTML = `<div class="bg-red-500 text-white p-3 rounded-lg">Error: ${result.message}</div>`;
                modalFeedback.classList.remove('hidden');
            }
        } catch (error) {
            modalFeedback.innerHTML = `<div class="bg-red-500 text-white p-3 rounded-lg">Error: ${error.message}</div>`;
            modalFeedback.classList.remove('hidden');
        } finally {
            saveButton.disabled = false;
            buttonText.textContent = 'Save';
            loader.classList.add('hidden');
        }
    }

    async function deleteHoliday(id) {
        if (!confirm('Are you sure you want to deactivate this holiday?')) return;

        try {
            const response = await fetch('actions/holiday_controller.php?action=delete_holiday', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const result = await response.json();
            if (result.success) {
                loadHolidays();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    /**
     * Export holidays as CSV file
     */
    function exportHolidays() {
        const year = document.getElementById('year-selector').value;
        window.location.href = `actions/holiday_controller.php?action=export_holidays&year=${year}`;
    }
    
    /**
     * Import holidays from CSV file
     */
    async function importHolidays(fileInput) {
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Please select a CSV file to import.');
            return;
        }
        
        // Confirm import
        if (!confirm('Are you sure you want to import holidays? Existing holidays with the same date will be updated.')) {
            fileInput.value = ''; // Reset file input
            return;
        }
        
        const file = fileInput.files[0];
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            alert('Please select a valid CSV file.');
            fileInput.value = '';
            return;
        }
        
        // Create form data for file upload
        const formData = new FormData();
        formData.append('csv_file', file);
        
        try {
            // Show loading state
            document.body.style.cursor = 'wait';
            
            // Send file to server
            const response = await fetch('actions/holiday_controller.php?action=import_holidays', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            // Reset file input
            fileInput.value = '';
            
            if (result.success) {
                alert(result.message);
                // Reload holidays
                loadHolidays();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            document.body.style.cursor = 'default';
        }
    }
</script>
