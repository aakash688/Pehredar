<?php
// UI/create_activity_view.php
global $societies; // Make sure we can access the global variable
?>
<head>
    <!-- Add Tagify from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />
    <!-- Centralized CSS -->
    <link rel="stylesheet" href="UI/assets/css/main.css">
</head>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6">Create New Activity</h1>
    
    <div id="form-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert">
        <!-- JS will populate this -->
    </div>

    <form id="create-activity-form" action="index.php?action=create_activity" method="POST" enctype="multipart/form-data">
        <div class="space-y-6">
            <div class="form-group">
                <label for="society_id" class="block text-sm font-medium text-gray-300 mb-2">Select Society</label>
                <select id="society_id" name="society_id" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="">-- Select a Society --</option>
                    <?php if (!empty($societies)): ?>
                        <?php foreach ($societies as $society): ?>
                            <option value="<?php echo htmlspecialchars($society['id']); ?>">
                                <?php echo htmlspecialchars($society['society_name']); ?>
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
                <input type="text" id="title" name="title" placeholder="e.g., Community Clean-up Drive" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
            </div>

            <div class="form-group">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Volunteers meet at 8 AM..." class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="date" class="block text-sm font-medium text-gray-300 mb-2">Scheduled Date & Time</label>
                    <input type="datetime-local" id="date" name="date" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div class="form-group">
                    <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Location (Optional)</label>
                    <input type="text" id="location" name="location" placeholder="e.g., Central Park" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="tags" class="block text-sm font-medium text-gray-300 mb-2">Tags (Optional, comma-separated)</label>
                    <input type="text" id="tags" name="tags" placeholder="e.g., environment, volunteering" class="bg-gray-700 text-white w-full p-3 rounded-lg">
                </div>
                <div class="form-group">
                    <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select id="status" name="status" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="attachments" class="block text-sm font-medium text-gray-300 mb-2">Attach Images (Optional)</label>
                <input type="file" id="attachments" name="attachments[]" multiple accept="image/jpeg,image/png" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <div id="image-preview-container" class="image-preview-container"></div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" id="submit-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> Create Activity
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Add Tagify JS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.polyfills.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('create-activity-form');
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
            (data.assignees || []).forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = `${u.name} (${u.user_type})`;
                assigneesSelect.appendChild(opt);
            });
        } catch (e) {
            console.error(e);
        }
    }

    societySelect.addEventListener('change', () => populateAssignees(societySelect.value));

    let stagedFiles = [];

    attachmentsInput.addEventListener('change', () => {
        Array.from(attachmentsInput.files).forEach(file => {
            if (!stagedFiles.some(f => f.name === file.name && f.size === file.size)) {
                stagedFiles.push(file);
            }
        });
        attachmentsInput.value = ''; // Clear the input
        renderPreviews();
    });

    function renderPreviews() {
        previewContainer.innerHTML = '';
        stagedFiles.forEach((file, index) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src);

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

        feedbackDiv.className = 'hidden';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';

        const formData = new FormData(form);
        formData.delete('attachments[]'); // Clear default empty value

        // Ensure multi-select values are included
        const selectedAssignees = Array.from(assigneesSelect.options).filter(o => o.selected).map(o => o.value);
        formData.delete('assignees[]');
        selectedAssignees.forEach(v => formData.append('assignees[]', v));

        stagedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            feedbackDiv.classList.remove('hidden');
            if (data.success) {
                feedbackDiv.className = 'p-4 mb-4 text-sm text-green-400 bg-green-900 rounded-lg';
                feedbackDiv.innerHTML = `<span class="font-medium">Success!</span> ${data.message}. Redirecting...`;
                form.reset();
                stagedFiles = [];
                renderPreviews();
                setTimeout(() => {
                    window.location.href = `index.php?page=activity-list`;
                }, 2000);
            } else {
                feedbackDiv.className = 'p-4 mb-4 text-sm text-red-400 bg-red-900 rounded-lg';
                feedbackDiv.innerHTML = `<span class="font-medium">Error!</span> ${data.message}`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            feedbackDiv.classList.remove('hidden');
            feedbackDiv.className = 'p-4 mb-4 text-sm text-red-400 bg-red-900 rounded-lg';
            feedbackDiv.textContent = error.message || 'An unexpected network error occurred. Please try again.';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> Create Activity';
        });
    });
});
</script> 