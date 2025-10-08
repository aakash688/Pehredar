<?php 
// Load JSON data
$json_file = __DIR__ . '/invoice_template_data.json';
$invoice_data = json_decode(file_get_contents($json_file), true);

/**
 * Helper function to calculate how many empty rows are required
 * at the bottom of the items table so that the table height
 * + footer always looks consistent.
 *
 * Rules:
 * - If last page and line items <= 16 → pad normally (till $maxRowsNormal).
 * - If last page and 17–20 line items → limit table to 20 rows total max.
 * - If not last page or more than 20 items → normal padding (till $maxRowsNormal).
 */
function calculateEmptyRows(int $count, bool $isLastPage, int $maxRowsNormal = 28): int {
    if ($isLastPage) {
        if ($count <= 15) {
            // Normal padding → extends table fully to page
            return max(0, $maxRowsNormal - $count);
        } elseif ($count >= 16 && $count <= 20) {
            // Special case → only pad till 20 rows
            return max(0, 20 - $count);
        } else {
            // More than 20 items handled by pagination anyway
            return max(0, $maxRowsNormal - $count);
        }
    } else {
        // Middle pages always padded fully
        return max(0, $maxRowsNormal - $count);
    }
}

function renderInvoiceTemplate($data=null, $is_pdf=false, $autoPdf=false){
    if(!$data){
        $json_file = __DIR__ . '/invoice_template_data.json';
        $data = json_decode(file_get_contents($json_file), true);
    }
    $h = fn($s)=>htmlspecialchars($s ?? '');

    // ========================================
    // Pagination Logic
    // ========================================
    $total = count($data['items']);
    $maxRowsNormal = 28; // rows per page
    $maxFooterSafe = 20; // max rows allowed with footer
    $pages = ceil($total / $maxRowsNormal);
    $itemsOnLastPage = $total - ($maxRowsNormal * ($pages - 1));
    $footerOnlyPage = false;
    if ($itemsOnLastPage > $maxFooterSafe) {
        $pages++;
        $footerOnlyPage = true;
    }

    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}

/* Force A4 canvas for screen + print */
body{
  font:12px <?= $is_pdf?"'DejaVu Sans'":"Arial, sans-serif" ?>;
  background:<?= $is_pdf?"#fff":"#ccc" ?>;
  margin:0;
  padding:20px 0;
  display:flex;
  flex-direction:column;
  align-items:center;
}

.invoice-container{
  width:210mm;
  height:297mm;
  background:#fff;
  border:2px solid #000;
  padding:10mm;
  display:flex;
  flex-direction:column;
  position:relative;
  margin-bottom:20px;
  page-break-after:always;
  overflow:hidden;
  box-shadow:<?= $is_pdf?"none":"0 4px 8px rgba(0,0,0,.2)" ?>;
}
.invoice-container:last-child{page-break-after:auto}

/* Watermark */
.watermark{position:absolute;opacity:.3;left:25.4mm;bottom:63.5mm;width:159.2mm;height:150mm;z-index:1}
.watermark img{width:100%;height:100%;object-fit:contain}

