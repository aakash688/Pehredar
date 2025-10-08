<?php
// UI/billing_dashboard_view.php
require_once __DIR__ . '/../helpers/database.php';

// Initialize database connection
$db = new Database();

// Get clients with their balance information
$clients_query = "
    SELECT 
        s.id,
        s.society_name,
        s.street_address,
        s.city,
        s.state,
        SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END) as pending_balance,
        COUNT(CASE WHEN i.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as paid_count
    FROM 
        society_onboarding_data s
    LEFT JOIN 
        invoices i ON s.id = i.client_id
    GROUP BY 
        s.id
    ORDER BY 
        s.society_name ASC";

$clients = $db->query($clients_query)->fetchAll();

// Get counts for stats
$totalClients = count($clients);
$totalPendingInvoices = array_sum(array_column($clients, 'pending_count'));
$totalPendingAmount = array_sum(array_column($clients, 'pending_balance'));

// Current month and previous month for auto-generation
$currentMonth = date('Y-m');
$previousMonth = date('Y-m', strtotime('-1 month'));
?>

<div class="bg-gray-800 rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white mb-4 md:mb-0">Billing Dashboard</h1>
        <button id="generate-invoices-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all">
            <i class="fas fa-file-invoice"></i> Generate Invoices for <?php echo date('F Y', strtotime('-1 month')); ?>
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Total Clients</span>
            <span class="text-3xl font-bold text-white"><?php echo $totalClients; ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Pending Invoices</span>
            <span class="text-3xl font-bold text-white"><?php echo $totalPendingInvoices; ?></span>
        </div>
        <div class="bg-gray-900 p-5 rounded-lg flex flex-col border border-gray-700">
            <span class="text-gray-400 text-sm">Total Outstanding</span>
            <span class="text-3xl font-bold text-white">₹<?php echo number_format($totalPendingAmount, 2); ?></span>
        </div>
    </div>

    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative">
            <input type="text" id="search-clients" 
                   class="bg-gray-700 text-white w-full pl-10 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                   placeholder="Search clients...">
            <div class="absolute left-3 top-2.5 text-gray-400">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-gray-900 rounded-lg overflow-hidden">
            <thead class="bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Client Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Location</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="clients-table-body" class="divide-y divide-gray-800">
                <?php if (empty($clients)) : ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-gray-400">No clients found. Please onboard clients first.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($clients as $client) : ?>
                        <tr class="client-row hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($client['society_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300"><?php echo htmlspecialchars($client['city'] . ', ' . $client['state']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-semibold <?php echo $client['pending_balance'] > 0 ? 'text-red-400' : 'text-green-400'; ?>">
                                    ₹<?php echo number_format($client['pending_balance'] ?? 0, 2); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo $client['pending_count']; ?> pending, <?php echo $client['paid_count']; ?> paid
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($client['pending_balance'] > 0) : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-300">Outstanding</span>
                                <?php else : ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-300">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a href="index.php?page=client-billing&id=<?php echo $client['id']; ?>" 
                                   class="text-blue-400 hover:text-blue-300 px-2 py-1">
                                    <i class="fas fa-file-invoice"></i> View Invoices
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast Message Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle search functionality
    const searchInput = document.getElementById('search-clients');
    const clientRows = document.querySelectorAll('.client-row');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        clientRows.forEach(row => {
            const clientName = row.querySelector('td:first-child').textContent.toLowerCase();
            const location = row.querySelectorAll('td')[1].textContent.toLowerCase();
            
            if (clientName.includes(searchTerm) || location.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Generate invoices button
    const generateInvoicesBtn = document.getElementById('generate-invoices-btn');
    
    generateInvoicesBtn.addEventListener('click', function() {
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        
        // Make API call to generate invoices
        fetch('actions/invoice_controller.php?action=generate_invoices', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show toast notification
            if (data.success) {
                showToast(data.message, 'success');
                // Refresh the page after 1.5 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(data.message, 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-file-invoice"></i> Generate Invoices';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while generating invoices', 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-file-invoice"></i> Generate Invoices';
        });
    });
    
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
});
</script> 