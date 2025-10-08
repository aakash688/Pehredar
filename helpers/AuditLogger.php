<?php
// helpers/AuditLogger.php - Comprehensive audit trail management

namespace Helpers;

require_once __DIR__ . '/database.php';

class AuditLogger {
    private $db;
    private $currentUserId;
    private $sessionId;
    private $ipAddress;
    private $userAgent;

    public function __construct($userId = null) {
        $this->db = new \Database();
        $this->currentUserId = $userId;
        $this->sessionId = session_id();
        $this->ipAddress = $this->getClientIpAddress();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Log an audit event
     */
    public function log($actionType, $tableName, $recordId = null, $oldValues = [], $newValues = [], $description = null, $module = 'general', $severity = 'medium') {
        try {
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action_type, table_name, record_id, old_values, new_values,
                    description, module, severity, ip_address, user_agent, session_id,
                    request_method, request_url, success, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
            ";

            $params = [
                $this->currentUserId,
                $actionType,
                $tableName,
                $recordId,
                json_encode($oldValues),
                json_encode($newValues),
                $description,
                $module,
                $severity,
                $this->ipAddress,
                $this->userAgent,
                $this->sessionId,
                $_SERVER['REQUEST_METHOD'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null
            ];

            $this->db->query($auditQuery, $params);

            return [
                'success' => true,
                'audit_id' => $this->db->lastInsertId()
            ];

        } catch (Exception $e) {
            error_log("AuditLogger::log error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to log audit event: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log successful operation
     */
    public function logSuccess($actionType, $tableName, $recordId = null, $oldValues = [], $newValues = [], $description = null, $module = 'general') {
        return $this->log($actionType, $tableName, $recordId, $oldValues, $newValues, $description, $module, 'low');
    }

    /**
     * Log failed operation
     */
    public function logFailure($actionType, $tableName, $recordId = null, $errorMessage = null, $module = 'general') {
        try {
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action_type, table_name, record_id, description, module, 
                    severity, ip_address, user_agent, session_id, request_method, 
                    request_url, success, error_message, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'high', ?, ?, ?, ?, ?, FALSE, ?, NOW())
            ";

            $params = [
                $this->currentUserId,
                $actionType,
                $tableName,
                $recordId,
                "Failed operation: $actionType on $tableName",
                $module,
                $this->ipAddress,
                $this->userAgent,
                $this->sessionId,
                $_SERVER['REQUEST_METHOD'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null,
                $errorMessage
            ];

            $this->db->query($auditQuery, $params);

            return [
                'success' => true,
                'audit_id' => $this->db->lastInsertId()
            ];

        } catch (Exception $e) {
            error_log("AuditLogger::logFailure error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to log audit failure: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log salary-related operations
     */
    public function logSalaryOperation($actionType, $recordId, $oldValues = [], $newValues = [], $description = null) {
        return $this->log($actionType, 'salary_records', $recordId, $oldValues, $newValues, $description, 'payroll', 'medium');
    }

    /**
     * Log advance-related operations
     */
    public function logAdvanceOperation($actionType, $recordId, $oldValues = [], $newValues = [], $description = null) {
        return $this->log($actionType, 'advance_salary_enhanced', $recordId, $oldValues, $newValues, $description, 'advance_management', 'high');
    }

    /**
     * Log bonus operations
     */
    public function logBonusOperation($actionType, $recordId, $oldValues = [], $newValues = [], $description = null) {
        return $this->log($actionType, 'bonus_records', $recordId, $oldValues, $newValues, $description, 'bonus_management', 'medium');
    }

    /**
     * Log deduction operations
     */
    public function logDeductionOperation($actionType, $recordId, $oldValues = [], $newValues = [], $description = null) {
        return $this->log($actionType, 'employee_deductions', $recordId, $oldValues, $newValues, $description, 'deduction_management', 'medium');
    }

    /**
     * Log bulk operations
     */
    public function logBulkOperation($operationType, $affectedCount, $operationData, $description = null) {
        $additionalData = [
            'operation_type' => $operationType,
            'affected_count' => $affectedCount,
            'operation_data' => $operationData
        ];

        return $this->log($operationType, 'multiple_tables', null, [], $additionalData, $description, 'bulk_operations', 'high');
    }

    /**
     * Log user authentication events
     */
    public function logAuthEvent($actionType, $userId, $success = true, $description = null) {
        $severity = $success ? 'low' : 'high';
        $module = 'authentication';

        if (!$success) {
            return $this->logFailure($actionType, 'users', $userId, $description, $module);
        } else {
            return $this->log($actionType, 'users', $userId, [], [], $description, $module, $severity);
        }
    }

    /**
     * Log system access events
     */
    public function logSystemAccess($page, $userId = null) {
        $description = "Accessed page: $page";
        return $this->log('PAGE_ACCESS', 'system', null, [], ['page' => $page], $description, 'system_access', 'low');
    }

    /**
     * Get audit logs with filtering
     */
    public function getAuditLogs($filters = []) {
        try {
            $whereConditions = [];
            $params = [];

            // Build where conditions
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "al.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['action_type'])) {
                $whereConditions[] = "al.action_type = ?";
                $params[] = $filters['action_type'];
            }

            if (!empty($filters['table_name'])) {
                $whereConditions[] = "al.table_name = ?";
                $params[] = $filters['table_name'];
            }

            if (!empty($filters['module'])) {
                $whereConditions[] = "al.module = ?";
                $params[] = $filters['module'];
            }

            if (!empty($filters['severity'])) {
                $whereConditions[] = "al.severity = ?";
                $params[] = $filters['severity'];
            }

            if (!empty($filters['success'])) {
                $whereConditions[] = "al.success = ?";
                $params[] = $filters['success'] == 'true' ? 1 : 0;
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "al.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "al.created_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['record_id'])) {
                $whereConditions[] = "al.record_id = ?";
                $params[] = $filters['record_id'];
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            $orderBy = "ORDER BY al.created_at DESC";
            $limit = !empty($filters['limit']) ? "LIMIT " . (int)$filters['limit'] : "LIMIT 100";

            $query = "
                SELECT 
                    al.*,
                    u.first_name,
                    u.surname,
                    u.user_type
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                $whereClause
                $orderBy
                $limit
            ";

            $logs = $this->db->query($query, $params)->fetchAll();

            // Format logs for display
            foreach ($logs as &$log) {
                $log['user_name'] = $log['first_name'] ? $log['first_name'] . ' ' . $log['surname'] : 'System';
                $log['formatted_date'] = date('M j, Y H:i:s', strtotime($log['created_at']));
                $log['time_ago'] = $this->formatTimeAgo($log['created_at']);
                $log['severity_class'] = $this->getSeverityClass($log['severity']);
                $log['success_class'] = $log['success'] ? 'text-green-600' : 'text-red-600';
                
                // Parse JSON fields
                $log['old_values_decoded'] = json_decode($log['old_values'] ?? '[]', true);
                $log['new_values_decoded'] = json_decode($log['new_values'] ?? '[]', true);
                $log['additional_data_decoded'] = json_decode($log['additional_data'] ?? '[]', true);
            }

            return [
                'success' => true,
                'logs' => $logs,
                'total_count' => count($logs)
            ];

        } catch (Exception $e) {
            error_log("AuditLogger::getAuditLogs error: " . $e->getMessage());
            return [
                'success' => false,
                'logs' => [],
                'message' => 'Failed to fetch audit logs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats($dateRange = '30 days') {
        try {
            $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL $dateRange)";

            // Overall statistics
            $overallStatsQuery = "
                SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN success = TRUE THEN 1 ELSE 0 END) as successful_events,
                    SUM(CASE WHEN success = FALSE THEN 1 ELSE 0 END) as failed_events,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT DATE(created_at)) as active_days
                FROM audit_logs 
                WHERE $dateCondition
            ";

            $overallStats = $this->db->query($overallStatsQuery)->fetch();

            // Module breakdown
            $moduleStatsQuery = "
                SELECT 
                    module,
                    COUNT(*) as event_count,
                    SUM(CASE WHEN success = FALSE THEN 1 ELSE 0 END) as error_count
                FROM audit_logs 
                WHERE $dateCondition
                GROUP BY module
                ORDER BY event_count DESC
            ";

            $moduleStats = $this->db->query($moduleStatsQuery)->fetchAll();

            // Action type breakdown
            $actionStatsQuery = "
                SELECT 
                    action_type,
                    COUNT(*) as event_count
                FROM audit_logs 
                WHERE $dateCondition
                GROUP BY action_type
                ORDER BY event_count DESC
                LIMIT 10
            ";

            $actionStats = $this->db->query($actionStatsQuery)->fetchAll();

            // Top users by activity
            $userStatsQuery = "
                SELECT 
                    al.user_id,
                    u.first_name,
                    u.surname,
                    u.user_type,
                    COUNT(*) as activity_count
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE $dateCondition
                GROUP BY al.user_id
                ORDER BY activity_count DESC
                LIMIT 10
            ";

            $userStats = $this->db->query($userStatsQuery)->fetchAll();

            // Daily activity for chart
            $dailyActivityQuery = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as event_count,
                    SUM(CASE WHEN success = FALSE THEN 1 ELSE 0 END) as error_count
                FROM audit_logs 
                WHERE $dateCondition
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            ";

            $dailyActivity = $this->db->query($dailyActivityQuery)->fetchAll();

            return [
                'success' => true,
                'stats' => [
                    'overall' => $overallStats,
                    'modules' => $moduleStats,
                    'actions' => $actionStats,
                    'users' => $userStats,
                    'daily_activity' => array_reverse($dailyActivity) // Reverse for chronological order
                ]
            ];

        } catch (Exception $e) {
            error_log("AuditLogger::getAuditStats error: " . $e->getMessage());
            return [
                'success' => false,
                'stats' => [],
                'message' => 'Failed to fetch audit statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get audit trail for a specific record
     */
    public function getRecordAuditTrail($tableName, $recordId) {
        try {
            $query = "
                SELECT 
                    al.*,
                    u.first_name,
                    u.surname,
                    u.user_type
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = ? AND al.record_id = ?
                ORDER BY al.created_at DESC
            ";

            $logs = $this->db->query($query, [$tableName, $recordId])->fetchAll();

            // Format logs
            foreach ($logs as &$log) {
                $log['user_name'] = $log['first_name'] ? $log['first_name'] . ' ' . $log['surname'] : 'System';
                $log['formatted_date'] = date('M j, Y H:i:s', strtotime($log['created_at']));
                $log['old_values_decoded'] = json_decode($log['old_values'] ?? '[]', true);
                $log['new_values_decoded'] = json_decode($log['new_values'] ?? '[]', true);
                $log['changes'] = $this->getChanges($log['old_values_decoded'], $log['new_values_decoded']);
            }

            return [
                'success' => true,
                'trail' => $logs
            ];

        } catch (Exception $e) {
            error_log("AuditLogger::getRecordAuditTrail error: " . $e->getMessage());
            return [
                'success' => false,
                'trail' => [],
                'message' => 'Failed to fetch audit trail: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Export audit logs
     */
    public function exportAuditLogs($filters = [], $format = 'csv') {
        try {
            $result = $this->getAuditLogs(array_merge($filters, ['limit' => 10000]));
            
            if (!$result['success']) {
                return $result;
            }

            $logs = $result['logs'];
            $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.' . $format;

            if ($format === 'csv') {
                return $this->exportToCsv($logs, $filename);
            } elseif ($format === 'json') {
                return $this->exportToJson($logs, $filename);
            }

            return ['success' => false, 'message' => 'Unsupported export format'];

        } catch (Exception $e) {
            error_log("AuditLogger::exportAuditLogs error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to export audit logs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Helper methods
     */
    private function getClientIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? null;
        }
    }

    private function formatTimeAgo($datetime) {
        $time = time() - strtotime($datetime);

        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' minutes ago';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' hours ago';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' days ago';
        } else {
            return date('M j, Y', strtotime($datetime));
        }
    }

    private function getSeverityClass($severity) {
        $classes = [
            'low' => 'text-green-600 bg-green-100',
            'medium' => 'text-yellow-600 bg-yellow-100',
            'high' => 'text-red-600 bg-red-100',
            'critical' => 'text-red-800 bg-red-200'
        ];

        return $classes[$severity] ?? $classes['medium'];
    }

    private function getChanges($oldValues, $newValues) {
        $changes = [];

        if (empty($oldValues) && !empty($newValues)) {
            // Record creation
            foreach ($newValues as $field => $value) {
                $changes[] = [
                    'field' => $field,
                    'type' => 'created',
                    'old_value' => null,
                    'new_value' => $value
                ];
            }
        } elseif (!empty($oldValues) && !empty($newValues)) {
            // Record modification
            foreach ($newValues as $field => $newValue) {
                $oldValue = $oldValues[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[] = [
                        'field' => $field,
                        'type' => 'modified',
                        'old_value' => $oldValue,
                        'new_value' => $newValue
                    ];
                }
            }
        }

        return $changes;
    }

    private function exportToCsv($logs, $filename) {
        $csvData = [];
        $csvData[] = ['Date', 'User', 'Action', 'Table', 'Record ID', 'Description', 'Module', 'Severity', 'Success', 'IP Address'];

        foreach ($logs as $log) {
            $csvData[] = [
                $log['formatted_date'],
                $log['user_name'],
                $log['action_type'],
                $log['table_name'],
                $log['record_id'],
                $log['description'],
                $log['module'],
                $log['severity'],
                $log['success'] ? 'Yes' : 'No',
                $log['ip_address']
            ];
        }

        return [
            'success' => true,
            'data' => $csvData,
            'filename' => $filename,
            'format' => 'csv'
        ];
    }

    private function exportToJson($logs, $filename) {
        return [
            'success' => true,
            'data' => $logs,
            'filename' => $filename,
            'format' => 'json'
        ];
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs($daysToKeep = 365) {
        try {
            $cleanupQuery = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $this->db->query($cleanupQuery, [$daysToKeep]);

            return ['success' => true, 'message' => "Cleaned up audit logs older than $daysToKeep days"];

        } catch (Exception $e) {
            error_log("AuditLogger::cleanupOldLogs error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cleanup old logs'];
        }
    }
}
?>