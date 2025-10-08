<?php
// UI/client_billing_view.php
require_once __DIR__ . '/../helpers/database.php';

// Check if client ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="bg-red-600 text-white p-4 rounded mb-4">Invalid client ID.</div>';
    exit;
}

$client_id = (int)$_GET['id'];
$db = new Database();

// Get client details
$client_query = "
    SELECT 
        s.*, 
        ct.type_name as client_type
    FROM 
        society_onboarding_data s
    LEFT JOIN 
        client_types ct ON s.client_type_id = ct.id
    WHERE 
        s.id = ?";
        
$client = $db->query($client_query, [$client_id])->fetch();

if (!$client) {
    echo '<div class="bg-red-600 text-white p-4 rounded mb-4">Client not found.</div>';
    exit;
}

// Get client invoices
$invoices_query = "
    SELECT 
        i.*
    FROM 
        invoices i
    WHERE 
        i.client_id = ?
    ORDER BY 
        i.month DESC";
        
$invoices = $db->query($invoices_query, [$client_id])->fetchAll();

// Format month names using PHP and calculate correct totals
foreach ($invoices as &$invoice) {
    $date = DateTime::createFromFormat('Y-m', $invoice['month']);
    $invoice['formatted_month'] = $date ? $date->format('F Y') : 'Invalid Date';
    
    // Calculate the correct total using the same logic as invoice template
    $subtotal = $invoice['amount']; // Base amount from invoice items
    
    // Calculate GST amount if applicable
    $gstAmount = $invoice['is_gst_applicable'] ? round($subtotal * 0.18, 2) : 0;
    
    // Calculate pre-round total (subtotal + GST if applicable)
    $preRoundTotal = $invoice['is_gst_applicable'] ? ($subtotal + $gstAmount) : $subtotal;
    
    // Round to nearest complete rupee
    $roundedTotal = round($preRoundTotal);
    
    // Calculate round-off amount (difference between rounded and pre-round total)
    $roundOff = round($roundedTotal - $preRoundTotal, 2);
    
    // Final total is the rounded amount
    $invoice['calculated_total'] = $roundedTotal;
    $invoice['calculated_gst'] = $gstAmount;
    $invoice['calculated_round_off'] = $roundOff;
}

// Count statistics using calculated totals
$totalAmount = array_sum(array_column($invoices, 'calculated_total'));
$pendingAmount = array_sum(array_map(function($inv) { 
    return $inv['status'] === 'pending' ? $inv['calculated_total'] : 0; 
}, $invoices));
$paidAmount = $totalAmount - $pendingAmount;
?>

