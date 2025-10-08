<?php
// schema/update_invoices_schema.php
// A script to add GST fields to the invoices table

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/database.php';

echo "<pre>"; // For better formatting of output

try {
    $pdo = get_db_connection();
    echo "Database connection successful.\n";

    // Add GST fields to invoices table
    $fields = [
        'is_gst_applicable' => "ADD COLUMN is_gst_applicable TINYINT(1) DEFAULT 1 COMMENT '1=GST Applicable, 0=GST Not Applicable'",
        'gst_amount' => "ADD COLUMN gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'total_with_gst' => "ADD COLUMN total_with_gst DECIMAL(12,2) NOT NULL DEFAULT 0.00"
    ];
    
    foreach ($fields as $field => $query_part) {
        try {
            $pdo->exec("ALTER TABLE invoices $query_part");
            echo "SUCCESS: Added '$field' field to invoices table.\n";
        } catch (PDOException $e) {
            // Check if error is because column already exists
            if ($e->getCode() == '42S21') {
                echo "INFO: Column '$field' already exists in invoices table.\n";
            } else {
                throw $e;
            }
        }
    }

    // Update existing invoices with GST calculation (18%)
    $query = "UPDATE invoices SET 
              gst_amount = amount * 0.18,
              total_with_gst = amount * 1.18";
    $pdo->exec($query);
    echo "SUCCESS: Updated existing invoices with GST calculations (18%).\n";

    echo "\nSchema update completed successfully!";

} catch (PDOException $e) {
    die("Database update failed: " . $e->getMessage());
}

echo "</pre>"; 
?> 