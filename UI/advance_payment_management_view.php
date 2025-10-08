<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Payment Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-pending { background-color: #f59e0b; }
        .status-approved { background-color: #10b981; }
        .status-active { background-color: #3b82f6; }
        .status-completed { background-color: #6b7280; }
        .status-cancelled { background-color: #ef4444; }
        
        .priority-low { border-left: 4px solid #10b981; }
        .priority-normal { border-left: 4px solid #3b82f6; }
        .priority-high { border-left: 4px solid #f59e0b; }
        .priority-urgent { border-left: 4px solid #ef4444; }
        
        .emergency-badge {
            animation: pulse 2s infinite;
            background: linear-gradient(45deg, #ef4444, #dc2626);
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <!-- Header -->
    <div class="bg-gray-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-white">Advance Payment Management</h1>
                    <p class="text-gray-300 mt-1">Complete lifecycle management for employee advance payments</p>
                </div>
                <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>New Payment Request</span>
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-blue-500 bg-opacity-20">
                        <i class="fas fa-file-alt text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Total Requests</p>
                        <p class="text-lg font-semibold text-blue-400"><?= $page_data['stats']['total_requests'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-green-500 bg-opacity-20">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Active</p>
                        <p class="text-lg font-semibold text-green-400"><?= $page_data['stats']['active_advances'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-yellow-500 bg-opacity-20">
                        <i class="fas fa-clock text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Pending</p>
                        <p class="text-lg font-semibold text-yellow-400"><?= $page_data['stats']['pending_requests'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-red-500 bg-opacity-20">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Emergency</p>
                        <p class="text-lg font-semibold text-red-400"><?= $page_data['stats']['emergency_requests'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-purple-500 bg-opacity-20">
                        <i class="fas fa-money-bill-wave text-purple-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Disbursed</p>
                        <p class="text-lg font-semibold text-purple-400">₹<?= number_format($page_data['stats']['total_amount_disbursed'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-orange-500 bg-opacity-20">
                        <i class="fas fa-hourglass-half text-orange-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Outstanding</p>
                        <p class="text-lg font-semibold text-orange-400">₹<?= number_format($page_data['stats']['total_outstanding'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-indigo-500 bg-opacity-20">
                        <i class="fas fa-calendar-alt text-indigo-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-gray-300 text-xs">Avg Monthly</p>
                        <p class="text-lg font-semibold text-indigo-400">₹<?= number_format($page_data['stats']['avg_monthly_deduction'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4 flex-wrap gap-3">
                    <div>
                        <input type="text" id="searchInput" placeholder="Search by request number, employee, purpose..." 
                               value="<?= htmlspecialchars($page_data['search'] ?? '') ?>"
                               class="bg-gray-700 border border-gray-600 text-white px-4 py-2 rounded-lg w-80 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mr-2">Status</label>
                        <select id="statusFilter" class="bg-gray-700 border border-gray-600 text-white px-3 py-2 rounded-lg">
                            <?php $selStatus = $page_data['filters']['status'] ?? ''; ?>
                            <option value="" <?= $selStatus===''?'selected':'' ?>>All</option>
                            <option value="pending" <?= $selStatus==='pending'?'selected':'' ?>>Pending</option>
                            <option value="approved" <?= $selStatus==='approved'?'selected':'' ?>>Approved</option>
                            <option value="active" <?= $selStatus==='active'?'selected':'' ?>>Active</option>
                            <option value="completed" <?= $selStatus==='completed'?'selected':'' ?>>Completed</option>
                            <option value="cancelled" <?= $selStatus==='cancelled'?'selected':'' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mr-2">Priority</label>
                        <select id="priorityFilter" class="bg-gray-700 border border-gray-600 text-white px-3 py-2 rounded-lg">
                            <?php $selPri = $page_data['filters']['priority'] ?? ''; ?>
                            <option value="" <?= $selPri===''?'selected':'' ?>>All</option>
                            <option value="low" <?= $selPri==='low'?'selected':'' ?>>Low</option>
                            <option value="normal" <?= $selPri==='normal'?'selected':'' ?>>Normal</option>
                            <option value="high" <?= $selPri==='high'?'selected':'' ?>>High</option>
                            <option value="urgent" <?= $selPri==='urgent'?'selected':'' ?>>Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mr-2">From</label>
                        <input type="date" id="dateFrom" value="<?= htmlspecialchars($page_data['filters']['date_from'] ?? '') ?>" class="bg-gray-700 border border-gray-600 text-white px-3 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mr-2">To</label>
                        <input type="date" id="dateTo" value="<?= htmlspecialchars($page_data['filters']['date_to'] ?? '') ?>" class="bg-gray-700 border border-gray-600 text-white px-3 py-2 rounded-lg">
                    </div>
                    <button onclick="performSearch()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search"></i>
                    </button>
                    <button onclick="exportData()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
                
                <div class="flex items-center space-x-2">
                    <span class="text-gray-300">Show:</span>
                    <select onchange="changePerPage(this.value)" class="bg-gray-700 border border-gray-600 text-white px-3 py-2 rounded-lg">
                        <option value="10" <?= ($page_data['pagination']['per_page'] ?? 10) == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= ($page_data['pagination']['per_page'] ?? 10) == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= ($page_data['pagination']['per_page'] ?? 10) == 50 ? 'selected' : '' ?>>50</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Payments List -->
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <?php if (empty($page_data['payments'])): ?>
                <div class="p-8 text-center text-gray-300">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p class="text-lg">No advance payment records found</p>
                    <p class="text-sm">Create a new payment request to get started</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Request Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Amount & Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Status & Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Timeline</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600">
                            <?php foreach ($page_data['payments'] as $payment): ?>
                                <tr class="hover:bg-gray-700 priority-<?= $payment['priority'] ?>">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-white font-medium"><?= htmlspecialchars($payment['request_number']) ?></div>
                                            <div class="text-gray-400 text-sm mt-1"><?= htmlspecialchars(substr($payment['purpose'], 0, 50)) ?><?= strlen($payment['purpose']) > 50 ? '...' : '' ?></div>
                                            <?php if ($payment['is_emergency']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium emergency-badge text-white mt-1">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    EMERGENCY
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-white font-medium"><?= htmlspecialchars($payment['employee_name']) ?></div>
                                            <div class="text-gray-400 text-sm"><?= htmlspecialchars($payment['employee_type']) ?></div>
                                            <div class="text-gray-400 text-sm">Salary: ₹<?= number_format($payment['employee_salary']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-white font-medium">₹<?= number_format($payment['amount'], 2) ?></div>
                                            <div class="text-gray-400 text-sm">
                                                Remaining: ₹<?= number_format($payment['remaining_balance'], 2) ?>
                                            </div>
                                            <div class="w-32 mt-2">
                                                <div class="flex justify-between text-xs mb-1">
                                                    <span class="text-white"><?= $payment['progress_percentage'] ?>%</span>
                                                    <span class="text-gray-400">
                                                        <?= $payment['paid_installments'] ?>/<?= $payment['original_installment_count'] ?? $payment['installment_count'] ?>
                                                        <?php if (($payment['total_skips'] ?? 0) > 0): ?>
                                                            <span class="text-yellow-400">(<?= $payment['total_skips'] ?> skipped)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="w-full bg-gray-600 rounded-full h-2">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $payment['progress_percentage'] ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full status-<?= $payment['status'] ?> text-white">
                                                <?= ucfirst($payment['status']) ?>
                                            </span>
                                            <div class="mt-1">
                                                <span class="text-<?= $payment['priority'] == 'urgent' ? 'red' : ($payment['priority'] == 'high' ? 'orange' : ($payment['priority'] == 'normal' ? 'blue' : 'green')) ?>-400 text-xs font-medium">
                                                    <?= strtoupper($payment['priority']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <?php if ($payment['start_date']): ?>
                                                <div class="text-gray-300">Started: <?= date('M d, Y', strtotime($payment['start_date'])) ?></div>
                                                <?php if ($payment['days_active'] > 0): ?>
                                                    <div class="text-gray-400"><?= $payment['days_active'] ?> days active</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-gray-400">Not started</div>
                                            <?php endif; ?>
                                            <?php if ($payment['completion_date']): ?>
                                                <div class="text-green-400">Completed: <?= date('M d, Y', strtotime($payment['completion_date'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <button onclick="viewPaymentDetails(<?= $payment['id'] ?>)" 
                                                    class="text-blue-400 hover:text-blue-300" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editPayment(<?= $payment['id'] ?>)" 
                                                    class="text-green-400 hover:text-green-300" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($payment['status'] == 'pending'): ?>
                                                <button onclick="approvePayment(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['employee_name']) ?>')" 
                                                        class="text-green-400 hover:text-green-300" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($payment['status'] == 'approved'): ?>
                                                <button onclick="activatePayment(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['employee_name']) ?>')" 
                                                        class="text-blue-400 hover:text-blue-300" title="Activate">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (in_array($payment['status'], ['active']) && $payment['remaining_balance'] > 0): ?>
                                                <button onclick="processDeduction(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['employee_name']) ?>')" 
                                                        class="text-purple-400 hover:text-purple-300" title="Process Deduction">
                                                    <i class="fas fa-minus-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!in_array($payment['status'], ['completed', 'cancelled'])): ?>
                                                <button onclick="cancelPayment(<?= $payment['id'] ?>, '<?= htmlspecialchars($payment['employee_name']) ?>')" 
                                                        class="text-red-400 hover:text-red-300" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php 
                    $currentPage = (int)($page_data['pagination']['current_page'] ?? 1);
                    $perPage = (int)($page_data['pagination']['per_page'] ?? 10);
                    $totalRecords = (int)($page_data['pagination']['total_records'] ?? 0);
                    $totalPages = max(1, (int)($page_data['pagination']['total_pages'] ?? 1));
                    $searchVal = urlencode($page_data['search'] ?? '');
                    $statusVal = urlencode($page_data['filters']['status'] ?? '');
                    $priorityVal = urlencode($page_data['filters']['priority'] ?? '');
                    $dateFromVal = urlencode($page_data['filters']['date_from'] ?? '');
                    $dateToVal = urlencode($page_data['filters']['date_to'] ?? '');
                    $baseQuery = "?page=advance-salary&per_page={$perPage}&search={$searchVal}&status={$statusVal}&priority={$priorityVal}&date_from={$dateFromVal}&date_to={$dateToVal}";
                ?>
                <div class="bg-gray-700 px-6 py-3 flex items-center justify-between">
                    <div class="text-sm text-gray-300">
                        Showing <?= ($totalRecords === 0) ? 0 : (($currentPage - 1) * $perPage) + 1 ?> 
                        to <?= min($currentPage * $perPage, $totalRecords) ?> 
                        of <?= $totalRecords ?> results
                    </div>
                    
                    <div class="flex items-center space-x-1">
                        <?php $prevPage = max(1, $currentPage - 1); $nextPage = min($totalPages, $currentPage + 1); ?>
                        <!-- First -->
                        <a href="<?= $currentPage > 1 ? $baseQuery . '&page_num=1' : 'javascript:void(0)' ?>" 
                           class="px-2 py-1 text-sm rounded <?= $currentPage > 1 ? 'bg-gray-600 hover:bg-gray-500 text-gray-200' : 'bg-gray-800 text-gray-500 cursor-not-allowed' ?>" title="First">
                            «
                        </a>
                        <!-- Prev -->
                        <a href="<?= $currentPage > 1 ? $baseQuery . '&page_num=' . $prevPage : 'javascript:void(0)' ?>" 
                           class="px-2 py-1 text-sm rounded <?= $currentPage > 1 ? 'bg-gray-600 hover:bg-gray-500 text-gray-200' : 'bg-gray-800 text-gray-500 cursor-not-allowed' ?>" title="Previous">
                            ‹
                        </a>

                        <!-- Page numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?= $baseQuery . '&page_num=' . $i ?>" 
                               class="px-3 py-1 text-sm rounded <?= $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-gray-600 text-gray-300 hover:bg-gray-500' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next -->
                        <a href="<?= $currentPage < $totalPages ? $baseQuery . '&page_num=' . $nextPage : 'javascript:void(0)' ?>" 
                           class="px-2 py-1 text-sm rounded <?= $currentPage < $totalPages ? 'bg-gray-600 hover:bg-gray-500 text-gray-200' : 'bg-gray-800 text-gray-500 cursor-not-allowed' ?>" title="Next">
                            ›
                        </a>
                        <!-- Last -->
                        <a href="<?= $currentPage < $totalPages ? $baseQuery . '&page_num=' . $totalPages : 'javascript:void(0)' ?>" 
                           class="px-2 py-1 text-sm rounded <?= $currentPage < $totalPages ? 'bg-gray-600 hover:bg-gray-500 text-gray-200' : 'bg-gray-800 text-gray-500 cursor-not-allowed' ?>" title="Last">
                            »
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4 text-white">New Advance Payment Request</h3>
            <form id="createForm" onsubmit="createPayment(event)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Employee *</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Amount *</label>
                        <input type="number" name="amount" step="0.01" min="1" required 
                               class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Installments</label>
                        <select name="installment_count" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3" selected>3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="5">5 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>
                    
                    <div class="mb-4 md:col-span-2">
                        <label class="flex items-center text-sm font-medium text-gray-300">
                            <input type="checkbox" name="is_emergency" value="1" class="mr-2 rounded">
                            Emergency Payment
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Purpose *</label>
                    <textarea name="purpose" rows="3" required
                              class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Please provide the reason for this advance payment..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2 text-gray-300 border border-gray-600 rounded-lg hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Create Payment Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-white">Payment Details</h3>
                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="detailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4 text-white">Edit Payment Request</h3>
            <form id="editForm" onsubmit="updatePayment(event)">
                <input type="hidden" id="edit-payment-id" name="id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Employee</label>
                        <input type="text" id="edit-employee-name" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-gray-300 rounded-lg" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Request Number</label>
                        <input type="text" id="edit-request-number" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-gray-300 rounded-lg" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Amount *</label>
                        <input type="number" id="edit-amount" name="amount" step="0.01" min="1" required 
                               class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Monthly Deduction *</label>
                        <input type="number" id="edit-monthly-deduction" name="monthly_deduction" step="0.01" min="0" required 
                               class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                        <select id="edit-priority" name="priority" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                        <select id="edit-status" name="status" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Installments</label>
                        <select id="edit-installment-count" name="installment_count" class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="5">5 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center text-sm font-medium text-gray-300">
                            <input type="checkbox" id="edit-is-emergency" name="is_emergency" value="1" class="mr-2 rounded">
                            Emergency Payment
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Purpose</label>
                    <textarea id="edit-purpose" name="purpose" rows="3" 
                              class="w-full px-3 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-300 border border-gray-600 rounded-lg hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let currentPaymentId = null;
        
        // Utility functions
        function showAlert(message, type = 'info') {
            const icons = {
                success: '✅',
                error: '❌',
                info: 'ℹ️',
                warning: '⚠️'
            };
            alert(`${icons[type] || ''} ${message}`);
        }
        
        // Modal functions
        function openCreateModal() {
            loadEmployeesList();
            document.getElementById('createModal').classList.remove('hidden');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
            document.getElementById('createForm').reset();
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editForm').reset();
        }
        
        // Load employees list
        function loadEmployeesList() {
            const select = document.querySelector('#createForm select[name="employee_id"]');
            select.innerHTML = '<option value="">Loading employees...</option>';
            
            fetch('?page=advance-salary-employees')
            .then(response => response.json())
            .then(data => {
                select.innerHTML = '<option value="">Select Employee</option>';
                if (data.success && data.employees) {
                    data.employees.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.textContent = `${emp.name} (${emp.user_type}) - ₹${parseFloat(emp.salary).toLocaleString()}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading employees:', error);
                select.innerHTML = '<option value="">Error loading employees</option>';
            });
        }
        
        // CRUD Operations
        function createPayment(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creating...';
            submitBtn.disabled = true;
            
            fetch('?page=create-advance-salary', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    closeCreateModal();
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                console.error('Create Error:', error);
                showAlert('Failed to create payment request: ' + error.message, 'error');
            });
        }
        
        function viewPaymentDetails(id) {
            fetch(`?page=advance-salary-details&id=${id}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayPaymentDetails(data.payment, data.transactions || []);
                    document.getElementById('detailsModal').classList.remove('hidden');
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('View Details Error:', error);
                showAlert('Failed to load payment details: ' + error.message, 'error');
            });
        }
        
        function displayPaymentDetails(payment, transactions) {
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-blue-400">Payment Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Request Number</label>
                            <p class="text-white font-mono">${payment.request_number}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Employee</label>
                            <p class="text-white">${payment.employee_name} (${payment.employee_type})</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Total Amount</label>
                            <p class="text-white text-xl font-bold">₹${parseFloat(payment.amount).toLocaleString()}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Monthly Deduction</label>
                            <p class="text-white">₹${parseFloat(payment.monthly_deduction).toLocaleString()}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Remaining Balance</label>
                            <p class="text-white">₹${parseFloat(payment.remaining_balance).toLocaleString()}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-green-400">Status & Progress</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Status</label>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full status-${payment.status} text-white">
                                ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                            </span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Priority</label>
                            <span class="text-${payment.priority == 'urgent' ? 'red' : (payment.priority == 'high' ? 'orange' : (payment.priority == 'normal' ? 'blue' : 'green'))}-400 font-semibold">
                                ${payment.priority.charAt(0).toUpperCase() + payment.priority.slice(1)}
                            </span>
                            ${payment.is_emergency == '1' ? '<span class="ml-2 text-red-400"><i class="fas fa-exclamation-triangle"></i> Emergency</span>' : ''}
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Progress</label>
                            <div class="w-full bg-gray-200 rounded-full h-4 mt-2">
                                <div class="bg-blue-600 h-4 rounded-full" style="width: ${payment.progress_percentage}%"></div>
                            </div>
                            <p class="text-sm text-gray-400 mt-1">${payment.progress_percentage}% completed (${payment.paid_installments}/${payment.installment_count} installments)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300">Installment Plan</label>
                            <p class="text-white">${payment.installment_count} months</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-300">Purpose</label>
                    <p class="text-gray-300 bg-gray-700 p-3 rounded mt-2">${payment.purpose}</p>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Requested By</label>
                        <p class="text-gray-300">${payment.requested_by_name || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Approved By</label>
                        <p class="text-gray-300">${payment.approved_by_name || 'Not approved yet'}</p>
                    </div>
                </div>
                
                ${payment.status === 'cancelled' && payment.cancel_reason ? `
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-300">Cancel Reason</label>
                    <p class="text-red-300 bg-gray-700 p-3 rounded mt-2">${payment.cancel_reason}</p>
                </div>
                ` : ''}
                
                ${transactions.length > 0 ? `
                <div class="mt-6">
                    <h4 class="text-lg font-semibold text-purple-400 mb-4">Transaction History</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-200">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-200">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-200">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-200">Installment</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-200">Processed By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                ${transactions.map(tx => `
                                <tr>
                                    <td class="px-4 py-2 text-sm text-white">${new Date(tx.payment_date).toLocaleDateString()}</td>
                                    <td class="px-4 py-2 text-sm text-white">${tx.transaction_type}</td>
                                    <td class="px-4 py-2 text-sm text-white">₹${parseFloat(tx.amount).toLocaleString()}</td>
                                    <td class="px-4 py-2 text-sm text-white">#${tx.installment_number}</td>
                                    <td class="px-4 py-2 text-sm text-white">${tx.processed_by_name || 'N/A'}</td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
            `;
            document.getElementById('detailsContent').innerHTML = content;
        }
        
        function editPayment(id) {
            fetch(`?page=advance-salary-details&id=${id}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    populateEditModal(data.payment);
                    document.getElementById('editModal').classList.remove('hidden');
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Edit Load Error:', error);
                showAlert('Failed to load payment details for editing: ' + error.message, 'error');
            });
        }
        
        function populateEditModal(payment) {
            document.getElementById('edit-payment-id').value = payment.id;
            document.getElementById('edit-employee-name').value = payment.employee_name;
            document.getElementById('edit-request-number').value = payment.request_number;
            document.getElementById('edit-amount').value = parseFloat(payment.amount);
            document.getElementById('edit-monthly-deduction').value = parseFloat(payment.monthly_deduction);
            document.getElementById('edit-priority').value = payment.priority;
            document.getElementById('edit-status').value = payment.status;
            document.getElementById('edit-installment-count').value = payment.installment_count;
            document.getElementById('edit-is-emergency').checked = payment.is_emergency == '1';
            document.getElementById('edit-purpose').value = payment.purpose || '';
        }
        
        function updatePayment(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
            
            fetch('?page=update-advance-salary', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    closeEditModal();
                    showAlert(data.message, 'success');
                    location.reload();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                console.error('Update Error:', error);
                showAlert('Failed to update payment: ' + error.message, 'error');
            });
        }
        
        function approvePayment(id, employeeName) {
            if (confirm(`Approve advance payment request for ${employeeName}?`)) {
                fetch('?page=approve-advance-salary', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: id }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        location.reload();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Approve Error:', error);
                    showAlert('Failed to approve payment: ' + error.message, 'error');
                });
            }
        }
        
        function activatePayment(id, employeeName) {
            if (confirm(`Activate advance payment for ${employeeName}?`)) {
                fetch('?page=activate-advance-salary', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: id }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        location.reload();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Activate Error:', error);
                    showAlert('Failed to activate payment: ' + error.message, 'error');
                });
            }
        }
        
        function cancelPayment(id, employeeName) {
            const reason = prompt(`Enter cancel reason for ${employeeName}:`);
            if (reason === null) { return; }
            if (String(reason).trim().length === 0) { 
                showAlert('Cancel reason is required', 'error');
                return; 
            }
            if (confirm(`Are you sure you want to cancel the advance payment for ${employeeName}?`)) {
                fetch('?page=cancel-advance-salary', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: id, reason: reason }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        location.reload();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Cancel Error:', error);
                    showAlert('Failed to cancel payment: ' + error.message, 'error');
                });
            }
        }
        
        function processDeduction(id, employeeName) {
            const amount = prompt(`Enter deduction amount for ${employeeName}:`);
            if (amount && parseFloat(amount) > 0) {
                fetch('?page=process-deduction-advance-salary', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ 
                        id: id, 
                        amount: parseFloat(amount)
                    }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        location.reload();
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Process Deduction Error:', error);
                    showAlert('Failed to process deduction: ' + error.message, 'error');
                });
            }
        }
        
        // Search and utility functions
        function performSearch() {
            const url = new URL(window.location);
            url.searchParams.set('search', document.getElementById('searchInput').value);
            url.searchParams.set('status', document.getElementById('statusFilter').value);
            url.searchParams.set('priority', document.getElementById('priorityFilter').value);
            url.searchParams.set('date_from', document.getElementById('dateFrom').value);
            url.searchParams.set('date_to', document.getElementById('dateTo').value);
            url.searchParams.set('page_num', '1');
            window.location.href = url.toString();
        }
        
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page_num', '1');
            // preserve filters
            url.searchParams.set('search', document.getElementById('searchInput').value);
            url.searchParams.set('status', document.getElementById('statusFilter').value);
            url.searchParams.set('priority', document.getElementById('priorityFilter').value);
            url.searchParams.set('date_from', document.getElementById('dateFrom').value);
            url.searchParams.set('date_to', document.getElementById('dateTo').value);
            window.location.href = url.toString();
        }
        
        function exportData() {
            const url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('page', 'export-advance-salary');
            url.searchParams.set('format', 'csv');
            url.searchParams.set('search', document.getElementById('searchInput').value);
            url.searchParams.set('status', document.getElementById('statusFilter').value);
            url.searchParams.set('priority', document.getElementById('priorityFilter').value);
            url.searchParams.set('date_from', document.getElementById('dateFrom').value);
            url.searchParams.set('date_to', document.getElementById('dateTo').value);
            window.open(url.toString(), '_blank');
        }
        
        // Enter key search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        // Close modals on outside click
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
                event.target.classList.add('hidden');
                const formIds = ['createForm', 'editForm'];
                formIds.forEach(formId => {
                    const form = document.getElementById(formId);
                    if (form) form.reset();
                });
            }
        });
    </script>
</body>
</html>