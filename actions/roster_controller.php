<?php
// Ensure no output before JSON response
ob_start();

require_once __DIR__ . '/../helpers/database.php';

// Error logging function
function log_error($message) {
    // Use PHP's default error logging mechanism
    error_log("Roster Controller Error: $message");
}

// Custom error handler
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    log_error("Error [$errno]: $errstr in $errfile on line $errline");
    
    // Clear any previous output
    ob_clean();
    
    // Send JSON response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "An internal server error occurred: $errstr",
        'error_code' => $errno
    ]);
    exit;
}

// Set custom error handler
set_error_handler('custom_error_handler');

// Check for overlapping date ranges for a guard. Optionally exclude a specific roster id
function find_overlaps(Database $db, int $guardId, string $startDate, string $endDate, ?int $excludeId = null) {
    $sql = "SELECT id, society_id, shift_id, team_id, assignment_start_date, assignment_end_date
            FROM roster
            WHERE guard_id = ?
              AND NOT (assignment_end_date < ? OR assignment_start_date > ?)";
    $params = [$guardId, $startDate, $endDate];
    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check for shift conflicts (same employee, same date, same shift)
function find_shift_conflicts(Database $db, int $guardId, int $shiftId, string $startDate, string $endDate, ?int $excludeId = null) {
    $sql = "SELECT id, society_id, shift_id, team_id, assignment_start_date, assignment_end_date
            FROM roster
            WHERE guard_id = ? 
              AND shift_id = ?
              AND NOT (assignment_end_date < ? OR assignment_start_date > ?)";
    $params = [$guardId, $shiftId, $startDate, $endDate];
    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_rosters() {
    try {
        $search = $_GET['search'] ?? '';
        $team_id = $_GET['team_id'] ?? null;
        $shift_id = $_GET['shift_id'] ?? null;
        $start_date = $_GET['start_date'] ?? null; // YYYY-MM-DD
        $end_date = $_GET['end_date'] ?? null;     // YYYY-MM-DD
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;

        $search = trim($search);
        $team_id = $team_id ? intval($team_id) : null;
        $shift_id = $shift_id ? intval($shift_id) : null;

        $offset = ($page - 1) * $per_page;

        $db = new Database();

        // Check if assignment date columns exist (for backward compatibility if migration not yet run)
        $hasAssignmentDates = false;
        try {
            $db->query("SELECT assignment_start_date, assignment_end_date FROM roster LIMIT 1");
            $hasAssignmentDates = true;
        } catch (Exception $e) {
            $hasAssignmentDates = false;
        }

        $assignmentInnerCols = $hasAssignmentDates ? ", r.assignment_start_date, r.assignment_end_date" : "";
        $assignmentOuterCols = $hasAssignmentDates ? ", assignment_start_date, assignment_end_date" : "";

        // Optional date range filter (only if both provided and columns exist)
        $dateFilterSql = "";
        $useDateFilter = false;
        if ($hasAssignmentDates && !empty($start_date) && !empty($end_date)) {
            $useDateFilter = true;
            $dateFilterSql = " AND NOT (r.assignment_end_date < ? OR r.assignment_start_date > ?)";
        }

        // Simplified query with subquery for pagination
        $query = "
            WITH RosterData AS (
                SELECT 
                    r.id, 
                    r.guard_id, 
                    r.society_id, 
                    r.shift_id, 
                    r.team_id,
                    CONCAT(u.first_name, ' ', u.surname) as guard_name,
                    t.team_name, 
                    sod.society_name, 
                    sm.shift_name, 
                    sm.start_time, 
                    sm.end_time" .
                    $assignmentInnerCols . " ,
                    ROW_NUMBER() OVER (
                        ORDER BY t.team_name, u.first_name
                    ) as row_num,
                    COUNT(*) OVER () as total_records
                FROM roster r
                JOIN users u ON r.guard_id = u.id
                JOIN teams t ON r.team_id = t.id
                JOIN society_onboarding_data sod ON r.society_id = sod.id
                JOIN shift_master sm ON r.shift_id = sm.id
                WHERE 1=1
                " . 
                (!empty($search) ? " AND (
                    CONCAT(u.first_name, ' ', u.surname) LIKE ? OR 
                    sod.society_name LIKE ? OR 
                    t.team_name LIKE ?
                )" : "") .
                ($team_id !== null ? " AND r.team_id = ?" : "") .
                ($shift_id !== null ? " AND r.shift_id = ?" : "") .
                ($useDateFilter ? $dateFilterSql : "") . "
            )
            SELECT 
                id, 
                guard_id, 
                society_id, 
                shift_id, 
                team_id,
                guard_name,
                team_name, 
                society_name, 
                shift_name, 
                start_time, 
                end_time" .
                $assignmentOuterCols . ",
                total_records
            FROM RosterData
            WHERE row_num BETWEEN ? AND ?
        ";
        
        $params = [];
        
        // Add search parameters
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Add team filter
        if ($team_id !== null) {
            $params[] = $team_id;
        }
        
        // Add shift filter
        if ($shift_id !== null) {
            $params[] = $shift_id;
        }
        
        // Add pagination parameters
        $params[] = $offset + 1;
        $params[] = $offset + $per_page;
        
        // Execute query
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rosters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total records from first row if results exist
        $total_records = $rosters ? $rosters[0]['total_records'] : 0;
        
        // Remove total_records from each row
        $rosters = array_map(function($roster) {
            unset($roster['total_records']);
            return $roster;
        }, $rosters);
        
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'rosters' => $rosters,
            'total_records' => $total_records,
            'current_page' => $page,
            'per_page' => $per_page
        ]);
        exit;
        
    } catch (Exception $e) {
        // Log the error
        log_error("Error in get_rosters: " . $e->getMessage());
        
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching rosters: ' . $e->getMessage()
        ]);
        exit;
    }
}

