<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// Load supervisor data
include_once 'helpers/database.php';
$db = new Database();

// Get supervisor ID from URL
$supervisor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "<!-- Debug: Supervisor ID = {$supervisor_id} -->";

if ($supervisor_id <= 0) {
    echo '<div class="bg-red-500 text-white p-4 rounded-lg mb-4">Invalid supervisor ID provided</div>';
    exit;
}

// Get supervisor details
$supervisor_query = "SELECT id, CONCAT(first_name, ' ', surname) as name, user_type, profile_photo 
                    FROM users 
                    WHERE id = ? AND user_type IN ('Supervisor', 'Site Supervisor')";
$supervisor = $db->query($supervisor_query, [$supervisor_id])->fetch();

echo "<!-- Debug: Supervisor found = " . ($supervisor ? "Yes" : "No") . " -->";
if ($supervisor) {
    echo "<!-- Debug: Supervisor name = {$supervisor['name']}, type = {$supervisor['user_type']} -->";
}

if (!$supervisor) {
    echo '<div class="bg-red-500 text-white p-4 rounded-lg mb-4">Supervisor not found</div>';
    exit;
}

// Get all sites assigned to this supervisor
$sites_query = "SELECT s.* 
               FROM society_onboarding_data s
               JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
               WHERE ssa.supervisor_id = ?";

// Debug: Count query before fetching full data               
$count_query = "SELECT COUNT(*) as count FROM supervisor_site_assignments WHERE supervisor_id = ?";
$count_result = $db->query($count_query, [$supervisor_id])->fetch();
echo "<!-- Debug: Number of assigned sites = {$count_result['count']} -->";

// Actual site data fetch               
$sites = $db->query($sites_query, [$supervisor_id])->fetchAll();
echo "<!-- Debug: Number of fetched sites = " . count($sites) . " -->";
?>

<div class="container mx-auto px-4 h-full flex flex-col">
    <!-- Supervisor Header -->
    <div class="flex items-center justify-between mb-6 bg-gray-800 p-4 rounded-lg">
        <div class="flex items-center">
            <div class="h-16 w-16 rounded-full overflow-hidden bg-gray-700 flex-shrink-0">
                <?php if (!empty($supervisor['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($supervisor['profile_photo']) ?>" alt="Profile" class="h-full w-full object-cover">
                <?php else: ?>
                <div class="h-full w-full flex items-center justify-center text-gray-400 text-xl">
                    <i class="fas fa-user"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="ml-4">
                <h1 class="text-2xl font-bold text-white"><?= htmlspecialchars($supervisor['name']) ?></h1>
                <p class="text-gray-400"><?= htmlspecialchars($supervisor['user_type']) ?></p>
            </div>
        </div>
        <div>
            <a href="index.php?page=supervisor-list" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Sites Count -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-xl font-semibold text-white">Assigned Sites (<?= count($sites) ?>)</h2>
        </div>
        <div class="flex items-center space-x-3">
            <button id="map-view-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded active">
                <i class="fas fa-map-marked-alt mr-2"></i> Map View
            </button>
            <button id="list-view-btn" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
                <i class="fas fa-list mr-2"></i> List View
            </button>
        </div>
    </div>

    <!-- Main Content Area with Map and Sites List -->
    <div class="flex-grow grid grid-cols-1 lg:grid-cols-4 gap-6 mb-16">
        <!-- Map Container (3/4 width on large screens) -->
        <div id="map-container" class="lg:col-span-3 bg-gray-800 rounded-lg overflow-hidden h-[70vh] shadow-xl">
            <div id="sites-map" class="h-full w-full" data-sites='<?= json_encode($sites) ?>'>
                <?php if (empty($sites)): ?>
                <div class="h-full flex items-center justify-center text-gray-400">
                    <div class="text-center">
                        <i class="fas fa-map-marked-alt text-4xl mb-3 opacity-50"></i>
                        <p class="text-lg">No sites are currently assigned to this supervisor.</p>
                        <p class="text-sm mt-2">You can assign sites from the <a href="index.php?page=assign-sites" class="text-blue-400 hover:underline">Assign Sites</a> page.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sites List (1/4 width on large screens) -->
        <div id="sites-list-container" class="overflow-auto bg-gray-800 rounded-lg p-4 max-h-[70vh] shadow-xl">
            <h3 class="text-lg font-semibold text-white mb-4">Site Details</h3>
            
            <?php if (empty($sites)): ?>
                <div class="text-gray-400 p-4 bg-gray-700 rounded-lg">No sites assigned to this supervisor</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($sites as $index => $site): ?>
                    <div class="site-card bg-gray-700 rounded-lg p-4 cursor-pointer hover:bg-gray-600 transition"
                         data-latitude="<?= htmlspecialchars($site['latitude']) ?>" 
                         data-longitude="<?= htmlspecialchars($site['longitude']) ?>"
                         data-index="<?= $index ?>">
                        <h4 class="font-semibold text-white"><?= htmlspecialchars($site['society_name']) ?></h4>
                        <div class="text-sm text-gray-400 mt-1">
                            <div><i class="fas fa-map-pin mr-2"></i><?= htmlspecialchars($site['city']) ?>, <?= htmlspecialchars($site['state']) ?></div>
                            <div><i class="fas fa-road mr-2"></i><?= htmlspecialchars($site['street_address']) ?></div>
                            <div><i class="fas fa-hashtag mr-2"></i><?= htmlspecialchars($site['pin_code']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Table View (Hidden initially) -->
        <div id="sites-table-container" class="lg:col-span-4 bg-gray-800 rounded-lg overflow-hidden hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-700 text-gray-300 uppercase text-sm">
                        <tr>
                            <th class="px-6 py-3">Client name</th>
                            <th class="px-6 py-3">Address</th>
                            <th class="px-6 py-3">City</th>
                            <th class="px-6 py-3">State</th>
                            <th class="px-6 py-3">PIN Code</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        <?php if (empty($sites)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center">No sites assigned to this supervisor</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($sites as $site): ?>
                            <tr class="hover:bg-gray-700">
                                <td class="px-6 py-4"><?= htmlspecialchars($site['society_name']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($site['street_address']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($site['city']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($site['state']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($site['pin_code']) ?></td>
                                <td class="px-6 py-4">
                                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm view-on-map"
                                            data-latitude="<?= htmlspecialchars($site['latitude']) ?>" 
                                            data-longitude="<?= htmlspecialchars($site['longitude']) ?>">
                                        <i class="fas fa-map-marker-alt mr-1"></i> View on Map
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Simple spacer footer -->
<div class="py-10"></div>

<script src="UI/assets/js/supervisor-sites-map.js"></script>
 