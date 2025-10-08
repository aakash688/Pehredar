<?php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../actions/team_controller.php';

$team_id = $_GET['id'] ?? null;
if (!$team_id) {
    die('Team ID is required.');
}

$db = new Database();
$controller = new TeamController();
$team = $controller->getTeamDetails($team_id);

if (!$team) {
    die('Team not found.');
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white"><?php echo htmlspecialchars($team['team_name']); ?></h1>
            <p class="text-gray-400 mt-1">
                Created on <?php echo date('F j, Y', strtotime($team['created_at'])); ?>
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button id="single-entry-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-lg transform hover:scale-105 transition-transform duration-200">
                <i class="fas fa-plus mr-2"></i> Single Entry
            </button>
            <button id="bulk-assign-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-lg transform hover:scale-105 transition-transform duration-200">
                <i class="fas fa-users mr-2"></i> Bulk Assign
            </button>
        </div>
    </div>

    <!-- Team Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gray-800 p-6 rounded-lg shadow-xl flex items-center hover:shadow-2xl transition-shadow duration-300">
            <i class="fas fa-user-shield text-blue-400 text-4xl"></i>
                <div class="ml-4">
                <h3 class="text-lg font-semibold text-white">Supervisor</h3>
                <p class="text-gray-300"><?php echo htmlspecialchars($team['supervisor_name']); ?></p>
            </div>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg shadow-xl flex items-center hover:shadow-2xl transition-shadow duration-300">
            <i class="fas fa-users text-green-400 text-4xl"></i>
                <div class="ml-4">
                <h3 class="text-lg font-semibold text-white">Team Size</h3>
                <p class="text-gray-300"><?php echo count($team['members']); ?> Members</p>
            </div>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg shadow-xl hover:shadow-2xl transition-shadow duration-300">
             <h3 class="text-lg font-semibold text-white mb-2">Description</h3>
             <p class="text-gray-400 text-sm">
                <?php echo !empty($team['description']) ? htmlspecialchars($team['description']) : 'No description provided.'; ?>
            </p>
        </div>
    </div>

    <!-- Current Roster -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-8">
        <h3 class="text-xl font-semibold text-white mb-6">Current Roster</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="py-3.5 px-4 text-left text-sm font-semibold text-gray-300">Guard</th>
                        <th class="py-3.5 px-4 text-left text-sm font-semibold text-gray-300">Client</th>
                        <th class="py-3.5 px-4 text-left text-sm font-semibold text-gray-300">Shift</th>
                        <th class="py-3.5 px-4 text-right text-sm font-semibold text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody id="roster-table-body" class="divide-y divide-gray-700 bg-gray-800">
                    <!-- Roster data will be populated by JavaScript -->
                    <tr><td colspan="4" class="text-center text-gray-400 py-4">Loading roster...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Team Members List -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <h3 class="text-xl font-semibold text-white mb-6">Team Members</h3>
        <div class="overflow-x-auto">
            <ul class="divide-y divide-gray-700">
                <?php if (empty($team['members'])): ?>
                    <li class="py-4 text-center text-gray-400">This team has no members yet.</li>
                <?php else: ?>
                    <?php foreach ($team['members'] as $member): ?>
                        <li class="py-3 flex justify-between items-center">
                            <span class="text-white"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></span>
                            <a href="index.php?page=view-employee&id=<?php echo $member['id']; ?>" class="text-blue-400 hover:text-blue-300">View Profile</a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Single Entry Modal -->
<div id="single-entry-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
      <form id="single-entry-form" class="space-y-6 p-6">
        <div class="flex justify-between items-center border-b border-gray-700 pb-4">
          <h3 class="text-lg leading-6 font-semibold text-white flex items-center">
            <i class="fas fa-plus-circle mr-3 text-blue-500"></i>
            Assign Roster - Single Entry
          </h3>
          <button type="button" class="modal-close text-gray-400 hover:text-white focus:outline-none" aria-label="Close">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label for="single-guard-select" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-user-shield mr-2 text-blue-400"></i>
              Guard
            </label>
            <select id="single-guard-select" name="guard_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
              <option value="">Select a guard</option>
              <?php foreach ($team['members'] as $member): ?>
                  <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label for="single-society-select" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-building mr-2 text-blue-400"></i>
              Client
            </label>
            <select id="single-society-select" name="society_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
          </div>
          
          <div>
            <label for="single-shift-select" class="block text-sm font-medium text-gray-300 mb-2">
              <i class="fas fa-clock mr-2 text-blue-400"></i>
              Shift
            </label>
            <select id="single-shift-select" name="shift_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="single-start-date" class="block text-sm font-medium text-gray-300 mb-2">
                <i class="fas fa-calendar-day mr-2 text-blue-400"></i>
                Start Date
              </label>
              <input type="date" id="single-start-date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300" />
            </div>
            <div>
              <label for="single-end-date" class="block text-sm font-medium text-gray-300 mb-2">
                <i class="fas fa-calendar-check mr-2 text-blue-400"></i>
                End Date
              </label>
              <input type="date" id="single-end-date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300" />
            </div>
          </div>
        </div>
        
        <div class="bg-gray-700 -mx-6 -mb-6 px-6 py-4 flex justify-end space-x-3 rounded-b-lg">
          <button type="button" class="modal-close bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300">
            Cancel
          </button>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300 flex items-center">
            <i class="fas fa-save mr-2"></i> Assign
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Assign Modal -->
<div id="bulk-assign-modal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full">
            <form id="bulk-assign-form" class="space-y-6 p-6">
                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <h3 class="text-lg leading-6 font-semibold text-white flex items-center">
                        <i class="fas fa-users mr-3 text-green-500"></i>
                        Bulk Assign Roster
                    </h3>
                    <button type="button" class="modal-close text-gray-400 hover:text-white focus:outline-none" aria-label="Close">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="bulk-society-select" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-building mr-2 text-blue-400"></i>
                            Client
                        </label>
                        <select id="bulk-society-select" name="society_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
                    </div>
                    <div>
                        <label for="bulk-shift-select" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-clock mr-2 text-blue-400"></i>
                            Shift
                        </label>
                        <select id="bulk-shift-select" name="shift_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="bulk-start-date" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-calendar-day mr-2 text-blue-400"></i>
                            Start Date
                        </label>
                        <input type="date" id="bulk-start-date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300" />
                    </div>
                    <div>
                        <label for="bulk-end-date" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-calendar-check mr-2 text-blue-400"></i>
                            End Date
                        </label>
                        <input type="date" id="bulk-end-date" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300" />
                    </div>
                </div>

                <div id="bulk-guards-list" class="space-y-3 max-h-60 overflow-y-auto mb-4 pr-2">
                    <!-- Guards will be populated by JS -->
                </div>

                <div class="bg-gray-700 -mx-6 -mb-6 px-6 py-4 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" class="modal-close bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300 flex items-center">
                        <i class="fas fa-users-cog mr-2"></i> Assign Selected Guards
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Roster Modal -->
<div id="edit-roster-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="edit-roster-form" class="space-y-6 p-6">
                <input type="hidden" id="edit-roster-id" name="roster_id">
                
                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <h3 class="text-lg leading-6 font-semibold text-white flex items-center">
                        <i class="fas fa-edit mr-3 text-blue-500"></i>
                        Edit Roster Assignment
                    </h3>
                    <button type="button" class="modal-close text-gray-400 hover:text-white focus:outline-none" aria-label="Close">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="edit-guard-select" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-user-shield mr-2 text-blue-400"></i>
                            Guard
                        </label>
                        <select id="edit-guard-select" name="guard_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                            <?php foreach ($team['members'] as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['surname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="edit-society-select" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-building mr-2 text-blue-400"></i>
                            Client
                        </label>
                        <select id="edit-society-select" name="society_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
                    </div>
                    
                    <div>
                        <label for="edit-shift-select" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-clock mr-2 text-blue-400"></i>
                            Shift
                        </label>
                        <select id="edit-shift-select" name="shift_id" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300"></select>
                    </div>
                </div>
                
                <div class="bg-gray-700 -mx-6 -mb-6 px-6 py-4 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" class="modal-close bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300 flex items-center">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const teamId = <?php echo json_encode($team_id); ?>;
    const supervisorId = <?php echo json_encode($team['supervisor_id']); ?>;
    const teamMembers = <?php echo json_encode($team['members']); ?>;

    const singleEntryModal = document.getElementById('single-entry-modal');
    const bulkAssignModal = document.getElementById('bulk-assign-modal');
    const editRosterModal = document.getElementById('edit-roster-modal');

    const singleSocietySelect = document.getElementById('single-society-select');
    const singleShiftSelect = document.getElementById('single-shift-select');
    const bulkSocietySelect = document.getElementById('bulk-society-select');
    const bulkShiftSelect = document.getElementById('bulk-shift-select');
    const editSocietySelect = document.getElementById('edit-society-select');
    const editShiftSelect = document.getElementById('edit-shift-select');

    function fetchAndPopulateDropdowns() {
        // Fetch and populate societies
        if (supervisorId) {
            fetch(`actions/society_controller.php?action=get_societies_by_supervisor&supervisor_id=${supervisorId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.societies) {
                        populateSelect([singleSocietySelect, bulkSocietySelect, editSocietySelect], data.societies, 'id', 'society_name', 'Select Client');
                    }
                });
        }
        
        // Fetch and populate shifts
        fetch(`actions/shift_controller.php?action=get_shifts&is_active=1`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const shiftOptions = data.data.map(s => ({ id: s.id, name: `${s.shift_name} (${s.start_time} - ${s.end_time})` }));
                    populateSelect([singleShiftSelect, bulkShiftSelect, editShiftSelect], shiftOptions, 'id', 'name', 'Select Shift');
                }
            });
    }

    function populateSelect(selects, options, valueKey, textKey, defaultOptionText) {
        selects.forEach(select => {
            select.innerHTML = `<option value="">${defaultOptionText}</option>`;
            options.forEach(option => {
                select.innerHTML += `<option value="${option[valueKey]}">${option[textKey]}</option>`;
            });
        });
    }
    
    function fetchAndDisplayRoster() {
        const rosterTableBody = document.getElementById('roster-table-body');
        rosterTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-gray-400 py-4">Loading roster...</td></tr>';

        fetch(`actions/roster_controller.php?action=get_rosters&team_id=${teamId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && (data.rosters || data.roster)) {
                    renderRosterTable(data.rosters || data.roster);
                } else {
                    rosterTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-gray-400 py-4">Failed to load roster: ${data.message}</td></tr>`;
                }
            })
            .catch(err => {
                rosterTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-red-400 py-4">An error occurred.</td></tr>`;
            });
    }
    
    function renderRosterTable(rosterData) {
        const rosterTableBody = document.getElementById('roster-table-body');
        if (rosterData.length === 0) {
            rosterTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-gray-400 py-4">No roster assignments for this team yet.</td></tr>';
            return;
        }

        rosterTableBody.innerHTML = rosterData.map(item => `
            <tr class="hover:bg-gray-700 transition-colors duration-200">
                <td class="px-4 py-3 text-sm text-white">${item.guard_name}</td>
                <td class="px-4 py-3 text-sm text-gray-300">${item.society_name}</td>
                <td class="px-4 py-3 text-sm text-gray-300">${item.shift_name} (${item.start_time} - ${item.end_time})</td>
                <td class="px-4 py-3 text-right text-sm space-x-4">
                    <button class="text-blue-400 hover:text-blue-300" onclick='openEditModal(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                    <button class="text-red-500 hover:text-red-400" onclick="deleteRoster(${item.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    }

    // Modal Handling
    document.getElementById('single-entry-btn').addEventListener('click', () => singleEntryModal.classList.remove('hidden'));
    document.getElementById('bulk-assign-btn').addEventListener('click', () => {
        populateBulkGuardsList();
        bulkAssignModal.classList.remove('hidden');
    });
    document.querySelectorAll('.modal-close').forEach(el => {
        el.addEventListener('click', () => {
            singleEntryModal.classList.add('hidden');
            bulkAssignModal.classList.add('hidden');
            editRosterModal.classList.add('hidden');
        });
    });

    function populateBulkGuardsList() {
        const bulkGuardsList = document.getElementById('bulk-guards-list');
        bulkGuardsList.innerHTML = teamMembers.map(member => `
            <div class="flex items-center justify-between bg-gray-700 p-3 rounded-lg hover:bg-gray-600 transition-colors duration-300">
                <div class="flex items-center">
                    <input type="checkbox" id="guard-${member.id}" name="guards[]" value="${member.id}" 
                           class="form-checkbox h-5 w-5 text-blue-600 bg-gray-900 border-gray-600 rounded focus:ring-blue-500 mr-3">
                    <label for="guard-${member.id}" class="text-white flex items-center">
                        <img src="path/to/default/avatar.png" alt="${member.first_name} ${member.surname}" 
                             class="w-10 h-10 rounded-full mr-3 object-cover">
                        <div>
                            <div class="font-semibold">${member.first_name} ${member.surname}</div>
                            <div class="text-xs text-gray-400">${member.user_type || 'Guard'}</div>
                        </div>
                    </label>
                </div>
            </div>
        `).join('');
    }

    // Form Submissions
    document.getElementById('single-entry-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.team_id = teamId;
        data.assignment_start_date = (document.getElementById('single-start-date').value || '').trim();
        data.assignment_end_date = (document.getElementById('single-end-date').value || '').trim();

        if (!data.guard_id || !data.society_id || !data.shift_id || !data.assignment_start_date || !data.assignment_end_date) {
            alert('Please select guard, client, shift, and both start and end dates.');
            return;
        }

        fetch('actions/roster_controller.php?action=assign_roster', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                singleEntryModal.classList.add('hidden');
                fetchAndDisplayRoster();
                this.reset();
            } else {
                alert('Failed: ' + result.message);
            }
        });
    });

    document.getElementById('bulk-assign-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const society_id = formData.get('society_id');
        const shift_id = formData.get('shift_id');
        const selectedGuards = formData.getAll('guards[]');
        const startDate = (document.getElementById('bulk-start-date').value || '').trim();
        const endDate = (document.getElementById('bulk-end-date').value || '').trim();

        if (!society_id || !shift_id || selectedGuards.length === 0 || !startDate || !endDate) {
            alert('Please select a client, a shift, at least one guard, and both start and end dates.');
            return;
        }

        const payload = {
            team_id: teamId,
            society_id: society_id,
            shift_id: shift_id,
            guard_ids: selectedGuards,
            assignment_start_date: startDate,
            assignment_end_date: endDate
        };

        fetch('actions/roster_controller.php?action=bulk_assign_roster', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                bulkAssignModal.classList.add('hidden');
                fetchAndDisplayRoster();
            } else {
                alert('Bulk assignment failed: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error during bulk assignment:', error);
            alert('An error occurred during bulk assignment.');
        });
    });

    window.openEditModal = function(roster) {
        document.getElementById('edit-roster-id').value = roster.id;
        document.getElementById('edit-guard-select').value = roster.guard_id;
        document.getElementById('edit-society-select').value = roster.society_id;
        document.getElementById('edit-shift-select').value = roster.shift_id;
        editRosterModal.classList.remove('hidden');
    }

    document.getElementById('edit-roster-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('actions/roster_controller.php?action=update_roster', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                editRosterModal.classList.add('hidden');
                fetchAndDisplayRoster();
            } else {
                alert('Update failed: ' + result.message);
            }
        });
    });

    window.deleteRoster = function(rosterId) {
        if (!confirm('Are you sure you want to delete this roster entry?')) return;
        
        fetch(`actions/roster_controller.php?action=delete_roster&id=${rosterId}`, { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    fetchAndDisplayRoster();
                } else {
                    alert('Failed to delete: ' + data.message);
                }
            });
    }

    // Initial Load
    fetchAndPopulateDropdowns();
    fetchAndDisplayRoster();
});
</script> 