<div class="bg-gray-800 rounded-lg shadow-lg p-6">
    <!-- Client Header -->
    <div class="flex flex-col md:flex-row justify-between items-start mb-6 border-b border-gray-700 pb-6">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <a href="index.php?page=billing-dashboard" class="text-blue-400 hover:text-blue-300">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($client['society_name']); ?></h1>
                <span class="bg-gray-700 text-gray-300 text-xs px-2 py-1 rounded">
                    <?php echo htmlspecialchars($client['client_type'] ?? 'No Type'); ?>
                </span>
                <?php if($client['compliance_status'] == 1): ?>
                <span class="bg-green-900 text-green-300 text-xs px-2 py-1 rounded">
                    Compliant
                </span>
                <?php else: ?>
                <span class="bg-yellow-900 text-yellow-300 text-xs px-2 py-1 rounded">
                    Non-Compliant
                </span>
                <?php endif; ?>
                <?php if($client['is_gst_applicable'] == 1): ?>
                <span class="bg-blue-900 text-blue-300 text-xs px-2 py-1 rounded">
                    GST Applicable
                </span>
                <?php else: ?>
                <span class="bg-purple-900 text-purple-300 text-xs px-2 py-1 rounded">
                    No GST
                </span>
                <?php endif; ?>
            </div>
            <p class="text-gray-400">
                <?php echo htmlspecialchars($client['street_address'] . ', ' . $client['city'] . ', ' . $client['state'] . ' - ' . $client['pin_code']); ?>
            </p>
        </div>
        <div class="mt-4 md:mt-0">
            <button id="create-invoice-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
                <i class="fas fa-plus"></i> Create Manual Invoice
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Total Billed</span>
            <span class="text-3xl font-bold text-white">₹<?php echo number_format($totalAmount, 2); ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Pending</span>
            <span class="text-3xl font-bold text-red-400">₹<?php echo number_format($pendingAmount, 2); ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Paid</span>
            <span class="text-3xl font-bold text-green-400">₹<?php echo number_format($paidAmount, 2); ?></span>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-gray-900 rounded-lg overflow-hidden">
            <thead class="bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Month</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Payment Info</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                <?php if (empty($invoices)) : ?>
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400">No invoices found for this client.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($invoices as $invoice) : ?>
                        <tr class="hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($invoice['formatted_month']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-semibold text-white">₹<?php echo number_format($invoice['calculated_total'], 2); ?></div>
                                <?php if($invoice['is_gst_applicable']): ?>
                                <div class="text-xs text-gray-400">
                                    Base: ₹<?php echo number_format($invoice['amount'], 2); ?> <br>
                                    GST: ₹<?php echo number_format($invoice['calculated_gst'], 2); ?> <br>
                                    Round: ₹<?php echo number_format($invoice['calculated_round_off'], 2); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($invoice['status'] === 'pending') : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-300">Pending</span>
                                <?php else : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-300">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php 
                                $typeBadgeClass = '';
                                switch($invoice['generation_type']) {
                                    case 'auto':
                                        $typeBadgeClass = 'bg-blue-900 text-blue-300';
                                        break;
                                    case 'manual':
                                        $typeBadgeClass = 'bg-purple-900 text-purple-300';
                                        break;
                                    case 'modified':
                                        $typeBadgeClass = 'bg-yellow-900 text-yellow-300';
                                        break;
                                }
                                ?>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $typeBadgeClass; ?>">
                                    <?php echo ucfirst($invoice['generation_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($invoice['status'] === 'paid') : ?>
                                    <div class="flex items-center justify-between">
                                        <div class="text-xs text-gray-300 flex-1">
                                            <div><strong>Date:</strong> <?php echo date('d M Y', strtotime($invoice['paid_at'])); ?></div>
                                            <?php if (!empty($invoice['payment_method'])) : ?>
                                                <div><strong>Method:</strong> <?php echo htmlspecialchars($invoice['payment_method']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($invoice['amount_received'])) : ?>
                                                <div><strong>Received:</strong> ₹<?php echo number_format($invoice['amount_received'], 2); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($invoice['tds_amount']) && $invoice['tds_amount'] > 0) : ?>
                                                <div><strong>TDS:</strong> ₹<?php echo number_format($invoice['tds_amount'], 2); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($invoice['short_balance']) && $invoice['short_balance'] != 0) : ?>
                                                <div><strong>Balance:</strong> ₹<?php echo number_format($invoice['short_balance'], 2); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <button onclick="showPaymentDetails(<?php echo $invoice['id']; ?>)" 
                                                class="ml-2 p-1 text-blue-400 hover:text-blue-300 hover:bg-blue-900/20 rounded transition-all duration-200"
                                                title="View Payment Details">
                                            <i class="fas fa-info-circle text-sm"></i>
                                        </button>
                                    </div>
                                <?php else : ?>
                                    <span class="text-xs text-gray-500">No payment recorded</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <?php if ($invoice['status'] === 'pending') : ?>
                                        <button data-invoice-id="<?php echo $invoice['id']; ?>" class="mark-paid-btn text-green-400 hover:text-green-300 transition" title="Mark as Paid">
                                            <i class="fas fa-check-circle"></i> Paid
                                        </button>
                                        <button data-invoice-id="<?php echo $invoice['id']; ?>" class="edit-invoice-btn text-yellow-400 hover:text-yellow-300 transition">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                    <a href="actions/invoice_controller.php?action=view_new_template&id=<?php echo $invoice['id']; ?>" target="_blank" class="text-blue-400 hover:text-blue-300 transition">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="actions/invoice_controller.php?action=view_new_template&id=<?php echo $invoice['id']; ?>&autopdf=1" target="_blank" class="text-blue-400 hover:text-blue-300 transition">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<!-- Modal for Creating/Editing Invoice -->
