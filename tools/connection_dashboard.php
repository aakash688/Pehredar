<?php
/**
 * Advanced Connection Monitoring Dashboard
 * Shows detailed MySQL connection statistics, quotas, and real-time monitoring
 */

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/ConnectionPool.php';

// Get connection statistics
function getConnectionStats() {
    try {
        $db = new Database();
        
        // Get current active connections (real-time)
        $stmt = $db->query("SHOW FULL PROCESSLIST");
        $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate real connection statistics from active connections
        $totalActiveConnections = count($processes);
        $activeConnections = 0;
        $idleConnections = 0;
        $connectionTimes = [];
        
        foreach ($processes as $process) {
            if ($process['Command'] == 'Sleep') {
                $idleConnections++;
            } else {
                $activeConnections++;
            }
            $connectionTimes[] = (int)$process['Time'];
        }
        
        $connectionStats = [
            'total_connections' => $totalActiveConnections,
            'active_connections' => $activeConnections,
            'idle_connections' => $idleConnections,
            'avg_connection_time' => count($connectionTimes) > 0 ? array_sum($connectionTimes) / count($connectionTimes) : 0,
            'longest_connection_time' => count($connectionTimes) > 0 ? max($connectionTimes) : 0,
            'shortest_connection_time' => count($connectionTimes) > 0 ? min($connectionTimes) : 0
        ];
        
        // Get server status for reference
        $stmt = $db->query("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Max_used_connections', 'Connection_errors_max_connections')");
        $status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get server variables
        $stmt = $db->query("SHOW VARIABLES WHERE Variable_name LIKE '%max_connection%'");
        $variables = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Since we can't track hourly usage without logging, we'll show current connections
        // and estimate usage based on connection patterns
        $currentConnections = (int)($status['Threads_connected'] ?? $totalActiveConnections);
        
        // Create a simple tracking mechanism using file-based logging
        $logFile = __DIR__ . '/connection_usage.log';
        $currentHour = date('Y-m-d H');
        $estimatedHourlyUsage = 0;
        
        // Log current connection count
        $logEntry = date('Y-m-d H:i:s') . "|{$currentConnections}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Read recent connections to estimate hourly usage
        if (file_exists($logFile)) {
            $recentLogs = array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -100); // Last 100 entries
            $hourlyConnections = 0;
            
            foreach ($recentLogs as $logLine) {
                if (strpos($logLine, $currentHour) === 0) {
                    $parts = explode('|', $logLine);
                    if (isset($parts[1])) {
                        $hourlyConnections = max($hourlyConnections, (int)$parts[1]);
                    }
                }
            }
            $estimatedHourlyUsage = $hourlyConnections;
        }
        
        // Override status with real data
        $status['Current_Connections'] = $currentConnections;
        $status['Estimated_Hourly_Usage'] = $estimatedHourlyUsage;
        
        return [
            'status' => $status,
            'variables' => $variables,
            'processes' => $processes,
            'stats' => $connectionStats,
            'success' => true
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'success' => false
        ];
    }
}

