// UI/assets/js/attendance-management.js
// --- Global Variables ---
let attendanceData = null;
let attendanceCodes = [];
let userSocieties = {}; // If needed per user
let availableShifts = [];
let changedCells = []; // For bulk save (if still used)
let currentPage = 1;
let pageSize = parseInt(localStorage.getItem('attendance_page_size')) || 10;
let filteredUsers = [];
let searchQuery = '';
let lastEntryContext = null; // Stores last opened modal context

// --- Utility Functions ---
const debounce = (func, delay) => {
    let debounceTimer;
    return function () {
        const context = this;
        const args = arguments;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => func.apply(context, args), delay);
    };
};

function escapeHtml(text) {
    if (typeof text !== 'string') return text; // Handle non-string inputs
    const map = {
        '&': '&amp;',
        '<': '<',
        '>': '>',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatTimeForDisplay(timeStr) {
    if (!timeStr) return 'N/A';
    // Assuming timeStr is in HH:MM:SS format
    const parts = timeStr.split(':');
    if (parts.length >= 2) {
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1];
        const period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12; // Convert 0 to 12
        return `${hours}:${minutes} ${period}`;
    }
    return timeStr;
}

function formatTimeForInput(timeStr) {
     if (!timeStr) return '';
     const parts = timeStr.split(':');
     if (parts.length >= 2) {
         return `${parts[0]}:${parts[1]}`; // HH:MM
     }
     return timeStr;
}

/**
 * Convert time string to minutes for comparison
 */
function timeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return (hours * 60) + minutes;
}

/**
 * Check if a shift spans overnight (end time is earlier than start time)
 */
function isOvernightShift(startTime, endTime) {
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);
    return endMinutes < startMinutes;
}

function getUserInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function getAvatarColor(userId) {
    // Simple hash-based color generation
    const hash = Math.abs((userId * 9301 + 49297) % 233280) / 233280;
    const hue = Math.floor(hash * 360);
    return `hsl(${hue}, 70%, 45%)`; // Slightly different saturation/lightness for better contrast
}

function findSocietyName(societyId) {
    if (window.allSocieties && societyId) {
        const society = window.allSocieties.find(s => s.id == societyId);
        return society ? society.society_name : 'Unknown Society';
    }

    // Fallback: if societies aren't loaded yet, try to load them
    if (!window.allSocieties && societyId) {
        fetchUserSocietiesLazy();
    }

    return 'Unknown Society';
}

function scrollToTopOfTable() {
    const tableContainer = document.getElementById('attendance-table-container');
    if (tableContainer) {
        tableContainer.scrollTop = 0;
    }
}

function checkScrollIndicators() {
    const container = document.querySelector('#attendance-table-container .overflow-auto');
    const leftIndicator = document.querySelector('.horizontal-scroll-indicator-left');
    const rightIndicator = document.querySelector('.horizontal-scroll-indicator-right');

    if (!container || !leftIndicator || !rightIndicator) return;

    const { scrollLeft, scrollWidth, clientWidth } = container;
    const isScrollable = scrollWidth > clientWidth;

    if (!isScrollable) {
        leftIndicator.classList.remove('visible');
        rightIndicator.classList.remove('visible');
        return;
    }

    if (scrollLeft > 5) {
        leftIndicator.classList.add('visible');
    } else {
        leftIndicator.classList.remove('visible');
    }

    if (scrollLeft < (scrollWidth - clientWidth - 5)) {
        rightIndicator.classList.add('visible');
    } else {
        rightIndicator.classList.remove('visible');
    }
}

// --- DOM Ready Initialization ---
document.addEventListener('DOMContentLoaded', function () {
    console.log("Attendance Management JS initialized.");
    setupEventListeners();
    loadInitialData();
    setupPageSizeSelector();
    checkScrollIndicators(); // Initial check
});

// --- Setup Functions ---
function setupEventListeners() {
    document.getElementById('filter-button')?.addEventListener('click', applyFilters);
    document.getElementById('reset-filters-btn')?.addEventListener('click', resetFilters);

    const instructionButtons = document.querySelectorAll('#show-instructions-btn, #show-instructions-btn-top');
    instructionButtons.forEach(btn => btn.addEventListener('click', toggleInstructions));

    document.getElementById('show-legend-btn')?.addEventListener('click', toggleLegend);
    document.getElementById('save-attendance-btn')?.addEventListener('click', saveAttendanceChanges); // Keep if bulk save is used
    document.getElementById('download-excel-btn')?.addEventListener('click', exportToExcel);
    document.getElementById('export-csv-btn')?.addEventListener('click', exportToCSV);
    document.getElementById('refresh-data-btn')?.addEventListener('click', refreshData);

    const searchInput = document.getElementById('employee-search');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () {
            searchQuery = this.value.trim().toLowerCase();
            currentPage = 1;
            renderAttendanceTable();
        }, 300));
    }

    document.getElementById('page-size')?.addEventListener('change', function () {
        const newSize = parseInt(this.value);
        if ([10, 20, 50].includes(newSize)) {
            pageSize = newSize;
            localStorage.setItem('attendance_page_size', newSize.toString());
            currentPage = 1;
            renderAttendanceTable();
        }
    });

    document.getElementById('prev-page')?.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderAttendanceTable();
            scrollToTopOfTable();
        }
    });

    document.getElementById('next-page')?.addEventListener('click', function () {
        const totalUsers = filteredUsers.length;
        const totalPages = Math.ceil(totalUsers / pageSize);
        if (currentPage < totalPages) {
            currentPage++;
            renderAttendanceTable();
            scrollToTopOfTable();
        }
    });

    // Modal close events (using existing function logic)
    document.getElementById('attendance-entry-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        saveAttendanceEntry();
    });

    // Scroll indicator listeners
    const tableContainer = document.getElementById('attendance-table-container');
    tableContainer?.addEventListener('scroll', debounce(checkScrollIndicators, 100));
    window.addEventListener('resize', debounce(checkScrollIndicators, 100));
}

function setupPageSizeSelector() {
    const selector = document.getElementById('page-size');
    if (selector) {
        selector.value = pageSize;
    }
}

async function loadInitialData() {
    showLoader();
    try {
        // Load essential data first (faster)
        await fetchAttendanceMasterCodes();

        // Load user societies first to ensure tooltips work correctly
        await fetchUserSocietiesLazy();

        // Load attendance data with optimized endpoint
        await loadAttendanceDataOptimized();

    } catch (err) {
        console.error("Error loading initial data:", err);
        hideLoader();
    }
}

