<?php
// actions/salary_slip_controller.php

// Performance optimization: Start output buffering and set time limit
ini_set('max_execution_time', 60);
ini_set('memory_limit', '256M');
ob_start();

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/SalarySlipPdfGenerator.php';

use Helpers\SalarySlipPdfGenerator;

try {
    $startTime = microtime(true);
    
    // Enhanced input validation
    $action = $_GET['action'] ?? '';
    $recordId = (int)($_GET['id'] ?? $_GET['record_id'] ?? 0);
    
    // Log the request for debugging
    error_log("PDF Generation Request - Action: {$action}, Record ID: {$recordId}");
    
    if ($action !== 'download' || !$recordId) {
        throw new Exception('Invalid parameters');
    }
    
    // Initialize database
    $db = new Database();
    
    // Get salary record with employee and company details, including advance deductions
    $query = "
        SELECT 
            sr.*,
            u.first_name,
            u.surname,
            u.user_type,
            u.esic_number,
            u.uan_number,
            u.pf_number,
            u.id as employee_id,
            COALESCE(apt.total_advance_deducted, 0) as advance_salary_deducted
        FROM salary_records sr
        LEFT JOIN users u ON sr.user_id = u.id
        LEFT JOIN (
            SELECT 
                salary_record_id, 
                SUM(amount) as total_advance_deducted
            FROM advance_payment_transactions 
            WHERE transaction_type = 'deduction' 
            GROUP BY salary_record_id
        ) apt ON apt.salary_record_id = sr.id
        WHERE sr.id = ?
    ";
    
    $salaryRecord = $db->query($query, [$recordId])->fetch();
    
    if (!$salaryRecord) {
        throw new Exception('Salary record not found');
    }
    
    // Get deduction details for this salary record
    $deductionQuery = "
        SELECT 
            sd.deduction_master_id,
            sd.deduction_amount,
            dm.deduction_name,
            dm.deduction_code
        FROM salary_deductions sd
        JOIN deduction_master dm ON sd.deduction_master_id = dm.id
        WHERE sd.salary_record_id = ?
    ";
    $deductions = $db->query($deductionQuery, [$recordId])->fetchAll();
    $salaryRecord['deductions_detail'] = $deductions;
    
    // Debug logging for advance deduction in salary slip
    error_log("Salary Slip Debug - Record ID: {$recordId}");
    error_log("Advance Salary Deducted: " . ($salaryRecord['advance_salary_deducted'] ?? 'NULL'));
    error_log("Calculated Salary: " . ($salaryRecord['calculated_salary'] ?? 'NULL'));
    error_log("Final Salary: " . ($salaryRecord['final_salary'] ?? 'NULL'));
    error_log("Deductions Detail: " . json_encode($deductions));
    
    // Get company settings using the proper function
    require_once __DIR__ . '/../actions/settings_controller.php';
    
    try {
        $pdo = $db->getPdo();
        $companySettings = get_company_settings($pdo);
        
        // If no company settings found, use defaults, but exclude logo and signature
        if (!$companySettings) {
            $companySettings = [
                'company_name' => 'Your Company Name',
                'street_address' => 'Company Address',
                'city' => 'City',
                'state' => 'State',
                'pincode' => '000000',
                'phone_number' => '0000000000',
                'email' => 'company@email.com',
                'gst_number' => 'GSTIN123456789',
                'logo_path' => null,  // Removed logo
                'signature_image' => null  // Removed signature
            ];
        }
    } catch (Exception $e) {
        // Fallback if there's any error
        error_log("Error fetching company settings: " . $e->getMessage());
        $companySettings = [
            'company_name' => 'Your Company Name',
            'street_address' => 'Company Address',
            'city' => 'City',
            'state' => 'State',
            'pincode' => '000000',
            'phone_number' => '0000000000',
            'email' => 'company@email.com',
            'gst_number' => 'GSTIN123456789',
            'logo_path' => null,  // Removed logo
            'signature_image' => null  // Removed signature
        ];
    }
    
    // Prepare employee data
    $employee = [
        'id' => $salaryRecord['employee_id'] ?? $salaryRecord['user_id'],
        'first_name' => $salaryRecord['first_name'],
        'surname' => $salaryRecord['surname'],
        'department' => $salaryRecord['user_type'] ?? 'N/A',
        'designation' => $salaryRecord['user_type'] ?? 'N/A',
        'esic_number' => $salaryRecord['esic_number'] ?? null,
        'uan_number' => $salaryRecord['uan_number'] ?? null,
        'pf_number' => $salaryRecord['pf_number'] ?? null
    ];
    
    // Attach statutory breakdown for display
    try {
        $salaryMonth = $salaryRecord['month'];
        $calcSalary = (float)($salaryRecord['calculated_salary'] ?? 0);
        $stat = $db->query("SELECT name, is_percentage, value, affects_net, scope FROM statutory_deductions WHERE is_active = 1 AND active_from_month <= ? ORDER BY id ASC", [$salaryMonth])->fetchAll();
        $breakdown = [];
        foreach ($stat as $s) {
            $amt = $s['is_percentage'] ? ($calcSalary * ((float)$s['value']/100.0)) : (float)$s['value'];
            $breakdown[] = [
                'name' => $s['name'],
                'amount' => round($amt, 2),
                'note' => ''
            ];
        }
        $salaryRecord['statutory_breakdown'] = $breakdown;
    } catch (Exception $e) {
        // Non-fatal: continue without breakdown
    }
    
    // Performance: Generate PDF with timing and validation
    $pdfStartTime = microtime(true);
    
    $pdfGenerator = new SalarySlipPdfGenerator($salaryRecord, $employee, $companySettings);
    
    // Generate PDF with error handling
    $pdfContent = $pdfGenerator->generate();
    $filename = $pdfGenerator->getFilename();
    
    $pdfTime = microtime(true) - $pdfStartTime;
    $totalTime = microtime(true) - $startTime;
    
    error_log("PDF generated successfully in: " . round($pdfTime, 3) . " seconds");
    error_log("Total processing time: " . round($totalTime, 3) . " seconds");
    
    // Validate PDF content
    if (!$pdfContent || strlen($pdfContent) < 1000) {
        throw new Exception('PDF generation failed - empty or invalid content');
    }
    
    // Clean any output buffer and ensure no output before headers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output PDF content
    echo $pdfContent;
    exit;
    
} catch (Exception $e) {
    // Enhanced error handling with detailed logging
    $errorTime = isset($startTime) ? microtime(true) - $startTime : 0;
    
    // Log comprehensive error details
    error_log('PDF Generation Error Details:');
    error_log('- Error: ' . $e->getMessage());
    error_log('- File: ' . $e->getFile());
    error_log('- Line: ' . $e->getLine());
    error_log('- Record ID: ' . $recordId);
    error_log('- Processing time before error: ' . round($errorTime, 3) . ' seconds');
    error_log('- Memory usage: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB');
    
    // Clean output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // User-friendly error page
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>PDF Generation Error</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .container { max-width: 600px; margin: 0 auto; }
            .error { background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; }
            .error-icon { font-size: 64px; margin-bottom: 20px; }
            .error h2 { color: #2d3748; margin: 0 0 20px 0; font-size: 28px; font-weight: 600; }
            .error p { margin: 15px 0; line-height: 1.6; color: #4a5568; }
            .error-details { background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; }
            .error-code { background: #fed7d7; color: #c53030; padding: 8px 12px; border-radius: 4px; font-family: "Monaco", "Menlo", monospace; font-size: 14px; display: inline-block; margin: 10px 0; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px; background: #4299e1; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; transition: all 0.2s; }
            .btn:hover { background: #3182ce; transform: translateY(-1px); }
            .btn-secondary { background: #48bb78; }
            .btn-secondary:hover { background: #38a169; }
            .help-text { font-size: 14px; color: #718096; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error">
                <div class="error-icon">📄❌</div>
                <h2>Unable to Generate Salary Slip</h2>
                <p><strong>We encountered an issue while creating your PDF.</strong></p>
                
                <div class="error-details">
                    <div class="error-code">Error: ' . htmlspecialchars($e->getMessage()) . '</div>
                    <p><strong>Record ID:</strong> ' . htmlspecialchars($recordId) . '</p>
                    <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    <p><strong>Processing Time:</strong> ' . round($errorTime, 2) . ' seconds</p>
                </div>
                
                <div>
                    <a href="javascript:history.back()" class="btn">← Go Back</a>
                    <a href="index.php?page=salary-records" class="btn btn-secondary">View All Records</a>
                </div>
                
                <div class="help-text">
                    <p><strong>💡 Quick fixes to try:</strong></p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>Refresh the page and try downloading again</li>
                        <li>Check your internet connection</li>
                        <li>Verify the salary record is complete</li>
                        <li>Contact support if the problem continues</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}
?>
