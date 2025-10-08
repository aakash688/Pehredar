<!DOCTYPE html>
  <html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Resume - <?= htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['surname'] ?? '')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    <!-- Fonts + Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>

    <style>
      :root{
        --primary-blue: #0b1220;
        --secondary-blue: #111827;
        --accent-cyan: #0ea5e9;
        --accent-emerald: #22c55e;
        --text-dark: #0b1220;
        --text-muted: #64748b;
        --paper-bg: #ffffff;
        --section-accent: #7c3aed;
      }

      *{ box-sizing:border-box; }
      body{ font-family:'Poppins',sans-serif; background:#0b1220; margin:0; color:var(--text-dark); }

      /* Controls (hidden on print) */
      .print-controls{ display:flex;justify-content:center;gap:10px;padding:16px;position:sticky;top:0;
        background:linear-gradient(135deg,rgba(15,23,42,0.85),rgba(51,65,85,0.85));backdrop-filter:blur(8px);z-index:50;}
      .btn{ background:#fff;color:#111827;border:none;padding:10px 16px;border-radius:10px;font-weight:600;cursor:pointer;
        display:inline-flex;align-items:center;gap:8px;box-shadow:0 6px 14px rgba(0,0,0,0.15);transition:transform .15s,box-shadow .15s;}
      .btn:hover{transform:translateY(-1px);box-shadow:0 10px 20px rgba(0,0,0,0.2);background:#f8fafc;}
      .btn.primary{background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));color:#fff;}
      .btn.primary:hover{background:linear-gradient(135deg,#04a4bf,#0da56f);}

      /* Page */
      .page{width:210mm;height:297mm;margin:12px auto;background:var(--paper-bg);border-radius:8px;overflow:hidden;
        box-shadow:0 12px 30px rgba(0,0,0,0.25);display:flex;flex-direction:column;}
      .page-inner{flex:1;display:grid;grid-template-columns:32% 68%;gap:10px;padding:8mm;position:relative;}

      /* Watermark */
      .watermark{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0.05;pointer-events:none;z-index:0;}
      .watermark img{max-width:60%;height:auto;filter:grayscale(100%);}

      /* Header (spans both columns) */
      .resume-header{grid-column:1/3;background:var(--primary-blue);color:#fff;
        border-bottom:3px solid var(--section-accent);border-radius:10px;padding:12px;display:flex;align-items:center;justify-content:space-between;z-index:1;box-shadow:0 6px 18px rgba(2,6,23,.25);gap:12px;}
      .header-left .name{font-size:24px;font-weight:800;margin-bottom:2px;letter-spacing:.2px;}
      .header-left .role{font-size:11px;text-transform:uppercase;letter-spacing:1px;display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.15);border:1px solid rgba(124,58,237,.35);color:#e9d5ff;padding:3px 8px;border-radius:999px;font-weight:700;}
      .avatar{width:100px;height:100px;border-radius:50%;overflow:hidden;border:3px solid var(--accent-cyan);
        box-shadow:0 0 10px rgba(6,182,212,0.4);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;background:#fff;color:var(--primary-blue);transition:transform 0.3s ease;flex-shrink:0;}
      .avatar:hover{transform:scale(1.05);}
      .avatar img{width:100%;height:100%;object-fit:cover;}

      /* Sections shared style */
      .section{margin-bottom:8px;padding:8px 10px;border-radius:8px;background:linear-gradient(135deg,#f9fafb,#ffffff);border-left:3px solid var(--section-accent);box-shadow:0 1px 4px rgba(0,0,0,0.05);transition:transform 0.2s ease;overflow:hidden;}
      .section:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.1);}
      .section-title{font-size:11px;font-weight:600;letter-spacing:.6px;margin-bottom:5px;color:var(--primary-blue);text-transform:uppercase;display:flex;align-items:center;gap:4px;padding:6px 8px;background:rgba(124,58,237,0.1);border-radius:6px;}
      .section-title i{color:var(--section-accent);font-size:10px;}
      .section p{font-size:10px;line-height:1.4;margin:0;color:#4b5563;}

      /* Tables */
      .kv-table{width:100%;border-collapse:collapse;margin-top:4px;}
      .kv-table td{padding:4px 2px;font-size:11px;vertical-align:top;}
      .kv-table tr:nth-child(even){background-color:rgba(248,250,252,0.5);}
      .kv-label{width:110px;font-weight:600;color:#334155;text-transform:capitalize;font-size:11px;}
      .kv-sep{width:8px;text-align:center;color:#64748b;font-weight:600;}
      .kv-value{font-family:'Roboto Mono',monospace;font-size:11px;}

      /* Skills */
      .skills-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
      .skill-chip{background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));color:#fff;font-size:11px;
        font-weight:600;padding:6px 12px;border-radius:18px;box-shadow:0 2px 6px rgba(0,0,0,0.1);transition:transform 0.2s ease;}
      .skill-chip:hover{transform:translateY(-1px);box-shadow:0 4px 8px rgba(0,0,0,0.15);}

      /* Family refs */
      .ref-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
      .ref-card{background:linear-gradient(135deg,#fff,#f8fafc);border:1px solid #e2e8f0;padding:10px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.05);transition:transform 0.2s ease;border-left:3px solid var(--accent-cyan);overflow:hidden;page-break-inside:avoid;}
      .ref-card:hover{transform:translateY(-1px);box-shadow:0 4px 8px rgba(0,0,0,0.1);}
      .ref-card h3{font-size:11px;margin:0 0 8px;color:var(--secondary-blue);display:flex;align-items:center;gap:6px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;}
      .ref-card h3::before{content:'';width:3px;height:14px;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));border-radius:2px;}
      .ref-table td{font-size:11px;padding:3px;}

      /* Footer */
      .resume-footer{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);padding:12px 20px;text-align:center;
        color:#475569;font-size:10px;border-top:2px solid var(--accent-cyan);font-weight:500;letter-spacing:0.5px;margin-top:auto;}
      .resume-footer span{margin:0 12px;position:relative;font-family:'Roboto Mono',monospace;}
      .resume-footer span:not(:last-child)::after{content:'•';position:absolute;right:-16px;color:var(--accent-cyan);font-weight:bold;}

      /* Additional enhancements */
      .page{position:relative;overflow:visible;}
      .page::before{content:none !important;display:none !important;}
      
      /* Enhanced hover effects */
      .kv-table tr:hover{background-color:rgba(6,182,212,0.05);transition:background-color 0.2s ease;}
      
      /* Professional summary enhancement */
      .section p{text-align:justify;hyphens:auto;}
      
      /* Contact info styling */
      .contact-highlight{color:var(--accent-cyan);font-weight:600;}
      
      /* Print adjustments */
      @page{size:A4;margin:0;}
      @media print{
        body{background:white!important;}
        .print-controls{display:none!important;}
        .page{margin:0;box-shadow:none;border-radius:0;}
        .page::before{display:none;}
        .resume-footer{position:static;}
        .section:hover{transform:none;box-shadow:0 2px 8px rgba(0,0,0,0.05);} 
        .ref-card:hover{transform:none;box-shadow:0 2px 4px rgba(0,0,0,0.05);} 
        .skill-chip:hover{transform:none;box-shadow:0 2px 4px rgba(0,0,0,0.1);} 
        .avatar:hover{transform:none;}
        /* Lock header size to UI */
        .resume-header{padding:12px !important;background:var(--primary-blue) !important;border-radius:10px !important;box-shadow:none !important;}
        .avatar{width:100px !important;height:100px !important;border-width:3px !important;font-size:28px !important;}
        /* Compact layout for print without reducing font sizes */
        .page-inner{padding:5mm !important;gap:6px !important;}
        .resume-header{padding:8px 10px !important;}
        .section{margin-bottom:6px !important;padding:6px 8px !important;}
        .kv-table td{padding:2px 2px !important;}
        .skills-list{gap:5px !important;}
        .skill-chip{padding:3px 8px !important;}
        .ref-grid{grid-template-columns:1fr 1fr !important;gap:6px !important;}
        .ref-card{padding:8px !important;}
        .resume-footer{padding:6px 10px !important;}
        }
    /* Timeline */
    .timeline{border-left:2px solid var(--accent-cyan);margin-top:6px;padding-left:12px;}
    .timeline-entry{position:relative;margin-bottom:12px;}
    .timeline-entry::before{content:'';position:absolute;left:-9px;top:3px;width:10px;height:10px;background:var(--accent-cyan);border-radius:50%;}
    .timeline-date{font-size:10px;font-weight:600;color:var(--text-muted);} 
    .timeline-content{font-size:11px;line-height:1.4;}

    /* Digital-only animation */
    @media screen{
      .section,.resume-header,.ref-card,.skill-chip{animation:fadeIn .6s ease-in-out;}
      @keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
    }

    /* Responsive viewing */
    @media screen and (max-width:768px){
      .page-inner{display:block;padding:10px;}
      .resume-header{flex-direction:column;text-align:center;gap:8px;}
      .ref-grid{grid-template-columns:1fr !important;}
        }
    </style>
</head>
<body>
  <?php 
    $show_professional_summary = true; 
    $show_footer = true; 
  ?>
    <div class="print-controls">
      <button class="btn primary" id="btn-print"><i class="fas fa-print"></i> Print / Save</button>
      <button class="btn" id="btn-download"><i class="fas fa-file-pdf"></i> Download PDF</button>
            </div>

    <div class="page" id="resume-root">
      <?php if (!empty($company_logo_src)): ?>
        <div class="watermark"><img src="<?= htmlspecialchars($company_logo_src) ?>" alt="Company Watermark"></div>
        <?php endif; ?>
        
      <div class="page-inner">
        <!-- Header -->
        <div class="resume-header">
          <div class="header-left">
            <div class="name"><?= htmlspecialchars(trim(($employee['first_name'] ?? '').' '.($employee['surname'] ?? ''))) ?></div>
            <div class="role"><?= htmlspecialchars($employee['user_type'] ?? 'Employee') ?></div>
          </div>
          <div class="avatar">
            <?php if (!empty($employee['profile_photo'])): ?>
              <img src="<?= htmlspecialchars($employee['profile_photo']) ?>" alt="Profile Photo">
            <?php else: ?>
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));color:#fff;font-size:24px;">
                <?= strtoupper(substr($employee['first_name'] ?? 'E',0,1).substr($employee['surname'] ?? 'M',0,1)) ?>
              </div>
            <?php endif; ?>
          </div>
              </div>

        <!-- LEFT COLUMN -->
        <div>
          <?php if($show_professional_summary): ?>
          <div class="section">
            <div class="section-title"><i class="fas fa-user-tie"></i> Professional Summary</div>
            <p>Dedicated security professional with comprehensive experience in asset protection, surveillance operations, and emergency response protocols. Proven expertise in maintaining security systems, conducting thorough inspections, and implementing preventive measures. Known for exceptional reliability, attention to detail, and commitment to maintaining safe environments. Strong communication skills and ability to work effectively in high-pressure situations.</p>
          </div>

          <div class="section">
            <div class="section-title"><i class="fas fa-star"></i> Key Skills</div>
            <div class="skills-list">
              <div class="skill-chip">Surveillance</div>
              <div class="skill-chip">Emergency Response</div>
              <div class="skill-chip">Access Control</div>
              <div class="skill-chip">Conflict Resolution</div>
              <div class="skill-chip">Risk Assessment</div>
              <div class="skill-chip">Security Protocols</div>
              <div class="skill-chip">Patrol Operations</div>
              <div class="skill-chip">Incident Reporting</div>
              <div class="skill-chip">Customer Service</div>
              <div class="skill-chip">Team Leadership</div>
              <?php if (!empty($employee['security_training_certified'])): ?>
                <div class="skill-chip" style="background:#0ea5e9;"><i class="fas fa-check-circle"></i> Trained Security Personnel</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Employment moved to left column -->
          <div class="section">
            <div class="section-title"><i class="fas fa-briefcase"></i> Employment Details</div>
            <table class="kv-table">
              <tr><td class="kv-label">Profile</td><td class="kv-sep">:</td><td><?= htmlspecialchars($employee['user_type'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">Employee ID</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['id'] ?? '-') ?></td></tr>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN -->
        <div>
        <div class="section">
            <div class="section-title"><i class="fas fa-user-circle"></i> Personal Info</div>
            <table class="kv-table">
              <tr><td class="kv-label">Full Name</td><td class="kv-sep">:</td><td><?= htmlspecialchars(trim(($employee['first_name'] ?? '') . ' ' . ($employee['surname'] ?? ''))) ?></td></tr>
              <tr><td class="kv-label">DOB</td><td class="kv-sep">:</td><td><?= htmlspecialchars(!empty($employee['date_of_birth']) ? date('F j, Y', strtotime($employee['date_of_birth'])) : 'Not specified') ?></td></tr>
              <tr><td class="kv-label">Gender</td><td class="kv-sep">:</td><td><?= htmlspecialchars($employee['gender'] ?? 'Not specified') ?></td></tr>
              <tr><td class="kv-label">Qualification</td><td class="kv-sep">:</td><td><?= htmlspecialchars($employee['highest_qualification'] ?? 'Not specified') ?></td></tr>
              <tr><td class="kv-label">Mobile</td><td class="kv-sep">:</td><td class="contact-highlight"><?= htmlspecialchars($employee['mobile_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">Email</td><td class="kv-sep">:</td><td class="contact-highlight"><?= htmlspecialchars($employee['email_id'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">Address</td><td class="kv-sep">:</td><td><?= htmlspecialchars($employee['address'] ?? '-') ?></td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title"><i class="fas fa-file-alt"></i> Documents</div>
            <table class="kv-table">
              <tr><td class="kv-label">Aadhar</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['aadhar_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">PAN</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['pan_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">Passport</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['passport_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">Voter ID</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['voter_id_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">PF No</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['pf_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">ESIC No</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['esic_number'] ?? '-') ?></td></tr>
              <tr><td class="kv-label">UAN</td><td class="kv-sep">:</td><td class="kv-value"><?= htmlspecialchars($employee['uan_number'] ?? '-') ?></td></tr>
            </table>
        </div>
        
          <?php if (!empty($family_references)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-users"></i> Family References</div>
            <div class="ref-grid">
              <?php foreach ($family_references as $index=>$reference): ?>
              <div class="ref-card">
                <h3>Reference <?= (int)$index+1 ?></h3>
                <table class="ref-table">
                  <tr><td>Name</td><td>: <?= htmlspecialchars($reference['name'] ?? '-') ?></td></tr>
                  <tr><td>Relation</td><td>: <?= htmlspecialchars($reference['relation'] ?? '-') ?></td></tr>
                  <tr><td>Primary Mobile</td><td>: <?= htmlspecialchars($reference['mobile_primary'] ?? '-') ?></td></tr>
                  <?php if(!empty($reference['mobile_secondary'])): ?>
                    <tr><td>Alternate</td><td>: <?= htmlspecialchars($reference['mobile_secondary']) ?></td></tr>
                  <?php endif; ?>
            </table>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- Declaration Section -->
          <div class="section">
            <div class="section-title"><i class="fas fa-file-signature"></i> Declaration</div>
            <p style="font-style:italic;text-align:justify;margin-bottom:8px;">
              I hereby declare that the information provided in this resume is true, complete, and accurate to the best of my knowledge. I understand that any false information may result in termination of employment or other disciplinary action.
            </p>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
              <div style="text-align:left;">
                <div style="height:28px;"></div>
                <div style="border-bottom:1px solid #334155;width:160px;margin-bottom:6px;"></div>
                <span style="font-size:10px;color:#64748b;">Signature</span>
              </div>
              <div style="text-align:right;">
                <div style="font-size:10px;color:#64748b;">Date: <?= date('F j, Y') ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php if (!empty($employee['linkedin_profile'])): ?>
        <div class="section">
          <div class="section-title"><i class="fas fa-qrcode"></i> Digital Profile</div>
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($employee['linkedin_profile']) ?>" alt="LinkedIn QR Code" style="width:100px;height:100px;">
        </div>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <?php 
      if($show_footer){
        $cs = is_array($company_settings ?? null) ? $company_settings : [];
        $footerEmail = $cs['company_email'] ?? $cs['email'] ?? $cs['company_email_id'] ?? $cs['contact_email'] ?? '';
        $footerPhone = $cs['company_contact'] ?? $cs['phone'] ?? $cs['contact_number'] ?? $cs['mobile'] ?? $cs['company_phone'] ?? $cs['company_contact_no'] ?? $cs['phone_number'] ?? $cs['company_contact_number'] ?? '';
        $footerEmail = $footerEmail ?: 'info@company.com';
        if ($footerPhone) {
          $footerPhone = preg_replace('/[^0-9+]/','', (string)$footerPhone);
          if (strlen($footerPhone) > 0 && $footerPhone[0] !== '+') { $footerPhone = '+91 ' . $footerPhone; }
        } else {
          $footerPhone = '+00 123 456 7890';
        }
      ?>
      <div class="resume-footer">
        <span><?= htmlspecialchars($cs['company_name'] ?? 'Company Name') ?></span>
        <span><?= htmlspecialchars($footerEmail) ?></span>
        <span><?= htmlspecialchars($footerPhone) ?></span>
        <span><?= 'Generated on '.date('F j, Y') ?></span>
      </div>
      <?php } ?>
    </div>

    <!-- PDF & Print -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
      function buildPdfFileName(){
        const name = (document.querySelector('.name')?.textContent || 'User').trim();
        const company = "<?= htmlspecialchars($company_settings['company_name'] ?? 'Company') ?>";
        return `${company} - Resume - ${name}.pdf`.replace(/[\/\\?%*:|"<>]/g,'');
      }
      async function downloadPDF(e){e?.preventDefault();await html2pdf().set({filename:buildPdfFileName()})
        .from(document.getElementById('resume-root')).save();}
      function printResume(e){e?.preventDefault();window.print();}
      document.getElementById('btn-print').addEventListener('click',printResume);
      document.getElementById('btn-download').addEventListener('click',downloadPDF);
    </script>
</body>
</html> 