<div id="invoice-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col modal-content">
        <!-- Modal Header -->
        <div class="flex justify-between items-center border-b border-gray-700 px-6 py-4 bg-gray-900 rounded-t-xl">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice text-white text-sm"></i>
                </div>
                <h3 class="text-xl font-semibold text-white" id="modal-title">Create Invoice</h3>
            </div>
            <button class="text-gray-400 hover:text-white transition-colors p-2 hover:bg-gray-700 rounded-lg" onclick="closeModal()">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body - Scrollable -->
        <div class="flex-1 overflow-y-auto modal-scrollbar">
            <form id="invoice-form" class="p-6 space-y-6">
            <input type="hidden" id="invoice-id" name="invoice_id">
            <input type="hidden" id="client-id" name="client_id" value="<?php echo $client_id; ?>">

                <!-- Basic Information Section -->
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                    <h4 class="text-lg font-medium text-white mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                        Basic Information
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="invoice-month" class="block text-sm font-medium text-gray-400 mb-2">
                                Invoice Month <span class="text-red-400">*</span>
                            </label>
                            <input type="month" id="invoice-month" name="month" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm 
                                          focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all duration-200
                                          hover:border-gray-500" required>
            </div>
            
                        <div class="flex items-center justify-center">
                            <label class="flex items-center cursor-pointer bg-gray-800 rounded-lg p-3 border border-gray-700 hover:border-blue-500 transition-colors">
                                <input type="checkbox" id="is-gst-applicable" name="is_gst_applicable" value="1" 
                                       <?php echo ($client['is_gst_applicable'] || $client['compliance_status']) ? 'checked' : ''; ?>
                           <?php echo $client['compliance_status'] ? 'disabled' : ''; ?> 
                                       class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500 focus:ring-2">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-white">Apply 18% GST</span>
                        <?php if ($client['compliance_status']): ?>
                                        <div class="text-xs text-yellow-400 mt-1">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Required for compliant clients
                                        </div>
                        <?php endif; ?>
                                </div>
                </label>
                        </div>
                    </div>
            </div>
            
                <!-- Invoice Items Section -->
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                    <h4 class="text-lg font-medium text-white mb-4 flex items-center">
                        <i class="fas fa-list text-blue-400 mr-2"></i>
                        Invoice Items
                    </h4>
                    
                    <!-- Items Header -->
                    <div class="grid grid-cols-12 gap-3 mb-3 pb-2 border-b border-gray-700">
                        <div class="col-span-5">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Service</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Quantity</span>
                        </div>
                        <div class="col-span-3">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Rate (₹)</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Amount</span>
                        </div>
                    </div>
                    
                    <div id="invoice-items" class="space-y-3">
                    <!-- Employee types based on client profile -->
                    <?php
                    $employee_types = [
                            'guards' => ['label' => 'Security Guards', 'icon' => 'fas fa-shield-alt', 'rate_field' => 'guard_client_rate'],
                            'dogs' => ['label' => 'Security Dogs', 'icon' => 'fas fa-dog', 'rate_field' => 'dog_client_rate'],
                            'armed_guards' => ['label' => 'Armed Guards', 'icon' => 'fas fa-shield', 'rate_field' => 'armed_client_rate'],
                            'housekeeping' => ['label' => 'Housekeeping', 'icon' => 'fas fa-broom', 'rate_field' => 'housekeeping_client_rate'],
                            'bouncers' => ['label' => 'Bouncers', 'icon' => 'fas fa-user-shield', 'rate_field' => 'bouncer_client_rate'],
                            'site_supervisors' => ['label' => 'Site Supervisors', 'icon' => 'fas fa-user-tie', 'rate_field' => 'site_supervisor_client_rate'],
                            'supervisors' => ['label' => 'Supervisors', 'icon' => 'fas fa-user-cog', 'rate_field' => 'supervisor_client_rate']
                    ];
                    
                    foreach ($employee_types as $type_key => $type_info) {
                        if ($client[$type_key] > 0) {
                                $quantity = htmlspecialchars($client[$type_key]);
                                $rate = htmlspecialchars($client[$type_info['rate_field']]);
                                $amount = $quantity * $rate;
                                
                                echo '<div class="invoice-item bg-gray-800 rounded-lg p-3 border border-gray-700 hover:border-blue-500 transition-colors">
                                    <input type="hidden" name="item_type[]" value="' . htmlspecialchars($type_key) . '">
                                        <div class="grid grid-cols-12 gap-3 items-center">
                                            <div class="col-span-5">
                                                <div class="flex items-center space-x-2">
                                                    <i class="' . $type_info['icon'] . ' text-blue-400 text-sm"></i>
                                                    <span class="text-sm font-medium text-white">' . htmlspecialchars($type_info['label']) . '</span>
                                    </div>
                                    </div>
                                            <div class="col-span-2">
                                                <input type="number" name="item_quantity[]" value="' . $quantity . '" 
                                                       class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                                                       onchange="calculateItemAmount(this)">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="number" step="0.01" name="item_rate[]" value="' . $rate . '" 
                                                       class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                                              focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                                                       onchange="calculateItemAmount(this)">
                                            </div>
                                            <div class="col-span-2">
                                                <div class="text-sm font-semibold text-green-400 item-amount">₹' . number_format($amount, 2) . '</div>
                                            </div>
                                    </div>
                                </div>';
                        }
                    }
                    ?>
                </div>
                    
                <!-- Add Custom Item Button -->
                    <button type="button" id="add-item-btn" class="mt-4 w-full bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-blue-500 text-blue-400 hover:text-blue-300 font-medium py-2 px-4 rounded-lg transition-all flex items-center justify-center">
                        <i class="fas fa-plus-circle mr-2"></i> Add Custom Item
                </button>
                    
                    <!-- Invoice Total Summary -->
                    <div class="mt-6 bg-gray-800 rounded-lg p-4 border border-gray-700">
                        <h5 class="text-sm font-medium text-gray-400 mb-3">Invoice Summary</h5>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">Subtotal:</span>
                                <span class="text-sm font-semibold text-white" id="invoice-total">₹0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">GST (18%):</span>
                                <span class="text-sm font-semibold text-blue-400" id="invoice-gst">₹0.00</span>
                            </div>
                            <div class="border-t border-gray-600 pt-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-base font-medium text-white">Grand Total:</span>
                                    <span class="text-lg font-bold text-green-400" id="invoice-grand-total">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            </div>
            
        <!-- Modal Footer -->
        <div class="border-t border-gray-700 px-6 py-4 bg-gray-900 rounded-b-xl">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    All fields marked with <span class="text-red-400">*</span> are required
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2.5 px-6 rounded-lg 
                                   transition-all duration-200 flex items-center space-x-2 border border-gray-500 hover:border-gray-400">
                        <i class="fas fa-times text-sm"></i>
                        <span>Cancel</span>
                </button>
                    <button type="submit" form="invoice-form" id="invoice-submit-btn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg 
                                   transition-all duration-200 flex items-center space-x-2 shadow-lg hover:shadow-blue-500/25">
                        <i class="fas fa-save text-sm"></i>
                        <span>Save Invoice</span>
                </button>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Marking Invoice as Paid -->
