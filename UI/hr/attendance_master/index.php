<?php
// UI/hr/attendance_master/index.php
require_once __DIR__ . '/../../../helpers/database.php';
$db = new Database();
$attendance_types = $db->query("SELECT * FROM attendance_master ORDER BY name")->fetchAll();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-white mb-6">Attendance Master</h1>
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl text-white">Manage Attendance Types</h2>
            <button onclick="openModal('add')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-plus"></i> Add New Type
            </button>
        </div>

        <table class="min-w-full bg-gray-900 rounded-lg">
            <thead>
                <tr>
                    <th class="py-3 px-4 text-left text-gray-300">Code</th>
                    <th class="py-3 px-4 text-left text-gray-300">Name</th>
                    <th class="py-3 px-4 text-left text-gray-300">Multiplier</th>
                    <th class="py-3 px-4 text-left text-gray-300">Status</th>
                    <th class="py-3 px-4 text-right text-gray-300">Actions</th>
                </tr>
            </thead>
            <tbody id="attendance-table-body">
                <?php foreach ($attendance_types as $type) : ?>
                    <tr id="row-<?= $type['id'] ?>" class="border-t border-gray-700">
                        <td class="py-3 px-4 text-white"><?= htmlspecialchars($type['code']) ?></td>
                        <td class="py-3 px-4 text-white"><?= htmlspecialchars($type['name']) ?></td>
                        <td class="py-3 px-4 text-white"><?= htmlspecialchars($type['multiplier']) ?></td>
                        <td class="py-3 px-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $type['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <a href="index.php?page=view-attendance-type&id=<?= $type['id'] ?>" class="text-green-400 hover:text-green-300 mr-3"><i class="fas fa-eye"></i></a>
                            <button onclick='openModal("edit", <?= json_encode($type) ?>)' class="text-blue-400 hover:text-blue-300 mr-3"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteType(<?= $type['id'] ?>)" class="text-red-500 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="attendance-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Add Attendance Type</h3>
                <div id="modal-feedback" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>
                <form id="attendance-form" class="mt-4 space-y-4">
                    <input type="hidden" id="type-id" name="id">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-300">Code</label>
                        <input type="text" id="code" name="code" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                        <input type="text" id="name" name="name" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-300">Description</label>
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <label for="multiplier" class="block text-sm font-medium text-gray-300">Multiplier</label>
                        <input type="number" id="multiplier" name="multiplier" step="0.01" min="0" max="1" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                     <div>
                        <label for="is_active" class="block text-sm font-medium text-gray-300">Status</label>
                        <select id="is_active" name="is_active" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="save-button" onclick="saveType()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">
                    <span id="save-button-text">Save</span>
                    <i id="loader" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                </button>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentMode = 'add';

    function openModal(mode, data = null) {
        currentMode = mode;
        const modal = document.getElementById('attendance-modal');
        const title = document.getElementById('modal-title');
        const form = document.getElementById('attendance-form');
        form.reset();

        if (mode === 'edit' && data) {
            title.textContent = 'Edit Attendance Type';
            document.getElementById('type-id').value = data.id;
            document.getElementById('code').value = data.code;
            document.getElementById('name').value = data.name;
            document.getElementById('description').value = data.description;
            document.getElementById('multiplier').value = data.multiplier;
            document.getElementById('is_active').value = data.is_active;
        } else {
            title.textContent = 'Add Attendance Type';
            document.getElementById('is_active').value = 1;
        }

        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = document.getElementById('attendance-modal');
        modal.classList.add('hidden');
    }

    async function saveType() {
        const saveButton = document.getElementById('save-button');
        const buttonText = document.getElementById('save-button-text');
        const loader = document.getElementById('loader');
        const modalFeedback = document.getElementById('modal-feedback');

        saveButton.disabled = true;
        buttonText.textContent = 'Saving...';
        loader.classList.remove('hidden');
        modalFeedback.classList.add('hidden');

        const id = document.getElementById('type-id').value;
        const code = document.getElementById('code').value;
        const name = document.getElementById('name').value;
        const description = document.getElementById('description').value;
        const multiplier = document.getElementById('multiplier').value;
        const is_active = document.getElementById('is_active').value;

        const url = currentMode === 'add' ? 'actions/attendance_master_controller.php?action=create_attendance_type' : 'actions/attendance_master_controller.php?action=update_attendance_type';

        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, code, name, description, multiplier, is_active })
        });

        const result = await response.json();
        if (result.success) {
            modalFeedback.innerHTML = `<div class="bg-green-500 text-white p-3 rounded-lg">${result.message}</div>`;
            modalFeedback.classList.remove('hidden');
            setTimeout(() => {
                closeModal();
                location.reload();
            }, 1500);
        } else {
            modalFeedback.innerHTML = `<div class="bg-red-500 text-white p-3 rounded-lg">Error: ${result.message}</div>`;
            modalFeedback.classList.remove('hidden');
            saveButton.disabled = false;
            buttonText.textContent = 'Save';
            loader.classList.add('hidden');
        }
    }

    async function deleteType(id) {
        if (!confirm('Are you sure you want to deactivate this attendance type?')) return;

        const response = await fetch('actions/attendance_master_controller.php?action=update_attendance_type', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, is_active: false })
        });

        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    }
</script> 