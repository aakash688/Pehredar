<?php
// actions/attendance_controller.php

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';
require_once __DIR__ . '/../helpers/CacheManager.php';

// Set CORS headers to allow requests from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route to appropriate function
switch ($action) {
    case 'get_attendance_data':
        get_attendance_data();
        break;
    case 'bulk_update_attendance':
        bulk_update_attendance();
        break;
    case 'get_attendance_audit_log':
        get_attendance_audit_log();
        break;
    case 'get_attendance_master_codes':
        get_attendance_master_codes();
        break;
    case 'export_attendance_excel':
        export_attendance_excel();
        break;
    case 'get_user_societies':
        get_user_societies();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified.'], 400);
        break;
}

/**
 * Fetch attendance data for display in the UI
 * Supports fetching multiple attendance entries per user per day for different societies
 */
function get_attendance_data() {
    // Use optimized version for better performance
    require_once __DIR__ . '/attendance_controller_optimized.php';
    get_attendance_data_optimized();
    return; // Exit early to use optimized version
    
    try {
        $db = new Database();
        
        // Get request parameters with defaults
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $user_type = isset($_GET['user_type']) ? $_GET['user_type'] : null;
        $society_id = isset($_GET['society_id']) ? intval($_GET['society_id']) : null;
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
        
        // Validate month
        if ($month < 1 || $month > 12) {
            $month = date('m');
        }
        
        // Calculate date range
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
        
        // Build query to get users
        $params = [];
        $where_clauses = [];
        
        // Check if supervisor team IDs are provided
        $supervisor_team_ids = isset($_GET['supervisor_team_ids']) ? explode(',', $_GET['supervisor_team_ids']) : null;

        $users_query = "SELECT DISTINCT
            u.id, 
            CONCAT(u.first_name, ' ', u.surname) as name,
            u.email_id as email,
            u.user_type
            FROM users u
            LEFT JOIN team_members tm ON u.id = tm.user_id
            WHERE 1=1";
            
        if ($supervisor_team_ids) {
            $placeholders = implode(',', array_fill(0, count($supervisor_team_ids), '?'));
            $users_query .= " AND (tm.team_id IN ($placeholders) OR tm.team_id IS NULL)";
            $params = array_merge($params, $supervisor_team_ids);
        }
        
        if ($user_type) {
            $users_query .= " AND u.user_type = ?";
            $params[] = $user_type;
        }
        
        if ($user_id) {
            $users_query .= " AND u.id = ?";
            $params[] = $user_id;
        }
        
        // Add team filter
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        if ($team_id) {
            $users_query .= " AND tm.team_id = ?";
            $params[] = $team_id;
        }
        
        $users_query .= " ORDER BY u.first_name ASC";
        
        // Get users
        $users = $db->query($users_query, $params)->fetchAll();
        
        // Get holidays for this period
        $holidays_query = "SELECT holiday_date, name FROM holidays 
            WHERE holiday_date BETWEEN ? AND ? AND is_active = 1";
        $holidays = $db->query($holidays_query, [$start_date, $end_date])->fetchAll();
        
        $holiday_dates = [];
        foreach ($holidays as $holiday) {
            $holiday_dates[$holiday['holiday_date']] = $holiday['name'];
        }
        
        // Get all societies
        $societies = [];
        $societies_query = "SELECT id, society_name, pin_code FROM society_onboarding_data";
        $society_results = $db->query($societies_query)->fetchAll();
        foreach ($society_results as $society) {
            $societies[] = [
                'id' => $society['id'],
                'society_name' => $society['society_name'] . (empty($society['pin_code']) ? '' : ' (' . $society['pin_code'] . ')')
            ];
        }
        
        // Check if shift columns exist
        $hasShiftColumns = false;
        $columnsResult = $db->query("SHOW COLUMNS FROM attendance LIKE 'shift_start'");
        if ($columnsResult && $columnsResult->rowCount() > 0) {
            $hasShiftColumns = true;
        }
        
        // Generate array of all dates in the month
        $dates = [];
        for ($day = 1; $day <= $days_in_month; $day++) {
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        
        // Prepare attendance data structure
        $attendance = [];
        foreach ($dates as $date) {
            $attendance[$date] = [];
        }
        
        // For each user, get attendance data
        foreach ($users as &$user) {
            // Build attendance query with team filtering
            if ($hasShiftColumns) {
                $att_query = "SELECT 
                    a.id,
                    a.attendance_date,
                    a.attendance_master_id,
                    a.society_id,
                    a.shift_id,
                    a.shift_start,
                    a.shift_end,
                    am.code as attendance_code
                    FROM attendance a
                    JOIN team_members tm ON a.user_id = tm.user_id
                    LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                    WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ?";
                
                // Add team filter if supervisor team IDs are provided
                if ($supervisor_team_ids) {
                    $placeholders = implode(',', array_fill(0, count($supervisor_team_ids), '?'));
                    $att_query .= " AND tm.team_id IN ($placeholders)";
                    $att_params = array_merge([$user['id'], $start_date, $end_date], $supervisor_team_ids);
                } else {
                    $att_params = [$user['id'], $start_date, $end_date];
                }
            } else {
                $att_query = "SELECT 
                    a.id,
                    a.attendance_date,
                    a.attendance_master_id,
                    a.society_id,
                    am.code as attendance_code
                    FROM attendance a
                    JOIN team_members tm ON a.user_id = tm.user_id
                    LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                    WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ?";
                
                // Add team filter if supervisor team IDs are provided
                if ($supervisor_team_ids) {
                    $placeholders = implode(',', array_fill(0, count($supervisor_team_ids), '?'));
                    $att_query .= " AND tm.team_id IN ($placeholders)";
                    $att_params = array_merge([$user['id'], $start_date, $end_date], $supervisor_team_ids);
                } else {
                    $att_params = [$user['id'], $start_date, $end_date];
                }
            }
            
            // Add society filter if specified
            if ($society_id) {
                $att_query .= " AND a.society_id = ?";
                $att_params[] = $society_id;
            }
            
            $att_query .= " ORDER BY a.attendance_date ASC";
            if ($hasShiftColumns) {
                $att_query .= ", a.shift_start";
            }
            
            // Execute query
            $att_results = $db->query($att_query, $att_params)->fetchAll();
            
            // Process results and organize by date
            foreach ($att_results as $att) {
                $date = $att['attendance_date'];
                
                if (!isset($attendance[$date])) {
                    $attendance[$date] = [];
                }
                
                if (!isset($attendance[$date][$user['id']])) {
                    $attendance[$date][$user['id']] = [];
                }
                
                $entry = [
                    'id' => $att['id'],
                    'attendance_code' => $att['attendance_code'],
                    'attendance_master_id' => $att['attendance_master_id'],
                    'society_id' => $att['society_id']
                ];
                
                // Add shift times if they exist
                if ($hasShiftColumns) {
                    $entry['shift_start'] = $att['shift_start'];
                    $entry['shift_end'] = $att['shift_end'];
                    $entry['shift_id'] = $att['shift_id'];
                }
                
                // Add to attendance array
                $attendance[$date][$user['id']][] = $entry;
            }
        }
        
        // Return data with consistent structure
        json_response([
            'success' => true,
            'data' => [
                'users' => $users,
                'holidays' => $holiday_dates,
                'days_in_month' => $days_in_month,
                'month' => $month,
                'year' => $year,
                'societies' => $societies,
                'attendance' => $attendance,
                'dates' => $dates
            ]
        ]);
    } catch (Exception $e) {
        // Log error
        error_log("Error in get_attendance_data: " . $e->getMessage());
        
        // Return error response
        json_response([
            'success' => false,
            'message' => 'Error fetching attendance data: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Bulk update attendance records
 * Supports adding multiple attendance entries per user per day for different societies
 */
function bulk_update_attendance() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['changes']) || !is_array($data['changes'])) {
        json_response(['success' => false, 'message' => 'Invalid attendance data. Expected "changes" array.'], 400);
        return;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Detect if actual time columns exist once per request
        $hasActualColumns = false;
        try {
            $hasActualColumns = ($db->query("SHOW COLUMNS FROM attendance LIKE 'check_in_time'")->rowCount() > 0)
                && ($db->query("SHOW COLUMNS FROM attendance LIKE 'check_out_time'")->rowCount() > 0);
        } catch (Exception $_) {
            $hasActualColumns = false;
        }
        $source = $data['source'] ?? 'web';
        $marked_by = $data['marked_by'] ?? 1; // Default to admin user (ID 1) if not provided
        $reason = $data['reason'] ?? 'Bulk update from attendance management';
        
        // Fetch attendance codes that don't require a society
        $exemptCodes = $db->query(
            "SELECT code FROM attendance_master 
             WHERE require_society = 0"
        )->fetchAll(PDO::FETCH_COLUMN);
        
        $updatedCount = 0;
        
        foreach ($data['changes'] as $record) {
            // Validate required fields
            if (!isset($record['user_id']) || !isset($record['date']) || !isset($record['code'])) {
                throw new Exception('Invalid attendance record. Required fields: user_id, date, code.');
            }
            
            $user_id = $record['user_id'];
            $date = $record['date'];
            $code = $record['code'];
            $society_id = $record['society_id'] ?? null;
            $shift_id = $record['shift_id'] ?? null;
            // In the attendance table, shift_start and shift_end ARE the actual in/out times
            // So we prioritize actual_in/actual_out from the form and store them in shift_start/shift_end
            $actual_in = $record['actual_in'] ?? $record['shift_start'] ?? null;
            $actual_out = $record['actual_out'] ?? $record['shift_end'] ?? null;
            
            // Normalize time format (HH:mm or HH:mm:ss to TIME format)
            $normalizeTime = function($timeStr) {
                if (!$timeStr) return null;
                $t = trim($timeStr);
                // If it contains date, extract just the time part
                if (strpos($t, ' ') !== false) {
                    $parts = explode(' ', $t);
                    $t = end($parts); // Get the time part
                }
                // Ensure seconds are included
                if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
                    $t .= ':00';
                }
                return $t;
            };
            
            // Store actual times in shift_start/shift_end (these are the actual in/out times)
            $shift_start = $normalizeTime($actual_in);
            $shift_end = $normalizeTime($actual_out);
            
            // Get shift_id from the record
            $shift_id = $record['shift_id'] ?? null;
            $record_reason = $record['reason'] ?? $reason;
            $attendance_id = $record['id'] ?? null;
            
            // Use the record-specific marked_by if provided, otherwise use the global one
            $record_marked_by = isset($record['marked_by']) ? $record['marked_by'] : $marked_by;
            $last_modified_by = isset($record['last_modified_by']) ? $record['last_modified_by'] : $record_marked_by;
            
            // Get attendance master ID for the code
            $attendanceMaster = $db->query(
                "SELECT id, code FROM attendance_master WHERE id = ? OR code = ?",
                [$code, $code]
            )->fetch();
            
            if (!$attendanceMaster) {
                throw new Exception("Invalid attendance code or ID: {$code}");
            }
            
            $attendanceMasterId = $attendanceMaster['id'];
            
            // Check if society is required
            $societyRequired = !in_array($attendanceMaster['code'], $exemptCodes);
            
            if ($societyRequired && !$society_id) {
                throw new Exception("Society is required for attendance code: {$attendanceMaster['code']}");
            }
            
            // Only check for time conflicts if this is a "Present" type of attendance with shift times
            if ($societyRequired && $shift_start && $shift_end) {
                // Modify the conflict query in the bulk_update_attendance function
                $conflictQuery = "
                    SELECT 
                        a.id, 
                        a.attendance_date, 
                        a.shift_start, 
                        a.shift_end, 
                        a.society_id, 
                        s.society_name, 
                        am.code as attendance_code,
                        am.require_society
                    FROM attendance a
                    JOIN society_onboarding_data s ON a.society_id = s.id
                    JOIN attendance_master am ON a.attendance_master_id = am.id
                    WHERE a.user_id = ? 
                    AND a.attendance_date = ? 
                    AND am.require_society = 1
                    AND a.shift_start IS NOT NULL 
                    AND a.shift_end IS NOT NULL
                ";

                // Exclude current record if updating
                $conflictParams = [$user_id, $date];
                if ($attendance_id) {
                    $conflictQuery .= " AND a.id != ?";
                    $conflictParams[] = $attendance_id;
                }

                // Log the conflict detection details
                debug_log("Conflict Detection Query: " . $conflictQuery);
                debug_log("Conflict Detection Params: " . json_encode($conflictParams));
                debug_log("New Shift Details - Start: {$shift_start}, End: {$shift_end}, Society ID: {$society_id}");

                $existingEntries = $db->query($conflictQuery, $conflictParams)->fetchAll();

                debug_log("Existing Entries Found: " . count($existingEntries));

                foreach ($existingEntries as $entry) {
                    // Skip entries without shift times
                    if (!$entry['shift_start'] || !$entry['shift_end']) continue;
                    
                    // Skip entries for the same society
                    if ($entry['society_id'] === $society_id) continue;
                    
                    // Detailed logging of entry details
                    debug_log("Comparing Shifts:");
                    debug_log("New Shift: {$shift_start} - {$shift_end}");
                    debug_log("Existing Shift: {$entry['shift_start']} - {$entry['shift_end']}");
                    debug_log("Existing Society: {$entry['society_name']}");
                    
                    // Check for time conflicts with 30-minute buffer
                    $hasConflict = isTimeOverlap(
                        $shift_start, 
                        $shift_end, 
                        $entry['shift_start'], 
                        $entry['shift_end'], 
                        1800 // 30 minutes in seconds
                    );
                    
                    if ($hasConflict) {
                        throw new Exception(
                            "Time conflict detected. Guard is already scheduled at {$entry['society_name']} " .
                            "from " . date('h:i A', strtotime($entry['shift_start'])) . 
                            " to " . date('h:i A', strtotime($entry['shift_end'])) . ". " .
                            "A guard cannot be present at multiple locations during the same time period."
                        );
                    }
                }
            }
            
            // Check if this is an update (we have an attendance ID) or a new record
            if ($attendance_id) {
                // This is an update to an existing record
                $existingRecord = $db->query(
                    "SELECT id, attendance_master_id, society_id, shift_start, shift_end, shift_id FROM attendance WHERE id = ?",
                    [$attendance_id]
                )->fetch();
                
                if (!$existingRecord) {
                    throw new Exception("Attendance record with ID {$attendance_id} not found.");
                }
                
                $createAuditLog = false;
                $auditDetails = [];
                
                // Check if the attendance code has changed
                if ($existingRecord['attendance_master_id'] != $attendanceMasterId) {
                    $createAuditLog = true;
                    $auditDetails['old_attendance_master_id'] = $existingRecord['attendance_master_id'];
                    $auditDetails['new_attendance_master_id'] = $attendanceMasterId;
                    $auditDetails['change_type'] = 'status';
                }
                
                // Check if shift times have changed
                $shiftChanged = false;
                if (($existingRecord['shift_start'] != $shift_start) || 
                    ($existingRecord['shift_end'] != $shift_end) || 
                    ($existingRecord['shift_id'] != $shift_id)) {
                    $shiftChanged = true;
                    $createAuditLog = true;
                    $auditDetails['old_shift_start'] = $existingRecord['shift_start'];
                    $auditDetails['new_shift_start'] = $shift_start;
                    $auditDetails['old_shift_end'] = $existingRecord['shift_end'];
                    $auditDetails['new_shift_end'] = $shift_end;
                    $auditDetails['old_shift_id'] = $existingRecord['shift_id'];
                    $auditDetails['new_shift_id'] = $shift_id;
                    $auditDetails['change_type'] = 'shift';
                }
                
                // Create audit log entry if needed
                if ($createAuditLog) {
                    $auditQuery = "INSERT INTO attendance_audit 
                        (attendance_id, changed_by, source, reason_for_change";
                    
                    // Add columns based on what changed
                    if (isset($auditDetails['old_attendance_master_id'])) {
                        $auditQuery .= ", old_attendance_master_id, new_attendance_master_id";
                    }
                    
                    if ($shiftChanged) {
                        $auditQuery .= ", change_details";
                    }
                    
                    $auditQuery .= ") VALUES (?, ?, ?, ?";
                    
                    $auditParams = [
                        $attendance_id,
                        $last_modified_by,
                        $source,
                        $record_reason
                    ];
                    
                    if (isset($auditDetails['old_attendance_master_id'])) {
                        $auditQuery .= ", ?, ?";
                        $auditParams[] = $auditDetails['old_attendance_master_id'];
                        $auditParams[] = $auditDetails['new_attendance_master_id'];
                    }
                    
                    if ($shiftChanged) {
                        $auditQuery .= ", ?";
                        $changeDetails = json_encode([
                            'change_type' => 'shift',
                            'old_shift_start' => $auditDetails['old_shift_start'],
                            'new_shift_start' => $auditDetails['new_shift_start'],
                            'old_shift_end' => $auditDetails['old_shift_end'],
                            'new_shift_end' => $auditDetails['new_shift_end'],
                            'old_shift_id' => $auditDetails['old_shift_id'],
                            'new_shift_id' => $auditDetails['new_shift_id']
                        ]);
                        $auditParams[] = $changeDetails;
                    }
                    
                    $auditQuery .= ")";
                    $db->query($auditQuery, $auditParams);
                }
                
                // Prepare update query - shift_start/shift_end contain the actual in/out times
                if ($societyRequired) {
                    $societyUpdateQuery = "UPDATE attendance SET attendance_master_id = ?, shift_start = ?, shift_end = ?, shift_id = ?, last_modified_by = ?, society_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $updateParams = [$attendanceMasterId, $shift_start, $shift_end, $shift_id, $last_modified_by, $society_id, $attendance_id];
                } else {
                    $societyUpdateQuery = "UPDATE attendance SET attendance_master_id = ?, shift_start = ?, shift_end = ?, shift_id = ?, last_modified_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $updateParams = [$attendanceMasterId, $shift_start, $shift_end, $shift_id, $last_modified_by, $attendance_id];
                }
                
                $db->query($societyUpdateQuery, $updateParams);
                $updatedCount++;
            } else {
                // This is a new record
                // Prepare insert query with shift_id and actual time support
                $insertColumns = [
                    'user_id', 
                    'attendance_master_id', 
                    'attendance_date', 
                    'shift_start', 
                    'shift_end',
                    'shift_id',
                    'marked_by', 
                    'last_modified_by', 
                    'source'
                ];
                
                $insertParams = [
                    $user_id,
                    $attendanceMasterId,
                    $date,
                    $shift_start,
                    $shift_end,
                    $shift_id,
                    $record_marked_by,
                    $last_modified_by,
                    $source
                ];
                
                if ($hasActualColumns) {
                    $insertColumns[] = 'check_in_time';
                    $insertColumns[] = 'check_out_time';
                    $insertParams[] = $actual_in;
                    $insertParams[] = $actual_out;
                }
                
                // Add society if required
                if ($societyRequired) {
                    $insertColumns[] = 'society_id';
                    $insertParams[] = $society_id;
                }
                
                // Build dynamic query
                $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
                $columnsStr = implode(',', $insertColumns);
                
                $insertQuery = "INSERT INTO attendance ($columnsStr) VALUES ($placeholders)";
                
                $db->query($insertQuery, $insertParams);
                
                // Get the ID of the newly inserted attendance record
                $attendanceId = $db->lastInsertId();
                
                // Create audit log entry for new record
                $db->query(
                    "INSERT INTO attendance_audit 
                    (attendance_id, changed_by, new_attendance_master_id, source, reason_for_change)
                    VALUES (?, ?, ?, ?, ?)",
                    [
                        $attendanceId,
                        $record_marked_by,
                        $attendanceMasterId,
                        $source,
                        $record_reason
                    ]
                );
                $updatedCount++;
            }
        }
        
        // Commit the transaction
        $db->commit();

        // Clear attendance caches to ensure fresh data is served
        try {
            $cache = CacheManager::getInstance();

            // Clear query caches that might contain attendance data
            $cache->clear('queries');

            // Clear API caches
            $cache->clear('api');

            // Clear any cached attendance data patterns
            // The attendance data cache keys follow the pattern: 'attendance_data_' + md5 hash
            // Since we can't predict the exact key, we clear all query caches which is safer

            error_log("Attendance cache cleared after update");
        } catch (Exception $cacheError) {
            // Don't fail the request if cache clearing fails
            error_log("Warning: Failed to clear attendance cache: " . $cacheError->getMessage());
        }

        json_response([
            'success' => true,
            'message' => 'Attendance updated successfully.',
            'updated' => $updatedCount
        ]);
    } catch (Exception $e) {
        // Rollback on error
        $db->rollback();
        json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

/**
 * Get the audit log for a specific attendance record
 */
function get_attendance_audit_log() {
    $db = new Database();
    $attendance_id = $_GET['id'] ?? null;
    
    if (!$attendance_id) {
        json_response(['success' => false, 'message' => 'Attendance ID is required.'], 400);
        return;
    }
    
    try {
        $auditLog = $db->query(
            "SELECT aa.id, aa.change_timestamp, aa.source, aa.reason_for_change, aa.change_details,
            u.first_name as changed_by_first_name, u.surname as changed_by_surname,
            old_am.code as old_code, old_am.name as old_status_name,
            new_am.code as new_code, new_am.name as new_status_name
            FROM attendance_audit aa
            LEFT JOIN users u ON aa.changed_by = u.id
            LEFT JOIN attendance_master old_am ON aa.old_attendance_master_id = old_am.id
            LEFT JOIN attendance_master new_am ON aa.new_attendance_master_id = new_am.id
            WHERE aa.attendance_id = ?
            ORDER BY aa.change_timestamp DESC",
            [$attendance_id]
        )->fetchAll();
        
        // Process each audit log entry to include shift change details
        foreach ($auditLog as &$entry) {
            if (!empty($entry['change_details'])) {
                $changeDetails = json_decode($entry['change_details'], true);
                if ($changeDetails && isset($changeDetails['change_type']) && $changeDetails['change_type'] === 'shift') {
                    // Add shift change details to the entry
                    $entry['is_shift_change'] = true;
                    $entry['old_shift_start'] = $changeDetails['old_shift_start'] ?? null;
                    $entry['new_shift_start'] = $changeDetails['new_shift_start'] ?? null;
                    $entry['old_shift_end'] = $changeDetails['old_shift_end'] ?? null;
                    $entry['new_shift_end'] = $changeDetails['new_shift_end'] ?? null;
                    
                    // Format the status display for shift changes
                    if (empty($entry['old_code']) && empty($entry['new_code'])) {
                        $entry['old_code'] = 'Shift';
                        $entry['old_status_name'] = $changeDetails['old_shift_start'] . ' - ' . $changeDetails['old_shift_end'];
                        $entry['new_code'] = 'Shift';
                        $entry['new_status_name'] = $changeDetails['new_shift_start'] . ' - ' . $changeDetails['new_shift_end'];
                    }
                }
            }
        }
        
        json_response(['success' => true, 'data' => $auditLog]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

/**
 * Get all active attendance codes from attendance_master
 */
function get_attendance_master_codes() {
    $db = new Database();
    try {
        $codes = $db->query("SELECT id, code, name, description, multiplier, require_society FROM attendance_master")->fetchAll();
        json_response(['success' => true, 'data' => $codes]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

/**
 * Get all societies for dropdown
 */
function get_user_societies() {
    $db = new Database();
    
    try {
        // Fetch ALL societies with their details
        $societies = $db->query(
            "SELECT 
                id, 
                society_name, 
                pin_code,
                street_address,
                city,
                state
             FROM society_onboarding_data
             ORDER BY society_name"
        )->fetchAll();
        
        // Return all societies
        json_response(['success' => true, 'data' => $societies]);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

/**
 * Export attendance data as Excel/CSV file
 */
function export_attendance_excel() {
    $db = new Database();
    
    try {
        // Get parameters
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $user_type = $_GET['department'] ?? null;
        $user_id = $_GET['user_id'] ?? null;
        $society_id = $_GET['society_id'] ?? null;
        
        // Create file name
        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
        $filename = "Attendance_Report_{$monthName}_{$year}.csv";
        
        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Calculate start and end dates of the month
        $start_date = sprintf("%04d-%02d-01", $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write header row
        $headerRow = ['Employee ID', 'Employee Name', 'User Type', 'Society', 'Date', 'Attendance Code', 'Shift Start', 'Shift End'];
        fputcsv($output, $headerRow);
        
        // Check if shift columns exist
        $hasShiftColumns = false;
        $columnsResult = $db->query("SHOW COLUMNS FROM attendance LIKE 'shift_start'");
        if ($columnsResult && $columnsResult->rowCount() > 0) {
            $hasShiftColumns = true;
        }
        
        // Get users
        $userQuery = "SELECT id, CONCAT(first_name, ' ', surname) as name, user_type FROM users";
        $userParams = [];
        $whereAdded = false;
        
        if ($user_type) {
            $userQuery .= " WHERE user_type = ?";
            $userParams[] = $user_type;
            $whereAdded = true;
        }
        
        if ($user_id) {
            $userQuery .= $whereAdded ? " AND id = ?" : " WHERE id = ?";
            $userParams[] = $user_id;
        }
        
        $userQuery .= " ORDER BY first_name, surname";
        $users = $db->query($userQuery, $userParams)->fetchAll();
        
        // Get attendance data for each user
        foreach ($users as $user) {
            // Build attendance query
            if ($hasShiftColumns) {
                $attendanceQuery = "SELECT a.attendance_date, am.code, s.society_name, a.shift_start, a.shift_end
                                  FROM attendance a
                                  JOIN attendance_master am ON a.attendance_master_id = am.id
                                  JOIN society_onboarding_data s ON a.society_id = s.id
                                  WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ?";
            } else {
                $attendanceQuery = "SELECT a.attendance_date, am.code, s.society_name
                                  FROM attendance a
                                  JOIN attendance_master am ON a.attendance_master_id = am.id
                                  JOIN society_onboarding_data s ON a.society_id = s.id
                                  WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ?";
            }
                
            $attendanceParams = [$user['id'], $start_date, $end_date];
            
            if ($society_id) {
                $attendanceQuery .= " AND a.society_id = ?";
                $attendanceParams[] = $society_id;
            }
            
            $attendanceQuery .= " ORDER BY a.attendance_date";
            if ($hasShiftColumns) {
                $attendanceQuery .= ", a.shift_start";
            }
            
            $attendanceData = $db->query($attendanceQuery, $attendanceParams)->fetchAll();
            
            // Write attendance data rows
            foreach ($attendanceData as $record) {
                $row = [
                    $user['id'],
                    $user['name'],
                    $user['user_type'],
                    $record['society_name'],
                    $record['attendance_date'],
                    $record['code'],
                    $hasShiftColumns ? ($record['shift_start'] ? date('H:i', strtotime($record['shift_start'])) : 'N/A') : 'N/A',
                    $hasShiftColumns ? ($record['shift_end'] ? date('H:i', strtotime($record['shift_end'])) : 'N/A') : 'N/A'
                ];
                
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        // For errors during CSV generation, redirect back with error message
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode('Error exporting data: ' . $e->getMessage()));
        exit;
    }
}

function debug_log($message) {
    error_log("[ATTENDANCE_CONFLICT_DEBUG] " . $message);
}

function isTimeOverlap($start1, $end1, $start2, $end2, $tolerance = 1800) {
    // Convert times to seconds
    $start1_sec = strtotime("1970-01-01 {$start1}");
    $end1_sec = strtotime("1970-01-01 {$end1}");
    $start2_sec = strtotime("1970-01-01 {$start2}");
    $end2_sec = strtotime("1970-01-01 {$end2}");

    // Adjust for overnight shifts
    if ($end1_sec < $start1_sec) $end1_sec += 86400;
    if ($end2_sec < $start2_sec) $end2_sec += 86400;

    // Detailed logging
    error_log("[TIME_OVERLAP_DEBUG] 
    Shift 1: {$start1} - {$end1} 
    Shift 2: {$start2} - {$end2}
    Shift 1 Seconds: {$start1_sec} - {$end1_sec}
    Shift 2 Seconds: {$start2_sec} - {$end2_sec}
    Tolerance: {$tolerance} seconds");

    // Check for true overlap considering tolerance
    $hasOverlap = !(
        $end1_sec < ($start2_sec - $tolerance) || 
        $start1_sec > ($end2_sec + $tolerance)
    );

    error_log("[TIME_OVERLAP_DEBUG] Overlap Result: " . ($hasOverlap ? 'TRUE' : 'FALSE'));

    return $hasOverlap;
}
?> 