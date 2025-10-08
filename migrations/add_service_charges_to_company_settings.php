<?php
// migrations/add_service_charges_to_company_settings.php
require_once __DIR__ . '/../helpers/database.php';

echo "Adding service charges columns to company_settings table...\n";

$db = new Database();

try {
    // Check if columns already exist
    $result = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_enabled'");
    if ($result->rowCount() == 0) {
        $db->query("ALTER TABLE company_settings ADD COLUMN service_charges_enabled TINYINT(1) DEFAULT 0 COMMENT '1=Service charges enabled, 0=Disabled'");
        echo "✓ Added service_charges_enabled column\n";
    } else {
        echo "✓ service_charges_enabled column already exists\n";
    }
} catch (Exception $e) {
    echo "Error adding service_charges_enabled: " . $e->getMessage() . "\n";
}

try {
    $result = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_percentage'");
    if ($result->rowCount() == 0) {
        $db->query("ALTER TABLE company_settings ADD COLUMN service_charges_percentage DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Service charges percentage (e.g., 10.00 for 10%)'");
        echo "✓ Added service_charges_percentage column\n";
    } else {
        echo "✓ service_charges_percentage column already exists\n";
    }
} catch (Exception $e) {
    echo "Error adding service_charges_percentage: " . $e->getMessage() . "\n";
}

echo "\n✅ Migration completed successfully!\n";
echo "The company_settings table now has the required service charges columns.\n";
?>
