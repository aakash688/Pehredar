<?php
// schema/update_society_schema.php
// A script to add the GST field to the society_onboarding_data table

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/database.php';

echo "<pre>"; // For better formatting of output

try {
    $pdo = get_db_connection();
    echo "Database connection successful.\n";

    // Add GST field to society_onboarding_data table
    $query = "ALTER TABLE society_onboarding_data 
              ADD COLUMN is_gst_applicable TINYINT(1) DEFAULT 1 
              COMMENT '1=GST Applicable, 0=GST Not Applicable'";
    
    try {
        $pdo->exec($query);
        echo "SUCCESS: Added 'is_gst_applicable' field to society_onboarding_data table.\n";
    } catch (PDOException $e) {
        // Check if error is because column already exists
        if ($e->getCode() == '42S21') {
            echo "INFO: Column 'is_gst_applicable' already exists in society_onboarding_data table.\n";
        } else {
            throw $e;
        }
    }

    // Update existing entries - set compliant societies to have GST
    $query = "UPDATE society_onboarding_data SET is_gst_applicable = compliance_status";
    $pdo->exec($query);
    echo "SUCCESS: Updated GST settings based on compliance status.\n";

    echo "\nSchema update completed successfully!";

} catch (PDOException $e) {
    die("Database update failed: " . $e->getMessage());
}

echo "</pre>"; 
?> 