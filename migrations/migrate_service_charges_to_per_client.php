<?php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/ConnectionPool.php';

echo "Starting migration: Migrate Service Charges to Per-Client Logic...\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // Step 1: Add sequence column to invoice_items table
    echo "Step 1: Adding sequence column to invoice_items...\n";
    
    // Check if sequence column already exists
    $columns = $db->query("SHOW COLUMNS FROM invoice_items LIKE 'sequence'")->fetchAll();
    if (count($columns) === 0) {
        $sql_sequence = "ALTER TABLE invoice_items ADD COLUMN sequence INT DEFAULT NULL AFTER total";
        $pdo->exec($sql_sequence);
        echo "✅ Successfully added sequence column to invoice_items.\n";
    } else {
        echo "✅ Sequence column already exists in invoice_items.\n";
    }

    // Step 2: Remove service charge fields from company_settings table
    echo "Step 2: Removing service charge fields from company_settings...\n";
    
    // Check if service_charges_percentage column exists
    $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_percentage'")->fetchAll();
    if (count($columns) > 0) {
        $sql_drop_percentage = "ALTER TABLE company_settings DROP COLUMN service_charges_percentage";
        $pdo->exec($sql_drop_percentage);
        echo "✅ Successfully removed service_charges_percentage column from company_settings.\n";
    } else {
        echo "✅ service_charges_percentage column already removed from company_settings.\n";
    }
    
    // Check if service_charges_enabled column exists
    $columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_enabled'")->fetchAll();
    if (count($columns) > 0) {
        $sql_drop_enabled = "ALTER TABLE company_settings DROP COLUMN service_charges_enabled";
        $pdo->exec($sql_drop_enabled);
        echo "✅ Successfully removed service_charges_enabled column from company_settings.\n";
    } else {
        echo "✅ service_charges_enabled column already removed from company_settings.\n";
    }

    // Step 3: Update existing invoice items to have proper sequence
    echo "Step 3: Updating existing invoice items with sequence values...\n";
    
    // Get all invoices and update their items with sequence
    $invoices = $db->query("SELECT id FROM invoices")->fetchAll();
    $updated_count = 0;
    
    foreach ($invoices as $invoice) {
        $items = $db->query("SELECT id, employee_type FROM invoice_items WHERE invoice_id = ? ORDER BY id", [$invoice['id']])->fetchAll();
        
        $sequence = 1;
        foreach ($items as $item) {
            // Set sequence, with service charges getting higher sequence numbers
            $item_sequence = $sequence;
            if (strpos($item['employee_type'], 'Service Charges') !== false) {
                $item_sequence = 9999; // Force service charges to be last
            }
            
            $stmt = $pdo->prepare("UPDATE invoice_items SET sequence = ? WHERE id = ?");
            $stmt->execute([$item_sequence, $item['id']]);
            $sequence++;
        }
        $updated_count++;
    }
    
    echo "✅ Updated sequence for {$updated_count} invoices.\n";

    // Step 4: Verification
    echo "Step 4: Verification...\n";
    
    // Verify company_settings columns are removed
    $company_columns = $db->query("SHOW COLUMNS FROM company_settings LIKE 'service_charges_%'")->fetchAll();
    if (count($company_columns) === 0) {
        echo "✅ Company settings service charge columns successfully removed.\n";
    } else {
        echo "⚠️  Warning: Some service charge columns still exist in company_settings.\n";
    }
    
    // Verify sequence column exists
    $sequence_columns = $db->query("SHOW COLUMNS FROM invoice_items LIKE 'sequence'")->fetchAll();
    if (count($sequence_columns) > 0) {
        echo "✅ Sequence column successfully added to invoice_items.\n";
    } else {
        echo "❌ Error: Sequence column not found in invoice_items.\n";
    }
    
    // Verify society_onboarding_data still has service charge fields
    $society_columns = $db->query("SHOW COLUMNS FROM society_onboarding_data LIKE 'service_charges_%'")->fetchAll();
    if (count($society_columns) >= 2) {
        echo "✅ Society onboarding service charge fields preserved.\n";
    } else {
        echo "❌ Error: Society onboarding service charge fields missing.\n";
    }

    echo "\n✅ Migration completed successfully!\n";
    echo "Service charges are now fully managed per-client with sequence control.\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    error_log("Migration failed: " . $e->getMessage());
    exit(1);
}
?>
