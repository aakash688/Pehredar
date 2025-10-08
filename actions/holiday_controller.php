<?php
// actions/holiday_controller.php

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_holidays':
        get_holidays();
        break;
    case 'add_holiday':
        add_holiday();
        break;
    case 'update_holiday':
        update_holiday();
        break;
    case 'delete_holiday':
        delete_holiday();
        break;
    case 'export_holidays':
        export_holidays();
        break;
    case 'import_holidays':
        import_holidays();
        break;
    default:
        json_response(['success' => false, 'message' => 'Invalid action specified.'], 400);
        break;
}

/**
 * Fetches all holidays for a given year
 */
function get_holidays() {
    $db = new Database();
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    $start_date = $year . '-01-01';
    $end_date = $year . '-12-31';
    
    $holidays = $db->query(
        "SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date",
        [$start_date, $end_date]
    )->fetchAll();
    
    json_response(['success' => true, 'data' => $holidays]);
}

/**
 * Adds a new holiday
 */
function add_holiday() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['holiday_date']) || empty($data['name'])) {
        json_response(['success' => false, 'message' => 'Date and name are required.'], 400);
        return;
    }
    
    // Check if a holiday already exists on this date
    $exists = $db->query("SELECT id FROM holidays WHERE holiday_date = ?", [$data['holiday_date']])->fetch();
    if ($exists) {
        json_response(['success' => false, 'message' => 'A holiday already exists on this date.'], 400);
        return;
    }
    
    $sql = "INSERT INTO holidays (holiday_date, name, description) VALUES (?, ?, ?)";
    $db->query($sql, [$data['holiday_date'], $data['name'], $data['description'] ?? '']);
    
    json_response(['success' => true, 'message' => 'Holiday added successfully.']);
}

/**
 * Updates an existing holiday
 */
function update_holiday() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        json_response(['success' => false, 'message' => 'Missing ID.'], 400);
        return;
    }
    
    if (empty($data['holiday_date']) || empty($data['name'])) {
        json_response(['success' => false, 'message' => 'Date and name are required.'], 400);
        return;
    }
    
    // Check if another holiday exists on this date (excluding current one)
    $exists = $db->query(
        "SELECT id FROM holidays WHERE holiday_date = ? AND id != ?", 
        [$data['holiday_date'], $data['id']]
    )->fetch();
    
    if ($exists) {
        json_response(['success' => false, 'message' => 'Another holiday already exists on this date.'], 400);
        return;
    }
    
    $sql = "UPDATE holidays SET holiday_date = ?, name = ?, description = ?, is_active = ? WHERE id = ?";
    $db->query(
        $sql, 
        [
            $data['holiday_date'], 
            $data['name'], 
            $data['description'] ?? '', 
            isset($data['is_active']) ? (bool)$data['is_active'] : true,
            $data['id']
        ]
    );
    
    json_response(['success' => true, 'message' => 'Holiday updated successfully.']);
}

/**
 * Deletes (deactivates) a holiday
 */
function delete_holiday() {
    $db = new Database();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        json_response(['success' => false, 'message' => 'Missing ID.'], 400);
        return;
    }
    
    $sql = "UPDATE holidays SET is_active = FALSE WHERE id = ?";
    $db->query($sql, [$data['id']]);
    
    json_response(['success' => true, 'message' => 'Holiday deactivated successfully.']);
}

/**
 * Export holidays as CSV file
 */
function export_holidays() {
    $db = new Database();
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Create file name
    $filename = "Holidays_Export_{$year}.csv";
    
    // Set headers for file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Get holidays for the year
    $holidays = $db->query(
        "SELECT holiday_date, name, description, is_active FROM holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date",
        [$year]
    )->fetchAll();
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write header row
    fputcsv($output, ['Date', 'Name', 'Description', 'Active']);
    
    // Write data rows
    foreach ($holidays as $holiday) {
        $row = [
            $holiday['holiday_date'],
            $holiday['name'],
            $holiday['description'],
            $holiday['is_active'] ? 'Yes' : 'No'
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Import holidays from CSV file
 */
function import_holidays() {
    $db = new Database();
    
    // Check if a file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'No file uploaded or error in upload.'], 400);
        return;
    }
    
    // Get uploaded file
    $file = $_FILES['csv_file']['tmp_name'];
    
    // Open and read the CSV file
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Skip header row
        fgetcsv($handle);
        
        // Track import stats
        $stats = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Process each row
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Ensure the row has at least 2 columns (date and name)
                if (count($data) < 2) {
                    $stats['skipped']++;
                    continue;
                }
                
                // Extract data from CSV
                $date = trim($data[0]);
                $name = trim($data[1]);
                $description = isset($data[2]) ? trim($data[2]) : '';
                $isActive = isset($data[3]) ? (strtolower($data[3]) === 'yes' || $data[3] === '1') : true;
                
                // Format date properly - handle multiple formats
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    // Already in YYYY-MM-DD format
                    $formattedDate = $date;
                } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                    // MM/DD/YYYY format
                    $parts = explode('/', $date);
                    $formattedDate = "{$parts[2]}-{$parts[0]}-{$parts[1]}";
                } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $date)) {
                    // D-M-YYYY format
                    $parts = explode('-', $date);
                    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                    $formattedDate = "{$parts[2]}-{$month}-{$day}";
                } elseif (strtotime($date) !== false) {
                    // Any other valid date format that PHP can parse
                    $formattedDate = date('Y-m-d', strtotime($date));
                } else {
                    // Invalid date format
                    $stats['errors']++;
                    continue;
                }
                
                // Check if holiday already exists
                $existingHoliday = $db->query(
                    "SELECT id FROM holidays WHERE holiday_date = ?",
                    [$formattedDate]
                )->fetch();
                
                if ($existingHoliday) {
                    // Update existing holiday
                    $db->query(
                        "UPDATE holidays SET name = ?, description = ?, is_active = ? WHERE holiday_date = ?",
                        [$name, $description, $isActive, $formattedDate]
                    );
                } else {
                    // Insert new holiday
                    $db->query(
                        "INSERT INTO holidays (holiday_date, name, description, is_active) VALUES (?, ?, ?, ?)",
                        [$formattedDate, $name, $description, $isActive]
                    );
                }
                
                $stats['imported']++;
            }
            
            // Commit transaction
            $db->commit();
            
            json_response([
                'success' => true, 
                'message' => "Import completed. {$stats['imported']} holidays imported, {$stats['skipped']} skipped, {$stats['errors']} errors."
            ]);
        } catch (Exception $e) {
            // Rollback on error
            $db->rollback();
            json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
        
        fclose($handle);
    } else {
        json_response(['success' => false, 'message' => 'Unable to open file.'], 400);
    }
}
