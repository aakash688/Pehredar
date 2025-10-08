<?php
global $societies, $activity; // Expect these to be populated from index.php
$activity = $activity ?? [];
?>
<head>
    <!-- Add Tagify from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />
    <!-- Centralized CSS -->
    <link rel="stylesheet" href="UI/assets/css/main.css">
</head>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6">Edit Activity</h1>
    
    <div id="form-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>

    <form id="edit-activity-form" action="index.php?action=update_activity" method="POST">
        <input type="hidden" name="activity_id" value="<?= htmlspecialchars($activity['id'] ?? '') ?>">
        <div class="space-y-6">
            <div class="form-group">
                <label for="society_id" class="block text-sm font-medium text-gray-300 mb-2">Select Society</label>
                <select id="society_id" name="society_id" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="">-- Select a Society --</option>
                    <?php if (!empty($societies)): ?>
                        <?php foreach ($societies as $society): ?>
                            <option value="<?= htmlspecialchars($society['id']) ?>" <?= (isset($activity['society_id']) && $activity['society_id'] == $society['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($society['society_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="assignees" class="block text-sm font-medium text-gray-300 mb-2">Assigned To (Optional)</label>
                <select id="assignees" name="assignees[]" multiple class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></select>
                <input type="hidden" name="assignees_submitted" value="1">
                <p class="text-xs text-gray-400 mt-2">Select one or more names. Leave empty if no specific assignee is needed.</p>
            </div>

            <div class="form-group">
                <label for="title" class="block text-sm font-medium text-gray-300 mb-2">Activity Title</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($activity['title'] ?? '') ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg" required>
            </div>

            <div class="form-group">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                <textarea id="description" name="description" rows="4" class="bg-gray-700 text-white w-full p-3 rounded-lg" required><?= htmlspecialchars($activity['description'] ?? '') ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="date" class="block text-sm font-medium text-gray-300 mb-2">Scheduled Date & Time</label>
                    <?php 
                        $dateValue = '';
                        if (isset($activity['scheduled_date'])) {
                            $date = new DateTime($activity['scheduled_date']);
                            $dateValue = $date->format('Y-m-d\TH:i');
                        }
                    ?>
                    <input type="datetime-local" id="date" name="date" value="<?= $dateValue ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg" required>
                </div>
                <div class="form-group">
                    <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Location</label>
                    <input type="text" id="location" name="location" value="<?= htmlspecialchars($activity['location'] ?? '') ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="tags" class="block text-sm font-medium text-gray-300 mb-2">Tags (comma-separated)</label>
                    <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($activity['tags'] ?? '') ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg">
                </div>
                <div class="form-group">
                    <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select id="status" name="status" class="bg-gray-700 text-white w-full p-3 rounded-lg" required>
                        <?php foreach (['Upcoming', 'Ongoing', 'Completed'] as $status): ?>
                            <option value="<?= $status ?>" <?= (isset($activity['status']) && $activity['status'] == $status) ? 'selected' : '' ?>>
                                <?= $status ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="attachments" class="block text-sm font-medium text-gray-300 mb-2">Add More Images</label>
                <input type="file" id="attachments" name="attachments[]" multiple accept="image/jpeg,image/png" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <div id="image-preview-container" class="image-preview-container"></div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" id="submit-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                    <i class="fas fa-save mr-2"></i> Update Activity
                </button>
            </div>
        </div>
    </form>

    <div class="mt-8">
        <h3 class="text-lg font-medium text-white mb-4">Existing Photos</h3>
        <div id="existing-photos" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($photos ?? [] as $photo): ?>
                <div class="relative" id="photo-<?= $photo['id'] ?>">
                    <img src="<?= htmlspecialchars($photo['image_url']) ?>" class="rounded-lg object-cover h-32 w-full">
                    <button data-photo-id="<?= $photo['id'] ?>" class="delete-photo-btn absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 text-xs w-6 h-6 flex items-center justify-center hover:bg-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Add Tagify JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.polyfills.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('edit-activity-form');
    const feedbackDiv = document.getElementById('form-feedback');
    const submitBtn = document.getElementById('submit-btn');
    const attachmentsInput = document.getElementById('attachments');
    const previewContainer = document.getElementById('image-preview-container');
    const societySelect = document.getElementById('society_id');
    const assigneesSelect = document.getElementById('assignees');

    // --- Tagify Initialization ---
    const tagsInput = document.querySelector('#tags');
    let tagify = new Tagify(tagsInput, {
        whitelist: [], // We will populate this from the server
        dropdown: {
            maxItems: 20,
            classname: 'tags-look',
            enabled: 0,
            closeOnSelect: false
        }
    });

    // Fetch existing tags for the whitelist
    fetch('index.php?action=get_all_tags')
        .then(res => res.json())
        .then(tags => {
            tagify.whitelist = tags;
        })
        .catch(err => console.error('Could not fetch tags', err));

    async function populateAssignees(societyId) {
        assigneesSelect.innerHTML = '';
        if (!societyId) return;
        try {
            const res = await fetch(`index.php?action=get_activity_assignees&society_id=${encodeURIComponent(societyId)}`);
            if (!res.ok) throw new Error('Failed to load assignees');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load assignees');
            const currentAssignees = (window.__activityAssignees || []).map(a => String(a.user_id));
            (data.assignees || []).forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = `${u.name} (${u.user_type})`;
                if (currentAssignees.includes(String(u.id))) opt.selected = true;
                assigneesSelect.appendChild(opt);
            });
        } catch (e) {
            console.error(e);
        }
    }

    // Initial population and on change
    try { window.__activityAssignees = <?php echo json_encode($activity_assignees ?? []); ?>; } catch (e) {}
    populateAssignees(societySelect.value);
    societySelect.addEventListener('change', () => populateAssignees(societySelect.value));

    let stagedFiles = [];

    attachmentsInput.addEventListener('change', () => {
        // Add new files to our stagedFiles array
        Array.from(attachmentsInput.files).forEach(file => {
            if (!stagedFiles.some(f => f.name === file.name && f.size === file.size)) {
                 stagedFiles.push(file);
            }
        });
        // Clear the input's file list to allow re-adding a removed file
        attachmentsInput.value = '';
        renderPreviews();
    });

    function renderPreviews() {
        previewContainer.innerHTML = '';
        stagedFiles.forEach((file, index) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src); // Free memory

            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '&times;';
            removeBtn.type = 'button';
            removeBtn.addEventListener('click', () => {
                stagedFiles.splice(index, 1);
                renderPreviews();
            });

            previewItem.appendChild(img);
            previewItem.appendChild(removeBtn);
            previewContainer.appendChild(previewItem);
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';

        const formData = new FormData(form);
        // Ensure multi-select values are included (some browsers handle automatically)
        const selectedAssignees = Array.from(assigneesSelect.options).filter(o => o.selected).map(o => o.value);
        formData.delete('assignees[]');
        selectedAssignees.forEach(v => formData.append('assignees[]', v));
        
        // Remove the empty 'attachments[]' from the form data
        formData.delete('attachments[]');

        // Append our staged files
        stagedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });

        fetch(form.action, {
            method: 'POST',
            body: formData, // No 'Content-Type' header - browser sets it for multipart/form-data
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(err => Promise.reject(err));
            }
            return res.json();
        })
        .then(data => {
            feedbackDiv.classList.remove('hidden');
            if (data.success) {
                feedbackDiv.className = 'p-4 mb-4 text-sm text-green-400 bg-green-900 rounded-lg';
                feedbackDiv.textContent = 'Success! Activity updated. Redirecting to list...';
                stagedFiles = []; // Clear staged files on success
                renderPreviews();
                setTimeout(() => window.location.href = `index.php?page=activity-list`, 1500);
            } else {
                feedbackDiv.className = 'p-4 mb-4 text-sm text-red-400 bg-red-900 rounded-lg';
                feedbackDiv.textContent = `Error: ${data.message || 'An unknown error occurred.'}`;
            }
        })
        .catch(err => {
            feedbackDiv.classList.remove('hidden');
            feedbackDiv.className = 'p-4 mb-4 text-sm text-red-400 bg-red-900 rounded-lg';
            feedbackDiv.textContent = `An unexpected error occurred: ${err.message || 'Check console for details.'}`;
            console.error(err);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Update Activity';
        });
    });

    document.getElementById('existing-photos').addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-photo-btn');
        if (!deleteBtn) return;

        const photoId = deleteBtn.dataset.photoId;
        if (!confirm('Are you sure you want to delete this photo?')) return;

        fetch('index.php?action=delete_activity_photo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: photoId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`photo-${photoId}`).remove();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch(err => alert('An error occurred.'));
    });
});
</script> 