<div id="payment-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl modal-content border border-gray-700">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-8 py-6 bg-gradient-to-r from-blue-600 to-blue-700 rounded-t-xl">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-credit-card text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-white">Mark Invoice as Paid</h3>
                    <p class="text-blue-100 text-sm">Record payment details</p>
                </div>
            </div>
            <button class="text-white/80 hover:text-white transition-all duration-200 p-3 hover:bg-white/10 rounded-lg" onclick="closePaymentModal()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-8">
            <form id="payment-form" class="space-y-6">
                <input type="hidden" id="payment-invoice-id" name="invoice_id">
                
                <!-- Payment Details Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Payment Date -->
                    <div class="space-y-2">
                        <label for="payment-date" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-calendar-alt text-blue-400 mr-2"></i>
                            Payment Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" id="payment-date" name="paid_at" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 text-sm 
                                      focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                      hover:border-gray-500" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="space-y-2">
                        <label for="payment-method" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-credit-card text-blue-400 mr-2"></i>
                            Payment Method <span class="text-red-400">*</span>
                        </label>
                        <select id="payment-method" name="payment_method" 
                                class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 text-sm 
                                       focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                       hover:border-gray-500" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">💵 Cash</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                            <option value="UPI">📱 UPI</option>
                            <option value="Cheque">📝 Cheque</option>
                            <option value="Other">🔄 Other</option>
                        </select>
                    </div>
                </div>
                
                <!-- Financial Details Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- TDS Amount -->
                    <div class="space-y-2">
                        <label for="tds-amount" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-calculator text-blue-400 mr-2"></i>
                            TDS Amount <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg">₹</span>
                            <input type="number" step="0.01" id="tds-amount" name="tds_amount" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-4 py-3 text-sm 
                                          focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                          hover:border-gray-500" 
                                   placeholder="0.00" value="0.00" required>
                        </div>
                    </div>
                    
                    <!-- Amount Received -->
                    <div class="space-y-2">
                        <label for="amount-received" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-money-bill-wave text-blue-400 mr-2"></i>
                            Amount Received <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg">₹</span>
                            <input type="number" step="0.01" id="amount-received" name="amount_received" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-4 py-3 text-sm 
                                          focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                          hover:border-gray-500" 
                                   placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <!-- Short Balance -->
                    <div class="space-y-2">
                        <label for="short-balance" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-balance-scale text-blue-400 mr-2"></i>
                            Short Balance <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg">₹</span>
                            <input type="number" step="0.01" id="short-balance" name="short_balance" 
                                   class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-4 py-3 text-sm 
                                          focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                          hover:border-gray-500" 
                                   placeholder="0.00" value="0.00" required readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Notes -->
                <div class="space-y-2">
                    <label for="payment-notes" class="block text-sm font-medium text-gray-300">
                        <i class="fas fa-sticky-note text-blue-400 mr-2"></i>
                        Payment Notes <span class="text-gray-500 text-xs">(Optional)</span>
                    </label>
                    <textarea id="payment-notes" name="payment_notes" rows="3" 
                              class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 text-sm 
                                     focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all duration-200
                                     hover:border-gray-500 resize-none" 
                              placeholder="Enter any additional notes about this payment..."></textarea>
                </div>
            </form>
        </div>
            
        <!-- Modal Footer -->
        <div class="border-t border-gray-700 px-6 py-4 bg-gray-900 rounded-b-xl">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-400 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-400"></i>
                    All fields marked with <span class="text-red-400 font-semibold">*</span> are required
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closePaymentModal()" 
                            class="px-6 py-2.5 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition-all duration-200 text-sm font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" form="payment-form" id="payment-submit-btn"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all duration-200 text-sm font-medium 
                                   shadow-lg hover:shadow-blue-500/25">
                        <i class="fas fa-check mr-2"></i>Confirm Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Overlay Modal -->
<div id="payment-details-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] modal-content border border-gray-700 flex flex-col">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 rounded-t-xl flex-shrink-0">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-receipt text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Payment Details</h3>
                    <p class="text-blue-100 text-xs">Complete payment information</p>
                </div>
            </div>
            <button class="text-white/80 hover:text-white transition-all duration-200 p-1.5 hover:bg-white/10 rounded-lg" onclick="closePaymentDetailsModal()">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        
        <!-- Modal Body - Scrollable -->
        <div class="flex-1 overflow-y-auto p-4">
            <div id="payment-details-content" class="space-y-3">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
// Immediately execute script to fix sidebar navigation issue
(function() {
    // Find all sidebar links
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    
    // Temporarily store their href values
    const links = Array.from(sidebarLinks).map(link => {
        return {
            element: link,
            href: link.getAttribute('href')
        };
    });
    
    // For links that are <a> tags with href attributes
    links.forEach(link => {
        if (link.href && link.element.tagName === 'A') {
            // Create a clean click handler
            link.element.onclick = function(e) {
                // Only if it's a real URL
                if (link.href && !link.href.startsWith('#')) {
                    window.location.href = link.href;
                    return false; // Prevent default and stop propagation
                }
            };
        }
    });
})();

