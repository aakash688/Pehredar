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

// Get all supervisors with count of assigned sites
$supervisors_query = "SELECT u.id, u.first_name, u.surname, 
                            u.email_id, u.mobile_number, 
                            u.date_of_joining, u.profile_photo,
                            u.user_type,
                            COUNT(ssa.site_id) as sites_assigned
                     FROM users u
                     LEFT JOIN supervisor_site_assignments ssa ON u.id = ssa.supervisor_id
                     WHERE u.user_type IN ('Supervisor', 'Site Supervisor')
                     GROUP BY u.id";
$supervisors = $db->query($supervisors_query)->fetchAll();
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Supervisor List</h1>
        <div>
            <input type="text" id="search-input" placeholder="Search supervisors..." class="bg-gray-700 border border-gray-600 text-white rounded p-2">
        </div>
    </div>
    
    <!-- Supervisors Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="supervisors-container">
        <?php if (empty($supervisors)): ?>
        <div class="col-span-full text-center p-6 bg-gray-800 rounded-lg">
            <p class="text-gray-400">No supervisors found.</p>
        </div>
        <?php else: ?>
            <?php foreach ($supervisors as $supervisor): ?>
            <div class="supervisor-card bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center mb-4">
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
                            <h3 class="text-lg font-semibold text-white"><?= htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['surname']) ?></h3>
                            <p class="text-gray-400"><?= htmlspecialchars($supervisor['user_type']) ?></p>
                        </div>
                    </div>
                    <div class="space-y-2 text-gray-300">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-gray-500 w-6"></i>
                            <span><?= htmlspecialchars($supervisor['email_id']) ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-gray-500 w-6"></i>
                            <span><?= htmlspecialchars($supervisor['mobile_number']) ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar text-gray-500 w-6"></i>
                            <span>Joined: <?= date('d M, Y', strtotime($supervisor['date_of_joining'])) ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-gray-500 w-6"></i>
                            <span><?= htmlspecialchars($supervisor['sites_assigned']) ?> Sites Assigned</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between">
                        <a href="index.php?page=view-employee&id=<?= htmlspecialchars($supervisor['id']) ?>" class="text-blue-400 hover:text-blue-300">
                            <i class="fas fa-user mr-1"></i> Profile
                        </a>
                        <a href="index.php?page=supervisor-sites-map&id=<?= htmlspecialchars($supervisor['id']) ?>" class="text-green-400 hover:text-green-300">
                            <i class="fas fa-map-marked-alt mr-1"></i> View Sites Map
                        </a>
                        <?php if ($supervisor['sites_assigned'] > 0): ?>
                        <a href="index.php?page=supervisor-performance&id=<?= htmlspecialchars($supervisor['id']) ?>" class="text-yellow-400 hover:text-yellow-300">
                            <i class="fas fa-chart-line mr-1"></i> Performance
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Map Modal -->
<div id="map-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg p-4 w-11/12 max-w-6xl h-5/6">
        <div class="flex justify-between items-center mb-4">
            <h2 id="map-title" class="text-xl font-bold text-white">Sites Assigned to Supervisor</h2>
            <button id="close-map-modal" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 h-full">
            <div class="lg:col-span-3 h-full">
                <div id="sites-map" class="rounded-lg h-full bg-gray-700"></div>
            </div>
            <div class="overflow-y-auto bg-gray-700 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-white mb-4">Assigned Sites</h3>
                <div id="sites-list" class="space-y-3">
                    <!-- Sites will be populated here dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

 