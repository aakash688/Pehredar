<?php
/**
 * Invoice Template Usage Example
 * 
 * This file demonstrates how to use the invoice template with custom data
 */

require_once 'invoice_template_dynamic.php';

// Example 1: Using default JSON data
echo "<h2>Example 1: Default Data</h2>";
echo renderInvoiceTemplate();

// Example 2: Using custom data
echo "<h2>Example 2: Custom Data</h2>";
$custom_data = [
    "company" => [
        "name" => "CUSTOM SECURITY SERVICES",
        "address" => "123 Business Park, Sector 5, New Mumbai, Maharashtra, 400001",
        "mobile" => "9876543210",
        "email" => "info@customsecurity.com",
        "pan" => "ABCDE1234F",
        "logo_path" => "http://localhost/project/Gaurd/Comapany/assets/logo-68c28e6699999-rpf%20logo.jpeg"
    ],
    "client" => [
        "title" => "BILL TO",
        "name" => "SAMPLE CLIENT COMPANY LTD",
        "address" => "456 Corporate Avenue, Business District, Mumbai, Maharashtra, 400002",
        "pan" => "XYZKL9876M"
    ],
    "invoice_meta" => [
        "invoice_no" => "CS/INV/2025/001",
        "invoice_date" => "15/09/2025",
        "due_date" => "30/09/2025"
    ],
    "header" => [
        "bill_of_supply" => "INVOICE",
        "original_text" => "ORIGINAL FOR RECIPIENT",
        "tagline" => "Your Security is Our Priority - Professional Protection Services"
    ],
    "items" => [
        [
            "sno" => 1,
            "service_name" => "Security Guard Services",
            "service_description" => "24x7 Security guard services for September 2025",
            "quantity" => "30 Days",
            "rate" => "₹ 2,000.00",
            "amount" => "₹ 60,000.00"
        ],
        [
            "sno" => 2,
            "service_name" => "CCTV Monitoring",
            "service_description" => "Remote CCTV monitoring and surveillance",
            "quantity" => "1 Month",
            "rate" => "₹ 15,000.00",
            "amount" => "₹ 15,000.00"
        ]
    ],
    "summary" => [
        "round_off" => "₹ 0.00",
        "total" => "₹ 75,000.00",
        "received_amount" => "₹ 0.00",
        "amount_in_words" => "Seventy Five Thousand Rupees Only"
    ],
    "bank_details" => [
        "title" => "Bank Details",
        "name" => "CUSTOM SECURITY SERVICES",
        "ifsc_code" => "HDFC0001234",
        "account_no" => "12345678901234",
        "bank" => "HDFC Bank, Business Branch"
    ],
    "terms" => [
        "title" => "Terms and Conditions",
        "conditions" => [
            "Payment should be made in favor of CUSTOM SECURITY SERVICES",
            "All disputes subject to Mumbai jurisdiction"
        ]
    ],
    "signature" => [
        "title" => "Authorized Signatory",
        "company_line" => "For CUSTOM SECURITY SERVICES",
        "signature_image_path" => ""
    ],
    "settings" => [
        "fixed_table_rows" => 25,
        "page_size" => "A4",
        "currency_symbol" => "₹"
    ]
];

echo renderInvoiceTemplate($custom_data);

// Example 3: Minimal Data (shows fixed table height with mostly empty rows)
echo "<h2>Example 3: Minimal Data (1 Item)</h2>";
$minimal_data = [
    "company" => [
        "name" => "MINIMAL SECURITY CO",
        "address" => "789 Small Street, Local Area, Mumbai, Maharashtra, 400003",
        "mobile" => "9111111111",
        "email" => "contact@minimal.com",
        "pan" => "MINPAN123A",
        "logo_path" => "http://localhost/project/Gaurd/Comapany/assets/logo-68c28e6699999-rpf%20logo.jpeg"
    ],
    "client" => [
        "title" => "BILL TO",
        "name" => "SMALL CLIENT LTD",
        "address" => "Small Office, Mumbai, Maharashtra, 400004",
        "pan" => "SMALLPAN01"
    ],
    "invoice_meta" => [
        "invoice_no" => "MIN/001/2025",
        "invoice_date" => "16/09/2025",
        "due_date" => "30/09/2025"
    ],
    "header" => [
        "bill_of_supply" => "BILL OF SUPPLY",
        "original_text" => "ORIGINAL FOR RECIPIENT",
        "tagline" => "Small but Professional Security Services"
    ],
    "items" => [
        [
            "sno" => 1,
            "service_name" => "Basic Security",
            "service_description" => "Basic security service for one day",
            "quantity" => "1 Day",
            "rate" => "₹ 1,000.00",
            "amount" => "₹ 1,000.00"
        ]
    ],
    "summary" => [
        "round_off" => "₹ 0.00",
        "total" => "₹ 1,000.00",
        "received_amount" => "₹ 0.00",
        "amount_in_words" => "One Thousand Rupees Only"
    ],
    "bank_details" => [
        "title" => "Bank Details",
        "name" => "MINIMAL SECURITY CO",
        "ifsc_code" => "SBIN0001234",
        "account_no" => "1234567890",
        "bank" => "State Bank of India, Local Branch"
    ],
    "terms" => [
        "title" => "Terms and Conditions",
        "conditions" => [
            "Payment should be made in favor of MINIMAL SECURITY CO"
        ]
    ],
    "signature" => [
        "title" => "Authorized Signatory",
        "company_line" => "For MINIMAL SECURITY CO",
        "signature_image_path" => ""
    ],
    "settings" => [
        "fixed_table_rows" => 25,
        "page_size" => "A4",
        "currency_symbol" => "₹"
    ]
];

