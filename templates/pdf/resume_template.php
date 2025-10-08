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
        /* Theme Colors - Customizable */
        --theme-primary: #2f3848; /* Primary background color */
        --theme-secondary: #0f0f10; /* Secondary background color */
        --theme-accent: #d4af37; /* Accent color (gold) */
        --theme-accent-dark: #b0892f; /* Dark accent color */
        --theme-text-primary: #f3f4f6; /* Primary text color */
        --theme-text-secondary: #bfbfbf; /* Secondary text color */
        --theme-card-bg: #0f0f10; /* Card background color */
        --theme-border: #d4af37; /* Border color */
        --theme-hover: #1a1a1a; /* Hover state color */
        --theme-shadow: rgba(0, 0, 0, 0.35); /* Shadow color */
        
        /* Legacy variables for backward compatibility */
        --primary-blue: var(--theme-primary);
        --secondary-blue: var(--theme-secondary);
        --accent-cyan: var(--theme-accent);
        --accent-emerald: var(--theme-accent-dark);
        --text-dark: var(--theme-text-primary);
        --text-muted: var(--theme-text-secondary);
        --paper-bg: var(--theme-card-bg);
        --section-accent: var(--theme-border);
      }

      *{ box-sizing:border-box; }
      body{ font-family:'Poppins',sans-serif; background:var(--theme-primary); margin:0; color:var(--theme-text-primary); }

      /* Controls (hidden on print) */
      .print-controls{ display:flex;justify-content:center;gap:10px;padding:16px;position:sticky;top:0;
        background:transparent;z-index:50;}
      .btn{ background:#fff;color:#111827;border:none;padding:10px 16px;border-radius:10px;font-weight:600;cursor:pointer;
        display:inline-flex;align-items:center;gap:8px;box-shadow:0 6px 14px rgba(0,0,0,0.15);transition:transform .15s,box-shadow .15s;}
      .btn:hover{transform:translateY(-1px);box-shadow:0 10px 20px rgba(0,0,0,0.2);background:#f8fafc;}
      .btn.primary{background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));color:#fff;}
      .btn.primary:hover{background:linear-gradient(135deg,#04a4bf,#0da56f);}

      /* Page */
      .page{width:210mm;height:297mm;margin:12px auto;background:var(--theme-secondary);border-radius:12px;overflow:hidden;
        box-shadow:0 18px 50px var(--theme-shadow);display:flex;flex-direction:column;border:1px solid rgba(148,163,184,.15);}
      .page-inner{flex:1;display:grid;grid-template-columns:32% 68%;gap:10px;padding:8mm;position:relative;}

      /* Watermark */
      .watermark{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0.05;pointer-events:none;z-index:0;}
      .watermark img{max-width:60%;height:auto;filter:grayscale(100%);}

      /* Updated Header (no boundary) */
      .resume-header{
        grid-column:1/3;
        padding:16px 0;
        display:flex;
        align-items:center;
        justify-content:space-between;
        z-index:1;
        gap:16px;
        margin-bottom:12px;
        border-bottom:3px solid var(--accent-cyan);
        padding-bottom:20px;
      }
      
      .header-left{
        flex:1;
      }
      
      .header-left .name{
        font-size:32px;
        font-weight:700;
        margin-bottom:6px;
        color:#fff;
        letter-spacing:-0.5px;
        line-height:1.1;
      }
      
      .header-left .role{
        font-size:14px;
        text-transform:uppercase;
        letter-spacing:2px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        color:var(--accent-cyan);
        font-weight:600;
        opacity:0.95;
      }
      
      .avatar{
        width:120px;
        height:120px;
        border-radius:50%;
        overflow:hidden;
        border:4px solid var(--accent-cyan);
        box-shadow:0 0 30px rgba(212,175,55,.35);
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        font-size:36px;
        background:#0a0a0a;
        color:#f3f4f6;
        transition:transform 0.3s ease;
        flex-shrink:0;
      }
      
      .avatar:hover{transform:scale(1.05);}
      .avatar img{width:100%;height:100%;object-fit:cover;}

      /* Sections */
      .section{margin-bottom:8px;padding:8px 10px;border-radius:12px;background:var(--theme-card-bg);border-left:3px solid var(--theme-border);box-shadow:0 1px 4px var(--theme-shadow);}
      .section:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(0,0,0,0.4);}
      .section-title{font-size:11px;font-weight:600;letter-spacing:.6px;margin-bottom:6px;color:var(--theme-text-primary);text-transform:uppercase;display:flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(212,175,55,0.08);border-radius:999px;}
      .section-title i{color:var(--theme-border);font-size:10px;}
      .section p{font-size:10px;line-height:1.4;margin:0;color:var(--theme-text-secondary);}

      /* Tables */
      .kv-table{width:100%;border-collapse:collapse;margin-top:4px;}
      .kv-table td{padding:4px 2px;font-size:11px;vertical-align:top;color:var(--theme-text-primary);}
      .kv-table tr:nth-child(even){background-color:var(--theme-hover);}
      .kv-label{width:110px;font-weight:700;color:var(--theme-text-secondary);text-transform:capitalize;font-size:11px;}
      .kv-sep{width:8px;text-align:center;color:var(--theme-text-secondary);font-weight:600;}
      .kv-value{font-family:'Roboto Mono',monospace;font-size:11px;color:var(--theme-text-primary);}

      /* Skills */
      .skills-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
      .skill-chip{background:linear-gradient(135deg,var(--theme-accent),var(--theme-accent-dark));color:#000000;font-size:11px;
        font-weight:700;padding:6px 12px;border-radius:18px;box-shadow:0 2px 8px var(--theme-shadow);}
      .skill-chip:hover{transform:translateY(-1px);box-shadow:0 4px 8px rgba(0,0,0,0.15);}

      /* Family refs */
      .ref-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
      .ref-card{background:var(--theme-card-bg);border:1px solid rgba(215,187,80,.2);padding:10px;border-radius:12px;box-shadow:0 1px 8px var(--theme-shadow);transition:transform 0.2s ease;border-left:3px solid var(--theme-accent);overflow:hidden;page-break-inside:avoid;}
      .ref-card:hover{transform:translateY(-1px);box-shadow:0 4px 8px rgba(0,0,0,0.1);}
      .ref-card h3{font-size:10px;margin:0 0 6px;color:var(--theme-text-primary);display:flex;align-items:center;gap:6px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;}
      .ref-card h3::before{content:'';width:3px;height:14px;background:linear-gradient(135deg,var(--theme-accent),var(--theme-accent-dark));border-radius:2px;}
      .ref-table td{font-size:10px;padding:2px;color:var(--theme-text-primary);}

      /* Footer */
      .resume-footer{background:#0a0a0a;padding:12px 20px;text-align:center;
        color:#d4d4d8;font-size:10px;border-top:1px solid rgba(215,187,80,.25);font-weight:500;letter-spacing:0.5px;margin-top:auto;}
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

      /* Print adjustments - matching UI exactly */
      @page{size:A4;margin:0;}
      @media print{
        body{background:#0a0a0a !important;color:var(--text-dark) !important;}
        .print-controls{display:none!important;}
        .page{
          margin:0;
          box-shadow:none;
          border-radius:0;
          background:var(--theme-secondary) !important;
          border:none;
        }
        .page::before{display:none;}
        
        /* Maintain exact header styling in print */
        .resume-header{
          padding:16px 0 !important;
          margin-bottom:12px !important;
          border-bottom:3px solid var(--accent-cyan) !important;
          padding-bottom:20px !important;
        }
        
        .header-left .name{
          font-size:32px !important;
          color:#fff !important;
        }
        
        .header-left .role{
          font-size:14px !important;
          color:var(--accent-cyan) !important;
        }
        
        .avatar{
          width:120px !important;
          height:120px !important;
          border:4px solid var(--accent-cyan) !important;
        }
        
        /* Maintain all colors and styles */
        .section{
          background:#0f0f10 !important;
          border-left:3px solid var(--section-accent) !important;
        }
        
        .section-title{
          color:#f3f4f6 !important;
          background:rgba(212,175,55,0.08) !important;
        }
        
        .kv-table td{color:#e2e8f0 !important;}
        .kv-label{color:#d1d5db !important;}
        .kv-value{color:#e2e8f0 !important;}
        .contact-highlight{color:var(--accent-cyan) !important;}
        
          .skill-chip{
            background:linear-gradient(135deg,var(--theme-accent),var(--theme-accent-dark)) !important;
            color:#000000 !important;
          }
        
        .ref-card{
          background:#0f0f10 !important;
          border:1px solid rgba(215,187,80,.2) !important;
          border-left:3px solid var(--accent-cyan) !important;
        }
        
        .resume-footer{
          background:#0a0a0a !important;
          color:#d4d4d8 !important;
          border-top:1px solid rgba(215,187,80,.25) !important;
          position:static;
        }
        
        /* Remove hover effects for print */
        .section:hover{transform:none;box-shadow:0 1px 4px rgba(0,0,0,0.35);}
        .ref-card:hover{transform:none;box-shadow:0 1px 8px rgba(0,0,0,0.35);}
        .skill-chip:hover{transform:none;box-shadow:0 2px 8px rgba(0,0,0,0.35);}
        .avatar:hover{transform:none;}
        
        /* Maintain layout */
        .page-inner{padding:7mm !important;gap:8px !important;}
        .section{margin-bottom:6px !important;}
        .kv-table td{padding:3px 2px !important;}
        .skills-list{gap:6px !important;}
        .skill-chip{padding:5px 10px !important;}
        .ref-grid{grid-template-columns:1fr 1fr !important;gap:8px !important;}
        .ref-card{padding:8px !important;}
        .resume-footer{padding:8px 14px !important;font-size:9px !important;}
        /* Slight overall shrink to keep footer on first page */
        #resume-root{transform:scale(0.985) !important;transform-origin:top center !important;width:210mm;}
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
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-emerald));color:#fff;font-size:36px;">
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
              <tr><td class="kv-label">Joining Date</td><td class="kv-sep">:</td><td><?= htmlspecialchars(!empty($employee['date_of_joining']) ? date('F j, Y', strtotime($employee['date_of_joining'])) : '-') ?></td></tr>
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
                <h3><?= htmlspecialchars($reference['relation'] ?? 'Reference') ?></h3>
                <table class="ref-table">
                  <tr><td>Name</td><td>: <?= htmlspecialchars($reference['name'] ?? '-') ?></td></tr>
                  <!-- <tr><td>Relation</td><td>: <?= htmlspecialchars($reference['relation'] ?? '-') ?></td></tr> -->
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
      function printResume(e){e?.preventDefault();window.print();}
      document.getElementById('btn-print').addEventListener('click',printResume);
    </script>
</body>
</html> 