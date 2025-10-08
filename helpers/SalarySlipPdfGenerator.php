<?php
namespace Helpers;

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../actions/settings_controller.php';
    
    use Dompdf\Dompdf;
use Dompdf\Options;

class SalarySlipPdfGenerator {
    private $salaryRecord;
    private $employee;
    private $company;

    public function __construct($salaryRecord, $employee, $company = null) {
        $this->salaryRecord = $salaryRecord;
        $this->employee = $employee;
        
        // If company details not provided, fetch from database
        if ($company === null) {
            $db = new \Database();
            $pdo = $db->getPdo(); // Note: lowercase 'd'
            $this->company = get_company_settings($pdo);
            
            // If no company settings found, set defaults
            if (!$this->company) {
                $this->company = [
                    'company_name' => 'Your Company Name',
                    'street_address' => 'Company Address',
                    'city' => 'City',
                    'state' => 'State',
                    'pincode' => '',
                    'email' => 'company@email.com',
                    'phone_number' => '0000000000',
                    'gst_number' => 'GSTIN12345678',
                    'logo_path' => null,
                    'signature_image' => null
                ];
            }
        } else {
            $this->company = $company;
        }
    }

    public function generate() {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('defaultCharset', 'UTF-8');
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->generateHtml();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    private function generateHtml() {
        $employeeName = $this->employee['first_name'] . ' ' . $this->employee['surname'];
        
        // Get the base URL from config file
        $config = require __DIR__ . '/../config.php';
        $baseUrl = rtrim($config['base_url'], '/');
        
        // Support both numeric month/year and 'YYYY-MM' string in salaryRecord
        if (isset($this->salaryRecord['year']) && isset($this->salaryRecord['month']) && is_numeric($this->salaryRecord['month'])) {
            $year = (int)$this->salaryRecord['year'];
            $month = (int)$this->salaryRecord['month'];
        } else {
            $monthParts = explode('-', (string)$this->salaryRecord['month']);
            $year = isset($monthParts[0]) ? (int)$monthParts[0] : (int)date('Y');
            $month = isset($monthParts[1]) ? (int)$monthParts[1] : (int)date('n');
        }
        
        $monthYear = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $payDate = isset($this->salaryRecord['disbursed_at']) && $this->salaryRecord['disbursed_at'] ? date('d/m/Y', strtotime($this->salaryRecord['disbursed_at'])) : 'Not Disbursed';
        
        // Calculate working days breakdown
        $totalWorkingDays = $this->salaryRecord['total_working_days'];
        $presentDays = $this->salaryRecord['attendance_present_days'];
        $absentDays = $this->salaryRecord['attendance_absent_days'];
        $holidayDays = $this->salaryRecord['attendance_holiday_days'];
        $doubleShiftDays = $this->salaryRecord['attendance_double_shift_days'];
        
        // Per day salary calculation - should be monthly salary / 30 days
        $perDaySalary = $this->salaryRecord['base_salary'] / 30;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 11px;
                    color: #000;
                    margin: 0;
                    padding: 0;
                    line-height: 1.2;
                }

                .container {
                    padding: 15px;
                }

                .header {
                    width: 100%;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 8px;
                    text-align: center;
                }

                .company-name {
                    font-size: 16px;
                    font-weight: bold;
                    color: #1f2937;
                    margin-bottom: 3px;
                    text-transform: uppercase;
                }

                .document-title {
                    font-size: 14px;
                    font-weight: bold;
                    color: #374151;
                    margin: 6px 0;
                    text-decoration: underline;
                }

                .employee-info {
                    display: table;
                    width: 100%;
                    margin-bottom: 12px;
                    border: 1px solid #000;
                    padding: 6px;
                    background-color: #f9f9f9;
                }

                .info-row {
                    display: table-row;
                }

                .info-label, .info-value {
                    display: table-cell;
                    padding: 3px 8px;
                    border-bottom: 1px dotted #ccc;
                    font-size: 10px;
                }

                .info-label {
                    font-weight: bold;
                    width: 30%;
                    color: #374151;
                }

                .salary-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }

                .salary-table th, .salary-table td {
                    border: 1px solid #000;
                    padding: 5px;
                    text-align: left;
                    font-size: 10px;
                }

                .salary-table th {
                    background-color: #e5e7eb;
                    font-weight: bold;
                    text-align: center;
                    font-size: 11px;
                }

                .text-right {
                    text-align: right;
                }

                .text-center {
                    text-align: center;
                }

                .earnings {
                    background-color: #f0f9ff;
                }

                .deductions {
                    background-color: #fef3f2;
                }

                .total-row {
                    background-color: #f3f4f6;
                    font-weight: bold;
                }

                .final-amount {
                    font-size: 12px;
                    font-weight: bold;
                    color: #059669;
                }

                .status-badge {
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 9px;
                    font-weight: bold;
                }

                .status-disbursed {
                    background-color: #d1fae5;
                    color: #047857;
                }

                .status-pending {
                    background-color: #fef3c7;
                    color: #d97706;
                }

                .footer {
                    margin-top: 15px;
                    border-top: 1px solid #000;
                    padding-top: 8px;
                    font-size: 9px;
                    color: #6b7280;
                }

                .signature-section {
                    display: table;
                    width: 100%;
                    margin-top: 20px;
                }

                .signature-left, .signature-right {
                    display: table-cell;
                    width: 50%;
                    text-align: center;
                    vertical-align: top;
                    font-size: 10px;
                }