// The rest of your script follows
document.addEventListener('DOMContentLoaded', function() {
    // Initialize other UI components
    const createInvoiceBtn = document.getElementById('create-invoice-btn');
    const invoiceModal = document.getElementById('invoice-modal');
    const invoiceForm = document.getElementById('invoice-form');
    const paymentModal = document.getElementById('payment-modal');
    const paymentForm = document.getElementById('payment-form');
    const addItemBtn = document.getElementById('add-item-btn');
    
    // Open modal for creating new invoice
    createInvoiceBtn.addEventListener('click', function() {
        document.getElementById('modal-title').textContent = 'Create Manual Invoice';
        document.getElementById('invoice-id').value = '';
        document.getElementById('invoice-month').value = '';
        // Reset the form
        invoiceForm.reset();
        openModal();
    });
    
    // Add event listener for GST checkbox change
    const gstCheckbox = document.getElementById('is-gst-applicable');
    if (gstCheckbox) {
        gstCheckbox.addEventListener('change', function() {
            calculateTotalAmount();
        });
    }
    
    // Calculate initial totals on page load
    calculateTotalAmount();
    
    // Add new item to invoice
    addItemBtn.addEventListener('click', function() {
        const itemsContainer = document.getElementById('invoice-items');
        const newItemRow = document.createElement('div');
        newItemRow.className = 'space-y-3';
        
        newItemRow.innerHTML = `
            <div class="invoice-item bg-gray-800 rounded-lg p-3 border border-gray-700 hover:border-blue-500 transition-colors">
                <div class="grid grid-cols-12 gap-3 items-center">
                    <div class="col-span-5">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-cog text-blue-400 text-sm"></i>
                            <input type="text" name="item_type[]" placeholder="Custom Service" 
                                   class="w-full bg-transparent border-none text-white text-sm 
                                          focus:outline-none placeholder-gray-400"
                                   style="background: transparent; border: none; outline: none;">
            </div>
                    </div>
                    <div class="col-span-2">
                <input type="number" name="item_quantity[]" value="1" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                      focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                               onchange="calculateItemAmount(this)">
            </div>
                    <div class="col-span-3">
                <input type="number" step="0.01" name="item_rate[]" value="0.00" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                      focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                               onchange="calculateItemAmount(this)">
                    </div>
                    <div class="col-span-2">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-green-400 item-amount">₹0.00</div>
                            <button type="button" class="text-red-400 hover:text-red-300 p-1" 
                        onclick="removeItem(this)">
                    <i class="fas fa-times"></i>
                </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        itemsContainer.appendChild(newItemRow);
        
        // Calculate totals after adding new item
        calculateTotalAmount();
    });
    
    // Submit invoice form
    invoiceForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(invoiceForm);
        const isEditing = !!formData.get('invoice_id');
        
        // Convert FormData to JSON
        const jsonData = {};
        formData.forEach((value, key) => {
            if (jsonData[key]) {
                if (!Array.isArray(jsonData[key])) {
                    jsonData[key] = [jsonData[key]];
                }
                jsonData[key].push(value);
            } else {
                jsonData[key] = value;
            }
        });
        
        // Process items separately
        const items = [];
        const types = formData.getAll('item_type[]');
        const quantities = formData.getAll('item_quantity[]');
        const rates = formData.getAll('item_rate[]');
        
        // Validate that we have items
        if (types.length === 0) {
            showToast('Please add at least one invoice item', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            return;
        }
        
        for (let i = 0; i < types.length; i++) {
            // Validate each item has required fields
            if (!types[i] || !quantities[i] || !rates[i]) {
                showToast('Please fill in all item fields', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                return;
            }
            
            items.push({
                employee_type: types[i],
                quantity: parseFloat(quantities[i]) || 0,
                rate: parseFloat(rates[i]) || 0
            });
        }
        
        jsonData.items = items;
        delete jsonData['item_type[]'];
        delete jsonData['item_quantity[]'];
        delete jsonData['item_rate[]'];
        
        // Handle GST checkbox
        const isGstApplicable = document.getElementById('is-gst-applicable');
        if (isGstApplicable) {
            jsonData.is_gst_applicable = isGstApplicable.checked ? 1 : 0;
        }
        
        // Get submit button and original text for loading state
        const submitBtn = document.getElementById('invoice-submit-btn');
        const originalBtnText = submitBtn.innerHTML;
        
        // Validate required fields
        if (!jsonData.client_id || !jsonData.month) {
            showToast('Please fill in all required fields', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        // Send to server
        fetch(`actions/invoice_controller.php?action=${isEditing ? 'update_invoice' : 'create_invoice'}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal();
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(data.message || 'An error occurred', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while processing your request: ' + error.message, 'error');
        })
        .finally(() => {
            // Always restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
    
    // Payment form submission
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Payment form submitted');
        
        // Get form data
        const formData = new FormData(paymentForm);
        const jsonData = {};
        formData.forEach((value, key) => {
            jsonData[key] = value;
        });
        
        console.log('Form data:', jsonData);
        
        // Show loading state
        const submitBtn = document.getElementById('payment-submit-btn');
        if (!submitBtn) {
            console.error('Submit button not found!');
            showToast('Submit button not found', 'error');
            return;
        }
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        console.log('Sending request to mark_as_paid API...');
        
        // Send to server
        fetch('actions/invoice_controller.php?action=mark_as_paid', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Payment marked as paid successfully');
                showToast(data.message, 'success');
                closePaymentModal();
                // Refresh the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                console.error('Payment failed:', data.message);
                showToast(data.message || 'An error occurred', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showToast('An error occurred while processing payment', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });

    // Mark as Paid functionality
    const markPaidButtons = document.querySelectorAll('.mark-paid-btn');
    console.log('Found', markPaidButtons.length, 'mark-paid buttons');
    
    markPaidButtons.forEach((button, index) => {
        console.log('Setting up mark-paid button', index, 'with ID:', button.getAttribute('data-invoice-id'));
        button.addEventListener('click', function() {
            console.log('Mark-paid button clicked for invoice ID:', this.getAttribute('data-invoice-id'));
            const invoiceId = this.getAttribute('data-invoice-id');
            document.getElementById('payment-invoice-id').value = invoiceId;
            
            // Get invoice total from the table row (now using calculated total)
            const row = this.closest('tr');
            const amountCell = row.querySelector('td:nth-child(2)');
            const totalText = amountCell.querySelector('.text-sm.font-semibold').textContent;
            const invoiceTotal = parseFloat(totalText.replace('₹', '').replace(/,/g, ''));
            
            // Set the amount received field to the invoice total by default
            document.getElementById('amount-received').value = invoiceTotal.toFixed(2);
            
            // Calculate short balance
            calculateShortBalance();
            
            openPaymentModal();
        });
    });
    
    // Add event listeners for amount calculations
    document.getElementById('amount-received').addEventListener('input', calculateShortBalance);
    document.getElementById('tds-amount').addEventListener('input', calculateShortBalance);
    
    function calculateShortBalance() {
        const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
        const tdsAmount = parseFloat(document.getElementById('tds-amount').value) || 0;
        
        // Get invoice total from the current row (now using calculated total)
        const activeButton = document.querySelector('.mark-paid-btn[data-invoice-id="' + document.getElementById('payment-invoice-id').value + '"]');
        if (activeButton) {
            const row = activeButton.closest('tr');
            const amountCell = row.querySelector('td:nth-child(2)');
            const totalText = amountCell.querySelector('.text-sm.font-semibold').textContent;
            const invoiceTotal = parseFloat(totalText.replace('₹', '').replace(/,/g, ''));
            
            const shortBalance = invoiceTotal - amountReceived - tdsAmount;
            const shortBalanceInput = document.getElementById('short-balance');
            shortBalanceInput.value = shortBalance.toFixed(2);
            
            // Add visual feedback based on balance
            shortBalanceInput.classList.remove('border-green-500', 'border-red-500', 'border-orange-500', 'bg-green-500/10', 'bg-red-500/10', 'bg-orange-500/10');
            
            if (shortBalance === 0) {
                shortBalanceInput.classList.add('border-green-500', 'bg-green-500/10');
            } else if (shortBalance > 0) {
                shortBalanceInput.classList.add('border-orange-500', 'bg-orange-500/10');
            } else {
                shortBalanceInput.classList.add('border-red-500', 'bg-red-500/10');
            }
        }
    }
    
    // Edit Invoice functionality
    document.querySelectorAll('.edit-invoice-btn').forEach(button => {
        button.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-invoice-id');
            editInvoice(invoiceId);
        });
    });
});

// Modal functions
function openModal() {
    document.getElementById('invoice-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('invoice-modal').classList.add('hidden');
}

function openPaymentModal() {
    console.log('Opening payment modal...');
    const modal = document.getElementById('payment-modal');
    if (modal) {
        modal.classList.remove('hidden');
        console.log('Payment modal opened successfully');
    } else {
        console.error('Payment modal element not found!');
    }
}

function closePaymentModal() {
    console.log('Closing payment modal...');
    const modal = document.getElementById('payment-modal');
    if (modal) {
        modal.classList.add('hidden');
        console.log('Payment modal closed successfully');
    } else {
        console.error('Payment modal element not found!');
    }
}

// Invoice functions
function editInvoice(invoiceId) {
    // Fetch invoice details and populate modal
    fetch(`actions/invoice_controller.php?action=get_invoice&id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const invoice = data.invoice;
                
                document.getElementById('modal-title').textContent = 'Edit Invoice';
                document.getElementById('invoice-id').value = invoice.id;
                document.getElementById('invoice-month').value = invoice.month;
                
                // Set GST checkbox
                const gstCheckbox = document.getElementById('is-gst-applicable');
                if (gstCheckbox) {
                    if (invoice.is_gst_applicable == 1) {
                        gstCheckbox.checked = true;
                    } else {
                        gstCheckbox.checked = false;
                    }
                    
                    // Disable checkbox if client is compliant
                    const isCompliant = <?php echo json_encode((bool)$client['compliance_status']); ?>;
                    if (isCompliant) {
                        gstCheckbox.disabled = true;
                        gstCheckbox.checked = true;
                    }
                }
                
                // Clear existing items
                const itemsContainer = document.getElementById('invoice-items');
                itemsContainer.innerHTML = '';
                
                // Add invoice items
                invoice.items.forEach(item => {
                    const itemRow = document.createElement('div');
                    itemRow.className = 'space-y-3';
                    
                    const amount = item.quantity * item.rate;
                    
                    itemRow.innerHTML = `
                        <div class="invoice-item bg-gray-800 rounded-lg p-3 border border-gray-700 hover:border-blue-500 transition-colors">
                            <div class="grid grid-cols-12 gap-3 items-center">
                                <div class="col-span-5">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-cog text-blue-400 text-sm"></i>
                            <input type="text" name="item_type[]" value="${item.employee_type}" 
                                               class="w-full bg-transparent border-none text-white text-sm 
                                                      focus:outline-none"
                                               style="background: transparent; border: none; outline: none;">
                        </div>
                                </div>
                                <div class="col-span-2">
                            <input type="number" name="item_quantity[]" value="${item.quantity}" 
                                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                                           onchange="calculateItemAmount(this)">
                        </div>
                                <div class="col-span-3">
                            <input type="number" step="0.01" name="item_rate[]" value="${item.rate}" 
                                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 text-sm 
                                                  focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all"
                                           onchange="calculateItemAmount(this)">
                                </div>
                                <div class="col-span-2">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-semibold text-green-400 item-amount">₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                        <button type="button" class="text-red-400 hover:text-red-300 p-1" 
                                    onclick="removeItem(this)">
                                <i class="fas fa-times"></i>
                            </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    itemsContainer.appendChild(itemRow);
                });
                
                // Calculate totals after loading invoice items
                setTimeout(() => {
                    calculateTotalAmount();
                }, 100);
                
                openModal();
            } else {
                showToast(data.message || 'Failed to load invoice details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while loading invoice details', 'error');
        });
}

