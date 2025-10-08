<?php
// Connection optimization recommendations

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/ConnectionPool.php';

echo "<h1>Connection Optimization Analysis</h1>\n";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 20px;'>\n";

try {
    $db = new Database();
    
    // Get current connection info
    $stmt = $db->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Max_used_connections')");
    $status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $currentConnections = (int)($status['Threads_connected'] ?? 0);
    $runningThreads = (int)($status['Threads_running'] ?? 0);
    $maxUsed = (int)($status['Max_used_connections'] ?? 0);
    
    echo "<h2>📊 Current Connection Status</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Current Connections:</strong> {$currentConnections}</li>\n";
    echo "<li><strong>Active/Running:</strong> {$runningThreads}</li>\n";
    echo "<li><strong>Idle/Sleeping:</strong> " . ($currentConnections - $runningThreads) . "</li>\n";
    echo "<li><strong>Peak Usage:</strong> {$maxUsed}</li>\n";
    echo "</ul>\n";
    
    // Analyze connection efficiency
    $idleConnections = $currentConnections - $runningThreads;
    $idlePercentage = ($currentConnections > 0) ? round(($idleConnections / $currentConnections) * 100, 1) : 0;
    
    echo "<h2>🔍 Connection Efficiency Analysis</h2>\n";
    
    if ($idlePercentage > 70) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0;'>\n";
        echo "<h3>⚠️ High Idle Connection Rate: {$idlePercentage}%</h3>\n";
        echo "<p>You have too many idle connections. This suggests:</p>\n";
        echo "<ul>\n";
        echo "<li>Connection pool timeout is too high</li>\n";
        echo "<li>Connections are being held open too long</li>\n";
        echo "<li>Possible connection leaks</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    } elseif ($idlePercentage < 20) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0;'>\n";
        echo "<h3>🚨 Very High Activity: {$idlePercentage}% idle</h3>\n";
        echo "<p>Most connections are actively working. This could indicate:</p>\n";
        echo "<ul>\n";
        echo "<li>Heavy database load</li>\n";
        echo "<li>Slow queries causing bottlenecks</li>\n";
        echo "<li>Need for more connections or optimization</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0;'>\n";
        echo "<h3>✅ Healthy Connection Ratio: {$idlePercentage}% idle</h3>\n";
        echo "<p>Good balance between active and idle connections.</p>\n";
        echo "</div>\n";
    }
    
    echo "<h2>🛠️ Optimization Recommendations</h2>\n";
    
    if ($currentConnections > 60) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0;'>\n";
        echo "<h3>1. Reduce Connection Pool Size</h3>\n";
        echo "<p>Your current {$currentConnections} connections is high. Consider:</p>\n";
        echo "<ul>\n";
        echo "<li>Reducing connection pool timeout</li>\n";
        echo "<li>Implementing connection limits per process</li>\n";
        echo "<li>Adding connection cleanup routines</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    echo "<div style='background: #e1f5fe; border: 1px solid #b3e5fc; padding: 15px; margin: 10px 0;'>\n";
    echo "<h3>2. Monitor Connection Sources</h3>\n";
    echo "<ul>\n";
    echo "<li>Check if multiple applications share the same database user</li>\n";
    echo "<li>Identify long-running connections</li>\n";
    echo "<li>Monitor peak usage times</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<div style='background: #e8f5e8; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0;'>\n";
    echo "<h3>3. Connection Pool Tuning</h3>\n";
    echo "<p>Current connection pooling is active. Consider adjusting:</p>\n";
    echo "<ul>\n";
    echo "<li>Connection timeout settings</li>\n";
    echo "<li>Maximum pool size limits</li>\n";
    echo "<li>Connection validation intervals</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // Check for your application's contribution
    $stmt = $db->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $yourConnections = count($processes);
    
    echo "<h2>📈 Your Application's Share</h2>\n";
    echo "<div style='background: #f0f9ff; border: 1px solid #bfdbfe; padding: 15px; margin: 10px 0;'>\n";
    echo "<p><strong>Your visible connections:</strong> {$yourConnections} out of {$currentConnections} total</p>\n";
    echo "<p><strong>Your percentage:</strong> " . round(($yourConnections / $currentConnections) * 100, 1) . "%</p>\n";
    
    if ($yourConnections < ($currentConnections * 0.2)) {
        echo "<p><strong>✅ Good:</strong> Your application is using a small portion of total connections.</p>\n";
        echo "<p><strong>Note:</strong> Most connections are from other sources (pooling, other apps, system processes).</p>\n";
    } else {
        echo "<p><strong>⚠️ Attention:</strong> Your application is using a significant portion of connections.</p>\n";
        echo "<p><strong>Action:</strong> Consider optimizing your application's connection usage.</p>\n";
    }
    echo "</div>\n";
    
    echo "<h2>🎯 Next Steps</h2>\n";
    echo "<ol>\n";
    echo "<li><strong>Monitor over time:</strong> Track if {$currentConnections} connections is normal or peak</li>\n";
    echo "<li><strong>Check application logs:</strong> Look for connection-related errors or warnings</li>\n";
    echo "<li><strong>Contact hosting provider:</strong> Ask about normal connection counts for your plan</li>\n";
    echo "<li><strong>Consider upgrades:</strong> If consistently hitting limits, request higher quotas</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0;'>\n";
    echo "<h2>❌ Error</h2>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";
?>
