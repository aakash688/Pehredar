<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Employee ID Card - Two Sided</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <!-- Use FA v5 CSS to match 'fas' classes -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>

  <style>
    :root {
      --primary-blue: #0f172a;
      --secondary-blue: #1e293b;
      --accent-cyan: #06b6d4;
      --accent-emerald: #10b981;
      --text-white: #ffffff;
      --text-light: rgba(255, 255, 255, 0.8);
      --text-muted: rgba(255, 255, 255, 0.6);
      --glass-bg: rgba(255, 255, 255, 0.1);
      --glass-border: rgba(255, 255, 255, 0.2);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #0f172a, #334155);
      min-height: 100vh;
      padding: 20px;
    }

    .controls {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
      z-index: 1000;
      gap: 10px;
    }

    .print-btn {
      background: white;
      border: none;
      padding: 12px 20px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      display: flex;
      align-items: center;
      gap: 8px;
      transition: transform 0.2s;
    }
    .print-btn:hover { transform: scale(1.03); }

     .cards-container {
       display: flex;
       flex-direction: column;
       align-items: center;
       gap: 30px;
       max-width: 1200px;
       margin: 0 auto;
       overflow: hidden;
       position: relative;
     }

    .card-wrapper { position: relative; }

    .card {
      width: 4.25in;
      height: 2.75in;
      background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
      overflow: hidden;
      border: 1px solid var(--glass-border);
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .card-label {
      position: absolute;
      top: -25px;
      left: 0;
      color: var(--text-white);
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .security-stripe {
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent-cyan), var(--accent-emerald));
    }

    /* FRONT SIDE STYLES */
    .card-header {
      display: flex;
      align-items: center;
      padding: 8px 20px 6px;
      border-bottom: 1px solid var(--glass-border);
      background: var(--glass-bg);
      backdrop-filter: blur(12px);
      gap: 10px;
    }

    .company-logo {
      height: 30px;
      width: 30px;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-shrink: 0;
      border-radius: 6px;
      overflow: hidden;
    }

    .company-logo img {
      max-height: 100%;
      max-width: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      display: block;
    }

    .company-name {
      color: var(--text-white);
      font-size: 13px;
      font-weight: 700;
      line-height: 1.2;
      letter-spacing: 0.5px;
      white-space: nowrap;
      flex-grow: 1;
    }

    .card-body {
      flex: 1;
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 15px;
      padding: 15px 20px;
      align-items: center;
    }

    .employee-avatar {
      width: 90px;
      height: 120px;
      border-radius: 14px;
      overflow: hidden;
      border: 2px solid var(--glass-border);
      background: var(--glass-bg);
    }
    .employee-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .employee-info { color: var(--text-white); }

    .employee-name { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
    .employee-role { font-size: 11px; text-transform: uppercase; color: var(--accent-cyan); margin-bottom: 10px; }

    .employee-details { font-size: 10px; display: flex; flex-direction: column; gap: 6px; }
    .detail-item { display: flex; align-items: center; gap: 8px; }
    .detail-icon {
      font-size: 9px; color: white; background: var(--accent-emerald);
      border-radius: 50%; width: 16px; height: 16px; display: flex; justify-content: center; align-items: center;
    }

    .qr-section { display: flex; flex-direction: column; align-items: center; gap: 5px; }
    .qr-container {
      width: 70px; height: 70px; border-radius: 12px; background: #fff; padding: 4px; border: 2px solid var(--glass-border);
    }
    .qr-container img { width: 100%; height: 100%; object-fit: contain; }
    .qr-label {
      font-size: 8px; color: var(--text-light); text-transform: uppercase; display: flex; align-items: center; gap: 4px; font-weight: 600;
    }

    .card-footer {
      display: flex; justify-content: space-between; align-items: center;
      padding: 8px 20px; background: rgba(0, 0, 0, 0.15);
      font-size: 8px; font-weight: 500; color: var(--text-light);
      border-top: 1px solid var(--glass-border);
    }
    .footer-center { color: var(--accent-emerald); font-weight: 600; }

    /* BACK SIDE STYLES */
    .back-header {
      display: flex; justify-content: center; align-items: center;
      padding: 12px 20px 10px; border-bottom: 1px solid var(--glass-border);
      background: var(--glass-bg); backdrop-filter: blur(12px);
    }
    .back-title { color: var(--text-white); font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }

    .back-body {
      flex: 1;
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 1px;
      padding: 15px 20px 8px;
    }

    .terms-section { display: flex; flex-direction: column; gap: 8px; }
    .terms-title {
      color: var(--text-white); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
      margin-bottom: 5px; border-bottom: 1px solid var(--glass-border); padding-bottom: 3px;
    }
    .terms-content { color: var(--text-light); font-size: 8px; line-height: 1.4; }
    .terms-content ol { margin: 0; padding-left: 12px; list-style-type: decimal; list-style-position: outside; }
    .terms-content li { margin-bottom: 4px; text-align: justify; padding-left: 4px; }

    .back-qr-section {
      display: flex; flex-direction: column; align-items: center;
      align-self: stretch; height: 100%; gap: 8px;
    }

    .company-qr-container {
      width: 80px; height: 80px; border-radius: 12px; background: #fff; padding: 4px; border: 2px solid var(--glass-border);
    }
    .company-qr-container img { width: 100%; height: 100%; object-fit: contain; }
    .company-qr-label { font-size: 7px; color: var(--text-light); text-transform: uppercase; text-align: center; font-weight: 600; line-height: 1.2; }

     /* Signature image and label (image sits above label/line area) */
     .signature-image {
       width: 100px !important; height: 40px !important; 
       object-fit: contain !important; object-position: center center;
       display: block !important; margin: 0 auto 2px auto;
       max-width: 100px !important; max-height: 40px !important;
       min-width: 100px !important; min-height: 40px !important;
       flex-shrink: 0 !important;
     }

    .signature-section {
      margin-top: -6px; margin-bottom: -8px; padding-top: 0;
      border-top: 1px solid var(--glass-border);
      text-align: center; width: 100%;
    }
    .signature-title { color: var(--text-white); font-size: 8px; font-weight: 600; margin-top: 0; text-transform: uppercase; text-align: center; }

    .back-footer {
      display: flex; justify-content: center; align-items: center;
      padding: 8px 20px; background: rgba(0, 0, 0, 0.15);
      font-size: 7px; font-weight: 500; color: var(--text-light); border-top: 1px solid var(--glass-border);
      text-align: center;
    }

    /* Responsive */
    @media (max-width: 768px) {
      body { padding: 10px; }
      .cards-container { gap: 20px; }
      .card { width: 95vw; max-width: 4.25in; height: auto; aspect-ratio: 4.25 / 2.75; }
    }

     /* Print Styles */
     @media print {
       @page { size: A4 portrait; margin: 0.5in; }
       body { background: white !important; padding: 0; margin: 0; }
       .controls { display: none !important; }
       .cards-container {
         display: flex; flex-direction: column; justify-content: center; align-items: center;
         gap: 0.75in; min-height: 100vh; margin: 0; padding: 0.5in 0;
         overflow: visible !important;
       }
       .card-wrapper { margin: 0; padding: 0; }
       .card {
         width: 4.25in !important; height: 2.75in !important;
         box-shadow: none !important; border: 2px solid #000 !important; page-break-inside: avoid; margin: 0;
       }
       .card-label, .security-stripe { display: none !important; }
       * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
       .signature-image { 
         -webkit-print-color-adjust: exact !important; 
         print-color-adjust: exact !important;
         width: 100px !important; 
         height: 40px !important;
         max-width: 100px !important;
         max-height: 40px !important;
         object-fit: contain !important;
         display: block !important;
         margin: 0 auto 2px auto !important;
         object-position: center center !important;
         flex-shrink: 0 !important;
       }
       .signature-section {
         margin-top: -6px !important;
         margin-bottom: -8px !important;
         padding-top: 0 !important;
         border-top: 1px solid var(--glass-border) !important;
         text-align: center !important;
         width: 100% !important;
       }
       .signature-title {
         color: var(--text-white) !important;
         font-size: 8px !important;
         font-weight: 600 !important;
         margin-top: 0 !important;
         text-transform: uppercase !important;
         text-align: center !important;
       }
       .card-wrapper:first-child { page-break-before: avoid; }
       .card-wrapper:last-child { page-break-after: avoid; }
     }
   </style>
 </head>
<body>
  <div class="controls">
    <button class="print-btn" id="btn-print" type="button">
      <i class="fas fa-print"></i> Print/Save ID Card
    </button>
    <!-- <button class="print-btn" id="btn-download" type="button">
      <i class="fas fa-download"></i> Download PDF
    </button> -->
  </div>

  <div class="cards-container" id="cards-root">
    <!-- FRONT SIDE -->
    <div class="card-wrapper">
      <div class="card">
        <div class="security-stripe"></div>

        <!-- HEADER -->
        <div class="card-header">
          <div class="company-logo">
            <?php if (isset($company_settings['logo_path']) && !empty($company_settings['logo_path'])): ?>
              <img src="<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo" onload="adjustLogoContainer(this)" crossorigin="anonymous" referrerpolicy="no-referrer">
            <?php else: ?>
              <div style="width: 40px; height: 40px; background: #fff; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #333; font-weight: bold; font-size: 12px;">
                RPF
              </div>
            <?php endif; ?>
          </div>
          <div class="company-name">
            <?= htmlspecialchars($company_settings['company_name'] ?? 'RYAN PROTECTION FORCE') ?>
          </div>
        </div>

        <!-- BODY -->
        <div class="card-body">
          <div class="employee-avatar">
            <?php if (isset($employee['profile_photo']) && !empty($employee['profile_photo'])): ?>
              <img src="<?= htmlspecialchars($employee['profile_photo']) ?>" alt="Employee Photo" crossorigin="anonymous" referrerpolicy="no-referrer">
            <?php else: ?>
              <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">
                <?= strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['surname'] ?? 'M', 0, 1)) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="employee-info">
            <div class="employee-name"><?= htmlspecialchars(trim(($employee['first_name'] ?? '').' '.($employee['surname'] ?? ''))) ?></div>
            <div class="employee-role"><?= htmlspecialchars($employee['user_type'] ?? 'Employee') ?></div>
            <div class="employee-details">
              <div class="detail-item">
                <div class="detail-icon"><i class="fas fa-id-badge"></i></div>
                <div><strong>ID:</strong> <?= htmlspecialchars($employee['employee_code'] ?? $employee['id'] ?? '') ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-icon"><i class="fas fa-calendar-plus"></i></div>
                <div><strong>Joined:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($employee['date_of_joining'] ?? date('Y-m-d')))) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-icon"><i class="fas fa-calendar-times"></i></div>
                <div><strong>Expires:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($expiry_date ?? date('Y-m-d', strtotime('+3 years'))))) ?></div>
              </div>
            </div>
          </div>

          <div class="qr-section">
            <div class="qr-container">
              <?php if (isset($qr_code_url) && !empty($qr_code_url)): ?>
                <img src="<?= htmlspecialchars($qr_code_url) ?>" alt="Employee QR Code" crossorigin="anonymous" referrerpolicy="no-referrer">
              <?php else: ?>
                <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666; font-size: 8px; text-align: center;">
                  QR Code<br>Not Available
                </div>
              <?php endif; ?>
            </div>
            <div class="qr-label">
              <i class="fas fa-qrcode"></i>
              Scan to Verify
            </div>
          </div>
        </div>

        <!-- FOOTER -->
        <div class="card-footer">
          <div>AUTHORIZED PERSONNEL</div>
          <div class="footer-center">VALID: <?= htmlspecialchars(date('Y', strtotime($employee['date_of_joining'] ?? date('Y-m-d')))) ?>–<?= htmlspecialchars(date('Y', strtotime($expiry_date ?? date('Y-m-d', strtotime('+3 years'))))) ?></div>
          <div><?= htmlspecialchars($company_settings['website'] ?? 'www.ryan.com') ?></div>
        </div>
      </div>
    </div>

    <!-- BACK SIDE -->
    <div class="card-wrapper">
      <div class="card">
        <div class="security-stripe"></div>

        <!-- BACK HEADER -->
        <div class="back-header">
          <div class="back-title">Terms & Conditions</div>
        </div>

        <!-- BACK BODY -->
        <div class="back-body">
          <div class="terms-section">
            <div class="terms-title">Important Guidelines</div>
            <div class="terms-content">
              <ol>
                <li>ID cards are issued based on executive criteria defined by the RPF.</li>
                <li>Applicants must provide accurate and current information during the application process.</li>
                <li>ID cards are non-transferable and remain the property of the RPF.</li>
                <li>Usage is restricted to the individual to whom they are issued.</li>
                <li>Report lost, stolen, or damaged ID cards promptly to the helpline. +91-9702295293 or scan the QR code above for more information.</li>
              </ol>
            </div>
          </div>

          <div class="back-qr-section">
            <div class="company-qr-container">
              <img src="https://api.qrserver.com/v1/create-qr-code/?data=BEGIN%3AVCARD%0AVERSION%3A3.0%0AN%3AMishara%3BS.R.%0AFN%3AS.R.%20Mishara%0AORG%3ARPF%20security%0ATEL%3BTYPE%3DWORK%2CVOICE%3A%2B91-9702295293%0AEMAIL%3Asmishra%40rayanprotection.co.in%0AEND%3AVCARD&size=300x300" alt="Company Contact QR" crossorigin="anonymous" referrerpolicy="no-referrer">
            </div>
            <div class="company-qr-label">
              Company<br>Contact Info
            </div>

            <?php if (isset($company_settings['signature_image']) && !empty($company_settings['signature_image'])): ?>
              <img
                class="signature-image"
                src="<?= htmlspecialchars($company_settings['signature_image']) ?>"
                alt="Authorized Signature"
                crossorigin="anonymous" referrerpolicy="no-referrer"
              />
            <?php else: ?>
              <div class="signature-image" style="background: #fff; display: flex; align-items: center; justify-content: center; color: #666; font-size: 8px; text-align: center;">
                Signature<br>Not Available
              </div>
            <?php endif; ?>

            <!-- SIGNATURE SECTION - label just above footer -->
            <div class="signature-section">
              <div class="signature-title">Authorized Signature</div>
            </div>
          </div>
        </div>

        <!-- BACK FOOTER -->
        <div class="back-footer">
          Property of <?= htmlspecialchars($company_settings['company_name'] ?? 'YANTRALOGIC CREAZION PVT LTD') ?> | If found, please return immediately
        </div>
      </div>
    </div>
  </div>

  <!-- html2pdf.js bundle (html2canvas + jsPDF) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

  <script>
    function adjustLogoContainer(img) {
      const fixedHeight = 40;
      const naturalHeight = img.naturalHeight || img.height;
      const naturalWidth = img.naturalWidth || img.width;
      if (!naturalHeight || !naturalWidth) return;
      if (naturalHeight > fixedHeight) {
        const scale = fixedHeight / naturalHeight;
        img.style.height = fixedHeight + 'px';
        img.style.width = (naturalWidth * scale) + 'px';
      } else {
        img.style.height = naturalHeight + 'px';
        img.style.width = naturalWidth + 'px';
      }
    }

    function printCard(e) {
      e?.preventDefault();
      window.print();
    }

    // Build the PDF filename: "{Company Name}, ID Card - {User Name}.pdf"
    function buildPdfFileName() {
      const companyName = (document.querySelector('.company-name')?.textContent || 'Company').trim();
      const userName = (document.querySelector('.employee-name')?.textContent || 'User').trim();
      const sanitize = (str) => str.replace(/[\/\\?%*:|"<>]/g, '').replace(/\s+/g, ' ').trim();
      return `${sanitize(companyName)}, ID Card - ${sanitize(userName)}.pdf`;
    }

    // Ensure all images are loaded and CORS-ready before rendering
    function preloadImages(rootEl) {
      const imgs = Array.from(rootEl.querySelectorAll('img'));
      imgs.forEach(img => {
        try { img.setAttribute('crossorigin', 'anonymous'); } catch(e){}
      });
      return Promise.all(imgs.map(img => new Promise(resolve => {
        if (img.complete && img.naturalWidth) return resolve();
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
      })));
    }

     async function downloadPDF(e) {
       e?.preventDefault();
       const element = document.getElementById('cards-root');

       // Preload images to avoid blank areas
       await preloadImages(element);

       const options = {
         margin: 0.5, // inches, to mirror @page margin in print
         filename: buildPdfFileName(),
         image: { type: 'jpeg', quality: 0.98 },
         html2canvas: {
           scale: 2,         // improve quality
           useCORS: true,
           allowTaint: true,
           backgroundColor: '#ffffff',
           logging: false,
           removeContainer: true,
           foreignObjectRendering: false,
           ignoreElements: (element) => {
             return element.classList.contains('controls');
           }
         },
         jsPDF: {
           unit: 'in',
           format: 'a4',
           orientation: 'portrait'
         },
         pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
       };

       try {
         await html2pdf().set(options).from(element).save();
       } catch (err) {
         console.error('PDF generation error:', err);
         alert('Unable to generate PDF. If images are hosted on a different domain without CORS, please host them locally or enable CORS.');
       }
     }

    // Event listeners
    document.getElementById('btn-print').addEventListener('click', printCard);
    document.getElementById('btn-download').addEventListener('click', downloadPDF);
  </script>
</body>
</html>