echo renderInvoiceTemplate($minimal_data);

// Example 4: Database Integration Guide
echo "<h2>Example 4: Database Integration Guide</h2>";
echo "<pre>";
echo "// Fixed Table Height System - Database Integration:

// 1. Fetch invoice data from database
\$invoice_id = 123;
\$invoice = getInvoiceFromDatabase(\$invoice_id);
\$company = getCompanyDetails(\$invoice['company_id']);
\$client = getClientDetails(\$invoice['client_id']);
\$items = getInvoiceItems(\$invoice_id);

// 2. Format data for template (IMPORTANT: Use 'fixed_table_rows' not 'empty_rows')
\$template_data = [
    'company' => [
        'name' => \$company['name'],
        'address' => \$company['address'],
        'mobile' => \$company['mobile'],
        'email' => \$company['email'],
        'pan' => \$company['pan'],
        'logo_path' => \$company['logo_url']
    ],
    'client' => [
        'title' => 'BILL TO',
        'name' => \$client['name'],
        'address' => \$client['address'],
        'pan' => \$client['pan']
    ],
    'invoice_meta' => [
        'invoice_no' => \$invoice['invoice_number'],
        'invoice_date' => date('d/m/Y', strtotime(\$invoice['invoice_date'])),
        'due_date' => date('d/m/Y', strtotime(\$invoice['due_date']))
    ],
    'header' => [
        'bill_of_supply' => 'BILL OF SUPPLY',
        'original_text' => 'ORIGINAL FOR RECIPIENT',
        'tagline' => 'Your trusted security partner for a safer tomorrow.'
    ],
    'items' => \$items, // Array of invoice items (any number - table will auto-adjust)
    'summary' => [
        'round_off' => '₹ ' . number_format(\$invoice['round_off'], 2),
        'total' => '₹ ' . number_format(\$invoice['total'], 2),
        'received_amount' => '₹ ' . number_format(\$invoice['received'], 2),
        'amount_in_words' => convertNumberToWords(\$invoice['total'])
    ],
    'bank_details' => [
        'title' => 'Bank Details',
        'name' => \$company['name'],
        'ifsc_code' => \$company['ifsc'],
        'account_no' => \$company['account_no'],
        'bank' => \$company['bank_name']
    ],
    'terms' => [
        'title' => 'Terms and Conditions',
        'conditions' => [
            'Payment should be made in favor of ' . \$company['name'],
            'For TDS- PAN NO. ' . \$client['pan']
        ]
    ],
    'signature' => [
        'title' => 'Authorized Signatory',
        'company_line' => 'For ' . \$company['name'],
        'signature_image_path' => \$company['signature_path'] ?? ''
    ],
    'settings' => [
        'fixed_table_rows' => 20,  // ⭐ FIXED HEIGHT: Always 20 rows total
        'page_size' => 'A4',       // ⭐ TABLE BEHAVIOR:
        'currency_symbol' => '₹'   //   - 1 item = 1 filled + 19 empty rows
    ]                              //   - 5 items = 5 filled + 15 empty rows  
];                                 //   - 20 items = 20 filled + 0 empty rows
                                   //   - Result: ALWAYS same table height!

// 3. Render template with fixed table height
echo renderInvoiceTemplate(\$template_data);

// 4. PDF generation with consistent layout
// \$html = renderInvoiceTemplate(\$template_data);
// \$pdf = new TCPDF();
// \$pdf->writeHTML(\$html);
// \$pdf->Output('invoice_' . \$invoice_id . '.pdf', 'D');

// 5. Adjust table size if needed (in settings)
// 'fixed_table_rows' => 25,  // For longer invoices
// 'fixed_table_rows' => 15,  // For shorter invoices
";
echo "</pre>";
?>
