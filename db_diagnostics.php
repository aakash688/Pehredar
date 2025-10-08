<?php
/**
 * Database Connection Diagnostic Page
 * This page provides detailed information about database connectivity issues
 */

// Prevent direct access
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .section h3 { margin-top: 0; color: #2c3e50; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .refresh-btn { background: #28a745; }
        .refresh-btn:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Database Connection Diagnostics</h1>
            <p>Comprehensive analysis of your database connectivity issues</p>
        </div>

        <?php
        // Load configuration
        $config = require 'config.php';
        
        // Load ConnectionPool
        require_once 'helpers/ConnectionPool.php';
        
        echo "<div class='status info'>";
        echo "<strong>Diagnostic Report Generated:</strong> " . date('Y-m-d H:i:s') . "<br>";
        echo "<strong>Your Current Network:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "<br>";
        echo "<strong>Issue:</strong> Network-specific database connectivity problems";
        echo "</div>";
        
        // Test 1: Basic PHP Extensions
        echo "<div class='section'>";
        echo "<h3>📋 PHP Environment Check</h3>";
        echo "<div class='grid'>";
        
        $pdo_mysql = extension_loaded('pdo_mysql');
        $mysqli = extension_loaded('mysqli');
        
        echo "<div class='status " . ($pdo_mysql ? 'success' : 'error') . "'>";
        echo "<strong>PDO MySQL:</strong> " . ($pdo_mysql ? '✅ Available' : '❌ Missing');
        echo "</div>";
        
        echo "<div class='status " . ($mysqli ? 'success' : 'error') . "'>";
        echo "<strong>MySQLi:</strong> " . ($mysqli ? '✅ Available' : '❌ Missing');
        echo "</div>";
        
        echo "</div></div>";
        
        // Test 2: Server Configuration
        echo "<div class='section'>";
        echo "<h3>⚙️ Database Server Configuration</h3>";
        echo "<div class='code'>";
        echo "Primary Server: " . $config['db']['host'] . ":" . $config['db']['port'] . "\n";
        echo "Database: " . $config['db']['dbname'] . "\n";
        echo "User: " . $config['db']['user'] . "\n";
        echo "Password: " . str_repeat('*', strlen($config['db']['pass'])) . "\n";
        echo "Fallback Servers: " . (isset($config['db_fallbacks']) ? count($config['db_fallbacks']) : 0) . "\n";
        echo "Connection Timeout: " . ($config['connection']['timeout'] ?? 'Not set') . " seconds\n";
        echo "Retry Attempts: " . ($config['connection']['retry_attempts'] ?? 'Not set') . "\n";
        echo "</div></div>";
        
        // Test 3: Network Connectivity Tests
        echo "<div class='section'>";
        echo "<h3>🌐 Network Connectivity Analysis</h3>";
        
        $servers = [$config['db']];
        if (isset($config['db_fallbacks'])) {
            $servers = array_merge($servers, $config['db_fallbacks']);
        }
        
        foreach ($servers as $index => $server) {
            $host = $server['host'];
            $port = $server['port'];
            
            echo "<h4>Server " . ($index + 1) . ": {$host}:{$port}</h4>";
            
            // Test network connectivity
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);
            $isReachable = $connection !== false;
            
            if ($connection) {
                fclose($connection);
            }
            
            echo "<div class='status " . ($isReachable ? 'success' : 'error') . "'>";
            echo "<strong>Network Test:</strong> " . ($isReachable ? '✅ Reachable' : '❌ Not Reachable');
            if (!$isReachable) {
                echo "<br><strong>Error:</strong> {$errstr} (Code: {$errno})";
            }
            echo "</div>";
            
            // Test PDO connection
            if ($isReachable) {
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$server['dbname']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $server['user'], $server['pass'], [
                        PDO::ATTR_TIMEOUT => 5,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    echo "<div class='status success'>";
                    echo "<strong>Database Test:</strong> ✅ Connection Successful<br>";
                    echo "<strong>Server Version:</strong> " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
                    echo "<strong>Connection ID:</strong> " . $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
                    echo "</div>";
                    
                } catch (PDOException $e) {
                    echo "<div class='status error'>";
                    echo "<strong>Database Test:</strong> ❌ Connection Failed<br>";
                    echo "<strong>Error:</strong> " . $e->getMessage();
                    echo "</div>";
                }
            }
        }
        echo "</div>";
        
        // Test 4: ConnectionPool Diagnostics
        echo "<div class='section'>";
        echo "<h3>🔧 ConnectionPool Diagnostics</h3>";
        
        try {
            $connection = ConnectionPool::getConnection();
            echo "<div class='status success'>";
            echo "<strong>ConnectionPool Status:</strong> ✅ Working<br>";
            echo "<strong>Connection Type:</strong> " . ($connection->getAttribute(PDO::ATTR_PERSISTENT) ? 'Persistent' : 'Non-Persistent');
            echo "</div>";
            
            $stats = ConnectionPool::getStats();
            echo "<div class='code'>";
            echo "Connection Statistics:\n";
            foreach ($stats as $key => $value) {
                if ($key !== 'connection_test_cache') {
                    echo "- " . ucwords(str_replace('_', ' ', $key)) . ": " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
                }
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='status error'>";
            echo "<strong>ConnectionPool Status:</strong> ❌ Failed<br>";
            echo "<strong>Error:</strong> " . $e->getMessage();
            echo "</div>";
        }
        echo "</div>";
        
        // Test 5: Network Analysis
        echo "<div class='section'>";
        echo "<h3>🔍 Network Analysis & Solutions</h3>";
        
        $primaryReachable = false;
        $connection = @fsockopen($config['db']['host'], $config['db']['port'], $errno, $errstr, 5);
        if ($connection) {
            $primaryReachable = true;
            fclose($connection);
        }
        
        if (!$primaryReachable) {
            echo "<div class='status warning'>";
            echo "<h4>🚨 Network Issue Detected</h4>";
            echo "<p>Your current network cannot reach the database server. This is a <strong>network-specific issue</strong>, not a code problem.</p>";
            echo "</div>";
            
            echo "<div class='status info'>";
            echo "<h4>💡 Possible Causes & Solutions</h4>";
            echo "<ul>";
            echo "<li><strong>WiFi Network Restrictions:</strong> Your current WiFi network may be blocking database connections</li>";
            echo "<li><strong>Corporate Firewall:</strong> If you're on a corporate network, database ports might be blocked</li>";
            echo "<li><strong>ISP Restrictions:</strong> Some ISPs block certain ports or IP ranges</li>";
            echo "<li><strong>VPN Issues:</strong> If using VPN, it might be interfering with connections</li>";
            echo "<li><strong>Router Settings:</strong> Your router might have security settings blocking the connection</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='status success'>";
            echo "<h4>✅ Immediate Solutions</h4>";
            echo "<ol>";
            echo "<li><strong>Switch Networks:</strong> Try connecting to a different WiFi network (mobile hotspot, different router)</li>";
            echo "<li><strong>Use Mobile Data:</strong> Test with your phone's mobile hotspot</li>";
            echo "<li><strong>Contact Network Admin:</strong> If on corporate network, ask IT to whitelist the database server</li>";
            echo "<li><strong>Router Settings:</strong> Check if your router has any security features blocking the connection</li>";
            echo "<li><strong>VPN:</strong> Try disabling VPN or switching VPN servers</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='status success'>";
            echo "<h4>✅ Network Connectivity is Working</h4>";
            echo "<p>Your network can reach the database server. If you're still experiencing issues, they might be intermittent or related to server load.</p>";
            echo "</div>";
        }
        echo "</div>";
        
        // Test 6: Recommendations
        echo "<div class='section'>";
        echo "<h3>📋 Recommendations</h3>";
        echo "<div class='status info'>";
        echo "<h4>For Development:</h4>";
        echo "<ul>";
        echo "<li>Keep this diagnostic page bookmarked for quick troubleshooting</li>";
        echo "<li>Consider setting up a local database for offline development</li>";
        echo "<li>Use mobile hotspot as backup when WiFi has issues</li>";
        echo "<li>Monitor your network's stability with different servers</li>";
        echo "</ul>";
        
        echo "<h4>For Production:</h4>";
        echo "<ul>";
        echo "<li>Set up multiple database servers in different locations</li>";
        echo "<li>Implement proper monitoring and alerting</li>";
        echo "<li>Consider using a database proxy or load balancer</li>";
        echo "<li>Have backup connectivity options (different ISPs, mobile backup)</li>";
        echo "</ul>";
        echo "</div>";
        echo "</div>";
        ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="?" class="btn refresh-btn">🔄 Refresh Diagnostics</a>
            <a href="index.php" class="btn">🏠 Back to Application</a>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 0.9em; color: #666;">
            <strong>Note:</strong> This diagnostic tool helps identify network connectivity issues. 
            The enhanced ConnectionPool now includes retry logic and better error handling to make your application more resilient to network problems.
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}
?>