// Optimized attendance data loading
async function loadAttendanceDataOptimized(forceRefresh = false) {
    const startTime = performance.now();

    try {
        const params = new URLSearchParams({
            action: 'get_attendance_data_optimized', // Use optimized endpoint
            month: document.getElementById('month-filter')?.value || new Date().getMonth() + 1,
            year: document.getElementById('year-filter')?.value || new Date().getFullYear(),
            user_type: document.getElementById('department-filter')?.value || '',
            society_id: document.getElementById('society-filter')?.value || '',
            team_id: document.getElementById('team-filter')?.value || ''
        });

        // Add force refresh parameter if needed
        if (forceRefresh) {
            params.append('force_refresh', '1');
        }

                const response = await fetch(`actions/attendance_controller_optimized.php?${params}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        // Check content type to ensure we get JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }

        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Failed to fetch data');

        attendanceData = result.data;
        
        const loadTime = Math.round(performance.now() - startTime);
        console.log(`✅ Optimized attendance data loaded in ${loadTime}ms`, {
            cached: result.cached,
            performance: result.performance,
            users: attendanceData.users?.length || 0
        });

        // Initialize filtered users for search functionality
        filteredUsers = [...(attendanceData.users || [])];
        currentPage = 1; // Reset to first page

        renderAttendanceTable();
        hideLoader();
        
        // Populate legend
        populateLegend(attendanceCodes);
        
    } catch (error) {
        console.error('Error loading optimized attendance data:', error);
        hideLoader();
        
        // Fallback to original method if optimized fails
        console.warn('Falling back to original attendance loading...');
        await loadAttendanceData();
    }
}

// Lazy load user societies only when modal is opened
async function fetchUserSocietiesLazy() {
    if (Object.keys(userSocieties).length > 0) {
        return; // Already loaded
    }
    
    try {
        const response = await fetch('actions/attendance_controller.php?action=get_user_societies');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            userSocieties = result.data;
            window.allSocieties = result.data; // Also store globally for modal use
        }
    } catch (error) {
        console.error('Error fetching user societies:', error);
    }
}

// --- Data Fetching ---
async function loadAttendanceData() {
    showLoader();
    try {
        const params = new URLSearchParams({
            action: 'get_attendance_data',
            month: document.getElementById('month-filter')?.value || new Date().getMonth() + 1,
            year: document.getElementById('year-filter')?.value || new Date().getFullYear(),
            user_type: document.getElementById('department-filter')?.value || '',
            society_id: document.getElementById('society-filter')?.value || '',
            team_id: document.getElementById('team-filter')?.value || ''
        });

        const response = await fetch(`actions/attendance_controller.php?${params}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        // Check content type to ensure we get JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }

        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Failed to fetch data');

        attendanceData = result.data;
        console.log("Loaded attendance data:", attendanceData); // Debug log

        // Initialize filtered users for search functionality
        filteredUsers = [...(attendanceData.users || [])];
        currentPage = 1; // Reset to first page

    renderAttendanceTable();
        hideLoader();
        // Update legend if needed (assuming populateLegend exists or is similar)
        populateLegend(attendanceCodes);
    } catch (error) {
        console.error('Error loading attendance data:', error);
        hideLoader();
        alert('Failed to load attendance data. Please try again.');
        // Show error in UI (avoid showing HTML content)
        const tableContainer = document.getElementById('attendance-table-container');
        if (tableContainer) {
            tableContainer.classList.remove('hidden');
            const errorMessage = error.message.includes('<') ? 'Server error occurred. Please refresh the page.' : error.message;
            tableContainer.innerHTML = `<div class="bg-red-800 text-white p-4 rounded"><h3 class="font-bold">Error Loading Data</h3><p>${errorMessage}</p></div>`;
        }
    }
}

async function fetchAttendanceMasterCodes() {
    try {
        const response = await fetch('actions/attendance_controller.php?action=get_attendance_master_codes');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            attendanceCodes = result.data;
            populateEntryCodes(attendanceCodes); // Populate dropdown in modal
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error fetching attendance codes:', error);
    }
}

/**
 * Fetch shifts for the attendance entry modal
 */
async function fetchShifts() {
    const shiftSelect = document.getElementById('entry-shift');
    if (shiftSelect) {
        shiftSelect.innerHTML = '<option value="">Loading shifts...</option>';
        shiftSelect.disabled = true;
    }
    
    try {
        // Only fetch active shifts
        const response = await fetch(`actions/shift_controller.php?action=get_shifts&is_active=1`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const result = await response.json();
        if (result.success) {
            availableShifts = result.data;
            populateShiftDropdown(availableShifts);
            console.log('Shifts loaded successfully:', availableShifts.length);
        } else {
            console.error('Error fetching shifts:', result.message);
            if (shiftSelect) {
                shiftSelect.innerHTML = '<option value="">Error loading shifts</option>';
            }
        }
    } catch (error) {
        console.error('Error fetching shifts:', error);
        if (shiftSelect) {
            shiftSelect.innerHTML = '<option value="">Error loading shifts</option>';
        }
    } finally {
        if (shiftSelect) {
            shiftSelect.disabled = false;
        }
    }
}

/**
 * Populate shift dropdown with data from the database
 */
function populateShiftDropdown(shifts) {
    const shiftSelect = document.getElementById('entry-shift');
    if (!shiftSelect) return;
    
    shiftSelect.innerHTML = '<option value="">Select Shift</option>';
    
    if (!shifts || shifts.length === 0) {
        const option = document.createElement('option');
        option.value = "";
        option.textContent = "No shifts available";
        option.disabled = true;
        shiftSelect.appendChild(option);
        return;
    }
    
    shifts.forEach(shift => {
        const option = document.createElement('option');
        option.value = shift.id;
        option.textContent = `${shift.shift_name} (${formatTimeForDisplay(shift.start_time)} - ${formatTimeForDisplay(shift.end_time)})`;
        
        // Store start/end times as data attributes for auto-fill
        option.dataset.startTime = shift.start_time;
        option.dataset.endTime = shift.end_time;
        
        shiftSelect.appendChild(option);
    });
    
    // Add event listener for shift selection to auto-fill times
    shiftSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.startTime && selectedOption.dataset.endTime) {
            document.getElementById('entry-shift-start').value = formatTimeForInput(selectedOption.dataset.startTime);
            document.getElementById('entry-shift-end').value = formatTimeForInput(selectedOption.dataset.endTime);
        }
    });
}

async function fetchUserSocieties() {
    try {
        const response = await fetch('actions/attendance_controller.php?action=get_user_societies');
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            window.allSocieties = result.data; // Store globally
            populateEntrySocieties(result.data); // Populate dropdown in modal
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error fetching societies:', error);
    }
}

