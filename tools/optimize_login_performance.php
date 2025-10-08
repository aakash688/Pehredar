<?php
/**
 * Optimize Login Performance
 * 
 * Specifically targets the login API performance issues
 */

require_once __DIR__ . '/../helpers/ConnectionPool.php';

echo "=== LOGIN PERFORMANCE OPTIMIZATION ===\n\n";

try {
    $conn = ConnectionPool::getConnection();
    
    // Test 1: Check current login query performance
    echo "--- TESTING CURRENT LOGIN QUERY ---\n";
    $startTime = microtime(true);
    
    $sql = "SELECT id, username, email, phone, password_hash, password_salt, 
                   name, society_id, is_primary
            FROM clients_users 
            WHERE username = 'test' OR email = 'test' OR phone = 'test' 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $endTime = microtime(true);
    $queryTime = ($endTime - $startTime) * 1000;
    
    echo "Current query time: " . round($queryTime, 2) . "ms\n";
    
    if ($result) {
        echo "✅ Query returned result: " . $result['username'] . "\n";
    } else {
        echo "ℹ️  Query returned no result (expected for test)\n";
    }
    
    // Test 2: Check if indexes exist
    echo "\n--- CHECKING EXISTING INDEXES ---\n";
    
    $indexSql = "SHOW INDEX FROM clients_users";
    $indexStmt = $conn->prepare($indexSql);
    $indexStmt->execute();
    $indexes = $indexStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasUsernameIndex = false;
    $hasEmailIndex = false;
    $hasPhoneIndex = false;
    $hasCompositeIndex = false;
    
    foreach ($indexes as $index) {
        if ($index['Column_name'] === 'username' && $index['Key_name'] !== 'PRIMARY') {
            $hasUsernameIndex = true;
        }
        if ($index['Column_name'] === 'email' && $index['Key_name'] !== 'PRIMARY') {
            $hasEmailIndex = true;
        }
        if ($index['Column_name'] === 'phone' && $index['Key_name'] !== 'PRIMARY') {
            $hasPhoneIndex = true;
        }
        if (strpos($index['Key_name'], 'login') !== false) {
            $hasCompositeIndex = true;
        }
    }
    
    echo "Username index: " . ($hasUsernameIndex ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "Email index: " . ($hasEmailIndex ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "Phone index: " . ($hasPhoneIndex ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "Composite login index: " . ($hasCompositeIndex ? "✅ EXISTS" : "❌ MISSING") . "\n";
    
    // Test 3: Add missing indexes
    echo "\n--- ADDING MISSING INDEXES ---\n";
    
    if (!$hasUsernameIndex) {
        echo "Adding username index...\n";
        $conn->exec("CREATE INDEX idx_clients_users_username ON clients_users(username)");
        echo "✅ Username index added\n";
    }
    
    if (!$hasEmailIndex) {
        echo "Adding email index...\n";
        $conn->exec("CREATE INDEX idx_clients_users_email ON clients_users(email)");
        echo "✅ Email index added\n";
    }
    
    if (!$hasPhoneIndex) {
        echo "Adding phone index...\n";
        $conn->exec("CREATE INDEX idx_clients_users_phone ON clients_users(phone)");
        echo "✅ Phone index added\n";
    }
    
    if (!$hasCompositeIndex) {
        echo "Adding composite login index...\n";
        $conn->exec("CREATE INDEX idx_clients_users_login ON clients_users(username, email, phone)");
        echo "✅ Composite login index added\n";
    }
    
    // Test 4: Test optimized query performance
    echo "\n--- TESTING OPTIMIZED QUERY ---\n";
    $startTime = microtime(true);
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $endTime = microtime(true);
    $optimizedTime = ($endTime - $startTime) * 1000;
    
    echo "Optimized query time: " . round($optimizedTime, 2) . "ms\n";
    
    if ($result) {
        echo "✅ Query returned result: " . $result['username'] . "\n";
    } else {
        echo "ℹ️  Query returned no result (expected for test)\n";
    }
    
    // Test 5: Test alternative query strategies
    echo "\n--- TESTING ALTERNATIVE QUERY STRATEGIES ---\n";
    
    // Strategy 1: Separate queries with early exit
    $startTime = microtime(true);
    
    $client = null;
    
    // Try username first (most common)
    $stmt = $conn->prepare("SELECT id, username, email, phone, password_hash, password_salt, name, society_id, is_primary FROM clients_users WHERE username = ? LIMIT 1");
    $stmt->execute(['test']);
    $client = $stmt->fetch();
    
    if (!$client) {
        // Try email
        $stmt = $conn->prepare("SELECT id, username, email, phone, password_hash, password_salt, name, society_id, is_primary FROM clients_users WHERE email = ? LIMIT 1");
        $stmt->execute(['test']);
        $client = $stmt->fetch();
    }
    
    if (!$client) {
        // Try phone
        $stmt = $conn->prepare("SELECT id, username, email, phone, password_hash, password_salt, name, society_id, is_primary FROM clients_users WHERE phone = ? LIMIT 1");
        $stmt->execute(['test']);
        $client = $stmt->fetch();
    }
    
    $endTime = microtime(true);
    $alternativeTime = ($endTime - $startTime) * 1000;
    
    echo "Alternative strategy time: " . round($alternativeTime, 2) . "ms\n";
    
    // Test 6: Performance comparison
    echo "\n--- PERFORMANCE COMPARISON ---\n";
    echo "Original query: " . round($queryTime, 2) . "ms\n";
    echo "With indexes: " . round($optimizedTime, 2) . "ms\n";
    echo "Alternative strategy: " . round($alternativeTime, 2) . "ms\n";
    
    $improvement = (($queryTime - $optimizedTime) / $queryTime) * 100;
    echo "Improvement with indexes: " . round($improvement, 2) . "%\n";
    
    // Test 7: Check society query performance
    echo "\n--- TESTING SOCIETY QUERY ---\n";
    
    if ($client && isset($client['society_id'])) {
        $startTime = microtime(true);
        
        $stmt = $conn->prepare("SELECT id, society_name FROM society_onboarding_data WHERE id = ? LIMIT 1");
        $stmt->execute([$client['society_id']]);
        $society = $stmt->fetch();
        
        $endTime = microtime(true);
        $societyTime = ($endTime - $startTime) * 1000;
        
        echo "Society query time: " . round($societyTime, 2) . "ms\n";
        
        if ($society) {
            echo "✅ Society found: " . $society['society_name'] . "\n";
        }
    }
    
    // Test 8: Check if society table has proper indexes
    echo "\n--- CHECKING SOCIETY TABLE INDEXES ---\n";
    
    $indexSql = "SHOW INDEX FROM society_onboarding_data";
    $indexStmt = $conn->prepare($indexSql);
    $indexStmt->execute();
    $societyIndexes = $indexStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasSocietyIdIndex = false;
    foreach ($societyIndexes as $index) {
        if ($index['Column_name'] === 'id' && $index['Key_name'] !== 'PRIMARY') {
            $hasSocietyIdIndex = true;
        }
    }
    
    echo "Society ID index: " . ($hasSocietyIdIndex ? "✅ EXISTS" : "❌ MISSING") . "\n";
    
    if (!$hasSocietyIdIndex) {
        echo "Adding society ID index...\n";
        $conn->exec("CREATE INDEX idx_society_onboarding_id ON society_onboarding_data(id)");
        echo "✅ Society ID index added\n";
    }
    
    echo "\n=== OPTIMIZATION COMPLETE ===\n";
    echo "Expected login performance improvement: " . round($improvement, 2) . "%\n";
    echo "Target response time: < 10ms\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
