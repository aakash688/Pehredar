<?php
/**
 * Advance Skip Request Management View
 * UI for managing advance salary skip requests
 */

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/AdvanceSkipManager.php';

$db = new Database();
$skipManager = new \Helpers\AdvanceSkipManager();

// Get pending skip requests
$pendingRequests = $skipManager->getPendingSkipRequests();

// Get all skip requests if advance_id is provided
$advanceId = $_GET['advance_id'] ?? null;
$advanceSkipRequests = [];
if ($advanceId) {
    $advanceSkipRequests = $skipManager->getAdvanceSkipRequests($advanceId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Skip Request Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Advance Skip Request Management</h1>
                <div class="flex space-x-4">
                    <button onclick="refreshData()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Refresh
                    </button>
                    <button onclick="showRequestForm()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        New Skip Request
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="showTab('pending')" id="pending-tab" class="tab-button py-4 px-1 border-b-2 border-blue-500 text-blue-600 font-medium">
                        Pending Requests (<?php echo count($pendingRequests); ?>)
                    </button>
                    <button onclick="showTab('all')" id="all-tab" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        All Requests
                    </button>
                </nav>
            </div>
        </div>

        <!-- Pending Requests Tab -->
        <div id="pending-content" class="tab-content">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Pending Skip Requests</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advance Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skip Month</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($pendingRequests)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No pending skip requests</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                            <div class="text-sm text-gray-500">Request #<?php echo htmlspecialchars($request['request_number']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">₹<?php echo number_format($request['amount'], 2); ?></div>
                                            <div class="text-sm text-gray-500">Remaining: ₹<?php echo number_format($request['remaining_balance'], 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['skip_month']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['reason']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['requested_by_name']); ?>
                                            <div class="text-xs text-gray-400">
                                                <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="approveRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900 mr-3">
                                                Approve
                                            </button>
                                            <button onclick="rejectRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900">
                                                Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- All Requests Tab -->
        <div id="all-content" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">All Skip Requests</h2>
                </div>
                <div class="p-6">
                    <p class="text-gray-500">Select an advance to view its skip request history.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Skip Request Form Modal -->
    <div id="requestModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Request Skip Deduction</h3>
                </div>
                <form id="skipRequestForm" class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Advance Payment</label>
                        <select name="advance_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Advance Payment</option>
                            <!-- Options will be populated via JavaScript -->
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Skip Month</label>
                        <input type="month" name="skip_month" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                        <textarea name="reason" required rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Please provide a reason for skipping this month's deduction..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRequestForm()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Approve Skip Request</h3>
                </div>
                <form id="approvalForm" class="p-6">
                    <input type="hidden" name="skip_request_id" id="approvalRequestId">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Approval Notes (Optional)</label>
                        <textarea name="notes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideApprovalModal()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                            Approve Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Reject Skip Request</h3>
                </div>
                <form id="rejectionForm" class="p-6">
                    <input type="hidden" name="skip_request_id" id="rejectionRequestId">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason</label>
                        <textarea name="reason" required rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Please provide a reason for rejecting this request..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRejectionModal()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                            Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }

        // Modal functions
        function showRequestForm() {
            document.getElementById('requestModal').classList.remove('hidden');
            loadAdvanceOptions();
        }

        function hideRequestForm() {
            document.getElementById('requestModal').classList.add('hidden');
            document.getElementById('skipRequestForm').reset();
        }

        function showApprovalModal(requestId) {
            document.getElementById('approvalRequestId').value = requestId;
            document.getElementById('approvalModal').classList.remove('hidden');
        }

        function hideApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.getElementById('approvalForm').reset();
        }

        function showRejectionModal(requestId) {
            document.getElementById('rejectionRequestId').value = requestId;
            document.getElementById('rejectionModal').classList.remove('hidden');
        }

        function hideRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.getElementById('rejectionForm').reset();
        }

        // Action functions
        function approveRequest(requestId) {
            showApprovalModal(requestId);
        }

        function rejectRequest(requestId) {
            showRejectionModal(requestId);
        }

        function refreshData() {
            location.reload();
        }

        // Load advance options for the form
        function loadAdvanceOptions() {
            // This would typically make an AJAX call to get active advances
            // For now, we'll use a placeholder
            console.log('Loading advance options...');
        }

        // Form submissions
        document.getElementById('skipRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('actions/advance_skip_controller.php?action=request_skip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Skip request submitted successfully');
                    hideRequestForm();
                    refreshData();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the request');
            });
        });

        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('actions/advance_skip_controller.php?action=approve_skip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Skip request approved successfully');
                    hideApprovalModal();
                    refreshData();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while approving the request');
            });
        });

        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('actions/advance_skip_controller.php?action=reject_skip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Skip request rejected');
                    hideRejectionModal();
                    refreshData();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rejecting the request');
            });
        });
    </script>
</body>
</html>