async function fetchAttendanceAuditLog(entryId) {
    try {
        const response = await fetch(`actions/attendance_controller.php?action=get_attendance_audit_log&id=${entryId}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error fetching audit log:', error);
        throw error;
    }
}

// --- UI Update Functions ---
function showLoader() {
    const loader = document.getElementById('loader');
        const tableContainer = document.getElementById('attendance-table-container');
    if (loader) loader.classList.remove('hidden');
    if (tableContainer) tableContainer.classList.add('hidden');
}

function hideLoader() {
    const loader = document.getElementById('loader');
    const tableContainer = document.getElementById('attendance-table-container');
    if (loader) loader.classList.add('hidden');
    if (tableContainer) tableContainer.classList.remove('hidden');
}

function toggleInstructions() {
    const panel = document.getElementById('attendance-instructions');
    const buttons = document.querySelectorAll('#show-instructions-btn, #show-instructions-btn-top');
    if (panel) {
        const isHidden = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !isHidden);
        buttons.forEach(btn => btn.setAttribute('aria-expanded', isHidden));
    }
}

function toggleLegend() {
    const legendPanel = document.getElementById('legend');
    const legendButton = document.getElementById('show-legend-btn');
    if (legendPanel) {
        const isHidden = legendPanel.classList.contains('hidden');
        legendPanel.classList.toggle('hidden', !isHidden);
        if (legendButton) {
            legendButton.setAttribute('aria-expanded', isHidden);
        }
    }
}

// --- Legend Population (Assuming this function exists or is similar) ---
function populateLegend(codes) {
    const legendItemsContainer = document.getElementById('legend-items');
    if (!legendItemsContainer) return;

    legendItemsContainer.innerHTML = '';
    codes.forEach(code => {
        // Assuming a simple mapping or using code.color if available
        // Fallback to generic badge classes if needed
        const badgeClass = getBadgeClassForCode(code.code);
        // Extract color from badge class or use a predefined map
        const colorMap = {
            'present': '#27ae60',
            'absent': '#e74c3c',
            'leave': '#f1c40f',
            'holiday': '#9b59b6',
            'weekend': '#3498db',
            'default': '#333'
        };
        const bgColor = colorMap[badgeClass] || colorMap['default'];

        const item = document.createElement('div');
        item.className = 'legend-item';
        item.innerHTML = `
            <div class="legend-color" style="background-color: ${bgColor};"></div>
            <span>${escapeHtml(code.code)} - ${escapeHtml(code.name)}</span>
        `;
        legendItemsContainer.appendChild(item);
    });
}

// --- Modal Population ---
function populateEntryCodes(codes) {
    const codeSelect = document.getElementById('entry-code');
    if (!codeSelect) return;

    codeSelect.innerHTML = '<option value="">Select Status</option>';
    codes.forEach(code => {
        const option = document.createElement('option');
        option.value = code.id; // Use ID for submission
        option.textContent = `${code.code} - ${code.name}`;
        option.dataset.code = code.code;
        option.dataset.requiresSociety = code.require_society; // Store requirement
        codeSelect.appendChild(option);
    });
}

function populateEntrySocieties(societiesList) {
    const societySelect = document.getElementById('entry-society');
    if (!societySelect) return;

    societySelect.innerHTML = '<option value="">Select Society</option>';
    societiesList.forEach(society => {
        const option = document.createElement('option');
        option.value = society.id;
        let displayName = society.society_name;
        if (society.pin_code) {
            displayName += ` (${society.pin_code})`;
        }
        option.textContent = displayName;
        societySelect.appendChild(option);
    });
}

// --- Main Table Rendering ---
function renderAttendanceTable() {
    if (!attendanceData) {
        console.warn("No attendance data to render.");
        return;
    }
    
    const tableContainerInner = document.querySelector('#attendance-table-container');
    if (!tableContainerInner) {
        console.error("Table container inner div not found.");
        return;
    }

    // --- Filter Users ---
    let displayedUsers = [...attendanceData.users];
    if (searchQuery) {
        const query = searchQuery.toLowerCase();
        displayedUsers = displayedUsers.filter(user =>
            user.name.toLowerCase().includes(query) ||
            user.user_type.toLowerCase().includes(query) ||
            (user.email && user.email.toLowerCase().includes(query)) ||
            user.id.toString().includes(query)
        );
    }
    filteredUsers = displayedUsers; // Update global filtered list

    // --- Pagination ---
    const totalUsers = filteredUsers.length;
    const totalPages = Math.ceil(totalUsers / pageSize);
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, totalUsers);
    const usersToDisplay = filteredUsers.slice(startIndex, endIndex);
    
    // --- Update Pagination Controls UI ---
    updatePaginationControls(totalUsers, totalPages);

    // --- Render Table ---
    // Clear previous table content except indicators
    tableContainerInner.innerHTML = `
        <div class="horizontal-scroll-indicator-left absolute top-0 bottom-0 left-0 w-4 bg-gradient-to-r from-gray-800 to-transparent pointer-events-none z-10 opacity-0 transition-opacity duration-300"></div>
        <div class="horizontal-scroll-indicator-right absolute top-0 bottom-0 right-0 w-4 bg-gradient-to-l from-gray-800 to-transparent pointer-events-none z-10 opacity-0 transition-opacity duration-300"></div>
        <div class="overflow-x-auto"> <!-- Wrapper for horizontal scrolling only -->
        </div>
    `;
    const scrollWrapper = tableContainerInner.querySelector('.overflow-x-auto');

    const table = document.createElement('table');
    table.id = 'attendance-table';
    table.className = 'w-full text-left border-collapse';

    // --- Table Header ---
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');

    // Employee Header
    const employeeHeader = document.createElement('th');
    employeeHeader.className = 'p-2 bg-gray-700 text-white sticky left-0 z-20 border border-gray-600';
    employeeHeader.style.minWidth = '200px';
    employeeHeader.style.width = '200px';
    employeeHeader.style.maxWidth = '200px';
    employeeHeader.textContent = 'EMPLOYEE';
    headerRow.appendChild(employeeHeader);

    // Date Headers
    attendanceData.dates.forEach(dateStr => {
        const dateHeader = document.createElement('th');
        dateHeader.className = 'p-2 bg-gray-700 text-white border border-gray-600';
        dateHeader.style.minWidth = '80px';
        dateHeader.style.width = '80px';
        const date = new Date(dateStr);
        const day = date.getDate();
        const dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()];
        
        // Create a container for date and day
        const dateContainer = document.createElement('div');
        dateContainer.className = 'flex flex-col items-center';
        
        // Add date
        const dateElement = document.createElement('div');
        dateElement.textContent = day;
        dateContainer.appendChild(dateElement);
        
        // Add day name
        const dayElement = document.createElement('div');
        dayElement.textContent = dayName;
        dayElement.className = 'text-xs opacity-80';
        dateContainer.appendChild(dayElement);
        
        dateHeader.appendChild(dateContainer);

        // Highlight Holidays and Weekends
        if (attendanceData.holidays && attendanceData.holidays[dateStr]) {
            dateHeader.classList.add('bg-purple-900');
            dateHeader.title = `Holiday: ${attendanceData.holidays[dateStr]}`;
        } else {
            const dayOfWeek = date.getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) { // Sunday or Saturday
                dateHeader.classList.add('bg-blue-900');
                dateHeader.title = 'Weekend';
            }
        }

        headerRow.appendChild(dateHeader);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    // --- Table Body ---
    const tbody = document.createElement('tbody');

    if (usersToDisplay.length === 0) {
        const emptyRow = document.createElement('tr');
        const emptyCell = document.createElement('td');
        emptyCell.colSpan = attendanceData.dates.length + 1; // +1 for employee column
        emptyCell.className = 'p-4 text-center text-gray-500';
        emptyCell.textContent = 'No employees found.';
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
    } else {
        usersToDisplay.forEach((user, index) => {
            const row = document.createElement('tr');
            // Uniform row styling - no alternating colors
            row.className = 'border-b border-gray-600';

            // --- Employee Cell ---
            const employeeCell = document.createElement('td');
            employeeCell.className = 'p-2 sticky left-0 z-20 border border-gray-600';
            employeeCell.style.minWidth = '200px';
            employeeCell.style.width = '200px';
            employeeCell.style.maxWidth = '200px';
            const initials = getUserInitials(user.name);
            const avatarColor = getAvatarColor(user.id);
            employeeCell.innerHTML = `
                <div class="employee-info">
                    <div class="avatar" style="background-color: ${avatarColor};">${initials}</div>
                    <div class="details">
                        <div class="name">${escapeHtml(user.name)}</div>
                        <div class="role">${escapeHtml(user.user_type)}</div>
                    </div>
                    </div>
            `;
            row.appendChild(employeeCell);

            // --- Attendance Cells ---
            attendanceData.dates.forEach(dateStr => {
                const dateCell = document.createElement('td');
                dateCell.className = 'p-1 text-center align-middle border border-gray-600';
                dateCell.style.minWidth = '80px';
                dateCell.style.width = '80px';

                // Get entries for this specific user and date
                // Data structure: attendance[dateStr][userId] = [entry1, entry2, ...]
                const entries = (attendanceData.attendance[dateStr] && attendanceData.attendance[dateStr][user.id])
                    ? attendanceData.attendance[dateStr][user.id] : [];

                // Create container for attendance badges and add button
                const container = document.createElement('div');
                container.className = 'attendance-badge-container';
                
                if (entries.length > 0) {
                    // Create badges container for existing entries
                    const badgesContainer = document.createElement('div');
                    badgesContainer.className = 'space-y-1 mb-2';
                    
                    // Multiple entries possible for multi-society
                    entries.forEach((entry, index) => {
                        const entryWrapper = document.createElement('div');
                        entryWrapper.className = 'flex items-center justify-between mb-1';
                        
                        // Create badge
                        const entryDiv = document.createElement('div');
                        entryDiv.className = `attendance-badge ${getBadgeClassForCode(entry.attendance_code)}`;
                        
                        // Remove default title attribute - we'll use custom tooltip
                        entryDiv.innerHTML = `${getBadgeTextForCode(entry.attendance_code)}`;
                        entryDiv.dataset.entryId = entry.id;
                        
                        // Get society name for tooltip
                        const societyName = findSocietyName(entry.society_id);
                        
                        // Add enhanced tooltip event listeners
                        entryDiv.addEventListener('mouseenter', () => {
                            showAttendanceTooltip(entryDiv, entry, societyName);
                        });
                        
                        entryDiv.addEventListener('mouseleave', () => {
                            hideAttendanceTooltip();
                        });
                        
                        // Keep click functionality
                        const enrichedEntry = Object.assign({}, entry, { user_id: user.id, attendance_date: dateStr });
                        entryDiv.addEventListener('click', () => {
                            hideAttendanceTooltip(); // Hide tooltip when clicking
                            openEditEntryModal(enrichedEntry);
                        });
                        entryWrapper.appendChild(entryDiv);
                        
                        // Add history icon
                        const historyIcon = document.createElement('button');
                        historyIcon.type = 'button';
                        historyIcon.className = 'text-gray-400 hover:text-white text-xs ml-1';
                        historyIcon.innerHTML = '<i class="fas fa-history"></i>';
                        historyIcon.title = 'View History';
                        historyIcon.dataset.entryId = entry.id;
                        historyIcon.addEventListener('click', (e) => {
                            e.stopPropagation(); // Prevent triggering row click if any
                            openAuditModal(entry.id);
                        });
                        entryWrapper.appendChild(historyIcon);
                        
                        badgesContainer.appendChild(entryWrapper);
                    });
                    
                    container.appendChild(badgesContainer);
                }
                
                // Always add the "Add Entry" button 
                const addButton = document.createElement('button');
                addButton.type = 'button';
                addButton.className = 'add-entry-btn';
                addButton.innerHTML = '<i class="fas fa-plus" aria-hidden="true"></i>';
                addButton.title = 'Add Entry';
                addButton.dataset.userId = user.id;
                addButton.dataset.date = dateStr;
                addButton.addEventListener('click', () => openAddEntryModal(user.id, dateStr));
                container.appendChild(addButton);
                
                dateCell.appendChild(container);

                row.appendChild(dateCell);
            });

            tbody.appendChild(row);
        });
    }

    table.appendChild(tbody);
    scrollWrapper.appendChild(table); // Append table to the scrollable wrapper
    checkScrollIndicators(); // Check after table is rendered
}

function updatePaginationControls(totalUsers, totalPages) {
    const prevButton = document.getElementById('prev-page');
    const nextButton = document.getElementById('next-page');
    const currentPageSpan = document.getElementById('current-page');
    const paginationInfo = document.getElementById('pagination-info');

    if (prevButton) prevButton.disabled = (currentPage <= 1);
    if (nextButton) nextButton.disabled = (currentPage >= totalPages || totalPages === 0);
    if (currentPageSpan) currentPageSpan.textContent = totalPages === 0 ? 0 : currentPage;

    const startEntry = totalUsers === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const endEntry = Math.min(currentPage * pageSize, totalUsers);
    if (paginationInfo) {
        paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalUsers} entries`;
    }
}

// --- Filter and Search ---
function applyFilters() {
    loadAttendanceData(); // This will read current filter values and reload data
}

function resetFilters() {
    const form = document.getElementById('filter-form');
    if (form) {
        form.reset();
        // Reset search
        const searchInput = document.getElementById('employee-search');
        if (searchInput) searchInput.value = '';
        searchQuery = '';
        currentPage = 1;
        loadAttendanceData();
    }
}

// --- Modal Functions ---
function openAddEntryModal(userId, date) {
    const modal = document.getElementById('attendance-entry-modal');
    const title = document.getElementById('entry-modal-title');
    const form = document.getElementById('attendance-entry-form');

    if (modal && title && form) {
        title.textContent = 'Add Attendance Entry';
        form.reset();
        document.getElementById('entry-user-id').value = userId;
        document.getElementById('entry-date').value = date;
        document.getElementById('entry-id').value = ''; // Clear for new entry

        // Fetch shifts for the dropdown
        fetchShifts();
        
        // Ensure societies are populated in the modal
        if (!window.allSocieties || window.allSocieties.length === 0) {
            fetchUserSocieties();
        } else {
            populateEntrySocieties(window.allSocieties);
        }

        // Pre-select society if contextually known
        // Note: For add modal, we don't pre-select society as it's a new entry

        // Pre-select society if contextually known or only one option (optional)
        // if (window.allSocieties && window.allSocieties.length === 1) {
        //     document.getElementById('entry-society').value = window.allSocieties[0].id;
        // }

        // Save context for fallback when submitting
        lastEntryContext = { userId, date };
        modal.classList.add('show');
    }
}

function openEditEntryModal(entry) {
    const modal = document.getElementById('attendance-entry-modal');
    const title = document.getElementById('entry-modal-title');
    const form = document.getElementById('attendance-entry-form');

    if (modal && title && form && entry) {
        title.textContent = 'Edit Attendance Entry';
        form.reset();
        // Ensure user/date are present and persist as context
        const modalUserId = entry.user_id || entry.userId || (entry.user ? entry.user.id : null);
        const modalDate = entry.date || entry.attendance_date || entry.attendanceDate || null;
        document.getElementById('entry-user-id').value = modalUserId || '';
        document.getElementById('entry-date').value = modalDate || '';
        lastEntryContext = { userId: modalUserId, date: modalDate };
        document.getElementById('entry-id').value = entry.id || '';

        document.getElementById('entry-society').value = entry.society_id || '';
        // Find the option by code ID (attendance_master_id) or code string
        const codeSelect = document.getElementById('entry-code');
        if (codeSelect) {
            const codeOption = Array.from(codeSelect.options).find(opt => opt.value == entry.attendance_master_id);
            if (codeOption) {
                codeSelect.value = codeOption.value;
        } else {
                codeSelect.value = ''; // Deselect if not found
            }
        }

        // Ensure societies are populated in the modal
        if (!window.allSocieties || window.allSocieties.length === 0) {
            fetchUserSocieties().then(() => {
                // Set society value after dropdown is populated
                setTimeout(() => {
                    document.getElementById('entry-society').value = entry.society_id || '';
                }, 100);
            });
        } else {
            populateEntrySocieties(window.allSocieties);
            // Set society value after dropdown is populated
            setTimeout(() => {
                document.getElementById('entry-society').value = entry.society_id || '';
            }, 100);
        }

        // Fetch shifts before setting values
        fetchShifts().then(() => {
            // Set shift values after fetching
            const shiftSelect = document.getElementById('entry-shift');
            if (entry.shift_id && shiftSelect) {
                // Try to select the shift by ID
                shiftSelect.value = entry.shift_id;

                // If the selection failed (value not found), try again with a delay
                // This handles race conditions with DOM updates
                if (shiftSelect.value !== entry.shift_id) {
                    setTimeout(() => {
                        shiftSelect.value = entry.shift_id;

                        // Trigger change event to update times
                        shiftSelect.dispatchEvent(new Event('change'));
                    }, 100);
                } else {
                    // Trigger change event to update times
                    shiftSelect.dispatchEvent(new Event('change'));
                }
        } else {
                // If no shift ID or shift select not found, set the times directly
                document.getElementById('entry-shift-start').value = entry.shift_start ? formatTimeForInput(entry.shift_start) : '';
                document.getElementById('entry-shift-end').value = entry.shift_end ? formatTimeForInput(entry.shift_end) : '';
            }
        });

        modal.classList.add('show');
    }
}

function closeEntryModal() {
    const modal = document.getElementById('attendance-entry-modal');
    const form = document.getElementById('attendance-entry-form');
    if (modal) {
        // Add fade-out animation
        modal.style.opacity = '0';
        const dialog = modal.querySelector('.modal-dialog');
        if (dialog) {
            dialog.style.transform = 'translateY(-20px)';
        }
        // Wait for animation to complete before hiding
        setTimeout(() => {
            modal.classList.remove('show');
            modal.style.opacity = '';
            if (dialog) {
                dialog.style.transform = '';
            }
    // Reset form fields
            if (form) form.reset();
    document.getElementById('entry-id').value = '';
    document.getElementById('entry-user-id').value = '';
    document.getElementById('entry-date').value = '';
        }, 200); // Match CSS transition duration
    }
}

/**
 * Save attendance entry from the modal
 */
async function saveAttendanceEntry() {
    // Get form values
    let userId = document.getElementById('entry-user-id').value;
    let date = document.getElementById('entry-date').value;
    const entryId = document.getElementById('entry-id').value;
    const codeId = document.getElementById('entry-code').value;
    const societyId = document.getElementById('entry-society').value;
    const shiftId = document.getElementById('entry-shift').value;
    const shiftStart = document.getElementById('entry-shift-start').value;
    const shiftEnd = document.getElementById('entry-shift-end').value;
    const reason = document.getElementById('entry-reason').value || "Attendance entry via UI"; // Default reason
    
    // Enhanced logging for debugging
    console.log('Attendance Entry Submission Details:', {
        userId,
        date,
        entryId,
        codeId,
        societyId,
        shiftId,
        shiftStart,
        shiftEnd,
        reason
    });
    
    // Fallback to last context if hidden inputs are empty
    if ((!userId || !date) && lastEntryContext) {
        if (!userId) userId = lastEntryContext.userId;
        if (!date) date = lastEntryContext.date;
        document.getElementById('entry-user-id').value = userId || '';
        document.getElementById('entry-date').value = date || '';
    }

    // Validate required fields
    if (!codeId) {
        alert('Please select Attendance Status.');
        return;
    }

    if (!userId || !date) {
        alert('Missing user or date for attendance entry. Please try again.');
        return;
    }
    
    // Get selected code option to check society requirement
    const codeSelect = document.getElementById('entry-code');
    const selectedOption = codeSelect.options[codeSelect.selectedIndex];
    const requiresSociety = selectedOption && selectedOption.dataset.requiresSociety == '1';

    if (requiresSociety && !societyId) {
        alert('Society is required for this attendance status.');
        return;
    }
    
    // Basic shift time validation (with overnight shift handling)
    if (shiftStart && shiftEnd) {
        const startMinutes = timeToMinutes(shiftStart);
        const endMinutes = timeToMinutes(shiftEnd);
        
        // If not an overnight shift and end time is before or same as start time
        if (!isOvernightShift(shiftStart, shiftEnd) && endMinutes <= startMinutes) {
            alert('Shift end time must be after shift start time.');
            return;
        }
    }

    // Check for duplicate shift for same user on same day
    if (shiftId && !entryId) { // Only check for new entries
        const duplicateShift = checkForDuplicateShift(userId, date, shiftId);
        if (duplicateShift) {
            alert(`This user already has this shift assigned on this day.`);
            return;
        }
    }

    // Get current user ID
    const currentUserId = getUserId();
    
    // Normalize numeric fields without losing presence when parsing fails
    const toNumberIfValid = (val) => {
        if (val === '' || val === null || val === undefined) return null;
        const n = parseInt(val, 10);
        return isNaN(n) ? val : n;
    };

    // Create record object
    const record = {
        user_id: toNumberIfValid(userId),
        date: date,
        code: toNumberIfValid(codeId),
        society_id: toNumberIfValid(societyId),
        shift_id: toNumberIfValid(shiftId),
        shift_start: shiftStart || null,
        shift_end: shiftEnd || null,
        reason: reason,
        marked_by: currentUserId,
        last_modified_by: currentUserId
    };

    if (entryId) {
        record.id = parseInt(entryId);
    }
    
    // Check for time conflicts if this is a "Present" type of attendance with shift times
    if (requiresSociety && shiftStart && shiftEnd) {
        const timeConflict = checkTimeConflict(userId, date, shiftStart, shiftEnd, entryId ? parseInt(entryId) : null, societyId ? parseInt(societyId) : null);
        if (timeConflict) {
            console.error('Time Conflict Detected:', timeConflict);
            alert(`Time conflict detected: ${timeConflict.message}\n\nA guard cannot be present at multiple locations during the same time period.`);
            return;
        }
    }
    
    showLoader();
    try {
        const response = await fetch('actions/attendance_controller.php?action=bulk_update_attendance', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                changes: [record],
                reason: reason || 'Updated via attendance UI',
                marked_by: currentUserId
            })
        });

        let data;
        let errorText = '';
        if (!response.ok) {
            // Try to parse error message from backend
            try {
                const text = await response.text();
                errorText = text;
                data = JSON.parse(text);
            } catch (e) {
                // Not JSON, just use text
                data = null;
            }
            let msg = `Failed to save attendance entry: HTTP error! status: ${response.status}`;
            if (data && data.message) {
                msg = data.message;
            } else if (errorText) {
                msg += `\n${errorText}`;
            }
            console.error('Submission Error:', {
                status: response.status,
                errorText,
                data
            });
            throw new Error(msg);
        } else {
            data = await response.json();
        }

        if (data.success) {
            closeEntryModal();
            // Try optimized data loading with force refresh first, fallback to regular if it fails
            try {
                await loadAttendanceDataOptimized(true); // Force refresh to bypass cache
            } catch (loadError) {
                console.warn('Optimized loading failed, trying fallback:', loadError);
                await loadAttendanceData();
            }
            alert('Attendance entry saved successfully.');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error saving attendance:', error);
        alert(error.message || `Failed to save attendance entry: ${error}`);
    } finally {
        hideLoader();
    }
}

