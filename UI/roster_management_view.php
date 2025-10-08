<?php
require_once 'config.php';
require_once 'helpers/database.php';

// Fetch teams for filter
$db = new Database();
$teams = $db->query("SELECT id, team_name FROM teams ORDER BY team_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch shifts for filter
$shifts = $db->query("SELECT id, shift_name FROM shift_master ORDER BY shift_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roster Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Custom Notification System */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .notification {
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left: 4px solid #10b981;
        }

        .notification.error {
            border-left: 4px solid #ef4444;
        }

        .notification.warning {
            border-left: 4px solid #f59e0b;
        }

        .notification-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification.success .notification-icon {
            color: #10b981;
        }

        .notification.error .notification-icon {
            color: #ef4444;
        }

        .notification.warning .notification-icon {
            color: #f59e0b;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #f9fafb;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .notification-message {
            color: #d1d5db;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .notification-refresh-indicator {
            color: #60a5fa;
            font-size: 11px;
            font-style: italic;
            opacity: 0.8;
        }

        .notification-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .notification-close:hover {
            color: #f9fafb;
            background: #374151;
        }

        /* Custom Modal System */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .custom-modal-overlay.show {
            display: flex;
        }

        .custom-modal {
            background: #1f2937;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .custom-modal-overlay.show .custom-modal {
            transform: scale(1);
        }

        .custom-modal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #374151;
        }

        .custom-modal-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .custom-modal.error .custom-modal-icon {
            background: #dc2626;
            color: white;
        }

        .custom-modal.warning .custom-modal-icon {
            background: #d97706;
            color: white;
        }

        .custom-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #f9fafb;
            margin: 0;
        }

        .custom-modal-body {
            margin-bottom: 24px;
        }

        .custom-modal-message {
            color: #d1d5db;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .custom-modal-details {
            background: #111827;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            max-height: 200px;
            overflow-y: auto;
        }

        .custom-modal-details h4 {
            color: #f9fafb;
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 12px 0;
        }

        .custom-modal-details ul {
            margin: 0;
            padding-left: 20px;
            color: #d1d5db;
            font-size: 13px;
        }

        .custom-modal-details li {
            margin-bottom: 6px;
        }

        .custom-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .custom-modal-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 14px;
        }

        .custom-modal-btn.primary {
            background: #3b82f6;
            color: white;
        }

        .custom-modal-btn.primary:hover {
            background: #2563eb;
        }

        .custom-modal-btn.secondary {
            background: #6b7280;
            color: white;
        }

        .custom-modal-btn.secondary:hover {
            background: #4b5563;
        }

        /* Minimalistic scrollbar for timeline */
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #1f2937; /* bg-gray-800 */
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4f46e5; /* bg-indigo-600 */
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4338ca; /* bg-indigo-700 */
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <!-- Notification Container -->
    <div id="notification-container" class="notification-container"></div>

    <!-- Custom Modal Overlay -->
    <div id="custom-modal-overlay" class="custom-modal-overlay">
        <div id="custom-modal" class="custom-modal">
            <div class="custom-modal-header">
                <div id="custom-modal-icon" class="custom-modal-icon">
                    <i id="custom-modal-icon-i" class="fas"></i>
                </div>
                <h3 id="custom-modal-title" class="custom-modal-title"></h3>
            </div>
            <div class="custom-modal-body">
                <div id="custom-modal-message" class="custom-modal-message"></div>
                <div id="custom-modal-details" class="custom-modal-details" style="display: none;">
                    <h4 id="custom-modal-details-title"></h4>
                    <ul id="custom-modal-details-list"></ul>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button id="custom-modal-ok" class="custom-modal-btn primary">OK</button>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold flex items-center">
                <i class="fas fa-clipboard-list mr-3 text-blue-500"></i>
                Roster Management
            </h1>
            <button id="bulk-assign-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300 flex items-center">
                <i class="fas fa-users-cog mr-2"></i> Bulk Assign
            </button>
        </div>

        <!-- Filters and Search -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search-input" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-search mr-2 text-blue-400"></i>Search
                    </label>
                    <input type="text" id="search-input" placeholder="Search guards or clients" 
                        class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="team-filter" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-users mr-2 text-blue-400"></i>Team
                    </label>
                    <select id="team-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="shift-filter" class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-clock mr-2 text-blue-400"></i>Shift
                    </label>
                    <select id="shift-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Shifts</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo $shift['id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="apply-filters" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300 flex items-center justify-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Roster Table -->
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Guard</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Team</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Shift</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Assign Dates</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="roster-table-body" class="divide-y divide-gray-700">
                        <!-- Dynamic content will be loaded here -->
                        <tr>
                            <td colspan="5" class="text-center py-4 text-gray-400">
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Loading roster entries...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-gray-700 px-4 py-3 flex items-center justify-between border-t border-gray-600">
                <div class="flex-1 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-300">
                            Showing 
                            <span id="start-record">1</span> to 
                            <span id="end-record">10</span> of 
                            <span id="total-records">0</span> entries
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <button id="prev-page" class="bg-gray-600 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded transition duration-300 disabled:opacity-50">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <button id="next-page" class="bg-gray-600 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded transition duration-300 disabled:opacity-50">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Assign Modal -->
        <div id="bulk-assign-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="fas fa-users-cog mr-3 text-blue-500"></i>
                        Bulk Roster Assignment
                    </h2>
                    <button id="close-bulk-modal" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="bulk-assign-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Team Selection -->
                        <div>
                            <label for="bulk-team-select" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-users mr-2 text-blue-400"></i>
                                Team
                            </label>
                            <select id="bulk-team-select" name="team_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Team</option>
                                <?php 
                                // Populate teams dynamically
                                $db = new Database();
                                $teams = $db->query("SELECT id, team_name FROM teams ORDER BY team_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($teams as $team) {
                                    echo "<option value='{$team['id']}'>{$team['team_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Supervisor Selection -->
                        <div>
                            <label for="bulk-supervisor-select" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-user-tie mr-2 text-blue-400"></i>
                                Supervisor
                            </label>
                            <select id="bulk-supervisor-select" name="supervisor_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Supervisor</option>
                            </select>
                        </div>

                        <!-- Client Selection -->
                        <div>
                            <label for="bulk-client-select" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-building mr-2 text-blue-400"></i>
                                Client
                            </label>
                            <select id="bulk-client-select" name="society_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Client</option>
                            </select>
                        </div>

                        <!-- Shift Selection -->
                        <div>
                            <label for="bulk-shift-select" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-clock mr-2 text-blue-400"></i>
                                Shift
                            </label>
                            <select id="bulk-shift-select" name="shift_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Shift</option>
                                <?php 
                                // Populate shifts dynamically
                                $shifts = $db->query("SELECT id, shift_name FROM shift_master ORDER BY shift_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($shifts as $shift) {
                                    echo "<option value='{$shift['id']}'>{$shift['shift_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Assignment Date Range -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-calendar-alt mr-2 text-blue-400"></i>
                                Assignment Date Range
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" id="assignment-start" name="assignment_start_date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <input type="date" id="assignment-end" name="assignment_end_date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Guards Selection -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-user-shield mr-2 text-blue-400"></i>
                            Select Guards
                        </label>
                        <div id="bulk-guards-container" class="max-h-64 overflow-y-auto bg-gray-700 p-3 rounded-lg border border-gray-600">
                            <p class="text-gray-500 text-center">Select a team to view guards</p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="button" id="close-bulk-modal-bottom" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                            Bulk Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Edit Roster Modal -->
        <div id="edit-roster-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-white">
                        <i class="fas fa-edit mr-3 text-indigo-400"></i>
                        Edit Roster Entry
                    </h2>
                    <button id="close-edit-modal" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="edit-roster-form" class="space-y-4">
                    <input type="hidden" id="edit-roster-id" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Client</label>
                            <select id="edit-client" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3">
                                <option value="">Select Client</option>
                                <?php 
                                // Populate clients dynamically
                                $db = new Database();
                                $clients = $db->query("SELECT id, society_name FROM society_onboarding_data ORDER BY society_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($clients as $client) {
                                    echo "<option value='{$client['id']}'>{$client['society_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Guard</label>
                            <input type="text" id="edit-guard" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3" disabled />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Shift</label>
                            <select id="edit-shift" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3">
                                <?php 
                                // Populate shifts dynamically
                                $shifts = $db->query("SELECT id, shift_name FROM shift_master ORDER BY shift_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($shifts as $shift) {
                                    echo "<option value='{$shift['id']}'>{$shift['shift_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Assignment Dates</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" id="edit-start" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3" />
                                <input type="date" id="edit-end" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" id="close-edit-modal-bottom" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Cancel</button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, setting up roster management...');
        
        // Notification System Functions
        function showNotification(message, type = 'success', title = null, onComplete = null) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const displayTitle = title || (type === 'success' ? 'Success' : 
                                         type === 'error' ? 'Error' : 
                                         type === 'warning' ? 'Warning' : 'Info');
            
            // Add refresh indicator for success notifications with onComplete callback
            const refreshIndicator = onComplete ? '<div class="notification-refresh-indicator">Page will refresh automatically</div>' : '';
            
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${displayTitle}</div>
                    <div class="notification-message">${message}</div>
                    ${refreshIndicator}
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(notification);
            
            // Trigger animation
            setTimeout(() => notification.classList.add('show'), 10);
            
            // Auto-dismiss after 5 seconds for success, 4 seconds for others
            const dismissTime = type === 'success' ? 5000 : 4000;
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                        // Call onComplete callback after notification is fully removed
                        if (onComplete) onComplete();
                    }, 300);
                }
            }, dismissTime);
        }

        // Custom Modal System Functions
        function showCustomModal(title, message, type = 'error', details = null, onOk = null) {
            const overlay = document.getElementById('custom-modal-overlay');
            const modal = document.getElementById('custom-modal');
            const modalIcon = document.getElementById('custom-modal-icon');
            const modalIconI = document.getElementById('custom-modal-icon-i');
            const modalTitle = document.getElementById('custom-modal-title');
            const modalMessage = document.getElementById('custom-modal-message');
            const modalDetails = document.getElementById('custom-modal-details');
            const modalDetailsTitle = document.getElementById('custom-modal-details-title');
            const modalDetailsList = document.getElementById('custom-modal-details-list');
            const okButton = document.getElementById('custom-modal-ok');

            // Set modal type and styling
            modal.className = `custom-modal ${type}`;
            modalIcon.className = `custom-modal-icon ${type}`;
            
            // Set icon
            const icon = type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            modalIconI.className = `fas ${icon}`;
            
            // Set content
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            
            // Handle details if provided
            if (details && details.length > 0) {
                modalDetails.style.display = 'block';
                modalDetailsTitle.textContent = 'Details:';
                
                const detailsHtml = details.map(detail => {
                    if (typeof detail === 'string') {
                        return `<li>${detail}</li>`;
                    } else if (detail.message) {
                        const guardName = detail.guard_name || `Guard ID ${detail.guard_id}`;
                        return `<li><strong>${guardName}:</strong> ${detail.message}</li>`;
                    } else {
                        return `<li>${JSON.stringify(detail)}</li>`;
                    }
                }).join('');
                
                modalDetailsList.innerHTML = detailsHtml;
            } else {
                modalDetails.style.display = 'none';
            }
            
            // Set up OK button
            okButton.onclick = () => {
                overlay.classList.remove('show');
                if (onOk) onOk();
            };
            
            // Show modal
            overlay.classList.add('show');
        }

        // Close modal when clicking outside
        document.getElementById('custom-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
        
        const searchInput = document.getElementById('search-input');
        const teamFilter = document.getElementById('team-filter');
        const shiftFilter = document.getElementById('shift-filter');
        const applyFiltersBtn = document.getElementById('apply-filters');
        const rosterTableBody = document.getElementById('roster-table-body');
        const bulkAssignBtn = document.getElementById('bulk-assign-btn');
        const bulkAssignModal = document.getElementById('bulk-assign-modal');
        const bulkTeamSelect = document.getElementById('bulk-team-select');
        const bulkSupervisorSelect = document.getElementById('bulk-supervisor-select');
        const bulkClientSelect = document.getElementById('bulk-client-select');
        const bulkGuardsContainer = document.getElementById('bulk-guards-container');
        const bulkShiftSelect = document.getElementById('bulk-shift-select');
        const closeBulkModalBtn = document.getElementById('close-bulk-modal');
        const closeBulkModalBottomBtn = document.getElementById('close-bulk-modal-bottom');
        
        // Edit modal elements
        const editModal = document.getElementById('edit-roster-modal');
        const closeEditModalBtn = document.getElementById('close-edit-modal');
        const closeEditModalBottomBtn = document.getElementById('close-edit-modal-bottom');
        const editForm = document.getElementById('edit-roster-form');
        const editRosterId = document.getElementById('edit-roster-id');
        const editClient = document.getElementById('edit-client');
        const editGuard = document.getElementById('edit-guard');
        const editShift = document.getElementById('edit-shift');
        const editStart = document.getElementById('edit-start');
        const editEnd = document.getElementById('edit-end');

        console.log('Elements found:', {
            searchInput: !!searchInput,
            rosterTableBody: !!rosterTableBody,
            editModal: !!editModal,
            editForm: !!editForm
        });


        let currentPage = 1;
        const recordsPerPage = 10;

        // Pagination elements
        const startRecordSpan = document.getElementById('start-record');
        const endRecordSpan = document.getElementById('end-record');
        const totalRecordsSpan = document.getElementById('total-records');
        const prevPageBtn = document.getElementById('prev-page');
        const nextPageBtn = document.getElementById('next-page');

        function fetchRosters() {
            const search = searchInput.value;
            const teamId = teamFilter.value;
            const shiftId = shiftFilter.value;

            // Debounce API calls to prevent excessive requests
            clearTimeout(window.rosterFetchTimeout);
            window.rosterFetchTimeout = setTimeout(() => {
                // Construct URL with proper base path
                const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]+$/, '');
                const url = new URL(`${baseUrl}/actions/roster_controller.php`);
                url.searchParams.set('action', 'get_rosters');
                url.searchParams.set('page', currentPage);
                url.searchParams.set('per_page', recordsPerPage);

                if (search) url.searchParams.set('search', search);
                if (teamId) url.searchParams.set('team_id', teamId);
                if (shiftId) url.searchParams.set('shift_id', shiftId);

                // Show loading indicator
                rosterTableBody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

                fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Clear previous table
                        rosterTableBody.innerHTML = '';

                        console.log('Roster data received:', data.rosters);

                        // Populate table
                        data.rosters.forEach(roster => {
                            const row = document.createElement('tr');
                            console.log('Creating row for roster:', roster.id, 'roster data:', roster);
                            
                            row.innerHTML = `
                                <td class="px-4 py-2">${roster.guard_name}</td>
                                <td class="px-4 py-2">${roster.team_name}</td>
                                <td class="px-4 py-2">${roster.society_name}</td>
                                <td class="px-4 py-2">${roster.shift_name}</td>
                                <td class="px-4 py-2">${roster.start_time} - ${roster.end_time}</td>
                                <td class="px-4 py-2">${roster.assignment_start_date ? (roster.assignment_start_date + ' → ' + (roster.assignment_end_date || '')) : '-'}</td>
                                <td class="px-4 py-2 space-x-3">
                                    <button class="text-indigo-400 hover:text-indigo-300" onclick="openEditRoster(${roster.id})" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteRoster(${roster.id})" class="text-red-500 hover:text-red-700" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            `;
                            rosterTableBody.appendChild(row);
                        });

                        // Update pagination
                        const startRecord = ((currentPage - 1) * recordsPerPage) + 1;
                        const endRecord = Math.min(startRecord + recordsPerPage - 1, data.total_records);

                        startRecordSpan.textContent = startRecord;
                        endRecordSpan.textContent = endRecord;
                        totalRecordsSpan.textContent = data.total_records;

                        // Update pagination buttons
                        prevPageBtn.disabled = currentPage === 1;
                        nextPageBtn.disabled = endRecord >= data.total_records;
                    } else {
                        throw new Error(data.message || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    rosterTableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center text-red-500">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Error loading rosters: ${error.message}
                            </td>
                        </tr>
                    `;
                });
            }, 300); // 300ms debounce
        }

        // Event Listeners with Debounce
        searchInput.addEventListener('input', () => {
            currentPage = 1;
            fetchRosters();
        });

        teamFilter.addEventListener('change', () => {
            currentPage = 1;
            fetchRosters();
        });

        shiftFilter.addEventListener('change', () => {
            currentPage = 1;
            fetchRosters();
        });

        // Pagination Event Listeners
        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                fetchRosters();
            }
        });

        nextPageBtn.addEventListener('click', () => {
            currentPage++;
            fetchRosters();
        });

        // Bulk Assign Modal
        bulkAssignBtn.addEventListener('click', () => {
            bulkAssignModal.classList.remove('hidden');
            // Reset cascading selects when modal opens
            resetCascadingSelects();
        });

        closeBulkModalBtn.addEventListener('click', () => {
            bulkAssignModal.classList.add('hidden');
        });

        closeBulkModalBottomBtn.addEventListener('click', () => {
            bulkAssignModal.classList.add('hidden');
        });

        // Reset function to clear dropdowns and guards
        function resetCascadingSelects(fromLevel = 'team') {
            switch(fromLevel) {
                case 'team':
                    bulkSupervisorSelect.innerHTML = '<option value="">Select Supervisor</option>';
                    bulkClientSelect.innerHTML = '<option value="">Select Client</option>';
                    bulkGuardsContainer.innerHTML = '<p class="text-gray-500 text-center">Select a team to view guards</p>';
                    break;
                case 'supervisor':
                    bulkClientSelect.innerHTML = '<option value="">Select Client</option>';
                    break;
            }
        }

        // Team Selection Handler
        bulkTeamSelect.addEventListener('change', function() {
            const teamId = this.value;
            
            // Reset subsequent dropdowns
            resetCascadingSelects('team');

            if (!teamId) return;

            // Fetch Team Supervisors
            fetch(`actions/roster_controller.php?action=get_team_supervisors&team_id=${teamId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.supervisors.length > 0) {
                        data.supervisors.forEach(supervisor => {
                            const option = document.createElement('option');
                            option.value = supervisor.id;
                            option.textContent = supervisor.supervisor_name;
                            bulkSupervisorSelect.appendChild(option);
                        });
                    } else {
                        showNotification('No supervisors found for this team', 'warning', 'No Supervisors');
                    }
                })
                .catch(error => {
                    console.error('Error fetching supervisors:', error);
                    showNotification('Failed to fetch supervisors', 'error', 'Fetch Error');
                });

            // Fetch Team Guards
            fetch(`actions/team_controller.php?action=getAllTeamMembers&team_id=${teamId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.members.length > 0) {
                        const guardsHtml = data.members.map(member => `
                            <div class="flex items-center mb-2">
                                <input type="checkbox" name="guard_ids[]" value="${member.id}" 
                                    id="guard-${member.id}" 
                                    class="mr-3 bg-gray-700 border-gray-600 text-blue-500">
                                <label for="guard-${member.id}" class="text-sm text-gray-300">
                                    ${member.first_name} ${member.surname} (${member.user_type})
                                </label>
                            </div>
                        `).join('');
                        bulkGuardsContainer.innerHTML = guardsHtml;
                    } else {
                        bulkGuardsContainer.innerHTML = '<p class="text-gray-500 text-center">No guards found in this team</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching team members:', error);
                    bulkGuardsContainer.innerHTML = `
                        <p class="text-red-500 text-center">
                            Failed to load team members: ${error.message}
                        </p>
                    `;
                });
        });

        // Supervisor Selection Handler
        bulkSupervisorSelect.addEventListener('change', function() {
            const supervisorId = this.value;
            
            // Reset client dropdown
            resetCascadingSelects('supervisor');

            if (!supervisorId) return;

            // Fetch Supervisor's Clients
            fetch(`actions/roster_controller.php?action=get_supervisor_clients&supervisor_id=${supervisorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.clients.length > 0) {
                        data.clients.forEach(client => {
                            const option = document.createElement('option');
                            option.value = client.id;
                            option.textContent = client.society_name;
                            bulkClientSelect.appendChild(option);
                        });
                    } else {
                        showNotification('No clients found for this supervisor', 'warning', 'No Clients');
                    }
                })
                .catch(error => {
                    console.error('Error fetching clients:', error);
                    showNotification('Failed to fetch clients', 'error', 'Fetch Error');
                });
        });

        // Bulk Assign Form Submission
        document.getElementById('bulk-assign-form').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate form
            const teamId = bulkTeamSelect.value;
            const supervisorId = bulkSupervisorSelect.value;
            const clientId = bulkClientSelect.value;
            const shiftId = bulkShiftSelect.value;
            const guardIds = Array.from(
                document.querySelectorAll('input[name="guard_ids[]"]:checked')
            ).map(el => el.value);

            if (!teamId || !supervisorId || !clientId || !shiftId || guardIds.length === 0) {
                showNotification('Please fill all fields and select at least one guard', 'warning', 'Validation Error');
                return;
            }

            // Prepare bulk assignment data
            const assignmentData = {
                team_id: teamId,
                society_id: clientId,
                shift_id: shiftId,
                guard_ids: guardIds,
                assignment_start_date: document.getElementById('assignment-start').value || null,
                assignment_end_date: document.getElementById('assignment-end').value || null
            };

            // Send bulk assignment request
            fetch('actions/roster_controller.php?action=bulk_assign_roster', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(assignmentData)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    if (result.details.conflicts && result.details.conflicts.length > 0) {
                        // Partial success with conflicts - show modal
                        showCustomModal(
                            'Partial Success',
                            result.message,
                            'warning',
                            result.details.conflicts,
                            () => {
                                // Close modal and show success notification with delayed refresh
                                showNotification('Partial assignment completed with some conflicts', 'warning', 'Partial Success', () => {
                                    window.location.reload();
                                });
                            }
                        );
                    } else {
                        // Complete success - show notification and refresh
                        const successMessage = result.message;
                        showNotification(successMessage, 'success', 'Bulk Assignment Complete', () => {
                            window.location.reload();
                        });
                    }
                } else {
                    // Complete failure - show modal
                    showCustomModal(
                        'Bulk Assignment Failed',
                        result.message,
                        'error',
                        null,
                        null
                    );
                }
            })
            .catch(error => {
                console.error('Bulk assignment error:', error);
                showCustomModal(
                    'Error',
                    'An unexpected error occurred during bulk assignment',
                    'error',
                    null,
                    null
                );
            });

            // Close modal immediately after starting the request
            bulkAssignModal.classList.add('hidden');
        });

        // Delete Roster Function
        window.deleteRoster = function(rosterId) {
            console.log('deleteRoster called with ID:', rosterId);
            if (confirm(`Are you sure you want to delete roster entry ${rosterId}?`)) {
                // Construct URL with proper base path
                const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]+$/, '');
                const url = new URL(`${baseUrl}/actions/roster_controller.php`);
                url.searchParams.set('action', 'delete_roster');
                url.searchParams.set('id', rosterId);

                console.log('Delete URL:', url.toString());

                fetch(url.toString(), { method: 'DELETE' })
                    .then(res => {
                        console.log('Delete response status:', res.status);
                        // Check if response is OK
                        if (!res.ok) {
                            // Try to parse error response
                            return res.json().then(errorData => {
                                throw new Error(errorData.message || `HTTP error! status: ${res.status}`);
                            }).catch(() => {
                                throw new Error(`HTTP error! status: ${res.status}`);
                            });
                        }
                        
                        // Check content type
                        const contentType = res.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new TypeError("Expected JSON response, got " + contentType);
                        }
                        
                        return res.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showNotification('Roster entry deleted successfully', 'success', 'Deleted', () => {
                                fetchRosters();
                            });
                        } else {
                            throw new Error(data.message || 'Failed to delete roster entry');
                        }
                    })
                    .catch(error => {
                        console.error('Delete roster error:', error);
                        showCustomModal(
                            'Delete Error',
                            error.message || 'An error occurred while deleting the roster entry',
                            'error',
                            null,
                            null
                        );
                    });
            }
        };

        // Open Edit Roster Modal
        window.openEditRoster = function(rosterId) {
            console.log('openEditRoster called with ID:', rosterId);
            // Fetch the specific roster entry details
            fetch(`actions/roster_controller.php?action=get_roster_by_id&id=${rosterId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const roster = data.roster;
                        console.log('Fetched roster for editing:', roster);
                        
                        editRosterId.value = roster.id;
                        editClient.value = roster.society_id; // Changed to society_id
                        editGuard.value = roster.guard_name;
                        editShift.value = roster.shift_id;
                        editStart.value = roster.assignment_start_date || '';
                        editEnd.value = roster.assignment_end_date || '';
                        editModal.classList.remove('hidden');
                    } else {
                        alert('Failed to fetch roster details for editing.');
                        console.error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching roster for edit:', error);
                    alert('An error occurred while fetching roster details for editing.');
                });
        };

        // Ensure functions are available globally
        console.log('deleteRoster function defined:', typeof window.deleteRoster);
        console.log('openEditRoster function defined:', typeof window.openEditRoster);

        function closeEditModal() {
            editModal.classList.add('hidden');
        }
        closeEditModalBtn.addEventListener('click', closeEditModal);
        closeEditModalBottomBtn.addEventListener('click', closeEditModal);

        // Save edited roster
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate dates
            const startDate = editStart.value;
            const endDate = editEnd.value;
            
            if (startDate && endDate && startDate > endDate) {
                showNotification('Start date cannot be after end date', 'warning', 'Date Validation');
                return;
            }

            const payload = {
                id: editRosterId.value,
                society_id: editClient.value, // Changed to society_id
                shift_id: editShift.value,
                assignment_start_date: startDate || null,
                assignment_end_date: endDate || null
            };

            fetch('actions/roster_controller.php?action=update_roster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    closeEditModal();
                    showNotification('Roster updated successfully', 'success', 'Updated', () => {
                        fetchRosters();
                    });
                } else {
                    showCustomModal(
                        'Update Error',
                        data.message || 'Failed to update roster',
                        'error',
                        null,
                        null
                    );
                }
            }).catch(err => {
                console.error(err);
                showCustomModal(
                    'Error',
                    'Error updating roster',
                    'error',
                    null,
                    null
                );
            });
        });

        // Initial load
        fetchRosters();
    });
    </script>
</body>
</html> 