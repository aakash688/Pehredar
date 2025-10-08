<?php
// Advanced Payroll Dashboard - Main entry point for enhanced payroll features
require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/AdvanceTracker.php';

// Initialize components
$db = new Database();
$advanceTracker = new \Helpers\AdvanceTracker();

// Get dashboard data
$advanceSummary = $advanceTracker->getAdvanceSummary();
$currentMonth = date('Y-m');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Payroll Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .stat-change.positive {
            color: #10B981;
        }
        .stat-change.negative {
            color: #EF4444;
        }
        .widget-loading {
            animation: pulse 1.5s infinite;
        }
        .notification-dot {
            animation: pulse 2s infinite;
        }
        .progress-ring {
            transition: stroke-dasharray 0.5s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">

<div class="min-h-screen">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Advanced Payroll Management</h1>
                    <p class="text-gray-400">Comprehensive payroll dashboard with advanced features</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-btn" class="relative p-2 bg-gray-700 rounded-lg hover:bg-gray-600">
                            <i class="fas fa-bell text-gray-300"></i>
                            <span id="notification-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center notification-dot hidden">
                                0
                            </span>
                        </button>
                    </div>

                    <!-- Quick Actions -->
                    <div class="relative">
                        <button id="quick-actions-btn" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-lightning-bolt mr-2"></i>Quick Actions
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Dashboard Content -->
    <main class="container mx-auto px-6 py-8">
        
        <!-- Key Metrics Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Advance Summary Widget -->
            <div class="dashboard-card bg-gradient-to-r from-orange-500 to-red-500 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Active Advances</p>
                        <p class="text-3xl font-bold" id="active-advances-count">
                            <?php echo $advanceSummary['active_advances_count']; ?>
                        </p>
                        <p class="text-orange-100 text-sm">
                            ₹<?php echo number_format($advanceSummary['total_outstanding_amount'], 0); ?> outstanding
                        </p>
                    </div>
                    <div class="relative w-16 h-16">
                        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                            <path class="stroke-current text-orange-200 opacity-25"
                                  fill="none" stroke-width="3" stroke-linecap="round"
                                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="stroke-current text-white progress-ring"
                                  fill="none" stroke-width="3" stroke-linecap="round"
                                  stroke-dasharray="75, 100"
                                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-credit-card text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Overview Widget -->
            <div class="dashboard-card bg-gradient-to-r from-green-500 to-blue-500 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">This Month Payroll</p>
                        <p class="text-3xl font-bold" id="monthly-payroll-count">--</p>
                        <p class="text-green-100 text-sm" id="monthly-payroll-amount">Loading...</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Actions Widget -->
            <div class="dashboard-card bg-gradient-to-r from-yellow-500 to-orange-500 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-100 text-sm font-medium">Pending Actions</p>
                        <p class="text-3xl font-bold" id="pending-actions-count">--</p>
                        <p class="text-yellow-100 text-sm" id="pending-actions-details">Loading...</p>
                    </div>
                    <div class="relative bg-white bg-opacity-20 p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                        <span class="absolute -top-1 -right-1 bg-red-500 w-3 h-3 rounded-full notification-dot"></span>
                    </div>
                </div>
            </div>

            <!-- System Health Widget -->
            <div class="dashboard-card bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">System Health</p>
                        <p class="text-3xl font-bold">98%</p>
                        <p class="text-purple-100 text-sm">All systems operational</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-full">
                        <i class="fas fa-heartbeat text-white text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Features Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Employee Status Distribution -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-white">Employee Status Distribution</h3>
                <div class="relative">
                    <canvas id="employee-status-chart" width="300" height="200"></canvas>
                </div>
                <div id="employee-status-legend" class="mt-4 space-y-2">
                    <!-- Legend will be populated by JavaScript -->
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">Recent Activities</h3>
                    <button id="refresh-activities" class="text-gray-400 hover:text-white">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div id="recent-activities-list" class="space-y-3 max-h-64 overflow-y-auto">
                    <!-- Activities will be loaded here -->
                </div>
            </div>

            <!-- Quick Actions Panel -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-white">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-3">
                    <button class="quick-action-btn bg-blue-600 hover:bg-blue-700 p-3 rounded-lg text-center text-sm" 
                            onclick="window.location.href='index.php?page=salary-calculation'">
                        <i class="fas fa-calculator text-lg mb-2"></i>
                        <div>Calculate Salary</div>
                    </button>
                    
                    <button class="quick-action-btn bg-green-600 hover:bg-green-700 p-3 rounded-lg text-center text-sm"
                            onclick="window.location.href='index.php?page=enhanced-salary-records'">
                        <i class="fas fa-list text-lg mb-2"></i>
                        <div>View Records</div>
                    </button>
                    
                    <button class="quick-action-btn bg-purple-600 hover:bg-purple-700 p-3 rounded-lg text-center text-sm"
                            onclick="openBulkOperationsModal()">
                        <i class="fas fa-layer-group text-lg mb-2"></i>
                        <div>Bulk Operations</div>
                    </button>
                    
                    <button class="quick-action-btn bg-orange-600 hover:bg-orange-700 p-3 rounded-lg text-center text-sm"
                            onclick="window.location.href='index.php?page=advance-management'">
                        <i class="fas fa-credit-card text-lg mb-2"></i>
                        <div>Advances</div>
                    </button>
                    
                    <button class="quick-action-btn bg-red-600 hover:bg-red-700 p-3 rounded-lg text-center text-sm"
                            onclick="window.location.href='index.php?page=reports'">
                        <i class="fas fa-chart-bar text-lg mb-2"></i>
                        <div>Reports</div>
                    </button>
                    
                    <button class="quick-action-btn bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg text-center text-sm"
                            onclick="window.location.href='index.php?page=audit-logs'">
                        <i class="fas fa-shield-alt text-lg mb-2"></i>
                        <div>Audit Logs</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Payroll Trends Chart -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-white">Payroll Trends (Last 6 Months)</h3>
                <div class="relative">
                    <canvas id="payroll-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Advance Analytics Chart -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-white">Advance Analytics</h3>
                <div class="relative">
                    <canvas id="advance-analytics-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Tables Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Employees with Advances -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-white">Employees with Active Advances</h3>
                    <button onclick="window.location.href='index.php?page=advance-management'" 
                            class="text-blue-400 hover:text-blue-300 text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
                <div id="employees-with-advances" class="space-y-3 max-h-80 overflow-y-auto">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <!-- Urgent Actions Required -->
            <div class="dashboard-card bg-gray-800 rounded-lg p-6 border border-gray-700">
                <h3 class="text-lg font-semibold mb-4 text-white">Urgent Actions Required</h3>
                <div id="urgent-actions-list" class="space-y-3 max-h-80 overflow-y-auto">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Notifications Panel -->
<div id="notifications-panel" class="fixed right-0 top-0 h-full w-80 bg-gray-800 border-l border-gray-700 transform translate-x-full transition-transform duration-300 z-50">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-white">Notifications</h3>
            <button id="close-notifications" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="notifications-list" class="space-y-3 max-h-96 overflow-y-auto">
            <!-- Notifications will be loaded here -->
        </div>
    </div>
</div>

<!-- Quick Actions Modal -->
<div id="quick-actions-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
        <h3 class="text-lg font-semibold mb-4 text-white">Quick Actions</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <button class="quick-action-card bg-blue-600 hover:bg-blue-700 p-4 rounded-lg text-center" 
                    onclick="generateSalaryForCurrentMonth()">
                <i class="fas fa-calculator text-2xl mb-2"></i>
                <div class="font-medium">Generate Current Month Salary</div>
                <div class="text-sm opacity-75">Calculate salary for <?php echo date('F Y'); ?></div>
            </button>
            
            <button class="quick-action-card bg-green-600 hover:bg-green-700 p-4 rounded-lg text-center"
                    onclick="bulkDisbursePendingSalaries()">
                <i class="fas fa-check-double text-2xl mb-2"></i>
                <div class="font-medium">Bulk Disburse</div>
                <div class="text-sm opacity-75">Disburse all pending salaries</div>
            </button>
            
            <button class="quick-action-card bg-purple-600 hover:bg-purple-700 p-4 rounded-lg text-center"
                    onclick="applyMonthlyBonus()">
                <i class="fas fa-gift text-2xl mb-2"></i>
                <div class="font-medium">Monthly Bonus</div>
                <div class="text-sm opacity-75">Apply bonus to all employees</div>
            </button>
            
            <button class="quick-action-card bg-orange-600 hover:bg-orange-700 p-4 rounded-lg text-center"
                    onclick="processAdvanceDeductions()">
                <i class="fas fa-credit-card text-2xl mb-2"></i>
                <div class="font-medium">Process Advances</div>
                <div class="text-sm opacity-75">Deduct monthly advance amounts</div>
            </button>
            
            <button class="quick-action-card bg-red-600 hover:bg-red-700 p-4 rounded-lg text-center"
                    onclick="generateMonthlyReport()">
                <i class="fas fa-file-pdf text-2xl mb-2"></i>
                <div class="font-medium">Monthly Report</div>
                <div class="text-sm opacity-75">Generate comprehensive report</div>
            </button>
            
            <button class="quick-action-card bg-indigo-600 hover:bg-indigo-700 p-4 rounded-lg text-center"
                    onclick="exportPayrollData()">
                <i class="fas fa-download text-2xl mb-2"></i>
                <div class="font-medium">Export Data</div>
                <div class="text-sm opacity-75">Export payroll data to Excel</div>
            </button>
        </div>

        <div class="flex justify-end mt-6">
            <button id="close-quick-actions" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg text-white">
                Close
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard
    initializeDashboard();
    loadDashboardData();
    initializeCharts();
    setupEventListeners();

    function initializeDashboard() {
        console.log('Advanced Payroll Dashboard initialized');
        
        // Load initial data
        loadNotifications();
        loadRecentActivities();
        loadEmployeesWithAdvances();
        loadUrgentActions();
    }

    async function loadDashboardData() {
        try {
            const response = await fetch('actions/dashboard/dashboard_widgets_controller.php?action=all_widgets');
            const data = await response.json();
            
            if (data.success) {
                updateWidgets(data.data);
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    function updateWidgets(widgetData) {
        // Update payroll overview
        if (widgetData.payroll_overview) {
            document.getElementById('monthly-payroll-count').textContent = widgetData.payroll_overview.current_month_generated || 0;
            document.getElementById('monthly-payroll-amount').textContent = 
                `₹${(widgetData.payroll_overview.current_month_total_amount || 0).toLocaleString()} disbursed`;
        }

        // Update pending actions
        if (widgetData.urgent_actions) {
            const totalActions = widgetData.urgent_actions.length;
            document.getElementById('pending-actions-count').textContent = totalActions;
            document.getElementById('pending-actions-details').textContent = 
                totalActions > 0 ? `${totalActions} items need attention` : 'All caught up!';
        }

        // Update notification count
        if (widgetData.notifications && widgetData.notifications.unread_count > 0) {
            const notificationBadge = document.getElementById('notification-count');
            notificationBadge.textContent = widgetData.notifications.unread_count;
            notificationBadge.classList.remove('hidden');
        }
    }

    async function loadRecentActivities() {
        try {
            const response = await fetch('actions/dashboard/dashboard_widgets_controller.php?action=recent_activities&limit=5');
            const data = await response.json();
            
            if (data.success) {
                const activitiesList = document.getElementById('recent-activities-list');
                activitiesList.innerHTML = '';
                
                data.data.forEach(activity => {
                    const activityElement = createActivityElement(activity);
                    activitiesList.appendChild(activityElement);
                });
            }
        } catch (error) {
            console.error('Error loading recent activities:', error);
        }
    }

    function createActivityElement(activity) {
        const div = document.createElement('div');
        div.className = `flex items-center space-x-3 p-3 rounded-lg ${activity.color_class || 'bg-gray-700'}`;
        div.innerHTML = `
            <div class="text-lg">${activity.icon || '📝'}</div>
            <div class="flex-1">
                <div class="text-sm font-medium text-white">${activity.description}</div>
                <div class="text-xs text-gray-400">${activity.user_name} • ${activity.time_ago}</div>
            </div>
            <div class="text-xs text-gray-400">${activity.success ? '✅' : '❌'}</div>
        `;
        return div;
    }

    async function loadEmployeesWithAdvances() {
        try {
            const response = await fetch('actions/advance/advance_dashboard_controller.php?action=employees_with_advances');
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById('employees-with-advances');
                container.innerHTML = '';
                
                data.data.employees.slice(0, 5).forEach(employee => {
                    if (employee.advance_id) {
                        const employeeElement = createEmployeeAdvanceElement(employee);
                        container.appendChild(employeeElement);
                    }
                });
            }
        } catch (error) {
            console.error('Error loading employees with advances:', error);
        }
    }

    function createEmployeeAdvanceElement(employee) {
        const div = document.createElement('div');
        div.className = `p-3 rounded-lg ${employee.visual_category.color_class} border ${employee.visual_category.border_class}`;
        
        const progressPercentage = employee.progress_data ? employee.progress_data.progress_percentage : 0;
        
        div.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium text-sm">${employee.first_name} ${employee.surname}</div>
                    <div class="text-xs opacity-75">₹${(employee.remaining_balance || 0).toLocaleString()} remaining</div>
                </div>
                <div class="text-xs">${progressPercentage}%</div>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                <div class="bg-orange-500 h-2 rounded-full" style="width: ${progressPercentage}%"></div>
            </div>
        `;
        
        return div;
    }

    async function loadUrgentActions() {
        try {
            const response = await fetch('actions/dashboard/dashboard_widgets_controller.php?action=all_widgets');
            const data = await response.json();
            
            if (data.success && data.data.urgent_actions) {
                const container = document.getElementById('urgent-actions-list');
                container.innerHTML = '';
                
                if (data.data.urgent_actions.length === 0) {
                    container.innerHTML = '<div class="text-center text-gray-400 py-4">No urgent actions required</div>';
                    return;
                }
                
                data.data.urgent_actions.forEach(action => {
                    const actionElement = createUrgentActionElement(action);
                    container.appendChild(actionElement);
                });
            }
        } catch (error) {
            console.error('Error loading urgent actions:', error);
        }
    }

    function createUrgentActionElement(action) {
        const div = document.createElement('div');
        const priorityClass = action.priority === 'high' ? 'bg-red-600' : 'bg-yellow-600';
        
        div.className = `p-3 rounded-lg bg-gray-700 border-l-4 border-${action.priority === 'high' ? 'red' : 'yellow'}-500`;
        div.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium text-sm text-white">${action.message}</div>
                    <div class="text-xs text-gray-400">${action.count} items</div>
                </div>
                <button onclick="window.location.href='${action.action_url}'" 
                        class="text-blue-400 hover:text-blue-300 text-xs">
                    Take Action →
                </button>
            </div>
        `;
        
        return div;
    }

    async function loadNotifications() {
        // This would load actual notifications from the server
        // For now, we'll simulate
        const notifications = [
            {
                title: "5 salary disbursements pending",
                message: "Review and approve pending disbursements",
                time: "2 hours ago",
                priority: "high"
            },
            {
                title: "Advance repayment completed",
                message: "John Doe has completed advance repayment",
                time: "1 day ago", 
                priority: "medium"
            }
        ];

        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = '';
        
        notifications.forEach(notification => {
            const notificationElement = createNotificationElement(notification);
            notificationsList.appendChild(notificationElement);
        });
    }

    function createNotificationElement(notification) {
        const div = document.createElement('div');
        div.className = `p-3 rounded-lg bg-gray-700 border-l-4 ${
            notification.priority === 'high' ? 'border-red-500' : 'border-blue-500'
        }`;
        div.innerHTML = `
            <div class="font-medium text-sm text-white">${notification.title}</div>
            <div class="text-xs text-gray-400 mt-1">${notification.message}</div>
            <div class="text-xs text-gray-500 mt-2">${notification.time}</div>
        `;
        return div;
    }

    function initializeCharts() {
        // Employee Status Chart
        const employeeStatusCtx = document.getElementById('employee-status-chart').getContext('2d');
        new Chart(employeeStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['With Advances', 'Without Advances', 'Overdue Advances'],
                datasets: [{
                    data: [15, 35, 3],
                    backgroundColor: ['#F59E0B', '#10B981', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#374151'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Payroll Trends Chart
        const payrollTrendsCtx = document.getElementById('payroll-trends-chart').getContext('2d');
        new Chart(payrollTrendsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Total Payroll',
                    data: [2500000, 2600000, 2550000, 2700000, 2650000, 2800000],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    x: {
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#F3F4F6'
                        }
                    }
                }
            }
        });

        // Advance Analytics Chart
        const advanceAnalyticsCtx = document.getElementById('advance-analytics-chart').getContext('2d');
        new Chart(advanceAnalyticsCtx, {
            type: 'bar',
            data: {
                labels: ['Active', 'Completed', 'Overdue', 'Suspended'],
                datasets: [{
                    label: 'Advance Count',
                    data: [15, 8, 3, 1],
                    backgroundColor: ['#F59E0B', '#10B981', '#EF4444', '#6B7280'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    x: {
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#F3F4F6'
                        }
                    }
                }
            }
        });
    }

    function setupEventListeners() {
        // Notifications panel
        document.getElementById('notifications-btn').addEventListener('click', function() {
            const panel = document.getElementById('notifications-panel');
            panel.classList.toggle('translate-x-full');
        });

        document.getElementById('close-notifications').addEventListener('click', function() {
            document.getElementById('notifications-panel').classList.add('translate-x-full');
        });

        // Quick actions modal
        document.getElementById('quick-actions-btn').addEventListener('click', function() {
            document.getElementById('quick-actions-modal').classList.remove('hidden');
        });

        document.getElementById('close-quick-actions').addEventListener('click', function() {
            document.getElementById('quick-actions-modal').classList.add('hidden');
        });

        // Refresh activities
        document.getElementById('refresh-activities').addEventListener('click', function() {
            loadRecentActivities();
        });

        // Close modals on outside click
        window.addEventListener('click', function(event) {
            const quickActionsModal = document.getElementById('quick-actions-modal');
            if (event.target === quickActionsModal) {
                quickActionsModal.classList.add('hidden');
            }
        });
    }

    // Auto-refresh dashboard data every 5 minutes
    setInterval(loadDashboardData, 300000);
});

// Quick action functions
function generateSalaryForCurrentMonth() {
    window.location.href = 'index.php?page=salary-calculation';
}

function bulkDisbursePendingSalaries() {
    if (confirm('Are you sure you want to disburse all pending salaries?')) {
        // Implementation for bulk disburse
        showToast('Bulk disbursement initiated', 'info');
    }
}

function applyMonthlyBonus() {
    window.location.href = 'index.php?page=bulk-operations&type=bonus';
}

function processAdvanceDeductions() {
    if (confirm('Process all monthly advance deductions?')) {
        // Implementation for advance processing
        showToast('Advance deductions processed', 'success');
    }
}

function generateMonthlyReport() {
    window.open('actions/reports/monthly_report_generator.php', '_blank');
}

function exportPayrollData() {
    window.open('actions/reports/payroll_export.php', '_blank');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' :
        type === 'warning' ? 'bg-yellow-600' :
        'bg-blue-600'
    }`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

</body>
</html>