<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// UI/create_ticket_view.php
// The logic to determine if the user is an admin and to fetch societies will be handled in index.php
global $is_admin, $societies;
?>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6">Create New Ticket</h1>
    
    <div id="form-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert">
        <!-- JS will populate this -->
    </div>

    <form id="create-ticket-form" action="index.php?action=create_ticket" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="redirect" value="1">
        <div class="space-y-6">
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label for="society_id" class="block text-sm font-medium text-gray-300 mb-2">Select Society (Admin)</label>
                <select id="society_id" name="society_id" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    <option value="">-- Select a Society --</option>
                    <?php foreach ($societies as $society): ?>
                        <option value="<?php echo htmlspecialchars($society['id']); ?>">
                            <?php echo htmlspecialchars($society['society_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="title" class="block text-sm font-medium text-gray-300 mb-2">Ticket Title</label>
                <input type="text" id="title" name="title" placeholder="e.g., Broken security camera at Gate A" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
            </div>

            <div class="form-group">
                <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Detailed Description</label>
                <textarea id="description" name="description" rows="6" placeholder="Please provide as much detail as possible..." class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div class="form-group">
                    <label for="priority" class="block text-sm font-medium text-gray-300 mb-2">Priority Level</label>
                    <select id="priority" name="priority" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="attachments" class="block text-sm font-medium text-gray-300 mb-2">Attach Images (Optional)</label>
                    <input type="file" id="attachments" name="attachments[]" multiple accept="image/*" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                </div>
            </div>

             <!-- File Preview Area -->
            <div id="file-preview-container" class="mt-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <!-- Previews will be injected here by JS -->
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" id="submit-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Ticket
                </button>
            </div>
        </div>
    </form>
</div>

 