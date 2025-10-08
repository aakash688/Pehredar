<?php
// actions/bulk/bulk_operations_controller.php - Controller for bulk payroll operations

header('Content-Type: application/json');
require_once __DIR__ . '/../../helpers/BulkOperationManager.php';
require_once __DIR__ . '/../../helpers/AuditLogger.php';
require_once __DIR__ . '/../../helpers/NotificationManager.php';

try {
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    $userId = $_SESSION['user_id'];
    $bulkManager = new \Helpers\BulkOperationManager();
    $auditLogger = new \Helpers\AuditLogger($userId);
    $notificationManager = new \Helpers\NotificationManager();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => '', 'data' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }

    $requestData = json_decode(file_get_contents('php://input'), true);
    
    if (!$requestData) {
        throw new Exception('Invalid JSON data');
    }

    switch ($action) {
        case 'preview_operation':
            // Preview bulk operation before applying
            $previewResult = $bulkManager->previewBulkOperation($requestData);
            
            if ($previewResult['success']) {
                $response = [
                    'success' => true,
                    'data' => $previewResult['preview'],
                    'message' => 'Preview generated successfully'
                ];
                
                // Log preview request
                $auditLogger->log('BULK_OPERATION_PREVIEW', 'multiple_tables', null, [], $requestData, 
                                "Generated preview for bulk operation: {$requestData['operation_type']}", 
                                'bulk_operations');
            } else {
                throw new Exception($previewResult['message']);
            }
            break;

        case 'apply_bulk_bonus':
            // Apply bulk bonuses
            if (empty($requestData['employees']) || empty($requestData['bonus_type']) || empty($requestData['amount'])) {
                throw new Exception('Employees list, bonus type, and amount are required');
            }

            $requestData['created_by'] = $userId;
            $result = $bulkManager->applyBulkBonus($requestData);
            
            if ($result['success']) {
                // Send notifications to affected employees
                foreach ($requestData['employees'] as $employeeId) {
                    $bonusData = [
                        'id' => 'bulk_' . $result['bulk_operation_id'],
                        'amount' => is_numeric($requestData['amount']) ? $requestData['amount'] : 'calculated',
                        'bonus_type' => $requestData['bonus_type'],
                        'month' => $requestData['month'],
                        'year' => $requestData['year']
                    ];
                    
                    $notificationManager->createBonusNotification($employeeId, $bonusData, $userId);
                }

                $response = [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'bulk_operation_id' => $result['bulk_operation_id'],
                        'success_count' => $result['success_count'],
                        'error_count' => $result['error_count'],
                        'errors' => $result['errors']
                    ]
                ];
                
                // Log successful bulk operation
                $auditLogger->logBulkOperation('BULK_BONUS_APPLY', $result['success_count'], $requestData, 
                                             "Applied bulk bonus to {$result['success_count']} employees");
            } else {
                // Log failed operation
                $auditLogger->logFailure('BULK_BONUS_APPLY', 'bonus_records', null, $result['message'], 'bulk_operations');
                throw new Exception($result['message']);
            }
            break;

        case 'apply_bulk_deduction':
            // Apply bulk deductions
            if (empty($requestData['employees']) || empty($requestData['deduction_type_id']) || empty($requestData['amount'])) {
                throw new Exception('Employees list, deduction type, and amount are required');
            }

            $requestData['created_by'] = $userId;
            $requestData['approved_by'] = $userId; // Admin is applying and approving
            $result = $bulkManager->applyBulkDeduction($requestData);
            
            if ($result['success']) {
                // Send notifications to affected employees
                foreach ($requestData['employees'] as $employeeId) {
                    $deductionData = [
                        'id' => 'bulk_' . $result['bulk_operation_id'],
                        'amount' => is_numeric($requestData['amount']) ? $requestData['amount'] : 'calculated',
                        'reason' => $requestData['reason'] ?? 'Bulk deduction applied',
                        'month' => $requestData['month'],
                        'year' => $requestData['year']
                    ];
                    
                    $notificationManager->createDeductionNotification($employeeId, $deductionData, $userId);
                }

                $response = [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'bulk_operation_id' => $result['bulk_operation_id'],
                        'success_count' => $result['success_count'],
                        'error_count' => $result['error_count'],
                        'errors' => $result['errors']
                    ]
                ];
                
                // Log successful bulk operation
                $auditLogger->logBulkOperation('BULK_DEDUCTION_APPLY', $result['success_count'], $requestData, 
                                             "Applied bulk deduction to {$result['success_count']} employees");
            } else {
                // Log failed operation
                $auditLogger->logFailure('BULK_DEDUCTION_APPLY', 'employee_deductions', null, $result['message'], 'bulk_operations');
                throw new Exception($result['message']);
            }
            break;

        case 'bulk_adjust_advances':
            // Bulk adjust advance repayments
            if (empty($requestData['advance_ids']) || empty($requestData['adjustment_type'])) {
                throw new Exception('Advance IDs and adjustment type are required');
            }

            $requestData['created_by'] = $userId;
            $result = $bulkManager->bulkAdjustAdvances($requestData);
            
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'bulk_operation_id' => $result['bulk_operation_id'],
                        'success_count' => $result['success_count'],
                        'error_count' => $result['error_count'],
                        'errors' => $result['errors']
                    ]
                ];
                
                // Log successful bulk operation
                $auditLogger->logBulkOperation('BULK_ADVANCE_ADJUST', $result['success_count'], $requestData, 
                                             "Bulk adjusted {$result['success_count']} advances");
            } else {
                // Log failed operation
                $auditLogger->logFailure('BULK_ADVANCE_ADJUST', 'advance_salary_enhanced', null, $result['message'], 'bulk_operations');
                throw new Exception($result['message']);
            }
            break;

        case 'get_deduction_types':
            // Get available deduction types for bulk operations
            require_once __DIR__ . '/../../helpers/database.php';
            $db = new Database();
            
            $deductionTypesQuery = "
                SELECT 
                    id, name, description, default_amount, is_percentage, 
                    max_amount, category, requires_approval
                FROM deduction_types 
                WHERE is_active = TRUE 
                ORDER BY category, name
            ";
            
            $deductionTypes = $db->query($deductionTypesQuery)->fetchAll();
            
            $response = [
                'success' => true,
                'data' => [
                    'deduction_types' => $deductionTypes
                ]
            ];
            
            // Log access
            $auditLogger->logSystemAccess('deduction_types_list', $userId);
            break;

        case 'get_employees_for_bulk':
            // Get employees list for bulk operations with filtering
            require_once __DIR__ . '/../../helpers/database.php';
            $db = new Database();
            
            $whereConditions = [];
            $params = [];
            
            // Apply filters
            if (!empty($requestData['user_type'])) {
                $whereConditions[] = "user_type = ?";
                $params[] = $requestData['user_type'];
            }
            
            if (!empty($requestData['salary_range_min'])) {
                $whereConditions[] = "salary >= ?";
                $params[] = $requestData['salary_range_min'];
            }
            
            if (!empty($requestData['salary_range_max'])) {
                $whereConditions[] = "salary <= ?";
                $params[] = $requestData['salary_range_max'];
            }
            
            if (!empty($requestData['exclude_advance_users'])) {
                $whereConditions[] = "id NOT IN (SELECT user_id FROM advance_salary_enhanced WHERE status = 'active')";
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $employeesQuery = "
                SELECT 
                    u.id, 
                    u.first_name, 
                    u.surname, 
                    u.user_type, 
                    u.salary,
                    ase.id as has_active_advance,
                    ase.remaining_balance as advance_balance
                FROM users u
                LEFT JOIN advance_salary_enhanced ase ON u.id = ase.user_id AND ase.status = 'active'
                $whereClause
                ORDER BY u.first_name, u.surname
            ";
            
            $employees = $db->query($employeesQuery, $params)->fetchAll();
            
            // Add calculated fields
            foreach ($employees as &$employee) {
                $employee['full_name'] = $employee['first_name'] . ' ' . $employee['surname'];
                $employee['has_advance'] = !empty($employee['has_active_advance']);
                $employee['formatted_salary'] = number_format($employee['salary'], 2);
            }
            
            $response = [
                'success' => true,
                'data' => [
                    'employees' => $employees,
                    'total_count' => count($employees),
                    'filters_applied' => array_filter($requestData)
                ]
            ];
            
            // Log access
            $auditLogger->logSystemAccess('bulk_employees_list', $userId);
            break;

        case 'get_bulk_operation_history':
            // Get history of bulk operations
            require_once __DIR__ . '/../../helpers/database.php';
            $db = new Database();
            
            $limit = (int)($requestData['limit'] ?? 50);
            $offset = (int)($requestData['offset'] ?? 0);
            
            $historyQuery = "
                SELECT 
                    al.id,
                    al.action_type,
                    al.description,
                    al.additional_data,
                    al.created_at,
                    al.success,
                    u.first_name,
                    u.surname
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module = 'bulk_operations'
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $history = $db->query($historyQuery, [$limit, $offset])->fetchAll();
            
            // Format history for display
            foreach ($history as &$record) {
                $record['user_name'] = $record['first_name'] . ' ' . $record['surname'];
                $record['formatted_date'] = date('M j, Y H:i:s', strtotime($record['created_at']));
                $additionalData = json_decode($record['additional_data'], true);
                $record['success_count'] = $additionalData['success_count'] ?? 0;
                $record['error_count'] = $additionalData['error_count'] ?? 0;
                $record['bulk_operation_id'] = $additionalData['bulk_operation_id'] ?? '';
            }
            
            $response = [
                'success' => true,
                'data' => [
                    'history' => $history,
                    'has_more' => count($history) === $limit
                ]
            ];
            
            // Log access
            $auditLogger->logSystemAccess('bulk_operation_history', $userId);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    error_log("Bulk Operations Controller Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ];
    
    // Log the error if logger is available
    if (isset($auditLogger)) {
        $auditLogger->logFailure('BULK_OPERATION_ERROR', 'multiple_tables', null, $e->getMessage(), 'bulk_operations');
    }
}

echo json_encode($response);
?>