function get_guard_name($db, $guard_id) {
    try {
        $stmt = $db->prepare("SELECT first_name, surname FROM users WHERE id = ?");
        $stmt->execute([$guard_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['first_name'] && $result['surname']) {
            $guardName = $result['first_name'] . ' ' . $result['surname'];
        } elseif ($result && $result['first_name']) {
            $guardName = $result['first_name'];
        } else {
            $guardName = "Guard ID {$guard_id}";
        }
        
        return $guardName;
    } catch (Exception $e) {
        log_error("Error getting guard name for ID {$guard_id}: " . $e->getMessage());
        return "Guard ID {$guard_id}";
    }
}

// Single assignment with date range
function assign_roster() {
    try {
        // Read and parse input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
            exit;
        }

        $required = ['team_id','guard_id','society_id','shift_id','assignment_start_date','assignment_end_date'];
        foreach ($required as $key) {
            if (!isset($data[$key]) || $data[$key] === '') {
                ob_clean();
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing field: {$key}"]);
                exit;
            }
        }

        $team_id = intval($data['team_id']);
        $guard_id = intval($data['guard_id']);
        $society_id = intval($data['society_id']);
        $shift_id = intval($data['shift_id']);
        $start = $data['assignment_start_date'];
        $end = $data['assignment_end_date'];

        if ($start > $end) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
            exit;
        }

        $db = new Database();

        // Check shift conflicts in the provided range
        $conflicts = find_shift_conflicts($db, $guard_id, $shift_id, $start, $end);
        if (!empty($conflicts)) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Employee already assigned to this shift during these dates.']);
            exit;
        }

        // Insert assignment
        $stmt = $db->prepare(
            "INSERT INTO roster (guard_id, society_id, shift_id, team_id, assignment_start_date, assignment_end_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$guard_id, $society_id, $shift_id, $team_id, $start, $end]);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Roster assigned successfully']);
        exit;

    } catch (Exception $e) {
        log_error('Error in assign_roster: ' . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error assigning roster: ' . $e->getMessage()]);
        exit;
    }
}

