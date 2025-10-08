<?php
// Connection limit monitoring script
$config = require __DIR__ . '/../config.php';

echo "<h2>MySQL Connection Limit Monitor</h2>\n";
echo "<pre>\n";

echo "=== Current Status ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Database Host: " . $config['db']['host'] . "\n";
echo "Database User: " . $config['db']['user'] . "\n\n";

// Try to connect and get status
try {
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Connection successful!\n\n";
    
    // Get connection statistics
    $stmt = $pdo->query("SHOW STATUS LIKE '%connection%'");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "=== Connection Statistics ===\n";
    foreach ($stats as $key => $value) {
        if (stripos($key, 'connection') !== false) {
            echo "$key: $value\n";
        }
    }
    
    // Get current limits
    $stmt = $pdo->query("SHOW VARIABLES LIKE '%max_connections%'");
    $limits = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "\n=== Connection Limits ===\n";
    foreach ($limits as $key => $value) {
        echo "$key: $value\n";
    }
    
    // Check processlist
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Active Connections ===\n";
    echo "Total active connections: " . count($processes) . "\n";
    
    $connectionsByUser = [];
    foreach ($processes as $process) {
        $user = $process['User'];
        $connectionsByUser[$user] = ($connectionsByUser[$user] ?? 0) + 1;
    }
    
    foreach ($connectionsByUser as $user => $count) {
        echo "$user: $count connections\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), '1226') !== false) {
        echo "=== MAX CONNECTIONS PER HOUR EXCEEDED ===\n";
        echo "Current limit: 500 connections per hour\n";
        echo "You need to wait for the hourly reset or contact your hosting provider.\n\n";
        
        echo "=== SOLUTIONS ===\n";
        echo "1. Wait until next hour (resets automatically)\n";
        echo "2. Contact hosting provider to increase limit\n";
        echo "3. Use temporary database credentials\n";
        echo "4. Optimize connection usage further\n\n";
        
        echo "=== NEXT RESET TIME (ESTIMATE) ===\n";
        $currentMinute = (int)date('i');
        $currentSecond = (int)date('s');
        $minutesUntilReset = 60 - $currentMinute;
        $secondsUntilReset = 60 - $currentSecond;
        
        if ($minutesUntilReset == 60) $minutesUntilReset = 0;
        
        echo "Estimated reset in: {$minutesUntilReset} minutes and {$secondsUntilReset} seconds\n";
        echo "Reset time (approx): " . date('H:i:s', strtotime("+{$minutesUntilReset} minutes +{$secondsUntilReset} seconds")) . "\n";
    }
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. ✅ Connection pooling is already implemented\n";
echo "2. ✅ Caching is already implemented\n";
echo "3. 🔄 Consider requesting higher limits from hosting provider\n";
echo "4. 📊 Monitor usage patterns to optimize further\n";

echo "</pre>\n";
?>
