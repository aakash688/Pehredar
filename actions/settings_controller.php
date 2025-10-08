<?php
// actions/settings_controller.php

include_once 'helpers/database.php';

// Create a Database instance
$db = new Database();

/**
 * Fetches all company settings from the database.
 * @param PDO $pdo
 * @return array|null The settings as an associative array or null if not found.
 */
function get_company_settings(PDO $pdo) {
    // There should only ever be one row.
    return $pdo->query("SELECT * FROM company_settings ORDER BY id LIMIT 1")->fetch();
}

/**
 * Fetches all HR settings from the database.
 * @param PDO $pdo
 * @return array|null The settings as an associative array or null if not found.
 */
function get_hr_settings(PDO $pdo) {
    // There should only ever be one row.
    return $pdo->query("SELECT * FROM hr_settings ORDER BY id LIMIT 1")->fetch();
}

/**
 * Handles the form submission for saving company settings (Update or Insert).
 * @param PDO $pdo
 * @param array $postData The $_POST superglobal.
 * @param array $filesData The $_FILES superglobal.
 * @return array A result array with success status and message.
 */
function handle_save_company_settings(PDO $pdo, array $postData, array $filesData) {
    try {
        $current_settings = get_company_settings($pdo);
        
        $uploadDir = __DIR__ . '/../Comapany/assets';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $logoPath = $current_settings['logo_path'] ?? null;
    if (isset($filesData['logo']) && $filesData['logo']['error'] === UPLOAD_ERR_OK) {
        if ($logoPath && file_exists($uploadDir . '/' . basename($logoPath))) {
            unlink($uploadDir . '/' . basename($logoPath));
        }
        $logoName = 'logo-' . uniqid() . '-' . basename($filesData['logo']['name']);
        if (move_uploaded_file($filesData['logo']['tmp_name'], "$uploadDir/$logoName")) {
            $logoPath = "Comapany/assets/$logoName";
        }
    }

    $faviconPath = $current_settings['favicon_path'] ?? null;
    if (isset($filesData['favicon']) && $filesData['favicon']['error'] === UPLOAD_ERR_OK) {
        if ($faviconPath && file_exists($uploadDir . '/' . basename($faviconPath))) {
            unlink($uploadDir . '/' . basename($faviconPath));
        }
        $faviconName = 'favicon-' . uniqid() . '-' . basename($filesData['favicon']['name']);
        if (move_uploaded_file($filesData['favicon']['tmp_name'], "$uploadDir/$faviconName")) {
            $faviconPath = "Comapany/assets/$faviconName";
        }
    }

    // Handle signature image upload
    $signatureImagePath = $current_settings['signature_image'] ?? null;
    if (isset($filesData['signature_image']) && $filesData['signature_image']['error'] === UPLOAD_ERR_OK) {
        if ($signatureImagePath && file_exists($uploadDir . '/' . basename($signatureImagePath))) {
            unlink($uploadDir . '/' . basename($signatureImagePath));
        }
        $signatureName = 'signature-' . uniqid() . '-' . basename($filesData['signature_image']['name']);
        if (move_uploaded_file($filesData['signature_image']['tmp_name'], "$uploadDir/$signatureName")) {
            $signatureImagePath = "Comapany/assets/$signatureName";
        }
    }

    // Handle watermark image upload
    $watermarkImagePath = $current_settings['watermark_image_path'] ?? null;
    if (isset($filesData['watermark_image']) && $filesData['watermark_image']['error'] === UPLOAD_ERR_OK) {
        if ($watermarkImagePath && file_exists($uploadDir . '/' . basename($watermarkImagePath))) {
            unlink($uploadDir . '/' . basename($watermarkImagePath));
        }
        $watermarkName = 'watermark-' . uniqid() . '-' . basename($filesData['watermark_image']['name']);
        if (move_uploaded_file($filesData['watermark_image']['tmp_name'], "$uploadDir/$watermarkName")) {
            $watermarkImagePath = "Comapany/assets/$watermarkName";
        }
    }

    // Get all the form data
    $companyName = $postData['company_name'] ?? ($current_settings['company_name'] ?? 'GuardSys');
    $gstNumber = $postData['gst_number'] ?? ($current_settings['gst_number'] ?? null);
    $streetAddress = $postData['street_address'] ?? ($current_settings['street_address'] ?? null);
    $city = $postData['city'] ?? ($current_settings['city'] ?? null);
    $state = $postData['state'] ?? ($current_settings['state'] ?? null);
    $pincode = $postData['pincode'] ?? ($current_settings['pincode'] ?? null);
    $email = $postData['email'] ?? ($current_settings['email'] ?? null);
    $phoneNumber = $postData['phone_number'] ?? ($current_settings['phone_number'] ?? null);
    $secondaryPhone = $postData['secondary_phone'] ?? ($current_settings['secondary_phone'] ?? null);
    $primaryColor = $postData['primary_color'] ?? ($current_settings['primary_color'] ?? '#4f46e5');
    
    // New fields
    $bankName = $postData['bank_name'] ?? ($current_settings['bank_name'] ?? null);
    $bankAccountNumber = $postData['bank_account_number'] ?? ($current_settings['bank_account_number'] ?? null);
    $bankIfscCode = $postData['bank_ifsc_code'] ?? ($current_settings['bank_ifsc_code'] ?? null);
    $bankBranch = $postData['bank_branch'] ?? ($current_settings['bank_branch'] ?? null);
    $bankAccountType = $postData['bank_account_type'] ?? ($current_settings['bank_account_type'] ?? null);
    $invoiceNotes = $postData['invoice_notes'] ?? ($current_settings['invoice_notes'] ?? null);
    $invoiceTerms = $postData['invoice_terms'] ?? ($current_settings['invoice_terms'] ?? null);
    
    // Service charges configuration
    $serviceChargesEnabled = isset($postData['service_charges_enabled']) ? 1 : 0;
    $serviceChargesPercentage = $postData['service_charges_percentage'] ?? ($current_settings['service_charges_percentage'] ?? 10.00);

    if ($current_settings) {
        // Update existing settings
        $sql = "UPDATE company_settings SET 
                company_name = ?, 
                gst_number = ?,
                street_address = ?,
                city = ?,
                state = ?,
                pincode = ?,
                email = ?,
                phone_number = ?,
                secondary_phone = ?,
                logo_path = ?, 
                favicon_path = ?, 
                signature_image = ?,
                watermark_image_path = ?,
                bank_name = ?,
                bank_account_number = ?,
                bank_ifsc_code = ?,
                bank_branch = ?,
                bank_account_type = ?,
                invoice_notes = ?,
                invoice_terms = ?,
                service_charges_enabled = ?,
                service_charges_percentage = ?,
                primary_color = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $companyName,
            $gstNumber,
            $streetAddress,
            $city,
            $state,
            $pincode,
            $email,
            $phoneNumber,
            $secondaryPhone,
            $logoPath, 
            $faviconPath, 
            $signatureImagePath,
            $watermarkImagePath,
            $bankName,
            $bankAccountNumber,
            $bankIfscCode,
            $bankBranch,
            $bankAccountType,
            $invoiceNotes,
            $invoiceTerms,
            $serviceChargesEnabled,
            $serviceChargesPercentage,
            $primaryColor, 
            $current_settings['id']
        ]);
    } else {
        // Insert new settings if none exist
        $sql = "INSERT INTO company_settings (
                company_name, 
                gst_number,
                street_address,
                city,
                state,
                pincode,
                email,
                phone_number,
                secondary_phone,
                logo_path, 
                favicon_path, 
                signature_image,
                watermark_image_path,
                bank_name,
                bank_account_number,
                bank_ifsc_code,
                bank_branch,
                bank_account_type,
                invoice_notes,
                invoice_terms,
                service_charges_enabled,
                service_charges_percentage,
                primary_color
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $companyName,
            $gstNumber,
            $streetAddress,
            $city,
            $state,
            $pincode,
            $email,
            $phoneNumber,
            $secondaryPhone,
            $logoPath, 
            $faviconPath, 
            $signatureImagePath,
            $watermarkImagePath,
            $bankName,
            $bankAccountNumber,
            $bankIfscCode,
            $bankBranch,
            $bankAccountType,
            $invoiceNotes,
            $invoiceTerms,
            $serviceChargesEnabled,
            $serviceChargesPercentage,
            $primaryColor
        ]);
    }
    
    return ['success' => true, 'message' => 'Company settings updated successfully!'];
    
    } catch (Exception $e) {
        error_log("Error saving company settings: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error saving settings: ' . $e->getMessage()];
    }
}