function markAsPaid(invoiceId) {
    document.getElementById('payment-invoice-id').value = invoiceId;
    openPaymentModal();
}

function exportInvoice(invoiceId, format) {
    window.open(`actions/invoice_controller.php?action=export_invoice&id=${invoiceId}&format=${format}`, '_blank');
}

function removeItem(button) {
    const itemRow = button.closest('.invoice-item');
    if (itemRow) {
    itemRow.remove();
        // Recalculate totals after removing item
        calculateTotalAmount();
    }
}

// Calculate item amount when quantity or rate changes
function calculateItemAmount(input) {
    const itemRow = input.closest('.invoice-item');
    if (!itemRow) return;
    
    const quantityInput = itemRow.querySelector('input[name="item_quantity[]"]');
    const rateInput = itemRow.querySelector('input[name="item_rate[]"]');
    const amountDiv = itemRow.querySelector('.item-amount');
    
    if (quantityInput && rateInput && amountDiv) {
        const quantity = parseFloat(quantityInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        const amount = quantity * rate;
        
        amountDiv.textContent = '₹' + amount.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Calculate and update total amount
        calculateTotalAmount();
    }
}

// Calculate total amount for all items
function calculateTotalAmount() {
    const allAmountDivs = document.querySelectorAll('.item-amount');
    let total = 0;
    
    allAmountDivs.forEach(amountDiv => {
        const amountText = amountDiv.textContent.replace('₹', '').replace(/,/g, '');
        const amount = parseFloat(amountText) || 0;
        total += amount;
    });
    
    // Update total display if it exists
    const totalDisplay = document.getElementById('invoice-total');
    if (totalDisplay) {
        totalDisplay.textContent = '₹' + total.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Calculate GST if applicable
    const gstCheckbox = document.getElementById('is-gst-applicable');
    const gstDisplay = document.getElementById('invoice-gst');
    const grandTotalDisplay = document.getElementById('invoice-grand-total');
    
    if (gstCheckbox && gstCheckbox.checked) {
        const gstAmount = total * 0.18;
        const grandTotal = total + gstAmount;
        
        if (gstDisplay) {
            gstDisplay.textContent = '₹' + gstAmount.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = '₹' + grandTotal.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    } else {
        if (gstDisplay) {
            gstDisplay.textContent = '₹0.00';
        }
        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = '₹' + total.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    }
}

// Toast notification function
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    
    const toast = document.createElement('div');
    toast.className = `flex items-center p-4 mb-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-800 text-green-200' : 'bg-red-800 text-red-200'}`;
    
    const icon = document.createElement('i');
    icon.className = `mr-2 ${type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'}`;
    
    toast.appendChild(icon);
    toast.appendChild(document.createTextNode(message));
    
    toastContainer.appendChild(toast);
    
    // Automatically remove toast after 5 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}

// Payment Details Modal Functions
function showPaymentDetails(invoiceId) {
    console.log('Loading payment details for invoice:', invoiceId);
    
    // Show loading state
    const content = document.getElementById('payment-details-content');
    content.innerHTML = `
        <div class="flex items-center justify-center py-4">
            <div class="loading-spinner"></div>
            <span class="ml-2 text-gray-400 text-xs">Loading payment details...</span>
        </div>
    `;
    
    // Show modal
    const modal = document.getElementById('payment-details-modal');
    modal.classList.remove('hidden');
    
    // Fetch payment details
    fetch(`actions/invoice_controller.php?action=get_payment_details&id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPaymentDetails(data.payment);
            } else {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                        <p class="text-red-400 text-xs">${data.message || 'Failed to load payment details'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading payment details:', error);
            content.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                    <p class="text-red-400 text-xs">Error loading payment details</p>
                </div>
            `;
        });
}

function displayPaymentDetails(payment) {
    const content = document.getElementById('payment-details-content');
    
    // Format date
    const paymentDate = new Date(payment.paid_at).toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // Format time
    const paymentTime = new Date(payment.paid_at).toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    content.innerHTML = `
        <!-- Payment Summary -->
        <div class="bg-gray-700/50 rounded-lg p-3 border border-gray-600">
            <h4 class="text-sm font-semibold text-white mb-2 flex items-center">
                <i class="fas fa-credit-card text-blue-400 mr-1.5 text-xs"></i>
                Payment Summary
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div class="space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Payment Date:</span>
                        <span class="text-white font-medium">${paymentDate}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Payment Time:</span>
                        <span class="text-white font-medium">${paymentTime}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Payment Method:</span>
                        <span class="text-white font-medium">${payment.payment_method || 'Not specified'}</span>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Invoice Total:</span>
                        <span class="text-white font-medium">₹${parseFloat(payment.calculated_total || payment.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Amount Received:</span>
                        <span class="text-green-400 font-medium">₹${parseFloat(payment.amount_received || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Status:</span>
                        <span class="px-1.5 py-0.5 text-xs rounded-full bg-green-900 text-green-300">Paid</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Breakdown -->
        <div class="bg-gray-700/50 rounded-lg p-3 border border-gray-600">
            <h4 class="text-sm font-semibold text-white mb-2 flex items-center">
                <i class="fas fa-calculator text-green-400 mr-1.5 text-xs"></i>
                Financial Breakdown
            </h4>
            <div class="space-y-1">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-400">Base Amount:</span>
                    <span class="text-white font-medium">₹${parseFloat(payment.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                ${payment.is_gst_applicable ? `
                <div class="flex justify-between text-xs">
                    <span class="text-gray-400">GST (18%):</span>
                    <span class="text-white font-medium">₹${parseFloat(payment.calculated_gst || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-gray-400">Round Off:</span>
                    <span class="text-white font-medium">₹${parseFloat(payment.calculated_round_off || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                ` : ''}
                <div class="flex justify-between text-xs">
                    <span class="text-gray-400">TDS Amount:</span>
                    <span class="text-red-400 font-medium">₹${parseFloat(payment.tds_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="flex justify-between text-xs border-t border-gray-600 pt-1">
                    <span class="text-gray-400 font-semibold">Short Balance:</span>
                    <span class="text-orange-400 font-semibold">₹${parseFloat(payment.short_balance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                </div>
            </div>
        </div>
        
        <!-- Payment Notes -->
        ${payment.payment_notes ? `
        <div class="bg-gray-700/50 rounded-lg p-3 border border-gray-600">
            <h4 class="text-sm font-semibold text-white mb-2 flex items-center">
                <i class="fas fa-sticky-note text-yellow-400 mr-1.5 text-xs"></i>
                Payment Notes
            </h4>
            <div class="bg-gray-800/50 rounded-lg p-2 border border-gray-600">
                <p class="text-gray-300 text-xs leading-relaxed">${payment.payment_notes}</p>
            </div>
        </div>
        ` : ''}
        
        <!-- Invoice Information -->
        <div class="bg-gray-700/50 rounded-lg p-3 border border-gray-600">
            <h4 class="text-sm font-semibold text-white mb-2 flex items-center">
                <i class="fas fa-file-invoice text-purple-400 mr-1.5 text-xs"></i>
                Invoice Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div class="space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Invoice ID:</span>
                        <span class="text-white font-medium">#${payment.id}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Month:</span>
                        <span class="text-white font-medium">${payment.formatted_month}</span>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Generation Type:</span>
                        <span class="px-1.5 py-0.5 text-xs rounded-full ${payment.generation_type === 'auto' ? 'bg-blue-900 text-blue-300' : payment.generation_type === 'manual' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300'}">${payment.generation_type}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400">Created:</span>
                        <span class="text-white font-medium">${new Date(payment.created_at).toLocaleDateString('en-IN')}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closePaymentDetailsModal() {
    const modal = document.getElementById('payment-details-modal');
    modal.classList.add('hidden');
}
</script>

<style>
/* Custom styles for the invoice modal */

/* Custom scrollbar for modal */
.modal-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.modal-scrollbar::-webkit-scrollbar-track {
    background: #1F2937;
    border-radius: 3px;
}

.modal-scrollbar::-webkit-scrollbar-thumb {
    background: #4B5563;
    border-radius: 3px;
}

.modal-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6B7280;
}

/* Enhanced focus states */
.focus-enhanced:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Animation for modal */
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-content {
    animation: modalSlideIn 0.2s ease-out;
}

/* Improved button hover effects */
.btn-enhanced {
    transition: all 0.2s ease-in-out;
    transform: translateY(0);
}

.btn-enhanced:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-enhanced:active {
    transform: translateY(0);
}

/* Professional table styling */
.invoice-table th {
    background: linear-gradient(135deg, #1F2937 0%, #374151 100%);
}

/* Input group styling */
.input-group {
    position: relative;
}

.input-group .input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9CA3AF;
    pointer-events: none;
}

.input-group input {
    padding-left: 40px;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Loading state */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #374151;
    border-top: 3px solid #3B82F6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced modal styling for better theme integration */
.modal-content {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Improved section styling */
.invoice-section {
    background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
    border: 1px solid #374151;
}

/* Enhanced input styling */
input[type="month"]::-webkit-calendar-picker-indicator,
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    cursor: pointer;
}

/* Better focus states for accessibility */
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Improved checkbox styling */
input[type="checkbox"]:checked {
    background-color: #3B82F6;
    border-color: #3B82F6;
}

/* Enhanced button hover effects */
button:hover {
    transform: translateY(-1px);
}

button:active {
    transform: translateY(0);
}
</style>