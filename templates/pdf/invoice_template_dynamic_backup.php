<?php
// Load JSON data
$json_file = __DIR__ . '/invoice_template_data.json';
$invoice_data = json_decode(file_get_contents($json_file), true);

function renderInvoiceTemplate($data = null, $is_pdf = false)
{
    if (!$data) {
        $json_file = __DIR__ . '/invoice_template_data.json';
        $data = json_decode(file_get_contents($json_file), true);
    }
    $h = fn($s) => htmlspecialchars($s ?? '');

    // ========================================
    // Pagination Logic
    // ========================================
    $allItems = $data['items'];
    $total = count($allItems);

    $maxIntermediate = 28; // rows for intermediate pages
    $maxLastPage = 20;     // rows to enforce on last page

    $pages = [];

    if ($total <= $maxLastPage) {
        // single-page => always pad to 20 rows
        $pages[] = array_pad($allItems, $maxLastPage, []);
    } else {
        // multi-page invoices
        while ($total > $maxLastPage) {
            $pages[] = array_slice($allItems, 0, $maxIntermediate);
            $allItems = array_slice($allItems, $maxIntermediate);
            $total = count($allItems);
        }
        // last page padded to 20
        $pages[] = array_pad($allItems, $maxLastPage, []);
    }

    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Invoice</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box }
            body {
                font: 12px <?= $is_pdf ? "'DejaVu Sans'" : "Arial, sans-serif" ?>;
                background: <?= $is_pdf ? "#fff" : "#f5f5f5" ?>;
                padding: <?= $is_pdf ? "0" : "20px" ?>;
            }
            .invoice-container {
                width: 210mm;
                height: 297mm;
                margin: 0 auto 20px;
                background: #fff;
                border: 2px solid #000;
                padding: 10mm;
                display: flex;
                flex-direction: column;
                position: relative;
                page-break-after: always;
                box-shadow: <?= $is_pdf ? "none" : "0 4px 8px rgba(0,0,0,.1)" ?>;
            }
            /* ✅ Prevent extra blank page */
            .invoice-container:last-of-type {
                page-break-after: auto !important;
                margin-bottom: 0;
            }
            .watermark {
                position: absolute; opacity: .3;
                left: 25.4mm; bottom: 63.5mm;
                width: 159.2mm; height: 150mm; z-index: 1;
            }
            .watermark img { width: 100%; height: 100%; object-fit: contain }
            .top-banner { display: flex; justify-content: space-between; margin-bottom: 6px }
            .bill-of-supply { font: bold 16px Arial; color: #333 }
            .original-box { border: 1px solid #333; padding: 3px 8px; font: bold 10px Arial }
            .secuitas-tagline { font: bold 11px Arial; color: #333 }
            .company-section { border: 2px solid #000 }
            .company-content { display: flex; padding: 8px }
            .logo-area { flex: 0 0 80px; text-align: center }
            .logo-area img { width: 80px; height: 80px; object-fit: contain }
            .company-info-area { flex: 1; text-align: center }
            .company-name { font: bold 18px Arial; text-transform: uppercase; margin: 6px 0; color: #333 }
            .company-info { font: 11px Arial; color: #555 }
            .contact-line { display: flex; justify-content: center; gap: 20px; font-size: 10px; margin-top: 4px }
            .bill-to-section { border: 2px solid #000; border-top: none; display: flex }
            .billto-left { flex: 55%; padding: 8px }
            .billto-right { flex: 45%; border-left: 2px solid #000; display: flex; align-items: center; justify-content: center }
            .billto-meta-table { width: 100%; font-size: 11px; text-align: center; border-collapse: collapse }
            .billto-meta-table th { font-weight: bold }
            .main-content { flex: 1; display: flex; flex-direction: column }
            .content-block { flex: 1; display: flex; flex-direction: column; min-height: 140mm; }
            .main-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-top: 5mm }
            .main-table th { border: 1px solid #333; padding: 6px; font: bold 11px Arial; background: #f8f8f8 }
            .main-table td { border-left: 1px solid #333; border-right: 1px solid #333; font: 9px Arial; text-align: center; padding: 4px; height: 18px }
            .description-cell { text-align: left }
            .amount-cell { text-align: right; font-weight: bold }
            .service-name { font-weight: bold }
            .service-description { font-style: italic; font-size: 8px; color: #666 }
            .summary-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-top: 5mm }
            .summary-table td { padding: 6px; font-size: 11px; border-left: 1px solid #333; border-right: 1px solid #333 }
            .summary-amount { text-align: right; font-weight: bold }
            .amount-words-section { border: 2px solid #000; padding: 6px; margin: 8px 0; font-size: 10px; background: #f9f9f9 }
            .footer-section { display: table; width: 100%; border-collapse: collapse; border: 2px solid #000; border-top: none; margin-top: auto }
            .footer-left, .footer-center, .footer-right { display: table-cell; vertical-align: top; padding: 8px; font-size: 9px; border: 1px solid #000 }
            .footer-left { width: 40% } .footer-center { width: 35% } .footer-right { width: 25%; text-align: center }
            .footer-title { font: bold 10px Arial; margin-bottom: 4px }
            .signature-line { border-bottom: 1px solid #000; width: 100px; margin: 0 auto 6px; height: 25px }
            .signature-text { font: bold 9px Arial; color: #333 }
            .page-number { position: absolute; bottom: 5mm; right: 10mm; font: italic 10px Arial; color: #555 }
            @media print {
                body { background: #fff; padding: 0 }
                .invoice-container { margin: 0; box-shadow: none }
                .invoice-container:last-of-type { page-break-after: auto !important; }
                .page-number { text-align: center; right: 0; left: 0 }
                .print-button, .pdf-button, button[onclick*="print"], button[onclick*="downloadPDF"], .no-print { display: none !important; }
                @page { size: A4; margin: 0 }
            }
        </style>
    </head>

    <body>

        <?php foreach ($pages as $pageIndex => $slice): 
            $isLastPage = ($pageIndex === count($pages) - 1); ?>
            <div class="invoice-container">
                <?php if ($data['watermark']['image_path']): ?>
                    <div class="watermark"><img src="<?= $h($data['watermark']['image_path']) ?>"></div>
                <?php endif; ?>

                <!-- Header Banner -->
                <div class="top-banner">
                    <div><span class="bill-of-supply"><?= $h($data['header']['bill_of_supply']) ?></span>
                        <span class="original-box"><?= $h($data['header']['original_text']) ?></span>
                    </div>
                    <div class="secuitas-tagline"><?= $h($data['header']['tagline']) ?></div>
                </div>

                <!-- Company -->
                <div class="company-section">
                    <div class="company-content">
                        <div class="logo-area"><?php if ($data['company']['logo_path']): ?><img src="<?= $h($data['company']['logo_path']) ?>"><?php endif; ?></div>
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

                <!-- Bill To -->
                <div class="bill-to-section">
                    <div class="billto-left">
                        <div class="section-title"><?= $h($data['client']['title']) ?></div>
                        <div class="client-name"><?= $h($data['client']['name']) ?></div>
                        <div class="client-details"><?= $h($data['client']['address']) ?><br>PAN: <?= $h($data['client']['pan']) ?></div>
                    </div>
                    <div class="billto-right">
                        <table class="billto-meta-table">
                            <tr>
                                <th>Invoice No.</th><th>Invoice Date</th><th>Due Date</th>
                            </tr>
                            <tr>
                                <td><?= $h($data['invoice_meta']['invoice_no']) ?></td>
                                <td><?= $h($data['invoice_meta']['invoice_date']) ?></td>
                                <td><?= $h($data['invoice_meta']['due_date']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="main-content">
                    <div class="content-block">
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
                                <?php foreach ($slice as $it): ?>
                                    <tr>
                                        <td><?= $h($it['sno'] ?? '') ?></td>
                                        <td class="description-cell">
                                            <div class="service-name"><?= $h($it['service_name'] ?? '') ?></div>
                                            <div class="service-description"><?= $h($it['service_description'] ?? '') ?></div>
                                        </td>
                                        <td><?= $h($it['quantity'] ?? '') ?></td>
                                        <td><?= $h($it['rate'] ?? '') ?></td>
                                        <td class="amount-cell"><?= $h($it['amount'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($isLastPage): ?>
                            <!-- Summary -->
                              <table class="summary-table">
                                  <tr>
                                     <td colspan="4" style="text-align:right">Subtotal</td>
                                     <td class="summary-amount"><?= $h($data['summary']['subtotal']) ?></td>
                                 </tr>
                                 <?php if ($data['summary']['gst_enabled']): ?>
                                 <tr>
                                     <td colspan="4" style="text-align:right">GST (<?= $h($data['summary']['gst_percent']) ?>%)</td>
                                     <td class="summary-amount"><?= $h($data['summary']['gst_amount']) ?></td>
                                 </tr>
                                 <?php endif; ?>
                                 <tr>
                                     <td colspan="4" style="text-align:right">Round Off</td>
                                     <td class="summary-amount"><?= $h($data['summary']['round_off']) ?></td>
                                 </tr>
                                 <tr>
                                     <td colspan="4" style="text-align:right"><b>TOTAL</b></td>
                                     <td class="summary-amount"><?= $h($data['summary']['total']) ?></td>
                                 </tr>
                              </table>

                            <div class="amount-words-section"><b>Total in words:</b> <?= $h($data['summary']['amount_in_words']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLastPage): ?>
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
                                <?php foreach ($data['terms']['conditions'] as $i => $c)
                                    echo ($i+1) . ". " . $h($c) . "<br>"; ?>
                            </div>
                            <div class="footer-right">
                                <div class="signature-area">
                                    <?php if ($data['signature']['signature_image_path']): ?>
                                        <img src="<?= $h($data['signature']['signature_image_path']) ?>" style="max-width:150px;max-height:50px">
                                    <?php else: ?>
                                        <div class="signature-line"></div>
                                    <?php endif; ?>
                                    <div class="signature-text"><?= $h($data['signature']['title']) ?></div>
                                    <div class="signature-text"><?= $h($data['signature']['company_line']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($pages) > 1): ?>
                    <div class="page-number">Page <?= ($pageIndex + 1) ?> of <?= count($pages) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!$is_pdf): ?>
<!-- Print Controls -->
<div style="position: fixed; top: 10px; right: 10px; z-index: 999; display: flex; gap: 10px;">
    <button onclick="printInvoice()" class="print-button" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
        🖨️ Print Invoice
    </button>
    <button onclick="downloadPDF()" class="pdf-button" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
        💾 Save as PDF
    </button>
</div>

<!-- html2pdf.js for pixel-perfect PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
            
            <script>
                function printInvoice() { window.print(); }
                function downloadPDF() {
                    // Select all invoice containers
                    const elements = document.querySelectorAll('.invoice-container');
                    if (!elements.length) {
                        alert("No invoice content found!");
                        return;
                    }

                    // Show loading indicator
                    const button = event.target;
                    const originalText = button.innerHTML;
                    button.innerHTML = '⏳ Generating PDF...';
                    button.disabled = true;

                    const opt = {
                        margin:       [0, 0, 0, 0],
                        filename:     'invoice.pdf',
                        image:        { type: 'jpeg', quality: 1 },
                        html2canvas:  { scale: 2, useCORS: true }, // scale=2 for sharpness
                        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                        pagebreak:    { mode: ['css', 'legacy'] }   // honor CSS page breaks
                    };

                    // Group all invoice-container elements into one wrapper
                    const wrapper = document.createElement('div');
                    elements.forEach(el => wrapper.appendChild(el.cloneNode(true)));

                    // Generate PDF with last page removal
                    html2pdf().from(wrapper).set(opt).toPdf().get('pdf').then(function (pdf) {
                        const totalPages = pdf.internal.getNumberOfPages();
                        
                        // Always remove the last page
                        if (totalPages > 1) {
                            const lastPage = totalPages;
                            pdf.deletePage(lastPage);
                            console.log('Removed last page');
                        }
                        
                        // Save the PDF
                        pdf.save('invoice.pdf');
                        
                        // Restore button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }).catch((error) => {
                        console.error('PDF generation failed:', error);
                        alert('PDF generation failed. Please try again.');
                        // Restore button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }
                document.addEventListener('keydown', e => {
                    if (e.ctrlKey) {
                        if (e.key === 'p') { e.preventDefault(); printInvoice(); }
                        if (e.key === 's') { e.preventDefault(); downloadPDF(); }
                    }
                });
            </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function renderInvoiceTemplateForPDF($data = null)
{
    return renderInvoiceTemplate($data, true);
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo renderInvoiceTemplate($invoice_data, ($_GET['pdf_mode'] ?? '') === '1');
}
?>