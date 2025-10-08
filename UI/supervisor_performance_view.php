<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// Load supervisor data
include_once 'helpers/database.php';
$db = new Database();

// Get supervisor ID from URL if provided
$supervisor_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Get all supervisors for filter dropdown
$supervisors_query = "SELECT id, CONCAT(first_name, ' ', surname) as name, user_type 
                     FROM users 
                     WHERE user_type IN ('Supervisor', 'Site Supervisor')
                     ORDER BY user_type, first_name, surname";
$supervisors = $db->query($supervisors_query)->fetchAll();

// Date range (IST) – default to current month
$istTz = new DateTimeZone('Asia/Kolkata');
$nowIst = new DateTime('now', $istTz);
$defaultFrom = (new DateTime($nowIst->format('Y-m-01'), $istTz))->format('Y-m-d');
$defaultTo = (new DateTime($nowIst->format('Y-m-t'), $istTz))->format('Y-m-d');
$fromDate = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : $defaultFrom;
$toDate = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : $defaultTo;

// Helper: convert server (+02:00) to IST for display
function to_ist_display($dt) {
	try {
		$src = new DateTime($dt, new DateTimeZone('+02:00'));
		$src->setTimezone(new DateTimeZone('Asia/Kolkata'));
		return $src->format('Y-m-d H:i:s');
	} catch (Throwable $t) {
		return $dt;
	}
}

