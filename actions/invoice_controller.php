<?php
// actions/invoice_controller.php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

// Check if action is set
$action = $_GET['action'] ?? '';

// Handle different actions
switch ($action) {
    case 'generate_invoices':
        generateInvoices();
        break;
    case 'create_invoice':
        createInvoice();
        break;
    case 'update_invoice':
        updateInvoice();
        break;
    case 'get_invoice':
        getInvoice();
        break;
    case 'mark_as_paid':
        markAsPaid();
        break;
    case 'get_payment_details':
        getPaymentDetails();
        break;
    case 'export_invoice':
        exportInvoice();
        break;
    case 'view_new_template':
        viewNewTemplate();
        break;
    case 'export_new_template':
        exportNewTemplate();
        break;
    case 'export_pdf':
        // Redirect to modular PDF controller
        header('Location: pdf_controller.php?action=export_pdf&id=' . ($_GET['id'] ?? ''));
        exit;
        break;
    case 'export_pdf_fallback':
        // Redirect to modular PDF controller
        header('Location: pdf_controller.php?action=export_pdf_fallback&id=' . ($_GET['id'] ?? ''));
        exit;
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified'], 400);
        break;
}

/**
 * Auto-generates invoices for all clients for the previous month
 */
function generateInvoices() {
    $db = new Database();
    $previousMonth = date('Y-m', strtotime('-1 month'));
    
    try {
        $db->beginTransaction();
        
        // Get all active clients
        $clients = $db->query("SELECT * FROM society_onboarding_data")->fetchAll();
        
        $generatedCount = 0;
        $skippedCount = 0;
        
        foreach ($clients as $client) {
            // Check if an invoice already exists for this client and month
            $existingInvoice = $db->query(
                "SELECT id, generation_type FROM invoices WHERE client_id = ? AND month = ?",
                [$client['id'], $previousMonth]
            )->fetch();
            
            // Skip if invoice exists and is manually created or modified
            if ($existingInvoice && in_array($existingInvoice['generation_type'], ['manual', 'modified'])) {
                $skippedCount++;
                continue;
            }
            
            // Delete auto-generated invoice if it exists (to regenerate)
            if ($existingInvoice && $existingInvoice['generation_type'] === 'auto') {
                $db->query("DELETE FROM invoice_items WHERE invoice_id = ?", [$existingInvoice['id']]);
                $db->query("DELETE FROM invoices WHERE id = ?", [$existingInvoice['id']]);
            }
            
            // Calculate invoice amount and create line items
            $invoiceItems = [];
            $totalAmount = 0;
            
            // Check and add each employee type
            $employeeTypes = [
                'guards' => ['label' => 'Guard', 'rate_field' => 'guard_client_rate'],
                'dogs' => ['label' => 'Dog Handler', 'rate_field' => 'dog_client_rate'],
                'armed_guards' => ['label' => 'Armed Guard', 'rate_field' => 'armed_client_rate'],
                'housekeeping' => ['label' => 'Housekeeping', 'rate_field' => 'housekeeping_client_rate'],
                'bouncers' => ['label' => 'Bouncer', 'rate_field' => 'bouncer_client_rate'],
                'site_supervisors' => ['label' => 'Site Supervisor', 'rate_field' => 'site_supervisor_client_rate'],
                'supervisors' => ['label' => 'Supervisor', 'rate_field' => 'supervisor_client_rate']
            ];
            
            foreach ($employeeTypes as $key => $info) {
                $countField = $key;
                $rateField = $info['rate_field'];
                
                if ($client[$countField] > 0 && $client[$rateField] > 0) {
                    $quantity = $client[$countField];
                    $rate = $client[$rateField];
                    $amount = $quantity * $rate;
                    
                    $invoiceItems[] = [
                        'employee_type' => $info['label'],
                        'quantity' => $quantity,
                        'rate' => $rate,
                        'total' => $amount
                    ];
                    
                    $totalAmount += $amount;
                }
            }
            
            if ($totalAmount > 0) {
                // Add service charges based only on per-client settings
                if (!empty($client['service_charges_enabled']) && $client['service_charges_enabled'] == 1 && 
                    !empty($client['service_charges_percentage']) && $client['service_charges_percentage'] > 0) {
                    
                    $serviceChargesPercentage = (float)$client['service_charges_percentage'];
                    $serviceChargeAmount = $totalAmount * ($serviceChargesPercentage / 100);
                    
                    $invoiceItems[] = [
                        'employee_type' => "Service Charges ({$serviceChargesPercentage}%)",
                        'quantity' => 1,
                        'rate' => $serviceChargeAmount,
                        'total' => $serviceChargeAmount,
                        'sequence' => 9999 // Force service charges to be last
                    ];
                    
                    $totalAmount += $serviceChargeAmount;
                }
                
                // Check if GST is applicable for this client
                $isGstApplicable = $client['is_gst_applicable'] ?? $client['compliance_status'];
                
                // Calculate GST amount and total with GST
                $gstAmount = $isGstApplicable ? $totalAmount * 0.18 : 0;
                $totalWithGst = $totalAmount + $gstAmount;
                
                // Create invoice with GST details
                $stmt = $db->prepare("INSERT INTO invoices 
                    (client_id, month, amount, is_gst_applicable, gst_amount, total_with_gst, status, generation_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', 'auto', NOW())");
                $stmt->execute([
                    $client['id'], 
                    $previousMonth, 
                    $totalAmount, 
                    $isGstApplicable ? 1 : 0, 
                    $gstAmount, 
                    $totalWithGst
                ]);
                $invoiceId = $db->lastInsertId();
                
                // Create invoice items with sequence
                $sequence = 1;
                foreach ($invoiceItems as $item) {
                    $itemSequence = $item['sequence'] ?? $sequence;
                    $stmt = $db->prepare("INSERT INTO invoice_items 
                        (invoice_id, employee_type, quantity, rate, total, sequence) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $invoiceId,
                        $item['employee_type'],
                        $item['quantity'],
                        $item['rate'],
                        $item['total'],
                        $itemSequence
                    ]);
                    $sequence++;
                }
                
                $generatedCount++;
            }
        }
        
        $db->commit();
        json_response([
            'success' => true, 
            'message' => "Generated $generatedCount invoices. Skipped $skippedCount invoices.",
            'generated' => $generatedCount,
            'skipped' => $skippedCount
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        json_response(['success' => false, 'message' => 'Error generating invoices: ' . $e->getMessage()], 500);
    }
}

/**
 * Creates a manual invoice for a client
 */
function createInvoice() {
    $db = new Database();
    
    try {
        // Parse JSON input
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['client_id']) || empty($data['month']) || empty($data['items'])) {
            json_response(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }
        
        // Check if an invoice already exists for this client and month
        $existingInvoice = $db->query(
            "SELECT id FROM invoices WHERE client_id = ? AND month = ?",
            [$data['client_id'], $data['month']]
        )->fetch();
        
        if ($existingInvoice) {
            json_response(['success' => false, 'message' => 'An invoice already exists for this client and month'], 400);
            return;
        }
        
        $db->beginTransaction();
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($data['items'] as $item) {
            $totalAmount += (float)$item['quantity'] * (float)$item['rate'];
        }
        
        // Get client details to check GST applicability
        $client = $db->query(
            "SELECT is_gst_applicable, compliance_status FROM society_onboarding_data WHERE id = ?",
            [$data['client_id']]
        )->fetch();
        
        // Check if GST is applicable (use client setting or compliance status as fallback)
        $isGstApplicable = isset($data['is_gst_applicable']) ? (bool)$data['is_gst_applicable'] : 
                          (isset($client['is_gst_applicable']) ? (bool)$client['is_gst_applicable'] : 
                           (bool)$client['compliance_status']);
        
        // Calculate GST and total
        $gstAmount = $isGstApplicable ? $totalAmount * 0.18 : 0;
        $totalWithGst = $totalAmount + $gstAmount;
        
        // Create invoice with GST details
        $stmt = $db->prepare("INSERT INTO invoices 
            (client_id, month, amount, is_gst_applicable, gst_amount, total_with_gst, status, generation_type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'manual', NOW())");
        $stmt->execute([
            $data['client_id'], 
            $data['month'], 
            $totalAmount,
            $isGstApplicable ? 1 : 0,
            $gstAmount,
            $totalWithGst
        ]);
        
        $invoiceId = $db->lastInsertId();
        
        // Create invoice items with sequence
        $sequence = 1;
        foreach ($data['items'] as $item) {
            $itemTotal = (float)$item['quantity'] * (float)$item['rate'];
            $itemSequence = $item['sequence'] ?? $sequence;
            $stmt = $db->prepare("INSERT INTO invoice_items 
                (invoice_id, employee_type, quantity, rate, total, sequence) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $invoiceId,
                $item['employee_type'],
                $item['quantity'],
                $item['rate'],
                $itemTotal,
                $itemSequence
            ]);
            $sequence++;
        }
        
        $db->commit();
        json_response(['success' => true, 'message' => 'Invoice created successfully', 'invoice_id' => $invoiceId]);
        
    } catch (Exception $e) {
        $db->rollBack();
        json_response(['success' => false, 'message' => 'Error creating invoice: ' . $e->getMessage()], 500);
    }
}

/**
 * Updates an existing invoice
 */
function updateInvoice() {
    $db = new Database();
    
    try {
        // Parse JSON input
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['invoice_id']) || empty($data['items'])) {
            json_response(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }
        
        // Get current invoice
        $invoice = $db->query(
            "SELECT * FROM invoices WHERE id = ?",
            [$data['invoice_id']]
        )->fetch();
        
        if (!$invoice) {
            json_response(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }
        
        if ($invoice['status'] === 'paid') {
            json_response(['success' => false, 'message' => 'Paid invoices cannot be modified'], 400);
            return;
        }
        
        $db->beginTransaction();
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($data['items'] as $item) {
            $totalAmount += (float)$item['quantity'] * (float)$item['rate'];
        }
        
        // Check if GST is applicable
        $isGstApplicable = isset($data['is_gst_applicable']) ? (bool)$data['is_gst_applicable'] : 
                         (bool)$invoice['is_gst_applicable'];
        
        // Calculate GST amount and total
        $gstAmount = $isGstApplicable ? $totalAmount * 0.18 : 0;
        $totalWithGst = $totalAmount + $gstAmount;
        
        // Update invoice with GST details
        $stmt = $db->prepare("UPDATE invoices 
            SET amount = ?, 
                is_gst_applicable = ?, 
                gst_amount = ?, 
                total_with_gst = ?, 
                generation_type = 'modified', 
                updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([
            $totalAmount, 
            $isGstApplicable ? 1 : 0,
            $gstAmount,
            $totalWithGst,
            $data['invoice_id']
        ]);
        
        // Delete old invoice items
        $db->query("DELETE FROM invoice_items WHERE invoice_id = ?", [$data['invoice_id']]);
        
        // Create new invoice items with sequence
        $sequence = 1;
        foreach ($data['items'] as $item) {
            $itemTotal = (float)$item['quantity'] * (float)$item['rate'];
            $itemSequence = $item['sequence'] ?? $sequence;
            $stmt = $db->prepare("INSERT INTO invoice_items 
                (invoice_id, employee_type, quantity, rate, total, sequence) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['invoice_id'],
                $item['employee_type'],
                $item['quantity'],
                $item['rate'],
                $itemTotal,
                $itemSequence
            ]);
            $sequence++;
        }
        
        $db->commit();
        json_response(['success' => true, 'message' => 'Invoice updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        json_response(['success' => false, 'message' => 'Error updating invoice: ' . $e->getMessage()], 500);
    }
}

/**
 * Gets details of a specific invoice
 */
function getInvoice() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            json_response(['success' => false, 'message' => 'Invoice ID is required'], 400);
            return;
        }
        
        // Get invoice details
        $invoice = $db->query(
            "SELECT i.*, c.society_name as client_name
            FROM invoices i
            JOIN society_onboarding_data c ON i.client_id = c.id
            WHERE i.id = ?",
            [$invoiceId]
        )->fetch();
        
        if (!$invoice) {
            json_response(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }
        
        // Get invoice items with sequence-based ordering
        $items = $db->query(
            "SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY COALESCE(sequence, 9998), employee_type ASC",
            [$invoiceId]
        )->fetchAll();
        
        $invoice['items'] = $items;
        
        json_response(['success' => true, 'invoice' => $invoice]);
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error retrieving invoice: ' . $e->getMessage()], 500);
    }
}

/**
 * Gets payment details for an invoice
 */
function getPaymentDetails() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            json_response(['success' => false, 'message' => 'Invoice ID is required'], 400);
            return;
        }
        
        // Get invoice with payment details
        $invoice = $db->query(
            "SELECT * FROM invoices WHERE id = ?",
            [$invoiceId]
        )->fetch();
        
        if (!$invoice) {
            json_response(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }
        
        // Format month name
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
        
        // Add calculated fields
        $invoice['calculated_total'] = $roundedTotal;
        $invoice['calculated_gst'] = $gstAmount;
        $invoice['calculated_round_off'] = $roundOff;
        
        json_response(['success' => true, 'payment' => $invoice]);
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error getting payment details: ' . $e->getMessage()], 500);
    }
}

