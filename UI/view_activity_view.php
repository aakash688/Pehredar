<?php
$activity = $activity ?? null;
$photos = $photos ?? [];
// $activity_assignees is prepared in controller when view-activity
?>

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-gray-800 rounded-xl shadow-2xl px-8 py-6 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <div>
            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($activity['title'] ?? 'Activity Details') ?></h1>
            <p class="text-lg text-blue-400">Society: <?= htmlspecialchars($activity['society_name'] ?? 'N/A') ?></p>
        </div>
        <div class="flex-shrink-0 flex gap-2 mt-4 sm:mt-0">
            <a href="index.php?page=edit-activity&id=<?= $activity['id'] ?? '' ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm">
                <i class="fas fa-edit mr-2"></i>Edit Activity
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl">
                <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Activity Information</h3></div>
                <div class="border-t border-gray-700 px-4 py-5 sm:px-6">
                    <dl class="divide-y divide-gray-700">
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Description</dt><dd class="mt-1 text-sm text-white sm:mt-0 col-span-2"><?= htmlspecialchars($activity['description'] ?? 'N/A') ?></dd></div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Scheduled Date</dt><dd class="mt-1 text-sm text-white sm:mt-0 col-span-2"><?= isset($activity['scheduled_date']) ? date('F j, Y, g:i a', strtotime($activity['scheduled_date'])) : 'N/A' ?></dd></div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Location</dt><dd class="mt-1 text-sm text-white sm:mt-0 col-span-2"><?= htmlspecialchars($activity['location'] ?? 'N/A') ?></dd></div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Status</dt><dd class="mt-1 text-sm text-white sm:mt-0 col-span-2"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-200 text-green-800"><?= htmlspecialchars($activity['status'] ?? 'N/A') ?></span></dd></div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4">
                            <dt class="text-sm font-medium text-gray-400">Assigned To</dt>
                            <dd class="mt-1 text-sm text-white sm:mt-0 col-span-2">
                                <?php if (!empty($activity_assignees ?? [])): ?>
                                    <?php foreach (($activity_assignees ?? []) as $assignee): ?>
                                        <span class="inline-block bg-blue-600 text-white text-xs font-semibold mr-2 px-2.5 py-1 rounded-full"><?php echo htmlspecialchars($assignee['name']); ?> (<?php echo htmlspecialchars($assignee['user_type']); ?>)</span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">None</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Tags</dt>
                            <dd class="mt-1 text-sm text-white sm:mt-0 col-span-2">
                                <?php
                                $tags_raw = $activity['tags'] ?? '';
                                $tags_array = json_decode($tags_raw, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($tags_array)) {
                                    // It's JSON from Tagify
                                    $tags = array_column($tags_array, 'value');
                                } else {
                                    // It's a plain comma-separated string
                                    $tags = !empty($tags_raw) ? array_map('trim', explode(',', $tags_raw)) : [];
                                }
                                
                                if (!empty($tags)) {
                                    foreach ($tags as $tag) {
                                        echo '<span class="inline-block bg-indigo-600 text-white text-xs font-semibold mr-2 px-2.5 py-1 rounded-full">' . htmlspecialchars($tag) . '</span>';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </dd>
                        </div>
                        <div class="py-3 sm:py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-400">Created By</dt><dd class="mt-1 text-sm text-white sm:mt-0 col-span-2"><?= htmlspecialchars($activity['creator_name'] ?? 'N/A') ?></dd></div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Right Column - Photo Gallery -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6">
                <h3 class="text-lg leading-6 font-medium text-white mb-4">Photo Gallery</h3>
                <?php if (!empty($photos)): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($photos as $photo): ?>
                            <?php $full = $photo['image_url_full'] ?? $photo['image_url'] ?? ''; ?>
                            <a href="<?= htmlspecialchars($full) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($full) ?>" alt="Activity Photo" class="rounded-lg object-cover h-32 w-full hover:opacity-80 transition">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400">No photos have been uploaded for this activity yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div> 