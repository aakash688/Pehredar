<?php
// UI/hr/shift_management/index.php

// Set page title
$page_title = "Shift Management";

// We don't need to include dashboard_layout.php here
// The main index.php will handle that
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="text-2xl font-bold text-white mb-4">Shift Management</h1>
    <p class="text-gray-300 mb-6">Manage shift timings for different user types</p>
</div>

<!-- Main Content -->
<div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
    <!-- Controls Section -->
    <div class="flex justify-between mb-6">
        <div class="flex space-x-4">
            <div class="form-group">
                <label for="status-filter" class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                <select id="status-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="invisible block">Add New</label>
            <button id="add-shift-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Add New Shift
            </button>
        </div>
    </div>
    
    <!-- Shifts Table -->
    <div id="shifts-table-container" class="w-full">
        <div id="loading-indicator" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-500 mx-auto"></div>
            <p class="text-gray-400 mt-2">Loading shifts...</p>
        </div>
        
        <table id="shifts-table" class="w-full hidden">
            <thead>
                <tr class="bg-gray-700 text-gray-200">
                    <th class="py-3 px-4 text-left">Shift Name</th>
                    <th class="py-3 px-4 text-left">Start Time</th>
                    <th class="py-3 px-4 text-left">End Time</th>
                    <th class="py-3 px-4 text-left">Status</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="shifts-table-body">
                <!-- Shifts will be populated by JavaScript -->
            </tbody>
        </table>
        
        <div id="no-shifts-message" class="text-center py-8 hidden">
            <p class="text-gray-400">No shifts found. Create a new shift to get started.</p>
        </div>
    </div>
</div>

<!-- Shift Modal -->
<div id="shift-modal" class="modal hidden" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-gray-800 text-white">
            <div class="modal-header border-gray-700">
                <h5 class="modal-title" id="shift-modal-title">Add New Shift</h5>
                <button type="button" class="close text-white" data-dismiss="modal" onclick="closeShiftModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="shift-form">
                    <input type="hidden" id="shift-id">
                    
                    <div class="form-group mb-4">
                        <label for="shift-name" class="block text-sm font-medium text-gray-300 mb-1">Shift Name <span class="text-red-500">*</span></label>
                        <input type="text" id="shift-name" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6 mb-4">
                            <label for="shift-start" class="block text-sm font-medium text-gray-300 mb-1">Start Time <span class="text-red-500">*</span></label>
                            <input type="time" id="shift-start" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        <div class="form-group col-md-6 mb-4">
                            <label for="shift-end" class="block text-sm font-medium text-gray-300 mb-1">End Time <span class="text-red-500">*</span></label>
                            <input type="time" id="shift-end" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="shift-description" class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                        <textarea id="shift-description" class="bg-gray-700 border border-gray-600 text-white rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group mb-4" id="shift-status-container">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="shift-status" checked>
                            <label class="custom-control-label text-sm font-medium text-gray-300" for="shift-status">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-gray-700">
                <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg mr-2" data-dismiss="modal" onclick="closeShiftModal()">
                    Cancel
                </button>
                <button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg" onclick="saveShift()">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal hidden" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-gray-800 text-white">
            <div class="modal-header border-gray-700">
                <h5 class="modal-title">Confirm Deactivation</h5>
                <button type="button" class="close text-white" data-dismiss="modal" onclick="closeDeleteModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate this shift?</p>
                <p class="text-gray-400">Note: This will only deactivate the shift. Existing attendance records will not be affected.</p>
                <input type="hidden" id="delete-shift-id">
            </div>
            <div class="modal-footer border-gray-700">
                <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg mr-2" data-dismiss="modal" onclick="closeDeleteModal()">
                    Cancel
                </button>
                <button type="button" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg" onclick="deactivateShift()">
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="UI/assets/js/shift-management.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        initShiftManagement();
    });
</script> 

<style>
    /* Modal styling */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1050;
        width: 100%;
        height: 100%;
        overflow: hidden;
        outline: 0;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-dialog {
        position: relative;
        width: auto;
        margin: 1.75rem auto;
        max-width: 500px;
    }
    
    .modal-content {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        pointer-events: auto;
        background-clip: padding-box;
        border-radius: 0.5rem;
        outline: 0;
    }
    
    .modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 1rem;
        border-bottom: 1px solid #2d3748;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }
    
    .modal-body {
        position: relative;
        flex: 1 1 auto;
        padding: 1rem;
    }
    
    .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 1rem;
        border-top: 1px solid #2d3748;
        border-bottom-right-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
    }
    
    .close {
        float: right;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
        color: #fff;
        text-shadow: 0 1px 0 #000;
        opacity: .5;
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
    }
    
    .close:hover {
        opacity: 1;
    }
    
    .modal-dialog-centered {
        display: flex;
        align-items: center;
        min-height: calc(100% - 3.5rem);
    }
</style> 