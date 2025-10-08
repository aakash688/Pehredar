<?php
// This file acts as a template for all dashboard-related pages.
global $page, $company_settings; // Use the global variables from index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $page)) . ' - ' . ($company_settings['company_name'] ?? 'GuardSys')); ?></title>
    <?php if (!empty($company_settings['favicon_path'])): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($company_settings['favicon_path']); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
        body { overflow-x: hidden; }

        #map { height: 100%; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .sidebar-link { 
            transition: all 0.2s ease; 
            position: relative;
        }
        .sidebar-link:hover { 
            background-color: #374151; 
            transform: translateX(2px);
        }
        .sidebar-link.active { 
            background-color: #2563eb !important; 
            color: white !important; 
            font-weight: 600;
            border-left: 4px solid #60a5fa;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        .sidebar-link.active i { 
            color: #dbeafe !important; 
            transform: scale(1.1);
        }
        .sidebar-link.parent-active {
            background-color: rgba(37, 99, 235, 0.1);
            border-left: 2px solid #3b82f6;
        }
        .submenu { 
            max-height: 0; 
            overflow: hidden; 
            transition: max-height 0.3s ease-out; 
        }
        .submenu.active-section {
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        /* Enhanced submenu visibility and scrolling */
        .submenu {
            max-height: 0; /* Collapsed by default */
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .submenu.expanded {
            max-height: 500px; /* Expanded state */
            overflow-y: auto;
        }
        .submenu.collapsed {
            max-height: 0;
            overflow: hidden;
        }
        
        /* Form Styles */
        .form-section { display: none; }
        .form-section.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .progress-step { transition: all 0.3s; }
        /* Scrollbar Styling */
        .sidebar nav::-webkit-scrollbar {
            width: 8px; /* Thin scrollbar */
        }
        .sidebar nav::-webkit-scrollbar-track {
            background: #374151; /* Dark gray track, matching sidebar background */
        }
        .sidebar nav::-webkit-scrollbar-thumb {
            background: #4B5563; /* Slightly lighter gray for the thumb */
            border-radius: 4px;
        }
        .sidebar nav::-webkit-scrollbar-thumb:hover {
            
        }

        /* New scrollbar and overflow styles */
        .sidebar nav {
            max-height: calc(100vh - 150px); /* Adjust height to allow scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
            scrollbar-width: thin; /* For Firefox */
            scrollbar-color: #4B5563 #374151; /* For Firefox */
        }
        .sidebar nav::-webkit-scrollbar-thumb:hover {
            background: #6B7280; /* Lighter gray on hover */
        }
        
        /* Fix email overlap issue - BEST SOLUTION */
        .email-field {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 40px;
        }

        .email-text {
            flex: 1;
            padding-right: 45px; /* Space for icon + buffer */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* Hover effect for better UX */
        .email-icon:hover {
            opacity: 0.8;
            transform: translateY(-50%) scale(1.1);
        }
        
        /* Mobile optimization */
        @media (max-width: 768px) {
            .email-text {
                padding-right: 40px; /* Slightly less padding on mobile */
            }
            
            .email-icon {
                right: 8px;
                width: 18px;
                height: 18px;
            }
        }
        
        /* Add smooth transitions */
        .email-field {
            transition: all 0.2s ease;
        }

        .email-field:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* Additional styling for email fields in the society view */
        .email-field dt {
            color: #9ca3af !important; /* text-gray-400 */
        }
        
        .email-field dd {
            color: #ffffff !important; /* text-white */
        }
        
        .email-field .email-icon {
            color: #9ca3af; /* text-gray-400 */
            transition: all 0.2s ease;
        }
        
        .email-field .email-icon:hover {
            color: #ffffff; /* text-white */
            transform: translateY(-50%) scale(1.1);
        }
        
        /* Ensure proper spacing in grid layout */
        .email-field.sm\\:grid {
            position: relative;
        }
        
        .email-field .email-text {
            word-break: break-all;
            hyphens: auto;
        }
        
        /* Clean Email Tooltip - Fixed positioning and no layout interference */
        .email-field {
            position: relative;
        }
        
        .email-field .email-text {
            position: relative;
            cursor: help;
            transition: background-color 0.2s ease;
        }
        
        .email-field .email-text:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-radius: 4px;
        }
        
        /* Tooltip container - fixed positioning to avoid layout issues */
        .email-tooltip {
            position: fixed;
            background: #1f2937;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            white-space: nowrap;
            z-index: 9999;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border: 1px solid #374151;
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            pointer-events: none;
        }
        
        .email-tooltip.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        /* Tooltip arrow */
        .email-tooltip::before {
            content: '';
            position: absolute;
            top: 100%;
            left: 20px;
            border: 6px solid transparent;
            border-top-color: #1f2937;
        }
        
        /* Mobile optimization */
        @media (max-width: 768px) {
            .email-tooltip {
                max-width: 280px;
                font-size: 12px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body class="bg-gray-200 antialiased">
<?php
// Enhanced route matching helper function
function isActiveRoute($currentPage, $targetPage, $aliases = []) {
    if ($currentPage === $targetPage) return true;
    if (in_array($currentPage, $aliases)) return true;
    return false;
}

function getParentActiveClass($currentPage, $childPages) {
    return in_array($currentPage, $childPages) ? 'parent-active' : '';
}

function getSubmenuActiveClass($currentPage, $childPages) {
    return in_array($currentPage, $childPages) ? 'active-section' : '';
}

// Define menu structure with page mappings
$menuStructure = [
    'hr' => ['employee-list', 'enroll-employee', 'attendance-master', 'shift-management', 'attendance-management', 'holiday-management', 'view-employee', 'edit-employee'],
    'clients' => ['society-onboarding', 'society-list', 'client-types', 'billing-dashboard', 'view-society', 'edit-society'],
    'salary' => ['advance-salary', 'salary-calculation', 'salary-records', 'salary-slips', 'statutory-deductions', 'deduction-master'],
    'ticketing' => ['ticket-list', 'create-ticket', 'ticket-details'],
    'activities' => ['activity-list', 'create-activity', 'view-activity', 'edit-activity'],
    'supervisor' => ['assign-sites', 'supervisor-list', 'supervisor-performance', 'supervisor-sites-map'],
    'teams' => ['assign-team', 'view-teams', 'roster-management', 'team-form'],
    'settings' => ['company-settings', 'mobile-app-settings', 'hr-settings']
];
?>
    <div class="flex h-screen bg-gray-900 text-white">
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 flex-shrink-0 bg-gray-800 flex flex-col justify-between h-screen md:sticky md:top-0 absolute md:relative transform -translate-x-full md:translate-x-0 sidebar">
            <div class="flex flex-col flex-1 overflow-hidden">
                <div class="p-4 text-2xl font-bold border-b border-gray-700">
                    <a href="#"><?php echo htmlspecialchars($company_settings['company_name'] ?? 'GuardSys'); ?></a>
                </div>
                <nav class="flex-1 mt-4 overflow-y-auto overflow-x-hidden">
                    <a href="index.php?page=dashboard" class="flex items-center py-3 px-4 sidebar-link <?php echo isActiveRoute($page, 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Dashboard</span>
                    </a>
                    <!-- Dashboard v2 temporarily hidden -->
                    <!-- <a href="index.php?page=dashboard-v2" class="flex items-center py-3 px-4 sidebar-link <?php echo isActiveRoute($page, 'dashboard-v2') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie w-6 text-center"></i><span class="ml-3">Dashboard v2</span>
                    </a> -->
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['hr']); ?>" onclick="toggleSubmenu('hr-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-users-cog w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">HR</span></span>
                            <i id="hr-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="hr-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['hr']); ?>">
                            <a href="index.php?page=employee-list" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'employee-list') ? 'active' : ''; ?>"><i class="fas fa-list-ul w-6 flex-shrink-0"></i><span class="ml-3 truncate">Employee List</span></a>
                            <a href="index.php?page=enroll-employee" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'enroll-employee') ? 'active' : ''; ?>"><i class="fas fa-user-plus w-6 flex-shrink-0"></i><span class="ml-3 truncate">Enroll New Employee</span></a>
                            <a href="index.php?page=attendance-master" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'attendance-master') ? 'active' : ''; ?>"><i class="fas fa-tasks w-6 flex-shrink-0"></i><span class="ml-3 truncate">Attendance Master</span></a>
                            <a href="index.php?page=shift-management" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'shift-management') ? 'active' : ''; ?>"><i class="fas fa-clock w-6 flex-shrink-0"></i><span class="ml-3 truncate">Shift Management</span></a>
                            <a href="index.php?page=attendance-management" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'attendance-management') ? 'active' : ''; ?>"><i class="far fa-calendar-check w-6 flex-shrink-0"></i><span class="ml-3 truncate">Attendance Management</span></a>
                            <a href="index.php?page=holiday-management" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'holiday-management') ? 'active' : ''; ?>"><i class="fas fa-calendar w-6 flex-shrink-0"></i><span class="ml-3 truncate">Holiday Management</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['clients']); ?>" onclick="toggleSubmenu('clients-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-building w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Clients</span></span>
                            <i id="clients-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="clients-submenu" class="submenu pl-8 bg-gray-700 collapsed <?php echo getSubmenuActiveClass($page, $menuStructure['clients']); ?>">
                            <a href="index.php?page=society-onboarding" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'society-onboarding') ? 'active' : ''; ?>"><i class="fas fa-handshake w-6 flex-shrink-0"></i><span class="ml-3 truncate">Client Onboarding</span></a>
                            <a href="index.php?page=society-list" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'society-list') ? 'active' : ''; ?>"><i class="fas fa-stream w-6 flex-shrink-0"></i><span class="ml-3 truncate">Client List</span></a>
                            <a href="index.php?page=client-types" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'client-types') ? 'active' : ''; ?>"><i class="fas fa-database w-6 flex-shrink-0"></i><span class="ml-3 truncate">Client Master</span></a>
                            <a href="index.php?page=billing-dashboard" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'billing-dashboard') ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar w-6 flex-shrink-0"></i><span class="ml-3 truncate">Billing</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['salary']); ?>" onclick="toggleSubmenu('salary-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-wallet w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Salary</span></span>
                            <i id="salary-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="salary-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['salary']); ?>">
                            <a href="index.php?page=advance-salary" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'advance-salary') ? 'active' : ''; ?>"><i class="fas fa-hand-holding-usd w-6 flex-shrink-0"></i><span class="ml-3 truncate">Advance Salary</span></a>
                            <a href="index.php?page=salary-calculation" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'salary-calculation') ? 'active' : ''; ?>"><i class="fas fa-calculator w-6 flex-shrink-0"></i><span class="ml-3 truncate">Salary Calculation</span></a>
                            <a href="index.php?page=salary-records" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'salary-records') ? 'active' : ''; ?>"><i class="fas fa-file-invoice w-6 flex-shrink-0"></i><span class="ml-3 truncate">Salary Records</span></a>
                            <a href="index.php?page=salary-slips" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'salary-slips') ? 'active' : ''; ?>"><i class="fas fa-receipt w-6 flex-shrink-0"></i><span class="ml-3 truncate">Salary Slips</span></a>
                            <a href="index.php?page=statutory-deductions" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'statutory-deductions') ? 'active' : ''; ?>"><i class="fas fa-balance-scale w-6 flex-shrink-0"></i><span class="ml-3 truncate">Statutory Deductions</span></a>
                            <a href="index.php?page=deduction-master" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'deduction-master') ? 'active' : ''; ?>"><i class="fas fa-list-alt w-6 flex-shrink-0"></i><span class="ml-3 truncate">Deduction Master</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['ticketing']); ?>" onclick="toggleSubmenu('ticketing-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-ticket-alt w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Ticketing</span></span>
                            <i id="ticketing-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="ticketing-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['ticketing']); ?>">
                            <a href="index.php?page=ticket-list" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'ticket-list') ? 'active' : ''; ?>"><i class="fas fa-list w-6 flex-shrink-0"></i><span class="ml-3 truncate">All Tickets</span></a>
                            <a href="index.php?page=create-ticket" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'create-ticket') ? 'active' : ''; ?>"><i class="fas fa-plus-circle w-6 flex-shrink-0"></i><span class="ml-3 truncate">Create Ticket</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['activities']); ?>" onclick="toggleSubmenu('activity-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-sitemap w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Activities</span></span>
                            <i id="activity-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="activity-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['activities']); ?>">
                            <a href="index.php?page=activity-list" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'activity-list') ? 'active' : ''; ?>"><i class="fas fa-list w-6 flex-shrink-0"></i><span class="ml-3 truncate">Activity List</span></a>
                            <a href="index.php?page=create-activity" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'create-activity') ? 'active' : ''; ?>"><i class="fas fa-plus-circle w-6 flex-shrink-0"></i><span class="ml-3 truncate">Create Activity</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['supervisor']); ?>" onclick="toggleSubmenu('supervisor-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-user-tie w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Supervisor</span></span>
                            <i id="supervisor-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="supervisor-submenu" class="submenu pl-8 bg-gray-700 collapsed <?php echo getSubmenuActiveClass($page, $menuStructure['supervisor']); ?>">
                            <a href="index.php?page=assign-sites" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'assign-sites') ? 'active' : ''; ?>"><i class="fas fa-map-marker-alt w-6 flex-shrink-0"></i><span class="ml-3 truncate">Assign Sites</span></a>
                            <a href="index.php?page=supervisor-list" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'supervisor-list') ? 'active' : ''; ?>"><i class="fas fa-list w-6 flex-shrink-0"></i><span class="ml-3 truncate">View List</span></a>
                            <a href="index.php?page=supervisor-performance" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'supervisor-performance') ? 'active' : ''; ?>"><i class="fas fa-chart-line w-6 flex-shrink-0"></i><span class="ml-3 truncate">Performance</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['teams']); ?>" onclick="toggleSubmenu('teams-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-users w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Teams</span></span>
                            <i id="teams-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="teams-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['teams']); ?>">
                            <a href="index.php?page=assign-team" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'assign-team') ? 'active' : ''; ?>"><i class="fas fa-user-plus w-6 flex-shrink-0"></i><span class="ml-3 truncate">Assign Team</span></a>
                            <a href="index.php?page=view-teams" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'view-teams') ? 'active' : ''; ?>"><i class="fas fa-list w-6 flex-shrink-0"></i><span class="ml-3 truncate">View Teams</span></a>
                            <a href="index.php?page=roster-management" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'roster-management') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt w-6 flex-shrink-0"></i><span class="ml-3 truncate">Roster</span></a>
                        </div>
                    </div>
                    <div>
                        <button class="w-full flex justify-between items-center py-3 px-4 sidebar-link <?php echo getParentActiveClass($page, $menuStructure['settings']); ?>" onclick="toggleSubmenu('settings-submenu')">
                            <span class="flex items-center flex-1 min-w-0"><i class="fas fa-cog w-6 text-center flex-shrink-0"></i><span class="ml-3 truncate">Settings</span></span>
                            <i id="settings-submenu-arrow" class="fas fa-chevron-down transform transition-transform duration-200 flex-shrink-0"></i>
                        </button>
                        <div id="settings-submenu" class="submenu pl-8 bg-gray-700 <?php echo getSubmenuActiveClass($page, $menuStructure['settings']); ?>">
                            <a href="index.php?page=company-settings" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'company-settings') ? 'active' : ''; ?>"><i class="fas fa-building w-6 flex-shrink-0"></i><span class="ml-3 truncate">Company Settings</span></a>
                            <a href="index.php?page=mobile-app-settings" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'mobile-app-settings') ? 'active' : ''; ?>"><i class="fas fa-mobile-alt w-6 flex-shrink-0"></i><span class="ml-3 truncate">Mobile App Settings</span></a>
                            <!-- <a href="index.php?page=hr-settings" class="flex items-center py-2 px-4 sidebar-link <?php echo isActiveRoute($page, 'hr-settings') ? 'active' : ''; ?>"><i class="fas fa-user-cog w-6 flex-shrink-0"></i><span class="ml-3 truncate">HR Settings</span></a> -->
                        </div>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-gray-700 relative">
                <button id="user-menu-button" class="flex items-center w-full text-left">
                    <img class="h-10 w-10 rounded-full object-cover" 
                         src="<?= htmlspecialchars($user_profile_photo ?? 'https://i.pravatar.cc/100') ?>" 
                         alt="User avatar">
                    <div class="ml-3">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($user_full_name ?? 'Guest'); ?></p>
                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($user_role ?? 'N/A'); ?></p>
                    </div>
                </button>
                <div id="user-menu" class="absolute bottom-full left-0 mb-2 w-full bg-gray-900 rounded-md shadow-lg hidden">
                    <a href="index.php?page=view-employee&id=<?php echo htmlspecialchars($user_id ?? ''); ?>" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Profile</a>
                    <a href="#" onclick="logout()" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Logout</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 min-w-0 flex flex-col bg-gray-900">
            <header class="md:hidden flex justify-between items-center p-4 bg-gray-800 text-white">
                <h1 class="text-xl font-bold capitalize"><?php echo str_replace('-', ' ', $page); ?></h1>
                 <button id="sidebar-toggle" class="text-white"><i class="fas fa-bars fa-lg"></i></button>
            </header>

            <main class="flex-1 min-w-0 overflow-y-auto p-6">
                <?php
                    // Load the specific view for the requested page
                    if ($page === 'dashboard') {
                        require __DIR__ . '/dashboard_content.php';
                    } elseif ($page === 'dashboard-v2') {
                        require __DIR__ . '/hr/dashboard_v2_view.php';
                    } elseif ($page === 'employee-list') {
                        require __DIR__ . '/employee_list_view.php';
                    } elseif ($page === 'enroll-employee') {
                        require __DIR__ . '/enroll_employee_view.php';
                    } elseif ($page === 'view-employee') {
                        require __DIR__ . '/view_employee_view.php';
                    } elseif ($page === 'edit-employee') {
                        require __DIR__ . '/edit_employee_view.php';
                    } elseif ($page === 'society-onboarding') {
                        require __DIR__ . '/society_onboarding_view.php';
                    } elseif ($page === 'society-list') {
                        require __DIR__ . '/society_list_view.php';
                    } elseif ($page === 'view-society') {
                        require __DIR__ . '/view_society_view.php';
                    } elseif ($page === 'edit-society') {
                        require __DIR__ . '/edit_society_view.php';
                    } elseif ($page === 'ticket-list') {
                        require __DIR__ . '/ticket_list_view.php';
                    } elseif ($page === 'create-ticket') {
                        require __DIR__ . '/create_ticket_view.php';
                    } elseif ($page === 'ticket-details') {
                        require __DIR__ . '/ticket_details_view.php';
                    } elseif ($page === 'activity-list') {
                        require __DIR__ . '/activity_list_view.php';
                    } elseif ($page === 'create-activity') {
                        require __DIR__ . '/create_activity_view.php';
                    } elseif ($page === 'view-activity') {
                        require __DIR__ . '/view_activity_view.php';
                    } elseif ($page === 'edit-activity') {
                        require __DIR__ . '/edit_activity_view.php';
                    } elseif ($page === 'company-settings') {
                        require __DIR__ . '/company_settings_view.php';
                    } elseif ($page === 'mobile-app-settings') {
                        require __DIR__ . '/mobile_app_settings_view.php';
                    } elseif ($page === 'hr-settings') {
                        require __DIR__ . '/hr_settings_view.php';
                    } elseif ($page === 'attendance-master') {
                        require __DIR__ . '/hr/attendance_master/index.php';
                    } elseif ($page === 'view-attendance-type') {
                        require __DIR__ . '/hr/attendance_master/view.php';
                    } elseif ($page === 'attendance-management') {
                        require __DIR__ . '/hr/attendance_management/index.php';
                    } elseif ($page === 'holiday-management') {
                        require __DIR__ . '/hr/holiday_management/index.php';
                    } elseif ($page === 'shift-management') {
                        require __DIR__ . '/hr/shift_management/index.php';
                    } elseif ($page === 'assign-sites') {
                        require __DIR__ . '/assign_sites_view.php';
                    } elseif ($page === 'supervisor-list') {
                        require __DIR__ . '/supervisor_list_view.php';
                    } elseif ($page === 'supervisor-performance') {
                        require __DIR__ . '/supervisor_performance_view.php';
                    } elseif ($page === 'supervisor-sites-map') {
                        require __DIR__ . '/supervisor_sites_map_view.php';
                    } elseif ($page === 'client-types') {
                        require __DIR__ . '/client_types_view.php';
                    } elseif ($page === 'assign-team') {
                        require __DIR__ . '/assign_team_view.php';
                    } elseif ($page === 'view-teams') {
                        require __DIR__ . '/view_teams_view.php';
                    } elseif ($page === 'view_team_details') {
                        require __DIR__ . '/view_team_details.php';
                    } elseif ($page === 'roster-management') {
                        require __DIR__ . '/roster_management_view.php';
                    } elseif ($page === 'billing-dashboard') {
                        require __DIR__ . '/billing_dashboard_view.php';
                    } elseif ($page === 'client-billing') {
                        require __DIR__ . '/client_billing_view.php';
                    } elseif ($page === 'create-team') {
                        require __DIR__ . '/team_form_view.php';
                    } elseif ($page === 'edit-team') {
                        require __DIR__ . '/team_form_view.php';
                    } elseif ($page === 'advance-salary') {
                        require __DIR__ . '/advance_payment_management_view.php';
                    } elseif ($page === 'salary-calculation') {
                        require __DIR__ . '/hr/salary_management/salary_calculation_view.php';
                    } elseif ($page === 'salary-records') {
                        require __DIR__ . '/hr/salary_management/salary_records_view.php';
                    } elseif ($page === 'salary-slips') {
                        require __DIR__ . '/hr/salary_management/salary_slips_view.php';
                    } elseif ($page === 'statutory-deductions') {
                        require __DIR__ . '/hr/salary_management/statutory_deductions_view.php';
                    } elseif ($page === 'deduction-master') {
                        require __DIR__ . '/hr/salary_management/deduction_master_view.php';
                    }
                ?>
            </main>
        </div>
    </div>

    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const arrow = document.getElementById(id + '-arrow');
            const allSubmenus = document.querySelectorAll('.submenu');

            // Close all other submenus
            allSubmenus.forEach(item => {
                if (item.id !== id) {
                    item.classList.remove('expanded');
                    item.classList.add('collapsed');
                    const otherArrow = document.getElementById(item.id + '-arrow');
                    if (otherArrow) {
                        otherArrow.classList.remove('rotate-180');
                    }
                }
            });

            // Toggle the clicked submenu
            if (submenu.classList.contains('expanded')) {
                submenu.classList.remove('expanded');
                submenu.classList.add('collapsed');
                if (arrow) arrow.classList.remove('rotate-180');
            } else {
                submenu.classList.remove('collapsed');
                submenu.classList.add('expanded');
                if (arrow) arrow.classList.add('rotate-180');
            }

            // Persist state
            try { 
                localStorage.setItem('submenu:'+id, submenu.classList.contains('expanded') ? 'open' : 'closed'); 
            } catch(e) {}
        }

        // Initialize sidebar state - collapse all submenus by default, expand only active section
        document.addEventListener('DOMContentLoaded', function() {
            // First, collapse ALL submenus by default
            const allSubmenus = document.querySelectorAll('.submenu');
            const allArrows = document.querySelectorAll('[id$="-arrow"]');
            
            allSubmenus.forEach(submenu => {
                submenu.classList.remove('expanded');
                submenu.classList.add('collapsed');
            });
            
            allArrows.forEach(arrow => {
                arrow.classList.remove('rotate-180');
            });
            
            // Then, find and expand only the submenu containing the active page
            const activeChildLinks = document.querySelectorAll('.submenu .sidebar-link.active');
            activeChildLinks.forEach(link => {
                const parentSubmenu = link.closest('.submenu');
                if (parentSubmenu) {
                    // Expand only this submenu
                    parentSubmenu.classList.remove('collapsed');
                    parentSubmenu.classList.add('expanded');
                    
                    // Rotate the arrow for this submenu
                    const arrow = document.getElementById(parentSubmenu.id + '-arrow');
                    if (arrow) {
                        arrow.classList.add('rotate-180');
                    }
                }
            });
        });

        const sidebarToggleBtn = document.getElementById('sidebar-toggle');
        if (sidebarToggleBtn) sidebarToggleBtn.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');
        userMenuButton.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', () => {
            userMenu.classList.add('hidden');
        });

        function logout() {
            // It's better to handle logout on the server-side to invalidate the token/session there.
            // We will redirect to a logout page that will handle the cookie deletion and session destruction.
            window.location.href = 'index.php?page=logout';
        }

        // Enhanced submenu management with active state awareness
        window.addEventListener('load', () => {
            const currentPage = '<?php echo $page; ?>';
            const menuStructure = <?php echo json_encode($menuStructure); ?>;
            
            // Ensure all submenus are collapsed first
            const allSubmenus = document.querySelectorAll('.submenu');
            allSubmenus.forEach(submenu => {
                submenu.classList.remove('expanded');
                submenu.classList.add('collapsed');
            });
            
            // Auto-expand only the submenu that contains the current page
            Object.keys(menuStructure).forEach(menuKey => {
                if (menuStructure[menuKey].includes(currentPage)) {
                    const submenuId = menuKey + '-submenu';
                    const submenu = document.getElementById(submenuId);
                    if (submenu) {
                        submenu.classList.remove('collapsed');
                        submenu.classList.add('expanded');
                        const arrow = document.getElementById(submenuId + '-arrow');
                        if (arrow) arrow.classList.add('rotate-180');
                    }
                }
            });
            
            // Add visual feedback for active parent menus
            document.querySelectorAll('.parent-active').forEach(parentBtn => {
                // Subtle animation to draw attention to active parent
                setTimeout(() => {
                    parentBtn.style.animation = 'activeParentPulse 1.5s ease-in-out';
                }, 300);
            });
            
            // Smooth scroll to active item if it's in view
            const activeLink = document.querySelector('.sidebar-link.active');
            if (activeLink) {
                setTimeout(() => {
                    activeLink.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest',
                        inline: 'nearest'
                    });
                }, 500);
            }
        });
        
        // Add CSS animation for active parent highlighting
        const style = document.createElement('style');
        style.textContent = `
            @keyframes activeParentPulse {
                0% { transform: translateX(0); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
                50% { transform: translateX(2px); box-shadow: 0 0 10px rgba(37, 99, 235, 0.3); }
                100% { transform: translateX(0); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
            }
            
            .sidebar-link.active {
                animation: activeItemGlow 2s ease-in-out;
            }
            
            @keyframes activeItemGlow {
                0% { box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); }
                50% { box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4); }
                100% { box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html> 