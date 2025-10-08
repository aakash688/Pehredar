<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

// Fetch all teams with supervisor and member count
$teams = $db->query("
    SELECT 
        t.id, 
        t.team_name, 
        t.description,
        t.created_at,
        u.id as supervisor_id,
        CONCAT(u.first_name, ' ', u.surname) as supervisor_name,
        (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND role != 'Supervisor') as member_count
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.role = 'Supervisor'
    LEFT JOIN users u ON tm.user_id = u.id
    ORDER BY t.created_at DESC
")->fetchAll();

foreach ($teams as &$team) {
    $members = $db->query("
        SELECT CONCAT(u.first_name, ' ', u.surname) as member_name
        FROM users u
        JOIN team_members tm ON u.id = tm.user_id
        WHERE tm.team_id = ? AND tm.role != 'Supervisor'
    ", [$team['id']])->fetchAll();
    $team['member_names'] = implode(', ', array_column($members, 'member_name'));
}
unset($team);

// Get supervisors who are not yet leading a team
$availableSupervisors = $db->query("
    SELECT id, first_name, surname 
    FROM users 
    WHERE user_type = 'Site Supervisor' AND id NOT IN (
        SELECT COALESCE(user_id, 0) FROM team_members WHERE role = 'Supervisor'
    )
    ORDER BY first_name, surname
")->fetchAll();

// Get all employees who can be team members and are not yet assigned
$availableEmployees = $db->query("
    SELECT u.id, u.first_name, u.surname, u.user_type
    FROM users u
    LEFT JOIN team_members tm ON u.id = tm.user_id
    WHERE tm.id IS NULL AND u.user_type IN ('Guard', 'Armed Guard', 'Bouncer', 'Housekeeping')
    ORDER BY u.first_name, u.surname
")->fetchAll();

?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center mb-6">
        <div class="sm:flex-auto">
            <h1 class="text-3xl font-bold text-white">Team Dashboard</h1>
            <p class="mt-2 text-lg text-gray-400">Manage your security teams and view key statistics.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button id="create-team-btn" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                <i class="fas fa-plus mr-2"></i> Create New Team
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-gray-800 p-5 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="bg-blue-500/20 p-3 rounded-full">
                    <i class="fas fa-users text-blue-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-400">Total Teams</p>
                    <p class="text-2xl font-bold text-white"><?php echo count($teams); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800 p-5 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="bg-green-500/20 p-3 rounded-full">
                    <i class="fas fa-user-shield text-green-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-400">Total Supervisors</p>
                    <p class="text-2xl font-bold text-white"><?php echo count($availableSupervisors); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800 p-5 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="bg-yellow-500/20 p-3 rounded-full">
                    <i class="fas fa-user-plus text-yellow-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-400">Unassigned Employees</p>
                    <p class="text-2xl font-bold text-white"><?php echo count($availableEmployees); ?></p>
                </div>
            </div>
        </div>
        <div class="bg-gray-800 p-5 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="bg-red-500/20 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-400">Teams without Supervisors</p>
                    <p class="text-2xl font-bold text-white"><?php echo count(array_filter($teams, fn($t) => !$t['supervisor_name'])); ?></p>
                </div>
            </div>
        </div>
    </div>


    <!-- Teams Table -->
    <div class="bg-gray-800 shadow-xl rounded-lg overflow-hidden">
        <div class="p-4 bg-gray-700 border-b border-gray-600">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="relative flex-grow">
                    <input type="text" id="search-input" placeholder="Search teams by name or supervisor..." class="w-full bg-gray-800 p-3 pl-10 rounded-lg border border-gray-600 focus:border-blue-500 outline-none text-white">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                <div class="flex-shrink-0">
                    <select id="size-filter" class="bg-gray-800 p-3 rounded-lg border border-gray-600 focus:border-blue-500 outline-none text-white">
                        <option value="">All Team Sizes</option>
                        <option value="small">Small (1-3 Members)</option>
                        <option value="medium">Medium (4-10 Members)</option>
                        <option value="large">Large (11+ Members)</option>
                        </select>
                    </div>
            </div>
        </div>
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700">
                <tr>
                    <th scope="col" class="py-3.5 px-4 text-left text-sm font-semibold text-white">Team Name</th>
                    <th scope="col" class="py-3.5 px-4 text-left text-sm font-semibold text-white">Supervisor</th>
                    <th scope="col" class="py-3.5 px-4 text-left text-sm font-semibold text-white">Members</th>
                    <th scope="col" class="py-3.5 px-4 text-left text-sm font-semibold text-white">Created On</th>
                    <th scope="col" class="py-3.5 px-4 text-right text-sm font-semibold text-white">Actions</th>
                </tr>
            </thead>
            <tbody id="teams-table-body" class="divide-y divide-gray-700 bg-gray-800">
                <?php if (empty($teams)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-6 text-gray-400">No teams found. Click "Create New Team" to get started.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teams as $team): ?>
                        <?php 
                            $team_size_class = 'small';
                            if ($team['member_count'] > 10) $team_size_class = 'large';
                            elseif ($team['member_count'] > 3) $team_size_class = 'medium';
                        ?>
                        <tr class="team-row" data-team-name="<?php echo htmlspecialchars($team['team_name']); ?>" data-supervisor-name="<?php echo htmlspecialchars($team['supervisor_name'] ?? ''); ?>" data-team-size="<?php echo $team_size_class; ?>" data-member-names="<?php echo htmlspecialchars($team['member_names']); ?>">
                            <td class="whitespace-nowrap py-4 px-4 text-sm font-medium text-white"><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td class="whitespace-nowrap py-4 px-4 text-sm text-gray-300"><?php echo htmlspecialchars($team['supervisor_name'] ?? 'N/A'); ?></td>
                            <td class="whitespace-nowrap py-4 px-4 text-sm text-gray-300"><?php echo $team['member_count']; ?></td>
                            <td class="whitespace-nowrap py-4 px-4 text-sm text-gray-300"><?php echo date('M d, Y', strtotime($team['created_at'])); ?></td>
                            <td class="whitespace-nowrap py-4 px-4 text-right text-sm font-medium">
                                <button type="button" class="text-blue-400 hover:text-blue-300 mr-3 view-team-btn" data-team-id="<?php echo $team['id']; ?>" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="text-yellow-400 hover:text-yellow-300 mr-3 edit-team-btn" data-team-id="<?php echo $team['id']; ?>" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="text-red-400 hover:text-red-300 delete-team-btn" data-team-id="<?php echo $team['id']; ?>" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->

<!-- Create/Edit Team Modal -->
<div id="team-modal" class="fixed inset-0 z-20 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
                    </div>
        <div class="bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-4xl sm:w-full" style="max-height: 90vh;">
            <form id="team-form">
                <input type="hidden" id="team-id" name="team_id">
                <div class="bg-gray-700 px-6 py-4 border-b border-gray-600">
                    <h3 class="text-xl leading-6 font-medium text-white flex items-center">
                        <i class="fas fa-users mr-3"></i>
                        <span id="modal-title">Create New Team</span>
                    </h3>
                </div>
                <div class="px-6 py-5 bg-gray-800 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label for="team-name" class="block text-sm font-medium text-gray-300 mb-1">Team Name*</label>
                                <input type="text" name="team_name" id="team-name" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label for="supervisor-id" class="block text-sm font-medium text-gray-300 mb-1">Supervisor*</label>
                                <select name="supervisor_id" id="supervisor-id" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                        <div>
                                <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                                <textarea name="description" id="description" rows="4" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Team Members</label>
                            <div class="grid grid-cols-2 gap-4" style="height: 350px;">
                                <!-- Available Employees -->
                                <div class="flex flex-col">
                                    <h4 class="text-xs font-semibold text-gray-400 mb-2">Available Employees</h4>
                                    <div class="relative flex-grow bg-gray-900 p-3 rounded-lg border border-gray-700">
                                        <input type="text" id="available-search" placeholder="Search..." class="w-full bg-gray-800 text-white rounded-md px-3 py-2 mb-3 text-sm">
                                        <ul id="available-employees-list" class="overflow-y-auto" style="height: calc(100% - 50px);">
                                            <!-- JS will populate this -->
                                        </ul>
                                    </div>
                                </div>
                                <!-- Selected Employees -->
                                <div class="flex flex-col">
                                    <h4 class="text-xs font-semibold text-gray-400 mb-2">Selected Members</h4>
                                    <div class="bg-gray-900 p-3 rounded-lg border border-gray-700 flex-grow">
                                        <ul id="selected-employees-list" class="overflow-y-auto h-full">
                                             <!-- JS will populate this -->
                                </ul>
                                    </div>
                                </div>
                            </div>
                             <select name="team_members[]" id="team-members" class="hidden" multiple>
                                <!-- This will be populated by JS for form submission -->
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-700 px-6 py-4 border-t border-gray-600 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i> Save
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-500 sm:mt-0 sm:w-auto sm:text-sm" id="cancel-btn">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Team Modal -->
<div id="view-team-modal" class="fixed inset-0 z-20 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
        </div>
        <div class="bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl sm:w-full">
            <div class="bg-gray-700 px-6 py-4 border-b border-gray-600">
                <h3 class="text-xl leading-6 font-medium text-white flex items-center">
                    <i class="fas fa-eye mr-3"></i>
                    <span id="view-modal-title">Team Details</span>
                </h3>
            </div>
            <div class="px-6 py-5 bg-gray-800" id="view-team-details">
                <!-- Details will be loaded here -->
            </div>
            <div class="bg-gray-700 px-6 py-4 border-t border-gray-600 text-right">
                <button type="button" class="w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-500 sm:w-auto sm:text-sm" id="view-close-btn">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createTeamBtn = document.getElementById('create-team-btn');
    const teamModal = document.getElementById('team-modal');
    const viewTeamModal = document.getElementById('view-team-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const viewCloseBtn = document.getElementById('view-close-btn');
    const teamForm = document.getElementById('team-form');
    
    const availableEmployees = <?php echo json_encode($availableEmployees); ?>;
    const availableSupervisors = <?php echo json_encode($availableSupervisors); ?>;
    let selectedEmployeeIds = new Set();

    function setupMemberSelection(teamMembers = []) {
        const availableList = document.getElementById('available-employees-list');
        const selectedList = document.getElementById('selected-employees-list');
        const hiddenSelect = document.getElementById('team-members');

        availableList.innerHTML = '';
        selectedList.innerHTML = '';
        hiddenSelect.innerHTML = '';
        selectedEmployeeIds.clear();

        if (teamMembers.length > 0) {
            teamMembers.forEach(member => selectedEmployeeIds.add(member.id.toString()));
        }

        const allPossibleMembers = [...availableEmployees, ...teamMembers].filter((v,i,a)=>a.findIndex(t=>(t.id === v.id))===i);

        allPossibleMembers.forEach(emp => {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-sm rounded-md cursor-pointer hover:bg-gray-700 flex justify-between items-center transition-colors';
            li.dataset.id = emp.id;

            const nameSpan = document.createElement('span');
            nameSpan.textContent = `${emp.first_name} ${emp.surname} (${emp.user_type || 'Guard'})`;
            li.appendChild(nameSpan);

            const icon = document.createElement('i');
            if (selectedEmployeeIds.has(emp.id.toString())) {
                icon.className = 'fas fa-minus-circle text-red-400';
                li.appendChild(icon);
                selectedList.appendChild(li);
                const option = new Option(`${emp.first_name} ${emp.surname}`, emp.id, true, true);
                hiddenSelect.appendChild(option);
            } else {
                icon.className = 'fas fa-plus-circle text-green-400';
                li.appendChild(icon);
                availableList.appendChild(li);
            }
        });

        const handleSelection = (e) => {
            if (e.target.tagName !== 'LI' && !e.target.closest('li')) return;
            const li = e.target.closest('li');
            const id = li.dataset.id;
            const icon = li.querySelector('i');
            const nameSpan = li.querySelector('span');
            
            const isSelected = selectedEmployeeIds.has(id);
            if (isSelected) {
                selectedEmployeeIds.delete(id);
                availableList.appendChild(li);
                icon.className = 'fas fa-plus-circle text-green-400';
                const option = hiddenSelect.querySelector(`option[value="${id}"]`);
                if (option) option.remove();
            } else {
                selectedEmployeeIds.add(id);
                selectedList.appendChild(li);
                icon.className = 'fas fa-minus-circle text-red-400';
                const option = new Option(nameSpan.textContent.split(' (')[0], id, true, true);
                hiddenSelect.appendChild(option);
            }
        };
        
        availableList.onclick = handleSelection;
        selectedList.onclick = handleSelection;

        document.getElementById('available-search').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            availableList.querySelectorAll('li').forEach(li => {
                li.style.display = li.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
            });
        });
    }

    function filterTeams() {
        const searchTerm = document.getElementById('search-input').value.toLowerCase();
        const sizeFilter = document.getElementById('size-filter').value;

        document.querySelectorAll('.team-row').forEach(row => {
            const teamName = row.dataset.teamName.toLowerCase();
            const supervisorName = row.dataset.supervisorName.toLowerCase();
            const memberNames = row.dataset.memberNames.toLowerCase();
            const teamSize = row.dataset.teamSize;

            const matchesSearch = teamName.includes(searchTerm) || supervisorName.includes(searchTerm) || memberNames.includes(searchTerm);
            const matchesSize = !sizeFilter || teamSize === sizeFilter;

            row.style.display = (matchesSearch && matchesSize) ? '' : 'none';
        });
    }

    document.getElementById('search-input').addEventListener('input', filterTeams);
    document.getElementById('size-filter').addEventListener('change', filterTeams);

    function handleApiResponse(response) {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }

    function showToast(message, type = 'success') {
        console.log(`Toast: ${type} - ${message}`);
        if (typeof GaurdUI !== 'undefined' && GaurdUI.showToast) {
            GaurdUI.showToast(message, type);
        } else {
            // Fallback notification
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white ${
                type === 'error' ? 'bg-red-600' : type === 'info' ? 'bg-blue-600' : 'bg-green-600'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    }

    // Open "Create Team" modal
    createTeamBtn.addEventListener('click', () => {
        teamForm.reset();
        document.getElementById('team-id').value = '';
        document.getElementById('modal-title').textContent = 'Create New Team';
        
        const supervisorSelect = document.getElementById('supervisor-id');
        supervisorSelect.innerHTML = '<option value="">Select a Supervisor</option>';
        availableSupervisors.forEach(sup => {
            const option = new Option(`${sup.first_name} ${sup.surname}`, sup.id);
            supervisorSelect.add(option);
        });

        setupMemberSelection();
        teamModal.classList.remove('hidden');
    });

    // Close modals
    cancelBtn.addEventListener('click', () => teamModal.classList.add('hidden'));
    viewCloseBtn.addEventListener('click', () => viewTeamModal.classList.add('hidden'));

    // Handle form submission for Create/Edit
    teamForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const teamId = document.getElementById('team-id').value;
        const url = teamId ? `actions/team_actions.php?action=update_team` : `actions/team_actions.php?action=create_team`;
        
        // Build clean data object
        const data = {
            team_name: document.getElementById('team-name').value.trim(),
            supervisor_id: document.getElementById('supervisor-id').value,
            description: document.getElementById('description').value.trim(),
            team_members: []
        };
        
        // Add team_id for updates
        if (teamId) {
            data.team_id = teamId;
        }
        
        // Get selected team members from the hidden select
        const teamMembersSelect = document.getElementById('team-members');
        if (teamMembersSelect) {
            data.team_members = Array.from(teamMembersSelect.selectedOptions).map(option => option.value);
        }
        
        // Remove any unwanted fields that might cause issues
        delete data['team_members[]'];
        
        console.log('Sending team data:', data);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Raw response:', text);
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
                }
            });
        })
        .then(result => {
            console.log('Parsed result:', result);
            
            if (result.success) {
                showToast(`Team ${teamId ? 'updated' : 'created'} successfully!`);
                teamModal.classList.add('hidden');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.error || 'An unknown error occurred.');
            }
        })
        .catch(error => {
            console.error('Team operation error:', error);
            showToast(`Error: ${error.message}`, 'error');
        });
    });

    // Handle View Team
    document.querySelectorAll('.view-team-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const teamId = this.dataset.teamId;
            window.location.href = `index.php?page=view_team_details&id=${teamId}`;
        });
    });

    // Handle Edit Team
    document.querySelectorAll('.edit-team-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const teamId = this.dataset.teamId;
            fetch(`actions/team_actions.php?action=get_team_details&team_id=${teamId}`)
            .then(handleApiResponse)
            .then(result => {
                if (result.success) {
                    const team = result.team;
                    document.getElementById('modal-title').textContent = `Edit Team: ${team.team_name}`;
                    document.getElementById('team-id').value = team.id;
                    document.getElementById('team-name').value = team.team_name;
                    document.getElementById('description').value = team.description;
                    
                    const supervisorSelect = document.getElementById('supervisor-id');
                    const allSupervisors = [...availableSupervisors, {id: team.supervisor_id, first_name: team.supervisor_name.split(' ')[0], surname: team.supervisor_name.split(' ')[1] || ''}];
                    const uniqueSupervisors = allSupervisors.filter((v,i,a)=>a.findIndex(t=>(t.id === v.id))===i);
                    
                    supervisorSelect.innerHTML = '';
                    uniqueSupervisors.forEach(sup => {
                        const option = new Option(`${sup.first_name} ${sup.surname}`, sup.id);
                        if(sup.id == team.supervisor_id) option.selected = true;
                        supervisorSelect.add(option);
                    });

                    setupMemberSelection(team.members);
                    teamModal.classList.remove('hidden');
                } else {
                    throw new Error(result.error || 'Could not fetch team details for editing.');
                }
            })
            .catch(error => showToast(`Error: ${error.message}`, 'error'));
        });
    });

    // Handle Delete Team
    document.querySelectorAll('.delete-team-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const teamId = this.dataset.teamId;
            const teamName = this.closest('tr').querySelector('td:first-child').textContent.trim();
            
            if (confirm(`Are you sure you want to delete the team "${teamName}"? This action cannot be undone.`)) {
                showToast('Deleting team...', 'info');
                
                fetch(`actions/team_actions.php?action=delete_team`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    // Check if response is ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    // Get the response text first to see what we're getting
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        
                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Response text:', text);
                            throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
                        }
                    });
                })
                .then(result => {
                    console.log('Parsed result:', result);
                    
                    if (result.success) {
                        showToast('Team deleted successfully!');
                        
                        // Remove the row from the table
                        const row = this.closest('tr');
                        console.log('Removing row:', row);
                        if (row) {
                            row.style.transition = 'opacity 0.3s ease';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                
                                // Check if table is now empty
                                const tbody = document.getElementById('teams-table-body');
                                if (tbody && tbody.children.length === 0) {
                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-6 text-gray-400">No teams found. Click "Create New Team" to get started.</td></tr>';
                                }
                            }, 300);
                        }
                        
                        // Update the total teams count
                        const totalTeamsElement = document.querySelector('.grid .bg-gray-800 .text-2xl');
                        console.log('Total teams element:', totalTeamsElement);
                        if (totalTeamsElement) {
                            const currentCount = parseInt(totalTeamsElement.textContent);
                            const newCount = Math.max(0, currentCount - 1);
                            console.log('Updating count from', currentCount, 'to', newCount);
                            totalTeamsElement.textContent = newCount;
                        }
                        
                        // Also update the page title or refresh data if needed
                        console.log('Team deletion completed successfully');
                        
                        } else {
                        throw new Error(result.error || 'Failed to delete team.');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    showToast(`Error: ${error.message}`, 'error');
                });
            }
        });
    });
});
</script>