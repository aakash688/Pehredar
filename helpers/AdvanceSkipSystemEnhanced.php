<?php
/**
 * Enhanced Advance Skip System
 * Handles skip logic with proper tenure management and mobile API integration
 */

namespace Helpers;

require_once __DIR__ . '/database.php';

class AdvanceSkipSystemEnhanced {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Process skip with proper tenure management
     */
    public function processSkip($advanceId, $skipMonth, $reason, $processedBy) {
        try {
            $this->db->beginTransaction();

            // Get advance details
            $advance = $this->db->query("
                SELECT * FROM advance_payments 
                WHERE id = ? AND status IN ('active', 'approved')
            ", [$advanceId])->fetch();

            if (!$advance) {
                throw new \Exception('Active advance not found');
            }

            // Check if already skipped for this month
            $existingSkip = $this->db->query("
                SELECT id FROM advance_skip_records 
                WHERE advance_payment_id = ? AND skip_month = ?
            ", [$advanceId, $skipMonth])->fetch();

            if ($existingSkip) {
                throw new \Exception('Already skipped for this month');
            }

            // Create skip request (auto-approved for manual skips)
            $skipRequestId = $this->db->query("
                INSERT INTO advance_skip_requests (
                    advance_payment_id, skip_month, reason, requested_by,
                    status, approved_by, approved_at, approval_notes, created_at
                ) VALUES (?, ?, ?, ?, 'approved', ?, NOW(), 'Manual skip via salary edit', NOW())
            ", [$advanceId, $skipMonth, $reason, $processedBy, $processedBy]);

            $skipRequestId = $this->db->lastInsertId();

            // Create skip record
            $this->db->query("
                INSERT INTO advance_skip_records (
                    advance_payment_id, skip_month, skip_request_id,
                    monthly_deduction_amount, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ", [$advanceId, $skipMonth, $skipRequestId, $advance['monthly_deduction'], $reason]);

            // Update advance payment - extend timeline but keep installment count same
            // The installment count should remain the same, only the timeline extends
            $newExpectedCompletion = $this->calculateNewCompletionDate($advance['expected_completion_date'], 1);

            // IMPORTANT: Update financial values when skipping
            // Reverse the financial impact of the skipped payment
            $this->db->query("
                UPDATE advance_payments 
                SET expected_completion_date = ?,
                    remaining_balance = remaining_balance + ?,
                    paid_installments = GREATEST(0, paid_installments - 1),
                    updated_at = NOW()
                WHERE id = ?
            ", [$newExpectedCompletion, $advance['monthly_deduction'], $advanceId]);

            // Create reversal transaction for the skipped payment
            // This is needed for proper transaction tracking and salary record sync
            // Note: salary_record_id is null for skip transactions as they're not tied to a specific salary record
            $this->db->query("
                INSERT INTO advance_payment_transactions (
                    advance_payment_id, transaction_type, amount, installment_number, 
                    payment_date, salary_record_id, notes, processed_by, created_at
                ) VALUES (?, 'reversal', ?, 1, CURDATE(), NULL, 'Skip advance deduction - month skipped', ?, NOW())
            ", [$advanceId, $advance['monthly_deduction'], $processedBy]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Skip processed successfully',
                'new_expected_completion' => $newExpectedCompletion,
                'timeline_extended' => true
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process unskip with proper rollback
     */
    public function processUnskip($advanceId, $skipMonth, $processedBy) {
        try {
            $this->db->beginTransaction();

            // Get skip records for this month
            $skipRecords = $this->db->query("
                SELECT sr.*, req.id as request_id
                FROM advance_skip_records sr
                JOIN advance_skip_requests req ON sr.skip_request_id = req.id
                WHERE sr.advance_payment_id = ? AND sr.skip_month = ? AND req.status = 'approved'
            ", [$advanceId, $skipMonth])->fetchAll();

            if (empty($skipRecords)) {
                throw new \Exception('No approved skip found for this month');
            }

            // Get advance details
            $advance = $this->db->query("
                SELECT * FROM advance_payments WHERE id = ?
            ", [$advanceId])->fetch();

            foreach ($skipRecords as $skipRecord) {
                // Delete skip record first
                $this->db->query("DELETE FROM advance_skip_records WHERE id = ?", [$skipRecord['id']]);
                
                // Delete skip request (not just cancel) to allow future skips
                $this->db->query("DELETE FROM advance_skip_requests WHERE id = ?", [$skipRecord['request_id']]);
            }

            // Rollback timeline - reduce by number of skips removed
            $skipsRemoved = count($skipRecords);
            $newExpectedCompletion = $this->calculateNewCompletionDate($advance['expected_completion_date'], -$skipsRemoved);

            // IMPORTANT: Restore financial values when unskipping
            // Restore the financial impact of the unskipped payment(s)
            $totalRestoredAmount = $skipsRemoved * $advance['monthly_deduction'];
            
            $this->db->query("
                UPDATE advance_payments 
                SET expected_completion_date = ?,
                    remaining_balance = remaining_balance - ?,
                    paid_installments = paid_installments + ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$newExpectedCompletion, $totalRestoredAmount, $skipsRemoved, $advanceId]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Unskip processed successfully',
                'skips_removed' => $skipsRemoved,
                'new_expected_completion' => $newExpectedCompletion,
                'timeline_restored' => true
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get enhanced advance details for mobile API
     */
    public function getAdvanceDetailsForMobile($userId) {
        $advances = $this->db->query("
            SELECT 
                ap.*,
                CONCAT(u.first_name, ' ', u.surname) as employee_name,
                (SELECT COUNT(*) FROM advance_skip_records sr 
                 JOIN advance_skip_requests req ON sr.skip_request_id = req.id 
                 WHERE sr.advance_payment_id = ap.id AND req.status = 'approved') as total_skips,
                (SELECT GROUP_CONCAT(sr.skip_month ORDER BY sr.skip_month) 
                 FROM advance_skip_records sr 
                 JOIN advance_skip_requests req ON sr.skip_request_id = req.id 
                 WHERE sr.advance_payment_id = ap.id AND req.status = 'approved') as skipped_months
            FROM advance_payments ap
            JOIN users u ON ap.employee_id = u.id
            WHERE ap.employee_id = ? AND ap.status IN ('active', 'approved', 'completed')
            ORDER BY ap.created_at DESC
        ", [$userId])->fetchAll();

        foreach ($advances as &$advance) {
            // Get transaction history with proper dates
            $transactions = $this->db->query("
                SELECT 
                    apt.transaction_type,
                    apt.amount,
                    apt.installment_number,
                    apt.payment_date,
                    apt.notes,
                    apt.processed_by,
                    apt.created_at,
                    apt.salary_record_id,
                    sr.month,
                    sr.year
                FROM advance_payment_transactions apt
                LEFT JOIN salary_records sr ON apt.salary_record_id = sr.id
                WHERE apt.advance_payment_id = ?
                ORDER BY apt.payment_date ASC, apt.created_at ASC
            ", [$advance['id']])->fetchAll();

            // Format transactions with proper dates
            $advance['transactions'] = array_map(function($tx) {
                return [
                    'type' => $tx['transaction_type'],
                    'amount' => (float)$tx['amount'],
                    'installment_number' => (int)$tx['installment_number'],
                    'payment_date' => $tx['payment_date'],
                    'salary_month' => $tx['month'] && $tx['year'] ? 
                        date('F Y', mktime(0, 0, 0, $tx['month'], 1, $tx['year'])) : null,
                    'notes' => $tx['notes'],
                    'processed_by' => (int)$tx['processed_by'],
                    'created_at' => $tx['created_at']
                ];
            }, $transactions);

            // Calculate progress based on original installment count (not extended timeline)
            // The progress should be calculated as: paid_installments / original_installment_count
            $originalInstallmentCount = (int)($advance['original_installment_count'] ?? $advance['installment_count']);
            $advance['progress_percentage'] = $originalInstallmentCount > 0 ? 
                round(($advance['paid_installments'] / $originalInstallmentCount) * 100, 2) : 0;
            
            // Ensure original installment count is set
            $advance['original_installment_count'] = $originalInstallmentCount;

            // Add skip information
            $advance['has_skips'] = $advance['total_skips'] > 0;
            $advance['skipped_months_list'] = $advance['skipped_months'] ? 
                explode(',', $advance['skipped_months']) : [];

            // Calculate effective repayment period
            $advance['effective_repayment_months'] = $advance['installment_count'];
            $advance['original_repayment_months'] = $advance['installment_count'] - $advance['total_skips'];
        }

        return $advances;
    }

    /**
     * Calculate new completion date
     */
    private function calculateNewCompletionDate($currentDate, $monthsToAdd) {
        if (!$currentDate) {
            return date('Y-m-d', strtotime('+' . abs($monthsToAdd) . ' months'));
        }
        
        $date = new \DateTime($currentDate);
        $date->modify($monthsToAdd > 0 ? '+' . $monthsToAdd . ' months' : $monthsToAdd . ' months');
        return $date->format('Y-m-d');
    }

    /**
     * Get skip history for an advance
     */
    public function getSkipHistory($advanceId) {
        return $this->db->query("
            SELECT 
                sr.*,
                req.reason,
                req.approved_at,
                req.approval_notes,
                CONCAT(u.first_name, ' ', u.surname) as processed_by_name
            FROM advance_skip_records sr
            JOIN advance_skip_requests req ON sr.skip_request_id = req.id
            LEFT JOIN users u ON req.approved_by = u.id
            WHERE sr.advance_payment_id = ?
            ORDER BY sr.created_at DESC
        ", [$advanceId])->fetchAll();
    }

    /**
     * Validate skip request
     */
    public function validateSkipRequest($advanceId, $skipMonth) {
        // Check if advance exists and is active
        $advance = $this->db->query("
            SELECT * FROM advance_payments 
            WHERE id = ? AND status IN ('active', 'approved')
        ", [$advanceId])->fetch();

        if (!$advance) {
            return ['valid' => false, 'message' => 'Advance not found or not active'];
        }

        // Check if already skipped for this month
        $existingSkip = $this->db->query("
            SELECT id FROM advance_skip_records 
            WHERE advance_payment_id = ? AND skip_month = ?
        ", [$advanceId, $skipMonth])->fetch();

        if ($existingSkip) {
            return ['valid' => false, 'message' => 'Already skipped for this month'];
        }

        // Check if skip month is in the future
        $currentMonth = date('Y-m');
        if ($skipMonth <= $currentMonth) {
            return ['valid' => false, 'message' => 'Can only skip future months'];
        }

        return ['valid' => true, 'message' => 'Valid skip request'];
    }
}
?>