/**
 * Marks an invoice as paid
 */
function markAsPaid() {
    $db = new Database();
    
    try {
        // Parse JSON input
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['invoice_id'])) {
            json_response(['success' => false, 'message' => 'Invoice ID is required'], 400);
            return;
        }
        
        // Get current invoice
        $invoice = $db->query(
            "SELECT * FROM invoices WHERE id = ?",
            [$data['invoice_id']]
        )->fetch();
        
        if (!$invoice) {
            json_response(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }
        
        if ($invoice['status'] === 'paid') {
            json_response(['success' => false, 'message' => 'Invoice is already marked as paid'], 400);
            return;
        }
        
        // Update invoice as paid
        $paidAt = !empty($data['paid_at']) ? $data['paid_at'] : date('Y-m-d H:i:s');
        $paymentMethod = $data['payment_method'] ?? null;
        $paymentNotes = $data['payment_notes'] ?? null;
        $tdsAmount = isset($data['tds_amount']) ? (float)$data['tds_amount'] : 0.00;
        $amountReceived = isset($data['amount_received']) ? (float)$data['amount_received'] : 0.00;
        $shortBalance = isset($data['short_balance']) ? (float)$data['short_balance'] : 0.00;
        
        $stmt = $db->prepare("UPDATE invoices 
            SET status = 'paid', paid_at = ?, payment_method = ?, payment_notes = ?, 
                tds_amount = ?, amount_received = ?, short_balance = ?, updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$paidAt, $paymentMethod, $paymentNotes, $tdsAmount, $amountReceived, $shortBalance, $data['invoice_id']]);
        
        json_response(['success' => true, 'message' => 'Invoice marked as paid']);
        
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error marking invoice as paid: ' . $e->getMessage()], 500);
    }
}

/**
 */
