<?php
namespace Helpers;

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfGenerator {
    private $invoice;
    private $company;
    private $items;

    public function __construct($invoice, $company, $items) {
        $this->invoice = $invoice;
        $this->company = $company;
        $this->items = $items;
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
        $invoiceNumber = str_pad($this->invoice['id'], 6, '0', STR_PAD_LEFT);
        $subtotal = array_sum(array_column($this->items, 'total'));

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color: #000;
                    margin: 0;
                    padding: 0;
                }

                .container {
                    padding: 20px;
                }

                .header {
                    width: 100%;
                    margin-bottom: 20px;
                    border-bottom: 1px solid #000;
                    padding-bottom: 10px;
                }

                .company-info, .invoice-info {
                    display: inline-block;
                    vertical-align: top;
                    width: 49%;
                }

                .invoice-info {
                    text-align: right;
                }

                .company-name {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1f2937;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    line-height: 1.2;
                    word-wrap: break-word;
                    max-width: 90%;
                }

                .company-details {
                    font-size: 10px;
                    color: #4b5563;
                    margin: 3px 0;
                    line-height: 1.4;
                }

                .status {
                    font-weight: bold;
                    font-size: 14px;
                }

                .status-paid {
                    color: #047857; /* Green for paid */
                }

                .status-pending {
                    color: #b91c1c; /* Red for pending */
                }

                .section-title {
                    font-size: 14px;
                    font-weight: bold;
                    margin-bottom: 5px;
                    margin-top: 20px;
                    color: #374151; /* Dark gray for section titles */
                }

                .client-details, .company-details {
                    margin-bottom: 3px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }

                th, td {
                    border: 1px solid #000;
                    padding: 8px;
                    font-size: 12px;
                }

                th {
                    font-weight: bold;
                    text-align: left;
                    background-color: #f3f4f6; /* Light gray background for headers */
                }

                .text-right {
                    text-align: right;
                }

                .footer-section {
                    display: table;
                    width: 100%;
                    margin-top: 30px;
                    border-top: 1px solid #000;
                    padding-top: 10px;
                }

                .notes, .payment-info {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                    padding-right: 10px;
                }

                .invoice-label {
                    display: inline-block;
                    width: 80px;
                    font-weight: 600;
                    color: #6b7280;
                    font-size: 10px;
                }

                .payment-label {
                    font-weight: bold;
                    display: inline-block;
                    width: 120px;
                    color: #374151; /* Dark gray for labels */
                }

                .signature {
                    margin-top: 50px;
                    text-align: right;
                }

                .signature-line {
                    width: 200px;
                    border-top: 1px solid #000;
                    margin-left: auto;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="company-info">
                        <div class="title">' . htmlspecialchars($this->company['company_name']) . '</div>
                        <div class="company-details">GST: ' . htmlspecialchars($this->company['gst_number']) . '</div>
                        <div class="company-details">' . htmlspecialchars($this->company['street_address']) . '</div>
                        <div class="company-details">' . htmlspecialchars($this->company['city']) . ', ' . htmlspecialchars($this->company['state']) . ' - ' . htmlspecialchars($this->company['pin_code']) . '</div>
                        <div class="company-details">Phone: ' . htmlspecialchars($this->company['phone_number']) . '</div>
                        <div class="company-details">Email: ' . htmlspecialchars($this->company['email']) . '</div>
                    </div>
                    <div class="invoice-info">
                        <div class="title">INVOICE</div>
                        <div class="status ' . ($this->invoice['status'] === 'paid' ? 'status-paid' : 'status-pending') . '">' . strtoupper($this->invoice['status']) . '</div>
                        <div>Invoice #: ' . $invoiceNumber . '</div>
                        <div>Date: ' . date('d/m/Y', strtotime($this->invoice['created_at'])) . '</div>
                        <div>Month: ' . date('F Y', strtotime($this->invoice['created_at'])) . '</div>
                    </div>
                </div>

                <div class="section-title">Bill To:</div>
                <div class="client-details"><strong>' . htmlspecialchars($this->invoice['society_name']) . '</strong></div>
                <div class="client-details">' . htmlspecialchars($this->invoice['street_address']) . '</div>
                <div class="client-details">' . htmlspecialchars($this->invoice['city']) . ', ' . htmlspecialchars($this->invoice['state']) . ' - ' . htmlspecialchars($this->invoice['pin_code']) . '</div>
                <div class="client-details">GST Number: ' . htmlspecialchars($this->invoice['gst_number']) . '</div>

                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($this->items as $item) {
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item['employee_type']) . '</td>
                            <td class="text-right">' . $item['quantity'] . '</td>
                            <td class="text-right">₹' . number_format($item['rate'], 2) . '</td>
                            <td class="text-right">Rs. ' . number_format($item['total'], 2) . '</td>
                        </tr>';
        }

        $html .= '
                        <tr>
                            <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                            <td class="text-right">Rs. ' . number_format($subtotal, 2) . '</td>
                        </tr>';

        if ($this->invoice['is_gst_applicable']) {
            $html .= '
                        <tr>
                            <td colspan="3" class="text-right"><strong>GST (18%):</strong></td>
                            <td class="text-right">&#8377;' . number_format($this->invoice['gst_amount'], 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                            <td class="text-right"><strong>&#8377;' . number_format($this->invoice['total_with_gst'], 2) . '</strong></td>
                        </tr>';
        } else {
            $html .= '
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                            <td class="text-right"><strong>&#8377;' . number_format($this->invoice['amount'], 2) . '</strong></td>
                        </tr>';
        }

        $html .= '
                    </tbody>
                </table>

                <div class="footer-section">
                    <div class="notes">
                        <div class="section-title">Notes</div>
                        <div>Thank you for your business! Payment due within 30 days.<br><strong>Terms & Conditions:</strong> This is a computer generated invoice.</div>
                    </div>

                    <div class="payment-info">
                        <div class="section-title">Payment Details</div>';

        // Conditionally add bank details only if they exist
        $bankDetails = [
            'Bank Name' => $this->company['bank_name'],
            'Account Number' => $this->company['bank_account_number'],
            'Account Type' => $this->company['bank_account_type'],
            'IFSC Code' => $this->company['bank_ifsc_code'],
            'Branch' => $this->company['bank_branch']
        ];

        foreach ($bankDetails as $label => $value) {
            if (!empty($value)) {
                $html .= '
                            <div><span class="payment-label">' . htmlspecialchars($label) . ':</span>' . htmlspecialchars($value) . '</div>';
            }
        }

        $html .= '
                    </div>
                </div>

                <div class="signature">
                    <div class="signature-line"></div>
                    <div>Authorized Signature</div>
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
}
