/**
 * Shift Management JavaScript
 * Handles shift management UI interactions, API calls, and data rendering
 */

// Global variable to store shifts data
let shiftsData = [];

/**
 * Initialize shift management
 */
function initShiftManagement() {
    // Add event listeners
    document.getElementById('add-shift-btn').addEventListener('click', () => openShiftModal('add'));
    document.getElementById('status-filter').addEventListener('change', fetchAndRenderShifts);
    
    // Fetch initial data
    fetchAndRenderShifts();
}

/**
 * Fetch shifts and render the table
 */
async function fetchAndRenderShifts() {
    try {
        // Show loading indicator
        showElement('loading-indicator');
        hideElement('shifts-table');
        hideElement('no-shifts-message');
        
        // Get filter values
        const statusFilter = document.getElementById('status-filter').value;
        
        // Build query string
        let queryString = 'action=get_shifts';
        if (statusFilter !== '') {
            queryString += `&is_active=${encodeURIComponent(statusFilter)}`;
        }
        
        // Fetch data
        const response = await fetch(`actions/shift_controller.php?${queryString}`);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Non-JSON response:', await response.text());
            throw new Error('Server returned non-JSON response');
        }
        
        const result = await response.json();
        
        if (result.success) {
            shiftsData = result.data;
            renderShiftsTable(shiftsData);
        } else {
            console.error('Error fetching shifts:', result.message);
            alert('Error loading shifts: ' + result.message);
        }
    } catch (error) {
        console.error('Error in fetchAndRenderShifts:', error);
        alert('Failed to load shifts. Please try refreshing the page.');
    } finally {
        hideElement('loading-indicator');
    }
}

/**
 * Render the shifts table
 * @param {Array} shifts - The shifts data to render
 */