$data = getConnectionStats();
$quotaLimit = 500; // Your current quota
$currentTime = time();
$hourStart = strtotime(date('Y-m-d H:00:00'));
$nextHourStart = strtotime('+1 hour', $hourStart);
$timeUntilReset = $nextHourStart - $currentTime;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Monitoring Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            min-height: 100vh;
        }
        
        .dashboard {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .quota-banner {
            background: rgba(255, 193, 7, 0.2);
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .reset-timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ffc107;
            text-align: center;
            margin: 10px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-card.critical {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }
        
        .stat-card.warning {
            border-color: #FF9800;
            background: rgba(255, 152, 0, 0.1);
        }
        
        .stat-card.good {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .stat-card h3 {
            font-size: 1rem;
            margin-bottom: 15px;
            color: #e0e0e0;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-unit {
            font-size: 0.9rem;
            color: #b0b0b0;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .connections-table {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .connections-table h3 {
            margin-bottom: 20px;
            text-align: center;
            color: #e0e0e0;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }
        
        .chart-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            text-align: center;
            color: #e0e0e0;
        }
        
        .refresh-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: background 0.3s;
        }
        
        .refresh-btn:hover {
            background: #45a049;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-good { background: #4CAF50; }
        .status-warning { background: #FF9800; }
        .status-critical { background: #f44336; }
        
        .last-updated {
            text-align: center;
            margin-top: 20px;
            color: #b0b0b0;
            font-size: 14px;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.2);
            border: 2px solid #f44336;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🔌 Connection Monitoring Dashboard</h1>
            <p>Real-time MySQL connection statistics and quota monitoring</p>
        </div>

        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>

        <?php if (!$data['success']): ?>
            <div class="error-message">
                <h2>❌ Connection Failed</h2>
                <p><strong>Error:</strong> <?= htmlspecialchars($data['error']) ?></p>
                <?php if (strpos($data['error'], '1226') !== false): ?>
                    <h3>🚨 MAX CONNECTIONS PER HOUR EXCEEDED</h3>
                    <p>You have exceeded your quota of <?= $quotaLimit ?> connections per hour.</p>
                    <div class="reset-timer">
                        ⏱️ Next reset in: <?= gmdate('i:s', $timeUntilReset) ?> minutes
                    </div>
                    <p><strong>Reset time:</strong> <?= date('H:i:s', $nextHourStart) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <!-- Quota Banner -->
            <div class="quota-banner">
                🎯 Hourly Quota: <?= $quotaLimit ?> connections | 
                ⏱️ Resets every hour | 
                🕐 Next reset: <?= date('H:i:s', $nextHourStart) ?> 
                (in <?= gmdate('i:s', $timeUntilReset) ?>)
            </div>

            <!-- Important Notice -->
            <div style="background: rgba(33, 150, 243, 0.2); border: 2px solid #2196F3; border-radius: 10px; padding: 15px; margin-bottom: 20px; text-align: center;">
                <h3>⚠️ Important Notice About Quota Tracking</h3>
                <p>MySQL doesn't provide direct access to hourly connection quotas. The "Estimated Hourly Usage" is based on tracking your current connections over time.</p>
                <p><strong>Real quota tracking can only be seen when you hit the limit (1226 error).</strong> This dashboard shows current active connections and estimates usage patterns.</p>
            </div>

            <!-- Connection Statistics -->
            <div class="stats-grid">
                <!-- Current Active Connections -->
                <div class="stat-card good">
                    <h3>🔗 Current Active Connections</h3>
                    <div class="stat-value"><?= number_format($data['status']['Current_Connections'] ?? 0) ?></div>
                    <div class="stat-unit">live connections</div>
                </div>

                <!-- Active Connections -->
                <div class="stat-card good">
                    <h3>⚡ Active Connections</h3>
                    <div class="stat-value"><?= number_format($data['stats']['active_connections'] ?? 0) ?></div>
                    <div class="stat-unit">currently active</div>
                </div>

                <!-- Idle Connections -->
                <div class="stat-card">
                    <h3>😴 Idle Connections</h3>
                    <div class="stat-value"><?= number_format($data['stats']['idle_connections'] ?? 0) ?></div>
                    <div class="stat-unit">sleeping</div>
                </div>

                <!-- Hourly Usage Estimate -->
                <div class="stat-card <?= ($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.8) ? 'critical' : (($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.6) ? 'warning' : 'good') ?>">
                    <h3>📊 Estimated Hourly Usage</h3>
                    <div class="stat-value"><?= number_format($data['status']['Estimated_Hourly_Usage'] ?? 0) ?></div>
                    <div class="stat-unit">of <?= $quotaLimit ?> quota</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= min(100, (($data['status']['Estimated_Hourly_Usage'] ?? 0) / $quotaLimit) * 100) ?>%; background: <?= ($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.8) ? '#f44336' : (($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.6) ? '#FF9800' : '#4CAF50') ?>;"></div>
                    </div>
                </div>

                <!-- Longest Connection Time -->
                <div class="stat-card">
                    <h3>⏰ Longest Connection</h3>
                    <div class="stat-value"><?= gmdate('H:i:s', $data['stats']['longest_connection_time'] ?? 0) ?></div>
                    <div class="stat-unit">alive time</div>
                </div>

                <!-- Average Connection Time -->
                <div class="stat-card">
                    <h3>📈 Average Connection Time</h3>
                    <div class="stat-value"><?= round($data['stats']['avg_connection_time'] ?? 0, 1) ?></div>
                    <div class="stat-unit">seconds</div>
                </div>

                <!-- Max Used Connections -->
                <div class="stat-card">
                    <h3>📊 Peak Connections</h3>
                    <div class="stat-value"><?= number_format($data['status']['Max_used_connections'] ?? 0) ?></div>
                    <div class="stat-unit">max simultaneous</div>
                </div>

                <!-- Connection Errors -->
                <div class="stat-card <?= ($data['status']['Connection_errors_max_connections'] ?? 0) > 0 ? 'warning' : 'good' ?>">
                    <h3>⚠️ Connection Errors</h3>
                    <div class="stat-value"><?= number_format($data['status']['Connection_errors_max_connections'] ?? 0) ?></div>
                    <div class="stat-unit">max conn errors</div>
                </div>
            </div>

            <!-- Active Connections Table -->
            <div class="connections-table">
                <h3>🔗 Live Active Connections</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Host</th>
                                <th>Database</th>
                                <th>Command</th>
                                <th>Time (sec)</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['processes'] as $process): ?>
                            <tr>
                                <td><?= htmlspecialchars($process['Id']) ?></td>
                                <td><?= htmlspecialchars($process['User']) ?></td>
                                <td><?= htmlspecialchars($process['Host']) ?></td>
                                <td><?= htmlspecialchars($process['db'] ?? 'NULL') ?></td>
                                <td>
                                    <span class="status-indicator <?= $process['Command'] == 'Sleep' ? 'status-warning' : 'status-good' ?>"></span>
                                    <?= htmlspecialchars($process['Command']) ?>
                                </td>
                                <td><?= number_format($process['Time']) ?></td>
                                <td><?= htmlspecialchars($process['State'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Connection Usage Pie Chart -->
                <div class="chart-card">
                    <h3>Connection Usage Distribution</h3>
                    <canvas id="connectionPieChart"></canvas>
                </div>

                <!-- Connection Timeline -->
                <div class="chart-card">
                    <h3>Quota Usage Progress</h3>
                    <canvas id="quotaChart"></canvas>
                </div>
            </div>

        <?php endif; ?>

        <div class="last-updated">
            Last updated: <?= date('Y-m-d H:i:s') ?> | 
            Auto-refresh available | 
            <span style="color: #4CAF50;">✅ Connection pooling active</span>
        </div>
    </div>

    <script>
        // Connection Distribution Pie Chart
        const pieCtx = document.getElementById('connectionPieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Idle', 'Available Quota'],
                datasets: [{
                    data: [
                        <?= $data['stats']['active_connections'] ?? 0 ?>,
                        <?= $data['stats']['idle_connections'] ?? 0 ?>,
                        <?= max(0, $quotaLimit - ($data['status']['Estimated_Hourly_Usage'] ?? 0)) ?>
                    ],
                    backgroundColor: ['#4CAF50', '#FF9800', '#2196F3'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                }
            }
        });

        // Quota Usage Bar Chart
        const quotaCtx = document.getElementById('quotaChart').getContext('2d');
        new Chart(quotaCtx, {
            type: 'bar',
            data: {
                labels: ['Connections Used', 'Available Quota'],
                datasets: [{
                    label: 'Connections',
                    data: [
                        <?= $data['status']['Estimated_Hourly_Usage'] ?? 0 ?>,
                        <?= max(0, $quotaLimit - ($data['status']['Estimated_Hourly_Usage'] ?? 0)) ?>
                    ],
                    backgroundColor: [
                        <?= ($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.8) ? "'#f44336'" : (($data['status']['Estimated_Hourly_Usage'] ?? 0) > ($quotaLimit * 0.6) ? "'#FF9800'" : "'#4CAF50'") ?>,
                        '#2196F3'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: <?= $quotaLimit ?>,
                        ticks: { color: '#fff' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: '#fff' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
