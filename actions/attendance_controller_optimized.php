<?php
// Optimized attendance controller with connection pooling and batch queries
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/ConnectionPool.php';
require_once __DIR__ . '/../helpers/CacheManager.php';
// Use shared JSON helper to avoid duplicate function definitions
require_once __DIR__ . '/../helpers/json_helper.php';

/**
 * Optimized get_attendance_data function - MASSIVE performance improvement
 * 
 * Key optimizations:
 * 1. Connection pooling for industrial-grade performance
 * 2. Single batch query instead of N+1 queries per user
 * 3. Caching layer for frequently accessed data
 * 4. Optimized data structures
 * 5. Reduced memory usage
 */
function get_attendance_data_optimized() {
    $startTime = microtime(true);
    
    try {
        // Use connection pool for industrial-grade performance
        $pdo = ConnectionPool::getConnection();
        $cache = CacheManager::getInstance();
        
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
        
        // Create cache key for this request
        $cacheKey = 'attendance_data_' . md5(serialize([
            'month' => $month,
            'year' => $year,
            'user_type' => $user_type,
            'society_id' => $society_id,
            'user_id' => $user_id
        ]));
        
        // Check for force refresh parameter
        $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] == '1';

        // Check cache first (5 minute cache for attendance data) unless force refresh
        if (!$forceRefresh) {
            $cachedData = $cache->get($cacheKey);
            if ($cachedData !== null) {
                error_log("Attendance data served from cache in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
                json_response([
                    'success' => true,
                    'data' => $cachedData,
                    'cached' => true,
                    'load_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                return;
            }
        } else {
            error_log("Force refresh requested, bypassing cache");
        }
        
        // Calculate date range
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
        
        // Check if attendance table has shift and actual time columns
        $hasShiftColumns = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'shift_start'")->rowCount() > 0;
        $hasActualColumns = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'check_in_time'")->rowCount() > 0
            && $pdo->query("SHOW COLUMNS FROM attendance LIKE 'check_out_time'")->rowCount() > 0;
        
        // Build optimized SINGLE query to get all data at once
        $params = [];
        $where_clauses = [];
        $params[] = $start_date;
        $params[] = $end_date;
        
        // Get supervisor team IDs if provided
        $supervisor_team_ids = isset($_GET['supervisor_team_ids']) ? explode(',', $_GET['supervisor_team_ids']) : null;
        
        if ($supervisor_team_ids) {
            $placeholders = implode(',', array_fill(0, count($supervisor_team_ids), '?'));
            $where_clauses[] = "(tm.team_id IN ($placeholders) OR tm.team_id IS NULL)";
            $params = array_merge($params, $supervisor_team_ids);
        }
        
        if ($user_type) {
            $where_clauses[] = "u.user_type = ?";
            $params[] = $user_type;
        }
        
        if ($society_id) {
            $where_clauses[] = "a.society_id = ?";
            $params[] = $society_id;
        }
        
        if ($user_id) {
            $where_clauses[] = "u.id = ?";
            $params[] = $user_id;
        }
        
        // Add team filter
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        if ($team_id) {
            $where_clauses[] = "tm.team_id = ?";
            $params[] = $team_id;
        }
        
        $where_sql = $where_clauses ? ' AND ' . implode(' AND ', $where_clauses) : '';
        
        // OPTIMIZED: Single query to get all users and their attendance data
        if ($hasShiftColumns) {
            $query = "
                SELECT 
                    u.id as user_id,
                    CONCAT(u.first_name, ' ', u.surname) as name,
                    u.email_id as email,
                    u.user_type,
                    a.id as attendance_id,
                    a.attendance_date,
                    a.attendance_master_id,
                    a.society_id,
                    a.shift_id,
                    a.shift_start,
                    a.shift_end," .
                    ($hasActualColumns ? "
                    a.check_in_time,
                    a.check_out_time," : "") . "
                    am.code as attendance_code
                FROM users u
                LEFT JOIN team_members tm ON u.id = tm.user_id
                LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date BETWEEN ? AND ?
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                WHERE 1=1" . $where_sql . "
                ORDER BY u.first_name, u.surname, a.attendance_date, a.shift_start
            ";
        } else {
            $query = "
                SELECT 
                    u.id as user_id,
                    CONCAT(u.first_name, ' ', u.surname) as name,
                    u.email_id as email,
                    u.user_type,
                    a.id as attendance_id,
                    a.attendance_date,
                    a.attendance_master_id,
                    a.society_id,
                    " . ($hasActualColumns ? "a.check_in_time, a.check_out_time," : "") . "
                    am.code as attendance_code
                FROM users u
                LEFT JOIN team_members tm ON u.id = tm.user_id
                LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date BETWEEN ? AND ?
                LEFT JOIN attendance_master am ON a.attendance_master_id = am.id
                WHERE 1=1" . $where_sql . "
                ORDER BY u.first_name, u.surname, a.attendance_date
            ";
        }
        
        $queryStartTime = microtime(true);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $queryTime = round((microtime(true) - $queryStartTime) * 1000, 2);
        
        // Process results efficiently
        $users = [];
        $attendance = [];
        $userIds = [];
        
        // Initialize date array
        $dates = [];
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dates[] = $date;
            $attendance[$date] = [];
        }
        
        // Process results in a single pass
        foreach ($results as $row) {
            $userId = $row['user_id'];
            
            // Add user if not already added
            if (!in_array($userId, $userIds)) {
                $users[] = [
                    'id' => $userId,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'user_type' => $row['user_type']
                ];
                $userIds[] = $userId;
            }
            
            // Add attendance data if exists
            if ($row['attendance_id']) {
                $date = $row['attendance_date'];
                
                if (!isset($attendance[$date][$userId])) {
                    $attendance[$date][$userId] = [];
                }
                
                $entry = [
                    'id' => $row['attendance_id'],
                    'attendance_code' => $row['attendance_code'],
                    'attendance_master_id' => $row['attendance_master_id'],
                    'society_id' => $row['society_id']
                ];
                
                // Add shift times and actual in/out times
                if ($hasShiftColumns) {
                    // shift_start and shift_end in attendance table are the ACTUAL in/out times
                    $entry['shift_start'] = $row['shift_start'];
                    $entry['shift_end'] = $row['shift_end'];
                    $entry['shift_id'] = $row['shift_id'];
                    
                    // Set actual_in and actual_out to the same values since shift_start/shift_end 
                    // in the attendance table represent the actual times the person checked in/out
                    $entry['actual_in'] = $row['shift_start'];
                    $entry['actual_out'] = $row['shift_end'];
                }

                // If check_in_time/check_out_time columns exist, they would override shift times
                if ($hasActualColumns) {
                    // Keep raw values; frontend may format to HH:mm
                    $entry['actual_in'] = $row['check_in_time'] ?? $row['shift_start'];
                    $entry['actual_out'] = $row['check_out_time'] ?? $row['shift_end'];
                }
                
                $attendance[$date][$userId][] = $entry;
            }
        }
        
        // Get holidays (cached for 1 hour)
        $holidayCacheKey = "holidays_{$year}_{$month}";
        $holiday_dates = $cache->get($holidayCacheKey);
        
        if ($holiday_dates === null) {
            $holiday_query = "SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?";
            $holiday_stmt = $pdo->prepare($holiday_query);
            $holiday_stmt->execute([$start_date, $end_date]);
            $holiday_dates = $holiday_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Cache holidays for 1 hour
            $cache->set($holidayCacheKey, $holiday_dates, 3600);
        }
        
        // Prepare response data
        $responseData = [
            'users' => $users,
            'attendance' => $attendance,
            'holidays' => $holiday_dates,
            'dates' => $dates,
            'days_in_month' => $days_in_month,
            'month' => $month,
            'year' => $year,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
        
        // Cache the response for 5 minutes
        $cache->set($cacheKey, $responseData, 300);
        
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log performance metrics
        error_log("Attendance data optimized load: Total={$totalTime}ms, Query={$queryTime}ms, Users=" . count($users) . ", Records=" . count($results));
        
        json_response([
            'success' => true,
            'data' => $responseData,
            'cached' => false,
            'performance' => [
                'total_time_ms' => $totalTime,
                'query_time_ms' => $queryTime,
                'user_count' => count($users),
                'record_count' => count($results)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Optimized attendance data error: " . $e->getMessage());
        json_response([
            'success' => false,
            'message' => 'Failed to load attendance data: ' . $e->getMessage()
        ], 500);
    }
}

// json_response is provided by helpers/json_helper.php

// Handle the request
if (isset($_GET['action']) && $_GET['action'] === 'get_attendance_data_optimized') {
    get_attendance_data_optimized();
}
