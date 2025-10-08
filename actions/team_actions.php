<?php
require_once __DIR__ . '/team_controller.php';

// Set appropriate headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Turn off error display and ensure a clean buffer before emitting JSON
ini_set('display_errors', 0);
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

$action = $_GET['action'] ?? '';
$controller = new TeamController();

// Handle JSON input with error checking
$input = file_get_contents('php://input');
$postData = [];

if ($input !== false && strlen(trim($input)) > 0) {
    $postData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input: ' . json_last_error_msg(),
            'input' => substr($input, 0, 200)
        ]);
        exit;
    }
}

// Handle both AJAX and form submissions
if (empty($postData)) {
    // Check if this is a form POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // This is a regular form submission
        session_start();
        
        switch ($action) {
            case 'create_team':
                $result = $controller->createTeam($_POST);
                if ($result['success']) {
                    $_SESSION['team_message'] = [
                        'type' => 'success',
                        'text' => $result['message'] ?? 'Team created successfully!'
                    ];
                    header('Location: ../index.php?page=assign-team');
                } else {
                    $_SESSION['team_message'] = [
                        'type' => 'error',
                        'text' => $result['error'] ?? 'Error creating team'
                    ];
                    $_SESSION['form_errors'] = $result['field_errors'] ?? [];
                    $_SESSION['form_data'] = $_POST;
                    header('Location: ../index.php?page=create-team');
                }
                exit;
                
            case 'update_team':
                $teamId = $_POST['team_id'] ?? null;
                $result = $controller->updateTeam($_POST);
                if ($result['success']) {
                    $_SESSION['team_message'] = [
                        'type' => 'success',
                        'text' => $result['message'] ?? 'Team updated successfully!'
                    ];
                    header('Location: ../index.php?page=assign-team');
                } else {
                    $_SESSION['team_message'] = [
                        'type' => 'error',
                        'text' => $result['error'] ?? 'Error updating team'
                    ];
                    $_SESSION['form_errors'] = $result['field_errors'] ?? [];
                    $_SESSION['form_data'] = $_POST;
                    header('Location: ../index.php?page=edit-team&id=' . $teamId);
                }
                exit;
        }
    }
}

switch ($action) {
    case 'get_team_details':
        $teamId = $_GET['team_id'] ?? null;
        if (!$teamId) {
            echo json_encode(['success' => false, 'error' => 'Team ID is required.']);
            exit;
        }
        $details = $controller->getTeamDetails($teamId);
        if ($details) {
            echo json_encode(['success' => true, 'team' => $details]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Team not found.']);
        }
        break;

    case 'create_team':
        try {
            // Validate required fields
            if (empty($postData['team_name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Team name is required.',
                    'debug' => ['postData' => $postData]
                ]);
                exit;
            }
            
            if (empty($postData['supervisor_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Supervisor is required.',
                    'debug' => ['postData' => $postData]
                ]);
                exit;
            }
            
            $result = $controller->createTeam($postData);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage(),
                'debug' => [
                    'exception' => $e->getTraceAsString(),
                    'postData' => $postData
                ]
            ]);
        }
        break;

    case 'update_team':
        try {
            // Validate required fields
            if (empty($postData['team_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Team ID is required for updates.',
                    'debug' => ['postData' => $postData]
                ]);
                exit;
            }
            
            if (empty($postData['team_name'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Team name is required.',
                    'debug' => ['postData' => $postData]
                ]);
                exit;
            }
            
            if (empty($postData['supervisor_id'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Supervisor is required.',
                    'debug' => ['postData' => $postData]
                ]);
                exit;
            }
            
            $result = $controller->updateTeam($postData);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage(),
                'debug' => [
                    'exception' => $e->getTraceAsString(),
                    'postData' => $postData
                ]
            ]);
        }
        break;

    case 'delete_team':
        try {
            // Specifically handle the case where team_id is in the request body
            $teamId = $postData['team_id'] ?? null;
            
            if (!$teamId) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Team ID is required.',
                    'debug' => [
                        'postData' => $postData,
                        'action' => $action,
                        'method' => $_SERVER['REQUEST_METHOD']
                    ]
                ]);
                exit;
            }
            
            // Validate team ID is numeric
            if (!is_numeric($teamId)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid team ID format.',
                    'debug' => ['teamId' => $teamId]
                ]);
                exit;
            }
            
            // Use getTeamDetails to validate team existence
            $teamDetails = $controller->getTeamDetails($teamId);
            if (!$teamDetails) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Team does not exist.',
                    'debug' => [
                        'teamId' => $teamId,
                        'postData' => $postData
                    ]
                ]);
                exit;
            }
            
            $result = $controller->deleteTeam($teamId);
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage(),
                'debug' => [
                    'exception' => $e->getTraceAsString()
                ]
            ]);
        }
        break;

    case 'remove_member':
        $teamId = $postData['team_id'] ?? null;
        $userId = $postData['user_id'] ?? null;
        if (!$teamId || !$userId) {
            echo json_encode(['success' => false, 'error' => 'Team ID and User ID are required.']);
            exit;
        }
        $result = $controller->removeMember($teamId, $userId);
        echo json_encode($result);
        break;

    case 'migrate_member':
        $userId = $postData['user_id'] ?? null;
        $fromTeamId = $postData['from_team_id'] ?? null;
        $toTeamId = $postData['to_team_id'] ?? null;
        if (!$userId || !$fromTeamId || !$toTeamId) {
            echo json_encode(['success' => false, 'error' => 'User ID, From Team ID, and To Team ID are required.']);
            exit;
        }
        $result = $controller->migrateMember($userId, $fromTeamId, $toTeamId);
        echo json_encode($result);
        break;

    case 'get_all_teams':
        $teams = $controller->getAllTeams();
        echo json_encode(['success' => true, 'teams' => $teams]);
        break;
        
    case 'get_team_stats':
        $stats = $controller->getTeamStats();
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action specified.', 'action' => $action]);
        break;
}

// Ensure we always end with a clean exit
exit; 