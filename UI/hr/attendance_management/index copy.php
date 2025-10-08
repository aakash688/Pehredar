<?php
// UI/hr/attendance_management/index.php

// Include necessary files
require_once __DIR__ . '/../../../helpers/database.php';

// Include JWT libraries if needed
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config.php';
global $config;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Initialize database
$db = new Database();

// Get user types for filter
$user_types = $db->query("SELECT DISTINCT user_type FROM users ORDER BY user_type")->fetchAll(PDO::FETCH_COLUMN);

// Get societies for filter
$societies = $db->query("SELECT id, society_name, pin_code FROM society_onboarding_data ORDER BY society_name")->fetchAll();

// Get current date info
$current_month = date('n');
$current_year = date('Y');

// Get current user ID from session or cookie
$current_user_id = 1; // Default to admin user
if (isset($_COOKIE['jwt'])) {
    // If using JWT, try to get user ID from it
    try {
        $decoded = JWT::decode($_COOKIE['jwt'], new Key($config['jwt']['secret'], 'HS256'));
        if (isset($decoded->data->id)) {
            $current_user_id = $decoded->data->id;
        }
    } catch (Exception $e) {
        // If JWT parsing fails, keep default user ID
        error_log("Error parsing JWT: " . $e->getMessage());
    }
}

// Page title
$page_title = "Attendance Management";
?>

<!-- Include the new CSS file -->
<link rel="stylesheet" href="UI/assets/css/attendance-management.css">