function exportInvoice() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice details with client information
        $invoice_query = "
            SELECT 
                i.*,
                s.*,
                ct.type_name as client_type
            FROM 
                invoices i
            JOIN 
                society_onboarding_data s ON i.client_id = s.id
            LEFT JOIN 
                client_types ct ON s.client_type_id = ct.id
            WHERE 
                i.id = ?";
                
        $invoice = $db->query($invoice_query, [$invoiceId])->fetch();
        
        // Format month name using PHP
        if ($invoice) {
            $date = DateTime::createFromFormat('Y-m', $invoice['month']);
            $invoice['formatted_month'] = $date ? $date->format('F Y') : 'Invalid Date';
        }
        
        if (!$invoice) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Get invoice items
        $items_query = "
            SELECT 
                employee_type, 
                quantity, 
                rate, 
                total
            FROM 
                invoice_items
            WHERE 
                invoice_id = ?
            ORDER BY 
                employee_type";
                
        $items = $db->query($items_query, [$invoiceId])->fetchAll();
        
        // Format invoice number
        $invoiceNumber = str_pad($invoice['id'], 6, '0', STR_PAD_LEFT);
        
        // Get company settings
        $company_query = "SELECT * FROM company_settings WHERE id = 1";
        $company = $db->query($company_query)->fetch();
        
        // If no company settings exist, use default values
        if (!$company) {
            $company = [
                'company_name' => 'Your Company Name',
                'gst_number' => '',
                'street_address' => '',
                'city' => '',
                'state' => '',
                'pincode' => '',
                'email' => '',
                'phone_number' => '',
                'invoice_notes' => 'Thank you for your business.',
                'invoice_terms' => 'This is a computer-generated invoice. No signature required.',
                'bank_name' => '',
                'bank_account_number' => '',
                'bank_account_type' => '',
                'bank_ifsc_code' => '',
                'bank_branch' => '',
                'signature_image' => ''
            ];
        }
        
        // Only PDF export is supported
        require_once __DIR__ . '/../vendor/autoload.php';
            
        // Create PDF with UTF-8 encoding and DejaVu Sans font for better Unicode support
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->set_option('defaultFont', 'DejaVu Sans');
        
        // Calculate subtotal
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['total'];
        }
        
        // Use the new invoice template
        ob_start();
        
        // Prepare data for template
        $template_data = [
            'invoice' => $invoice,
            'items' => $items,
            'company_settings' => $company,
            'invoice_number' => $invoiceNumber,
            'subtotal' => $subtotal
        ];
        
        // Extract variables for template
        extract($template_data);
        
        // Include the template
        include __DIR__ . '/../templates/pdf/invoice_template.php';
        
        $html = ob_get_clean();
        
        /*
        // Create HTML content that is optimized for A4 paper size and printer-friendly layout
        $html_old = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title>Invoice #' . $invoiceNumber . '</title>
            <style>
                @font-face {
                    font-family: "DejaVu Sans";
                    font-style: normal;
                    src: url("https://cdnjs.cloudflare.com/ajax/libs/dejavu-sans/4.0.1/DejaVuSans.ttf") format("truetype");
                }
                @page {
                    size: A4;
                    margin: 15mm 10mm 15mm 10mm;
                }
                body { 
                    font-family: "DejaVu Sans", Arial, sans-serif; 
                    margin: 0; 
                    padding: 0; 
                    color: #333; 
                    background-color: #fff;
                    line-height: 1.2;
                    font-size: 11px;
                    width: 100%;
                }
                .invoice-container {
                    width: 100%;
                    margin: 0;
                    padding: 10px;
                    background-color: #fff;
                }
                .header {
                    display: table;
                    width: 100%;
                    table-layout: fixed;
                    margin-bottom: 15px;
                }
                .company-info {
                    display: table-cell;
                    width: 60%;
                    vertical-align: top;
                }
                .invoice-info {
                    display: table-cell;
                    width: 40%;
                    text-align: right;
                    vertical-align: top;
                }
                .company-name {
                    font-size: 16px;
                    font-weight: bold;
                    margin: 0 0 5px 0;
                }
                .invoice-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: #1e40af;
                    margin: 0 0 5px 0;
                }
                .status-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 11px;
                    font-weight: bold;
                    margin: 5px 0;
                }
                .status-paid {
                    background-color: #d1fae5;
                    color: #065f46;
                }
                .status-pending {
                    background-color: #fee2e2;
                    color: #991b1b;
                }
                .client-info {
                    margin: 15px 0 10px 0;
                    background-color: #f9fafb;
                    padding: 8px;
                    border: 1px solid #e5e7eb;
                    border-radius: 3px;
                }
                h1, h2, h3, h4, h5, h6 {
                    margin-top: 0;
                    margin-bottom: 3px;
                    color: #1f2937;
                }
                h3 {
                    font-size: 12px;
                }
                p {
                    margin: 0 0 3px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                    page-break-inside: avoid;
                }
                table.items-table th {
                    background-color: #f3f4f6;
                    color: #374151;
                    font-weight: bold;
                    text-align: left;
                    padding: 5px;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 11px;
                }
                table.items-table td {
                    padding: 5px;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 11px;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .font-bold {
                    font-weight: bold;
                }
                .total-row {
                    background-color: #f3f4f6;
                }
                .payment-info {
                    margin-bottom: 10px;
                    padding: 5px 0;
                    border-top: 1px solid #e5e7eb;
                    border-bottom: 1px solid #e5e7eb;
                    page-break-inside: avoid;
                }
                .footer-sections {
                    display: table;
                    width: 100%;
                    table-layout: fixed;
                    page-break-inside: avoid;
                }
                .footer-section {
                    display: table-cell;
                    width: 48%;
                    vertical-align: top;
                }
                .bank-details {
                    margin-top: 10px;
                }
                .bank-table {
                    width: 100%;
                }
                .bank-table td {
                    padding: 2px 5px 2px 0;
                    font-size: 10px;
                }
                .signature-area {
                    margin-top: 15px;
                    text-align: right;
                    page-break-inside: avoid;
                }
                .signature-image {
                    height: 40px;
                    margin-bottom: 3px;
                }
                .compact-address {
                    margin: 0;
                    line-height: 1.2;
                }
                .section-heading {
                    margin-bottom: 3px;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                                        <!-- Header - Two column layout with fixed dimensions for A4 paper -->
                <div class="header" style="display: table; width: 100%; table-layout: fixed;">
                    <!-- Left Column: Company Info -->
                    <div class="company-info" style="display: table-cell; width: 60%; vertical-align: top;">
                        <h2 class="company-name" style="font-size: 16px; margin-top: 0; margin-bottom: 5px;">' . htmlspecialchars($company['company_name']) . '</h2>';
        
        if (!empty($company['gst_number'])) {
            $html .= '<p style="margin: 2px 0;">GST: ' . htmlspecialchars($company['gst_number']) . '</p>';
        }
        
        // Compact address format
        $addressParts = [];
        if (!empty($company['street_address'])) $addressParts[] = htmlspecialchars($company['street_address']);
        
        $cityStatePin = '';
        if (!empty($company['city'])) $cityStatePin .= htmlspecialchars($company['city']);
        if (!empty($company['city']) && !empty($company['state'])) $cityStatePin .= ', ';
        if (!empty($company['state'])) $cityStatePin .= htmlspecialchars($company['state']);
        if (!empty($cityStatePin) && !empty($company['pincode'])) $cityStatePin .= ' - ';
        if (!empty($company['pincode'])) $cityStatePin .= htmlspecialchars($company['pincode']);
        
        if (!empty($cityStatePin)) $addressParts[] = $cityStatePin;
        
        // Join address parts with line breaks
        if (!empty($addressParts)) {
            $html .= '<p class="compact-address" style="margin: 2px 0; line-height: 1.3;">' . implode('<br>', $addressParts) . '</p>';
        }
        
        // Contact info on one line if possible
        $contactInfo = [];
        if (!empty($company['phone_number'])) $contactInfo[] = 'Phone: ' . htmlspecialchars($company['phone_number']);
        if (!empty($company['email'])) $contactInfo[] = 'Email: ' . htmlspecialchars($company['email']);
        
        if (!empty($contactInfo)) {
            $html .= '<p style="margin: 2px 0;">' . implode(' | ', $contactInfo) . '</p>';
        }
        
        $html .= '
                    </div>
                    
                    <!-- Right Column: Invoice Title, Status, and Details -->
                    <div class="invoice-info" style="display: table-cell; width: 40%; text-align: right; vertical-align: top;">
                        <!-- Invoice Title and Status Badge -->
                        <h1 class="invoice-title" style="color: #1e40af; margin-top: 0; margin-bottom: 5px; font-size: 20px;">INVOICE</h1>';
        
        if ($invoice['status'] === 'paid') {
            $html .= '<div style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; background-color: #d1fae5; color: #065f46;">PAID</div>';
        } else {
            $html .= '<div style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; background-color: #fee2e2; color: #991b1b;">PENDING</div>';
        }
        
        $html .= '
                                                    <!-- Invoice Details - Moved to top right -->
                        <div class="invoice-details" style="margin-top: 5px;">
                            <p style="margin: 2px 0;"><strong>Invoice #:</strong> ' . $invoiceNumber . '</p>
                            <p style="margin: 2px 0;"><strong>Date:</strong> ' . date('d/m/Y', strtotime($invoice['created_at'])) . '</p>
                            <p style="margin: 2px 0;"><strong>Month:</strong> ' . $invoice['formatted_month'] . '</p>
                        </div>
                    </div>
                </div>
            
                <!-- Client Information -->
                <div class="client-info">
                    <h3 class="section-heading">Bill To:</h3>
                    <p style="font-weight: 600;">' . htmlspecialchars($invoice['society_name']) . '</p>';
        
        // Compact client address
        $clientAddressParts = [];
        if (!empty($invoice['street_address'])) $clientAddressParts[] = htmlspecialchars($invoice['street_address']);
        
        $clientCityState = '';
        if (!empty($invoice['city'])) $clientCityState .= htmlspecialchars($invoice['city']);
        if (!empty($invoice['city']) && !empty($invoice['state'])) $clientCityState .= ', ';
        if (!empty($invoice['state'])) $clientCityState .= htmlspecialchars($invoice['state']);
        if (!empty($clientCityState) && !empty($invoice['pin_code'])) $clientCityState .= ' - ';
        if (!empty($invoice['pin_code'])) $clientCityState .= htmlspecialchars($invoice['pin_code']);
        
        if (!empty($clientCityState)) $clientAddressParts[] = $clientCityState;
        
        // Join client address parts
        if (!empty($clientAddressParts)) {
            $html .= '<p>' . implode('<br>', $clientAddressParts) . '</p>';
        }
        
        if (!empty($invoice['gst_number'])) {
            $html .= '<p><strong>GST Number:</strong> ' . htmlspecialchars($invoice['gst_number']) . '</p>';
        }
        
        $html .= '
                </div>
                
                <!-- Invoice Items -->
            <table class="items-table">
                <thead>
                    <tr>
                            <th style="width: 40%;">Description</th>
                            <th style="width: 20%; text-align: center;">Quantity</th>
                            <th style="width: 20%; text-align: right;">Rate</th>
                            <th style="width: 20%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($items as $item) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['employee_type']) . '</td>
                            <td style="text-align: center;">' . $item['quantity'] . '</td>
                            <td style="text-align: right;">₹' . number_format($item['rate'], 2) . '</td>
                            <td style="text-align: right;">₹' . number_format($item['total'], 2) . '</td>
                    </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
                
                <!-- Summary -->
                <table class="summary-table" style="width: 100%;">
                    <tr>
                        <td style="width: 80%; text-align: right; font-weight: 600;">Subtotal:</td>
                        <td style="width: 20%; text-align: right; font-weight: 600;">₹' . number_format($subtotal, 2) . '</td>
                    </tr>';
                        
        if ($invoice['is_gst_applicable']) {
            $html .= '
                    <tr>
                        <td style="text-align: right;">GST (18%):</td>
                        <td style="text-align: right;">₹' . number_format($invoice['gst_amount'], 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td style="text-align: right; font-weight: bold;">Total:</td>
                        <td style="text-align: right; font-weight: bold;">₹' . number_format($invoice['total_with_gst'], 2) . '</td>
                    </tr>';
        } else {
            $html .= '
                    <tr class="total-row">
                        <td style="text-align: right; font-weight: bold;">Total:</td>
                        <td style="text-align: right; font-weight: bold;">₹' . number_format($invoice['amount'], 2) . '</td>
                    </tr>';
        }
        
        $html .= '
            </table>';
                
        // Payment Information (if paid)
        if ($invoice['status'] === 'paid') {
            $html .= '
                <div class="payment-info">
                    <h3 class="section-heading">Payment Information</h3>
                    <p><strong>Paid on:</strong> ' . date('d/m/Y', strtotime($invoice['paid_at'])) . ' | <strong>Payment Method:</strong> ' . 
                    htmlspecialchars($invoice['payment_method'] ?? 'N/A');
            
            if (!empty($invoice['payment_notes'])) {
                $html .= ' | <strong>Notes:</strong> ' . htmlspecialchars($invoice['payment_notes']);
            }
            
            $html .= '</p></div>';
        }
        
        // Footer sections - Notes, Terms, and Bank Details in a more compact layout
        $html .= '<div class="footer-sections">';
        
        // Left column for Notes and Terms
        if (!empty($company['invoice_notes']) || !empty($company['invoice_terms'])) {
            $html .= '<div class="footer-section">';
            
            if (!empty($company['invoice_notes'])) {
                $html .= '
                    <h3 class="section-heading">Notes</h3>
                    <p style="font-size: 10px;">' . nl2br(htmlspecialchars($company['invoice_notes'])) . '</p>';
            }
            
            if (!empty($company['invoice_terms'])) {
                $html .= '
                    <h3 class="section-heading">Terms & Conditions</h3>
                    <p style="font-size: 10px;">' . nl2br(htmlspecialchars($company['invoice_terms'])) . '</p>';
            }
            
            $html .= '</div>';
        }
        
        // Right column for Bank Details
        if (!empty($company['bank_name']) || !empty($company['bank_account_number'])) {
            $html .= '
                <div class="footer-section">
                    <h3 class="section-heading">Payment Details</h3>
                    <table class="bank-table">';
            
            if (!empty($company['bank_name'])) {
                $html .= '
                        <tr>
                            <td style="font-weight: 600; width: 100px;">Bank Name:</td>
                            <td>' . htmlspecialchars($company['bank_name']) . '</td>
                        </tr>';
            }
            
            if (!empty($company['bank_account_number'])) {
                $html .= '
                        <tr>
                            <td style="font-weight: 600;">Account Number:</td>
                            <td>' . htmlspecialchars($company['bank_account_number']) . '</td>
                        </tr>';
            }
            
            if (!empty($company['bank_account_type'])) {
                $html .= '
                        <tr>
                            <td style="font-weight: 600;">Account Type:</td>
                            <td>' . htmlspecialchars($company['bank_account_type']) . '</td>
                        </tr>';
            }
            
            if (!empty($company['bank_ifsc_code'])) {
                $html .= '
                        <tr>
                            <td style="font-weight: 600;">IFSC Code:</td>
                            <td>' . htmlspecialchars($company['bank_ifsc_code']) . '</td>
                        </tr>';
            }
            
            if (!empty($company['bank_branch'])) {
                $html .= '
                        <tr>
                            <td style="font-weight: 600;">Branch:</td>
                            <td>' . htmlspecialchars($company['bank_branch']) . '</td>
                        </tr>';
            }
            
            $html .= '
                    </table>
            </div>';
        }
        
        $html .= '</div>'; // End footer-sections
        
        // Signature - made smaller and positioned more efficiently
        if (!empty($company['signature_image'])) {
            // Get the full path to the signature image
            $signatureImagePath = __DIR__ . '/../' . $company['signature_image'];
            $signatureImageData = '';
            
            // Check if the file exists and convert it to base64
            if (file_exists($signatureImagePath)) {
                $imageData = file_get_contents($signatureImagePath);
                $imageType = mime_content_type($signatureImagePath);
                $signatureImageData = 'data:' . $imageType . ';base64,' . base64_encode($imageData);
            }
            
            if (!empty($signatureImageData)) {
        $html .= '
                    <div class="signature-area">
                        <img src="' . $signatureImageData . '" alt="Authorized Signature" class="signature-image">
                        <p style="font-weight: 600; font-size: 10px;">Authorized Signature</p>
                    </div>';
            }
        }
        
        $html .= '
            </div>
        </body>
        </html>';
        */
        
        $dompdf->loadHtml($html);
        // Set paper size to A4 and ensure consistent rendering
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-' . $invoiceNumber . '.pdf"');
        header('Cache-Control: max-age=0');
        
        echo $dompdf->output();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error exporting invoice: " . $e->getMessage();
        exit;
    }
}