function bulk_assign_roster() {
    try {
        // Get raw POST data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Validate input
        if (!$data || 
            !isset($data['team_id']) || 
            !isset($data['society_id']) || 
            !isset($data['shift_id']) || 
            !isset($data['guard_ids']) || 
            !is_array($data['guard_ids'])
        ) {
            // Clear any previous output
            ob_clean();
            
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid input. Please provide team_id, society_id, shift_id, and guard_ids.'
            ]);
            exit;
        }

        $team_id = intval($data['team_id']);
        $society_id = intval($data['society_id']);
        $shift_id = intval($data['shift_id']);
        $guard_ids = array_map('intval', $data['guard_ids']);

        $db = new Database();
        $db->beginTransaction();

        $successCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;

        // Insert-only; allow multiple non-overlapping entries
        $insertStmt = $db->prepare(
            "INSERT INTO roster (guard_id, society_id, shift_id, team_id, assignment_start_date, assignment_end_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $assignment_start_date = $data['assignment_start_date'] ?? null;
        $assignment_end_date = $data['assignment_end_date'] ?? null;

        // Basic validation
        if (!$assignment_start_date || !$assignment_end_date) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Assignment start and end dates are required.']);
            exit;
        }
        if ($assignment_start_date > $assignment_end_date) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
            exit;
        }

        $conflicts = [];

        foreach ($guard_ids as $guard_id) {
            try {
                // Check shift conflict (same employee, same shift, same dates)
                $shiftConflicts = find_shift_conflicts($db, $guard_id, $shift_id, $assignment_start_date, $assignment_end_date);
                if (!empty($shiftConflicts)) {
                    $duplicateCount++;
                    
                    // Get guard name for better user experience
                    $guardName = get_guard_name($db, $guard_id);
                    
                    $conflicts[] = [
                        'guard_id' => $guard_id,
                        'guard_name' => $guardName,
                        'type' => 'SHIFT_CONFLICT',
                        'message' => 'Employee already assigned to this shift during these dates.'
                    ];
                } else {
                    $insertStmt->execute([$guard_id, $society_id, $shift_id, $team_id, $assignment_start_date, $assignment_end_date]);
                    $successCount++;
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {  // Duplicate entry by other constraints
                    $duplicateCount++;
                    
                    // Get guard name for better user experience
                    $guardName = get_guard_name($db, $guard_id);
                    
                    $conflicts[] = [
                        'guard_id' => $guard_id,
                        'guard_name' => $guardName,
                        'type' => 'DUPLICATE_ENTRY',
                        'message' => 'Duplicate entry constraint violation.'
                    ];
                } else {
                    $errorCount++;
                    log_error("Bulk assign error for guard $guard_id: " . $e->getMessage());
                }
            }
        }

        $db->commit();

        // Build message with only relevant information
        $messageParts = [];
        $messageParts[] = "Bulk assignment complete";
        
        if ($successCount > 0) {
            $messageParts[] = "Successfully assigned: {$successCount}";
        }
        
        if ($duplicateCount > 0) {
            $messageParts[] = "Shift conflicts: {$duplicateCount}";
        }
        
        if ($errorCount > 0) {
            $messageParts[] = "Errors: {$errorCount}";
        }
        
        $message = implode(", ", $messageParts);

        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'details' => [
                'total_guards' => count($guard_ids),
                'assigned' => $successCount,
                'duplicates' => $duplicateCount,
                'errors' => $errorCount,
                'conflicts' => $conflicts
            ]
        ]);
        exit;

    } catch (Exception $e) {
        // Log the error
        log_error("Error in bulk_assign_roster: " . $e->getMessage());
        
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error during bulk assignment: ' . $e->getMessage()
        ]);
        exit;
    }
}

function delete_roster() {
    try {
        $roster_id = $_GET['id'] ?? null;

        if (!$roster_id) {
            // Clear any previous output
            ob_clean();
            
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid roster ID'
            ]);
            exit;
        }

        $db = new Database();
        $stmt = $db->prepare("DELETE FROM roster WHERE id = ?");
        $stmt->execute([$roster_id]);

        if ($stmt->rowCount() > 0) {
            // Clear any previous output
            ob_clean();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => "Roster entry {$roster_id} deleted successfully"
            ]);
            exit;
        } else {
            // Clear any previous output
            ob_clean();
            
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => "No roster entry found with ID {$roster_id}"
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Log the error
        log_error("Error in delete_roster: " . $e->getMessage());
        
        // Clear any previous output
        ob_clean();
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting roster entry: ' . $e->getMessage()
        ]);
        exit;
    }
}

