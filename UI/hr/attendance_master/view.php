<?php
// UI/hr/attendance_master/view.php
$type = $page_data['attendance_type'] ?? null;
?>
<div class="p-6">
    <h1 class="text-3xl font-bold text-white mb-6">Attendance Type Details</h1>
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl text-white"><?= htmlspecialchars($type['name']) ?></h2>
            <a href="index.php?page=attendance-master" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
        <dl class="divide-y divide-gray-700">
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Code</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['code']) ?></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Name</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['name']) ?></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Description</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['description']) ?></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Multiplier</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['multiplier']) ?></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Status</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $type['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Created At</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['created_at']) ?></dd>
            </div>
            <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm font-medium text-gray-400">Updated At</dt>
                <dd class="mt-1 text-sm text-white sm:mt-0 sm:col-span-2"><?= htmlspecialchars($type['updated_at']) ?></dd>
            </div>
        </dl>
    </div>
</div> 