/**
 * Check for duplicate shift assignment for a user on a specific date
 */
function checkForDuplicateShift(userId, date, shiftId) {
    if (!attendanceData || !attendanceData.attendance || !attendanceData.attendance[date]) {
        return false;
    }
    
    const userEntries = attendanceData.attendance[date][userId] || [];
    
    return userEntries.some(entry => entry.shift_id == shiftId);
}

function openAuditModal(attendanceId) {
    const modal = document.getElementById('audit-modal');
    const content = document.getElementById('audit-content');

    if (!modal || !content) return;

    content.innerHTML = '<p>Loading history...</p>';
    // Show modal with animation (assuming 'show' class handles it)
    modal.classList.add('show');

    fetchAttendanceAuditLog(attendanceId)
        .then(logEntries => {
            if (!logEntries || logEntries.length === 0) {
                content.innerHTML = '<p>No history found for this entry.</p>';
                        return;
                    }
                    
            let html = '<ul class="space-y-3">';
            logEntries.forEach(entry => {
                const timestamp = new Date(entry.change_timestamp).toLocaleString();
                const changedByName = entry.changed_by_first_name && entry.changed_by_surname ?
                    `${entry.changed_by_first_name} ${entry.changed_by_surname}` : 'Unknown User';

                let changeDescription = '';
                if (entry.is_shift_change) {
                     changeDescription = `Shift changed from ${entry.old_shift_start || 'N/A'}-${entry.old_shift_end || 'N/A'} to ${entry.new_shift_start || 'N/A'}-${entry.new_shift_end || 'N/A'}.`;
                } else if (entry.old_code && entry.new_code) {
                    changeDescription = `Status changed from <strong>${entry.old_code}</strong> to <strong>${entry.new_code}</strong>.`;
                } else if (entry.new_code) {
                    changeDescription = `Status set to <strong>${entry.new_code}</strong>.`;
                }

                html += `
                    <li class="border-b border-gray-700 pb-3">
                        <div class="font-medium">${timestamp}</div>
                        <div class="text-sm text-gray-400">Changed by: ${escapeHtml(changedByName)} (${entry.source || 'Unknown Source'})</div>
                        <div class="mt-1">${changeDescription}</div>
                        ${entry.reason_for_change ? `<div class="text-sm italic mt-1">Reason: ${escapeHtml(entry.reason_for_change)}</div>` : ''}
                    </li>
                `;
            });
            html += '</ul>';
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching audit log:', error);
            content.innerHTML = `<p>Error loading history: ${error.message}</p>`;
        });
}

