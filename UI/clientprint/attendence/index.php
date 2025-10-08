<?php
// Fetch company settings from database
require_once '../../../helpers/database.php';
require_once '../../../config.php';

$companyName = 'Security Guard Services'; // Default fallback
$companyLogo = '';
$companyTagline = 'Professional Security Services';

try {
    $conn = get_db_connection();
    
    // Get config
    $config = require '../../../config.php';
    $baseUrl = $config['base_url'];
    
    // Fetch company settings using the correct schema
    $stmt = $conn->prepare("SELECT company_name, logo_path FROM company_settings LIMIT 1");
    $stmt->execute();
    $companySettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($companySettings) {
        $companyName = $companySettings['company_name'] ?: 'Security Guard Services';
        $logoPath = $companySettings['logo_path'] ?: '';
        
        // Construct full logo URL using base_url from config
        if ($logoPath) {
            // Remove any leading slashes and construct full URL
            $logoPath = ltrim($logoPath, '/');
            $companyLogo = $baseUrl . '/' . $logoPath;
        }
        
        // Note: tagline column doesn't exist in the schema, so we'll use a default
    }
} catch (Exception $e) {
    // Use default values if database connection fails
    error_log("Failed to fetch company settings: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo htmlspecialchars($companyName); ?> - QR Code</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
      --primary-color: #1e88e5;
      --secondary-color: #43a047;
      --accent-color: #90caf9;
      --dark-bg: #0f0f0f;
      --card-bg: #1a1a1a;
      --text-color: #f1f1f1;
      --text-muted: #cccccc;
      --border-radius: 14px;
      --box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--dark-bg);
      color: var(--text-color);
      margin: 0;
      padding: 30px 15px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .container {
      width: 100%;
      max-width: 850px;
      background-color: var(--card-bg);
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #1e88e5, #0d47a1);
      padding: 35px 20px;
      text-align: center;
      color: white;
      position: relative;
    }

    .header i {
      font-size: 40px;
      margin-bottom: 10px;
    }

    .company-logo {
      max-width: 120px;
      max-height: 80px;
      margin: 0 auto 15px;
      display: block;
    }

    .company-name {
      font-size: 26px;
      font-weight: 700;
      margin: 10px 0 5px;
      letter-spacing: 0.5px;
    }

    .company-details {
      font-size: 14px;
      opacity: 0.9;
    }

    .content {
      padding: 35px 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .society-name {
      font-size: 30px;
      font-weight: 600;
      margin-bottom: 25px;
      color: var(--primary-color);
      text-align: center;
      text-transform: uppercase;
    }

    .qr-container {
      background-color: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
      margin-bottom: 35px;
    }

    #qrcode {
      width: 250px;
      height: 250px;
    }

    .info-container {
      width: 100%;
      max-width: 520px;
    }

    .info-item {
      display: flex;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .info-icon {
      font-size: 20px;
      margin-right: 15px;
      color: var(--accent-color);
      margin-top: 3px;
    }

    .info-label {
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 16px;
      color: var(--text-color);
      word-break: break-word;
    }

    .buttons {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 40px;
    }

    .btn {
      background-color: var(--primary-color);
      color: white;
      padding: 12px 25px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn:hover {
      background-color: #1565c0;
      box-shadow: 0 4px 14px rgba(30, 136, 229, 0.4);
      transform: translateY(-2px);
    }

    .btn-secondary {
      background-color: var(--secondary-color);
    }

    .footer {
      text-align: center;
      font-size: 12px;
      padding: 20px;
      color: var(--text-muted);
    }

    @media print {
      .buttons {
        display: none;
      }
    }
  </style>
</head>
<body>
  <div class="container" id="qr-card">
    <div class="header">
      <?php if ($companyLogo): ?>
        <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="company-logo" />
      <?php else: ?>
        <i class="fas fa-shield-alt"></i>
      <?php endif; ?>
      <div class="company-name" id="company-name"><?php echo htmlspecialchars($companyName); ?></div>
      <div class="company-details" id="company-details"><?php echo htmlspecialchars($companyTagline); ?></div>
    </div>

    <div class="content">
      <div class="society-name" id="society-name">Society Name</div>

      <div class="qr-container">
        <div id="qrcode"></div>
      </div>

      <div class="info-container">
        <div class="info-item">
          <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
          <div>
            <div class="info-label">Address</div>
            <div class="info-value" id="society-address">Loading...</div>
          </div>
        </div>

        <div class="info-item" id="location-info" style="display: none;">
          <div class="info-icon"><i class="fas fa-globe"></i></div>
          <div>
            <div class="info-label">Coordinates</div>
            <div class="info-value" id="society-coordinates">Loading...</div>
          </div>
        </div>
      </div>

      <div class="buttons">
        <button class="btn" id="print-btn"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-secondary" id="download-btn"><i class="fas fa-download"></i> Download</button>
      </div>
    </div>

    <div class="footer">
      © <span id="current-year"></span> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.
    </div>
  </div>

  <!-- Libraries -->
  <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.getElementById('current-year').textContent = new Date().getFullYear();

      try {
        const rawStoredData = localStorage.getItem('societyQRData');
        const data = JSON.parse(rawStoredData || '{}');

        document.getElementById('society-name').textContent = data.name || 'Society Name';
        const fullAddress = localStorage.getItem('societyFullAddress') || 'Address not available';
        document.getElementById('society-address').textContent = fullAddress;

        if (data.latitude && data.longitude) {
          document.getElementById('society-coordinates').textContent = `${data.latitude}, ${data.longitude}`;
          document.getElementById('location-info').style.display = 'flex';
        }

        // Update company info from database (overrides localStorage if available)
        const companyName = '<?php echo addslashes($companyName); ?>';
        const companyTagline = '<?php echo addslashes($companyTagline); ?>';
        
        if (companyName && companyName !== 'Security Guard Services') {
          document.getElementById('company-name').textContent = companyName;
        } else if (data.company && data.company.name) {
          document.getElementById('company-name').textContent = data.company.name;
        }
        
        if (companyTagline && companyTagline !== 'Professional Security Services') {
          document.getElementById('company-details').textContent = companyTagline;
        } else if (data.company && data.company.tagline) {
          document.getElementById('company-details').textContent = data.company.tagline;
        }

        if (data.name) {
          const qrData = {
            name: data.name,
            id: data.id || '',
            qrCodeId: data.qrCodeId || ''
          };

          if (data.latitude && data.longitude) {
            qrData.location = {
              lat: parseFloat(data.latitude),
              lng: parseFloat(data.longitude)
            };
          }

          new QRCode(document.getElementById('qrcode'), {
            text: JSON.stringify(qrData),
            width: 250,
            height: 250,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
          });
        }
      } catch (error) {
        console.error('Error loading data:', error);
        alert('Failed to load society data. Please refresh the page or try again.');
      }

      document.getElementById('print-btn').addEventListener('click', () => window.print());

      document.getElementById('download-btn').addEventListener('click', () => {
        const container = document.querySelector('.container');

        html2canvas(container, {
          scale: 2,
          useCORS: true,
          windowWidth: container.scrollWidth,
          windowHeight: container.scrollHeight
        }).then(canvas => {
          const imgData = canvas.toDataURL('image/png');
          const pdf = new jspdf.jsPDF('p', 'mm', 'a4');

          const pageWidth = pdf.internal.pageSize.getWidth();
          const imgProps = canvas.width / canvas.height;
          const imgHeight = pageWidth / imgProps;

          pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, imgHeight);
          const societyName = document.getElementById('society-name').textContent.replace(/\s+/g, '_');
          pdf.save(`${societyName}_QR_Code.pdf`);
        }).catch(error => {
          console.error('PDF Download Error:', error);
          alert('Failed to download PDF. Try again.');
        });
      });
    });
  </script>
</body>
</html>
