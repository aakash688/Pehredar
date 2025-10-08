<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// Removed header setting that was causing "headers already sent" error
require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

// Statistics queries
// Total teams
$totalTeams = $db->query('SELECT COUNT(*) as cnt FROM teams')->fetch()['cnt'];

// Total employees assigned to teams
$totalAssignedEmployees = $db->query('
    SELECT COUNT(DISTINCT user_id) as cnt 
    FROM team_members
')->fetch()['cnt'];

// Total unassigned employees
$totalEmployees = $db->query('
    SELECT COUNT(*) as cnt 
    FROM users 
    WHERE user_type IN ("Guard", "Armed Guard", "Bouncer", "Housekeeping")
')->fetch()['cnt'];

$totalUnassignedEmployees = $totalEmployees - $totalAssignedEmployees;

// Average team size
$averageTeamSize = 0;
if ($totalTeams > 0) {
    $averageTeamSize = $db->query('
        SELECT AVG(member_count) as avg_size FROM (
            SELECT team_id, COUNT(*) as member_count 
            FROM team_members 
            GROUP BY team_id
        ) as team_sizes
    ')->fetch()['avg_size'];
    $averageTeamSize = round($averageTeamSize, 1);
}

// Team with highest number of members
$largestTeam = $db->query('
    SELECT t.team_name, COUNT(tm.id) as member_count 
    FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    GROUP BY t.id
    ORDER BY member_count DESC
    LIMIT 1
')->fetch();

// Team with lowest number of members
$smallestTeam = $db->query('
    SELECT t.team_name, COUNT(tm.id) as member_count 
    FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    GROUP BY t.id
    ORDER BY member_count ASC
    LIMIT 1
')->fetch();

// Get all teams with basic info
$teams = $db->query('
    SELECT t.id, t.team_name, t.description, t.created_at,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
           u.id as supervisor_id, CONCAT(u.first_name, " ", u.surname) as supervisor_name,
           u.profile_photo as supervisor_photo
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.role = "Supervisor"
    LEFT JOIN users u ON tm.user_id = u.id
    ORDER BY t.team_name
')->fetchAll();

// Get performance data if available
$teamPerformance = [];
$performanceData = $db->query('
    SELECT team_id, AVG(attendance_percentage) as avg_attendance, 
           AVG(performance_score) as avg_performance
    FROM team_performance
    GROUP BY team_id
')->fetchAll();

foreach ($performanceData as $perf) {
    $teamPerformance[$perf['team_id']] = [
        'attendance' => $perf['avg_attendance'],
        'performance' => $perf['avg_performance']
    ];
}
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center mb-6">
        <div class="sm:flex-auto">
            <h1 class="text-3xl font-bold text-white">Teams</h1>
            <p class="mt-2 text-lg text-gray-400">View and manage your security teams.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="index.php?page=assign-team" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                <i class="fas fa-plus mr-2"></i> Assign Team
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- Team Stats Card -->
        <div class="stats-card bg-gradient-to-br from-blue-600 to-blue-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]" data-stat-type="total_teams">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-blue-100 mb-1">Teams</p>
                    <h3 class="text-2xl font-bold stat-value"><?php echo number_format($totalTeams); ?></h3>
                    </div>
                <div class="bg-blue-500/20 p-2 rounded-full">
                    <i class="fas fa-users text-lg"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-blue-100 opacity-90">
                <div class="flex justify-between w-full">
                    <span>Average size: <span class="stat-value-avg"><?php echo $averageTeamSize; ?></span> members</span>
                </div>
            </div>
        </div>

        <!-- Assignment Stats Card -->
        <div class="stats-card bg-gradient-to-br from-green-600 to-green-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]" data-stat-type="total_assigned">
            <div class="flex items-center justify-between">
            <div>
                    <p class="text-xs font-medium text-green-100 mb-1">Assigned Employees</p>
                    <h3 class="text-2xl font-bold stat-value"><?php echo number_format($totalAssignedEmployees); ?></h3>
                </div>
                <div class="bg-green-500/20 p-2 rounded-full">
                    <i class="fas fa-user-check text-lg"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-green-100 opacity-90">
                <div class="flex justify-between w-full">
                    <span>Unassigned: <span class="stat-value-unassigned"><?php echo number_format($totalUnassignedEmployees); ?></span></span>
                    <span>Total: <?php echo number_format($totalEmployees); ?></span>
        </div>
    </div>
        </div>

        <!-- Team Size Comparison Card -->
        <div class="bg-gradient-to-br from-purple-600 to-purple-800 p-4 rounded-lg shadow-md text-white transform transition-all duration-200 hover:scale-[1.02]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-purple-100 mb-1">Team Size Range</p>
                    <h3 class="text-lg font-bold">
                        <?php if (!empty($largestTeam) && !empty($smallestTeam)): ?>
                            <?php echo htmlspecialchars($smallestTeam['member_count']); ?> - <?php echo htmlspecialchars($largestTeam['member_count']); ?> members
                        <?php else: ?>
                            No data available
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="bg-purple-500/20 p-2 rounded-full">
                    <i class="fas fa-chart-bar text-lg"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center text-xs text-purple-100 opacity-90">
                <div class="flex justify-between w-full">
                    <?php if (!empty($largestTeam) && !empty($smallestTeam)): ?>
                        <span>Smallest: <?php echo htmlspecialchars($smallestTeam['team_name']); ?></span>
                        <span>Largest: <?php echo htmlspecialchars($largestTeam['team_name']); ?></span>
                    <?php else: ?>
                        <span>No team data available</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-grow">
            <input type="text" id="search-input" placeholder="Search teams by name or supervisor..." class="w-full bg-gray-700 p-3 pl-10 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <select id="size-filter" class="bg-gray-700 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none">
                <option value="">All Team Sizes</option>
                <option value="small">Small Teams (1-3)</option>
                <option value="medium">Medium Teams (4-10)</option>
                <option value="large">Large Teams (11+)</option>
            </select>
            <button type="button" id="apply-filters" class="bg-blue-600 hover:bg-blue-700 text-white font-medium p-3 rounded-lg">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
    </div>

    <!-- Teams Grid View -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <?php if (count($teams) == 0): ?>
            <div class="col-span-3 bg-gray-800 rounded-lg p-8 text-center">
                <p class="text-gray-400">No teams found. Create a team to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($teams as $team): 
                // Get performance data for this team
                $performance = isset($teamPerformance[$team['id']]) ? $teamPerformance[$team['id']] : null;
                
                // Get members count
                $memberCount = $team['member_count'];
                
                // Team size classification
                $sizeClass = 'small';
                if ($memberCount > 10) {
                    $sizeClass = 'large';
                } elseif ($memberCount > 3) {
                    $sizeClass = 'medium';
                }
            ?>
                <div class="team-card bg-gray-800 rounded-lg overflow-hidden shadow-lg transition-transform duration-300 hover:scale-[1.02]" 
                     data-team-id="<?php echo $team['id']; ?>"
                     data-team-name="<?php echo htmlspecialchars($team['team_name']); ?>"
                     data-team-size="<?php echo $sizeClass; ?>"
                     data-supervisor="<?php echo htmlspecialchars($team['supervisor_name']); ?>">
                    <div class="bg-gray-700 p-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-white"><?php echo htmlspecialchars($team['team_name']); ?></h3>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                <?php echo $memberCount; ?> members
                            </span>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 h-10 w-10">
                                <?php if (!empty($team['supervisor_photo'])): ?>
                                    <img class="h-10 w-10 rounded-full" src="<?php echo htmlspecialchars($team['supervisor_photo']); ?>" alt="">
                                <?php else: ?>
                                    <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-white">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-white">
                                    <?php echo htmlspecialchars($team['supervisor_name']); ?>
                                </p>
                                <p class="text-xs text-gray-400">Team Leader</p>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-400 mb-4 line-clamp-2" title="<?php echo htmlspecialchars($team['description']); ?>">
                            <?php echo !empty($team['description']) ? htmlspecialchars($team['description']) : 'No description available.'; ?>
                        </p>
                        
                        <!-- Action buttons -->
                        <div class="flex justify-between mt-4">
                            <a href="index.php?page=view_team_details&id=<?php echo $team['id']; ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 rounded text-xs font-medium text-white">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <button type="button" class="edit-team-btn inline-flex items-center px-3 py-1.5 bg-amber-600 hover:bg-amber-700 rounded text-xs font-medium text-white" data-team-id="<?php echo $team['id']; ?>" onclick="window.location.href='index.php?page=edit-team&id=<?php echo $team['id']; ?>'">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button type="button" class="delete-team-btn inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 rounded text-xs font-medium text-white" data-team-id="<?php echo $team['id']; ?>" data-team-name="<?php echo htmlspecialchars($team['team_name']); ?>">
                                <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Team Details Modal -->
<div id="team-details-modal" class="fixed inset-0 z-10 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Team Details</h3>
                        <div class="mt-4">
                            <div class="flex flex-col md:flex-row gap-6">
                                <div class="md:w-1/2">
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-400">Team Name</h4>
                                <p class="mt-1 text-sm text-white" id="team-name-display">Alpha Team</p>
                            </div>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-400">Team Leader</h4>
                                <p class="mt-1 text-sm text-white" id="team-leader-display">Mike Johnson</p>
                            </div>
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-400">Description</h4>
                                <p class="mt-1 text-sm text-white" id="team-description-display">Main security team for the north sector.</p>
                            </div>
                                    <div class="mb-4">
                                        <h4 class="text-sm font-medium text-gray-400">Created</h4>
                                        <p class="mt-1 text-sm text-white" id="team-created-display">June 8, 2023</p>
                                    </div>
                                </div>
                                <div class="md:w-1/2" id="team-performance-section">
                                    <h4 class="text-sm font-medium text-gray-400 mb-2">Performance Metrics</h4>
                                    <div id="team-performance-metrics">
                                        <div class="mb-3">
                                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                                <span>Attendance Rate</span>
                                                <span id="team-attendance-value">85%</span>
                                            </div>
                                            <div class="w-full bg-gray-700 rounded-full h-2">
                                                <div id="team-attendance-bar" class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                                <span>Performance Score</span>
                                                <span id="team-performance-value">7.5/10</span>
                                            </div>
                                            <div class="w-full bg-gray-700 rounded-full h-2">
                                                <div id="team-performance-bar" class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                                <span>Incidents Reported</span>
                                                <span id="team-incidents-value">5 this month</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <h4 class="text-sm font-medium text-gray-400 mb-2">Team Members</h4>
                                <div class="bg-gray-900 rounded-lg max-h-60 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-700">
                                        <thead class="bg-gray-700">
                                            <tr>
                                                <th scope="col" class="py-3 px-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                                                <th scope="col" class="py-3 px-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Role</th>
                                                <th scope="col" class="py-3 px-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Contact</th>
                                                <th scope="col" class="py-3 px-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Assigned Date</th>
                                            </tr>
                                        </thead>
                                        <tbody id="team-members-table" class="bg-gray-900 divide-y divide-gray-700">
                                            <!-- Team members will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="close-modal-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 text-base font-medium text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Team Confirmation Modal -->
<div id="delete-team-modal" class="fixed inset-0 hidden bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg shadow-xl w-full max-w-md">
        <h3 class="text-xl font-semibold text-white mb-4">Delete Team</h3>
        <p class="text-gray-300 mb-4">Are you sure you want to delete the team "<span id="delete-team-name" class="font-medium"></span>"?</p>
        <p class="text-red-400 mb-4">This action cannot be undone. All team members will be unassigned.</p>
        
        <div class="flex justify-end gap-3">
            <button type="button" id="cancel-delete-team" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white">
                Cancel
            </button>
            <button type="button" id="confirm-delete-team" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white">
                Delete Team
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-4 rounded-lg shadow-lg flex items-center">
        <svg class="animate-spin h-6 w-6 text-blue-600 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-gray-800">Processing...</span>
    </div>
</div>

<script>
// Preload any necessary data
let cachedTeamData = {};

// Use a more efficient DOM Ready pattern
(function() {
    // Execute code when DOM is fully loaded
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializePage);
                    } else {
        initializePage();
    }

    function initializePage() {
        const loadingOverlay = document.getElementById('loading-overlay');
        let selectedTeamId = null;
                                
        // Helper functions for loading overlay
        function showLoading() {
            loadingOverlay.classList.remove('hidden');
                            }
        
        function hideLoading() {
            loadingOverlay.classList.add('hidden');
        }
        
        // Event delegation for better performance
        document.addEventListener('click', function(e) {
            // Handle delete button clicks with event delegation
            if (e.target.closest('.delete-team-btn')) {
                e.preventDefault();
                e.stopPropagation();
                
                const button = e.target.closest('.delete-team-btn');
                const teamId = button.getAttribute('data-team-id');
                const teamName = button.getAttribute('data-team-name');
                
                document.getElementById('delete-team-name').textContent = teamName;
                selectedTeamId = teamId;
                
                document.getElementById('delete-team-modal').classList.remove('hidden');
        }
    });
    
        // Handle Cancel Delete button
        document.getElementById('cancel-delete-team')?.addEventListener('click', function() {
            document.getElementById('delete-team-modal').classList.add('hidden');
    });
    
        // Handle Confirm Delete button
        document.getElementById('confirm-delete-team')?.addEventListener('click', function() {
            if (!selectedTeamId) return;
            
            showLoading();
            
            fetch('actions/team_actions.php?action=delete_team', {
                    method: 'POST',
                    headers: {
                    'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                    team_id: selectedTeamId
                    })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    // Remove the team card from the UI without page reload
                    const teamCard = document.querySelector(`.team-card[data-team-id="${selectedTeamId}"]`);
                    if (teamCard) {
                        teamCard.style.transition = 'all 0.5s ease';
                        teamCard.style.opacity = '0';
                        teamCard.style.transform = 'scale(0.9)';
                        setTimeout(() => teamCard.remove(), 500);
                    }
                    document.getElementById('delete-team-modal').classList.add('hidden');
                    
                    // Show success toast/alert with more details
                    const successMessage = `Team "${document.getElementById('delete-team-name').textContent}" has been successfully deleted.`;
                    if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
                        GaurdUI.showToast(successMessage, 'success');
                    } else {
                        alert(successMessage);
                    }
                    
                    // Update team statistics
                    updateTeamStats();
                } else {
                    // Show detailed error message
                    const errorMessage = data.error || 'Failed to delete team. Please try again.';
                    if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
                        GaurdUI.showToast(errorMessage, 'error');
                    } else {
                        alert(errorMessage);
                    }
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                
                const errorMessage = 'An unexpected error occurred while deleting the team. Please try again.';
                if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
                    GaurdUI.showToast(errorMessage, 'error');
                } else {
                    alert(errorMessage);
                }
            });
        });
        
        // Function to update team statistics
        function updateTeamStats() {
            fetch('actions/team_actions.php?action=get_team_stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statsElements = document.querySelectorAll('.stats-card');
                    statsElements.forEach(el => {
                        const statType = el.getAttribute('data-stat-type');
                        const statValue = el.querySelector('.text-2xl');
                        
                        if (statType === 'total_teams' && statValue) {
                            statValue.textContent = data.stats.total_teams;
                        }
                        if (statType === 'total_assigned' && statValue) {
                            statValue.textContent = data.stats.total_assigned;
                }
                        if (statType === 'total_unassigned' && statValue) {
                            statValue.textContent = data.stats.total_unassigned;
            }
        });
                }
            })
            .catch(error => {
                console.error('Failed to update team stats:', error);
});
        }
    }
})();
</script> 