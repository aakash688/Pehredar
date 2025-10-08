<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// UI/hr_settings_view.php
// Form to manage HR related settings like salary multipliers for overtime.
global $page_data;
$settings = $page_data['hr_settings'] ?? null;
?>
<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6 flex items-center"><i class="fas fa-user-cog mr-3"></i>HR Settings</h1>

    <div id="form-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>

    <form id="hr-settings-form" action="index.php?action=save_hr_settings" method="POST">
        <div class="space-y-6">
            <div class="form-group">
                <label for="general_multiplier" class="block text-sm font-medium text-gray-300 mb-2">General Pay Multiplier (Base)</label>
                <input type="number" step="0.01" id="general_multiplier" name="general_multiplier" value="<?php echo htmlspecialchars($settings['general_multiplier'] ?? '1.00'); ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" readonly>
            </div>

            <div class="form-group">
                <label for="overtime_multiplier" class="block text-sm font-medium text-gray-300 mb-2">Overtime Pay Multiplier</label>
                <input type="number" step="0.1" id="overtime_multiplier" name="overtime_multiplier" value="<?php echo htmlspecialchars($settings['overtime_multiplier'] ?? '1.50'); ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                <p class="text-xs text-gray-400 mt-1">Example: 1.5 means employees earn 150% of their base salary for overtime hours.</p>
            </div>

            <div class="form-group">
                <label for="holiday_multiplier" class="block text-sm font-medium text-gray-300 mb-2">Holiday Pay Multiplier</label>
                <input type="number" step="0.1" id="holiday_multiplier" name="holiday_multiplier" value="<?php echo htmlspecialchars($settings['holiday_multiplier'] ?? '2.00'); ?>" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                <p class="text-xs text-gray-400 mt-1">Example: 2.0 means employees earn double pay on holidays.</p>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" id="submit-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

 