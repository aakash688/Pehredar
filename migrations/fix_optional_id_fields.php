<?php
/**
 * Migration: Fix Optional ID Fields - Prevent Duplicate Errors on Blanks
 * Date: 2025-01-27
 * Description: Ensures optional ID fields properly handle NULL values and prevent duplicate errors on blanks
 */

require_once __DIR__ . '/../helpers/database.php';

try {
    $db = new Database();
    
    echo "=== Migration: Fix Optional ID Fields ===\n\n";
    
    // Step 1: Clean up existing empty strings
    echo "1. Cleaning up existing empty strings...\n";
    
    $optional_fields = ['passport_number', 'voter_id_number', 'pf_number', 'esic_number', 'uan_number'];
    
    foreach ($optional_fields as $field) {
        // Convert empty strings to NULL
        $result = $db->query("UPDATE users SET $field = NULL WHERE $field = ''");
        $affected = $result->rowCount();
        echo "✓ Converted $affected empty strings to NULL for $field\n";
        
        // Also convert whitespace-only strings to NULL
        $result = $db->query("UPDATE users SET $field = NULL WHERE $field IS NOT NULL AND TRIM($field) = ''");
        $affected = $result->rowCount();
        echo "✓ Converted $affected whitespace-only strings to NULL for $field\n";
    }
    
    // Step 2: Add missing UNIQUE constraint for uan_number
    echo "\n2. Adding missing UNIQUE constraint for uan_number...\n";
    
    try {
        $db->query("ALTER TABLE users ADD UNIQUE KEY unique_uan_number (uan_number)");
        echo "✓ Added UNIQUE constraint for uan_number\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "✓ UNIQUE constraint for uan_number already exists\n";
        } else {
            echo "✗ Error adding UNIQUE constraint for uan_number: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 3: Verify all constraints are in place
    echo "\n3. Verifying UNIQUE constraints...\n";
    
    $result = $db->query("SHOW INDEX FROM users WHERE Non_unique = 0");
    $unique_indexes = [];
    while ($row = $result->fetch()) {
        $unique_indexes[$row['Key_name']][] = $row['Column_name'];
    }
    
    foreach ($optional_fields as $field) {
        $has_unique = false;
        foreach ($unique_indexes as $index_name => $columns) {
            if (in_array($field, $columns)) {
                $has_unique = true;
                echo "✓ $field has UNIQUE constraint (index: $index_name)\n";
                break;
            }
        }
        if (!$has_unique) {
            echo "✗ $field missing UNIQUE constraint\n";
        }
    }
    
    // Step 4: Test the fix
    echo "\n4. Testing the fix...\n";
    
    try {
        $db->beginTransaction();
        
        // Test 1: Insert two users with all optional fields as NULL
        $stmt = $db->prepare("INSERT INTO users (first_name, surname, date_of_birth, gender, mobile_number, email_id, address, permanent_address, aadhar_number, pan_number, date_of_joining, user_type, salary, bank_account_number, ifsc_code, bank_name, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $test_data1 = [
            'Test', 'User1', '1990-01-01', 'Male', '9876543210', 'test1@example.com', 
            'Test Address', 'Test Address', '123456789012', 'ABCDE1234F', date('Y-m-d'), 'Guard', '25000.00', 
            '1234567890', 'SBIN0001234', 'Test Bank', password_hash('test123', PASSWORD_DEFAULT)
        ];
        
        $stmt->execute($test_data1);
        $id1 = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        echo "✓ Inserted first test user with NULL optional fields, ID: $id1\n";
        
        $test_data2 = [
            'Test', 'User2', '1990-01-01', 'Male', '9876543211', 'test2@example.com', 
            'Test Address', 'Test Address', '123456789013', 'ABCDE1235F', date('Y-m-d'), 'Guard', '25000.00', 
            '1234567891', 'SBIN0001234', 'Test Bank', password_hash('test123', PASSWORD_DEFAULT)
        ];
        
        $stmt->execute($test_data2);
        $id2 = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        echo "✓ Inserted second test user with NULL optional fields, ID: $id2\n";
        
        // Test 2: Try to insert duplicate non-empty values
        $stmt2 = $db->prepare("INSERT INTO users (first_name, surname, date_of_birth, gender, mobile_number, email_id, address, permanent_address, aadhar_number, pan_number, date_of_joining, user_type, salary, bank_account_number, ifsc_code, bank_name, password, passport_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $test_data3 = [
            'Test', 'User3', '1990-01-01', 'Male', '9876543212', 'test3@example.com', 
            'Test Address', 'Test Address', '123456789014', 'ABCDE1236F', date('Y-m-d'), 'Guard', '25000.00', 
            '1234567892', 'SBIN0001234', 'Test Bank', password_hash('test123', PASSWORD_DEFAULT), 'PASS123456'
        ];
        
        $stmt2->execute($test_data3);
        $id3 = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        echo "✓ Inserted third test user with passport_number, ID: $id3\n";
        
        // Try to insert duplicate passport number
        try {
            $test_data4 = [
                'Test', 'User4', '1990-01-01', 'Male', '9876543213', 'test4@example.com', 
                'Test Address', 'Test Address', '123456789015', 'ABCDE1237F', date('Y-m-d'), 'Guard', '25000.00', 
                '1234567893', 'SBIN0001234', 'Test Bank', password_hash('test123', PASSWORD_DEFAULT), 'PASS123456'
            ];
            
            $stmt2->execute($test_data4);
            echo "✗ ERROR: Duplicate passport number was allowed!\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "✓ Duplicate passport number correctly rejected\n";
            } else {
                echo "✗ Unexpected error: " . $e->getMessage() . "\n";
            }
        }
        
        $db->commit();
        echo "✓ All tests passed\n";
        
        // Clean up test data
        $db->query("DELETE FROM users WHERE id IN ($id1, $id2, $id3)");
        echo "✓ Test data cleaned up\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "✗ Test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Migration completed successfully ===\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