// --- Export ---
function exportToExcel(event) {
    event.preventDefault();
    const params = new URLSearchParams({
        action: 'export_attendance_excel',
        month: document.getElementById('month-filter')?.value || new Date().getMonth() + 1,
        year: document.getElementById('year-filter')?.value || new Date().getFullYear(),
        department: document.getElementById('department-filter')?.value || '',
        society_id: document.getElementById('society-filter')?.value || ''
    });

    // Create a temporary link to trigger download
    const url = `actions/attendance_controller.php?${params.toString()}`;
    const link = document.createElement('a');
    link.href = url;
    link.download = `Attendance_Report_${document.getElementById('month-filter')?.options[document.getElementById('month-filter')?.selectedIndex]?.text || 'Month'}_${document.getElementById('year-filter')?.value || 'Year'}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// --- Helper Functions (Badge/Color) ---
function getBadgeClassForCode(code) {
    switch (code?.toUpperCase()) {
        case 'P':
            return 'present';
        case 'PR':
            return 'partial';
        case 'A':
            return 'absent';
        case 'L':
            return 'absent'; // Leave - same styling as absent
        case 'PL':
            return 'pto'; // Paid Leave - same styling as PTO
        case 'HL':
            return 'holiday'; // Holiday
        case 'H':
            return 'holiday';
        case 'W':
            return 'weekend';
        case 'WFH':
            return 'wfh';
        case 'PTO':
            return 'pto';
        case 'SL':
            return 'sick';
        default:
            return 'default';
    }
}

function getBadgeTextForCode(code) {
    const icons = {
        'P': '<i class="fas fa-check-circle mr-1"></i>',
        'PR': '<i class="fas fa-check-circle mr-1"></i>',
        'A': '<i class="fas fa-times-circle mr-1"></i>',
        'L': '<i class="fas fa-calendar-times mr-1"></i>',
        'PL': '<i class="fas fa-umbrella-beach mr-1"></i>',
        'HL': '<i class="fas fa-calendar-day mr-1"></i>',
        'H': '<i class="fas fa-calendar-day mr-1"></i>',
        'W': '<i class="fas fa-calendar-week mr-1"></i>',
        'WFH': '<i class="fas fa-home mr-1"></i>',
        'PTO': '<i class="fas fa-umbrella-beach mr-1"></i>',
        'SL': '<i class="fas fa-heartbeat mr-1"></i>'
    };
    
    const upperCode = code?.toUpperCase() || '';
    const icon = icons[upperCode] || '<i class="fas fa-info-circle mr-1"></i>';
    
    return `${icon}${code}`;
}

// --- Enhanced Tooltip System ---
let tooltipTimeout = null;
let currentTooltip = null;

// Global event listeners for tooltip management
document.addEventListener('scroll', hideAttendanceTooltip, true);
document.addEventListener('click', (e) => {
    // Hide tooltip when clicking outside of attendance badges
    if (!e.target.closest('.attendance-badge')) {
        hideAttendanceTooltip();
    }
});
document.addEventListener('keydown', (e) => {
    // Hide tooltip on escape key
    if (e.key === 'Escape') {
        hideAttendanceTooltip();
    }
});

function showAttendanceTooltip(element, entry, societyName) {
    // Clear any existing tooltip
    hideAttendanceTooltip();
    
    // Add delay for better UX
    tooltipTimeout = setTimeout(() => {
        const tooltip = document.getElementById('custom-tooltip');
        if (!tooltip) return;
        
        // Get status info
        const statusInfo = getStatusInfo(entry.attendance_code);
        
        // Check if this is a non-working status (no time tracking needed)
        const nonWorkingStatuses = ['A', 'L', 'PL', 'HL', 'H', 'W', 'PTO', 'SL'];
        const isNonWorking = nonWorkingStatuses.includes(entry.attendance_code?.toUpperCase());
        
        // Calculate total hours only for working statuses
        let totalHours = 'N/A';
        let hoursClass = '';
        if (!isNonWorking && entry.shift_start && entry.shift_end) {
            const startTime = new Date(`2000-01-01 ${entry.shift_start}`);
            const endTime = new Date(`2000-01-01 ${entry.shift_end}`);
            
            // Handle overnight shifts
            if (endTime < startTime) {
                endTime.setDate(endTime.getDate() + 1);
            }
            
            const diffMs = endTime - startTime;
            const hours = Math.floor(diffMs / (1000 * 60 * 60));
            const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            if (hours > 0 || minutes > 0) {
                totalHours = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
                hoursClass = hours >= 7 ? 'text-green-400' : hours >= 4 ? 'text-yellow-400' : 'text-red-400';
            }
        }
        
        // Build tooltip HTML based on status type
        let tooltipBodyContent = '';
        
        if (isNonWorking) {
            // For non-working statuses, only show location and status description
            tooltipBodyContent = `
                <div class="tooltip-detail-row">
                    <div class="tooltip-detail-label">
                        <i class="fas fa-building"></i>
                        Location
                    </div>
                    <div class="tooltip-detail-value">${societyName || 'N/A'}</div>
                </div>
                <div class="tooltip-status-description">
                    <i class="fas fa-info-circle mr-2"></i>
                    ${getStatusDescription(entry.attendance_code)}
                </div>
                ${entry.location_details ? `
                <div class="tooltip-location">
                    <i class="fas fa-map-marker-alt mr-2"></i>${entry.location_details}
                </div>
                ` : ''}
            `;
        } else {
            // For working statuses, show full time details
            tooltipBodyContent = `
                <div class="tooltip-detail-row">
                    <div class="tooltip-detail-label">
                        <i class="fas fa-building"></i>
                        Location
                    </div>
                    <div class="tooltip-detail-value">${societyName || 'N/A'}</div>
                </div>
                <div class="tooltip-detail-row">
                    <div class="tooltip-detail-label">
                        <i class="fas fa-sign-in-alt"></i>
                        In Time
                    </div>
                    <div class="tooltip-detail-value">${entry.shift_start ? formatTimeForDisplay(entry.shift_start) : 'N/A'}</div>
                </div>
                <div class="tooltip-detail-row">
                    <div class="tooltip-detail-label">
                        <i class="fas fa-sign-out-alt"></i>
                        Out Time
                    </div>
                    <div class="tooltip-detail-value">${entry.shift_end ? formatTimeForDisplay(entry.shift_end) : 'N/A'}</div>
                </div>
                ${totalHours !== 'N/A' ? `
                <div class="tooltip-total-hours ${hoursClass}">
                    <i class="fas fa-clock mr-2"></i>Total Hours: ${totalHours}
                </div>
                ` : ''}
                ${entry.location_details ? `
                <div class="tooltip-location">
                    <i class="fas fa-map-marker-alt mr-2"></i>${entry.location_details}
                </div>
                ` : ''}
            `;
        }
        
        const tooltipHTML = `
            <div class="tooltip-header">
                <div class="tooltip-status-indicator ${statusInfo.class}"></div>
                <div class="tooltip-status-text">${statusInfo.text}</div>
            </div>
            <div class="tooltip-body">
                ${tooltipBodyContent}
            </div>
        `;
        
        tooltip.innerHTML = tooltipHTML;
        tooltip.classList.add('show');
        
        // Position tooltip
        positionTooltip(tooltip, element);
        currentTooltip = tooltip;
        
    }, 200); // 200ms delay
}

function hideAttendanceTooltip() {
    if (tooltipTimeout) {
        clearTimeout(tooltipTimeout);
        tooltipTimeout = null;
    }
    
    if (currentTooltip) {
        currentTooltip.classList.remove('show');
        setTimeout(() => {
            if (currentTooltip && !currentTooltip.classList.contains('show')) {
                currentTooltip.innerHTML = '';
            }
        }, 300); // Wait for transition
        currentTooltip = null;
    }
}

function positionTooltip(tooltip, element) {
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate initial position (centered above element)
    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
    let top = rect.top - tooltipRect.height - 15;
    
    // Adjust horizontal position if tooltip goes off-screen
    if (left < 10) {
        left = 10;
    } else if (left + tooltipRect.width > viewportWidth - 10) {
        left = viewportWidth - tooltipRect.width - 10;
    }
    
    // Adjust vertical position if tooltip goes above viewport
    if (top < 10) {
        top = rect.bottom + 15; // Show below element instead
        
        // If still doesn't fit below, position at top of viewport
        if (top + tooltipRect.height > viewportHeight - 10) {
            top = 10;
        }
    }
    
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

function getStatusInfo(code) {
    const statusMap = {
        'P': { text: 'Present', class: 'present' },
        'PR': { text: 'Partial', class: 'partial' },
        'A': { text: 'Absent', class: 'absent' },
        'L': { text: 'Leave', class: 'absent' },
        'PL': { text: 'Paid Leave', class: 'pto' },
        'HL': { text: 'Holiday', class: 'holiday' },
        'H': { text: 'Holiday', class: 'holiday' },
        'W': { text: 'Weekend', class: 'weekend' },
        'WFH': { text: 'Work From Home', class: 'wfh' },
        'PTO': { text: 'Paid Time Off', class: 'pto' },
        'SL': { text: 'Sick Leave', class: 'sick' }
    };
    
    return statusMap[code?.toUpperCase()] || { text: code || 'Unknown', class: 'default' };
}

function getStatusDescription(code) {
    const descriptions = {
        'A': 'Employee was not present at work',
        'L': 'Employee is on leave',
        'PL': 'Employee is on approved paid leave',
        'HL': 'Official holiday - no work required',
        'H': 'Official holiday - no work required',
        'W': 'Weekend - regular day off',
        'PTO': 'Approved paid time off',
        'SL': 'Medical leave - sick day'
    };
    
    return descriptions[code?.toUpperCase()] || 'Status information';
}

// --- Existing Functions (kept or slightly modified) ---
function hideElement(id) {
    const element = document.getElementById(id);
    if (element) {
        // Handle modal animations if class 'modal' is present
        if (element.classList.contains('modal')) {
            element.style.opacity = '0';
            const dialog = element.querySelector('.modal-dialog');
            if (dialog) {
                dialog.style.transform = 'translateY(-20px)';
            }
            setTimeout(() => {
                element.classList.remove('show');
                element.style.opacity = '';
                if (dialog) {
                    dialog.style.transform = '';
                }
            }, 200); // Match CSS transition duration
        } else {
            element.classList.remove('show'); // Fallback for non-modals
        }
    }
}

// Save attendance changes (bulk save for changed cells - if still used)
async function saveAttendanceChanges() {
    if (changedCells.length === 0) {
        alert('No changes to save.');
        return;
    }
    
        const saveBtn = document.getElementById('save-attendance-btn');
    const originalBtnText = saveBtn ? saveBtn.innerHTML : '';
    try {
        if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveBtn.disabled = true;
        }
        
        const currentUserId = getUserId(); // Assume this function exists
        
        const response = await fetch('actions/attendance_controller.php?action=bulk_update_attendance', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                changes: changedCells,
                reason: 'Bulk update from attendance management',
                marked_by: currentUserId
            })
        });
        
        // Check content type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Non-JSON response:', await response.text());
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();

        if (data.success) {
            alert(`Changes saved successfully. ${data.updated || 0} records updated.`);
            changedCells = []; // Clear local changes
            if (saveBtn) saveBtn.classList.add('hidden'); // Hide save button
            await loadAttendanceData(); // Reload data from server
        } else {
            console.error('Error saving changes:', data);
            alert(`Error saving changes: ${data.message || 'Unknown error'}`);
        }
    } catch (error) {
        console.error('Error in saveAttendanceChanges:', error);
        alert(`Failed to save changes: ${error.message || 'Unknown error'}`);
    } finally {
        if (saveBtn) {
            saveBtn.innerHTML = originalBtnText;
            saveBtn.disabled = false;
        }
    }
}

function getUserId() {
    // Try to get user ID from the page
    const userIdElement = document.getElementById('current-user-id');
    if (userIdElement && userIdElement.value) {
        return parseInt(userIdElement.value);
    }
    // Add other methods (cookie, JWT) if necessary
    return 1; // Default fallback
}

// Ensure the modal close buttons also call the animated close
document.addEventListener('click', function(e) {
    if (e.target.closest('.close') && e.target.closest('.modal')) {
        const modalId = e.target.closest('.modal').id;
        if (modalId === 'attendance-entry-modal') {
            closeEntryModal();
        } else {
            hideElement(modalId);
        }
        e.preventDefault(); // Prevent default if it's a link/button
    }
});

// Helper function to format time (HH:MM:SS -> HH:MM)
function formatTime(timeStr) {
    if (!timeStr) return '';
    return timeStr.substring(0, 5); // Return HH:MM
}

// Helper function to get status color
function getStatusColor(statusCode) {
    const colors = {
        'P': 'rgba(34, 197, 94, 0.2)',  // Green for Present
        'A': 'rgba(239, 68, 68, 0.2)',  // Red for Absent
        'L': 'rgba(234, 179, 8, 0.2)',  // Yellow for Leave
        'H': 'rgba(99, 102, 241, 0.2)'  // Indigo for Holiday
    };
    return colors[statusCode] || 'rgba(75, 85, 99, 0.2)'; // Default gray
}

/**
 * Check for time conflicts with existing attendance entries
 * @param {number} userId - User ID
 * @param {string} date - Date in YYYY-MM-DD format
 * @param {string} shiftStart - Start time in HH:MM format
 * @param {string} shiftEnd - End time in HH:MM format
 * @param {number|null} currentEntryId - ID of the current entry being edited (to exclude from conflict check)
 * @returns {object|null} - Conflict details or null if no conflict
 */
function checkTimeConflict(userId, date, shiftStart, shiftEnd, currentEntryId = null, societyId = null) {
    // Check conflicts in existing attendance data
    if (!attendanceData || !attendanceData.attendance || !attendanceData.attendance[date]) {
    return null;
}

    const userEntries = attendanceData.attendance[date][userId] || [];
    
    const conflictingEntries = userEntries.filter(entry => {
        // Skip the current entry being edited
        if (currentEntryId && entry.id === currentEntryId) return false;
        
        // Skip entries without shift times
        if (!entry.shift_start || !entry.shift_end) return false;
        
        // Skip entries that don't require society (like absent, leave, etc.)
        const entryCode = attendanceCodes.find(c => c.id == entry.attendance_master_id);
        if (!entryCode || entryCode.require_society !== 1) return false;
        
        // Skip entries for the same society
        if (entry.society_id === parseInt(societyId)) return false;
        
        // Check for time conflicts
        return checkTimeOverlap(
            shiftStart, 
            shiftEnd, 
            entry.shift_start, 
            entry.shift_end,
            1800 // 30 minutes in seconds
        );
    });
    
    if (conflictingEntries.length > 0) {
        const conflict = conflictingEntries[0];
        const societyName = findSocietyName(conflict.society_id);
        return {
            message: `Guard is already scheduled at ${societyName} from ${formatTimeForDisplay(conflict.shift_start)} to ${formatTimeForDisplay(conflict.shift_end)}.`,
            conflict
        };
    }
    
    return null;
}

/**
 * Check if two time periods overlap, properly handling overnight shifts
 * @param {number} newStart - New shift start time in minutes
 * @param {number} newEnd - New shift end time in minutes
 * @param {boolean} isNewOvernight - Whether the new shift spans overnight
 * @param {number} existingStart - Existing shift start time in minutes
 * @param {number} existingEnd - Existing shift end time in minutes
 * @param {boolean} isExistingOvernight - Whether the existing shift spans overnight
 * @param {number} tolerance - Tolerance in minutes
 * @returns {boolean} - True if there is an overlap
 */
function checkTimeOverlap(newStart, newEnd, existingStart, existingEnd, tolerance = 1800) {
    // Convert times to minutes for comparison
    const minutesInDay = 24 * 60;
    
    // Convert times to minutes
    const newStartMinutes = timeToMinutes(newStart);
    const newEndMinutes = timeToMinutes(newEnd);
    const existingStartMinutes = timeToMinutes(existingStart);
    const existingEndMinutes = timeToMinutes(existingEnd);
    
    // Adjust for overnight shifts
    const adjustedNewEnd = newEndMinutes < newStartMinutes ? 
        newEndMinutes + minutesInDay : newEndMinutes;
    const adjustedExistingEnd = existingEndMinutes < existingStartMinutes ? 
        existingEndMinutes + minutesInDay : existingEndMinutes;
    
    // Detailed console logging
    console.log('Time Overlap Check:', {
        newStart,
        newEnd,
        existingStart,
        existingEnd,
        newStartMinutes,
        newEndMinutes: adjustedNewEnd,
        existingStartMinutes,
        existingEndMinutes: adjustedExistingEnd,
        tolerance
    });
    
    // Check for true overlap considering tolerance
    const hasOverlap = !(
        adjustedNewEnd < (existingStartMinutes - tolerance/60) || 
        newStartMinutes > (adjustedExistingEnd + tolerance/60)
    );
    
    console.log('Overlap Result:', hasOverlap);
    
    return hasOverlap;
}

// --- New Export and Refresh Functions ---
function exportToCSV() {
    if (!attendanceData || !attendanceData.users) {
        alert('No data available to export');
        return;
    }
    
    // Create CSV content
    let csvContent = 'Employee ID,Employee Name,User Type,Email,Month,Year\n';
    
    const month = document.getElementById('month-filter')?.value || new Date().getMonth() + 1;
    const year = document.getElementById('year-filter')?.value || new Date().getFullYear();
    
    filteredUsers.forEach(user => {
        csvContent += `${user.id},"${user.name}","${user.user_type}","${user.email || ''}",${month},${year}\n`;
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `attendance_export_${month}_${year}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function refreshData() {
    console.log('Refreshing attendance data...');
    loadAttendanceDataOptimized(true); // Force refresh
}