/* Header */
.top-banner{display:flex;justify-content:space-between;margin-bottom:6px}
.bill-of-supply{font:bold 16px Arial;color:#333}
.original-box{border:1px solid #333;padding:3px 8px;font:bold 10px Arial}
.secuitas-tagline{font:bold 11px Arial;color:#333}

/* Company */
.company-section{border:2px solid #000}
.company-content{display:flex;padding:8px}
.logo-area{flex:0 0 80px;text-align:center}
.logo-area img{width:80px;height:80px;object-fit:contain}
.company-info-area{flex:1;text-align:center}
.company-name{font:bold 18px Arial;text-transform:uppercase;margin:6px 0;color:#333}
.company-info{font:11px Arial;color:#555}
.contact-line{display:flex;justify-content:center;gap:20px;font-size:10px;margin-top:4px}

/* Bill To */
.bill-to-section{border:2px solid #000;border-top:none;display:flex}
.billto-left{flex:55%;padding:8px}
.billto-right{flex:45%;border-left:2px solid #000;display:flex;align-items:center;justify-content:center}
.billto-meta-table{width:100%;font-size:11px;text-align:center;border-collapse:collapse}
.billto-meta-table th{font-weight:bold}

/* Main */
.main-content{flex:1;display:flex;flex-direction:column;}
.content-block{flex:1;display:flex;flex-direction:column;justify-content:flex-start;min-height:140mm;}
.main-table{width:100%;border-collapse:collapse;border:2px solid #000}
.main-table th{border:1px solid #333;padding:6px;font:bold 11px Arial;background:#f8f8f8;text-align:center}
.main-table td{border-left:1px solid #333;border-right:1px solid #333;font:9px Arial;text-align:left;padding:4px;height:18px}
.amount-cell{text-align:right;font-weight:bold}
.service-name{font-weight:bold}
.service-description{font-style:italic;font-size:8px;color:#666}

/* Summary */
.summary-table{width:100%;border-collapse:collapse;border:2px solid #000;border-top:none}
.summary-table td{padding:6px;font-size:11px;border-left:1px solid #333;border-right:1px solid #333}
.summary-amount{text-align:right;font-weight:bold}
.amount-words-section{border:2px solid #000;padding:6px;margin:8px 0;font-size:10px;background:#f9f9f9}

/* Footer */
.footer-section{display:table;width:100%;border-collapse:collapse;border:2px solid #000;border-top:none;margin-top:auto}
.footer-left,.footer-center,.footer-right{display:table-cell;vertical-align:top;padding:8px;font-size:9px;border:1px solid #000}
.footer-left{width:40%}
.footer-center{width:35%}
.footer-right{width:25%;text-align:center}
.footer-title{font:bold 10px Arial;margin-bottom:4px}
.signature-line{border-bottom:1px solid #000;width:100px;margin:0 auto 6px;height:25px}
.signature-text{font:bold 9px Arial;color:#333}

/* Page Numbers INSIDE A4 Canvas */
.page-number{
  position:absolute;
  bottom:5mm;
  right:10mm;
  font:italic 10px Arial;
  color:#555;
}

/* Print */
@media print{
  body{background:#fff !important;padding:0 !important;margin:0 !important}
  .invoice-container{
    width:210mm !important;
    height:297mm !important;
    margin:0 auto !important;
    box-shadow:none !important;
    border:none !important;
    page-break-after:always;
    position:relative;
  }
  .invoice-container:last-child, .invoice-container.last-page{page-break-after:auto !important;margin-bottom:0 !important}
  button[onclick*="print"], button[onclick*="downloadPDF"], .print-button, .pdf-button, .no-print { display: none !important; }
  @page{
    size:A4;
    margin:0;
  }
}
</style>
</head>
<body>

<?php for($p=1;$p<=$pages;$p++):
  $isLastPage = ($p == $pages);
  $footerOnlyPage = ($isLastPage && $itemsOnLastPage > $maxFooterSafe && $total % $maxRowsNormal != 0);
  if(!$footerOnlyPage){
    $offset = ($p-1) * $maxRowsNormal;
    $slice = array_slice($data['items'], $offset, $maxRowsNormal);

    // ✅ Use improved helper function
    $empty = calculateEmptyRows(count($slice), $isLastPage, $maxRowsNormal);

  } else {
    $slice = [];
    $empty = 0;
  } ?>
<div class="invoice-container<?= $isLastPage ? ' last-page' : '' ?>">
  <?php if($data['watermark']['image_path']): ?>
    <div class="watermark"><img src="<?= $h($data['watermark']['image_path']) ?>"></div>
  <?php endif; ?>

  <!-- Header -->
  <div class="top-banner">
    <div>
      <span class="bill-of-supply"><?= $h($data['header']['bill_of_supply']) ?></span>
      <span class="original-box"><?= $h($data['header']['original_text']) ?></span>
    </div>
    <div class="secuitas-tagline"><?= $h($data['header']['tagline']) ?></div>
  </div>

  <!-- Company -->
  <div class="company-section">
    <div class="company-content">
      <div class="logo-area"><?php if($data['company']['logo_path']): ?><img src="<?= $h($data['company']['logo_path']) ?>"><?php endif;?></div>
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
        <tr><th>Invoice No.</th><th>Invoice Date</th><th>Due Date</th></tr>
        <tr>
          <td><?= $h($data['invoice_meta']['invoice_no']) ?></td>
          <td><?= $h($data['invoice_meta']['invoice_date']) ?></td>
          <td><?= $h($data['invoice_meta']['due_date']) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Items Table -->
  <div class="main-content">
    <div class="content-block">
    <?php if(!$footerOnlyPage): ?>
      <table class="main-table">
        <thead><tr><th>S.NO</th><th>Items/Services</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach($slice as $it): ?>
          <tr>
            <td><?= $h($it['sno']) ?></td>
            <td><div class="service-name"><?= $h($it['service_name']) ?></div><div class="service-description"><?= $h($it['service_description']) ?></div></td>
            <td><?= $h($it['quantity']) ?></td>
            <td><?= $h($it['rate']) ?></td>
            <td class="amount-cell"><?= $h($it['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?= str_repeat("<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>",$empty) ?>
        </tbody>
      </table>
    <?php endif; ?>

      <?php if($isLastPage): ?>
        <table class="summary-table">
         <tr><td colspan="4" style="text-align:right">Subtotal</td><td class="summary-amount"><?= $h($data['summary']['subtotal']) ?></td></tr>
         <?php if ($data['summary']['gst_enabled']): ?>
         <tr>
           <td colspan="4" style="text-align:right">GST (<?= $h($data['summary']['gst_percent']) ?>%)</td>
           <td class="summary-amount"><?= $h($data['summary']['gst_amount']) ?></td>
         </tr>
         <?php endif; ?>
         <tr><td colspan="4" style="text-align:right">Round Off</td><td class="summary-amount"><?= $h($data['summary']['round_off']) ?></td></tr>
         <tr><td colspan="4" style="text-align:right"><b>TOTAL</b></td><td class="summary-amount"><?= $h($data['summary']['total']) ?></td></tr>
        </table>
        <div class="amount-words-section"><b>Total in words:</b> <?= $h($data['summary']['amount_in_words']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <?php if($isLastPage): ?>
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
      <?php foreach($data['terms']['conditions'] as $i=>$c) echo ($i+1).". ".$h($c)."<br>"; ?>
    </div>
    <div class="footer-right">
      <div class="signature-area">
        <?php if($data['signature']['signature_image_path']): ?>
          <img src="<?= $h($data['signature']['signature_image_path']) ?>" style="max-width:150px;max-height:50px">
        <?php else: ?><div class="signature-line"></div><?php endif; ?>
        <div class="signature-text"><?= $h($data['signature']['title']) ?></div>
        <div class="signature-text"><?= $h($data['signature']['company_line']) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page Number ALWAYS inside page -->
  <?php if($pages>1): ?>
    <div class="page-number">Page <?= $p ?> of <?= $pages ?></div>
  <?php endif; ?>
</div><!-- invoice-container -->
<?php endfor; ?>

<?php if(!$is_pdf): ?>
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
function printInvoice(){
  // Ensure last page doesn't have page break
  const lastContainer = document.querySelector('.invoice-container:last-child');
  if (lastContainer) {
    lastContainer.style.pageBreakAfter = 'avoid';
    lastContainer.style.marginBottom = '0';
  }
  window.print();
}
function downloadPDF(){
  console.log('downloadPDF: Function called');
  
  // Select all invoice containers
  const elements = document.querySelectorAll('.invoice-container');
  console.log('downloadPDF: Found', elements.length, 'invoice containers');
  
  if (!elements.length) {
    console.error('downloadPDF: No invoice content found!');
    alert("No invoice content found!");
    return;
  }

  // Show loading indicator (only if called from button click)
  let button = null;
  let originalText = '';
  if (event && event.target) {
    button = event.target;
    originalText = button.innerHTML;
    button.innerHTML = '⏳ Generating PDF...';
    button.disabled = true;
  }

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
    console.log('downloadPDF: PDF saved successfully');
    
    // Restore button state (only if button exists)
    if (button) {
      button.innerHTML = originalText;
      button.disabled = false;
    }
  }).catch((error) => {
    console.error('PDF generation failed:', error);
    alert('PDF generation failed. Please try again.');
    // Restore button state (only if button exists)
    if (button) {
      button.innerHTML = originalText;
      button.disabled = false;
    }
  });
}
document.addEventListener('keydown',e=>{
  if(e.ctrlKey){
    if(e.key==='p'){e.preventDefault();printInvoice();}
    if(e.key==='s'){e.preventDefault();downloadPDF();}
  }
});

// Handle print events to prevent extra pages
window.addEventListener('beforeprint', function() {
  const lastContainer = document.querySelector('.invoice-container:last-child');
  if (lastContainer) {
    lastContainer.style.pageBreakAfter = 'avoid';
    lastContainer.style.marginBottom = '0';
  }
});

<?php if ($autoPdf): ?>
// Auto-generate PDF when page loads
window.addEventListener('load', function() {
  console.log('Auto-PDF: Page loaded, checking html2pdf availability...');
  
  // Check if html2pdf is available
  if (typeof html2pdf === 'undefined') {
    console.error('Auto-PDF: html2pdf library not loaded!');
    alert('PDF generation library not loaded. Please refresh the page and try again.');
    return;
  }
  
  console.log('Auto-PDF: html2pdf library found, starting PDF generation...');
  
  // Small delay to ensure all content is rendered
  setTimeout(function() {
    console.log('Auto-PDF: Triggering downloadPDF()...');
    try {
      downloadPDF();
    } catch (error) {
      console.error('Auto-PDF: Error during PDF generation:', error);
      alert('PDF generation failed: ' + error.message);
    }
  }, 1000); // Increased delay to 1 second
});
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
<?php return ob_get_clean(); }

function renderInvoiceTemplateForPDF($data=null){ return renderInvoiceTemplate($data,true); }

if(basename(__FILE__)==basename($_SERVER['PHP_SELF'])){
  echo renderInvoiceTemplate($invoice_data, ($_GET['pdf_mode']??'')==='1');
}
?>