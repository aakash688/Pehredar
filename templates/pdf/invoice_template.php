<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill of Supply - <?= htmlspecialchars($company_settings['company_name'] ?? 'Company') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>

    <style>
        @page {
            size: A4;
            margin: 15mm 10mm 15mm 10mm;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #000;
            background-color: #fff;
            line-height: 1.4;
            font-size: 11px;
            width: 100%;
        }
        
        .invoice-container {
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #fff;
            position: relative;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            opacity: 0.3;
            z-index: 1;
            pointer-events: none;
            /* Fixed size: A4 width (210mm) - 2 inches (50.8mm) = 159.2mm */
            width: 159.2mm;
            /* Height: A4 height (297mm) - 2.5 inches (63.5mm) from bottom - some top margin = ~150mm max */
            height: 150mm;
            /* Center horizontally with 1 inch (25.4mm) from each side */
            left: 25.4mm;
            /* Position with 2.5 inch (63.5mm) from bottom */
            bottom: 63.5mm;
        }
        
        .watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }
        
        /* Header Section */
        .header {
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .bill-title {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            color: #000;
            margin: 0;
        }
        
        .original-badge {
            background-color: #f0f0f0;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 500;
            color: #666;
        }
        
        .tagline {
            font-size: 10px;
            font-style: italic;
            color: #666;
            margin: 0;
        }
        
        .company-section {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #d4af37, #b8860b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .company-logo-text {
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: 700;
            color: #000;
            margin: 0 0 8px 0;
            text-transform: uppercase;
        }
        
        .company-details {
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        
        .company-details p {
            margin: 2px 0;
        }
        
        /* Bill To Section */
        .bill-to-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .bill-to-info {
            flex: 1;
        }
        
        .bill-to-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #000;
            margin: 0 0 8px 0;
        }
        
        .client-name {
            font-size: 13px;
            font-weight: 600;
            color: #000;
            margin: 0 0 5px 0;
        }
        
        .client-details {
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        
        .invoice-details {
            text-align: right;
            font-size: 10px;
        }
        
        .invoice-details p {
            margin: 2px 0;
            color: #333;
        }
        
        .invoice-number {
            font-weight: 600;
            color: #000;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            background-color: transparent; /* Make table background transparent */
        }
        
        .items-table th {
            background-color: transparent; /* Make header transparent */
            border: 1px solid #dee2e6;
            padding: 8px 6px;
            text-align: center;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            color: #000;
        }
        
        .items-table td {
            border: 1px solid #dee2e6;
            padding: 6px;
            font-size: 10px;
            vertical-align: top;
            background-color: transparent; /* Make cells transparent */
        }
        
        .item-description {
            text-align: left;
            width: 50%;
        }
        
        .item-qty, .item-rate, .item-amount {
            text-align: center;
            width: 15%;
        }
        
        .item-amount {
            text-align: right;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .total-amount {
            font-size: 12px;
            font-weight: 700;
            color: #000;
        }
        
        /* Amount in Words */
        .amount-words {
            margin: 10px 0;
            font-size: 10px;
            font-style: italic;
            color: #333;
            position: relative;
            z-index: 1;
        }
        
        /* Footer Section */
        .footer-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }
        
        .footer-column {
            flex: 1;
            margin-right: 20px;
        }
        
        .footer-column:last-child {
            margin-right: 0;
        }
        
        .footer-title {
            font-size: 10px;
            font-weight: 600;
            color: #000;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        
        .footer-content {
            font-size: 9px;
            line-height: 1.3;
            color: #333;
        }
        
        .footer-content p {
            margin: 2px 0;
        }
        
        .signature-section {
            text-align: center;
        }
        
        .signature-image {
            width: 80px;
            height: 40px;
            margin: 10px 0 5px 0;
            border-bottom: 1px solid #000;
        }
        
        .signature-text {
            font-size: 9px;
            color: #333;
            margin: 0;
        }
        
        /* Print Button Styles */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .print-btn, .pdf-btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background-color 0.2s;
        }

        .print-btn:hover, .pdf-btn:hover {
            background: #4338ca;
        }

        /* Print Styles */
        @media print {
            body {
                font-size: 10px;
            }
            
            .invoice-container {
                padding: 0;
            }
            
            .watermark {
                opacity: 0.3;
                position: absolute;
                width: 159.2mm;
                height: 150mm;
                left: 25.4mm;
                bottom: 63.5mm;
            }

            .print-controls {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <button class="print-btn" onclick="printInvoice()">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button class="pdf-btn" onclick="downloadPDF()">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button>
    </div>

    <div class="invoice-container">
        <!-- Watermark - Positioned within invoice container -->
        <?php if (!empty($company_settings['watermark_image_path'])): ?>
        <div class="watermark">
            <img src="<?php echo htmlspecialchars($company_settings['watermark_image_path']); ?>" alt="Company Watermark">
        </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div>
                    <h1 class="bill-title">Bill of Supply</h1>
                    <div class="original-badge">ORIGINAL FOR RECIPIENT</div>
                </div>
                <div>
                    <p class="tagline">SECURITAS: Your trusted security partner for a safer tomorrow.</p>
                </div>
            </div>
            
            <div class="company-section">
                <div class="company-logo">
                    <div class="company-logo-text">RPF</div>
                </div>
                <div class="company-info">
                    <h2 class="company-name"><?= htmlspecialchars($company_settings['company_name'] ?? 'RYAN PROTECTION FORCE') ?></h2>
                    <div class="company-details">
                        <p><?= htmlspecialchars($company_settings['street_address'] ?? 'Shop No. 3 Bramha Tower, Plot no. 40, Sector no. 2, Charkop, Kandivali-West, Mumbai, Maharashtra, 400067') ?></p>
                        <p>Mobile: <?= htmlspecialchars($company_settings['phone'] ?? '9702295293') ?></p>
                        <p>Email: <?= htmlspecialchars($company_settings['email'] ?? 'ryanprotectionforce@gmail.com') ?></p>
                        <p>PAN Number: <?= htmlspecialchars($company_settings['pan_number'] ?? 'ATDPM0414C') ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bill To Section -->
        <div class="bill-to-section">
            <div class="bill-to-info">
                <h3 class="bill-to-label">Bill To</h3>
                <h4 class="client-name"><?= htmlspecialchars($invoice['society_name'] ?? 'ACME AVENUE A WING CHS LTD') ?></h4>
                <div class="client-details">
                    <p><?= htmlspecialchars($invoice['address'] ?? 'Bhabrekar nagar Charkop, Kandivali West, Mumbai, Maharashtra, 400067') ?></p>
                    <p>PAN Number: <?= htmlspecialchars($invoice['pan_number'] ?? 'AMAPM0686Q') ?></p>
                </div>
            </div>
            <div class="invoice-details">
                <p class="invoice-number">Invoice No. <?= htmlspecialchars($invoice['invoice_number'] ?? 'RP/SL/25-26/94') ?></p>
                <p>Invoice Date <?= htmlspecialchars(date('d/m/Y', strtotime($invoice['created_at'] ?? 'now'))) ?></p>
                <p>Due Date <?= htmlspecialchars(date('d/m/Y', strtotime($invoice['due_date'] ?? '+7 days'))) ?></p>
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>S.NO</th>
                    <th class="item-description">ITEMS/SERVICES</th>
                    <th class="item-qty">QTY.</th>
                    <th class="item-rate">RATE</th>
                    <th class="item-amount">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td class="item-description">
                            <strong><?= htmlspecialchars($item['employee_type']) ?></strong><br>
                            <small><?= htmlspecialchars($item['employee_type']) ?> charges for the month of <?= htmlspecialchars($invoice['formatted_month'] ?? 'August 2005') ?></small>
                        </td>
                        <td class="item-qty"><?= htmlspecialchars($item['quantity']) ?> NOS</td>
                        <td class="item-rate"><?= number_format($item['rate'], 2) ?></td>
                        <td class="item-amount"><?= number_format($item['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Service Charges Row -->
                <tr>
                    <td><?= count($items) + 1 ?></td>
                    <td class="item-description">
                        <strong>Service charges</strong><br>
                        <small>service charges @10%</small>
                    </td>
                    <td class="item-qty">1 PCS</td>
                    <td class="item-rate"><?= number_format($invoice['subtotal'] * 0.1, 2) ?></td>
                    <td class="item-amount"><?= number_format($invoice['subtotal'] * 0.1, 2) ?></td>
                </tr>
                
                <!-- Round Off -->
                <tr>
                    <td colspan="4" style="text-align: right; font-weight: 600;">Round Off</td>
                    <td class="item-amount">₹ 0.01</td>
                </tr>
                
                <!-- Total -->
                <tr class="total-row">
                    <td colspan="4" style="text-align: right; font-weight: 700;">TOTAL</td>
                    <td class="item-amount total-amount">₹ <?= number_format($invoice['total_amount'], 2) ?></td>
                </tr>
                
                <!-- Received Amount -->
                <tr>
                    <td colspan="4" style="text-align: right; font-weight: 600;">RECEIVED AMOUNT</td>
                    <td class="item-amount">₹ <?= number_format($invoice['paid_amount'] ?? 0, 2) ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Amount in Words -->
        <div class="amount-words">
            <strong><?= ucwords(convertNumberToWords($invoice['total_amount'])) ?> Rupees</strong>
        </div>
        
        <!-- Footer Section -->
        <div class="footer-section">
            <!-- Bank Details -->
            <div class="footer-column">
                <h4 class="footer-title">Bank Details</h4>
                <div class="footer-content">
                    <p><strong>Name:</strong> <?= htmlspecialchars($company_settings['company_name'] ?? 'RYAN PROTECTION FORCE') ?></p>
                    <p><strong>IFSC Code:</strong> <?= htmlspecialchars($company_settings['bank_ifsc_code'] ?? 'SURYOBKD000') ?></p>
                    <p><strong>Account No:</strong> <?= htmlspecialchars($company_settings['bank_account_number'] ?? '2120020001301') ?></p>
                    <p><strong>Bank:</strong> <?= htmlspecialchars($company_settings['bank_name'] ?? 'Suryoday Small Finance Bank/CENTRAL BACK OFFICE') ?></p>
                </div>
            </div>
            
            <!-- Terms and Conditions -->
            <div class="footer-column">
                <h4 class="footer-title">Terms and Conditions</h4>
                <div class="footer-content">
                    <p>1: Payment should be name with <?= htmlspecialchars($company_settings['company_name'] ?? 'RYAN PROTECTION FORCE') ?></p>
                    <p>2: For TDS PAN NO. <?= htmlspecialchars($company_settings['pan_number'] ?? 'AMAPM06860') ?></p>
                </div>
            </div>
            
            <!-- Authorized Signatory -->
            <div class="footer-column signature-section">
                <div class="signature-image"></div>
                <p class="signature-text">Authorised Signatory For <?= htmlspecialchars($company_settings['company_name'] ?? 'RYAN PROTECTION FORCE') ?></p>
            </div>
        </div>
    </div>
</body>
</html>

<script>
// Print functionality
function printInvoice() {
    // Hide print controls before printing
    const printControls = document.querySelector('.print-controls');
    if (printControls) {
        printControls.style.display = 'none';
    }
    
    // Set print-specific styles
    document.body.classList.add('printing');
    
    // Trigger print
    window.print();
    
    // Restore print controls after printing
    setTimeout(() => {
        if (printControls) {
            printControls.style.display = 'flex';
        }
        document.body.classList.remove('printing');
    }, 1000);
}

// PDF Download functionality
function downloadPDF() {
    // Get current URL parameters to determine invoice ID
    const urlParams = new URLSearchParams(window.location.search);
    const invoiceId = urlParams.get('id') || urlParams.get('invoice_id');
    
    if (invoiceId) {
        // Use absolute path from project root to avoid double actions path
        const pdfUrl = `/project/Gaurd/actions/invoice_controller.php?action=export_invoice&id=${invoiceId}`;
        
        // Open in new tab for download
        window.open(pdfUrl, '_blank');
    } else {
        alert('Unable to determine invoice ID for PDF generation.');
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printInvoice();
    }
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        downloadPDF();
    }
});

// Handle print media queries for better print layout
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
</script>

<?php
// Helper function to convert numbers to words
function convertNumberToWords($number) {
    $ones = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    );
    
    $tens = array(
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    
    $hundreds = array(
        100 => 'Hundred', 1000 => 'Thousand', 100000 => 'Lakh', 10000000 => 'Crore'
    );
    
    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        return $tens[10 * floor($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
    } elseif ($number < 1000) {
        return $ones[floor($number / 100)] . ' Hundred' . ($number % 100 ? ' ' . convertNumberToWords($number % 100) : '');
    } elseif ($number < 100000) {
        return convertNumberToWords(floor($number / 1000)) . ' Thousand' . ($number % 1000 ? ' ' . convertNumberToWords($number % 1000) : '');
    } elseif ($number < 10000000) {
        return convertNumberToWords(floor($number / 100000)) . ' Lakh' . ($number % 100000 ? ' ' . convertNumberToWords($number % 100000) : '');
    } else {
        return convertNumberToWords(floor($number / 10000000)) . ' Crore' . ($number % 10000000 ? ' ' . convertNumberToWords($number % 10000000) : '');
    }
}
?>
