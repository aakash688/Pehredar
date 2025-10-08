<?php
/**
 * Advance Skip Request Manager
 * Handles skip requests, tenure extensions, and deduction adjustments
 */

namespace Helpers;

require_once __DIR__ . '/database.php';

class AdvanceSkipManager {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Request to skip advance deduction for a specific month
     */
    public function requestSkipDeduction($advanceId, $skipMonth, $reason, $requestedBy) {
        try {
            $this->db->beginTransaction();

            // Validate advance exists and is active
            $advance = $this->db->query("
                SELECT * FROM advance_payments 
                WHERE id = ? AND status = 'active' AND remaining_balance > 0
            ", [$advanceId])->fetch();

            if (!$advance) {
                throw new \Exception('Active advance not found');
            }

            // Check if skip request already exists for this month
            $existingSkip = $this->db->query("
                SELECT id FROM advance_skip_requests 
                WHERE advance_payment_id = ? AND skip_month = ? AND status IN ('pending', 'approved')
            ", [$advanceId, $skipMonth])->fetch();

            if ($existingSkip) {
                throw new \Exception('Skip request already exists for this month');
            }

            // Create skip request
            $skipRequestId = $this->db->query("
                INSERT INTO advance_skip_requests (
                    advance_payment_id, skip_month, reason, requested_by, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ", [$advanceId, $skipMonth, $reason, $requestedBy]);

            $this->db->commit();

            return [
                'success' => true,
                'skip_request_id' => $this->db->lastInsertId(),
                'message' => 'Skip request submitted successfully'
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Approve skip request and extend tenure
     */
    public function approveSkipRequest($skipRequestId, $approvedBy, $notes = '') {
        try {
            $this->db->beginTransaction();

            // Get skip request details
            $skipRequest = $this->db->query("
                SELECT sr.*, ap.* FROM advance_skip_requests sr
                JOIN advance_payments ap ON sr.advance_payment_id = ap.id
                WHERE sr.id = ? AND sr.status = 'pending'
            ", [$skipRequestId])->fetch();

            if (!$skipRequest) {
                throw new \Exception('Pending skip request not found');
            }

            // Update skip request status
            $this->db->query("
                UPDATE advance_skip_requests 
                SET status = 'approved', approved_by = ?, approved_at = NOW(), 
                    approval_notes = ?, updated_at = NOW()
                WHERE id = ?
            ", [$approvedBy, $notes, $skipRequestId]);

            // Extend tenure by 1 month
            $newExpectedCompletion = date('Y-m-d', strtotime($skipRequest['expected_completion_date'] . ' +1 month'));
            
            $this->db->query("
                UPDATE advance_payments 
                SET expected_completion_date = ?, installment_count = installment_count + 1,
                    updated_at = NOW()
                WHERE id = ?
            ", [$newExpectedCompletion, $skipRequest['advance_payment_id']]);

            // Create skip record for the month
            $this->db->query("
                INSERT INTO advance_skip_records (
                    advance_payment_id, skip_month, skip_request_id, 
                    monthly_deduction_amount, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ", [
                $skipRequest['advance_payment_id'],
                $skipRequest['skip_month'],
                $skipRequestId,
                $skipRequest['monthly_deduction'],
                $skipRequest['reason']
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Skip request approved and tenure extended',
                'new_completion_date' => $newExpectedCompletion
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Reject skip request
     */
    public function rejectSkipRequest($skipRequestId, $rejectedBy, $reason) {
        try {
            $this->db->beginTransaction();

            $this->db->query("
                UPDATE advance_skip_requests 
                SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), 
                    rejection_reason = ?, updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ", [$rejectedBy, $reason, $skipRequestId]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Skip request rejected'
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if deduction should be skipped for a specific month
     */
    public function shouldSkipDeduction($advanceId, $salaryMonth) {
        $skipRecord = $this->db->query("
            SELECT sr.* FROM advance_skip_records sr
            JOIN advance_skip_requests req ON sr.skip_request_id = req.id
            WHERE sr.advance_payment_id = ? AND sr.skip_month = ? AND req.status = 'approved'
        ", [$advanceId, $salaryMonth])->fetch();

        return $skipRecord ? true : false;
    }

    /**
     * Get all skip requests for an advance
     */
    public function getAdvanceSkipRequests($advanceId) {
        return $this->db->query("
            SELECT sr.*, 
                   CONCAT(u.first_name, ' ', u.surname) as requested_by_name,
                   CONCAT(approver.first_name, ' ', approver.surname) as approved_by_name,
                   CONCAT(rejector.first_name, ' ', rejector.surname) as rejected_by_name
            FROM advance_skip_requests sr
            LEFT JOIN users u ON sr.requested_by = u.id
            LEFT JOIN users approver ON sr.approved_by = approver.id
            LEFT JOIN users rejector ON sr.rejected_by = rejector.id
            WHERE sr.advance_payment_id = ?
            ORDER BY sr.created_at DESC
        ", [$advanceId])->fetchAll();
    }

    /**
     * Get pending skip requests for admin approval
     */
    public function getPendingSkipRequests() {
        return $this->db->query("
            SELECT sr.*, 
                   CONCAT(u.first_name, ' ', u.surname) as employee_name,
                   ap.request_number, ap.amount, ap.remaining_balance,
                   CONCAT(req.first_name, ' ', req.surname) as requested_by_name
            FROM advance_skip_requests sr
            JOIN advance_payments ap ON sr.advance_payment_id = ap.id
            JOIN users u ON ap.employee_id = u.id
            LEFT JOIN users req ON sr.requested_by = req.id
            WHERE sr.status = 'pending'
            ORDER BY sr.created_at ASC
        ")->fetchAll();
    }

    /**
     * Calculate new monthly deduction after skip
     */
    public function calculateAdjustedDeduction($advanceId) {
        $advance = $this->db->query("
            SELECT * FROM advance_payments WHERE id = ?
        ", [$advanceId])->fetch();

        if (!$advance) {
            return null;
        }

        // Count approved skips
        $skipCount = $this->db->query("
            SELECT COUNT(*) as skip_count FROM advance_skip_records sr
            JOIN advance_skip_requests req ON sr.skip_request_id = req.id
            WHERE sr.advance_payment_id = ? AND req.status = 'approved'
        ", [$advanceId])->fetch()['skip_count'];

        // Calculate remaining months
        $remainingMonths = $advance['installment_count'] - $advance['paid_installments'] + $skipCount;
        
        if ($remainingMonths <= 0) {
            return 0;
        }

        // Recalculate monthly deduction
        $newMonthlyDeduction = $advance['remaining_balance'] / $remainingMonths;

        return [
            'original_monthly_deduction' => $advance['monthly_deduction'],
            'new_monthly_deduction' => round($newMonthlyDeduction, 2),
            'remaining_months' => $remainingMonths,
            'skip_count' => $skipCount
        ];
    }

    /**
     * Update monthly deduction after skip approval
     */
    public function updateMonthlyDeduction($advanceId) {
        $adjustment = $this->calculateAdjustedDeduction($advanceId);
        
        if (!$adjustment) {
            return ['success' => false, 'message' => 'Advance not found'];
        }

        $this->db->query("
            UPDATE advance_payments 
            SET monthly_deduction = ?, updated_at = NOW()
            WHERE id = ?
        ", [$adjustment['new_monthly_deduction'], $advanceId]);

        return [
            'success' => true,
            'adjustment' => $adjustment
        ];
    }
}
?>

