<?php
// Standalone layout for ID card and resume views - no sidebar, no navigation
global $page, $company_settings, $page_data; // Use the global variables from index.php

// Determine page title and template
$pageTitle = 'Document';
$templatePath = '';

if ($page === 'id-card-view') {
    $pageTitle = 'ID Card - ' . ($company_settings['company_name'] ?? 'GuardSys');
    $templatePath = '../templates/pdf/rpf_id_card_templete.php';
} elseif ($page === 'resume-view') {
    $pageTitle = 'Resume - ' . ($company_settings['company_name'] ?? 'GuardSys');
    $templatePath = '../templates/pdf/resume_template.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <?php if (!empty($company_settings['favicon_path'])): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($company_settings['favicon_path']); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
        body { 
            margin: 0; 
            padding: 0; 
            background: #f8fafc;
            min-height: 100vh;
        }
        
        /* Hide all navigation and sidebar elements */
        .sidebar, .top-nav, .main-header, .footer, .debug-badge {
            display: none !important;
        }
        
        /* Full-width content area */
        .standalone-content {
            width: 100%;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        /* Print styles */
        @media print {
            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }
            
            .standalone-content {
                padding: 0;
                margin: 0;
            }
            
            .print-controls {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="standalone-content">
        <?php
        // Include the appropriate template
        if (isset($page_data['employee']) && !empty($templatePath)) {
            $employee = $page_data['employee'];
            $company_settings = $page_data['company_settings'];
            $config = $page_data['config'];
            
            // For ID card view, add additional variables if needed
            if ($page === 'id-card-view') {
                $expiry_date = $page_data['expiry_date'] ?? null;
                $qr_code_url = $page_data['qr_code_url'] ?? null;
                $vcard_data = $page_data['vcard_data'] ?? null;
            }
            
            // For resume view, add additional variables if needed
            if ($page === 'resume-view') {
                $expiry_date = $page_data['expiry_date'] ?? null;
                $qr_code_url = $page_data['qr_code_url'] ?? null;
                $vcard_data = $page_data['vcard_data'] ?? null;
                $family_references = $page_data['family_references'] ?? [];
            }
            
            require __DIR__ . '/' . $templatePath;
        } else {
            echo '<div class="flex items-center justify-center min-h-screen">';
            echo '<div class="text-center">';
            echo '<h1 class="text-2xl font-bold text-gray-800 mb-4">Document Not Found</h1>';
            echo '<p class="text-gray-600">The requested document could not be found.</p>';
            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
