<?php
/**
 * Real-Time Performance Dashboard
 * 
 * Comprehensive monitoring dashboard for database, API, caching, and application performance
 * Designed for industrial-level monitoring of 3000-4000+ users/hour capacity
 */

require_once __DIR__ . '/../helpers/ConnectionPool.php';
require_once __DIR__ . '/../helpers/CacheManager.php';
require_once __DIR__ . '/../helpers/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Dashboard - Industrial Guard Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .header .subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.2em;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-excellent { background: #27ae60; }
        .status-good { background: #f39c12; }
        .status-warning { background: #e74c3c; }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
        }
        
        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .metric-value.excellent { color: #27ae60; }
        .metric-value.good { color: #f39c12; }
        .metric-value.warning { color: #e74c3c; }
        
        .metric-description {
            color: #7f8c8d;
            font-size: 0.9em;
            line-height: 1.4;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: 350px; /* Fixed height */
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .chart-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
            font-size: 1.2em;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .chart-container {
            flex: 1;
            position: relative;
            min-height: 0; /* Important for flex child */
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .refresh-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.3s ease;
        }
        
        .refresh-button:hover {
            background: #2980b9;
        }
        
        .performance-log {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: 350px; /* Fixed height */
            display: flex;
            flex-direction: column;
        }
        
        .performance-log h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .log-container {
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .log-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .log-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }
        
        .log-container::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 3px;
        }
        
        .log-container::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.5);
        }
        
        .log-entry {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .log-success { background: #d5f4e6; color: #27ae60; }
        .log-warning { background: #fef9e7; color: #f39c12; }
        .log-error { background: #fadbd8; color: #e74c3c; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .loading {
            animation: pulse 1.5s infinite;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.excellent {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-badge.good {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .status-badge.warning {
            background: #fadbd8;
            color: #e74c3c;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .auto-refresh {
                position: relative;
                top: auto;
                right: auto;
                margin-bottom: 20px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <div class="dashboard-container">
        <div class="header">
            <h1>🚀 Industrial Performance Dashboard</h1>
            <p class="subtitle">Real-time monitoring for 3000-4000+ users/hour capacity</p>
        </div>
        
        <div class="auto-refresh">
            <span>Auto-refresh:</span>
            <button class="refresh-button" onclick="toggleAutoRefresh()">ON</button>
            <button class="refresh-button" onclick="refreshDashboard()">Refresh Now</button>
        </div>
        
        <div class="metrics-grid" id="metricsGrid">
            <!-- Metrics will be populated by JavaScript -->
        </div>
        
        <div class="charts-section">
            <div class="chart-card">
                <h3>📈 Response Time Trends</h3>
                <div class="chart-container">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>🎯 Database Performance</h3>
                <div class="chart-container">
                    <canvas id="databaseChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>⚡ Cache Performance</h3>
                <div class="chart-container">
                    <canvas id="cacheChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>🔗 Connection Pool Status</h3>
                <div class="chart-container">
                    <canvas id="connectionChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="performance-log">
            <h3>📋 Performance Log</h3>
            <div class="log-container" id="performanceLog">
                <!-- Log entries will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let autoRefreshEnabled = true;
        let refreshInterval;
        let charts = {};
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            refreshDashboard();
            startAutoRefresh();
        });
        
        function hideLoadingOverlay() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 300);
            }
        }
        
        function initializeCharts() {
            // Response Time Chart
            const responseCtx = document.getElementById('responseTimeChart').getContext('2d');
            charts.responseTime = new Chart(responseCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'API Response Time (ms)',
                        data: [],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { 
                                display: true, 
                                text: 'Response Time (ms)',
                                font: { size: 12 }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });
            
            // Database Performance Chart
            const dbCtx = document.getElementById('databaseChart').getContext('2d');
            charts.database = new Chart(dbCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Query Cache Hits', 'Cache Misses', 'Direct Queries'],
                    datasets: [{
                        data: [70, 20, 10],
                        backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
            
            // Cache Performance Chart
            const cacheCtx = document.getElementById('cacheChart').getContext('2d');
            charts.cache = new Chart(cacheCtx, {
                type: 'bar',
                data: {
                    labels: ['API Cache', 'Query Cache', 'User Cache', 'Dashboard Cache'],
                    datasets: [{
                        label: 'Hit Rate (%)',
                        data: [85, 92, 78, 95],
                        backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#9b59b6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { 
                                display: true, 
                                text: 'Hit Rate (%)',
                                font: { size: 12 }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Connection Pool Chart
            const connCtx = document.getElementById('connectionChart').getContext('2d');
            charts.connection = new Chart(connCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Active Connections',
                        data: [],
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            title: { 
                                display: true, 
                                text: 'Active Connections',
                                font: { size: 12 }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    }
                }
            });
        }
        
        async function refreshDashboard() {
            try {
                // Show loading state
                document.getElementById('metricsGrid').classList.add('loading');
                
                // Fetch performance data
                const response = await fetch('performance_dashboard_api.php');
                const data = await response.json();
                
                // Update metrics
                updateMetrics(data.metrics);
                
                // Update charts
                updateCharts(data.charts);
                
                // Update performance log
                updatePerformanceLog(data.logs);
                
                // Remove loading state
                document.getElementById('metricsGrid').classList.remove('loading');
                
                // Hide initial loading overlay
                hideLoadingOverlay();
                
                addLogEntry(`Dashboard updated successfully at ${new Date().toLocaleTimeString()}`, 'success');
                
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
                addLogEntry(`Dashboard refresh failed: ${error.message}`, 'error');
            }
        }
        
        function updateMetrics(metrics) {
            const metricsGrid = document.getElementById('metricsGrid');
            metricsGrid.innerHTML = '';
            
            metrics.forEach(metric => {
                const card = createMetricCard(metric);
                metricsGrid.appendChild(card);
            });
        }
        
        function createMetricCard(metric) {
            const card = document.createElement('div');
            card.className = 'metric-card';
            
            const statusClass = getStatusClass(metric.status);
            
            card.innerHTML = `
                <h3>
                    <span class="status-indicator ${statusClass}"></span>
                    ${metric.title}
                    <span class="status-badge ${statusClass.replace('status-', '')}">${metric.status}</span>
                </h3>
                <div class="metric-value ${statusClass.replace('status-', '')}">${metric.value}</div>
                <div class="metric-description">${metric.description}</div>
            `;
            
            card.classList.add('fade-in');
            
            return card;
        }
        
        function getStatusClass(status) {
            switch (status) {
                case 'excellent': return 'status-excellent';
                case 'good': return 'status-good';
                case 'warning': return 'status-warning';
                default: return 'status-good';
            }
        }
        
        function updateCharts(chartData) {
            // Update response time chart
            if (chartData.responseTime) {
                charts.responseTime.data.labels = chartData.responseTime.labels;
                charts.responseTime.data.datasets[0].data = chartData.responseTime.data;
                charts.responseTime.update();
            }
            
            // Update database chart
            if (chartData.database) {
                charts.database.data.datasets[0].data = chartData.database.data;
                charts.database.update();
            }
            
            // Update cache chart
            if (chartData.cache) {
                charts.cache.data.datasets[0].data = chartData.cache.data;
                charts.cache.update();
            }
            
            // Update connection chart
            if (chartData.connections) {
                charts.connection.data.labels = chartData.connections.labels;
                charts.connection.data.datasets[0].data = chartData.connections.data;
                charts.connection.update();
            }
        }
        
        function updatePerformanceLog(logs) {
            const logContainer = document.getElementById('performanceLog');
            
            // Keep only last 50 entries
            while (logContainer.children.length > 50) {
                logContainer.removeChild(logContainer.firstChild);
            }
            
            logs.forEach(log => {
                addLogEntry(log.message, log.type, false);
            });
        }
        
        function addLogEntry(message, type = 'success', prepend = true) {
            const logContainer = document.getElementById('performanceLog');
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            
            if (prepend) {
                logContainer.insertBefore(entry, logContainer.firstChild);
            } else {
                logContainer.appendChild(entry);
            }
        }
        
        function toggleAutoRefresh() {
            autoRefreshEnabled = !autoRefreshEnabled;
            const button = document.querySelector('.refresh-button');
            button.textContent = autoRefreshEnabled ? 'ON' : 'OFF';
            
            if (autoRefreshEnabled) {
                startAutoRefresh();
            } else {
                clearInterval(refreshInterval);
            }
        }
        
        function startAutoRefresh() {
            if (autoRefreshEnabled) {
                refreshInterval = setInterval(refreshDashboard, 10000); // Refresh every 10 seconds
            }
        }
    </script>
</body>
</html>
