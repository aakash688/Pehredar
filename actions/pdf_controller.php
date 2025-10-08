<?php
// actions/pdf_controller.php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

// Check if action is set
$action = $_GET['action'] ?? '';

// Handle different PDF actions
switch ($action) {
    case 'export_pdf':
        exportPDFWithWkhtmltopdf();
        break;
    case 'export_pdf_fallback':
        exportPDFWithDomPDF();
        break;
    case 'export_pdf_optimized':
        exportPDFOptimized();
        break;
    case 'test_pdf_generation':
        testPDFGeneration();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid PDF action specified'], 400);
        break;
}

/**
 * Export invoice as PDF using wkhtmltopdf for pixel-perfect rendering
 * This is the most reliable method for PDF generation
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
        header("Content-Disposition: attachment; filename=invoice-{$invoiceNumber}.pdf");
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
 * Fallback PDF export using DomPDF with optimized scaling
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
        $html = applyDomPDFOptimizations($html);
        
        // Generate PDF using DomPDF
        $pdf = generateDomPDF($html);
        
        // Format invoice number for filename
        $invoiceNumber = str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice-' . $invoiceNumber . '.pdf"');
        header('Cache-Control: max-age=0');
        
        echo $pdf;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error generating PDF: " . $e->getMessage();
    }
}

/**
 * Export PDF with optimized settings (new modular approach)
 */
function exportPDFOptimized() {
    $db = new Database();
    
    try {
        $invoiceId = $_GET['id'] ?? null;
        $scale = $_GET['scale'] ?? '0.95'; // Allow custom scaling
        $method = $_GET['method'] ?? 'auto'; // auto, wkhtmltopdf, dompdf
        
        if (!$invoiceId) {
            http_response_code(400);
            echo "Invoice ID is required";
            exit;
        }
        
        // Get invoice data
        $template_data = getInvoiceDataForTemplate($db, $invoiceId);
        
        if (!$template_data) {
            http_response_code(404);
            echo "Invoice not found";
            exit;
        }
        
        // Generate HTML
        require_once __DIR__ . '/../templates/pdf/invoice_template_dynamic_backup.php';
        $html = renderInvoiceTemplateForPDF($template_data);
        
        // Apply optimizations based on method
        if ($method === 'wkhtmltopdf' || ($method === 'auto' && detectWkhtmltopdfPath())) {
            // Use wkhtmltopdf
            exportPDFWithWkhtmltopdf();
        } else {
            // Use DomPDF with custom scaling
            $html = applyDomPDFOptimizations($html, $scale);
            $pdf = generateDomPDF($html);
            
            $invoiceNumber = str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="invoice-' . $invoiceNumber . '.pdf"');
            header('Cache-Control: max-age=0');
            
            echo $pdf;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error generating PDF: " . $e->getMessage();
    }
}

/**
 * Test PDF generation with different settings
 */
function testPDFGeneration() {
    $invoiceId = $_GET['id'] ?? '48';
    $scale = $_GET['scale'] ?? '0.80';
    
    echo "Testing PDF Generation\n";
    echo "====================\n\n";
    echo "Invoice ID: $invoiceId\n";
    echo "Scale: $scale\n";
    echo "Method: " . (detectWkhtmltopdfPath() ? 'wkhtmltopdf' : 'DomPDF') . "\n\n";
    
    $url = "http://localhost/project/Gaurd/actions/pdf_controller.php?action=export_pdf_optimized&id=$invoiceId&scale=$scale";
    echo "Test URL: $url\n\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET'
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        echo "❌ Failed to generate PDF\n";
        echo "Error: " . error_get_last()['message'] . "\n";
    } else {
        $size = strlen($result);
        echo "✅ PDF generated successfully!\n";
        echo "Size: $size bytes\n";
        echo "Method: " . (detectWkhtmltopdfPath() ? 'wkhtmltopdf' : 'DomPDF') . "\n";
    }
}

/**
 * Apply DomPDF-specific optimizations to HTML
 */
