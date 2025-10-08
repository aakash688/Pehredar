<?php
/**
 * Advance Deduction Debug View
 * UI to verify and debug advance deduction processing
 */

require_once __DIR__ . '/../helpers/database.php';

$db = new Database();

// Get recent salary records with advance deductions
$recentRecords = $db->query("
    SELECT sr.id, sr.user_id, sr.month, sr.year, sr.advance_salary_deducted,
           u.first_name, u.surname,
           COALESCE(SUM(apt.amount), 0) as total_transactions
    FROM salary_records sr
    JOIN users u ON sr.user_id = u.id
    LEFT JOIN advance_payment_transactions apt ON sr.id = apt.salary_record_id AND apt.transaction_type = 'deduction'
    WHERE sr.advance_salary_deducted > 0
    GROUP BY sr.id, sr.user_id, sr.month, sr.year, sr.advance_salary_deducted, u.first_name, u.surname
    ORDER BY sr.created_at DESC
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Deduction Debug</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Advance Deduction Debug Tool</h1>
                <div class="flex space-x-4">
                    <button onclick="checkCurrentMonth()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Check Current Month
                    </button>
                    <button onclick="refreshData()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Refresh Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="text-sm text-gray-500">Total Records</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo count($recentRecords); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="text-sm text-gray-500">Total Advance Deducted</div>
                <div class="text-2xl font-bold text-green-600">₹<?php echo number_format(array_sum(array_column($recentRecords, 'advance_salary_deducted')), 2); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="text-sm text-gray-500">Total Transactions</div>
                <div class="text-2xl font-bold text-blue-600">₹<?php echo number_format(array_sum(array_column($recentRecords, 'total_transactions')), 2); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="text-sm text-gray-500">Issues Found</div>
                <div class="text-2xl font-bold text-red-600" id="issues-count">0</div>
            </div>
        </div>

        <!-- Recent Records Table -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Recent Salary Records with Advance Deductions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advance Deducted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentRecords as $record): ?>
                            <?php 
                            $advanceDeducted = (float)$record['advance_salary_deducted'];
                            $totalTransactions = (float)$record['total_transactions'];
                            $isBalanced = abs($advanceDeducted - $totalTransactions) < 0.01;
                            ?>
                            <tr class="<?php echo $isBalanced ? 'bg-green-50' : 'bg-red-50'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['surname']); ?></div>
                                    <div class="text-sm text-gray-500">ID: <?php echo $record['user_id']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('F Y', mktime(0, 0, 0, $record['month'], 1, $record['year'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ₹<?php echo number_format($advanceDeducted, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    ₹<?php echo number_format($totalTransactions, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($isBalanced): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            ✅ Balanced
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ❌ Issue
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="verifyRecord(<?php echo $record['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        Verify
                                    </button>
                                    <?php if (!$isBalanced): ?>
                                        <button onclick="fixRecord(<?php echo $record['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            Fix
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Debug Results -->
        <div id="debug-results" class="mt-6 hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Debug Results</h3>
                <div id="debug-content"></div>
            </div>
        </div>
    </div>

    <script>
        function verifyRecord(salaryRecordId) {
            showLoading();
            
            fetch(`actions/advance_deduction_verifier.php?action=verify_salary_record&salary_record_id=${salaryRecordId}`)
                .then(response => response.json())
                .then(result => {
                    showDebugResults('Verify Record Results', result);
                })
                .catch(error => {
                    showDebugResults('Error', { error: error.message });
                });
        }

        function fixRecord(salaryRecordId) {
            if (!confirm('Are you sure you want to fix missing transactions for this record?')) {
                return;
            }
            
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'fix_missing_transactions');
            formData.append('salary_record_id', salaryRecordId);
            
            fetch('actions/advance_deduction_verifier.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                showDebugResults('Fix Record Results', result);
                if (result.success) {
                    refreshData();
                }
            })
            .catch(error => {
                showDebugResults('Error', { error: error.message });
            });
        }

        function checkCurrentMonth() {
            const now = new Date();
            const month = now.getMonth() + 1;
            const year = now.getFullYear();
            
            showLoading();
            
            fetch(`actions/advance_deduction_verifier.php?action=check_month&month=${month}&year=${year}`)
                .then(response => response.json())
                .then(result => {
                    showDebugResults('Current Month Check', result);
                    updateIssuesCount(result.issues_found || 0);
                })
                .catch(error => {
                    showDebugResults('Error', { error: error.message });
                });
        }

        function refreshData() {
            location.reload();
        }

        function showLoading() {
            document.getElementById('debug-results').classList.remove('hidden');
            document.getElementById('debug-content').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500"></i> Loading...</div>';
        }

        function showDebugResults(title, data) {
            document.getElementById('debug-results').classList.remove('hidden');
            document.getElementById('debug-content').innerHTML = `
                <h4 class="font-semibold text-gray-800 mb-2">${title}</h4>
                <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto">${JSON.stringify(data, null, 2)}</pre>
            `;
        }

        function updateIssuesCount(count) {
            document.getElementById('issues-count').textContent = count;
        }

        // Update issues count on page load
        document.addEventListener('DOMContentLoaded', function() {
            let issuesCount = 0;
            <?php foreach ($recentRecords as $record): ?>
                <?php 
                $advanceDeducted = (float)$record['advance_salary_deducted'];
                $totalTransactions = (float)$record['total_transactions'];
                $isBalanced = abs($advanceDeducted - $totalTransactions) < 0.01;
                if (!$isBalanced) $issuesCount++;
                ?>
            <?php endforeach; ?>
            updateIssuesCount(<?php echo $issuesCount; ?>);
        });
    </script>
</body>
</html>

