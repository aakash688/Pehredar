<?php
/**
 * Employee Controller
 * Handles employee-related actions such as enrollment, updates, etc.
 */

// Include necessary dependencies
require_once __DIR__ . '/../helpers/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Logging function
function log_error($message, $details = []) {
    $log_file = __DIR__ . '/../logs/employee_enrollment_errors.log';
    
    // Ensure log directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    
    if (!empty($details)) {
        $log_entry .= "Details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Log server details
    $log_entry .= "Server Details:\n";
    $log_entry .= "- Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
    $log_entry .= "- Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
    $log_entry .= "- Remote Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Function to handle JSON responses
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Function to validate and sanitize input
function sanitize_input($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

// Function to handle file uploads
function upload_file($file, $allowed_types, $max_size, $upload_dir) {
    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Check if file was uploaded successfully
    if ($file['error'] !== UPLOAD_ERR_OK) {
        log_error("File upload error", [
            'error_code' => $file['error'],
            'error_message' => match($file['error']) {
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
                default => 'Unknown upload error'
            }
        ]);
        return ['success' => false, 'message' => 'File upload failed'];
    }

    // Check file size
    if ($file['size'] > $max_size) {
        log_error("File size exceeded", ['file_size' => $file['size'], 'max_size' => $max_size]);
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }

    // Check file type with robust handling (Windows often reports PDFs/images as application/octet-stream)
    $file_type = mime_content_type($file['tmp_name']);
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extension_allowed = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
    $is_pdf_alias = ($file_type === 'application/x-pdf');
    $is_octet_stream_but_known_ext = ($file_type === 'application/octet-stream' && $extension_allowed);
    if (!in_array($file_type, $allowed_types) && !$is_pdf_alias && !$is_octet_stream_but_known_ext) {
        log_error("Invalid file type", [
            'file_type' => $file_type,
            'file_extension' => $file_extension,
            'allowed_types' => $allowed_types
        ]);
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    // Generate unique filename
    $new_filename = uniqid('employee_') . '.' . $file_extension;
    $destination = $upload_dir . '/' . $new_filename;

    // Determine relative path from project root
    $config = require __DIR__ . '/../config.php';
    $base_upload_dir = 'uploads/employees/';
    $relative_path = $base_upload_dir . $new_filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => true, 
            'message' => 'File uploaded successfully', 
            'filename' => $new_filename,
            'path' => $relative_path
        ];
    }

    log_error("File move failed", ['tmp_name' => $file['tmp_name'], 'destination' => $destination]);
    return ['success' => false, 'message' => 'File upload failed'];
}

// Main action handler
function handle_employee_action() {
    try {
        // Log all incoming POST and FILE data for debugging
        log_error("Incoming Request Data", [
            'POST' => $_POST,
            'FILES' => array_keys($_FILES)
        ]);

        $action = $_GET['action'] ?? null;

        switch ($action) {
            case 'enroll_employee':
                enroll_employee();
                break;
            case 'get_family_references':
                get_family_references();
                break;
            case 'update_family_references':
                update_family_references();
                break;
            default:
                log_error("Invalid action", ['action' => $action]);
                json_response(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        log_error("Unhandled exception", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        json_response([
            'success' => false, 
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}

function enroll_employee() {
    try {
        $db = new Database();

        // Initialize variables to avoid linter errors
        $query = '';
        $values = [];

        // Validate required fields
        $required_fields = [
            'first_name', 'surname', 'date_of_birth', 'gender', 
            'mobile_number', 'email_id', 'address',
            'date_of_joining', 'user_type', 'salary',
            'bank_account_number', 'ifsc_code', 'bank_name', 'password'
        ];

        // Check for missing fields
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missing_fields[] = $field;
            }
        }

        // Handle permanent address
        if (!isset($_POST['permanent_address']) || trim($_POST['permanent_address']) === '') {
            // If permanent address is not provided, use current address
            $_POST['permanent_address'] = $_POST['address'];
        }

        if (!empty($missing_fields)) {
            log_error("Missing required fields", ['missing_fields' => $missing_fields]);
            json_response([
                'success' => false, 
                'message' => "Missing required fields: " . implode(', ', $missing_fields)
            ], 400);
        }

        // Additional validation
        $validation_errors = [];
        
        // Validate salary (DECIMAL(10,2) - max value is 99,999,999.99)
        $salary = floatval($_POST['salary']);
        if ($salary > 99999999.99) {
            $validation_errors[] = "Salary must not exceed 99,999,999.99";
        } elseif ($salary < 0) {
            $validation_errors[] = "Salary must be a positive number";
        }
        
        // Validate email format
        if (!filter_var($_POST['email_id'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Invalid email format";
        }
        
        // Validate mobile number (basic check for numeric and reasonable length)
        if (!preg_match('/^[0-9]{10,15}$/', $_POST['mobile_number'])) {
            $validation_errors[] = "Mobile number must be 10-15 digits";
        }
        
        // Validate Aadhar number format (12 digits)
        if (isset($_POST['aadhar_number']) && !empty($_POST['aadhar_number'])) {
            if (!preg_match('/^[0-9]{12}$/', $_POST['aadhar_number'])) {
                $validation_errors[] = "Aadhar number must be exactly 12 digits";
            }
        }
        
        // Validate PAN number format (basic check)
        if (isset($_POST['pan_number']) && !empty($_POST['pan_number'])) {
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($_POST['pan_number']))) {
                $validation_errors[] = "PAN number format is invalid (e.g., ABCDE1234F)";
            }
        }
        
        // Validate family references
        $family_references = [];
        for ($i = 1; $i <= 2; $i++) {
            $ref_prefix = "family_ref_{$i}_";
            $ref_data = [
                'name' => $_POST[$ref_prefix . 'name'] ?? '',
                'relation' => $_POST[$ref_prefix . 'relation'] ?? '',
                'mobile_primary' => $_POST[$ref_prefix . 'mobile_primary'] ?? '',
                'mobile_secondary' => $_POST[$ref_prefix . 'mobile_secondary'] ?? '',
                'address' => $_POST[$ref_prefix . 'address'] ?? ''
            ];
            
            // Reference 1 is required
            if ($i == 1) {
                if (empty($ref_data['name'])) {
                    $validation_errors[] = "Family Reference 1: Name is required";
                }
                if (empty($ref_data['relation'])) {
                    $validation_errors[] = "Family Reference 1: Relation is required";
                }
                if (empty($ref_data['mobile_primary'])) {
                    $validation_errors[] = "Family Reference 1: Primary mobile number is required";
                } elseif (!preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_primary'])) {
                    $validation_errors[] = "Family Reference 1: Primary mobile number must be 10-15 digits";
                }
                if (!empty($ref_data['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_secondary'])) {
                    $validation_errors[] = "Family Reference 1: Secondary mobile number must be 10-15 digits";
                }
                if (empty($ref_data['address'])) {
                    $validation_errors[] = "Family Reference 1: Address is required";
                }
            }
            
            // Reference 2 is optional - if any field is provided, all required fields must be filled
            if ($i == 2) {
                $hasAnyField = !empty($ref_data['name']) || !empty($ref_data['relation']) || 
                             !empty($ref_data['mobile_primary']) || !empty($ref_data['address']);
                
                if ($hasAnyField) {
                    if (empty($ref_data['name'])) {
                        $validation_errors[] = "Family Reference 2: Name is required when other fields are provided";
                    }
                    if (empty($ref_data['relation'])) {
                        $validation_errors[] = "Family Reference 2: Relation is required when other fields are provided";
                    }
                    if (empty($ref_data['mobile_primary'])) {
                        $validation_errors[] = "Family Reference 2: Primary mobile number is required when other fields are provided";
                    } elseif (!preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_primary'])) {
                        $validation_errors[] = "Family Reference 2: Primary mobile number must be 10-15 digits";
                    }
                    if (!empty($ref_data['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_secondary'])) {
                        $validation_errors[] = "Family Reference 2: Secondary mobile number must be 10-15 digits";
                    }
                    if (empty($ref_data['address'])) {
                        $validation_errors[] = "Family Reference 2: Address is required when other fields are provided";
                    }
                }
            }
            
            $family_references[] = $ref_data;
        }
        
        if (!empty($validation_errors)) {
            log_error("Validation errors", ['validation_errors' => $validation_errors]);
            json_response([
                'success' => false, 
                'message' => "Validation errors: " . implode(', ', $validation_errors)
            ], 400);
        }

        // File upload handling
        $upload_dir = __DIR__ . '/../uploads/employees';
        $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_doc_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        $document_fields = [
            'profile_photo', 'aadhar_card_scan', 'pan_card_scan', 
            'bank_passbook_scan', 'police_verification_document', 
            'ration_card_scan', 'light_bill_scan', 
            'voter_id_scan', 'passport_scan'
        ];

        $uploaded_files = [];

        foreach ($document_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] == UPLOAD_ERR_OK) {
                $file_type = $field === 'profile_photo' ? $allowed_image_types : $allowed_doc_types;
                $upload_result = upload_file(
                    $_FILES[$field], 
                    $file_type, 
                    $max_file_size, 
                    $upload_dir
                );

                if ($upload_result['success']) {
                    $uploaded_files[$field] = $upload_result['path']; // Use 'path' instead of 'filename'
                } else {
                    log_error("File upload failed for field", [
                        'field' => $field,
                        'upload_result' => $upload_result
                    ]);
                    json_response([
                        'success' => false, 
                        'message' => "File upload failed for $field: " . $upload_result['message']
                    ], 400);
                }
            }
        }

        // Prepare data for database insertion
        $employee_data = [
            'first_name' => sanitize_input($_POST['first_name']),
            'surname' => sanitize_input($_POST['surname']),
            'date_of_birth' => sanitize_input($_POST['date_of_birth']),
            'gender' => sanitize_input($_POST['gender']),
            'mobile_number' => sanitize_input($_POST['mobile_number']),
            'email_id' => sanitize_input($_POST['email_id']),
            'address' => sanitize_input($_POST['address']),
            'permanent_address' => sanitize_input($_POST['permanent_address']),
            'date_of_joining' => sanitize_input($_POST['date_of_joining']),
            'user_type' => sanitize_input($_POST['user_type']),
            'salary' => sanitize_input($_POST['salary']),
            'bank_account_number' => sanitize_input($_POST['bank_account_number']),
            'ifsc_code' => sanitize_input($_POST['ifsc_code']),
            'bank_name' => sanitize_input($_POST['bank_name']),
            'web_access' => isset($_POST['web_access']) ? 1 : 0,
            'mobile_access' => isset($_POST['mobile_access']) ? 1 : 0,
            'password' => password_hash(sanitize_input($_POST['password']), PASSWORD_DEFAULT)
        ];

        // Optional fields - normalize empty values to NULL to prevent duplicate errors
        $optional_fields = [
            'aadhar_number', 'pan_number', 'voter_id_number', 
            'passport_number', 'highest_qualification',
            'esic_number', 'uan_number', 'pf_number'
        ];

        foreach ($optional_fields as $field) {
            if (isset($_POST[$field]) && trim($_POST[$field]) !== '') {
                $employee_data[$field] = sanitize_input($_POST[$field]);
            } else {
                // Explicitly set to NULL for database insertion (MySQL allows multiple NULLs in unique columns)
                $employee_data[$field] = null;
            }
        }

        // Add uploaded file paths
        $employee_data = array_merge($employee_data, $uploaded_files);

        // Begin transaction for more robust insertion
        $db->beginTransaction();

        try {
            // Check for existing records to provide more specific error messages
            $checkQueries = [];
            $checkParams = [];
            
            // Always check required unique fields
            $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE email_id = ?) as email_count";
            $checkParams[] = $employee_data['email_id'];
            
            $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE mobile_number = ?) as mobile_count";
            $checkParams[] = $employee_data['mobile_number'];
            
            $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE bank_account_number = ?) as bank_account_count";
            $checkParams[] = $employee_data['bank_account_number'];
            
            // Only check optional fields if they are provided (not null/empty)
            if (isset($employee_data['aadhar_number']) && !empty($employee_data['aadhar_number'])) {
                $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE aadhar_number = ?) as aadhar_count";
                $checkParams[] = $employee_data['aadhar_number'];
            }
            
            if (isset($employee_data['pan_number']) && !empty($employee_data['pan_number'])) {
                $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE pan_number = ?) as pan_count";
                $checkParams[] = $employee_data['pan_number'];
            }
            
            if (isset($employee_data['voter_id_number']) && !empty($employee_data['voter_id_number'])) {
                $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE voter_id_number = ?) as voter_id_count";
                $checkParams[] = $employee_data['voter_id_number'];
            }
            
            if (isset($employee_data['passport_number']) && !empty($employee_data['passport_number'])) {
                $checkQueries[] = "(SELECT COUNT(*) FROM users WHERE passport_number = ?) as passport_count";
                $checkParams[] = $employee_data['passport_number'];
            }
            
            $checkQuery = "SELECT " . implode(", ", $checkQueries);
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute($checkParams);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // Prepare detailed error message if duplicates exist
            $duplicateErrors = [];
            if ($checkResult['email_count'] > 0) $duplicateErrors[] = "Email";
            if ($checkResult['mobile_count'] > 0) $duplicateErrors[] = "Mobile Number";
            if ($checkResult['bank_account_count'] > 0) $duplicateErrors[] = "Bank Account Number";
            
            // Check optional fields only if they were checked
            if (isset($checkResult['aadhar_count']) && $checkResult['aadhar_count'] > 0) {
                $duplicateErrors[] = "Aadhar Number";
            }
            if (isset($checkResult['pan_count']) && $checkResult['pan_count'] > 0) {
                $duplicateErrors[] = "PAN Number";
            }
            if (isset($checkResult['voter_id_count']) && $checkResult['voter_id_count'] > 0) {
                $duplicateErrors[] = "Voter ID Number";
            }
            if (isset($checkResult['passport_count']) && $checkResult['passport_count'] > 0) {
                $duplicateErrors[] = "Passport Number";
            }

            if (!empty($duplicateErrors)) {
                throw new Exception("Duplicate entries found: " . implode(", ", $duplicateErrors));
            }

            // Prepare insertion query
            // Keep all data including NULLs (MySQL handles NULLs properly in unique constraints)
            $columns = implode(', ', array_keys($employee_data));
            $placeholders = implode(', ', array_fill(0, count($employee_data), '?'));
            $values = array_values($employee_data);

            $query = "INSERT INTO users ($columns) VALUES ($placeholders)";
            $stmt = $db->prepare($query);
            $result = $stmt->execute($values);

            // Explicitly fetch the last inserted ID
            $userId = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();

            // Insert family references
            foreach ($family_references as $index => $ref_data) {
                $refIndex = $index + 1;
                
                // Skip Reference 2 if all fields are empty
                if ($refIndex == 2 && empty($ref_data['name']) && empty($ref_data['relation']) && 
                    empty($ref_data['mobile_primary']) && empty($ref_data['address'])) {
                    continue;
                }
                
                $ref_query = "INSERT INTO employee_family_references 
                    (employee_id, reference_index, name, relation, mobile_primary, mobile_secondary, address, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $ref_stmt = $db->prepare($ref_query);
                $ref_result = $ref_stmt->execute([
                    $userId,
                    $refIndex, // reference_index (1 or 2)
                    sanitize_input($ref_data['name']),
                    sanitize_input($ref_data['relation']),
                    sanitize_input($ref_data['mobile_primary']),
                    !empty($ref_data['mobile_secondary']) ? sanitize_input($ref_data['mobile_secondary']) : null,
                    sanitize_input($ref_data['address']),
                    $userId // created_by (using the employee's own ID)
                ]);
                
                if (!$ref_result) {
                    throw new Exception("Failed to insert family reference " . $refIndex);
                }
            }

            // Commit transaction
            $db->commit();

            if ($result) {
                log_error("Employee enrolled successfully", [
                    'employee_data' => $employee_data,
                    'last_insert_id' => $userId
                ]);

                json_response([
                    'success' => true, 
                    'message' => 'Employee enrolled successfully',
                    'employee_id' => $userId
                ]);
            } else {
                log_error("Failed to enroll employee", ['query' => $query, 'values' => $values]);
                json_response([
                    'success' => false, 
                    'message' => 'Failed to enroll employee'
                ], 500);
            }
        } catch (PDOException $e) {
            // Rollback transaction
            $db->rollback();

            log_error("Database insertion error", [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'query' => $query,
                'values' => $values
            ]);

            // Provide specific user-friendly error messages based on the error
            $errorMessage = 'An unexpected database error occurred. Please try again.';
            $errorDetails = $e->getMessage();
            
            if ($e->getCode() === '23000') {
                // Integrity constraint violation - analyze the specific constraint
                if (strpos($errorDetails, 'email_id') !== false) {
                    $errorMessage = 'An employee with this email address already exists. Please use a different email.';
                } elseif (strpos($errorDetails, 'mobile_number') !== false) {
                    $errorMessage = 'An employee with this mobile number already exists. Please use a different mobile number.';
                } elseif (strpos($errorDetails, 'aadhar_number') !== false) {
                    $errorMessage = 'An employee with this Aadhar number already exists. Please check the Aadhar number.';
                } elseif (strpos($errorDetails, 'pan_number') !== false) {
                    $errorMessage = 'An employee with this PAN number already exists. Please check the PAN number.';
                } elseif (strpos($errorDetails, 'voter_id_number') !== false) {
                    $errorMessage = 'An employee with this Voter ID number already exists. Please check the Voter ID number.';
                } elseif (strpos($errorDetails, 'passport_number') !== false) {
                    $errorMessage = 'An employee with this Passport number already exists. Please check the Passport number.';
                } elseif (strpos($errorDetails, 'bank_account_number') !== false) {
                    $errorMessage = 'An employee with this bank account number already exists. Please check the bank account number.';
                } else {
                    $errorMessage = 'An employee with similar details already exists. Please check all the information.';
                }
            } elseif ($e->getCode() === '22003') {
                // Numeric value out of range
                if (strpos($errorDetails, 'salary') !== false) {
                    $errorMessage = 'The salary amount is too large. Please enter a valid salary amount (maximum: 99,999,999.99).';
                } else {
                    $errorMessage = 'One of the numeric values is out of range. Please check all numeric fields.';
                }
            } elseif ($e->getCode() === '22001') {
                // Data too long for column
                $errorMessage = 'One of the entered values is too long. Please check all fields and reduce the length.';
            }

            json_response([
                'success' => false, 
                'message' => $errorMessage
            ], 500);
        }

    } catch (Exception $e) {
        log_error("Enrollment process error", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        json_response([
            'success' => false, 
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}

function get_family_references() {
    try {
        $employee_id = $_GET['employee_id'] ?? null;
        
        if (!$employee_id) {
            json_response(['success' => false, 'message' => 'Employee ID is required'], 400);
        }
        
        $db = new Database();
        
        $query = "SELECT * FROM employee_family_references 
                  WHERE employee_id = ? 
                  ORDER BY reference_index ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$employee_id]);
        $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        json_response([
            'success' => true,
            'references' => $references
        ]);
        
    } catch (Exception $e) {
        log_error("Error getting family references", [
            'message' => $e->getMessage(),
            'employee_id' => $employee_id ?? 'unknown'
        ]);
        json_response([
            'success' => false,
            'message' => 'Failed to retrieve family references: ' . $e->getMessage()
        ], 500);
    }
}

function update_family_references() {
    try {
        $employee_id = $_POST['employee_id'] ?? null;
        
        if (!$employee_id) {
            json_response(['success' => false, 'message' => 'Employee ID is required'], 400);
        }
        
        $db = new Database();
        
        // Validate family references
        $family_references = [];
        $validation_errors = [];
        for ($i = 1; $i <= 2; $i++) {
            $ref_prefix = "family_ref_{$i}_";
            $ref_data = [
                'name' => $_POST[$ref_prefix . 'name'] ?? '',
                'relation' => $_POST[$ref_prefix . 'relation'] ?? '',
                'mobile_primary' => $_POST[$ref_prefix . 'mobile_primary'] ?? '',
                'mobile_secondary' => $_POST[$ref_prefix . 'mobile_secondary'] ?? '',
                'address' => $_POST[$ref_prefix . 'address'] ?? ''
            ];
            
            // Reference 1 is required
            if ($i == 1) {
                if (empty($ref_data['name'])) {
                    $validation_errors[] = "Family Reference 1: Name is required";
                }
                if (empty($ref_data['relation'])) {
                    $validation_errors[] = "Family Reference 1: Relation is required";
                }
                if (empty($ref_data['mobile_primary'])) {
                    $validation_errors[] = "Family Reference 1: Primary mobile number is required";
                } elseif (!preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_primary'])) {
                    $validation_errors[] = "Family Reference 1: Primary mobile number must be 10-15 digits";
                }
                if (!empty($ref_data['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_secondary'])) {
                    $validation_errors[] = "Family Reference 1: Secondary mobile number must be 10-15 digits";
                }
                if (empty($ref_data['address'])) {
                    $validation_errors[] = "Family Reference 1: Address is required";
                }
            }
            
            // Reference 2 is optional - if any field is provided, all required fields must be filled
            if ($i == 2) {
                $hasAnyField = !empty($ref_data['name']) || !empty($ref_data['relation']) || 
                             !empty($ref_data['mobile_primary']) || !empty($ref_data['address']);
                
                if ($hasAnyField) {
                    if (empty($ref_data['name'])) {
                        $validation_errors[] = "Family Reference 2: Name is required when other fields are provided";
                    }
                    if (empty($ref_data['relation'])) {
                        $validation_errors[] = "Family Reference 2: Relation is required when other fields are provided";
                    }
                    if (empty($ref_data['mobile_primary'])) {
                        $validation_errors[] = "Family Reference 2: Primary mobile number is required when other fields are provided";
                    } elseif (!preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_primary'])) {
                        $validation_errors[] = "Family Reference 2: Primary mobile number must be 10-15 digits";
                    }
                    if (!empty($ref_data['mobile_secondary']) && !preg_match('/^[0-9]{10,15}$/', $ref_data['mobile_secondary'])) {
                        $validation_errors[] = "Family Reference 2: Secondary mobile number must be 10-15 digits";
                    }
                    if (empty($ref_data['address'])) {
                        $validation_errors[] = "Family Reference 2: Address is required when other fields are provided";
                    }
                }
            }
            
            $family_references[] = $ref_data;
        }
        
        if (!empty($validation_errors)) {
            json_response([
                'success' => false,
                'message' => implode(', ', $validation_errors)
            ], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete existing references
            $delete_query = "DELETE FROM employee_family_references WHERE employee_id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->execute([$employee_id]);
            
            // Insert updated references
            foreach ($family_references as $index => $ref_data) {
                $refIndex = $index + 1;
                
                // Skip Reference 2 if all fields are empty
                if ($refIndex == 2 && empty($ref_data['name']) && empty($ref_data['relation']) && 
                    empty($ref_data['mobile_primary']) && empty($ref_data['address'])) {
                    continue;
                }
                
                $ref_query = "INSERT INTO employee_family_references 
                    (employee_id, reference_index, name, relation, mobile_primary, mobile_secondary, address, updated_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $ref_stmt = $db->prepare($ref_query);
                $ref_result = $ref_stmt->execute([
                    $employee_id,
                    $refIndex, // reference_index (1 or 2)
                    sanitize_input($ref_data['name']),
                    sanitize_input($ref_data['relation']),
                    sanitize_input($ref_data['mobile_primary']),
                    !empty($ref_data['mobile_secondary']) ? sanitize_input($ref_data['mobile_secondary']) : null,
                    sanitize_input($ref_data['address']),
                    $employee_id // updated_by (using the employee's own ID)
                ]);
                
                if (!$ref_result) {
                    throw new Exception("Failed to update family reference " . $refIndex);
                }
            }
            
            // Commit transaction
            $db->commit();
            
            json_response([
                'success' => true,
                'message' => 'Family references updated successfully'
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        log_error("Error updating family references", [
            'message' => $e->getMessage(),
            'employee_id' => $employee_id ?? 'unknown'
        ]);
        json_response([
            'success' => false,
            'message' => 'Failed to update family references: ' . $e->getMessage()
        ], 500);
    }
}

// Execute the action
handle_employee_action(); 