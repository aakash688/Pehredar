<?php
require_once __DIR__ . '/../../../helpers/database.php';
$db = new Database();
$rows = $db->query("SELECT id, name, is_percentage, value, affects_net, scope, is_active, active_from_month, created_at, updated_at FROM statutory_deductions ORDER BY id ASC")->fetchAll();
?>
<div class="bg-gray-800 rounded-lg shadow-lg p-6">
	<div class="flex items-center justify-between mb-6">
		<h1 class="text-2xl font-bold text-white">Statutory Deductions</h1>
		<div class="space-x-2">
			<button id="btn-add" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded text-white"><i class="fas fa-plus mr-2"></i>Add</button>
			<a href="index.php?page=salary-calculation" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white">Go to Salary Calculation</a>
		</div>
	</div>
	<p class="text-gray-300 mb-4 text-sm">Configure statutory items applied during salary calculation. Employer-scoped items (e.g., PF Employer) are displayed on slips but do not reduce net salary.</p>
	<div class="overflow-x-auto">
		<table class="min-w-full bg-gray-900 rounded" id="stat-table">
			<thead class="bg-gray-700">
				<tr>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Name</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Type</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Value</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Affects Net</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Scope</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Active From</th>
					<th class="px-4 py-2 text-left text-gray-200 text-xs uppercase">Status</th>
					<th class="px-4 py-2 text-right text-gray-200 text-xs uppercase">Actions</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-800" id="tbody">
				<?php foreach ($rows as $r): ?>
				<tr class="hover:bg-gray-800" data-id="<?php echo $r['id']; ?>">
					<td class="px-4 py-2 text-white"><?php echo htmlspecialchars($r['name']); ?></td>
					<td class="px-4 py-2 text-gray-300"><?php echo $r['is_percentage'] ? 'Percentage' : 'Fixed'; ?></td>
					<td class="px-4 py-2 text-gray-300"><?php echo $r['is_percentage'] ? number_format($r['value'],2) . '%':'₹' . number_format($r['value'],2); ?></td>
					<td class="px-4 py-2 text-gray-300"><?php echo $r['affects_net'] ? 'Yes':'No'; ?></td>
					<td class="px-4 py-2 text-gray-300"><?php echo ucfirst($r['scope']); ?></td>
					<td class="px-4 py-2 text-gray-300"><?php echo htmlspecialchars($r['active_from_month']); ?></td>
					<td class="px-4 py-2"><span class="px-2 py-1 text-xs rounded <?php echo $r['is_active']? 'bg-green-900 text-green-300':'bg-gray-700 text-gray-300'; ?>"><?php echo $r['is_active']?'Active':'Inactive'; ?></span></td>
					<td class="px-4 py-2 text-right space-x-2">
						<button class="btn-edit px-2 py-1 text-blue-400 hover:text-blue-300"><i class="fas fa-edit"></i></button>
						<button class="btn-toggle px-2 py-1 text-yellow-400 hover:text-yellow-300" title="Toggle Active"><i class="fas fa-power-off"></i></button>
						<button class="btn-delete px-2 py-1 text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
	<div class="flex items-center justify-center min-h-screen p-4">
		<div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
			<div class="flex justify-between items-center mb-4">
				<h2 class="text-xl font-bold text-white" id="modal-title">Add Deduction</h2>
				<button id="modal-close" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
			</div>
			<form id="form">
				<input type="hidden" name="id" id="id">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm text-gray-300 mb-1">Name</label>
						<input class="w-full bg-gray-700 text-white rounded px-3 py-2" name="name" id="name" required>
					</div>
					<div>
						<label class="block text-sm text-gray-300 mb-1">Type</label>
						<select class="w-full bg-gray-700 text-white rounded px-3 py-2" name="is_percentage" id="is_percentage">
							<option value="0">Fixed</option>
							<option value="1">Percentage</option>
						</select>
					</div>
					<div>
						<label class="block text-sm text-gray-300 mb-1">Value</label>
						<input type="number" step="0.01" min="0" class="w-full bg-gray-700 text-white rounded px-3 py-2" name="value" id="value" required>
					</div>
					<div>
						<label class="block text-sm text-gray-300 mb-1">Affects Net</label>
						<select class="w-full bg-gray-700 text-white rounded px-3 py-2" name="affects_net" id="affects_net">
							<option value="1">Yes</option>
							<option value="0">No</option>
						</select>
					</div>
					<div>
						<label class="block text-sm text-gray-300 mb-1">Scope</label>
						<select class="w-full bg-gray-700 text-white rounded px-3 py-2" name="scope" id="scope">
							<option value="employee">Employee</option>
							<option value="employer">Employer</option>
						</select>
					</div>
					<div>
						<label class="block text-sm text-gray-300 mb-1">Active From (YYYY-MM)</label>
						<input class="w-full bg-gray-700 text-white rounded px-3 py-2" name="active_from_month" id="active_from_month" placeholder="2025-07" required>
					</div>
				</div>
				<div class="flex justify-end space-x-3 mt-6">
					<button type="button" id="btn-cancel" class="px-4 py-2 text-gray-400 hover:text-white">Cancel</button>
					<button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded text-white">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function(){
	const modal = document.getElementById('modal');
	const form = document.getElementById('form');
	const title = document.getElementById('modal-title');
	const closeBtn = document.getElementById('modal-close');
	document.getElementById('btn-add').addEventListener('click', () => openModal());
	closeBtn.addEventListener('click', hideModal);
	document.getElementById('btn-cancel').addEventListener('click', hideModal);

	function openModal(data){
		form.reset();
		title.textContent = data ? 'Edit Deduction' : 'Add Deduction';
		if (data){
			for (const k in data){ if (form[k] !== undefined) form[k].value = data[k]; }
		}
		modal.classList.remove('hidden');
	}
	function hideModal(){ modal.classList.add('hidden'); }

	function refreshTable(){
		fetch('actions/statutory_deductions_controller.php?action=list')
			.then(r=>r.json()).then(res=>{
				if(!res.success) return alert(res.message||'Failed');
				const tbody = document.getElementById('tbody');
				tbody.innerHTML = '';
				res.data.forEach(r => {
					const tr = document.createElement('tr');
					tr.className = 'hover:bg-gray-800';
					tr.dataset.id = r.id;
					tr.innerHTML = `
						<td class="px-4 py-2 text-white">${escapeHtml(r.name)}</td>
						<td class="px-4 py-2 text-gray-300">${r.is_percentage ? 'Percentage' : 'Fixed'}</td>
						<td class="px-4 py-2 text-gray-300">${r.is_percentage ? (Number(r.value).toFixed(2)+'%') : ('₹'+Number(r.value).toFixed(2))}</td>
						<td class="px-4 py-2 text-gray-300">${r.affects_net ? 'Yes':'No'}</td>
						<td class="px-4 py-2 text-gray-300">${r.scope.charAt(0).toUpperCase()+r.scope.slice(1)}</td>
						<td class="px-4 py-2 text-gray-300">${r.active_from_month}</td>
						<td class="px-4 py-2"><span class="px-2 py-1 text-xs rounded ${r.is_active? 'bg-green-900 text-green-300':'bg-gray-700 text-gray-300'}">${r.is_active?'Active':'Inactive'}</span></td>
						<td class="px-4 py-2 text-right space-x-2">
							<button class="btn-edit px-2 py-1 text-blue-400 hover:text-blue-300"><i class="fas fa-edit"></i></button>
							<button class="btn-toggle px-2 py-1 text-yellow-400 hover:text-yellow-300" title="Toggle Active"><i class="fas fa-power-off"></i></button>
							<button class="btn-delete px-2 py-1 text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
						</td>`;
					tbody.appendChild(tr);
				});
				wireRowActions();
			});
	}

	function wireRowActions(){
		document.querySelectorAll('#tbody .btn-edit').forEach(btn=>{
			btn.onclick = (e)=>{
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const cells = tr.children;
				openModal({
					id,
					name: cells[0].textContent.trim(),
					is_percentage: cells[1].textContent.trim()==='Percentage' ? 1:0,
					value: (cells[2].textContent.includes('%')? cells[2].textContent.replace('%',''): cells[2].textContent.replace('₹','')).trim(),
					affects_net: cells[3].textContent.trim()==='Yes'?1:0,
					scope: cells[4].textContent.trim().toLowerCase(),
					active_from_month: cells[5].textContent.trim()
				});
			};
		});
		document.querySelectorAll('#tbody .btn-toggle').forEach(btn=>{
			btn.onclick = (e)=>{
				const id = e.target.closest('tr').dataset.id;
				const fd = new FormData(); fd.append('id', id); fd.append('action','toggle');
				fetch('actions/statutory_deductions_controller.php?action=toggle', {method:'POST', body: fd})
					.then(r=>r.json()).then(()=> refreshTable());
			};
		});
		document.querySelectorAll('#tbody .btn-delete').forEach(btn=>{
			btn.onclick = (e)=>{
				const id = e.target.closest('tr').dataset.id;
				if(!confirm('Delete this deduction?')) return;
				const fd = new FormData(); fd.append('id', id); fd.append('hard', 1);
				fetch('actions/statutory_deductions_controller.php?action=delete', {method:'POST', body: fd})
					.then(r=>r.json()).then(()=> refreshTable());
			};
		});
	}

	form.addEventListener('submit', (e)=>{
		e.preventDefault();
		const data = Object.fromEntries(new FormData(form).entries());
		const isEdit = !!data.id;
		fetch('actions/statutory_deductions_controller.php?action='+(isEdit?'update':'create'), {
			method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
		}).then(r=>r.json()).then(res=>{
			if(!res.success) return alert(res.message||'Failed');
			hideModal(); refreshTable();
		});
	});

	function escapeHtml(s){ return s.replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
	wireRowActions();
})();
</script>
<?php require_once 'UI/dashboard_layout_footer.php'; ?> 