// If a specific supervisor is selected, get their data
$selected_supervisor = null;
if ($supervisor_id) {
    $supervisor_data_query = "SELECT id, CONCAT(first_name, ' ', surname) as name, 
                                  email_id, mobile_number, profile_photo, user_type
                             FROM users 
                             WHERE id = ? AND user_type IN ('Supervisor','Site Supervisor')";
    $selected_supervisor = $db->query($supervisor_data_query, [$supervisor_id])->fetch();
    
    // Get assigned sites
    if ($selected_supervisor) {
        $sites_query = "SELECT s.id, s.society_name as name, s.street_address as location
                       FROM society_onboarding_data s
                       JOIN supervisor_site_assignments ssa ON s.id = ssa.site_id
                       WHERE ssa.supervisor_id = ?";
        $assigned_sites = $db->query($sites_query, [$supervisor_id])->fetchAll();
        $selected_supervisor['sites'] = $assigned_sites;

		// Real site visit metrics from supervisor_site_visits in selected IST date range
		// Per-day visits and minutes
		$perDay = $db->query(
			"SELECT DATE(CONVERT_TZ(checkin_at,'+02:00','+05:30')) AS day,
			        COUNT(*) AS visits,
			        COALESCE(SUM(duration_minutes),0) AS minutes
			   FROM supervisor_site_visits
			  WHERE supervisor_id = ?
			    AND DATE(CONVERT_TZ(checkin_at,'+02:00','+05:30')) BETWEEN ? AND ?
			  GROUP BY day
			  ORDER BY day ASC",
			[$supervisor_id, $fromDate, $toDate]
		)->fetchAll();

		$totalVisits = 0; $totalMinutes = 0;
		foreach ($perDay as $row) { $totalVisits += (int)$row['visits']; $totalMinutes += (int)$row['minutes']; }

		// Per-site aggregation
		$perSite = $db->query(
			"SELECT v.location_id, s.society_name AS site_name,
			        COUNT(*) AS visits_count,
			        COALESCE(SUM(v.duration_minutes),0) AS minutes,
			        MAX(CONVERT_TZ(v.checkin_at,'+02:00','+05:30')) AS last_visit
			   FROM supervisor_site_visits v
			   JOIN society_onboarding_data s ON s.id = v.location_id
			  WHERE v.supervisor_id = ?
			    AND DATE(CONVERT_TZ(v.checkin_at,'+02:00','+05:30')) BETWEEN ? AND ?
			  GROUP BY v.location_id, s.society_name
			  ORDER BY visits_count DESC",
			[$supervisor_id, $fromDate, $toDate]
		)->fetchAll();
		$selected_supervisor['site_visits'] = $perSite;

		// Detailed logs
		// Pagination for detailed logs
		$logs_per_page = 20;
		$logs_current_page = isset($_GET['logs_page']) ? max(1, (int)$_GET['logs_page']) : 1;
		$logs_offset = ($logs_current_page - 1) * $logs_per_page;

		$total_logs = (int)$db->query(
			"SELECT COUNT(*)
			   FROM supervisor_site_visits v
			   JOIN society_onboarding_data s ON s.id = v.location_id
			  WHERE v.supervisor_id = ?
			    AND DATE(CONVERT_TZ(v.checkin_at,'+02:00','+05:30')) BETWEEN ? AND ?",
			[$supervisor_id, $fromDate, $toDate]
		)->fetchColumn();
		$logs_total_pages = max(1, (int)ceil($total_logs / $logs_per_page));
		if ($logs_current_page > $logs_total_pages) { $logs_current_page = $logs_total_pages; $logs_offset = ($logs_current_page - 1) * $logs_per_page; }

		$logs = $db->query(
			"SELECT v.id, v.location_id, s.society_name AS site_name,
			        v.checkin_at, v.checkout_at, v.duration_minutes
		   FROM supervisor_site_visits v
		   JOIN society_onboarding_data s ON s.id = v.location_id
		  WHERE v.supervisor_id = ?
		    AND DATE(CONVERT_TZ(v.checkin_at,'+02:00','+05:30')) BETWEEN ? AND ?
		  ORDER BY v.checkin_at DESC
		  LIMIT $logs_per_page OFFSET $logs_offset",
			[$supervisor_id, $fromDate, $toDate]
		)->fetchAll();
		$selected_supervisor['logs'] = $logs;
		$selected_supervisor['logs_pagination'] = [
			'total' => $total_logs,
			'per_page' => $logs_per_page,
			'current_page' => $logs_current_page,
			'total_pages' => $logs_total_pages,
			'offset' => $logs_offset
		];

		// Attendance proxy: days with at least one visit in range vs total days in range
		$daysInRange = (int) ((new DateTime($toDate, $istTz))->diff(new DateTime($fromDate, $istTz)))->format('%a') + 1;
		$selected_supervisor['attendance'] = [
			'present' => count($perDay),
			'absent' => max(0, $daysInRange - count($perDay)),
			
			'total_days' => $daysInRange,
			'total_visits' => $totalVisits,
			'total_minutes' => $totalMinutes
		];
    }
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-3xl font-bold mb-6 text-white">Supervisor Performance</h1>
    
    <!-- Supervisor Selection -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl text-white mb-4">Select Supervisor</h2>
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-gray-400 mb-2">Supervisor</label>
                <select id="supervisor-select" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2">
                    <option value="">Select a supervisor</option>
                    <?php foreach ($supervisors as $supervisor): ?>
                    <option value="<?= $supervisor['id'] ?>" <?= ($supervisor_id == $supervisor['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($supervisor['name']) ?> (<?= htmlspecialchars($supervisor['user_type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-gray-400 mb-2">From</label>
                <input type="date" id="from-date" value="<?= htmlspecialchars($fromDate) ?>" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-gray-400 mb-2">To</label>
                <input type="date" id="to-date" value="<?= htmlspecialchars($toDate) ?>" class="w-full bg-gray-700 border border-gray-600 text-white rounded p-2">
            </div>
            <div>
                <button id="view-performance" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    View Performance
                </button>
            </div>
        </div>
    </div>
    
    <?php if ($selected_supervisor): ?>
    <!-- Supervisor Overview -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <div class="flex items-center mb-4">
            <div class="h-16 w-16 rounded-full overflow-hidden bg-gray-700 flex-shrink-0">
                <?php if (!empty($selected_supervisor['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($selected_supervisor['profile_photo']) ?>" alt="Profile" class="h-full w-full object-cover">
                <?php else: ?>
                <div class="h-full w-full flex items-center justify-center text-gray-400 text-xl">
                    <i class="fas fa-user"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="ml-4">
                <h2 class="text-xl font-semibold text-white"><?= htmlspecialchars($selected_supervisor['name']) ?></h2>
                <div class="text-gray-400">
                    <span class="mr-4"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($selected_supervisor['email_id']) ?></span>
                    <span><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($selected_supervisor['mobile_number']) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Performance Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <!-- Sites Card -->
            <div class="bg-gray-700 rounded-lg p-4">
                <h3 class="text-gray-300 text-sm uppercase">Assigned Sites</h3>
                <div class="flex items-baseline mt-2">
                    <span class="text-2xl font-bold text-white"><?= count($selected_supervisor['sites']) ?></span>
                </div>
            </div>
            
            <!-- Attendance Card -->
            <div class="bg-gray-700 rounded-lg p-4">
                <h3 class="text-gray-300 text-sm uppercase">Attendance Rate</h3>
                <div class="flex items-baseline mt-2">
                    <?php 
                    $attendance_rate = ($selected_supervisor['attendance']['present'] / $selected_supervisor['attendance']['total_days']) * 100;
                    $attendance_color = $attendance_rate > 90 ? 'text-green-400' : ($attendance_rate > 75 ? 'text-yellow-400' : 'text-red-400');
                    ?>
                    <span class="text-2xl font-bold <?= $attendance_color ?>"><?= number_format($attendance_rate, 1) ?>%</span>
                    <span class="text-sm text-gray-400 ml-2">(<?= $selected_supervisor['attendance']['present'] ?>/<?= $selected_supervisor['attendance']['total_days'] ?> days)</span>
                </div>
            </div>
            
            <!-- Site Visits Card -->
            <div class="bg-gray-700 rounded-lg p-4">
                <h3 class="text-gray-300 text-sm uppercase">Average Site Visits</h3>
                <div class="flex items-baseline mt-2">
                    <?php 
                    $total_visits = array_sum(array_column($selected_supervisor['site_visits'], 'visits_count'));
                    $avg_visits = count($selected_supervisor['site_visits']) > 0 ? $total_visits / count($selected_supervisor['site_visits']) : 0;
                    ?>
                    <span class="text-2xl font-bold text-white"><?= number_format($avg_visits, 1) ?></span>
                    <span class="text-sm text-gray-400 ml-2">per site</span>
                </div>
            </div>
            
            <!-- Total Time Card -->
            <div class="bg-gray-700 rounded-lg p-4">
                <h3 class="text-gray-300 text-sm uppercase">Total Time at Sites</h3>
                <div class="flex items-baseline mt-2">
                    <?php 
                    $mins = (int)$selected_supervisor['attendance']['total_minutes'];
                    $hrs = intdiv($mins, 60);
                    $rem = $mins % 60;
                    ?>
                    <span class="text-2xl font-bold text-white"><?= sprintf('%02d hrs %02d min', $hrs, $rem) ?></span>
                    <span class="text-sm text-gray-400 ml-2">(<?= (int)$selected_supervisor['attendance']['total_visits'] ?> visits)</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Attendance Chart -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Attendance Overview</h3>
            <div class="h-64">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        
        <!-- Site Visits -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Site Visits</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="px-4 py-2">Site</th>
                            <th class="px-4 py-2">Location</th>
                            <th class="px-4 py-2">Visits</th>
                            <th class="px-4 py-2">Last Visit</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300">
                        <?php foreach ($selected_supervisor['site_visits'] as $visit): ?>
                        <tr class="border-t border-gray-700">
                            <td class="px-4 py-3"><?= htmlspecialchars($visit['site_name']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($selected_supervisor['sites'][array_search($visit['location_id'], array_column($selected_supervisor['sites'], 'id'))]['location'] ?? 'N/A') ?></td>
                            <td class="px-4 py-3"><?= $visit['visits_count'] ?></td>
                            <td class="px-4 py-3"><?= date('d M, Y', strtotime($visit['last_visit'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

		<!-- Detailed Logs (moved to full-width below) -->
	</div>

	<?php 
	  $logsPageData = $selected_supervisor['logs_pagination'] ?? ['current_page'=>1,'total_pages'=>1,'total'=>count($selected_supervisor['logs'] ?? [])];
	  $lp = (int)$logsPageData['current_page'];
	  $ltp = (int)$logsPageData['total_pages'];
	  $ltotal = (int)$logsPageData['total'];
	  $baseParams = [
		'page' => 'supervisor-performance',
		'id' => $supervisor_id,
		'from' => $fromDate,
		'to' => $toDate
	  ];
	  $prevUrl = 'index.php?' . http_build_query($baseParams + ['logs_page' => max(1, $lp - 1)]);
	  $nextUrl = 'index.php?' . http_build_query($baseParams + ['logs_page' => min($ltp, $lp + 1)]);
	?>

	<div class="bg-gray-800 rounded-lg p-6 mt-6">
		<div class="flex items-center justify-between mb-4">
			<h3 class="text-lg font-semibold text-white">Detailed Logs</h3>
			<div class="flex items-center gap-2">
				<a href="<?= $prevUrl ?>" class="px-3 py-1 rounded bg-gray-700 text-gray-200 hover:bg-gray-600 <?php if ($lp <= 1) echo 'opacity-50 pointer-events-none'; ?>">Prev</a>
				<span class="text-gray-400 text-sm">Page <?= $lp ?> of <?= $ltp ?></span>
				<a href="<?= $nextUrl ?>" class="px-3 py-1 rounded bg-gray-700 text-gray-200 hover:bg-gray-600 <?php if ($lp >= $ltp) echo 'opacity-50 pointer-events-none'; ?>">Next</a>
			</div>
		</div>
		<div class="overflow-x-auto">
			<table class="w-full text-left">
				<thead class="text-xs text-gray-400 uppercase">
					<tr>
						<th class="px-4 py-2">Date</th>
						<th class="px-4 py-2">Site</th>
						<th class="px-4 py-2">Check-in</th>
						<th class="px-4 py-2">Check-out</th>
						<th class="px-4 py-2">Duration</th>
					</tr>
				</thead>
				<tbody class="text-gray-300">
					<?php foreach ($selected_supervisor['logs'] as $log): ?>
					<?php 
					  $mins = (int)($log['duration_minutes'] ?? 0);
					  $hrs = intdiv($mins, 60); $rem = $mins % 60;
					?>
					<tr class="border-t border-gray-700">
						<td class="px-4 py-3"><?= htmlspecialchars(date('d M, Y', strtotime($log['checkin_at']))) ?></td>
						<td class="px-4 py-3"><?= htmlspecialchars($log['site_name']) ?></td>
						<td class="px-4 py-3"><?= htmlspecialchars(to_ist_display($log['checkin_at'])) ?></td>
						<td class="px-4 py-3"><?= htmlspecialchars(!empty($log['checkout_at']) ? to_ist_display($log['checkout_at']) : '-') ?></td>
						<td class="px-4 py-3"><?= sprintf('%02d:%02d', $hrs, $rem) ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="flex items-center justify-between mt-4">
			<div class="text-gray-400 text-sm">Showing <?= count($selected_supervisor['logs']) ?> of <?= $ltotal ?> records</div>
			<div class="flex items-center gap-2">
				<a href="<?= $prevUrl ?>" class="px-3 py-1 rounded bg-gray-700 text-gray-200 hover:bg-gray-600 <?php if ($lp <= 1) echo 'opacity-50 pointer-events-none'; ?>">Prev</a>
				<span class="text-gray-400 text-sm">Page <?= $lp ?> of <?= $ltp ?></span>
				<a href="<?= $nextUrl ?>" class="px-3 py-1 rounded bg-gray-700 text-gray-200 hover:bg-gray-600 <?php if ($lp >= $ltp) echo 'opacity-50 pointer-events-none'; ?>">Next</a>
			</div>
		</div>
	</div>
    <?php else: ?>
    <div class="bg-gray-800 rounded-lg p-8 text-center">
        <i class="fas fa-chart-line text-gray-600 text-5xl mb-4"></i>
        <h2 class="text-xl text-gray-400 mb-2">Select a supervisor to view performance data</h2>
        <p class="text-gray-500">Performance metrics will appear here</p>
    </div>
    <?php endif; ?>
</div>

<?php if ($selected_supervisor): ?>
<!-- Attendance Overview Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function() {
    const canvas = document.getElementById('attendanceChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const attendance = <?php echo json_encode([
        'present' => (int)($selected_supervisor['attendance']['present'] ?? 0),
        'absent' => (int)($selected_supervisor['attendance']['absent'] ?? 0),
        'total_days' => (int)($selected_supervisor['attendance']['total_days'] ?? 0)
    ]); ?>;

    const dataValues = [attendance.present, attendance.absent];
    const hasData = dataValues.some(v => v > 0);

    if (!hasData) {
      // Graceful fallback text if there's truly no data for the range
      const container = canvas.parentElement;
      if (container) {
        container.innerHTML = '<div class="h-full flex items-center justify-center text-gray-400">No attendance data for the selected range</div>';
      }
      return;
    }

    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Present', 'Absent'],
        datasets: [{
          data: dataValues,
          backgroundColor: ['#16a34a', '#dc2626'],
          borderColor: '#111827',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#d1d5db' } },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.raw || 0;
                const total = attendance.total_days || 0;
                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return `${context.label}: ${value} day(s) (${pct}%)`;
              }
            }
          }
        }
      }
    });
  })();
</script>
<?php endif; ?>

<!-- Simple navigation to apply filters -->
<script>
  document.getElementById('view-performance').addEventListener('click', function() {
    const sup = document.getElementById('supervisor-select').value;
    const from = document.getElementById('from-date').value;
    const to = document.getElementById('to-date').value;
    const params = new URLSearchParams();
    params.set('page', 'supervisor-performance');
    if (sup) params.set('id', sup);
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    window.location = 'index.php?' + params.toString();
  });
</script>


 