                .signature-line {
                    width: 120px;
                    border-top: 1px solid #000;
                    margin: 25px auto 5px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                                 <!-- Header -->
                 <div class="header">
                     <!-- Company Logo if available -->
                     ' . ($this->company['logo_path'] ? '<div style="margin-bottom: 10px;"><img src="' . $baseUrl . '/' . htmlspecialchars($this->company['logo_path']) . '" alt="Company Logo" style="max-height: 60px; max-width: 200px;"></div>' : '') . '
                     
                     <div class="company-name">' . htmlspecialchars($this->company['company_name'] ?? 'Your Company Name') . '</div>
                     ' . ($this->company['street_address'] ? '<div>' . htmlspecialchars($this->company['street_address']) . '</div>' : '') . '
                     ' . (($this->company['city'] || $this->company['state'] || $this->company['pincode']) ? 
                         '<div>' . 
                         htmlspecialchars($this->company['city'] ?? '') . 
                         ($this->company['city'] && ($this->company['state'] || $this->company['pincode']) ? ', ' : '') .
                         htmlspecialchars($this->company['state'] ?? '') . 
                         ($this->company['pincode'] ? ' - ' . htmlspecialchars($this->company['pincode']) : '') . 
                         '</div>' : '') . '
                     ' . (($this->company['email'] || $this->company['phone_number']) ? 
                         '<div>' . 
                         ($this->company['email'] ? 'Email: ' . htmlspecialchars($this->company['email']) : '') .
                         (($this->company['email'] && $this->company['phone_number']) ? ' | ' : '') .
                         ($this->company['phone_number'] ? 'Phone: ' . htmlspecialchars($this->company['phone_number']) : '') .
                         '</div>' : '') . '
                     ' . ($this->company['gst_number'] ? '<div>GST No: ' . htmlspecialchars($this->company['gst_number']) . '</div>' : '') . '
                     <div class="document-title">SALARY SLIP</div>
                 </div>

                <!-- Employee Information -->
                <div class="employee-info">
                    <div class="info-row">
                        <div class="info-label">Employee Name:</div>
                        <div class="info-value">' . htmlspecialchars($employeeName) . '</div>
                        <div class="info-label">Employee ID:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['id']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Department:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['department'] ?? 'N/A') . '</div>
                        <div class="info-label">Designation:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['designation'] ?? 'N/A') . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ESIC Number:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['esic_number'] ?? 'N/A') . '</div>
                        <div class="info-label">UAN Number:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['uan_number'] ?? 'N/A') . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">PF Number:</div>
                        <div class="info-value">' . htmlspecialchars($this->employee['pf_number'] ?? 'N/A') . '</div>
                        <div class="info-label"></div>
                        <div class="info-value"></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Pay Period:</div>
                        <div class="info-value">' . htmlspecialchars($monthYear) . '</div>
                        <div class="info-label">Pay Date:</div>
                        <div class="info-value">' . htmlspecialchars($payDate) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Status:</div>
                        <div class="info-value">
                            <span class="status-badge ' . ($this->salaryRecord['disbursement_status'] === 'disbursed' ? 'status-disbursed' : 'status-pending') . '">
                                ' . strtoupper($this->salaryRecord['disbursement_status']) . '
                            </span>
                        </div>
                        <div class="info-label">Generation Type:</div>
                        <div class="info-value">' . ($this->salaryRecord['manually_modified'] ? 'Manually Modified' : 'Auto Generated') . '</div>
                    </div>
                </div>

                <!-- Attendance Summary -->
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th colspan="4">ATTENDANCE SUMMARY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Present Days</strong></td>
                            <td class="text-center">' . $presentDays . '</td>
                            <td><strong>Absent Days</strong></td>
                            <td class="text-center">' . $absentDays . '</td>
                        </tr>
                        <tr>
                            <td><strong>Holiday Days</strong></td>
                            <td class="text-center">' . $holidayDays . '</td>
                            <td><strong>Double Shift Days</strong></td>
                            <td class="text-center">' . $doubleShiftDays . '</td>
                        </tr>
                        <tr>
                            <td><strong>Total Working Days</strong></td>
                            <td class="text-center">' . $totalWorkingDays . '</td>
                            <td><strong>Attendance Multiplier</strong></td>
                            <td class="text-center">' . number_format($this->salaryRecord['attendance_multiplier_total'], 2) . '</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Salary Breakdown -->
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">EARNINGS</th>
                            <th style="width: 50%;">DEDUCTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="earnings">
                                <div><strong>Basic Salary (Monthly)</strong></div>
                                <div style="margin: 2px 0;">Rs. ' . number_format($this->salaryRecord['base_salary'], 2) . '</div>
                                
                                <div><strong>Per Day Rate</strong></div>
                                <div style="margin: 2px 0;">Rs. ' . number_format($perDaySalary, 2) . '</div>
                                
                                <div><strong>Calculated Salary</strong></div>
                                <div style="margin: 2px 0;">Rs. ' . number_format($this->salaryRecord['calculated_salary'], 2) . '</div>';

        if ($this->salaryRecord['additional_bonuses'] > 0) {
            $html .= '
                                <div style="margin-top: 5px;"><strong>Additional Bonuses</strong></div>
                                <div style="margin: 2px 0; color: #059669;">+Rs. ' . number_format($this->salaryRecord['additional_bonuses'], 2) . '</div>';
        }

        $html .= '
                            </td>
                            <td class="deductions">';

        if ($this->salaryRecord['deductions'] > 0) {
            $html .= '
                                <div><strong>Salary Deductions</strong></div>
                                <div style="margin: 2px 0; color: #dc2626;">-Rs. ' . number_format($this->salaryRecord['deductions'], 2) . '</div>';
        }

        if ($this->salaryRecord['advance_salary_deducted'] > 0) {
            $html .= '
                                <div><strong>Advance Salary Deducted</strong></div>
                                <div style="margin: 2px 0; color: #dc2626;">-Rs. ' . number_format($this->salaryRecord['advance_salary_deducted'], 2) . '</div>';
        }

        // Display specific deduction amounts from deduction master
        if (!empty($this->salaryRecord['deductions_detail'])) {
            $html .= '<div style="margin-top: 5px;"><strong>Specific Deductions</strong></div>';
            foreach ($this->salaryRecord['deductions_detail'] as $deduction) {
                $label = htmlspecialchars($deduction['deduction_name'] . ' (' . $deduction['deduction_code'] . ')');
                $html .= '<div style="margin: 1px 0; color: #dc2626;">-Rs. ' . number_format($deduction['deduction_amount'], 2) . ' — ' . $label . '</div>';
            }
        }

        // Optional detailed statutory breakdown if provided (from joined query)
        if (!empty($this->salaryRecord['statutory_breakdown'])) {
            $html .= '<div style="margin-top: 5px;"><strong>Statutory Deductions</strong></div>';
            foreach ($this->salaryRecord['statutory_breakdown'] as $s) {
                $label = htmlspecialchars($s['name'] . (!empty($s['note']) ? (' (' . $s['note'] . ')') : ''));
                $html .= '<div style="margin: 1px 0; color: #dc2626;">-Rs. ' . number_format($s['amount'], 2) . ' — ' . $label . '</div>';
            }
        }

        $hasAnyDeductions = $this->salaryRecord['deductions'] > 0 || 
                           $this->salaryRecord['advance_salary_deducted'] > 0 || 
                           !empty($this->salaryRecord['deductions_detail']) || 
                           !empty($this->salaryRecord['statutory_breakdown']);

        if (!$hasAnyDeductions) {
            $html .= '
                                <div style="text-align: center; color: #6b7280; padding: 10px;">No Deductions</div>';
        }

        $html .= '
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Final Amount -->
                <table class="salary-table">
                    <tbody>
                        <tr class="total-row">
                            <td><strong>NET SALARY PAYABLE</strong></td>
                            <td class="text-right final-amount">Rs. ' . number_format($this->salaryRecord['final_salary'], 2) . '</td>
                        </tr>
                    </tbody>
                </table>

                                 <!-- Signature Section -->
                 <div class="signature-section">
                     <div class="signature-left">
                         <div class="signature-line"></div>
                         <div>Employee Signature</div>
                     </div>
                     <div class="signature-right">
                         ' . ($this->company['signature_image'] ? 
                             '<div style="margin-bottom: 10px;"><img src="' . $baseUrl . '/' . htmlspecialchars($this->company['signature_image']) . '" alt="Authorized Signature" style="max-height: 40px; max-width: 120px;"></div>' : 
                             '<div class="signature-line"></div>') . '
                         <div>Authorized Signature</div>
                     </div>
                 </div>

                <!-- Footer -->
                <div class="footer">
                    <div><strong>Note:</strong> This is a computer-generated salary slip and does not require a physical signature.</div>
                    <div>Generated on: ' . date('d/m/Y H:i:s') . '</div>
                    <div>Salary Record ID: #' . str_pad($this->salaryRecord['id'], 6, '0', STR_PAD_LEFT) . '</div>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }

    public function saveToFile($filename) {
        $pdfContent = $this->generate();
        file_put_contents($filename, $pdfContent);
        return $filename;
    }

    public function getFilename() {
        $employeeName = str_replace(' ', '_', $this->employee['first_name'] . '_' . $this->employee['surname']);
        
        // Support both numeric month/year and 'YYYY-MM' string
        if (isset($this->salaryRecord['year']) && isset($this->salaryRecord['month']) && is_numeric($this->salaryRecord['month'])) {
            $year = (int)$this->salaryRecord['year'];
            $month = (int)$this->salaryRecord['month'];
        } else {
            $monthParts = explode('-', (string)$this->salaryRecord['month']);
            $year = isset($monthParts[0]) ? (int)$monthParts[0] : (int)date('Y');
            $month = isset($monthParts[1]) ? (int)$monthParts[1] : (int)date('n');
        }
        
        $monthYear = date('M_Y', mktime(0, 0, 0, $month, 1, $year));
        return "salary_slip_{$employeeName}_{$monthYear}.pdf";
    }
}