// New functions for dynamic roster assignment
function update_roster() {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Support passing ID via query param when body lacks it (e.g., PATCH from REST router)
        if ((!$data || !isset($data['id'])) && isset($_GET['id'])) {
            if (!is_array($data)) { $data = []; }
            $data['id'] = intval($_GET['id']);
        }

        if (!$data || !isset($data['id'])) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Roster ID is required']);
            exit;
        }

        $id = intval($data['id']);
        $society_id = isset($data['society_id']) ? intval($data['society_id']) : null;
        $shift_id = isset($data['shift_id']) ? intval($data['shift_id']) : null;
        $start = $data['assignment_start_date'] ?? null;
        $end = $data['assignment_end_date'] ?? null;

        $db = new Database();

        // Validate dates if provided
        if ($start && $end && $start > $end) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
            exit;
        }

        // Get guard_id and current values for overlap check
        $row = $db->query('SELECT guard_id, society_id, assignment_start_date, assignment_end_date FROM roster WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Roster entry not found']);
            exit;
        }
        $guardId = intval($row['guard_id']);
        $currentSocietyId = intval($row['society_id']);

        // If either start, end, or society provided, check overlap with others
        if ($start !== null || $end !== null || $society_id !== null) {
            // Get existing dates for missing values
            $checkStart = $start !== null ? $start : $row['assignment_start_date'];
            $checkEnd = $end !== null ? $end : $row['assignment_end_date'];
            $checkSocietyId = $society_id !== null ? $society_id : $currentSocietyId;

            if (!$checkStart || !$checkEnd) {
                ob_clean();
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Both start and end dates are required.']);
                exit;
            }
            if ($checkStart > $checkEnd) {
                ob_clean();
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
                exit;
            }

            // Check overlap only if dates are being changed
            if ($start !== null || $end !== null) {
                // Get the shift ID to check (either new or current)
                $checkShiftId = $shift_id !== null ? $shift_id : intval($row['shift_id']);
                
                $shiftConflicts = find_shift_conflicts($db, $guardId, $checkShiftId, $checkStart, $checkEnd, $id);
                if (!empty($shiftConflicts)) {
                    ob_clean();
                    header('Content-Type: application/json');
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Employee already assigned to this shift during these dates.']);
                    exit;
                }
            }
        }

        // Build dynamic SQL based on provided fields
        $fields = [];
        $params = [];
        if ($society_id !== null) { $fields[] = 'society_id = ?'; $params[] = $society_id; }
        if ($shift_id !== null) { $fields[] = 'shift_id = ?'; $params[] = $shift_id; }
        if ($start !== null || $start === null) { $fields[] = 'assignment_start_date = ?'; $params[] = $start; }
        if ($end !== null || $end === null) { $fields[] = 'assignment_end_date = ?'; $params[] = $end; }

        if (empty($fields)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }

        $params[] = $id;
        $sql = 'UPDATE roster SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Roster updated']);
        exit;
    } catch (Exception $e) {
        log_error('Error in update_roster: ' . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating roster: ' . $e->getMessage()]);
        exit;
    }
}
function get_team_supervisors() {
    try {
        $team_id = $_GET['team_id'] ?? null;

        if (!$team_id) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Team ID is required'
            ]);
            exit;
        }

        $db = new Database();
        $supervisors = $db->query("
            SELECT 
                u.id, 
                CONCAT(u.first_name, ' ', u.surname) as supervisor_name
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE 
                tm.team_id = ? AND 
                tm.role = 'Supervisor'
        ", [$team_id])->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'supervisors' => $supervisors
        ]);
        exit;
    } catch (Exception $e) {
        log_error("Error in get_team_supervisors: " . $e->getMessage());
        
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching supervisors: ' . $e->getMessage()
        ]);
        exit;
    }
}

function get_supervisor_clients() {
    try {
        $supervisor_id = $_GET['supervisor_id'] ?? null;

        if (!$supervisor_id) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Supervisor ID is required'
            ]);
            exit;
        }

        $db = new Database();
        $clients = $db->query("
            SELECT DISTINCT
                sod.id, 
                sod.society_name
            FROM supervisor_site_assignments ssa
            JOIN society_onboarding_data sod ON ssa.site_id = sod.id
            WHERE 
                ssa.supervisor_id = ?
        ", [$supervisor_id])->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'clients' => $clients
        ]);
        exit;
    } catch (Exception $e) {
        log_error("Error in get_supervisor_clients: " . $e->getMessage());
        
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching clients: ' . $e->getMessage()
        ]);
        exit;
    }
}

function get_roster_by_id() {
    try {
        $roster_id = $_GET['id'] ?? null;

        if (!$roster_id) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Roster ID is required'
            ]);
            exit;
        }

        $db = new Database();
        
        // Get roster details with all related information
        $query = "
            SELECT 
                r.id, 
                r.guard_id, 
                r.society_id, 
                r.shift_id, 
                r.team_id,
                r.assignment_start_date,
                r.assignment_end_date,
                CONCAT(u.first_name, ' ', u.surname) as guard_name,
                t.team_name, 
                sod.society_name, 
                sm.shift_name, 
                sm.start_time, 
                sm.end_time
            FROM roster r
            JOIN users u ON r.guard_id = u.id
            JOIN teams t ON r.team_id = t.id
            JOIN society_onboarding_data sod ON r.society_id = sod.id
            JOIN shift_master sm ON r.shift_id = sm.id
            WHERE r.id = ?
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$roster_id]);
        $roster = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$roster) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Roster entry not found'
            ]);
            exit;
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'roster' => $roster
        ]);
        exit;
        
    } catch (Exception $e) {
        log_error("Error in get_roster_by_id: " . $e->getMessage());
        
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching roster: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Update main routing to include new actions
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? null;

    switch ($action) {
        case 'get_rosters':
            get_rosters();
            break;
        case 'get_roster_by_team': // compatibility alias
        case 'list': // alias used by mobile proxy
            get_rosters();
            break;
        case 'assign_roster':
            assign_roster();
            break;
        case 'bulk_assign_roster':
            bulk_assign_roster();
            break;
        case 'delete_roster':
            delete_roster();
            break;
        case 'get_team_supervisors':
            get_team_supervisors();
            break;
        case 'get_supervisor_clients':
            get_supervisor_clients();
            break;
        case 'update_roster':
            update_roster();
            break;
        case 'get_roster_by_id':
            get_roster_by_id();
            break;
        default:
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action'
            ]);
            exit;
    }
} catch (Exception $e) {
    log_error("Unexpected error: " . $e->getMessage());
    
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
    exit;
}
?> 