/**
 * View invoice using new dynamic template
 */
function viewNewTemplate() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice data and format for new template
        $template_data = getInvoiceDataForTemplate($db, $invoiceId);
        
        if (!$template_data) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Check if auto PDF generation is requested
        $autoPdf = isset($_GET['autopdf']) && $_GET['autopdf'] == '1';
        
        // Include and render the new template
        require_once __DIR__ . '/../templates/pdf/invoice_template_dynamic.php';
        echo renderInvoiceTemplate($template_data, false, $autoPdf);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error viewing invoice: " . $e->getMessage();
        exit;
    }
}

/**
 * Export invoice as PDF using new dynamic template
 */
function exportNewTemplate() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice data and format for new template
        $template_data = getInvoiceDataForTemplate($db, $invoiceId);
        
        if (!$template_data) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Generate HTML for PDF using the UI template but without pagination
        require_once __DIR__ . '/../templates/pdf/invoice_template_dynamic.php';
        
        // For PDF, we want to show everything without pagination
        // Set a flag to indicate this is PDF mode
        $_GET['pdf_mode'] = '1';
        unset($_GET['page']);
        
        // Get the template HTML
        $html = renderInvoiceTemplate($template_data);
        
        // Clean up the flag
        unset($_GET['pdf_mode']);
        
        // Remove interactive elements for PDF
        $html = preg_replace('/<div class="print-controls">.*?<\/div>/s', '', $html);
        $html = preg_replace('/<div class="pagination-nav".*?<\/div>/s', '', $html);
        $html = preg_replace('/<script>.*?<\/script>/s', '', $html);
        
        // Add CSS to ensure proper PDF rendering with proper A4 page constraints
        $html = str_replace('</head>', '
        <style>
        /* PDF Mode: Force proper A4 page constraints */
        body {
            font-size: 9px !important; /* Slightly larger for readability */
            line-height: 1.3 !important;
        }
        
        .invoice-container {
            width: 210mm !important; /* Full A4 width */
            height: 297mm !important; /* Fixed A4 height - THE KEY FIX */
            min-height: 297mm !important; /* Ensure minimum A4 height */
            margin: 0 auto !important;
            padding: 10mm !important; /* Standard A4 margins */
            box-sizing: border-box !important;
            
            /* Force page breaks for multiple invoices */
            page-break-after: always !important;
            break-after: page !important;
        }
        
        /* Prevent content from splitting across pages */
        .main-table, 
        .summary-section, 
        .footer-section, 
        .bottom-section {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        
        .main-table {
            height: auto !important;
            min-height: auto !important;
            font-size: 8px !important; /* Readable table font */
        }
        
        .main-table th {
            padding: 4px 3px !important; /* Readable padding */
            font-size: 9px !important;
        }
        
        .main-table td {
            padding: 3px 2px !important; /* Readable padding */
            font-size: 8px !important;
            height: 14px !important; /* Readable row height */
            line-height: 1.2 !important;
        }
        
        /* Scale down company section */
        .company-name {
            font-size: 14px !important; /* Reduced from 18px */
        }
        
        .company-info {
            font-size: 8px !important; /* Reduced from 10px */
        }
        
        .contact-item {
            font-size: 7px !important;
        }
        
        /* Scale down client section */
        .client-name {
            font-size: 11px !important; /* Reduced from 14px */
        }
        
        .client-details {
            font-size: 8px !important;
        }
        
        /* Scale down service descriptions */
        .service-name {
            font-size: 7px !important; /* Reduced from 9px */
            margin-bottom: 1px !important;
        }
        
        .service-description {
            font-size: 6px !important; /* Reduced from 8px */
            line-height: 1.0 !important;
        }
        
        /* Scale down summary section */
        .summary-table td {
            padding: 4px 6px !important; /* Reduced padding */
            font-size: 8px !important;
        }
        
        .grand-total-row {
            font-size: 9px !important;
        }
        
        /* Scale down footer sections */
        .footer-section {
            min-height: 80px !important; /* Reduced from 120px */
        }
        
        .footer-left, .footer-center, .footer-right {
            padding: 4px !important; /* Reduced padding */
        }
        
        .footer-title {
            font-size: 8px !important;
            margin-bottom: 3px !important;
        }
        
        .bank-details, .terms-details {
            font-size: 7px !important;
            line-height: 1.1 !important;
        }
        
        .bank-item {
            margin-bottom: 1px !important;
        }
        
        /* Scale down amount in words section */
        .amount-words-section {
            padding: 4px 6px !important;
            font-size: 8px !important;
        }
        
        .amount-words-label {
            margin-bottom: 2px !important;
        }
        
        /* Scale down top banner */
        .top-banner {
            padding: 4px 6px !important;
            font-size: 8px !important;
        }
        
        .bill-of-supply {
            font-size: 7px !important;
            padding: 1px 4px !important;
        }
        
        .original-box {
            font-size: 6px !important;
            padding: 1px 3px !important;
        }
        
        .secuitas-tagline {
            font-size: 6px !important;
        }
        
        /* Scale down bill-to section */
        .billto-left, .billto-right {
            padding: 4px 6px !important;
        }
        
        .billto-meta-table th, .billto-meta-table td {
            font-size: 8px !important;
            padding: 1px 2px !important;
        }
        
        .section-title {
            font-size: 9px !important;
            margin-bottom: 2px !important;
        }
        
        /* Company logo scaling */
        .company-logo img {
            max-width: 50px !important; /* Reduced from 70px */
            max-height: 40px !important; /* Reduced from 60px */
        }
        
        /* Watermark scaling */
        .watermark {
            width: 140mm !important; /* Reduced to fit smaller container */
            height: 120mm !important; /* Reduced height */
            left: 25mm !important;
            bottom: 50mm !important; /* Adjusted position */
        }
        
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            
            body {
                font-size: 9px !important;
            }
            
            .invoice-container {
                width: 210mm !important;
                height: 297mm !important; /* Fixed A4 height */
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 10mm !important;
                box-shadow: none !important;
                page-break-after: always !important;
                box-sizing: border-box !important;
            }
            
            /* Prevent sections from splitting */
            .main-table, 
            .summary-section, 
            .footer-section, 
            .bottom-section {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            
            .main-table tbody tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
        </style>
        </head>', $html);
        
        // Generate PDF
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Format invoice number for filename
        $invoiceNumber = str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-' . $invoiceNumber . '.pdf"');
        header('Cache-Control: max-age=0');
        
        echo $dompdf->output();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error exporting invoice: " . $e->getMessage();
        exit;
    }
}

/**
 * Convert database invoice data to new template format
 */
function getInvoiceDataForTemplate($db, $invoiceId) {
    // Get invoice details with client information
    $invoice_query = "
        SELECT 
            i.id as invoice_id,
            i.client_id,
            i.amount,
            i.is_gst_applicable as invoice_gst_applicable,
            i.gst_amount,
            i.total_with_gst,
            i.month,
            i.created_at,
            i.status,
            s.*,
            ct.type_name as client_type
        FROM 
            invoices i
        JOIN 
            society_onboarding_data s ON i.client_id = s.id
        LEFT JOIN 
            client_types ct ON s.client_type_id = ct.id
        WHERE 
            i.id = ?";
            
    $invoice = $db->query($invoice_query, [$invoiceId])->fetch();
    
    // Debug: Ensure we got the correct invoice
    if ($invoice && $invoice['invoice_id'] != $invoiceId) {
        error_log("Invoice ID mismatch: requested $invoiceId, got {$invoice['invoice_id']}");
        return null;
    }
    
    // Format month name using PHP
    if ($invoice) {
        $date = DateTime::createFromFormat('Y-m', $invoice['month']);
        $invoice['formatted_month'] = $date ? $date->format('F Y') : 'Invalid Date';
    }
    
    if (!$invoice) {
        return null;
    }
    
    // Get invoice items with sequence-based ordering
    $items_query = "
        SELECT 
            employee_type, 
            quantity, 
            rate, 
            total,
            sequence
        FROM 
            invoice_items
        WHERE 
            invoice_id = ?
        ORDER BY 
            COALESCE(sequence, 9998),
            employee_type ASC";
            
    $items = $db->query($items_query, [$invoiceId])->fetchAll();
    
    // Load company settings (for bank details, terms, signature, and watermark)
    $company_query = "SELECT * FROM company_settings WHERE id = 1";
    $company = $db->query($company_query)->fetch();
    if (!$company) {
        $company = [
            'company_name' => 'Your Company Name',
            'invoice_terms' => '',
            'bank_name' => '',
            'bank_account_number' => '',
            'bank_ifsc_code' => '',
            'bank_branch' => '',
            'signature_image' => ''
        ];
    }

    // Format invoice number
    $invoiceNumber = 'RP/SL/' . date('y', strtotime($invoice['created_at'])) . '-' . date('y', strtotime($invoice['created_at']) + 31536000) . '/' . str_pad($invoice['invoice_id'], 3, '0', STR_PAD_LEFT);
    
     // Calculate amounts
     $subtotal = 0;
     foreach ($items as $item) {
         $subtotal += $item['total'];
     }
     
     // Calculate GST amount if applicable
     $gstAmount = $invoice['invoice_gst_applicable'] ? round($subtotal * 0.18, 2) : null;
     
     // Calculate pre-round total (subtotal + GST if applicable)
     $preRoundTotal = $invoice['invoice_gst_applicable'] ? ($subtotal + $gstAmount) : $subtotal;
     
     // Round to nearest complete rupee
     $roundedTotal = round($preRoundTotal);
     
     // Calculate round-off amount (difference between rounded and pre-round total)
     $roundOff = round($roundedTotal - $preRoundTotal, 2);
     
     // Final total is the rounded amount
     $total = $roundedTotal;
    
    // Convert number to words (you can implement this function)
    $amountInWords = convertNumberToWords($total);
    
    // Format items for new template
    $templateItems = [];
    foreach ($items as $index => $item) {
        $templateItems[] = [
            'sno' => $index + 1,
            'service_name' => $item['employee_type'],
            'service_description' => $item['employee_type'] . ' charges for the month of ' . $invoice['formatted_month'],
            'quantity' => $item['quantity'] . ' NOS',
            'rate' => '₹ ' . number_format($item['rate'], 2),
            'amount' => '₹ ' . number_format($item['total'], 2)
        ];
    }
    
    // Prepare terms and conditions from settings (split by newline if provided)
    $termsArray = [];
    if (!empty($company['invoice_terms'])) {
        $termsArray = preg_split('/\r\n|\r|\n/', trim($company['invoice_terms']));
        $termsArray = array_values(array_filter(array_map('trim', $termsArray), function($t){ return $t !== ''; }));
    }

    // Get the base URL from config file
    $config = require __DIR__ . '/../config.php';
    $baseUrl = rtrim($config['base_url'], '/');

    // Prepare signature image path (absolute URL if relative)
    $signaturePath = '';
    if (!empty($company['signature_image'])) {
        $sig = $company['signature_image'];
        if (preg_match('/^https?:\/\//i', $sig)) {
            $signaturePath = $sig;
        } else {
            $signaturePath = $baseUrl . '/' . ltrim($sig, '/');
        }
    }

    // Prepare watermark image path (absolute URL if relative)
    $watermarkPath = '';
    if (!empty($company['watermark_image_path'])) {
        $watermark = $company['watermark_image_path'];
        if (preg_match('/^https?:\/\//i', $watermark)) {
            $watermarkPath = $watermark;
        } else {
            $watermarkPath = $baseUrl . '/' . ltrim($watermark, '/');
        }
    }

    // Return data in new template format
    return [
        'company' => [
            'name' => $company['company_name'] ?: 'RYAN PROTECTION FORCE',
            'address' => 'Shop No. 3 Bramha Tower, Plot no. 40, Sector no. 2, Charkop, Kandivali-West, Mumbai, Maharashtra, 400067',
            'mobile' => '9702295293',
            'email' => 'ryanprotectionforce@gmail.com',
            'pan' => 'ATDPM0414C',
            'logo_path' => !empty($company['logo_path']) ? $baseUrl . '/' . ltrim($company['logo_path'], '/') : ''
        ],
        'client' => [
            'title' => 'BILL TO',
            'name' => strtoupper($invoice['society_name']),
            'address' => $invoice['street_address'] . ', ' . $invoice['city'] . ', ' . $invoice['state'] . ', ' . $invoice['pin_code'],
            'pan' => $invoice['gst_number'] ?? 'N/A'
        ],
        'invoice_meta' => [
            'invoice_no' => $invoiceNumber,
            'invoice_date' => date('d/m/Y', strtotime($invoice['created_at'])),
            'due_date' => date('d/m/Y', strtotime($invoice['created_at'] . ' +30 days'))
        ],
        'header' => [
            'bill_of_supply' => 'BILL OF SUPPLY',
            'original_text' => 'ORIGINAL FOR RECIPIENT',
            'tagline' => 'SECURITAS: Your trusted security partner for a safer tomorrow.'
        ],
        'items' => $templateItems,
         'summary' => [
             'subtotal' => '₹ ' . number_format($subtotal, 2),
             'gst_enabled' => (bool)$invoice['invoice_gst_applicable'],
             'gst_amount' => $invoice['invoice_gst_applicable'] ? '₹ ' . number_format($gstAmount, 2) : null,
             'gst_amount_raw' => $invoice['invoice_gst_applicable'] ? $gstAmount : null,
             'gst_percent' => $invoice['invoice_gst_applicable'] ? 18 : null,
             'round_off' => '₹ ' . number_format($roundOff, 2),
             'total' => '₹ ' . number_format($total, 2),
             'amount_in_words' => $amountInWords
         ],
        'bank_details' => [
            'title' => 'Bank Details',
            // Name should be the company name
            'name' => $company['company_name'] ?: 'RYAN PROTECTION FORCE',
            'ifsc_code' => $company['bank_ifsc_code'] ?? '',
            'account_no' => $company['bank_account_number'] ?? '',
            'bank' => trim(($company['bank_name'] ?? '') . (empty($company['bank_branch']) ? '' : ', ' . $company['bank_branch']))
        ],
        'terms' => [
            'title' => 'Terms and Conditions',
            'conditions' => !empty($termsArray) ? $termsArray : [
                'Payment should be made in favor of ' . ($company['company_name'] ?: 'RYAN PROTECTION FORCE')
            ]
        ],
        'signature' => [
            'title' => 'Authorised Signatory',
            'company_line' => 'For ' . ($company['company_name'] ?: 'RYAN PROTECTION FORCE'),
            'signature_image_path' => $signaturePath
        ],
        'watermark' => [
            'image_path' => $watermarkPath
        ],
        'settings' => [
            'fixed_table_rows' => 25,
            'page_size' => 'A4',
            'currency_symbol' => '₹'
        ]
    ];
}

/**
 * Convert number to words (basic implementation)
 */
function convertNumberToWords($number) {
    // Basic implementation - you can enhance this
    $number = (int)$number;
    
    if ($number == 0) return 'Zero Rupees Only';
    
    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    ];
    
    if ($number < 21) {
        return $words[$number] . ' Rupees Only';
    } elseif ($number < 100) {
        $tens = intval($number / 10) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '') . ' Rupees Only';
    } elseif ($number < 1000) {
        $hundreds = intval($number / 100);
        $remainder = $number % 100;
        $result = $words[$hundreds] . ' Hundred';
        if ($remainder) {
            $result .= ' ' . str_replace(' Rupees Only', '', convertNumberToWords($remainder));
        }
        return $result . ' Rupees Only';
    } elseif ($number < 100000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        $result = convertNumberToWords($thousands);
        $result = str_replace(' Rupees Only', '', $result) . ' Thousand';
        if ($remainder) {
            $result .= ' ' . str_replace(' Rupees Only', '', convertNumberToWords($remainder));
        }
        return $result . ' Rupees Only';
    } elseif ($number < 10000000) {
        $lakhs = intval($number / 100000);
        $remainder = $number % 100000;
        $result = convertNumberToWords($lakhs);
        $result = str_replace(' Rupees Only', '', $result) . ' Lakh';
        if ($remainder) {
            $result .= ' ' . str_replace(' Rupees Only', '', convertNumberToWords($remainder));
        }
        return $result . ' Rupees Only';
    }
    
    return number_format($number) . ' Rupees Only';
}

