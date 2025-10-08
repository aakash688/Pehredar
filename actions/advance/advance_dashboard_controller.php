<?php
// actions/advance/advance_dashboard_controller.php - Controller for advance dashboard data

header('Content-Type: application/json');
require_once __DIR__ . '/../../helpers/AdvanceTracker.php';
require_once __DIR__ . '/../../helpers/AuditLogger.php';

try {
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $userId = $_SESSION['user_id'];
    $advanceTracker = new \Helpers\AdvanceTracker();
    $auditLogger = new \Helpers\AuditLogger($userId);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'summary';
    $response = ['success' => false, 'message' => '', 'data' => []];

    switch ($action) {
        case 'summary':
            // Get advance summary for dashboard widget
            $summary = $advanceTracker->getAdvanceSummary();
            $response = [
                'success' => true,
                'data' => $summary
            ];
            
            // Log dashboard access
            $auditLogger->logSystemAccess('advance_dashboard_summary', $userId);
            break;

        case 'employees_with_advances':
            // Get employees with their advance status
            $filters = [
                'advance_status' => $_GET['advance_status'] ?? null,
                'priority_level' => $_GET['priority_level'] ?? null,
                'emergency_only' => isset($_GET['emergency_only']) ? (bool)$_GET['emergency_only'] : false,
                'overdue_only' => isset($_GET['overdue_only']) ? (bool)$_GET['overdue_only'] : false
            ];

            $employees = $advanceTracker->getEmployeesWithAdvanceStatus($filters);
            
            $response = [
                'success' => true,
                'data' => [
                    'employees' => $employees,
                    'total_count' => count($employees),
                    'filters_applied' => array_filter($filters)
                ]
            ];
            
            // Log access
            $auditLogger->logSystemAccess('advance_employees_list', $userId);
            break;

        case 'advance_history':
            // Get advance history for a specific employee
            $targetUserId = (int)($_GET['user_id'] ?? 0);
            
            if (!$targetUserId) {
                throw new Exception('User ID is required');
            }

            $history = $advanceTracker->getAdvanceHistory($targetUserId);
            
            $response = [
                'success' => true,
                'data' => [
                    'user_id' => $targetUserId,
                    'advances' => $history,
                    'total_advances' => count($history)
                ]
            ];
            
            // Log access
            $auditLogger->log('VIEW_ADVANCE_HISTORY', 'advance_salary_enhanced', null, [], 
                            ['target_user_id' => $targetUserId], 
                            "Viewed advance history for user ID: $targetUserId", 
                            'advance_management');
            break;

        case 'create_advance':
            // Create a new advance request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $requestData = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['user_id', 'total_advance_amount', 'monthly_deduction_amount', 'repayment_months', 'start_date'];
            foreach ($requiredFields as $field) {
                if (empty($requestData[$field])) {
                    throw new Exception("$field is required");
                }
            }

            // Add created_by and approved_by
            $requestData['created_by'] = $userId;
            $requestData['approved_by'] = $userId; // Admin is creating and approving

            $result = $advanceTracker->createAdvanceRequest($requestData);
            
            if ($result['success']) {
                // Log successful creation
                $auditLogger->logAdvanceOperation('CREATE_ADVANCE', $result['advance_id'], [], $requestData, 
                                                "Created new advance request: {$result['advance_request_id']}");
                
                $response = [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'advance_id' => $result['advance_id'],
                        'advance_request_id' => $result['advance_request_id']
                    ]
                ];
            } else {
                // Log failed creation
                $auditLogger->logFailure('CREATE_ADVANCE', 'advance_salary_enhanced', null, $result['message'], 'advance_management');
                throw new Exception($result['message']);
            }
            break;

        case 'update_advance_status':
            // Update advance status (suspend/reactivate/cancel)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $requestData = json_decode(file_get_contents('php://input'), true);
            
            if (empty($requestData['advance_id']) || empty($requestData['new_status'])) {
                throw new Exception('Advance ID and new status are required');
            }

            $advanceId = (int)$requestData['advance_id'];
            $newStatus = $requestData['new_status'];
            $reason = $requestData['reason'] ?? null;

            // Get current advance data for logging
            $currentAdvance = $advanceTracker->getAdvanceHistory($requestData['user_id']);
            $advance = array_filter($currentAdvance, function($a) use ($advanceId) {
                return $a['id'] == $advanceId;
            });
            $advance = reset($advance);

            if (!$advance) {
                throw new Exception('Advance not found');
            }

            // Update status based on new status
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'suspended') {
                $updateData['suspended_at'] = date('Y-m-d H:i:s');
                $updateData['suspension_reason'] = $reason;
            } elseif ($newStatus === 'active') {
                $updateData['suspended_at'] = null;
                $updateData['suspension_reason'] = null;
            }

            // Update in database
            require_once __DIR__ . '/../../helpers/database.php';
            $db = new Database();
            
            $updateFields = [];
            $params = [];
            foreach ($updateData as $field => $value) {
                if ($value === null) {
                    $updateFields[] = "$field = NULL";
                } else {
                    $updateFields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            $params[] = $advanceId;

            $updateQuery = "UPDATE advance_salary_enhanced SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $db->query($updateQuery, $params);

            // Log the update
            $auditLogger->logAdvanceOperation('UPDATE_ADVANCE_STATUS', $advanceId, $advance, $updateData, 
                                            "Updated advance status to: $newStatus" . ($reason ? " - Reason: $reason" : ""));

            $response = [
                'success' => true,
                'message' => "Advance status updated to $newStatus successfully",
                'data' => [
                    'advance_id' => $advanceId,
                    'new_status' => $newStatus
                ]
            ];
            break;

        case 'advance_analytics':
            // Get advance analytics data
            require_once __DIR__ . '/../../helpers/database.php';
            $db = new Database();

            // Get analytics data
            $analyticsQuery = "
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(total_advance_amount) as total_amount,
                    SUM(remaining_balance) as total_remaining,
                    AVG(remaining_balance) as avg_remaining
                FROM advance_salary_enhanced 
                GROUP BY status
            ";
            $statusAnalytics = $db->query($analyticsQuery)->fetchAll();

            $priorityAnalyticsQuery = "
                SELECT 
                    priority_level,
                    COUNT(*) as count,
                    SUM(remaining_balance) as total_remaining
                FROM advance_salary_enhanced 
                WHERE status = 'active'
                GROUP BY priority_level
            ";
            $priorityAnalytics = $db->query($priorityAnalyticsQuery)->fetchAll();

            $monthlyTrendsQuery = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_advances,
                    SUM(total_advance_amount) as total_granted
                FROM advance_salary_enhanced 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ";
            $monthlyTrends = $db->query($monthlyTrendsQuery)->fetchAll();

            $response = [
                'success' => true,
                'data' => [
                    'status_breakdown' => $statusAnalytics,
                    'priority_breakdown' => $priorityAnalytics,
                    'monthly_trends' => $monthlyTrends
                ]
            ];
            
            // Log analytics access
            $auditLogger->logSystemAccess('advance_analytics', $userId);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    error_log("Advance Dashboard Controller Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ];
    
    // Log the error if logger is available
    if (isset($auditLogger)) {
        $auditLogger->logFailure('ADVANCE_DASHBOARD_ERROR', 'advance_salary_enhanced', null, $e->getMessage(), 'advance_management');
    }
}

echo json_encode($response);
?>