/**
 * Handles the form submission for saving HR settings (Update or Insert).
 * @param PDO $pdo
 * @param array $postData The $_POST superglobal.
 * @return array A result array with success status and message.
 */
function handle_save_hr_settings(PDO $pdo, array $postData) {
    $current_settings = get_hr_settings($pdo);

    $overtimeMultiplier = $postData['overtime_multiplier'] ?? ($current_settings['overtime_multiplier'] ?? 1.50);
    $holidayMultiplier = $postData['holiday_multiplier'] ?? ($current_settings['holiday_multiplier'] ?? 2.00);

    if ($current_settings) {
        // Update existing settings
        $sql = "UPDATE hr_settings SET overtime_multiplier = ?, holiday_multiplier = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$overtimeMultiplier, $holidayMultiplier, $current_settings['id']]);
    } else {
        // Insert new settings if none exist
        $sql = "INSERT INTO hr_settings (overtime_multiplier, holiday_multiplier) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$overtimeMultiplier, $holidayMultiplier]);
    }

    return ['success' => true, 'message' => 'HR settings updated successfully!'];
}

/**
 * Update company settings in the database
 */
function updateCompanySettings($db, $data, $files) {
    $response = ['success' => false];
    
    try {
        // Prepare the settings array
        $settings = [
            'company_name' => $data['company_name'] ?? '',
            'company_address' => $data['company_address'] ?? '',
            'company_phone' => $data['company_phone'] ?? '',
            'company_email' => $data['company_email'] ?? '',
            'company_website' => $data['company_website'] ?? ''
        ];
        
        // Handle file uploads if present
        if (!empty($files['company_logo']['name'])) {
            $logo_file = $files['company_logo'];
            $upload_dir = 'uploads/company/';
            $logo_filename = uniqid() . '-' . $logo_file['name'];
            $logo_path = $upload_dir . $logo_filename;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($logo_file['tmp_name'], $logo_path)) {
                $settings['logo_path'] = $logo_path;
            }
        }
        
        if (!empty($files['favicon']['name'])) {
            $favicon_file = $files['favicon'];
            $upload_dir = 'uploads/company/';
            $favicon_filename = uniqid() . '-' . $favicon_file['name'];
            $favicon_path = $upload_dir . $favicon_filename;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($favicon_file['tmp_name'], $favicon_path)) {
                $settings['favicon_path'] = $favicon_path;
            }
        }
        
        // Check if settings record already exists
        $existing = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();
        
        if ($existing) {
            // Update existing settings
            $set_clauses = [];
            $params = [];
            
            foreach ($settings as $key => $value) {
                if (!empty($value) || $value === '0') {  // Handle zero values properly
                    $set_clauses[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (!empty($set_clauses)) {
                $query = "UPDATE company_settings SET " . implode(', ', $set_clauses);
                $db->query($query, $params);
            }
        } else {
            // Insert new settings
            $columns = [];
            $placeholders = [];
            $values = [];
            
            foreach ($settings as $key => $value) {
                if (!empty($value) || $value === '0') {
                    $columns[] = $key;
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }
            
            if (!empty($columns)) {
                $query = "INSERT INTO company_settings (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $db->query($query, $values);
            }
        }
        
        $response = ['success' => true];
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Update HR settings in the database
 */
function updateHRSettings($db, $data) {
    $response = ['success' => false];
    
    try {
        // Prepare the settings array
        $settings = [
            'work_hours_per_day' => $data['work_hours_per_day'] ?? 8,
            'overtime_multiplier' => $data['overtime_multiplier'] ?? 1.5,
            'weekend_overtime_multiplier' => $data['weekend_overtime_multiplier'] ?? 2,
            'holiday_overtime_multiplier' => $data['holiday_overtime_multiplier'] ?? 2.5,
            'attendance_grace_period_minutes' => $data['attendance_grace_period_minutes'] ?? 15
        ];
        
        // Check if settings record already exists
        $existing = $db->query("SELECT * FROM hr_settings LIMIT 1")->fetch();
        
        if ($existing) {
            // Update existing settings
            $set_clauses = [];
            $params = [];
            
            foreach ($settings as $key => $value) {
                $set_clauses[] = "$key = ?";
                $params[] = $value;
            }
            
            if (!empty($set_clauses)) {
                $query = "UPDATE hr_settings SET " . implode(', ', $set_clauses);
                $db->query($query, $params);
            }
        } else {
            // Insert new settings
            $columns = array_keys($settings);
            $placeholders = array_fill(0, count($columns), '?');
            $values = array_values($settings);
            
            $query = "INSERT INTO hr_settings (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $db->query($query, $values);
        }
        
        $response = ['success' => true];
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Assign or update supervisor assignment to a site
 */
function assignSupervisor($db, $data) {
    $response = ['success' => false];
    
    try {
        $site_id = isset($data['site_id']) ? (int)$data['site_id'] : 0;
        $supervisor_id = isset($data['supervisor_id']) ? (int)$data['supervisor_id'] : 0;
        
        if ($site_id <= 0) {
            throw new Exception("Invalid site ID");
        }
        
        // Check if the site exists
        $site = $db->query("SELECT id FROM society_onboarding_data WHERE id = ?", [$site_id])->fetch();
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // If supervisor_id is provided and not 0, verify the supervisor exists and is of correct type
        if ($supervisor_id > 0) {
            $supervisor = $db->query(
                "SELECT id FROM users WHERE id = ? AND user_type IN ('Supervisor', 'Site Supervisor')", 
                [$supervisor_id]
            )->fetch();
            
            if (!$supervisor) {
                throw new Exception("User not found or not a Supervisor or Site Supervisor");
            }
        }
        
        // Check if an assignment already exists
        $existing = $db->query(
            "SELECT id FROM supervisor_site_assignments WHERE site_id = ?", 
            [$site_id]
        )->fetch();
        
        if ($existing) {
            // If supervisor_id is 0 or empty, we want to remove the assignment
            if (empty($supervisor_id)) {
                $db->query(
                    "DELETE FROM supervisor_site_assignments WHERE site_id = ?", 
                    [$site_id]
                );
                $response = ['success' => true, 'message' => 'Supervisor assignment removed'];
            } else {
                // Update the existing assignment
                $db->query(
                    "UPDATE supervisor_site_assignments SET supervisor_id = ? WHERE site_id = ?", 
                    [$supervisor_id, $site_id]
                );
                $response = ['success' => true, 'message' => 'Supervisor assignment updated'];
            }
        } else if (!empty($supervisor_id)) {
            // Create a new assignment if supervisor_id is provided
            $db->query(
                "INSERT INTO supervisor_site_assignments (supervisor_id, site_id) VALUES (?, ?)", 
                [$supervisor_id, $site_id]
            );
            $response = ['success' => true, 'message' => 'Supervisor assigned successfully'];
        } else {
            // No existing assignment and no supervisor_id provided - nothing to do
            $response = ['success' => true, 'message' => 'No change required'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} 