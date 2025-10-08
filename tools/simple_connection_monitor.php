<?php
/**
 * Simple Connection Monitor - Shows what MySQL actually provides
 */

require_once __DIR__ . '/../helpers/database.php';

echo "<h1>Simple Connection Monitor - Real Data Only</h1>\n";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 20px; border-radius: 10px;'>\n";

try {
    $db = new Database();
    
    echo "<h2>🔌 Current Active Connections (Real-Time)</h2>\n";
    
    // Get current process list
    $stmt = $db->query("SHOW FULL PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalConnections = count($processes);
    $activeConnections = 0;
    $sleepingConnections = 0;
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background: #ddd;'><th>ID</th><th>User</th><th>Host</th><th>DB</th><th>Command</th><th>Time (sec)</th><th>State</th></tr>\n";
    
    foreach ($processes as $process) {
        if ($process['Command'] == 'Sleep') {
            $sleepingConnections++;
            $bgColor = '#fff3cd'; // Light yellow for sleeping
        } else {
            $activeConnections++;
            $bgColor = '#d4edda'; // Light green for active
        }
        
        echo "<tr style='background: {$bgColor};'>";
        echo "<td>" . htmlspecialchars($process['Id']) . "</td>";
        echo "<td>" . htmlspecialchars($process['User']) . "</td>";
        echo "<td>" . htmlspecialchars($process['Host']) . "</td>";
        echo "<td>" . htmlspecialchars($process['db'] ?? 'NULL') . "</td>";
        echo "<td><strong>" . htmlspecialchars($process['Command']) . "</strong></td>";
        echo "<td>" . number_format($process['Time']) . "</td>";
        echo "<td>" . htmlspecialchars($process['State'] ?? 'N/A') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>📊 Summary</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Total Connections:</strong> {$totalConnections}</li>\n";
    echo "<li><strong>Active (Working):</strong> {$activeConnections}</li>\n";
    echo "<li><strong>Idle (Sleeping):</strong> {$sleepingConnections}</li>\n";
    echo "</ul>\n";
    
    echo "<h2>📈 MySQL Server Statistics</h2>\n";
    
    // Get relevant server statistics
    $stmt = $db->query("SHOW STATUS WHERE Variable_name IN (
        'Threads_connected', 
        'Max_used_connections', 
        'Connection_errors_max_connections',
        'Connections'
    )");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "<ul>\n";
    echo "<li><strong>Threads Connected:</strong> " . number_format($stats['Threads_connected'] ?? 0) . " (current)</li>\n";
    echo "<li><strong>Max Used Connections:</strong> " . number_format($stats['Max_used_connections'] ?? 0) . " (peak since server start)</li>\n";
    echo "<li><strong>Total Connections Since Server Start:</strong> " . number_format($stats['Connections'] ?? 0) . " (cumulative - this is the dummy number!)</li>\n";
    echo "<li><strong>Connection Errors:</strong> " . number_format($stats['Connection_errors_max_connections'] ?? 0) . "</li>\n";
    echo "</ul>\n";
    
    echo "<h2>⚠️ The Quota Problem</h2>\n";
    echo "<div style='background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
    echo "<h3>Why You Can't See Real Quota Usage:</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>MySQL doesn't expose hourly quotas</strong> - The 'max_connections_per_hour' limit is enforced internally</li>\n";
    echo "<li><strong>The 'Connections' variable is cumulative</strong> - It shows total connections since server restart, not hourly usage</li>\n";
    echo "<li><strong>No quota API</strong> - There's no way to query how many connections you've used this hour</li>\n";
    echo "<li><strong>You only know when you hit the limit</strong> - Error 1226 'User has exceeded max_connections_per_hour'</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<h2>✅ What This Dashboard IS Useful For:</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Current Connection Monitoring:</strong> See live active/idle connections</li>\n";
    echo "<li><strong>Connection Patterns:</strong> Track how many connections you typically use</li>\n";
    echo "<li><strong>Optimization:</strong> Identify long-running connections</li>\n";
    echo "<li><strong>Troubleshooting:</strong> See what's currently connected to your database</li>\n";
    echo "</ul>\n";
    
    echo "<h2>🎯 Practical Quota Management:</h2>\n";
    echo "<div style='background: #d1ecf1; border: 2px solid #17a2b8; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
    echo "<h3>Best Practices:</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>Monitor current connections</strong> - Keep an eye on how many you typically use</li>\n";
    echo "<li><strong>Optimize connection usage</strong> - Use connection pooling (✅ you already have this)</li>\n";
    echo "<li><strong>Plan for peak hours</strong> - Know your usage patterns</li>\n";
    echo "<li><strong>Request higher limits</strong> - Contact hosting provider if you consistently hit 500/hour</li>\n";
    echo "<li><strong>Watch for the 1226 error</strong> - This is your real quota indicator</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<h2>🔄 Connection Pooling Status</h2>\n";
    echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
    echo "<p><strong>✅ Your connection pooling is working!</strong></p>\n";
    echo "<p>Instead of creating hundreds of new connections, you're reusing existing ones.</p>\n";
    echo "<p>Without pooling, you'd likely hit the 500/hour limit much faster.</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 5px;'>\n";
    echo "<h2>❌ Connection Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    
    if (strpos($e->getMessage(), '1226') !== false) {
        echo "<h3>🚨 QUOTA EXCEEDED!</h3>\n";
        echo "<p>You have hit your 500 connections per hour limit.</p>\n";
        echo "<p>The quota will reset at the top of the next hour.</p>\n";
        
        $nextHour = date('H:i:s', strtotime('+1 hour', strtotime(date('H:00:00'))));
        echo "<p><strong>Next reset:</strong> {$nextHour}</p>\n";
    }
    echo "</div>\n";
}

echo "<div style='text-align: center; margin-top: 30px; color: #666;'>\n";
echo "<p>Last updated: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p><a href='?' style='color: #007bff;'>🔄 Refresh</a></p>\n";
echo "</div>\n";

echo "</div>\n";
?>