function applyDomPDFOptimizations($html, $scale = '0.95') {
    $scalePercent = (100 / floatval($scale));
    
    return str_replace('</head>', '
    <style>
    /* DomPDF Optimizations with custom scaling */
    body { 
        font-family: "DejaVu Sans", Arial, sans-serif !important;
        font-size: 9px !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: scale(' . $scale . ') !important;
        transform-origin: top left !important;
        width: ' . $scalePercent . '% !important;
        height: ' . $scalePercent . '% !important;
    }
     .invoice-container { 
         width: 200mm !important;
         height: 280mm !important;
         margin: 0 !important;
         padding: 8mm !important;
         box-shadow: none !important;
         border: none !important;
         overflow: hidden !important;
         page-break-after: avoid !important;
     }
    .invoice-container:last-child { 
        page-break-after: avoid !important; 
        margin-bottom: 0 !important;
    }
    
     /* Fix table widths with better proportions for larger size */
     .main-table {
         width: 100% !important;
         table-layout: fixed !important;
         font-size: 9px !important;
     }
    .main-table th:nth-child(1) { width: 8% !important; }
    .main-table th:nth-child(2) { width: 50% !important; }
    .main-table th:nth-child(3) { width: 12% !important; }
    .main-table th:nth-child(4) { width: 15% !important; }
    .main-table th:nth-child(5) { width: 15% !important; }
    
     /* Fix content overflow with larger fonts for better readability */
     .main-table td {
         word-wrap: break-word !important;
         overflow: hidden !important;
         padding: 3px !important;
         font-size: 9px !important;
         line-height: 1.2 !important;
     }
    
     /* Fix watermark positioning with scaling */
     .watermark {
         position: absolute !important;
         opacity: 0.3 !important;
         left: 20mm !important;
         bottom: 50mm !important;
         width: 150mm !important;
         height: 130mm !important;
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
    
     /* Increase font sizes throughout for better readability */
     .company-name { font-size: 16px !important; }
     .bill-of-supply { font-size: 16px !important; }
     .tagline { font-size: 11px !important; }
     .company-info { font-size: 11px !important; }
     .contact-line { font-size: 10px !important; }
     .billto-meta-table { font-size: 11px !important; }
     .summary-table { font-size: 11px !important; }
     .amount-words-section { font-size: 10px !important; }
     .footer-title { font-size: 10px !important; }
     .signature-text { font-size: 9px !important; }
    </style>
    </head>', $html);
}

/**
 * Generate PDF using DomPDF with optimized settings
 */
function generateDomPDF($html) {
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
    
    return $dompdf->output();
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
 * Convert database invoice data to new template format
 * (Moved from invoice_controller.php for modularity)
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
    $invoiceNumber = 'RP/SL/' . date('y', strtotime($invoice['created_at'])) . '-' . date('y', strtotime($invoice['created_at']) + 31536000) . '/' . str_pad($invoice['id'], 3, '0', STR_PAD_LEFT);
    
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
    
    $receivedAmount = $invoice['status'] === 'paid' ? $total : 0;
    
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

    // Prepare signature image path (absolute URL if relative)
    $signaturePath = '';
    if (!empty($company['signature_image'])) {
        $sig = $company['signature_image'];
        if (preg_match('/^https?:\/\//i', $sig)) {
            $signaturePath = $sig;
        } else {
            $signaturePath = 'http://localhost/project/Gaurd/' . ltrim($sig, '/');
        }
    }

    // Prepare watermark image path (absolute URL if relative)
    $watermarkPath = '';
    if (!empty($company['watermark_image_path'])) {
        $watermark = $company['watermark_image_path'];
        if (preg_match('/^https?:\/\//i', $watermark)) {
            $watermarkPath = $watermark;
        } else {
            $watermarkPath = 'http://localhost/project/Gaurd/' . ltrim($watermark, '/');
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
            'logo_path' => 'http://localhost/project/Gaurd/Comapany/assets/logo-68c28e6699999-rpf%20logo.jpeg'
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
            'received_amount' => '₹ ' . number_format($receivedAmount, 2),
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
?>