// PDF functions moved to modular pdf_controller.php

/**
 * Export invoice as PDF using wkhtmltopdf for pixel-perfect rendering
 * This is the most reliable method for PDF generation
 * MOVED TO: actions/pdf_controller.php
 */
function exportPDFWithWkhtmltopdf() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice data and format for template
        $template_data = getInvoiceDataForTemplate($db, $invoiceId);
        
        if (!$template_data) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Generate HTML for PDF using the dynamic template
        require_once __DIR__ . '/../templates/pdf/invoice_template_dynamic_backup.php';
        
        // Render the invoice template for PDF (with $is_pdf = true)
        $html = renderInvoiceTemplateForPDF($template_data);
        
        // ========================================
        // Save temporary HTML file
        // ========================================
        $tmpHtml = sys_get_temp_dir() . "/invoice_" . uniqid() . ".html";
        file_put_contents($tmpHtml, $html);
        
        // Temporary output PDF file
        $tmpPdf = sys_get_temp_dir() . "/invoice_" . uniqid() . ".pdf";
        
        // ========================================
        // Run wkhtmltopdf
        // ========================================
        
        // Detect wkhtmltopdf binary path
        $wkhtml_bin = detectWkhtmltopdfPath();
        
        if (!$wkhtml_bin) {
            unlink($tmpHtml);
            // Fallback to DomPDF if wkhtmltopdf is not available
            exportPDFWithDomPDF();
            return;
        }
        
        // Build the command with proper options for pixel-perfect rendering
        $cmd = escapeshellcmd($wkhtml_bin) . " " . 
               "--enable-local-file-access " .
               "--print-media-type " .
               "--page-size A4 " .
               "--orientation Portrait " .
               "--margin-top 10mm " .
               "--margin-right 10mm " .
               "--margin-bottom 10mm " .
               "--margin-left 10mm " .
               "--encoding UTF-8 " .
               "--disable-smart-shrinking " .
               "--zoom 1.0 " .
               "'$tmpHtml' '$tmpPdf' 2>&1";
        
        // Execute the command
        exec($cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            unlink($tmpHtml);
            http_response_code(500);
            echo "wkhtmltopdf failed:\n" . implode("\n", $output);
            exit;
        }
        
        // Check if PDF was created successfully
        if (!file_exists($tmpPdf) || filesize($tmpPdf) === 0) {
            unlink($tmpHtml);
            if (file_exists($tmpPdf)) unlink($tmpPdf);
            http_response_code(500);
            echo "PDF generation failed - no output file created";
            exit;
        }
        
        // ========================================
        // Stream PDF to browser
        // ========================================
        
        // Format invoice number for filename
        $invoiceNumber = str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
        
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=invoice-{$invoiceNumber}.pdf");
        header("Content-Length: " . filesize($tmpPdf));
        header("Cache-Control: max-age=0");
        
        // Stream the PDF file
        readfile($tmpPdf);
        
        // Cleanup temp files
        unlink($tmpHtml);
        unlink($tmpPdf);
        
    } catch (Exception $e) {
        // Cleanup temp files in case of error
        if (isset($tmpHtml) && file_exists($tmpHtml)) unlink($tmpHtml);
        if (isset($tmpPdf) && file_exists($tmpPdf)) unlink($tmpPdf);
        
        http_response_code(500);
        echo "Error generating PDF: " . $e->getMessage();
    }
}

