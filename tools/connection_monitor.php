<?php
/**
 * Database Connection Monitor
 * 
 * This script helps monitor the effectiveness of the connection pool optimization.
 * Use this to verify that the "max connections per hour" issue has been resolved.
 */

require_once __DIR__ . '/../helpers/ConnectionPool.php';
require_once __DIR__ . '/../helpers/database.php';

// Set content type for web viewing
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Database Connection Monitor ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Test connection pool
echo "--- Connection Pool Status ---\n";
try {
    $stats = ConnectionPool::getStats();
    echo "Has Active Connection: " . ($stats['has_connection'] ? 'YES' : 'NO') . "\n";
    echo "Connection Age: " . ($stats['connection_age'] ? $stats['connection_age'] . ' seconds' : 'N/A') . "\n";
    echo "Is Persistent: " . ($stats['is_persistent'] ? 'YES' : 'NO') . "\n";
    echo "Server Info: " . ($stats['server_info'] ?: 'N/A') . "\n";
} catch (Exception $e) {
    echo "Error getting pool stats: " . $e->getMessage() . "\n";
}

echo "\n--- Connection Pool Test ---\n";
$start_time = microtime(true);

try {
    // Test multiple connections to verify they reuse the same connection
    $connections = [];
    for ($i = 1; $i <= 5; $i++) {
        $pdo = ConnectionPool::getConnection();
        $connections[] = spl_object_hash($pdo);
        echo "Connection $i: " . substr(spl_object_hash($pdo), -8) . "\n";
        
        // Test that the connection is working
        $result = $pdo->query("SELECT 'Test $i' as test")->fetch();
        echo "  Query result: " . $result['test'] . "\n";
    }
    
    // Check if all connections share the same object hash (reusing connection)
    $unique_connections = array_unique($connections);
    echo "\nTotal unique connection objects: " . count($unique_connections) . "\n";
    if (count($unique_connections) === 1) {
        echo "✅ SUCCESS: All requests reused the same connection!\n";
    } else {
        echo "⚠️  WARNING: Multiple connection objects created\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

$end_time = microtime(true);
echo "\nTotal test time: " . round(($end_time - $start_time) * 1000, 2) . " ms\n";

echo "\n--- Database Helper Test ---\n";
try {
    // Test the Database class wrapper
    $db = new Database();
    $result = $db->query("SELECT 'Database Helper Test' as test")->fetch();
    echo "Database Helper: " . $result['test'] . "\n";
    echo "✅ Database helper working correctly\n";
} catch (Exception $e) {
    echo "❌ Database helper error: " . $e->getMessage() . "\n";
}

echo "\n--- MySQL Process Information ---\n";
try {
    $pdo = ConnectionPool::getConnection();
    
    // Get connection ID
    $connectionId = $pdo->query("SELECT CONNECTION_ID() as id")->fetch();
    echo "Current connection ID: " . $connectionId['id'] . "\n";
    
    // Get process list to see connection usage
    $processes = $pdo->query("SHOW PROCESSLIST")->fetchAll();
    $appConnections = 0;
    foreach ($processes as $process) {
        if (strpos($process['Host'], 'localhost') !== false || 
            strpos($process['Host'], '127.0.0.1') !== false) {
            $appConnections++;
        }
    }
    echo "Active local connections: $appConnections\n";
    
    // Get global status for connections
    $status = $pdo->query("SHOW GLOBAL STATUS LIKE 'Connections'")->fetch();
    echo "Total connections since server start: " . $status['Value'] . "\n";
    
    $threadsConnected = $pdo->query("SHOW GLOBAL STATUS LIKE 'Threads_connected'")->fetch();
    echo "Currently connected threads: " . $threadsConnected['Value'] . "\n";
    
} catch (Exception $e) {
    echo "❌ MySQL info error: " . $e->getMessage() . "\n";
}

echo "\n--- Performance Benchmark ---\n";
$iterations = 10;
echo "Testing $iterations rapid connection requests...\n";

$start_time = microtime(true);
for ($i = 1; $i <= $iterations; $i++) {
    try {
        $pdo = get_db_connection();
        $pdo->query("SELECT 1")->fetch();
    } catch (Exception $e) {
        echo "  Request $i failed: " . $e->getMessage() . "\n";
    }
}
$end_time = microtime(true);

$total_time = ($end_time - $start_time) * 1000;
$avg_time = $total_time / $iterations;

echo "Total time: " . round($total_time, 2) . " ms\n";
echo "Average per request: " . round($avg_time, 2) . " ms\n";

if ($avg_time < 5) {
    echo "✅ EXCELLENT: Very fast connection reuse\n";
} elseif ($avg_time < 20) {
    echo "✅ GOOD: Fast connection performance\n";
} else {
    echo "⚠️  WARNING: Slow connection performance\n";
}

echo "\n=== Summary ===\n";
echo "Connection pool optimization is " . ($stats['has_connection'] ? "ACTIVE" : "INACTIVE") . "\n";
echo "Persistent connections: " . ($stats['is_persistent'] ? "ENABLED" : "DISABLED") . "\n";
echo "This should significantly reduce the 'max connections per hour' MySQL errors.\n";
echo "\nMonitor this script regularly to ensure optimal performance.\n";
?>
