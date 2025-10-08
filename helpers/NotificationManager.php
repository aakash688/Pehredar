<?php
// helpers/NotificationManager.php - Comprehensive notification management system

namespace Helpers;

require_once __DIR__ . '/database.php';

class NotificationManager {
    private $db;

    public function __construct() {
        $this->db = new \Database();
    }

    /**
     * Create and send a notification
     */
    public function createNotification($data) {
        try {
            $this->db->beginTransaction();

            // Insert notification
            $notificationQuery = "
                INSERT INTO notifications (
                    user_id, title, message, notification_type, priority, category,
                    related_table, related_id, action_url, action_text, delivery_method,
                    expires_at, metadata, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $params = [
                $data['user_id'],
                $data['title'],
                $data['message'],
                $data['notification_type'],
                $data['priority'] ?? 'medium',
                $data['category'] ?? 'general',
                $data['related_table'] ?? null,
                $data['related_id'] ?? null,
                $data['action_url'] ?? null,
                $data['action_text'] ?? null,
                $data['delivery_method'] ?? 'in_app',
                $data['expires_at'] ?? null,
                json_encode($data['metadata'] ?? []),
                $data['created_by'] ?? null
            ];

            $this->db->query($notificationQuery, $params);
            $notificationId = $this->db->lastInsertId();

            // Check user preferences and send accordingly
            $userPreferences = $this->getUserNotificationPreferences($data['user_id'], $data['notification_type']);
            
            if ($userPreferences['is_enabled']) {
                $this->processNotificationDelivery($notificationId, $userPreferences);
            }

            $this->db->commit();

            return [
                'success' => true,
                'notification_id' => $notificationId,
                'message' => 'Notification created successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("NotificationManager::createNotification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotification($users, $notificationData) {
        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($users as $userId) {
                $individualData = array_merge($notificationData, ['user_id' => $userId]);
                $result = $this->createNotification($individualData);
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "User ID $userId: " . $result['message'];
                }
            }

            return [
                'success' => $successCount > 0,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors,
                'message' => "Bulk notification sent to $successCount users"
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::sendBulkNotification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Bulk notification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications($userId, $filters = []) {
        try {
            $whereConditions = ["user_id = ?"];
            $params = [$userId];

            // Apply filters
            if (!empty($filters['is_read'])) {
                $whereConditions[] = "is_read = ?";
                $params[] = $filters['is_read'] == 'true' ? 1 : 0;
            }

            if (!empty($filters['category'])) {
                $whereConditions[] = "category = ?";
                $params[] = $filters['category'];
            }

            if (!empty($filters['priority'])) {
                $whereConditions[] = "priority = ?";
                $params[] = $filters['priority'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }

            // Exclude expired notifications
            $whereConditions[] = "(expires_at IS NULL OR expires_at > NOW())";

            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            $orderBy = "ORDER BY created_at DESC";
            $limit = !empty($filters['limit']) ? "LIMIT " . (int)$filters['limit'] : "";

            $query = "
                SELECT 
                    n.*,
                    u.first_name as created_by_name,
                    u.surname as created_by_surname
                FROM notifications n
                LEFT JOIN users u ON n.created_by = u.id
                $whereClause
                $orderBy
                $limit
            ";

            $notifications = $this->db->query($query, $params)->fetchAll();

            // Add time-based formatting
            foreach ($notifications as &$notification) {
                $notification['time_ago'] = $this->formatTimeAgo($notification['created_at']);
                $notification['is_new'] = strtotime($notification['created_at']) > (time() - 300); // 5 minutes
            }

            return [
                'success' => true,
                'notifications' => $notifications
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::getUserNotifications error: " . $e->getMessage());
            return [
                'success' => false,
                'notifications' => [],
                'message' => 'Failed to fetch notifications: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId = null) {
        try {
            $whereClause = "id = ?";
            $params = [$notificationId];

            if ($userId) {
                $whereClause .= " AND user_id = ?";
                $params[] = $userId;
            }

            $updateQuery = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE $whereClause";
            $this->db->query($updateQuery, $params);

            return [
                'success' => true,
                'message' => 'Notification marked as read'
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::markAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        try {
            $updateQuery = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE";
            $this->db->query($updateQuery, [$userId]);

            return [
                'success' => true,
                'message' => 'All notifications marked as read'
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::markAllAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark all notifications as read: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notification statistics for a user
     */
    public function getNotificationStats($userId) {
        try {
            $statsQuery = "
                SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread_count,
                    SUM(CASE WHEN priority = 'urgent' AND is_read = FALSE THEN 1 ELSE 0 END) as urgent_unread,
                    SUM(CASE WHEN priority = 'high' AND is_read = FALSE THEN 1 ELSE 0 END) as high_unread,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as today_count
                FROM notifications 
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            ";

            $stats = $this->db->query($statsQuery, [$userId])->fetch();

            // Get category breakdown
            $categoryQuery = "
                SELECT 
                    category,
                    COUNT(*) as count,
                    SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread_count
                FROM notifications 
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
                GROUP BY category
            ";

            $categories = $this->db->query($categoryQuery, [$userId])->fetchAll();

            return [
                'success' => true,
                'stats' => [
                    'total_notifications' => (int)($stats['total_notifications'] ?? 0),
                    'unread_count' => (int)($stats['unread_count'] ?? 0),
                    'urgent_unread' => (int)($stats['urgent_unread'] ?? 0),
                    'high_unread' => (int)($stats['high_unread'] ?? 0),
                    'today_count' => (int)($stats['today_count'] ?? 0),
                    'categories' => $categories
                ]
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::getNotificationStats error: " . $e->getMessage());
            return [
                'success' => false,
                'stats' => [],
                'message' => 'Failed to fetch notification stats: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create predefined notification types for payroll events
     */
    public function createAdvanceNotification($userId, $advance, $type, $createdBy) {
        $notifications = [
            'advance_granted' => [
                'title' => 'Advance Salary Granted',
                'message' => "Your advance salary request of ₹{$advance['total_advance_amount']} has been approved. Monthly deduction: ₹{$advance['monthly_deduction_amount']}",
                'category' => 'advance',
                'priority' => 'high',
                'action_url' => "index.php?page=advance-details&id={$advance['id']}",
                'action_text' => 'View Details'
            ],
            'advance_deducted' => [
                'title' => 'Advance Salary Deducted',
                'message' => "₹{$advance['deduction_amount']} has been deducted from your salary. Remaining balance: ₹{$advance['remaining_balance']}",
                'category' => 'advance',
                'priority' => 'medium'
            ],
            'advance_completed' => [
                'title' => 'Advance Repayment Completed',
                'message' => "Congratulations! Your advance salary has been fully repaid. Total amount: ₹{$advance['total_advance_amount']}",
                'category' => 'advance',
                'priority' => 'medium'
            ]
        ];

        if (!isset($notifications[$type])) {
            return ['success' => false, 'message' => 'Unknown notification type'];
        }

        $notificationData = array_merge($notifications[$type], [
            'user_id' => $userId,
            'notification_type' => $type,
            'related_table' => 'advance_salary_enhanced',
            'related_id' => $advance['id'],
            'metadata' => ['advance_data' => $advance],
            'created_by' => $createdBy
        ]);

        return $this->createNotification($notificationData);
    }

    public function createBonusNotification($userId, $bonus, $createdBy) {
        $notificationData = [
            'user_id' => $userId,
            'title' => 'Bonus Added to Your Salary',
            'message' => "Great news! A bonus of ₹{$bonus['amount']} ({$bonus['bonus_type']}) has been added to your salary for {$bonus['month']}/{$bonus['year']}",
            'notification_type' => 'bonus_added',
            'category' => 'bonus',
            'priority' => 'medium',
            'related_table' => 'bonus_records',
            'related_id' => $bonus['id'],
            'action_url' => "index.php?page=salary-records&month={$bonus['month']}",
            'action_text' => 'View Salary',
            'metadata' => ['bonus_data' => $bonus],
            'created_by' => $createdBy
        ];

        return $this->createNotification($notificationData);
    }

    public function createDeductionNotification($userId, $deduction, $createdBy) {
        $notificationData = [
            'user_id' => $userId,
            'title' => 'Salary Deduction Applied',
            'message' => "A deduction of ₹{$deduction['amount']} has been applied to your salary for {$deduction['month']}/{$deduction['year']}. Reason: {$deduction['reason']}",
            'notification_type' => 'deduction_applied',
            'category' => 'deduction',
            'priority' => 'high',
            'related_table' => 'employee_deductions',
            'related_id' => $deduction['id'],
            'action_url' => "index.php?page=salary-records&month={$deduction['month']}",
            'action_text' => 'View Details',
            'metadata' => ['deduction_data' => $deduction],
            'created_by' => $createdBy
        ];

        return $this->createNotification($notificationData);
    }

    public function createSalaryNotification($userId, $salaryRecord, $type, $createdBy) {
        $notifications = [
            'salary_generated' => [
                'title' => 'Salary Generated',
                'message' => "Your salary for {$salaryRecord['month']}/{$salaryRecord['year']} has been generated. Net salary: ₹{$salaryRecord['net_salary']}",
                'priority' => 'medium'
            ],
            'salary_disbursed' => [
                'title' => 'Salary Disbursed',
                'message' => "Your salary of ₹{$salaryRecord['net_salary']} for {$salaryRecord['month']}/{$salaryRecord['year']} has been disbursed",
                'priority' => 'high'
            ]
        ];

        if (!isset($notifications[$type])) {
            return ['success' => false, 'message' => 'Unknown notification type'];
        }

        $notificationData = array_merge($notifications[$type], [
            'user_id' => $userId,
            'notification_type' => $type,
            'category' => 'payroll',
            'related_table' => 'salary_records',
            'related_id' => $salaryRecord['id'],
            'action_url' => "index.php?page=salary-slip&id={$salaryRecord['id']}",
            'action_text' => 'Download Slip',
            'metadata' => ['salary_data' => $salaryRecord],
            'created_by' => $createdBy
        ]);

        return $this->createNotification($notificationData);
    }

    /**
     * Helper methods
     */
    private function getUserNotificationPreferences($userId, $notificationType) {
        try {
            $query = "
                SELECT is_enabled, delivery_method, frequency 
                FROM notification_preferences 
                WHERE user_id = ? AND notification_type = ?
            ";
            
            $preference = $this->db->query($query, [$userId, $notificationType])->fetch();
            
            if ($preference) {
                return $preference;
            }

            // Return default preferences if not set
            return [
                'is_enabled' => true,
                'delivery_method' => 'in_app',
                'frequency' => 'immediate'
            ];

        } catch (Exception $e) {
            error_log("NotificationManager::getUserNotificationPreferences error: " . $e->getMessage());
            return [
                'is_enabled' => true,
                'delivery_method' => 'in_app',
                'frequency' => 'immediate'
            ];
        }
    }

    private function processNotificationDelivery($notificationId, $preferences) {
        try {
            // Mark as sent
            $this->db->query("UPDATE notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = ?", [$notificationId]);

            // Here you would implement actual email/SMS delivery
            // For now, we'll just mark the delivery methods as sent
            if ($preferences['delivery_method'] === 'email' || $preferences['delivery_method'] === 'all') {
                $this->db->query("UPDATE notifications SET email_sent = TRUE, email_sent_at = NOW() WHERE id = ?", [$notificationId]);
            }

            if ($preferences['delivery_method'] === 'sms' || $preferences['delivery_method'] === 'all') {
                $this->db->query("UPDATE notifications SET sms_sent = TRUE, sms_sent_at = NOW() WHERE id = ?", [$notificationId]);
            }

        } catch (Exception $e) {
            error_log("NotificationManager::processNotificationDelivery error: " . $e->getMessage());
        }
    }

    private function formatTimeAgo($datetime) {
        $time = time() - strtotime($datetime);

        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            return floor($time / 60) . 'm ago';
        } elseif ($time < 86400) {
            return floor($time / 3600) . 'h ago';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . 'd ago';
        } else {
            return date('M j, Y', strtotime($datetime));
        }
    }

    /**
     * Clean up expired notifications
     */
    public function cleanupExpiredNotifications() {
        try {
            $cleanupQuery = "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()";
            $this->db->query($cleanupQuery);

            return ['success' => true, 'message' => 'Expired notifications cleaned up'];

        } catch (Exception $e) {
            error_log("NotificationManager::cleanupExpiredNotifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cleanup notifications'];
        }
    }
}
?>