/**
 * Detect wkhtmltopdf binary path
 * Tries common installation paths
 */
function detectWkhtmltopdfPath() {
    $possible_paths = [
        '/usr/local/bin/wkhtmltopdf',      // macOS Homebrew
        '/usr/bin/wkhtmltopdf',            // Ubuntu/Debian
        '/opt/homebrew/bin/wkhtmltopdf',   // macOS Apple Silicon
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe', // Windows
        'C:\\wkhtmltopdf\\bin\\wkhtmltopdf.exe', // Windows alternative
        'wkhtmltopdf' // Try PATH
    ];
    
    foreach ($possible_paths as $path) {
        if ($path === 'wkhtmltopdf') {
            // Check if it's in PATH
            $output = [];
            exec('which wkhtmltopdf 2>/dev/null', $output);
            if (!empty($output)) {
                return trim($output[0]);
            }
        } else {
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    
    return false;
}

/**
 * Generate DomPDF-optimized HTML that matches the view exactly
 */
function generateDomPDFOptimizedHTML($data) {
    $h = fn($s) => htmlspecialchars($s ?? '');
    
    // Pagination logic
    $allItems = $data['items'];
    $total = count($allItems);
    $maxRowsPerPage = 20;
    
    $pages = [];
    if ($total <= $maxRowsPerPage) {
        $pages[] = $allItems;
    } else {
        while ($total > $maxRowsPerPage) {
            $pages[] = array_slice($allItems, 0, $maxRowsPerPage);
            $allItems = array_slice($allItems, $maxRowsPerPage);
            $total = count($allItems);
        }
        $pages[] = $allItems;
    }
    
    ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif; 
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        .invoice-page {
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 10mm;
            border: 2px solid #000;
            position: relative;
            page-break-after: always;
            background: #fff;
        }
        .invoice-page:last-child { page-break-after: avoid; }
        
        /* Header */
        .header-banner {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
        }
        .bill-of-supply {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .original-box {
            border: 1px solid #333;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
        }
        .tagline {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }
        
        /* Company Section */
        .company-section {
            border: 2px solid #000;
            margin-bottom: 0;
        }
        .company-content {
            display: table;
            width: 100%;
            padding: 8px;
        }
        .logo-area {
            display: table-cell;
            width: 80px;
            vertical-align: top;
            text-align: center;
        }
        .company-info-area {
            display: table-cell;
            vertical-align: top;
            text-align: center;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 6px 0;
            color: #333;
        }
        .company-info {
            font-size: 11px;
            color: #555;
        }
        .contact-line {
            font-size: 10px;
            margin-top: 4px;
        }
        
        /* Bill To Section */
        .bill-to-section {
            border: 2px solid #000;
            border-top: none;
            display: table;
            width: 100%;
        }
        .billto-left {
            display: table-cell;
            width: 55%;
            padding: 8px;
            vertical-align: top;
        }
        .billto-right {
            display: table-cell;
            width: 45%;
            border-left: 2px solid #000;
            padding: 8px;
            vertical-align: middle;
            text-align: center;
        }
        .billto-meta-table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
        }
        .billto-meta-table th {
            font-weight: bold;
            padding: 4px;
            border: 1px solid #333;
        }
        .billto-meta-table td {
            padding: 4px;
            border: 1px solid #333;
            text-align: center;
        }
        
        /* Main Table */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-top: 5mm;
        }
        .main-table th {
            border: 1px solid #333;
            padding: 6px;
            font-size: 11px;
            font-weight: bold;
            background: #f8f8f8;
            text-align: center;
        }
        .main-table td {
            border: 1px solid #333;
            padding: 4px;
            font-size: 9px;
            text-align: center;
            height: 18px;
        }
        .description-cell {
            text-align: left;
        }
        .amount-cell {
            text-align: right;
            font-weight: bold;
        }
        .service-name {
            font-weight: bold;
        }
        .service-description {
            font-style: italic;
            font-size: 8px;
            color: #666;
        }
        
        /* Summary Table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-top: 5mm;
        }
        .summary-table td {
            padding: 6px;
            font-size: 11px;
            border: 1px solid #333;
        }
        .summary-amount {
            text-align: right;
            font-weight: bold;
        }
        
        /* Amount in Words */
        .amount-words-section {
            border: 2px solid #000;
            padding: 6px;
            margin: 8px 0;
            font-size: 10px;
            background: #f9f9f9;
        }
        
        /* Footer */
        .footer-section {
            display: table;
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            border-top: none;
            margin-top: auto;
        }
        .footer-left, .footer-center, .footer-right {
            display: table-cell;
            vertical-align: top;
            padding: 8px;
            font-size: 9px;
            border: 1px solid #000;
        }
        .footer-left { width: 40%; }
        .footer-center { width: 35%; }
        .footer-right { width: 25%; text-align: center; }
        .footer-title {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 100px;
            margin: 0 auto 6px;
            height: 25px;
        }
        .signature-text {
            font-size: 9px;
            font-weight: bold;
            color: #333;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            opacity: 0.3;
            left: 25.4mm;
            bottom: 63.5mm;
            width: 159.2mm;
            height: 150mm;
            z-index: 1;
        }
        .watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* Page Number */
        .page-number {
            position: absolute;
            bottom: 5mm;
            right: 10mm;
            font-size: 10px;
            font-style: italic;
            color: #555;
        }
    </style>
</head>
<body>
    <?php foreach ($pages as $pageIndex => $slice): 
        $isLastPage = ($pageIndex === count($pages) - 1); ?>
        <div class="invoice-page">
            <?php if ($data['watermark']['image_path']): ?>
                <div class="watermark">
                    <img src="<?= $h($data['watermark']['image_path']) ?>" alt="Watermark">
                </div>
            <?php endif; ?>
            
            <!-- Header Banner -->
            <div class="header-banner">
                <div class="header-left">
                    <span class="bill-of-supply"><?= $h($data['header']['bill_of_supply']) ?></span>
                    <span class="original-box"><?= $h($data['header']['original_text']) ?></span>
                </div>
                <div class="header-right">
                    <div class="tagline"><?= $h($data['header']['tagline']) ?></div>
                </div>
            </div>
            
            <!-- Company Section -->
            <div class="company-section">
                <div class="company-content">
                    <div class="logo-area">
                        <?php if ($data['company']['logo_path']): ?>
                            <img src="<?= $h($data['company']['logo_path']) ?>" alt="Logo" style="width: 80px; height: 80px;">
                        <?php endif; ?>
                    </div>
                    <div class="company-info-area">
                        <div class="company-name"><?= $h($data['company']['name']) ?></div>
                        <div class="company-info"><?= $h($data['company']['address']) ?></div>
                        <div class="contact-line">
                            <span>Mobile: <?= $h($data['company']['mobile']) ?></span>
                            <span>Email: <?= $h($data['company']['email']) ?></span>
                            <span>PAN: <?= $h($data['company']['pan']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bill To Section -->
            <div class="bill-to-section">
                <div class="billto-left">
                    <div style="font-weight: bold;"><?= $h($data['client']['title']) ?></div>
                    <div style="font-weight: bold;"><?= $h($data['client']['name']) ?></div>
                    <div><?= $h($data['client']['address']) ?><br>PAN: <?= $h($data['client']['pan']) ?></div>
                </div>
                <div class="billto-right">
                    <table class="billto-meta-table">
                        <tr>
                            <th>Invoice No.</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                        </tr>
                        <tr>
                            <td><?= $h($data['invoice_meta']['invoice_no']) ?></td>
                            <td><?= $h($data['invoice_meta']['invoice_date']) ?></td>
                            <td><?= $h($data['invoice_meta']['due_date']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="main-table">
                <thead>
                    <tr>
                        <th>S.NO</th>
                        <th>Items/Services</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slice as $item): ?>
                        <tr>
                            <td><?= $h($item['sno'] ?? '') ?></td>
                            <td class="description-cell">
                                <div class="service-name"><?= $h($item['service_name'] ?? '') ?></div>
                                <div class="service-description"><?= $h($item['service_description'] ?? '') ?></div>
                            </td>
                            <td><?= $h($item['quantity'] ?? '') ?></td>
                            <td><?= $h($item['rate'] ?? '') ?></td>
                            <td class="amount-cell"><?= $h($item['amount'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($isLastPage): ?>
                <!-- Summary -->
                <table class="summary-table">
                    <tr>
                        <td colspan="4" style="text-align: right;">Round Off</td>
                        <td class="summary-amount"><?= $h($data['summary']['round_off']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="summary-amount"><?= $h($data['summary']['total']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;">Received</td>
                        <td class="summary-amount"><?= $h($data['summary']['received_amount']) ?></td>
                    </tr>
                </table>
                
                <div class="amount-words-section">
                    <strong>Total in words:</strong> <?= $h($data['summary']['amount_in_words']) ?>
                </div>
                
                <!-- Footer -->
                <div class="footer-section">
                    <div class="footer-left">
                        <div class="footer-title"><?= $h($data['bank_details']['title']) ?></div>
                        Name: <?= $h($data['bank_details']['name']) ?><br>
                        IFSC: <?= $h($data['bank_details']['ifsc_code']) ?><br>
                        A/C: <?= $h($data['bank_details']['account_no']) ?><br>
                        Bank: <?= $h($data['bank_details']['bank']) ?>
                    </div>
                    <div class="footer-center">
                        <div class="footer-title"><?= $h($data['terms']['title']) ?></div>
                        <?php foreach ($data['terms']['conditions'] as $i => $condition): ?>
                            <?= ($i+1) ?>. <?= $h($condition) ?><br>
                        <?php endforeach; ?>
                    </div>
                    <div class="footer-right">
                        <div class="signature-area">
                            <?php if ($data['signature']['signature_image_path']): ?>
                                <img src="<?= $h($data['signature']['signature_image_path']) ?>" style="max-width: 150px; max-height: 50px;">
                            <?php else: ?>
                                <div class="signature-line"></div>
                            <?php endif; ?>
                            <div class="signature-text"><?= $h($data['signature']['title']) ?></div>
                            <div class="signature-text"><?= $h($data['signature']['company_line']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (count($pages) > 1): ?>
                <div class="page-number">Page <?= ($pageIndex + 1) ?> of <?= count($pages) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Fallback PDF export using DomPDF
 * This function provides PDF export when wkhtmltopdf is not available
 */
function exportPDFWithDomPDF() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice data and format for template
        $template_data = getInvoiceDataForTemplate($db, $invoiceId);
        
        if (!$template_data) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Generate HTML for PDF using the exact same template as view but with PDF optimizations
        require_once __DIR__ . '/../templates/pdf/invoice_template_dynamic_backup.php';
        
        // Render the invoice template for PDF (with $is_pdf = true)
        $html = renderInvoiceTemplateForPDF($template_data);
        
        // Add DomPDF-specific optimizations with increased scaling
        $html = str_replace('</head>', '
        <style>
        /* DomPDF Optimizations with increased scaling */
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif !important;
            font-size: 9px !important; /* Further reduced from 10px */
            margin: 0 !important;
            padding: 0 !important;
            transform: scale(0.80) !important; /* Scale down entire content by 20% */
            transform-origin: top left !important;
            width: 125% !important; /* Compensate for scale (100/0.80) */
            height: 125% !important; /* Compensate for scale (100/0.80) */
        }
        .invoice-container { 
            width: 180mm !important; /* Further reduced from 190mm */
            height: 250mm !important; /* Further reduced from 270mm */
            margin: 0 !important;
            padding: 6mm !important; /* Further reduced from 8mm */
            box-shadow: none !important;
            border: 2px solid #000 !important;
            overflow: hidden !important;
            page-break-after: avoid !important;
        }
        .invoice-container:last-child { 
            page-break-after: avoid !important; 
            margin-bottom: 0 !important;
        }
        
        /* Fix table widths with tighter proportions */
        .main-table {
            width: 100% !important;
            table-layout: fixed !important;
            font-size: 7px !important; /* Even smaller font for table */
        }
        .main-table th:nth-child(1) { width: 8% !important; }  /* S.NO - narrower */
        .main-table th:nth-child(2) { width: 50% !important; } /* Items/Services - wider for content */
        .main-table th:nth-child(3) { width: 12% !important; } /* Qty - narrower */
        .main-table th:nth-child(4) { width: 15% !important; } /* Rate */
        .main-table th:nth-child(5) { width: 15% !important; } /* Amount */
        
        /* Fix content overflow with even smaller fonts */
        .main-table td {
            word-wrap: break-word !important;
            overflow: hidden !important;
            padding: 2px !important; /* Further reduced padding */
            font-size: 7px !important; /* Even smaller font */
            line-height: 1.1 !important;
        }
        
        /* Fix watermark positioning with increased scaling */
        .watermark {
            position: absolute !important;
            opacity: 0.3 !important;
            left: 15mm !important; /* Further adjusted for smaller container */
            bottom: 40mm !important; /* Further adjusted for smaller container */
            width: 120mm !important; /* Further reduced width */
            height: 100mm !important; /* Further reduced height */
            z-index: 1 !important;
        }
        
        /* Fix footer layout with smaller fonts */
        .footer-section {
            display: table !important;
            width: 100% !important;
            table-layout: fixed !important;
            font-size: 8px !important;
        }
        .footer-left { width: 40% !important; }
        .footer-center { width: 35% !important; }
        .footer-right { width: 25% !important; }
        
        /* Remove any flexbox that might cause issues */
        .top-banner, .company-content, .bill-to-section {
            display: table !important;
            width: 100% !important;
        }
        .top-banner > div, .company-content > div, .bill-to-section > div {
            display: table-cell !important;
            vertical-align: top !important;
        }
        
        /* Reduce font sizes throughout - more aggressive */
        .company-name { font-size: 12px !important; } /* Further reduced from 14px */
        .bill-of-supply { font-size: 12px !important; } /* Further reduced from 14px */
        .tagline { font-size: 8px !important; } /* Further reduced from 9px */
        .company-info { font-size: 8px !important; } /* Further reduced from 9px */
        .contact-line { font-size: 7px !important; } /* Further reduced from 8px */
        .billto-meta-table { font-size: 8px !important; } /* Further reduced from 9px */
        .summary-table { font-size: 8px !important; } /* Further reduced from 9px */
        .amount-words-section { font-size: 7px !important; } /* Further reduced from 8px */
        .footer-title { font-size: 7px !important; } /* Further reduced from 8px */
        .signature-text { font-size: 6px !important; } /* Further reduced from 7px */
        </style>
        </head>', $html);
        
        // Generate PDF using DomPDF
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isJavascriptEnabled', false);
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        $options->set('debugLayoutLines', false);
        $options->set('debugLayoutBlocks', false);
        $options->set('debugLayoutInline', false);
        $options->set('debugLayoutPaddingBox', false);
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Format invoice number for filename
        $invoiceNumber = str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice-' . $invoiceNumber . '.pdf"');
        header('Cache-Control: max-age=0');
        
        echo $dompdf->output();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error generating PDF: " . $e->getMessage();
    }
}