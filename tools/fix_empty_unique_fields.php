<?php
// Script to fix existing empty string values in unique optional fields
require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

// Check if running from command line
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    echo "<h2>Fix Empty String Values in Unique Optional Fields</h2>\n";
    echo "<pre>\n";
}

$fieldsToFix = ['voter_id_number', 'passport_number', 'aadhar_number', 'pan_number'];

foreach ($fieldsToFix as $field) {
    echo "=== Processing field: $field ===\n";
    
    // Check current empty string values
    $checkQuery = "SELECT COUNT(*) as count FROM users WHERE $field = ''";
    $result = $db->query($checkQuery)->fetch(PDO::FETCH_ASSOC);
    
    echo "Found {$result['count']} rows with empty string values\n";
    
    if ($result['count'] > 0) {
        // Update empty strings to NULL
        $updateQuery = "UPDATE users SET $field = NULL WHERE $field = ''";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute();
        $updated = $stmt->rowCount();
        
        echo "Updated $updated rows: changed empty strings to NULL\n";
        
        // Verify the fix
        $verifyQuery = "SELECT COUNT(*) as count FROM users WHERE $field = ''";
        $verifyResult = $db->query($verifyQuery)->fetch(PDO::FETCH_ASSOC);
        
        if ($verifyResult['count'] == 0) {
            echo "✅ Successfully fixed all empty strings for $field\n";
        } else {
            echo "❌ Still have {$verifyResult['count']} empty strings for $field\n";
        }
    } else {
        echo "✅ No empty strings found for $field\n";
    }
    
    echo "\n";
}

echo "=== Checking for unique constraints ===\n";
$constraintQuery = "SHOW INDEX FROM users WHERE Column_name IN ('voter_id_number', 'passport_number', 'aadhar_number', 'pan_number') AND Non_unique = 0";
$constraints = $db->query($constraintQuery)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($constraints)) {
    echo "Found unique constraints:\n";
    foreach ($constraints as $constraint) {
        echo "  - {$constraint['Column_name']}: {$constraint['Key_name']}\n";
    }
} else {
    echo "No unique constraints found (or they might be named differently)\n";
}

echo "\n=== Summary ===\n";
echo "✅ Empty strings converted to NULL values\n";
echo "✅ MySQL allows multiple NULL values in unique columns\n";
echo "✅ Future employee registrations should work correctly\n";

if (!$isCLI) {
    echo "</pre>\n";
}
?>