<!-- Page Header -->
<div class="container mx-auto px-4 py-6 overflow-hidden">
    <!-- Hidden input for current user ID -->
    <input type="hidden" id="current-user-id" value="<?= $current_user_id ?>">
    
    <div class="flex flex-wrap justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Attendance Management</h1>
        <div class="flex mt-2 sm:mt-0">
            <button id="show-instructions-btn" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center" aria-label="Show instructions" aria-expanded="false" aria-controls="attendance-instructions">
                <i class="fas fa-info-circle mr-1" aria-hidden="true"></i> Instructions
            </button>
        </div>
    </div>
    
    <!-- Multi-Society Attendance Instructions (hidden by default) -->
    <div id="attendance-instructions" class="bg-blue-900 p-4 rounded-lg mb-6 hidden" aria-labelledby="show-instructions-btn" role="region">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-lg font-bold text-white">Multi-Society Attendance Instructions</h3>
            <button class="text-white hover:text-gray-300" onclick="document.getElementById('attendance-instructions').classList.add('hidden')" aria-label="Close instructions">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <ul class="text-gray-300 list-disc pl-5">
            <li class="mb-1">A single user can have multiple attendance entries on the same day for different societies.</li>
            <li class="mb-1">To add a new attendance entry, click the "Add Entry" button in any day cell.</li>
            <li class="mb-1">To edit an existing entry, click the edit icon on that entry.</li>
            <li class="mb-1">Each entry includes society, attendance status, and optional shift times.</li>
            <li class="mb-1">Select a shift to automatically populate start and end times, or adjust them manually.</li>
            <li class="mb-1">View the attendance history by clicking the history icon.</li>
        </ul>
    </div>
    
    <!-- Filters Section -->
    <div class="bg-gray-800 p-4 rounded-lg shadow-lg mb-6 w-full">
        <div class="flex flex-wrap justify-between items-center mb-2">
            <h2 class="text-lg font-semibold text-white">Filters</h2>
            <button id="reset-filters-btn" class="text-gray-400 hover:text-white" aria-label="Reset all filters">
                <i class="fas fa-undo mr-1" aria-hidden="true"></i> Reset Filters
            </button>
        </div>
        
        <form id="filter-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="month-filter" class="block text-sm font-medium text-gray-300 mb-1">Month</label>
                <select id="month-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Select month">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $current_month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label for="year-filter" class="block text-sm font-medium text-gray-300 mb-1">Year</label>
                <select id="year-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Select year">
                    <?php for ($i = $current_year - 2; $i <= $current_year + 3; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $current_year ? 'selected' : '' ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label for="department-filter" class="block text-sm font-medium text-gray-300 mb-1">User Type</label>
                <select id="department-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Select user type">
                    <option value="">All User Types</option>
                    <?php foreach ($user_types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="society-filter" class="block text-sm font-medium text-gray-300 mb-1">Society</label>
                <select id="society-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Select society">
                    <option value="">All Societies</option>
                    <?php foreach ($societies as $society): ?>
                        <?php 
                        $displayName = htmlspecialchars($society['society_name']);
                        if (!empty($society['pin_code'])) {
                            $displayName .= ' (' . htmlspecialchars($society['pin_code']) . ')';
                        }
                        ?>
                        <option value="<?= $society['id'] ?>"><?= $displayName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <div class="flex justify-end mt-4">
            <button id="filter-button" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center" aria-label="Apply filters">
                <i class="fas fa-filter mr-1" aria-hidden="true"></i> Apply Filters
            </button>
        </div>
    </div>
    
    <!-- Attendance Sheet Section -->
    <div class="attendance-wrapper bg-gray-800 rounded-lg shadow-lg w-full">
        <div class="p-4">
            <div class="flex flex-wrap justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-white">Attendance Sheet</h2>
                <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                    <button id="show-legend-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded flex items-center" aria-label="Toggle legend" aria-expanded="false" aria-controls="legend">
                        <i class="fas fa-list mr-1" aria-hidden="true"></i> Legend
                    </button>
                    <button id="save-attendance-btn" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded hidden flex items-center" aria-label="Save attendance changes">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> Save Changes
                    </button>
                    <a id="download-excel-btn" href="#" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded flex items-center" aria-label="Export as Excel">
                        <i class="fas fa-file-excel mr-1" aria-hidden="true"></i> Export Excel
                    </a>
                </div>
            </div>
            
            <!-- Legend Panel -->
            <div id="legend" class="mb-4 bg-gray-900 p-3 rounded-lg hidden" aria-labelledby="show-legend-btn" role="region">
                <h3 class="font-bold text-white mb-2">Attendance Code Legend</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2" id="legend-items">
                    <!-- Legend items will be populated by JavaScript -->
                </div>
            </div>
            
            <!-- Search and Pagination Controls -->
            <div class="attendance-controls">
                <div class="search-container">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text" id="employee-search" placeholder="Search employees..." class="focus:ring-2 focus:ring-blue-500 focus:outline-none" aria-label="Search employees">
                </div>
                
                <div class="pagination-container" role="navigation" aria-label="Employee pagination">
                    <div class="page-size-selector">
                        <label for="page-size" class="text-gray-300 text-sm">Show</label>
                        <select id="page-size" class="focus:ring-2 focus:ring-blue-500 focus:outline-none" aria-label="Select number of entries per page">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                        <span class="text-gray-300 text-sm">entries</span>
                    </div>
                    
                    <div id="pagination-controls" class="pagination-controls" role="navigation" aria-label="Pagination">
                        <!-- Pagination will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Loading indicator -->
        <div id="loader" class="py-8 flex justify-center" role="status" aria-label="Loading attendance data">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
            <span class="sr-only">Loading...</span>
        </div>
        
        <!-- Attendance table container with scroll indicators -->
        <div id="attendance-table-container" class="attendance-table-container hidden" role="region" aria-label="Attendance data table" tabindex="0">
            <div class="horizontal-scroll-indicator-left" aria-hidden="true"></div>
            <div class="horizontal-scroll-indicator-right" aria-hidden="true"></div>
            <!-- Table will be rendered here by JavaScript -->
        </div>
    </div>
</div>

<!-- Attendance Entry Modal -->
<div id="attendance-entry-modal" class="modal hidden" tabindex="-1" role="dialog" aria-labelledby="entry-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-gray-800 text-white">
            <div class="modal-header border-gray-700">
                <h5 class="modal-title" id="entry-modal-title">Add Attendance Entry</h5>
                <button type="button" class="close" onclick="closeEntryModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="attendance-entry-form">
                    <input type="hidden" id="entry-user-id">
                    <input type="hidden" id="entry-date">
                    <input type="hidden" id="entry-id">
                    
                    <div class="form-group mb-4">
                        <label for="entry-society" class="block text-sm font-medium text-gray-300 mb-1">Society <span class="text-red-500">*</span></label>
                        <select id="entry-society" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required aria-required="true">
                            <option value="">Select Society</option>
                            <!-- Society options will be populated by JavaScript -->
                        </select>
                        <small class="text-gray-400">A user can have one attendance record per society per day.</small>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="entry-code" class="block text-sm font-medium text-gray-300 mb-1">Attendance Status <span class="text-red-500">*</span></label>
                        <select id="entry-code" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required aria-required="true">
                            <option value="">Select Status</option>
                            <!-- Attendance codes will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <!-- Shift Selection Section -->
                    <div class="form-group mb-4">
                        <label for="entry-shift" class="block text-sm font-medium text-gray-300 mb-1">Shift <span class="text-red-500">*</span></label>
                        <select id="entry-shift" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required aria-required="true">
                            <option value="">Select Shift</option>
                            <!-- Shifts will be populated by JavaScript -->
                        </select>
                        <small class="text-gray-400">Select a predefined shift or customize shift times</small>
                    </div>

                    <!-- Existing Shift Time Section (now with auto-fill capability) -->
                    <div class="form-row grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group mb-4">
                            <label for="entry-shift-start" class="block text-sm font-medium text-gray-300 mb-1">Shift Start</label>
                            <input type="time" id="entry-shift-start" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Shift start time">
                        </div>
                        <div class="form-group mb-4">
                            <label for="entry-shift-end" class="block text-sm font-medium text-gray-300 mb-1">Shift End</label>
                            <input type="time" id="entry-shift-end" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Shift end time">
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="entry-reason" class="block text-sm font-medium text-gray-300 mb-1">Reason for Change</label>
                        <textarea id="entry-reason" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" rows="2" placeholder="Enter reason for changing attendance (optional)" aria-label="Reason for change"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-gray-700">
                <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg mr-2" onclick="closeEntryModal()" aria-label="Cancel">
                    Cancel
                </button>
                <button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg" onclick="saveAttendanceEntry()" aria-label="Save attendance entry">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit History Modal -->
<div id="audit-modal" class="modal hidden" tabindex="-1" role="dialog" aria-labelledby="audit-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content bg-gray-800 text-white">
            <div class="modal-header border-gray-700">
                <h5 class="modal-title" id="audit-modal-title">Attendance History</h5>
                <button type="button" class="close" onclick="hideElement('audit-modal')" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="audit-content" role="region" aria-label="Attendance history entries">
                    <!-- Audit data will be populated here -->
                </div>
            </div>
            <div class="modal-footer border-gray-700">
                <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg" onclick="hideElement('audit-modal')" aria-label="Close history">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom tooltip element for enhanced hover information -->
<div id="custom-tooltip" class="custom-tooltip" role="tooltip" aria-hidden="true"></div>

<script src="UI/assets/js/attendance-management.js"></script> 