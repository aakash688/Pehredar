<?php
/**
 * Advance Deduction Verifier
 * Helper class to verify and debug advance deduction processing
 */

namespace Helpers;

require_once __DIR__ . '/database.php';

class AdvanceDeductionVerifier {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Verify advance deduction processing for a specific salary record
     */
    public function verifySalaryRecordDeductions($salaryRecordId) {
        try {
            // Get salary record details
            $salaryRecord = $this->db->query("
                SELECT sr.*, u.first_name, u.surname 
                FROM salary_records sr
                JOIN users u ON sr.user_id = u.id
                WHERE sr.id = ?
            ", [$salaryRecordId])->fetch();

            if (!$salaryRecord) {
                return [
                    'success' => false,
                    'message' => 'Salary record not found'
                ];
            }

            // Get advance deduction amount from salary record
            $advanceDeducted = (float)$salaryRecord['advance_salary_deducted'];

            // Get advance payment transactions for this salary record
            $transactions = $this->db->query("
                SELECT apt.*, ap.amount as advance_amount, ap.remaining_balance, ap.status as advance_status
                FROM advance_payment_transactions apt
                JOIN advance_payments ap ON apt.advance_payment_id = ap.id
                WHERE apt.salary_record_id = ? AND apt.transaction_type = 'deduction'
                ORDER BY apt.id ASC
            ", [$salaryRecordId])->fetchAll();

            // Get current advance payment status for this user
            $currentAdvances = $this->db->query("
                SELECT id, amount, remaining_balance, paid_installments, status, created_at
                FROM advance_payments 
                WHERE employee_id = ? AND status IN ('active', 'completed')
                ORDER BY created_at ASC
            ", [$salaryRecord['user_id']])->fetchAll();

            // Calculate totals
            $totalTransactionAmount = array_sum(array_column($transactions, 'amount'));
            $totalRemainingBalance = array_sum(array_column($currentAdvances, 'remaining_balance'));

            return [
                'success' => true,
                'salary_record' => [
                    'id' => $salaryRecord['id'],
                    'employee' => $salaryRecord['first_name'] . ' ' . $salaryRecord['surname'],
                    'month' => $salaryRecord['month'],
                    'year' => $salaryRecord['year'],
                    'advance_deducted' => $advanceDeducted
                ],
                'transactions' => $transactions,
                'current_advances' => $currentAdvances,
                'summary' => [
                    'salary_advance_deducted' => $advanceDeducted,
                    'total_transaction_amount' => $totalTransactionAmount,
                    'total_remaining_balance' => $totalRemainingBalance,
                    'transaction_count' => count($transactions),
                    'advance_count' => count($currentAdvances),
                    'is_balanced' => abs($advanceDeducted - $totalTransactionAmount) < 0.01
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error verifying deductions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get advance payment history for a specific user
     */
    public function getAdvancePaymentHistory($userId) {
        try {
            // Get all advance payments for user
            $advances = $this->db->query("
                SELECT ap.*, 
                       COALESCE(SUM(apt.amount), 0) as total_deducted,
                       COUNT(apt.id) as transaction_count
                FROM advance_payments ap
                LEFT JOIN advance_payment_transactions apt ON ap.id = apt.advance_payment_id AND apt.transaction_type = 'deduction'
                WHERE ap.employee_id = ?
                GROUP BY ap.id
                ORDER BY ap.created_at ASC
            ", [$userId])->fetchAll();

            // Get all transactions for this user
            $transactions = $this->db->query("
                SELECT apt.*, sr.month, sr.year
                FROM advance_payment_transactions apt
                JOIN advance_payments ap ON apt.advance_payment_id = ap.id
                LEFT JOIN salary_records sr ON apt.salary_record_id = sr.id
                WHERE ap.employee_id = ? AND apt.transaction_type = 'deduction'
                ORDER BY apt.payment_date DESC, apt.id DESC
            ", [$userId])->fetchAll();

            return [
                'success' => true,
                'advances' => $advances,
                'transactions' => $transactions
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting advance history: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if advance deductions are properly processed for a month
     */
    public function checkMonthAdvanceDeductions($month, $year) {
        try {
            // Get all salary records for the month with advance deductions
            $salaryRecords = $this->db->query("
                SELECT sr.id, sr.user_id, sr.advance_salary_deducted, 
                       u.first_name, u.surname,
                       COALESCE(SUM(apt.amount), 0) as total_transactions
                FROM salary_records sr
                JOIN users u ON sr.user_id = u.id
                LEFT JOIN advance_payment_transactions apt ON sr.id = apt.salary_record_id AND apt.transaction_type = 'deduction'
                WHERE sr.month = ? AND sr.year = ? AND sr.advance_salary_deducted > 0
                GROUP BY sr.id, sr.user_id, sr.advance_salary_deducted, u.first_name, u.surname
                ORDER BY sr.user_id
            ", [$month, $year])->fetchAll();

            $issues = [];
            $processed = [];

            foreach ($salaryRecords as $record) {
                $advanceDeducted = (float)$record['advance_salary_deducted'];
                $totalTransactions = (float)$record['total_transactions'];
                
                if (abs($advanceDeducted - $totalTransactions) > 0.01) {
                    $issues[] = [
                        'salary_record_id' => $record['id'],
                        'employee' => $record['first_name'] . ' ' . $record['surname'],
                        'advance_deducted' => $advanceDeducted,
                        'total_transactions' => $totalTransactions,
                        'difference' => $advanceDeducted - $totalTransactions
                    ];
                } else {
                    $processed[] = [
                        'salary_record_id' => $record['id'],
                        'employee' => $record['first_name'] . ' ' . $record['surname'],
                        'amount' => $advanceDeducted
                    ];
                }
            }

            return [
                'success' => true,
                'month' => $month,
                'year' => $year,
                'total_records' => count($salaryRecords),
                'processed_correctly' => count($processed),
                'issues_found' => count($issues),
                'issues' => $issues,
                'processed' => $processed
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error checking month deductions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fix missing advance payment transactions for a salary record
     */
    public function fixMissingTransactions($salaryRecordId) {
        try {
            $this->db->beginTransaction();

            // Get salary record
            $salaryRecord = $this->db->query("
                SELECT * FROM salary_records WHERE id = ?
            ", [$salaryRecordId])->fetch();

            if (!$salaryRecord) {
                throw new \Exception('Salary record not found');
            }

            $advanceDeducted = (float)$salaryRecord['advance_salary_deducted'];
            
            if ($advanceDeducted <= 0) {
                return [
                    'success' => true,
                    'message' => 'No advance deduction to process'
                ];
            }

            // Get active advances
            $activeAdvances = $this->db->query("
                SELECT id, remaining_balance, monthly_deduction 
                FROM advance_payments 
                WHERE employee_id = ? AND status IN ('active', 'approved') AND remaining_balance > 0 
                ORDER BY created_at ASC
            ", [$salaryRecord['user_id']])->fetchAll();

            if (empty($activeAdvances)) {
                throw new \Exception('No active advances found');
            }

            $remainingDeduction = $advanceDeducted;
            $processedAdvances = [];

            foreach ($activeAdvances as $advance) {
                if ($remainingDeduction <= 0) break;

                $deductionFromThisAdvance = min($remainingDeduction, (float)$advance['remaining_balance']);

                if ($deductionFromThisAdvance > 0) {
                    // Check if transaction already exists
                    $existingTx = $this->db->query("
                        SELECT id FROM advance_payment_transactions 
                        WHERE advance_payment_id = ? AND salary_record_id = ? AND transaction_type = 'deduction'
                    ", [$advance['id'], $salaryRecordId])->fetch();

                    if (!$existingTx) {
                        // Create transaction
                        $monthFormatted = sprintf('%04d-%02d', $salaryRecord['year'], $salaryRecord['month']);
                        $notesText = "Salary deduction for " . date('F Y', strtotime($monthFormatted . '-01'));

                        $this->db->query("
                            INSERT INTO advance_payment_transactions 
                            (advance_payment_id, transaction_type, amount, installment_number, payment_date, salary_record_id, notes, processed_by) 
                            VALUES (?, 'deduction', ?, 1, LAST_DAY(STR_TO_DATE(CONCAT(?, '-01'), '%Y-%m-%d')), ?, ?, ?)
                        ", [
                            $advance['id'],
                            $deductionFromThisAdvance,
                            $monthFormatted,
                            $salaryRecordId,
                            $notesText,
                            $_SESSION['user_id'] ?? 1
                        ]);

                        // Update advance payment
                        $newBalance = (float)$advance['remaining_balance'] - $deductionFromThisAdvance;
                        $monthlyAmt = max(0.00001, (float)$advance['monthly_deduction']);
                        $shouldIncrement = (int)floor(($deductionFromThisAdvance + 1e-6) / $monthlyAmt);

                        $this->db->query("
                            UPDATE advance_payments 
                            SET remaining_balance = ?, 
                                paid_installments = paid_installments + ?, 
                                status = CASE WHEN ? <= 0 THEN 'completed' ELSE 'active' END, 
                                completion_date = CASE WHEN ? <= 0 THEN CURDATE() ELSE completion_date END, 
                                updated_at = NOW() 
                            WHERE id = ?
                        ", [$newBalance, $shouldIncrement, $newBalance, $newBalance, $advance['id']]);

                        $processedAdvances[] = [
                            'advance_id' => $advance['id'],
                            'deducted' => $deductionFromThisAdvance,
                            'new_balance' => $newBalance
                        ];

                        $remainingDeduction -= $deductionFromThisAdvance;
                    }
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Missing transactions fixed',
                'processed_advances' => $processedAdvances,
                'remaining_unallocated' => $remainingDeduction
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error fixing transactions: ' . $e->getMessage()
            ];
        }
    }
}
?>