function renderShiftsTable(shifts) {
    const tableBody = document.getElementById('shifts-table-body');
    
    // Clear existing rows
    tableBody.innerHTML = '';
    
    if (!shifts || shifts.length === 0) {
        hideElement('shifts-table');
        showElement('no-shifts-message');
        return;
    }
    
    // Show table and hide no data message
    showElement('shifts-table');
    hideElement('no-shifts-message');
    
    // Add rows
    shifts.forEach(shift => {
        const row = document.createElement('tr');
        row.className = 'border-t border-gray-700';
        
        // Format times for display
        const startTime = formatTime(shift.start_time);
        const endTime = formatTime(shift.end_time);
        
        // Create cell content
        row.innerHTML = `
            <td class="py-3 px-4">${escapeHtml(shift.shift_name)}</td>
            <td class="py-3 px-4">${startTime}</td>
            <td class="py-3 px-4">${endTime}</td>
            <td class="py-3 px-4">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${shift.is_active === 1 ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'}">
                    ${shift.is_active === 1 ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td class="py-3 px-4 text-right">
                <button class="text-blue-400 hover:text-blue-200 mr-2" onclick="openShiftModal('edit', ${shift.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                ${shift.is_active === 1 ? `
                <button class="text-red-400 hover:text-red-200" onclick="openDeleteModal(${shift.id})">
                    <i class="fas fa-times-circle"></i> Deactivate
                </button>
                ` : `
                <button class="text-green-400 hover:text-green-200" onclick="reactivateShift(${shift.id})">
                    <i class="fas fa-check-circle"></i> Activate
                </button>
                `}
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

/**
 * Format time for display
 * @param {string} timeString - Time in HH:MM:SS format
 * @returns {string} - Formatted time
 */
function formatTime(timeString) {
    if (!timeString) return '';
    
    try {
        // Extract hours and minutes from time string
        const parts = timeString.split(':');
        const hours = parseInt(parts[0], 10);
        const minutes = parts[1];
        
        // Format in 12-hour format with AM/PM
        const period = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
        
        return `${displayHours}:${minutes} ${period}`;
    } catch (error) {
        console.error('Error formatting time:', error);
        return timeString;
    }
}

/**
 * Open shift modal for adding or editing
 * @param {string} mode - 'add' or 'edit'
 * @param {number} shiftId - ID of the shift to edit (when mode is 'edit')
 */
function openShiftModal(mode, shiftId = null) {
    // Set modal title
    const modalTitle = document.getElementById('shift-modal-title');
    modalTitle.textContent = mode === 'add' ? 'Add New Shift' : 'Edit Shift';
    
    // Reset form
    document.getElementById('shift-form').reset();
    document.getElementById('shift-id').value = '';
    
    // For edit mode, populate form with shift data
    if (mode === 'edit' && shiftId) {
        const shift = shiftsData.find(s => parseInt(s.id) === parseInt(shiftId));
        
        if (shift) {
            // Set form values
            document.getElementById('shift-id').value = shift.id;
            document.getElementById('shift-name').value = shift.shift_name;
            document.getElementById('shift-start').value = shift.start_time ? shift.start_time.substring(0, 5) : '';
            document.getElementById('shift-end').value = shift.end_time ? shift.end_time.substring(0, 5) : '';
            document.getElementById('shift-description').value = shift.description || '';
            document.getElementById('shift-status').checked = shift.is_active === '1' || shift.is_active === 1;
            
            // Show status field for edit mode
            document.getElementById('shift-status-container').style.display = 'block';
        }
    } else {
        // Hide status field for add mode
        document.getElementById('shift-status-container').style.display = 'none';
    }
    
    // Show modal
    showElement('shift-modal');
}

/**
 * Close shift modal
 */
function closeShiftModal() {
    hideElement('shift-modal');
}

/**
 * Open delete confirmation modal
 * @param {number} shiftId - ID of the shift to delete
 */
function openDeleteModal(shiftId) {
    document.getElementById('delete-shift-id').value = shiftId;
    showElement('delete-modal');
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
    hideElement('delete-modal');
}

/**
 * Save shift
 */
async function saveShift() {
    try {
        // Get form values
        const shiftId = document.getElementById('shift-id').value;
        const shiftName = document.getElementById('shift-name').value;
        const startTime = document.getElementById('shift-start').value;
        const endTime = document.getElementById('shift-end').value;
        const description = document.getElementById('shift-description').value;
        const isActive = document.getElementById('shift-status').checked ? 1 : 0;
        
        // Validate required fields
        if (!shiftName || !startTime || !endTime) {
            alert('Please fill in all required fields.');
            return;
        }
        
        // Create payload
        const payload = {
            shift_name: shiftName,
            start_time: startTime,
            end_time: endTime,
            description: description
        };
        
        // For edit mode, add id and status
        if (shiftId) {
            payload.id = parseInt(shiftId);
            payload.is_active = isActive;
        }
        
        // Determine action based on mode
        const action = shiftId ? 'update_shift' : 'create_shift';
        
        // Send request
        const response = await fetch(`actions/shift_controller.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(shiftId ? 'Shift updated successfully.' : 'Shift created successfully.');
            closeShiftModal();
            fetchAndRenderShifts();
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error in saveShift:', error);
        alert('Failed to save shift. Please try again.');
    }
}

/**
 * Deactivate a shift
 */
async function deactivateShift() {
    try {
        const shiftId = document.getElementById('delete-shift-id').value;
        
        if (!shiftId) {
            alert('No shift selected for deactivation.');
            return;
        }
        
        const response = await fetch('actions/shift_controller.php?action=delete_shift', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: parseInt(shiftId)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Shift deactivated successfully.');
            closeDeleteModal();
            fetchAndRenderShifts();
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error in deactivateShift:', error);
        alert('Failed to deactivate shift. Please try again.');
    }
}

/**
 * Reactivate a shift
 */
async function reactivateShift(shiftId) {
    try {
        if (!shiftId) {
            alert('No shift selected for reactivation.');
            return;
        }

        const response = await fetch('actions/shift_controller.php?action=reactivate_shift', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: parseInt(shiftId)
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Shift reactivated successfully.');
            closeDeleteModal(); // Close the delete modal as it's now a reactivation modal
            fetchAndRenderShifts();
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error in reactivateShift:', error);
        alert('Failed to reactivate shift. Please try again.');
    }
}

/**
 * Show an element
 * @param {string} id - Element ID
 */
function showElement(id) {
    const element = document.getElementById(id);
    if (element) {
        element.classList.remove('hidden');
        
        // For modals, add additional styling to position them properly
        if (id.includes('modal')) {
            document.body.classList.add('modal-open');
            element.style.display = 'block';
            element.style.position = 'fixed';
            element.style.top = '0';
            element.style.right = '0';
            element.style.bottom = '0';
            element.style.left = '0';
            element.style.zIndex = '1050';
            element.style.overflow = 'auto';
            
            // Add backdrop
            if (!document.getElementById('modal-backdrop')) {
                const backdrop = document.createElement('div');
                backdrop.id = 'modal-backdrop';
                backdrop.style.position = 'fixed';
                backdrop.style.top = '0';
                backdrop.style.right = '0';
                backdrop.style.bottom = '0';
                backdrop.style.left = '0';
                backdrop.style.zIndex = '1040';
                backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                document.body.appendChild(backdrop);
            }
        }
    }
}

/**
 * Hide an element
 * @param {string} id - Element ID
 */
function hideElement(id) {
    const element = document.getElementById(id);
    if (element) {
        element.classList.add('hidden');
        
        // For modals, remove additional styling
        if (id.includes('modal')) {
            document.body.classList.remove('modal-open');
            element.style.display = 'none';
            
            // Remove backdrop if no other modals are open
            const openModals = document.querySelectorAll('.modal:not(.hidden)');
            if (openModals.length === 0) {
                const backdrop = document.getElementById('modal-backdrop');
                if (backdrop) {
                    document.body.removeChild(backdrop);
                }
            }
        }
    }
}

/**
 * Escape HTML to prevent XSS
 * @param {string} unsafe - Potentially unsafe string
 * @returns {string} - Escaped string
 */
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
} 