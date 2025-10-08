<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<!-- Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg">
        <h3 class="text-lg font-medium text-gray-400">Total Locations & Societies</h3>
        <p class="text-4xl font-bold text-white mt-2"><?= $page_data['total_societies'] ?? 0 ?></p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg">
        <h3 class="text-lg font-medium text-gray-400">Total Staff</h3>
        <p class="text-4xl font-bold text-white mt-2"><?= $page_data['total_staff'] ?? 0 ?></p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg">
        <h3 class="text-lg font-medium text-gray-400">Employees Active</h3>
        <p class="text-4xl font-bold text-white mt-2"><?= $page_data['active_employees'] ?? 0 ?></p>
    </div>
</div>

<!-- Map Container -->
<div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden" style="height: 70vh;">
    <div id="map" style="width: 100%; height: 100%;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('map').setView([20.5937, 78.9629], 5); // Center on India with better zoom level
    map.setMaxBounds([[-50, -180], [80, 180]]); // Restrict map bounds to India region

    // Add the tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Add markers for all societies with coordinates
    <?php if (!empty($page_data['societies_for_map'])): ?>
        <?php foreach ($page_data['societies_for_map'] as $location): ?>
            L.marker([<?= $location['latitude'] ?>, <?= $location['longitude'] ?>])
             .bindPopup("<?= htmlspecialchars($location['society_name']) ?>")
             .addTo(map);
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

 