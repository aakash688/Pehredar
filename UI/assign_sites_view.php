<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// Load site and supervisor data
include_once 'helpers/database.php';
$db = new Database();

// Check for filter parameters
$filter = $_GET['filter'] ?? '';
$supervisor_id = isset($_GET['supervisor_id']) ? (int)$_GET['supervisor_id'] : 0;

// Build query based on filter
$params = [];
$where_clause = '';
$search = $_GET['search'] ?? '';

if ($filter === 'supervisor' && $supervisor_id > 0) {
    $where_clause = 'WHERE ssa.supervisor_id = ?';
    $params[] = $supervisor_id;
} else if ($filter === 'assigned') {
    $where_clause = 'WHERE ssa.supervisor_id IS NOT NULL';
} else if ($filter === 'unassigned') {
    $where_clause = 'WHERE ssa.supervisor_id IS NULL';
} else if ($filter === 'site' && !empty($search)) {
    $where_clause = 'WHERE s.society_name LIKE ? OR s.street_address LIKE ?';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Get all sites with supervisor count
$sites_query = "SELECT s.*, 
                      CONCAT(u.first_name, ' ', u.surname) as supervisor_name, 
                      u.id as supervisor_id, 
                      u.profile_photo as supervisor_profile_photo,
                      (SELECT cu.name 
                       FROM clients_users cu 
                       WHERE cu.society_id = s.id AND cu.is_primary = 1
                       LIMIT 1) as client_name,
                       COUNT(ssa.site_id) as supervisor_count
               FROM society_onboarding_data s
               LEFT JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
               LEFT JOIN users u ON ssa.supervisor_id = u.id
               $where_clause
               GROUP BY s.id";
$sites = $db->query($sites_query, $params)->fetchAll();

// Get all supervisors
$supervisors_query = "SELECT u.id, CONCAT(u.first_name, ' ', u.surname) as name, 
                           u.email_id, u.mobile_number, u.user_type, u.profile_photo
                    FROM users u
                    WHERE u.user_type IN ('Supervisor', 'Site Supervisor')
                    ORDER BY u.user_type, u.first_name";
$supervisors = $db->query($supervisors_query)->fetchAll();
?>

<div class="container mx-auto px-4">
    <h1 class="text-3xl font-bold mb-6 text-white">Assign Sites to Supervisors</h1>
    
    <!-- Filters -->
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-gray-400 mb-1">Filter by</label>
                <select id="filter-type" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2">
                    <option value="all" <?= $filter === '' ? 'selected' : '' ?>>All Sites</option>
                    <option value="assigned">Assigned Sites</option>
                    <option value="unassigned">Unassigned Sites</option>
                    <option value="supervisor" <?= $filter === 'supervisor' ? 'selected' : '' ?>>By Supervisor</option>
                    <option value="site">By Site Name/Location</option>
                </select>
            </div>
            <div id="supervisor-filter-container" class="flex-1 min-w-[200px] <?= $filter !== 'supervisor' ? 'hidden' : '' ?>">
                <label class="block text-gray-400 mb-1">Select Supervisor</label>
                <select id="supervisor-filter" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2">
                    <option value="">Select a supervisor</option>
                    <?php foreach ($supervisors as $supervisor): ?>
                    <option value="<?= htmlspecialchars($supervisor['id']) ?>" <?= $supervisor_id == $supervisor['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($supervisor['name']) ?> (<?= htmlspecialchars($supervisor['user_type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="site-search-container" class="flex-1 min-w-[200px] hidden">
                <label class="block text-gray-400 mb-1">Search by Site</label>
                <input type="text" id="site-search" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2" placeholder="Enter site name or location">
            </div>
            <div class="flex-none">
                <button id="apply-filter" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Apply Filter
                </button>
            </div>
        </div>
    </div>
    
    <!-- Sites Table -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-700 text-gray-300 uppercase text-sm">
                    <tr>
                        <th class="px-6 py-3">Client name</th>
                        <th class="px-6 py-3">City & PIN</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Assigned Supervisors</th>
                        <th class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200" id="sites-table-body">
                    <?php foreach ($sites as $site): ?>
                    <tr class="site-row hover:bg-gray-700" 
                        data-site-id="<?= htmlspecialchars($site['id']) ?>" 
                        data-site-name="<?= htmlspecialchars($site['society_name']) ?>"
                        data-site-location="<?= htmlspecialchars($site['street_address'] ?? '') ?>">
                        <td class="px-6 py-4"><?= htmlspecialchars($site['society_name']) ?></td>
                        <td class="px-6 py-4">
                            <?= htmlspecialchars($site['city'] ?? 'N/A') ?> 
                            <?php if (!empty($site['pin_code'])): ?>
                                <span class="text-gray-500">(<?= htmlspecialchars($site['pin_code']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($site['supervisor_count'])): ?>
                            <span class="px-3 py-1 bg-green-800 text-green-200 rounded-full text-xs">Assigned</span>
                            <?php else: ?>
                            <span class="px-3 py-1 bg-red-800 text-red-200 rounded-full text-xs">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($site['supervisor_name'])): ?>
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full overflow-hidden bg-gray-700 flex-shrink-0 mr-2">
                                        <?php if (!empty($site['supervisor_profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($site['supervisor_profile_photo']) ?>" alt="Supervisor" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center bg-gray-600 text-gray-300">
                                                <i class="fas fa-user-shield"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div><?= htmlspecialchars($site['supervisor_name']) ?></div>
                                        <?php if (!empty($site['supervisor_count']) && $site['supervisor_count'] > 1): ?>
                                            <div class="text-xs text-blue-400 hover:text-blue-300 cursor-pointer view-all-supervisors" 
                                                 data-site-id="<?= htmlspecialchars($site['id']) ?>"
                                                 data-site-name="<?= htmlspecialchars($site['society_name']) ?>">
                                                +<?= $site['supervisor_count'] - 1 ?> more (click to view all)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                Not Assigned
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <button class="assign-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" 
                                    onclick="openAssignModal(<?= $site['id'] ?>, '<?= addslashes($site['society_name']) ?>')">
                                <?= empty($site['supervisor_count']) ? 'Assign' : 'Manage' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center">No sites found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assign-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4 text-white">Assign Supervisors</h2>
        <p id="site-name-display" class="text-gray-300 mb-4"></p>
        
        <div class="mb-4">
            <label class="block text-gray-400 mb-1">Current Supervisors</label>
            <div id="current-supervisors" class="mb-4 space-y-2">
                <!-- Current supervisors will be displayed here -->
                <div class="text-gray-400 italic text-sm">Loading assigned supervisors...</div>
            </div>
            
            <label class="block text-gray-400 mb-1">Add Supervisor</label>
            <div class="flex space-x-2">
                <select id="supervisor-select" class="flex-1 bg-gray-700 border border-gray-600 text-white rounded p-2">
                    <option value="">Select a supervisor</option>
                    <?php foreach ($supervisors as $supervisor): ?>
                    <option value="<?= htmlspecialchars($supervisor['id']) ?>" 
                            data-photo="<?= htmlspecialchars($supervisor['profile_photo'] ?? '') ?>"
                            data-name="<?= htmlspecialchars($supervisor['name']) ?>"
                            data-type="<?= htmlspecialchars($supervisor['user_type']) ?>">
                        <?= htmlspecialchars($supervisor['name']) ?> (<?= htmlspecialchars($supervisor['user_type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button id="add-supervisor-btn" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <div id="selected-supervisor-preview" class="mt-3 flex items-center hidden">
                <div id="supervisor-photo" class="h-10 w-10 rounded-full overflow-hidden bg-gray-700 flex-shrink-0 mr-2">
                    <div class="h-full w-full flex items-center justify-center text-gray-400">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div id="supervisor-name" class="text-white"></div>
            </div>
        </div>
        
        <div class="flex justify-end mt-6 space-x-2">
            <button id="cancel-assign" class="bg-gray-600 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
            <button id="confirm-assign" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save Assignments</button>
        </div>
    </div>
</div>

<!-- All Supervisors Modal -->
<div id="all-supervisors-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white">All Assigned Supervisors</h2>
            <button id="close-all-supervisors" class="text-gray-400 hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p id="all-supervisors-site-name" class="text-gray-300 mb-4"></p>
        
        <div id="all-supervisors-list" class="space-y-3 max-h-96 overflow-y-auto">
            <!-- Supervisors will be listed here -->
            <div class="text-gray-400 italic text-sm">Loading supervisors...</div>
        </div>
        
        <div class="flex justify-end mt-6">
            <button id="manage-supervisors-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                Manage Assignments
            </button>
        </div>
    </div>
</div>

<script src="UI/assets/js/assign-sites.js"></script>
 