<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// Placeholder for UI/view_society_view.php

// Include QR code generator
require_once __DIR__ . '/../helpers/qr_code_generator.php';

$society = $page_data['society'] ?? null;

// Find the primary contact from the list of all client users.
$primary_contact = null;
if (!empty($page_data['all_client_users'])) {
    foreach ($page_data['all_client_users'] as $user) {
        if (!empty($user['is_primary'])) {
            $primary_contact = $user;
            break;
        }
    }
}

function display_info($label, $value, $format = null) {
    $formatted_value = htmlspecialchars($value ?? 'N/A');
    if ($value) {
        switch ($format) {
            case 'date': $formatted_value = date('F j, Y', strtotime($value)); break;
            case 'currency': $formatted_value = '₹' . number_format($value, 2); break;
        }
    }
    
    // Special handling for email fields to fix overlap issue
    if (strtolower($label) === 'email' && $value && $value !== 'N/A') {
        echo "<div class='py-3 sm:grid sm:grid-cols-3 sm:gap-4 email-field'>
                <dt class='text-sm font-medium text-gray-400'>{$label}</dt>
                <dd class='mt-1 text-sm text-white sm:mt-0 sm:col-span-2 email-text' style='word-break: break-word;' data-email='{$formatted_value}'>{$formatted_value}</dd>
                <div class='email-icon'>
                    <i class='fas fa-copy text-gray-400 hover:text-white' title='Copy email' onclick='copyToClipboard(\"{$formatted_value}\")'></i>
                </div>
              </div>";
    } else {
        echo "<div class='py-3 sm:grid sm:grid-cols-3 sm:gap-4'><dt class='text-sm font-medium text-gray-400'>{$label}</dt><dd class='mt-1 text-sm text-white sm:mt-0 sm:col-span-2'>{$formatted_value}</dd></div>";
    }
}
?>
<div class="px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="bg-gray-800 rounded-xl shadow-2xl px-8 py-6 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <div>
            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($society['society_name'] ?? 'Client Details') ?></h1>
            <div class="flex items-center space-x-4 mt-2">
            <p class="text-lg text-blue-400">ID: <?= htmlspecialchars($society['id'] ?? 'N/A') ?></p>
                <?php 
                $compliance_status = $society['compliance_status'] ?? 0;
                $compliance_class = $compliance_status == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                $compliance_text = $compliance_status == 1 ? 'Compliant' : 'Non-Compliant';
                ?>
                <span class="px-3 py-1 text-sm font-semibold rounded-full <?= $compliance_class ?>">
                    <i class="fas <?= $compliance_status == 1 ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-1"></i>
                    <?= $compliance_text ?>
                </span>
            </div>
        </div>
        <div class="flex-shrink-0 flex gap-2 mt-4 sm:mt-0">
            <a href="index.php?page=edit-society&id=<?= $society['id'] ?? '' ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm"><i class="fas fa-edit mr-2"></i>Edit Client</a>
            <button class="delete-society-btn bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm" data-id="<?= $society['id'] ?? '' ?>"><i class="fas fa-trash mr-2"></i>Delete Client</button>
            <button id="generate-society-qr-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm"><i class="fas fa-qrcode mr-2"></i>Generate QR</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6">
                <h3 class="text-lg leading-6 font-medium text-white mb-4">Client Details</h3>
                <dl class="divide-y divide-gray-700">
                    <?php display_info('Client Type', $society['client_type_name']); ?>
                    <?php 
                    $compliance_status = $society['compliance_status'] ?? 0;
                    $compliance_text = $compliance_status == 1 ? 'Compliant' : 'Non-Compliant';
                    display_info('Compliance Status', $compliance_text); 
                    ?>
                    <?php 
                    $service_charges_enabled = $society['service_charges_enabled'] ?? 0;
                    if ($service_charges_enabled == 1) {
                        $service_charges_text = 'Yes (' . ($society['service_charges_percentage'] ?? 0) . '%)';
                    } else {
                        $service_charges_text = 'Not Applicable';
                    }
                    display_info('Service Charges', $service_charges_text); 
                    ?>
                    <?php display_info('Street Address', $society['street_address']); ?>
                    <?php display_info('City', $society['city']); ?>
                    <?php display_info('District', $society['district']); ?>
                    <?php display_info('State', $society['state']); ?>
                    <?php display_info('Pin Code', $society['pin_code']); ?>
                    <?php if (!empty($society['gst_number'])): ?>
                    <?php display_info('GST Number', $society['gst_number']); ?>
                    <?php endif; ?>
                    <?php display_info('Onboarding Date', $society['onboarding_date'], 'date'); ?>
                    <?php display_info('Contract Expiry', $society['contract_expiry_date'], 'date'); ?>
                    <?php display_info('QR Code ID', $society['qr_code']); ?>
                </dl>
            </div>
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6">
                <h3 class="text-lg leading-6 font-medium text-white mb-4">Primary Contact</h3>
                <?php if ($primary_contact): 
                    // Debug: Check if primary contact has required data
                    $name = $primary_contact['name'] ?? '';
                    $phone = $primary_contact['phone'] ?? '';
                    $email = $primary_contact['email'] ?? '';
                    
                    // Debug: Log the data being used
                    error_log("QR Code Debug - Name: '$name', Phone: '$phone', Email: '$email'");
                    
                    // Generate QR code
                    $qrCodeUri = generate_vcard_qr_code($name, $phone, $email);
                    
                    // Debug: Log QR code generation result
                    if (empty($qrCodeUri)) {
                        error_log("QR Code generation failed for: Name=$name, Phone=$phone, Email=$email");
                        error_log("QR Code generation failed - Check if GD extension is enabled and QR library is loaded");
                    } else {
                        error_log("QR Code generated successfully - Data URI length: " . strlen($qrCodeUri));
                    }
                ?>
                <div class="flex items-center space-x-4">
                    <div class="flex-grow">
                        <dl>
                            <?php display_info('Name', $primary_contact['name']); ?>
                            <?php display_info('Email', $primary_contact['email']); ?>
                            <?php display_info('Phone', $primary_contact['phone']); ?>
                        </dl>
                    </div>
                    <?php if ($qrCodeUri): ?>
                    <div class="flex-shrink-0">
                        <button id="open-qr-modal" class="text-gray-400 hover:text-white" title="View Contact QR Code">
                            <i class="fas fa-qrcode fa-2x"></i>
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="flex-shrink-0">
                        <div class="text-gray-500 text-sm" title="QR Code generation failed - check contact information">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span class="ml-1">QR Unavailable</span>
                            <br><small class="text-xs text-gray-600">
                                Debug: Name='<?= htmlspecialchars($name) ?>', 
                                Phone='<?= htmlspecialchars($phone) ?>', 
                                Email='<?= htmlspecialchars($email) ?>'
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-400">No primary contact user found for this society.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Middle Column -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6">
                <h3 class="text-lg leading-6 font-medium text-white mb-4">Service Requirements</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Service</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Count</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Client Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php
                            $services = [
                                'Guards' => ['count' => 'guards', 'client_rate' => 'guard_client_rate', 'employee_rate' => 'guard_employee_rate'],
                                'Armed Guards' => ['count' => 'armed_guards', 'client_rate' => 'armed_client_rate', 'employee_rate' => 'armed_guard_employee_rate'],
                                'Supervisors' => ['count' => 'supervisors', 'client_rate' => 'supervisor_client_rate', 'employee_rate' => 'supervisor_employee_rate'],
                                'Site Supervisors' => ['count' => 'site_supervisors', 'client_rate' => 'site_supervisor_client_rate', 'employee_rate' => 'site_supervisor_employee_rate'],
                                'Bouncers' => ['count' => 'bouncers', 'client_rate' => 'bouncer_client_rate', 'employee_rate' => 'bouncer_employee_rate'],
                                'Housekeeping' => ['count' => 'housekeeping', 'client_rate' => 'housekeeping_client_rate', 'employee_rate' => 'housekeeping_employee_rate'],
                                'Dogs' => ['count' => 'dogs', 'client_rate' => 'dog_client_rate', 'employee_rate' => 'dog_employee_rate'],
                            ];

                            foreach ($services as $label => $fields) {
                                echo "<tr>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm font-medium text-white'>$label</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-300'>" . ($society[$fields['count']] ?? 0) . "</td>";
                                echo "<td class='px-4 py-2 whitespace-nowrap text-sm text-gray-300'>₹" . number_format($society[$fields['client_rate']] ?? 0, 2) . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Service Charges Information -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 mt-6">
                <h3 class="text-lg leading-6 font-medium text-white mb-4">Service Charges</h3>
                <div class="space-y-4">
                    <?php 
                    $service_charges_enabled = $society['service_charges_enabled'] ?? 0;
                    if ($service_charges_enabled == 1) {
                        $service_charges_text = 'Yes (' . ($society['service_charges_percentage'] ?? 0) . '%)';
                        $service_charges_class = 'text-green-400';
                        $service_charges_icon = 'fas fa-check-circle';
                    } else {
                        $service_charges_text = 'Not Applicable';
                        $service_charges_class = 'text-gray-400';
                        $service_charges_icon = 'fas fa-times-circle';
                    }
                    ?>
                    <div class="flex items-center space-x-3">
                        <i class="<?= $service_charges_icon ?> <?= $service_charges_class ?> text-lg"></i>
                        <div>
                            <span class="text-sm font-medium text-gray-300">Service Charges Applicable:</span>
                            <span class="ml-2 text-sm <?= $service_charges_class ?> font-semibold"><?= $service_charges_text ?></span>
                        </div>
                    </div>
                    
                    <?php if ($service_charges_enabled == 1): ?>
                    <div class="bg-gray-700/50 rounded-lg p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide">Percentage</span>
                                <p class="text-lg font-semibold text-white"><?= $society['service_charges_percentage'] ?>%</p>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide">Calculation</span>
                                <p class="text-sm text-gray-300">Applied to invoice subtotal</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Map -->
        <div class="lg:col-span-1 bg-gray-800 rounded-xl shadow-2xl p-1" style="min-height: 400px;">
            <div id="map" class="rounded-lg"></div>
        </div>
    </div>

    <!-- Client User Management Section -->
    <div class="bg-gray-800 rounded-xl shadow-2xl p-6 mt-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg leading-6 font-medium text-white">Client Users</h3>
            <button id="add-user-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm">
                <i class="fas fa-plus mr-2"></i>Add New User
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="client-users-table" class="divide-y divide-gray-700">
                    <?php if (!empty($page_data['all_client_users'])): ?>
                        <?php foreach ($page_data['all_client_users'] as $user): ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                                <?= htmlspecialchars($user['name']) ?>
                                <?php if ($user['is_primary']): ?>
                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-200 text-blue-800">Primary</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($user['email']) ?><br><?= htmlspecialchars($user['phone']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button class="user-qr-btn text-purple-400 hover:text-purple-300 mx-2" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['name']) ?>" data-user-email="<?= htmlspecialchars($user['email']) ?>" data-user-phone="<?= htmlspecialchars($user['phone']) ?>" title="QR Code">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <?php if (!$user['is_primary']): ?>
                                    <button class="make-primary-btn text-green-400 hover:text-green-300 mx-2" data-user-id="<?= $user['id'] ?>" title="Make Primary">Make Primary</button>
                                <?php endif; ?>
                                <button class="edit-user-btn text-blue-400 hover:text-blue-300 mx-2" data-user-id="<?= $user['id'] ?>">Edit</button>
                                <button class="reset-password-btn text-yellow-400 hover:text-yellow-300 mx-2" data-user-id="<?= $user['id'] ?>">Reset Password</button>
                                <button class="delete-user-btn text-red-400 hover:text-red-300 mx-2" data-user-id="<?= $user['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-400">No client users found for this society.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qr-code-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full p-4">
        <div class="text-center">
            <h3 class="text-lg leading-6 font-medium text-white mb-4" id="qr-code-title">Scan Contact QR Code</h3>
            <img id="modal-qr-code-img" src="" alt="Contact QR Code" class="mx-auto" style="width: 256px; height: 256px;">
        </div>
        <div class="mt-5 sm:mt-6">
            <button id="close-qr-modal" type="button" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700">
                Close
            </button>
        </div>
    </div>
  </div>
</div>

<!-- Add/Edit User Modal -->
<div id="user-modal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
      <form id="user-form">
        <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Add New User</h3>
          <div id="modal-message-area" class="mt-2"></div>
          <input type="hidden" id="user-id" name="user_id">
          <input type="hidden" name="society_id" value="<?= $society['id'] ?>">
          <div class="mt-4 space-y-4">
            <div><label class="text-gray-400">Full Name*</label><input type="text" id="name" name="name" required class="w-full bg-gray-700 p-2 rounded mt-1"></div>
            <div><label class="text-gray-400">Phone Number*</label><input type="tel" id="phone" name="phone" required class="w-full bg-gray-700 p-2 rounded mt-1"></div>
            <div><label class="text-gray-400">Email Address*</label><input type="email" id="email" name="email" required class="w-full bg-gray-700 p-2 rounded mt-1"></div>
            <div><label class="text-gray-400">Username*</label><input type="text" id="username" name="username" required class="w-full bg-gray-700 p-2 rounded mt-1"></div>
            <div id="password-field-container">
              <label class="text-gray-400">Password*</label>
              <input type="password" id="password" name="password" required class="w-full bg-gray-700 p-2 rounded mt-1">
            </div>
          </div>
        </div>
        <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button type="submit" id="form-submit-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
          <button type="button" id="cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 text-base font-medium text-white hover:bg-gray-600 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75"></div>
        <div class="bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full">
            <form id="reset-password-form">
                <div class="px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-white">Reset Password</h3>
                     <div id="reset-message-area" class="mt-2"></div>
                    <input type="hidden" id="reset-user-id" name="user_id">
                    <div class="mt-4">
                        <label class="text-gray-400">New Password*</label>
                        <input type="password" name="new_password" required class="w-full bg-gray-700 p-2 rounded mt-1">
                    </div>
                </div>
                <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 sm:ml-3 sm:w-auto sm:text-sm">Set Password</button>
                    <button type="button" id="cancel-reset-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div id="delete-user-modal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75"></div>
        <div class="bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full">
            <div class="px-4 pt-5 pb-4 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-white">Confirm Deletion</h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-300">Are you sure you want to delete this user? This action cannot be undone.</p>
                </div>
            </div>
            <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button id="confirm-delete-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Delete</button>
                <button id="cancel-delete-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Email Tooltip -->
<div id="email-tooltip" class="email-tooltip"></div>

<script>
// Load Leaflet CSS and JS if not already loaded
function loadLeafletResources() {
    return new Promise((resolve, reject) => {
        if (typeof L !== 'undefined') {
            resolve();
            return;
        }
        
        // Load Leaflet CSS
        const leafletCSS = document.createElement('link');
        leafletCSS.rel = 'stylesheet';
        leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        leafletCSS.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
        leafletCSS.crossOrigin = '';
        document.head.appendChild(leafletCSS);
        
        // Load Leaflet JS
        const leafletJS = document.createElement('script');
        leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        leafletJS.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        leafletJS.crossOrigin = '';
        leafletJS.async = true;
        leafletJS.defer = true;
        leafletJS.onload = () => resolve();
        leafletJS.onerror = () => reject(new Error('Failed to load Leaflet'));
        document.head.appendChild(leafletJS);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Load Leaflet resources first
    loadLeafletResources()
        .then(() => {
            // Initialize map with error handling
            let map;
            try {
                map = L.map('map', {
                    center: [20.5937, 78.9629], // Default center of India
                    zoom: 4,
                    zoomControl: true,
                    attributionControl: true
                });
                
                // Add custom CSS for full container usage
                const style = document.createElement('style');
                style.textContent = `
                    #map {
                        width: 100% !important;
                        height: 100% !important;
                        min-height: 400px;
                        border-radius: 0.5rem;
                    }
                    .leaflet-container {
                        background: #374151 !important;
                        border-radius: 0.5rem;
                    }
                    .leaflet-popup-content-wrapper {
                        border-radius: 0.5rem !important;
                    }
                `;
                document.head.appendChild(style);
                
            } catch (error) {
                console.error('Error initializing map:', error);
                document.getElementById('map').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #6b7280; font-size: 14px;"><i class="fas fa-map-marked-alt" style="margin-right: 8px;"></i>Map could not be loaded</div>';
                return;
            }

    // Add the tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // If we have society coordinates, show marker
    const latitude = <?php echo json_encode($society['latitude'] ?? null); ?>;
    const longitude = <?php echo json_encode($society['longitude'] ?? null); ?>;
    
    if (latitude && longitude && map) {
        try {
            // Create marker at society location
            const marker = L.marker([latitude, longitude]).addTo(map);
            
            // Add popup with society name
            marker.bindPopup("<?php echo htmlspecialchars($society['society_name'] ?? ''); ?>");
            
            // Center map on society location with appropriate zoom
            map.setView([latitude, longitude], 15);
        } catch (error) {
            console.error('Error adding marker to map:', error);
        }
    } else {
        // If no coordinates, try to geocode the address
        const address = '<?= htmlspecialchars($society['street_address'] ?? '') ?>, <?= htmlspecialchars($society['city'] ?? '') ?>, <?= htmlspecialchars($society['state'] ?? '') ?>, <?= htmlspecialchars($society['pin_code'] ?? '') ?>';
        
        if (address.trim()) {
            // Try to geocode the address using Nominatim
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        // Update map center and add marker
                        map.setView([lat, lng], 15);
                        const marker = L.marker([lat, lng]).addTo(map);
                        marker.bindPopup(`
                            <div style="color: #000; min-width: 200px;">
                                <h3 style="margin: 0 0 5px 0; color: #1f2937; font-size: 16px;"><?= htmlspecialchars($society['society_name'] ?? 'Society') ?></h3>
                                <p style="margin: 0; font-size: 14px; color: #6b7280;">${data[0].display_name}</p>
                            </div>
                        `);
                    }
                })
                .catch(error => {
                    console.log('Geocoding failed:', error);
                });
        }
    }
    
    // Ensure map uses full container size
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
    
    // Handle window resize
    window.addEventListener('resize', () => {
        setTimeout(() => {
            map.invalidateSize();
        }, 100);
    });
        })
        .catch(error => {
            console.error('Failed to load Leaflet:', error);
            document.getElementById('map').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #6b7280; font-size: 14px;"><i class="fas fa-map-marked-alt" style="margin-right: 8px;"></i>Map could not be loaded</div>';
        });

    // User Management Functionality
    const userModal = document.getElementById('user-modal');
    const userForm = document.getElementById('user-form');
    const modalTitle = document.getElementById('modal-title');
    const modalMessageArea = document.getElementById('modal-message-area');
    const userIdField = document.getElementById('user-id');
    const passwordContainer = document.getElementById('password-field-container');
    const passwordField = document.getElementById('password');
    const formSubmitBtn = document.getElementById('form-submit-btn');
    const qrCodeModal = document.getElementById('qr-code-modal');
    const deleteUserModal = document.getElementById('delete-user-modal');
    let userToDelete = null;

    // Add User Button
    document.getElementById('add-user-btn')?.addEventListener('click', function() {
        modalTitle.textContent = 'Add New User';
        userForm.reset();
        userIdField.value = '';
        passwordContainer.style.display = 'block';
        passwordField.required = true;
        formSubmitBtn.textContent = 'Save';
        modalMessageArea.innerHTML = '';
        userModal.classList.remove('hidden');
    });

    // Edit User Buttons
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const row = document.getElementById(`user-row-${userId}`);
            
            // Extract name - need to handle potential "Primary" badge
            let nameCell = row.querySelector('td:first-child');
            let name = nameCell.childNodes[0].nodeValue.trim();
            
            // Extract contact info
            const contactCell = row.querySelector('td:nth-child(2)');
            const contactLines = contactCell.innerHTML.split('<br>');
            const email = contactLines[0].trim();
            const phone = contactLines[1].trim();
            
            // Extract username
            const username = row.querySelector('td:nth-child(3)').textContent.trim();

            modalTitle.textContent = 'Edit User';
            userIdField.value = userId;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('phone').value = phone;
            document.getElementById('username').value = username;
            passwordContainer.style.display = 'none';
            passwordField.required = false;
            formSubmitBtn.textContent = 'Update';
            modalMessageArea.innerHTML = '';
            userModal.classList.remove('hidden');
        });
    });

    // Cancel Button
    document.getElementById('cancel-btn')?.addEventListener('click', function() {
        userModal.classList.add('hidden');
    });

    // User Form Submission
    userForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = userIdField.value !== '';
        
        modalMessageArea.innerHTML = '<div class="p-2 bg-blue-500 text-white rounded text-sm">Processing...</div>';

        fetch(`index.php?action=${isEdit ? 'update_client_user' : 'add_client_user'}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalMessageArea.innerHTML = `<div class="p-2 bg-green-500 text-white rounded text-sm">${data.message}</div>`;
                setTimeout(() => {
                    userModal.classList.add('hidden');
                    window.location.reload();
                }, 1500);
            } else {
                modalMessageArea.innerHTML = `<div class="p-2 bg-red-500 text-white rounded text-sm">${data.error || 'An error occurred'}</div>`;
            }
        })
        .catch(error => {
            modalMessageArea.innerHTML = `<div class="p-2 bg-red-500 text-white rounded text-sm">Network error: ${error.message}</div>`;
        });
    });

    // QR Code User Buttons
    document.querySelectorAll('.user-qr-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userName = this.getAttribute('data-user-name');
            const userEmail = this.getAttribute('data-user-email');
            const userPhone = this.getAttribute('data-user-phone');
            
            // Debug: Log the data being sent
            console.log('QR Code Request - Name:', userName, 'Email:', userEmail, 'Phone:', userPhone);
            
            // Make an AJAX call to generate the QR code
            fetch(`index.php?action=generate_user_qr_code`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    name: userName, 
                    email: userEmail, 
                    phone: userPhone 
                })
            })
            .then(response => {
                console.log('QR Code Response Status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('QR Code Response Data:', data);
                if (data.success) {
                    document.getElementById('qr-code-title').textContent = `Contact: ${userName}`;
                    document.getElementById('modal-qr-code-img').src = data.qr_code_uri;
                    qrCodeModal.classList.remove('hidden');
                } else {
                    alert('Error: ' + (data.error || 'Failed to generate QR code'));
                }
            })
            .catch(error => {
                console.error('QR Code Error:', error);
                alert('Network error: ' + error.message);
            });
        });
    });

    // Delete User Buttons
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            userToDelete = this.getAttribute('data-user-id');
            deleteUserModal.classList.remove('hidden');
        });
    });

    // Confirm Delete
    document.getElementById('confirm-delete-btn')?.addEventListener('click', function() {
        if (userToDelete) {
            const societyId = <?php echo json_encode($society['id'] ?? ''); ?>;
            
            fetch('index.php?action=delete_client_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    user_id: userToDelete, 
                    society_id: societyId 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.getElementById(`user-row-${userToDelete}`);
                    if (row) {
                        row.remove();
                    }
                    deleteUserModal.classList.add('hidden');
                    userToDelete = null;
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete user'));
                }
            })
            .catch(error => {
                alert('Network error: ' + error.message);
            });
        }
    });

    // Cancel Delete
    document.getElementById('cancel-delete-btn')?.addEventListener('click', function() {
        deleteUserModal.classList.add('hidden');
        userToDelete = null;
    });

    // Make Primary Buttons
    document.querySelectorAll('.make-primary-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const societyId = <?php echo json_encode($society['id'] ?? ''); ?>;
            
            if (confirm('Are you sure you want to make this user the primary contact?')) {
                fetch('index.php?action=set_primary_client_user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId, society_id: societyId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to update primary contact'));
                    }
                })
                .catch(error => {
                    alert('Network error: ' + error.message);
                });
            }
        });
    });

    // Reset Password Buttons
    document.querySelectorAll('.reset-password-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            document.getElementById('reset-user-id').value = userId;
            document.getElementById('reset-password-modal').classList.remove('hidden');
        });
    });

    // Reset Password Form
    document.getElementById('reset-password-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const messageArea = document.getElementById('reset-message-area');
        
        messageArea.innerHTML = '<div class="p-2 bg-blue-500 text-white rounded text-sm">Processing...</div>';

        fetch('index.php?action=reset_client_user_password', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageArea.innerHTML = `<div class="p-2 bg-green-500 text-white rounded text-sm">${data.message}</div>`;
                setTimeout(() => {
                    document.getElementById('reset-password-modal').classList.add('hidden');
                }, 1500);
            } else {
                messageArea.innerHTML = `<div class="p-2 bg-red-500 text-white rounded text-sm">${data.error || 'An error occurred'}</div>`;
            }
        })
        .catch(error => {
            messageArea.innerHTML = `<div class="p-2 bg-red-500 text-white rounded text-sm">Network error: ${error.message}</div>`;
        });
    });

    // Cancel Reset Password
    document.getElementById('cancel-reset-btn')?.addEventListener('click', function() {
        document.getElementById('reset-password-modal').classList.add('hidden');
    });

    // Delete Client Button
    document.querySelector('.delete-society-btn')?.addEventListener('click', function() {
        const societyId = this.getAttribute('data-id');
        const societyName = <?php echo json_encode($society['society_name'] ?? ''); ?>;
        
        if (confirm(`Are you sure you want to delete "${societyName}"? This action cannot be undone and will delete all associated data.`)) {
            fetch('index.php?action=delete_society', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: societyId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php?page=society-list';
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete Client'));
                }
            })
            .catch(error => {
                alert('Network error: ' + error.message);
            });
        }
    });

    // Society QR Code Generation
    const generateSocietyQrBtn = document.getElementById('generate-society-qr-btn');
    const societyQrModal = document.getElementById('society-qr-modal');
    const societyQrCodeImg = document.getElementById('society-qr-code-img');
    const downloadSocietyQrBtn = document.getElementById('download-society-qr-btn');
    const closeSocietyQrModalBtn = document.getElementById('close-society-qr-modal');

    generateSocietyQrBtn?.addEventListener('click', function() {
        // Sanitize and validate data
        const societyName = <?php echo json_encode(trim($society['society_name'] ?? '')); ?>;
        const streetAddress = <?php echo json_encode(trim($society['street_address'] ?? '')); ?>;
        const city = <?php echo json_encode(trim($society['city'] ?? '')); ?>;
        const state = <?php echo json_encode(trim($society['state'] ?? '')); ?>;
        const pinCode = <?php echo json_encode(trim($society['pin_code'] ?? '')); ?>;
        const qrCodeId = <?php echo json_encode(trim($society['qr_code'] ?? '')); ?>;
        const societyId = <?php echo json_encode(trim($society['id'] ?? '')); ?>;
        
        // Get company logo path
        const logoPath = '<?php echo isset($_SERVER['HTTP_HOST']) ? 
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . 
            $_SERVER['HTTP_HOST'] . '/project/Gaurd/Comapany/assets/logo-6858f5cfb718c-561-5610966_cyber-security-logo-png-transparent-png-removebg-preview.png' : ''; ?>';

        // Get location coordinates
        const latitude = <?php echo json_encode($society['latitude'] ?? null); ?>;
        const longitude = <?php echo json_encode($society['longitude'] ?? null); ?>;

        // Primary contact details with fallback
        const primaryContact = <?php 
            $contact = $primary_contact ?? [];
            echo json_encode([
                'name' => trim($contact['name'] ?? 'N/A'),
                'phone' => trim($contact['phone'] ?? 'N/A'),
                'email' => trim($contact['email'] ?? 'N/A')
            ]); 
        ?>;

        // Construct address with fallback
        const fullAddress = [streetAddress, city, state, pinCode]
            .filter(part => part && part.trim() !== '')
            .join(', ');

        // Ensure we have a valid address
        if (!fullAddress) {
            alert('Cannot generate QR code: Address is incomplete');
            return;
        }

        // Create data object for QR code with more robust checks
        const societyData = {
            id: societyId || '',
            name: societyName || 'Unknown Society',
            qrCodeId: qrCodeId || '',
            latitude: latitude ? parseFloat(latitude) : null,
            longitude: longitude ? parseFloat(longitude) : null,
            location: latitude && longitude ? {
                lat: parseFloat(latitude),
                lng: parseFloat(longitude),
                coordinates: `${latitude}, ${longitude}`
            } : null,
            company: {
                name: 'Security Guard Services',
                tagline: 'Professional Security Solutions',
                logo: logoPath
            }
        };

        console.log('Generating Society QR Data:', JSON.stringify(societyData, null, 2));

        // Validate data before storing
        const requiredFields = ['name'];
        const missingFields = requiredFields.filter(field => !societyData[field]);
        
        if (missingFields.length > 0) {
            alert(`Cannot generate QR code: Missing required fields: ${missingFields.join(', ')}`);
            return;
        }

        // Store data in localStorage with error handling
        try {
            // Store full address separately
            localStorage.setItem('societyFullAddress', fullAddress);
            
            localStorage.setItem('societyQRData', JSON.stringify(societyData));
            
            // Verify storage
            const storedData = localStorage.getItem('societyQRData');
            if (!storedData) {
                throw new Error('Failed to store data in localStorage');
            }
            
            // Open the QR code page in a new window
            window.open('UI/clientprint/attendence/index.php', '_blank');
        } catch (error) {
            console.error('localStorage Error:', error);
            alert('Error storing QR code data. Please try again.');
        }
    });

    // Download QR Code
    downloadSocietyQrBtn?.addEventListener('click', function() {
        const link = document.createElement('a');
        link.href = societyQrCodeImg.src;
        link.download = `${<?php echo json_encode($society['society_name'] ?? 'Society'); ?>}_QR_Code.png`;
        link.click();
    });

    // Close Modal
    closeSocietyQrModalBtn?.addEventListener('click', function() {
        societyQrModal.classList.add('hidden');
    });
});

// Sidebar functionality should be handled by dashboard_layout.php
// The view-society page is correctly included in the clients menu structure

// Initialize modals and other UI components
document.getElementById('open-qr-modal')?.addEventListener('click', function() {
    const qrCodeUri = <?php echo json_encode($qrCodeUri ?? ''); ?>;
    if (qrCodeUri) {
        document.getElementById('modal-qr-code-img').src = qrCodeUri;
        document.getElementById('qr-code-modal').classList.remove('hidden');
    }
});

document.getElementById('close-qr-modal')?.addEventListener('click', function() {
    document.getElementById('qr-code-modal').classList.add('hidden');
});

// Copy to clipboard functionality for email fields
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        // Use modern clipboard API
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess();
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyToClipboard(text);
    }
}

// Email tooltip functionality
let tooltipTimeout = null;

function showEmailTooltip(element, email) {
    // Clear any existing timeout
    if (tooltipTimeout) {
        clearTimeout(tooltipTimeout);
    }
    
    // Add small delay for better UX
    tooltipTimeout = setTimeout(() => {
        const tooltip = document.getElementById('email-tooltip');
        if (!tooltip) return;
        
        tooltip.textContent = email;
        tooltip.classList.add('show');
        
        // Position tooltip above the email field
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        // Calculate position
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 15; // 15px above the element
        
        // Ensure tooltip doesn't go off-screen
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 10) {
            // If tooltip would go above viewport, show below instead
            top = rect.bottom + 15;
        }
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }, 300); // 300ms delay
}

function hideEmailTooltip() {
    // Clear any pending show timeout
    if (tooltipTimeout) {
        clearTimeout(tooltipTimeout);
        tooltipTimeout = null;
    }
    
    const tooltip = document.getElementById('email-tooltip');
    if (tooltip) {
        tooltip.classList.remove('show');
    }
}

// Add event listeners to email fields after DOM loads
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltip functionality to email fields
    const emailFields = document.querySelectorAll('.email-text');
    emailFields.forEach(function(field) {
        const email = field.getAttribute('data-email');
        if (email) {
            field.addEventListener('mouseenter', function() {
                showEmailTooltip(this, email);
            });
            
            field.addEventListener('mouseleave', function() {
                hideEmailTooltip();
            });
        }
    });
});

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showCopySuccess();
    } catch (err) {
        console.error('Fallback copy failed: ', err);
        alert('Failed to copy to clipboard');
    }
    
    document.body.removeChild(textArea);
}

function showCopySuccess() {
    // Create a temporary success message
    const successMsg = document.createElement('div');
    successMsg.textContent = 'Copied to clipboard!';
    successMsg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 500;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;
    
    // Add animation CSS
    if (!document.getElementById('copy-animation-style')) {
        const style = document.createElement('style');
        style.id = 'copy-animation-style';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(successMsg);
    
    // Remove after 2 seconds
    setTimeout(() => {
        successMsg.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (successMsg.parentNode) {
                successMsg.parentNode.removeChild(successMsg);
            }
        }, 300);
    }, 2000);
}
</script>
 