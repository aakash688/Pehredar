<?php
require_once __DIR__ . '/../helpers/database.php';

// Check for session messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$message = $_SESSION['team_message'] ?? null;
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];

// Clear session data after reading
unset($_SESSION['team_message'], $_SESSION['form_errors'], $_SESSION['form_data']);

$db = new Database();

// Determine if we're editing or creating
$editing = isset($_GET['id']) && is_numeric($_GET['id']);
$team = null;
$selectedMembers = [];

if ($editing) {
    $teamId = (int)$_GET['id'];
    $team = $db->query('SELECT * FROM teams WHERE id = ?', [$teamId])->fetch();
    // Get supervisor
    $supervisor = $db->query('SELECT user_id FROM team_members WHERE team_id = ? AND role = "Supervisor"', [$teamId])->fetch();
    $team['supervisor_id'] = $supervisor['user_id'] ?? null;
    // Get members
    $selectedMembers = $db->query('SELECT user_id FROM team_members WHERE team_id = ? AND role != "Supervisor"', [$teamId])->fetchAll();
    $selectedMembers = array_column($selectedMembers, 'user_id');
}

// Get supervisors who are not yet leading a team, or the current one if editing
$availableSupervisors = $db->query('
    SELECT id, first_name, surname 
    FROM users 
    WHERE user_type = "Site Supervisor" AND (id NOT IN (
        SELECT user_id FROM team_members WHERE role = "Supervisor"
    )' . ($editing && $team['supervisor_id'] ? ' OR id = ' . (int)$team['supervisor_id'] : '') . ')
    ORDER BY first_name, surname
')->fetchAll();

// Get all employees who can be team members and are not yet assigned, or are already in this team
$availableEmployees = $db->query('
    SELECT u.id, u.first_name, u.surname, u.user_type
    FROM users u
    LEFT JOIN team_members tm ON u.id = tm.user_id AND (tm.role = "Guard" OR tm.role = "Armed Guard" OR tm.role = "Bouncer" OR tm.role = "Housekeeping")
    WHERE (tm.id IS NULL OR tm.team_id = ?)
      AND u.user_type IN ("Guard", "Armed Guard", "Bouncer", "Housekeeping")
    ORDER BY u.first_name, u.surname
', [$editing ? $team['id'] : 0])->fetchAll();

?>

<!-- Include shared components -->
<script src="UI/assets/js/shared-components.js"></script>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center mb-6">
        <div class="sm:flex-auto">
            <h1 class="text-3xl font-bold text-white"><?php echo $editing ? 'Edit Team' : 'Create New Team'; ?></h1>
            <p class="mt-2 text-lg text-gray-400"><?php echo $editing ? 'Update team information and members.' : 'Create a new security team with supervisor and members.'; ?></p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="index.php?page=assign-team" class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i> Back to Teams
            </a>
        </div>
    </div>

    <!-- Success/Error message container -->
    <div id="message-container" class="mb-6">
        <?php if ($message): ?>
            <div class="hidden" id="session-message" 
                 data-type="<?php echo htmlspecialchars($message['type']); ?>"
                 data-text="<?php echo htmlspecialchars($message['text']); ?>">
            </div>
            <noscript>
                <div class="<?php echo $message['type'] === 'success' ? 'bg-green-500' : 'bg-red-500'; ?> text-white p-4 rounded-lg shadow-md">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            </noscript>
        <?php endif; ?>
    </div>

    <div class="bg-gray-800 rounded-lg shadow-xl">
        <form id="team-form" class="p-8" onsubmit="submitTeamForm(event)" method="POST" action="actions/team_actions.php?action=<?php echo $editing ? 'update_team' : 'create_team'; ?>">
            <noscript>
                <div class="bg-blue-500 text-white p-4 rounded-lg mb-6">
                    JavaScript is disabled. You will be redirected after form submission.
                </div>
            </noscript>
            <?php if ($editing): ?>
                <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['id']); ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div>
                        <label for="team-name" class="block text-sm font-medium text-gray-300 mb-2">Team Name*</label>
                        <input type="text" name="team_name" id="team-name" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required value="<?php echo htmlspecialchars($team['team_name'] ?? ''); ?>" placeholder="Enter team name">
                    </div>
                    <div>
                        <label for="supervisor-id" class="block text-sm font-medium text-gray-300 mb-2">Supervisor*</label>
                        <select name="supervisor_id" id="supervisor-id" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                            <option value="">Select Supervisor</option>
                            <?php foreach ($availableSupervisors as $sup): ?>
                                <option value="<?php echo $sup['id']; ?>" <?php if (($team['supervisor_id'] ?? null) == $sup['id']) echo 'selected'; ?>><?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['surname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea name="description" id="description" rows="6" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-4 py-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Enter team description (optional)"><?php echo htmlspecialchars($team['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-4">Team Members</label>
                        <div class="grid grid-cols-2 gap-6" style="height: 400px;">
                            <!-- Available Employees -->
                            <div class="flex flex-col">
                                <h4 class="text-sm font-semibold text-gray-400 mb-3">Available Employees</h4>
                                <div class="relative flex-grow bg-gray-900 p-3 rounded-lg border border-gray-700">
                                    <input type="text" id="available-search" placeholder="Search employees..." class="w-full bg-gray-800 text-white rounded-md px-3 py-2 mb-3 text-sm">
                                    <ul id="available-employees-list" class="overflow-y-auto h-full space-y-1">
                                        <?php foreach ($availableEmployees as $emp): if (in_array($emp['id'], $selectedMembers)) continue; ?>
                                            <li class="cursor-pointer px-3 py-2 rounded hover:bg-blue-700 text-white text-sm transition-colors" data-id="<?php echo $emp['id']; ?>">
                                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['surname'] . ' (' . $emp['user_type'] . ')'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <!-- Selected Employees -->
                            <div class="flex flex-col">
                                <h4 class="text-sm font-semibold text-gray-400 mb-3">Selected Members</h4>
                                <div class="bg-gray-900 p-3 rounded-lg border border-gray-700 flex-grow">
                                    <ul id="selected-employees-list" class="overflow-y-auto h-full space-y-1">
                                        <?php foreach ($availableEmployees as $emp): if (!in_array($emp['id'], $selectedMembers)) continue; ?>
                                            <li class="cursor-pointer px-3 py-2 rounded hover:bg-red-700 text-white text-sm transition-colors" data-id="<?php echo $emp['id']; ?>">
                                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['surname'] . ' (' . $emp['user_type'] . ')'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <select name="team_members[]" id="team-members" class="hidden" multiple>
                        <?php foreach ($availableEmployees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php if (in_array($emp['id'], $selectedMembers)) echo 'selected'; ?>><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['surname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-4 pt-6 border-t border-gray-700">
                <a href="index.php?page=assign-team" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-white font-semibold transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    <?php echo $editing ? 'Update Team' : 'Create Team'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-4 rounded-lg shadow-lg flex items-center">
            <svg class="animate-spin h-6 w-6 text-blue-600 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-800">Processing...</span>
        </div>
    </div>

<script>
// Display session message if exists
document.addEventListener('DOMContentLoaded', function() {
    const sessionMessage = document.getElementById('session-message');
    if (sessionMessage) {
        const type = sessionMessage.dataset.type;
        const text = sessionMessage.dataset.text;
        GaurdUI.showToast(text, type, type === 'success' ? 4000 : 0);
    }
});

// JS for moving employees between lists and updating the hidden select
const availableList = document.getElementById('available-employees-list');
const selectedList = document.getElementById('selected-employees-list');
const teamMembersSelect = document.getElementById('team-members');

function updateTeamMembersSelect() {
    // Clear
    teamMembersSelect.innerHTML = '';
    selectedList.querySelectorAll('li').forEach(li => {
        const id = li.getAttribute('data-id');
        const option = document.createElement('option');
        option.value = id;
        option.selected = true;
        teamMembersSelect.appendChild(option);
    });
}

availableList.addEventListener('click', function(e) {
    if (e.target.tagName === 'LI') {
        selectedList.appendChild(e.target);
        updateTeamMembersSelect();
    }
});
selectedList.addEventListener('click', function(e) {
    if (e.target.tagName === 'LI') {
        availableList.appendChild(e.target);
        updateTeamMembersSelect();
    }
});
updateTeamMembersSelect();

document.getElementById('available-search').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    availableList.querySelectorAll('li').forEach(li => {
        li.style.display = li.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

// Clear all error states and messages
function clearFormErrors() {
    const errorFields = document.querySelectorAll('.border-red-500');
    errorFields.forEach(field => {
        field.classList.remove('border-red-500');
    });
    
    const errorMessages = document.querySelectorAll('.text-red-500.text-sm');
    errorMessages.forEach(message => {
        message.remove();
    });
}

// Add AJAX form submission
function submitTeamForm(event) {
    event.preventDefault();
    
    // Clear any previous error states
    clearFormErrors();

    // Basic form validation
    const teamName = document.getElementById('team-name').value.trim();
    const supervisorId = document.getElementById('supervisor-id').value;
    let hasErrors = false;
    
    // Validate team name
    if (!teamName) {
        document.getElementById('team-name').classList.add('border-red-500');
        const errorMsg = document.createElement('p');
        errorMsg.className = 'text-red-500 text-sm mt-1';
        errorMsg.textContent = 'Team name is required';
        document.getElementById('team-name').parentNode.appendChild(errorMsg);
        hasErrors = true;
    }
    
    // Validate supervisor
    if (!supervisorId) {
        document.getElementById('supervisor-id').classList.add('border-red-500');
        const errorMsg = document.createElement('p');
        errorMsg.className = 'text-red-500 text-sm mt-1';
        errorMsg.textContent = 'Supervisor is required';
        document.getElementById('supervisor-id').parentNode.appendChild(errorMsg);
        hasErrors = true;
    }
    
    if (hasErrors) {
        GaurdUI.showToast('Please fix the validation errors', 'error', 0);
        return;
    }

    // Show loading overlay
    document.getElementById('loading-overlay').classList.remove('hidden');

    // Prepare form data
    const formData = {
        team_name: teamName,
        supervisor_id: supervisorId,
        description: document.getElementById('description').value,
        team_members: Array.from(document.getElementById('team-members').selectedOptions).map(option => option.value)
    };

    // Add team_id for editing
    const teamIdInput = document.querySelector('input[name="team_id"]');
    if (teamIdInput) {
        formData.team_id = teamIdInput.value;
    }

    // Determine action based on whether we're editing or creating
    const action = teamIdInput ? 'update_team' : 'create_team';

    // Send AJAX request
    fetch('actions/team_actions.php?action=' + action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading overlay
        document.getElementById('loading-overlay').classList.add('hidden');

        if (data.success) {
            // Show success toast
            const message = teamIdInput ? 'Team updated successfully!' : 'Team created successfully!';
            GaurdUI.showToast(message, 'success', 4000);
            
            // Redirect after short delay to allow user to see the success message
            setTimeout(() => {
                window.location.href = 'index.php?page=assign-team';
            }, 1500);
        } else {
            // Show error message
            GaurdUI.showToast(data.error || 'An unknown error occurred', 'error', 0);
            
            // Also highlight any field errors if they exist
            if (data.field_errors) {
                Object.keys(data.field_errors).forEach(field => {
                    const element = document.getElementById(field);
                    if (element) {
                        element.classList.add('border-red-500');
                        // Add error message below the field
                        const errorMsg = document.createElement('p');
                        errorMsg.className = 'text-red-500 text-sm mt-1';
                        errorMsg.textContent = data.field_errors[field];
                        element.parentNode.appendChild(errorMsg);
                    }
                });
            }
        }
    })
    .catch(error => {
        // Hide loading overlay
        document.getElementById('loading-overlay').classList.add('hidden');

        // Show error toast
        GaurdUI.showToast('Network error: ' + error.message, 'error', 0);
    });
}
</script> 