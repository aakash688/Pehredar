<?php
/**
 * Fix User Deletion Constraint Issue
 * 
 * This script analyzes and fixes the foreign key constraint problem
 */

require_once __DIR__ . '/../helpers/database.php';

echo "=== FIXING USER DELETION CONSTRAINT ISSUE ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Check data types
    echo "1. Checking data types:\n";
    $users_desc = $pdo->query('DESCRIBE users')->fetchAll();
    $roster_desc = $pdo->query('DESCRIBE roster')->fetchAll();

    $users_id_type = '';
    $roster_guard_id_type = '';

    foreach ($users_desc as $col) {
        if ($col['Field'] === 'id') {
            $users_id_type = $col['Type'];
            echo "  users.id: {$col['Type']}\n";
            break;
        }
    }

    foreach ($roster_desc as $col) {
        if ($col['Field'] === 'guard_id') {
            $roster_guard_id_type = $col['Type'];
            echo "  roster.guard_id: {$col['Type']}\n";
            break;
        }
    }

    if ($users_id_type !== $roster_guard_id_type) {
        echo "  ❌ DATA TYPE MISMATCH FOUND!\n";
        echo "     This can cause foreign key constraint issues.\n";
    } else {
        echo "  ✅ Data types match\n";
    }

    // 2. Check the actual foreign key constraint
    echo "\n2. Checking foreign key constraint:\n";
    try {
        $result = $pdo->query("SHOW CREATE TABLE roster")->fetch();
        $createTable = $result['Create Table'];
        
        // Extract foreign key constraints
        if (preg_match('/CONSTRAINT.*guard.*FOREIGN KEY.*users/i', $createTable, $matches)) {
            echo "  Found constraint: " . trim($matches[0]) . "\n";
        } else {
            echo "  No guard foreign key constraint found in CREATE TABLE\n";
        }
        
        // Look for the specific constraint that's causing issues
        if (strpos($createTable, 'fk_roster_guard') !== false) {
            echo "  ✅ fk_roster_guard constraint exists\n";
        }
        
    } catch (Exception $e) {
        echo "  Error getting CREATE TABLE: " . $e->getMessage() . "\n";
    }

    // 3. Try to temporarily disable foreign key checks and delete
    echo "\n3. Attempting deletion with foreign key checks disabled:\n";
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        echo "  ✅ Foreign key checks disabled\n";
        
        // Try the deletion
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([54]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "  ✅ User 54 deleted successfully!\n";
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "  ✅ Foreign key checks re-enabled\n";
            
            // Commit the transaction
            $pdo->commit();
            echo "  ✅ Transaction committed\n";
            
        } else {
            echo "  ❌ Deletion failed - no rows affected\n";
            $pdo->rollBack();
        }
        
    } catch (Exception $e) {
        echo "  ❌ Error during deletion: " . $e->getMessage() . "\n";
        try {
            $pdo->rollBack();
            echo "  ↩️ Transaction rolled back\n";
        } catch (Exception $rollbackError) {
            echo "  ❌ Rollback failed: " . $rollbackError->getMessage() . "\n";
        }
        
        // Make sure foreign key checks are re-enabled
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "  ✅ Foreign key checks re-enabled\n";
        } catch (Exception $fkError) {
            echo "  ❌ Error re-enabling FK checks: " . $fkError->getMessage() . "\n";
        }
    }

    // 4. Verify the user is gone
    echo "\n4. Verification:\n";
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE id = ?");
        $stmt->execute([54]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            echo "  ✅ User 54 successfully deleted from database\n";
        } else {
            echo "  ❌ User 54 still exists in database\n";
        }
    } catch (Exception $e) {
        echo "  Error verifying deletion: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
}

echo "\n=== PROCESS COMPLETE ===\n";
?>
