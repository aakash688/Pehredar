<?php
// actions/dashboard/dashboard_widgets_controller.php - Controller for dashboard widgets data

header('Content-Type: application/json');
require_once __DIR__ . '/../../helpers/AdvanceTracker.php';
require_once __DIR__ . '/../../helpers/AuditLogger.php';
require_once __DIR__ . '/../../helpers/NotificationManager.php';
require_once __DIR__ . '/../../helpers/database.php';

try {
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $userId = $_SESSION['user_id'];
    $advanceTracker = new \Helpers\AdvanceTracker();
    $auditLogger = new \Helpers\AuditLogger($userId);
    $notificationManager = new \Helpers\NotificationManager();
    $db = new Database();

    $action = $_GET['action'] ?? 'all_widgets';
    $response = ['success' => false, 'message' => '', 'data' => []];

    switch ($action) {
        case 'all_widgets':
            // Get all dashboard widget data
            $widgetData = [];

            // 1. Advance Summary Widget
            $advanceSummary = $advanceTracker->getAdvanceSummary();
            $widgetData['advance_summary'] = $advanceSummary;

            // 2. Payroll Overview Widget
            $currentMonth = date('Y-m');
            $previousMonth = date('Y-m', strtotime('-1 month'));

            $payrollOverviewQuery = "
                SELECT 
                    COUNT(*) as total_employees_with_salary,
                    SUM(CASE WHEN sr.month = ? THEN 1 ELSE 0 END) as current_month_generated,
                    SUM(CASE WHEN sr.month = ? THEN 1 ELSE 0 END) as previous_month_generated,
                    SUM(CASE WHEN sr.month = ? AND sr.disbursement_status = 'disbursed' THEN 1 ELSE 0 END) as current_month_disbursed,
                    SUM(CASE WHEN sr.month = ? THEN sr.net_salary ELSE 0 END) as current_month_total_amount,
                    SUM(CASE WHEN sr.month = ? THEN sr.additional_bonuses ELSE 0 END) as current_month_bonuses,
                    SUM(CASE WHEN sr.month = ? THEN sr.total_deductions ELSE 0 END) as current_month_deductions
                FROM salary_records sr
                WHERE sr.month IN (?, ?)
            ";
            
            $payrollParams = [$currentMonth, $previousMonth, $currentMonth, $currentMonth, $currentMonth, $currentMonth, $currentMonth, $previousMonth];
            $payrollOverview = $db->query($payrollOverviewQuery, $payrollParams)->fetch();

            // Get total employees
            $totalEmployeesQuery = "SELECT COUNT(*) as total_employees FROM users WHERE salary > 0";
            $totalEmployees = $db->query($totalEmployeesQuery)->fetch()['total_employees'];

            $widgetData['payroll_overview'] = [
                'total_employees' => (int)$totalEmployees,
                'current_month_generated' => (int)($payrollOverview['current_month_generated'] ?? 0),
                'previous_month_generated' => (int)($payrollOverview['previous_month_generated'] ?? 0),
                'current_month_disbursed' => (int)($payrollOverview['current_month_disbursed'] ?? 0),
                'current_month_total_amount' => (float)($payrollOverview['current_month_total_amount'] ?? 0),
                'current_month_bonuses' => (float)($payrollOverview['current_month_bonuses'] ?? 0),
                'current_month_deductions' => (float)($payrollOverview['current_month_deductions'] ?? 0),
                'generation_percentage' => $totalEmployees > 0 ? round(($payrollOverview['current_month_generated'] / $totalEmployees) * 100, 1) : 0,
                'disbursement_percentage' => $payrollOverview['current_month_generated'] > 0 ? round(($payrollOverview['current_month_disbursed'] / $payrollOverview['current_month_generated']) * 100, 1) : 0
            ];

            // 3. Recent Activities Widget
            $recentActivitiesQuery = "
                SELECT 
                    al.action_type,
                    al.description,
                    al.module,
                    al.created_at,
                    al.severity,
                    al.success,
                    u.first_name,
                    u.surname
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module IN ('payroll', 'advance_management', 'bonus_management', 'deduction_management', 'bulk_operations')
                ORDER BY al.created_at DESC
                LIMIT 10
            ";
            
            $recentActivities = $db->query($recentActivitiesQuery)->fetchAll();
            
            foreach ($recentActivities as &$activity) {
                $activity['user_name'] = $activity['first_name'] . ' ' . $activity['surname'];
                $activity['time_ago'] = $this->formatTimeAgo($activity['created_at']);
                $activity['icon'] = $this->getActivityIcon($activity['module'], $activity['action_type']);
                $activity['color_class'] = $this->getActivityColorClass($activity['module'], $activity['success']);
            }

            $widgetData['recent_activities'] = $recentActivities;

            // 4. Notifications Summary Widget
            $notificationStats = $notificationManager->getNotificationStats($userId);
            $widgetData['notifications'] = $notificationStats['success'] ? $notificationStats['stats'] : [];

            // 5. Employee Status Distribution Widget
            $employeeStatusQuery = "
                SELECT 
                    u.user_type,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN ase.id IS NOT NULL THEN 1 ELSE 0 END) as with_advances,
                    SUM(CASE WHEN sr.id IS NOT NULL THEN 1 ELSE 0 END) as with_current_salary,
                    AVG(u.salary) as avg_salary
                FROM users u
                LEFT JOIN advance_salary_enhanced ase ON u.id = ase.user_id AND ase.status = 'active'
                LEFT JOIN salary_records sr ON u.id = sr.user_id AND sr.month = ?
                WHERE u.salary > 0
                GROUP BY u.user_type
                ORDER BY total_count DESC
            ";
            
            $employeeStatus = $db->query($employeeStatusQuery, [$currentMonth])->fetchAll();
            $widgetData['employee_status'] = $employeeStatus;

            // 6. Financial Overview Widget
            $financialOverviewQuery = "
                SELECT 
                    SUM(CASE WHEN sr.month = ? THEN sr.gross_salary ELSE 0 END) as current_gross_total,
                    SUM(CASE WHEN sr.month = ? THEN sr.net_salary ELSE 0 END) as current_net_total,
                    SUM(CASE WHEN sr.month = ? THEN sr.additional_bonuses ELSE 0 END) as current_bonuses,
                    SUM(CASE WHEN sr.month = ? THEN sr.total_deductions ELSE 0 END) as current_deductions,
                    SUM(CASE WHEN sr.month = ? THEN sr.advance_deduction_amount ELSE 0 END) as current_advance_deductions,
                    SUM(CASE WHEN sr.month = ? THEN sr.net_salary ELSE 0 END) as previous_net_total
                FROM salary_records sr
                WHERE sr.month IN (?, ?)
            ";
            
            $financialParams = [$currentMonth, $currentMonth, $currentMonth, $currentMonth, $currentMonth, $previousMonth, $currentMonth, $previousMonth];
            $financialOverview = $db->query($financialOverviewQuery, $financialParams)->fetch();

            // Calculate percentage changes
            $currentNetTotal = (float)($financialOverview['current_net_total'] ?? 0);
            $previousNetTotal = (float)($financialOverview['previous_net_total'] ?? 0);
            $netChangePercentage = $previousNetTotal > 0 ? round((($currentNetTotal - $previousNetTotal) / $previousNetTotal) * 100, 1) : 0;

            $widgetData['financial_overview'] = [
                'current_gross_total' => (float)($financialOverview['current_gross_total'] ?? 0),
                'current_net_total' => $currentNetTotal,
                'current_bonuses' => (float)($financialOverview['current_bonuses'] ?? 0),
                'current_deductions' => (float)($financialOverview['current_deductions'] ?? 0),
                'current_advance_deductions' => (float)($financialOverview['current_advance_deductions'] ?? 0),
                'previous_net_total' => $previousNetTotal,
                'net_change_percentage' => $netChangePercentage,
                'net_change_direction' => $netChangePercentage >= 0 ? 'up' : 'down'
            ];

            // 7. Urgent Actions Widget
            $urgentActions = [];
            
            // Overdue advances
            $overdueAdvancesQuery = "
                SELECT COUNT(*) as count 
                FROM advance_salary_enhanced 
                WHERE status = 'active' AND expected_completion_date < CURDATE()
            ";
            $overdueCount = $db->query($overdueAdvancesQuery)->fetch()['count'];
            if ($overdueCount > 0) {
                $urgentActions[] = [
                    'type' => 'overdue_advances',
                    'count' => (int)$overdueCount,
                    'message' => "$overdueCount overdue advance(s) need attention",
                    'priority' => 'high',
                    'action_url' => 'index.php?page=advance-management&filter=overdue'
                ];
            }

            // Pending salary disbursements
            $pendingDisbursementsQuery = "
                SELECT COUNT(*) as count 
                FROM salary_records 
                WHERE month = ? AND disbursement_status = 'pending'
            ";
            $pendingCount = $db->query($pendingDisbursementsQuery, [$currentMonth])->fetch()['count'];
            if ($pendingCount > 0) {
                $urgentActions[] = [
                    'type' => 'pending_disbursements',
                    'count' => (int)$pendingCount,
                    'message' => "$pendingCount salary(ies) pending disbursement",
                    'priority' => 'medium',
                    'action_url' => 'index.php?page=salary-records&status=pending'
                ];
            }

            // High priority unread notifications
            $urgentNotificationsQuery = "
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = FALSE AND priority IN ('high', 'urgent')
            ";
            $urgentNotifCount = $db->query($urgentNotificationsQuery, [$userId])->fetch()['count'];
            if ($urgentNotifCount > 0) {
                $urgentActions[] = [
                    'type' => 'urgent_notifications',
                    'count' => (int)$urgentNotifCount,
                    'message' => "$urgentNotifCount urgent notification(s)",
                    'priority' => 'high',
                    'action_url' => 'index.php?page=notifications&priority=high'
                ];
            }

            $widgetData['urgent_actions'] = $urgentActions;

            $response = [
                'success' => true,
                'data' => $widgetData,
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_duration' => 300 // 5 minutes
            ];
            
            // Log dashboard access
            $auditLogger->logSystemAccess('dashboard_widgets_all', $userId);
            break;

        case 'advance_summary':
            // Get only advance summary widget
            $advanceSummary = $advanceTracker->getAdvanceSummary();
            $response = [
                'success' => true,
                'data' => $advanceSummary
            ];
            break;

        case 'payroll_overview':
            // Get only payroll overview widget
            $currentMonth = date('Y-m');
            $payrollOverviewQuery = "
                SELECT 
                    COUNT(DISTINCT user_id) as total_employees_with_salary,
                    SUM(CASE WHEN disbursement_status = 'disbursed' THEN 1 ELSE 0 END) as disbursed_count,
                    SUM(net_salary) as total_amount,
                    SUM(additional_bonuses) as total_bonuses,
                    SUM(total_deductions) as total_deductions
                FROM salary_records 
                WHERE month = ?
            ";
            
            $payrollOverview = $db->query($payrollOverviewQuery, [$currentMonth])->fetch();
            
            $response = [
                'success' => true,
                'data' => $payrollOverview
            ];
            break;

        case 'recent_activities':
            // Get only recent activities widget
            $limit = (int)($_GET['limit'] ?? 10);
            $recentActivitiesQuery = "
                SELECT 
                    al.action_type,
                    al.description,
                    al.module,
                    al.created_at,
                    al.severity,
                    al.success,
                    u.first_name,
                    u.surname
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module IN ('payroll', 'advance_management', 'bonus_management', 'deduction_management', 'bulk_operations')
                ORDER BY al.created_at DESC
                LIMIT ?
            ";
            
            $recentActivities = $db->query($recentActivitiesQuery, [$limit])->fetchAll();
            
            foreach ($recentActivities as &$activity) {
                $activity['user_name'] = $activity['first_name'] . ' ' . $activity['surname'];
                $activity['time_ago'] = $this->formatTimeAgo($activity['created_at']);
                $activity['icon'] = $this->getActivityIcon($activity['module'], $activity['action_type']);
                $activity['color_class'] = $this->getActivityColorClass($activity['module'], $activity['success']);
            }
            
            $response = [
                'success' => true,
                'data' => $recentActivities
            ];
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    error_log("Dashboard Widgets Controller Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ];
    
    // Log the error if logger is available
    if (isset($auditLogger)) {
        $auditLogger->logFailure('DASHBOARD_WIDGET_ERROR', 'dashboard', null, $e->getMessage(), 'dashboard');
    }
}

// Helper methods as functions since this is a procedural script
function formatTimeAgo($datetime) {
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
        return date('M j', strtotime($datetime));
    }
}

function getActivityIcon($module, $actionType) {
    $icons = [
        'payroll' => '💰',
        'advance_management' => '💳',
        'bonus_management' => '🎁',
        'deduction_management' => '📉',
        'bulk_operations' => '📊'
    ];
    return $icons[$module] ?? '📝';
}

function getActivityColorClass($module, $success) {
    if (!$success) {
        return 'text-red-600 bg-red-50';
    }
    
    $colors = [
        'payroll' => 'text-green-600 bg-green-50',
        'advance_management' => 'text-blue-600 bg-blue-50',
        'bonus_management' => 'text-purple-600 bg-purple-50',
        'deduction_management' => 'text-orange-600 bg-orange-50',
        'bulk_operations' => 'text-indigo-600 bg-indigo-50'
    ];
    
    return $colors[$module] ?? 'text-gray-600 bg-gray-50';
}

echo json_encode($response);
?>