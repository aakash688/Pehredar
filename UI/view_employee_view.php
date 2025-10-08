<?php
// The $page_data and $config variables are available here from index.php
global $page_data, $config;
$employee = $page_data['employee'];
$basePath = rtrim($config['base_url'], '/');

// Helper function for displaying information with better formatting
function display_info($label, $value, $format = null, $copyable = false) {
    $formatted_value = 'Not provided';
    $copy_class = '';
    $copy_attr = '';

    if ($value !== null && $value !== '') {
        switch ($format) {
            case 'currency':
                $formatted_value = '₹' . number_format(floatval($value), 2);
                break;
            case 'date':
                if (!empty($value) && strtotime($value) !== false) {
                    $formatted_value = date('F j, Y', strtotime($value));
                }
                break;
            case 'boolean':
                $formatted_value = $value ? 
                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-200 text-green-800">Enabled</span>' : 
                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-200 text-red-800">Disabled</span>';
                break;
            case 'phone':
                $formatted_value = '<a href="tel:' . htmlspecialchars($value) . '" class="text-blue-400 hover:text-blue-300 transition-colors">' . htmlspecialchars($value) . '</a>';
                break;
            case 'email':
                $formatted_value = '<a href="mailto:' . htmlspecialchars($value) . '" class="text-blue-400 hover:text-blue-300 transition-colors break-all" title="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</a>';
                break;
            default:
                $formatted_value = htmlspecialchars($value);
                break;
        }
        
        // Add copy functionality for specific fields
        if ($copyable && in_array($format, ['phone', 'email']) || $copyable === true) {
            $copy_class = 'cursor-pointer hover:text-blue-400 transition-colors';
            $copy_attr = 'onclick="copyToClipboard(\'' . htmlspecialchars($value) . '\', \'' . $label . '\')" title="Click to copy"';
        }
    }
    
    // Add overflow handling for email fields
    $dd_class = "mt-1 text-sm text-white sm:mt-0 sm:col-span-2 {$copy_class}";
    if ($format === 'email') {
        $dd_class .= " detail-email";
    }
    
    echo "<div class='py-3 sm:py-4 sm:grid sm:grid-cols-3 sm:gap-4'>
        <dt class='text-sm font-medium text-gray-400'>{$label}</dt>
        <dd class='{$dd_class}' {$copy_attr}>{$formatted_value}</dd>
    </div>";
}

// Helper function for displaying document links
function display_doc_link($label, $path, $basePath) {
    echo '<li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">';
    echo '<div class="w-0 flex-1 flex items-center">
        <i class="fas fa-paperclip text-gray-400 mr-2"></i>
        <span class="ml-2 flex-1 w-0 truncate text-gray-300">' . $label . '</span>
    </div>';
    echo '<div class="ml-4 flex-shrink-0">';
    if ($path) {
        $fullPath = str_starts_with($path, 'http') ? $path : $basePath . '/' . ltrim($path, '/');
        $relativePath = ltrim($path, '/');
        $downloadUrl = $basePath . '/download.php?file=' . urlencode($relativePath);
        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fileName = basename($path);
        
        $icon = 'fa-file-alt';
        $previewType = 'other';
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $icon = 'fa-image';
            $previewType = 'image';
        } elseif ($fileExtension === 'pdf') {
            $icon = 'fa-file-pdf';
            $previewType = 'pdf';
        }
        
        echo '<button onclick="openDocumentModal(\'' . htmlspecialchars($fullPath) . '\', \'' . $previewType . '\', \'' . htmlspecialchars($label) . '\', \'' . htmlspecialchars($fileName) . '\')" class="text-blue-400 hover:text-blue-300 transition-colors mr-2" title="Preview">
            <i class="fas ' . $icon . '"></i>
        </button>';
        echo '<a href="' . htmlspecialchars($downloadUrl) . '" class="text-green-400 hover:text-green-300 transition-colors" title="Download">
            <i class="fas fa-download"></i>
        </a>';
    } else {
        echo '<span class="text-gray-500 text-xs">Not uploaded</span>';
    }
    echo '</div></li>';
}

// Calculate tenure
$joiningDate = new DateTime($employee['date_of_joining']);
$currentDate = new DateTime();
$tenure = $currentDate->diff($joiningDate);
$tenureText = $tenure->y > 0 ? $tenure->y . ' years, ' . $tenure->m . ' months' : $tenure->m . ' months';

// Generate avatar initials
$initials = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['surname'], 0, 1));
?>

<?php
// Display flash message if present
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear the flash message after displaying
    
    $alertClass = $flash['type'] === 'success' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300';
    $iconClass = $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    echo "<div class='fixed top-4 right-4 {$alertClass} px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300' id='flash-message'>
        <div class='flex items-center'>
            <i class='fas {$iconClass} mr-2'></i>
            <span>{$flash['message']}</span>
            <button onclick='this.parentElement.parentElement.remove()' class='ml-4 text-current hover:opacity-75'>
                <i class='fas fa-times'></i>
            </button>
        </div>
    </div>";
}
?>

<div class="max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="index.php?page=employee-list" class="inline-flex items-center text-sm font-medium text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-users mr-2"></i>
                    Employees
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <span class="text-sm font-medium text-white"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['surname']) ?></span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="bg-gray-800 rounded-xl shadow-2xl px-8 py-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center space-x-6 mb-4 lg:mb-0">
                <!-- Avatar -->
                <div class="relative">
                    <?php if ($employee['profile_photo']): ?>
                        <img class="h-24 w-24 rounded-full object-cover ring-4 ring-gray-700" 
                             src="<?= htmlspecialchars($basePath . '/' . ltrim($employee['profile_photo'], '/')) ?>" 
                             alt="Profile Photo">
                    <?php else: ?>
                        <div class="h-24 w-24 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center ring-4 ring-gray-700">
                            <span class="text-2xl font-bold text-white"><?= $initials ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Status indicator (only in development) -->
                    <?php if (defined('APP_ENV') && APP_ENV !== 'production'): ?>
                    <div class="absolute -bottom-1 -right-1 h-6 w-6 bg-green-500 rounded-full border-2 border-gray-800" title="Development Status Indicator"></div>
                    <?php endif; ?>
                </div>
                
                <!-- Employee Info -->
                <div>
                    <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['surname']) ?></h1>
                    <p class="text-lg text-blue-400"><?= htmlspecialchars($employee['user_type']) ?></p>
                    <div class="flex items-center space-x-4 mt-2">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-200 text-green-800">Active</span>
                        <span class="text-sm text-gray-400">ID: <?= $employee['id'] ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3">
                <a href="index.php?page=edit-employee&id=<?= $employee['id'] ?>" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm flex items-center">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <a href="index.php?page=id-card-view&id=<?= $employee['id'] ?>" 
                   target="_blank" rel="noopener noreferrer"
                   class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm flex items-center">
                    <i class="fas fa-id-card mr-2"></i>
                    ID Card
                </a>
                <a href="index.php?page=resume-view&id=<?= $employee['id'] ?>" 
                   target="_blank" rel="noopener noreferrer"
                   class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm flex items-center">
                    <i class="fas fa-file-alt mr-2"></i>Resume
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Strip -->
    <div class="bg-gray-800 rounded-xl shadow-2xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-400"><?= date('M j, Y', strtotime($employee['date_of_joining'])) ?></div>
                <div class="text-sm text-gray-400">Joining Date</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-400"><?= $tenureText ?></div>
                <div class="text-sm text-gray-400">Tenure</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-400">
                    <a href="tel:<?= htmlspecialchars($employee['mobile_number']) ?>" class="hover:text-purple-300 transition-colors">
                        <?= htmlspecialchars($employee['mobile_number']) ?>
                    </a>
                </div>
                <div class="text-sm text-gray-400">Primary Contact</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-400">
                    <a href="mailto:<?= htmlspecialchars($employee['email_id']) ?>" 
                       class="hover:text-orange-300 transition-colors block summary-email px-2" 
                       title="<?= htmlspecialchars($employee['email_id']) ?>">
                        <?= htmlspecialchars($employee['email_id']) ?>
                    </a>
                </div>
                <div class="text-sm text-gray-400">Email</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-gray-800 rounded-xl shadow-2xl mb-6">
        <div class="border-b border-gray-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <button onclick="switchTab('overview')" 
                        id="tab-overview" 
                        class="tab-button active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-400">
                    <i class="fas fa-user mr-2"></i>Overview
                </button>
                <button onclick="switchTab('employment')" 
                        id="tab-employment" 
                        class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                    <i class="fas fa-briefcase mr-2"></i>Employment
                </button>
                <button onclick="switchTab('family')" 
                        id="tab-family" 
                        class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                    <i class="fas fa-users mr-2"></i>Family Details
                </button>
                <button onclick="switchTab('documents')" 
                        id="tab-documents" 
                        class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                    <i class="fas fa-file-alt mr-2"></i>Documents
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Overview Tab -->
            <div id="content-overview" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user-circle mr-2 text-blue-400"></i>
                            Personal Information
                        </h3>
                        <dl class="divide-y divide-gray-700">
                            <?php display_info('Full Name', $employee['first_name'] . ' ' . $employee['surname']); ?>
                            <?php display_info('Date of Birth', $employee['date_of_birth'], 'date'); ?>
                            <?php display_info('Gender', $employee['gender']); ?>
                            <?php display_info('Mobile Number', $employee['mobile_number'], 'phone', true); ?>
                            <?php display_info('Email Address', $employee['email_id'], 'email', true); ?>
                            <?php display_info('Current Address', $employee['address']); ?>
                            <?php display_info('Permanent Address', $employee['permanent_address']); ?>
                        </dl>
                    </div>

                    <!-- Identification Numbers -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-id-card mr-2 text-green-400"></i>
                            Identification Numbers
                        </h3>
                        <dl class="divide-y divide-gray-700">
                            <?php display_info('Aadhar Number', $employee['aadhar_number'], null, true); ?>
                            <?php display_info('PAN Number', $employee['pan_number'], null, true); ?>
                            <?php display_info('Passport Number', $employee['passport_number'] ?? null, null, true); ?>
                            <?php display_info('Voter ID Number', $employee['voter_id_number'] ?? null, null, true); ?>
                            <?php display_info('PF Number', $employee['pf_number'] ?? null, null, true); ?>
                            <?php display_info('ESIC Number', $employee['esic_number'] ?? null, null, true); ?>
                            <?php display_info('UAN Number', $employee['uan_number'] ?? null, null, true); ?>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Employment Tab -->
            <div id="content-employment" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Employment Details -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-briefcase mr-2 text-purple-400"></i>
                            Employment Details
                        </h3>
                        <dl class="divide-y divide-gray-700">
                            <?php display_info('Employee ID', $employee['id'], null, true); ?>
                            <?php display_info('User Role', $employee['user_type']); ?>
                            <?php display_info('Date of Joining', $employee['date_of_joining'], 'date'); ?>
                            <?php display_info('Highest Qualification', $employee['highest_qualification']); ?>
                            <?php display_info('Salary', $employee['salary'], 'currency'); ?>
                            <?php display_info('Advance Salary', $employee['advance_salary'] ?? null, 'currency'); ?>
                        </dl>
                    </div>

                    <!-- Banking & Access -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-university mr-2 text-orange-400"></i>
                            Banking & Access
                        </h3>
                        <dl class="divide-y divide-gray-700">
                            <?php display_info('Bank Name', $employee['bank_name'] ?? ''); ?>
                            <?php display_info('Account Number', $employee['bank_account_number'] ?? '', null, true); ?>
                            <?php display_info('IFSC Code', $employee['ifsc_code'] ?? '', null, true); ?>
                            <?php display_info('Web Access', $employee['web_access'], 'boolean'); ?>
                            <?php display_info('Mobile Access', $employee['mobile_access'], 'boolean'); ?>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Family Details Tab -->
            <div id="content-family" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if (!empty($page_data['family_references'])): ?>
                        <?php 
                        $ref1 = null;
                        $ref2 = null;
                        foreach ($page_data['family_references'] as $ref) {
                            if ($ref['reference_index'] == 1) {
                                $ref1 = $ref;
                            } elseif ($ref['reference_index'] == 2) {
                                $ref2 = $ref;
                            }
                        }
                        ?>
                        
                        <!-- Family Reference 1 (Required) -->
                        <div class="bg-gray-900 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-friends mr-2 text-blue-400"></i>
                                Family Reference 1 <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded-full ml-2">Required</span>
                            </h3>
                            <?php if ($ref1): ?>
                                <dl class="divide-y divide-gray-700">
                                    <?php display_info('Name', $ref1['name']); ?>
                                    <?php display_info('Relation', $ref1['relation']); ?>
                                    <?php display_info('Primary Mobile', $ref1['mobile_primary'], 'phone', true); ?>
                                    <?php display_info('Alternate Mobile', $ref1['mobile_secondary'], 'phone', true); ?>
                                    <?php display_info('Address', $ref1['address']); ?>
                                </dl>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                                    <p class="text-red-400">Required family reference missing</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Family Reference 2 (Optional) -->
                        <div class="bg-gray-900 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-friends mr-2 text-green-400"></i>
                                Family Reference 2 <span class="text-xs bg-gray-200 text-gray-800 px-2 py-1 rounded-full ml-2">Optional</span>
                            </h3>
                            <?php if ($ref2): ?>
                                <dl class="divide-y divide-gray-700">
                                    <?php display_info('Name', $ref2['name']); ?>
                                    <?php display_info('Relation', $ref2['relation']); ?>
                                    <?php display_info('Primary Mobile', $ref2['mobile_primary'], 'phone', true); ?>
                                    <?php display_info('Alternate Mobile', $ref2['mobile_secondary'], 'phone', true); ?>
                                    <?php display_info('Address', $ref2['address']); ?>
                                </dl>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-minus-circle text-gray-500 text-3xl mb-3"></i>
                                    <p class="text-gray-400">Not provided</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="col-span-2 bg-gray-900 rounded-lg p-6">
                            <div class="text-center py-12">
                                <i class="fas fa-users text-gray-500 text-5xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-400 mb-2">No Family Details</h3>
                                <p class="text-gray-500">No family references have been provided for this employee.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Tab -->
            <div id="content-documents" class="tab-content hidden">
                <div class="bg-gray-900 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-yellow-400"></i>
                        Uploaded Documents
                    </h3>
                    <ul role="list" class="divide-y divide-gray-700">
                        <?php display_doc_link('Aadhar Card', $employee['aadhar_card_scan'], $basePath); ?>
                        <?php display_doc_link('PAN Card', $employee['pan_card_scan'], $basePath); ?>
                        <?php display_doc_link('Bank Passbook', $employee['bank_passbook_scan'], $basePath); ?>
                        <?php display_doc_link('Police Verification', $employee['police_verification_document'], $basePath); ?>
                        <?php display_doc_link('Ration Card', $employee['ration_card_scan'], $basePath); ?>
                        <?php display_doc_link('Light Bill', $employee['light_bill_scan'], $basePath); ?>
                        <?php display_doc_link('Voter ID Card', $employee['voter_id_scan'] ?? null, $basePath); ?>
                        <?php display_doc_link('Passport', $employee['passport_scan'] ?? null, $basePath); ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Document Preview Modal -->
<div id="documentPreviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-75 flex items-center justify-center">
    <div class="relative bg-gray-900 rounded-lg shadow-2xl max-w-4xl w-full mx-4 my-8">
        <div class="flex justify-between items-center p-4 border-b border-gray-700">
            <h2 id="documentModalTitle" class="text-xl font-semibold text-white">Document Preview</h2>
            <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-4">
            <div id="imagePreviewContainer" class="hidden">
                <img id="imagePreview" class="max-w-full h-auto rounded-lg" src="" alt="Document Preview">
            </div>
            <div id="pdfPreviewContainer" class="hidden">
                <iframe id="pdfPreview" class="w-full h-[70vh] rounded-lg" src="" frameborder="0"></iframe>
            </div>
            <div id="otherPreviewContainer" class="hidden text-center py-8">
                <i class="fas fa-file-alt text-6xl text-gray-400 mb-4"></i>
                <p class="text-gray-300">Preview not available for this file type</p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-700 flex justify-end">
            <a href="#" id="downloadLink" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition">
                <i class="fas fa-download mr-2"></i>Download
            </a>
        </div>
    </div>
</div>

<!-- PDF Preview Modal -->
<div id="pdfPreviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-75 flex items-center justify-center">
    <div class="relative bg-gray-900 rounded-lg shadow-2xl max-w-4xl w-full mx-4 my-8">
        <div class="flex justify-between items-center p-4 border-b border-gray-700">
            <h2 id="pdfModalTitle" class="text-xl font-semibold text-white">PDF Preview</h2>
            <button onclick="closePdfModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-4 relative">
            <!-- Loading Indicator -->
            <div id="pdfLoadingIndicator" class="absolute inset-0 flex items-center justify-center bg-gray-800 bg-opacity-90 hidden">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-4 border-blue-500 border-t-transparent"></div>
                    <p class="text-white mt-4 text-lg">Generating PDF...</p>
                    <p class="text-gray-300 text-sm">This may take a few seconds</p>
                </div>
            </div>
            
            <iframe id="pdfPreviewFrame" class="w-full h-[70vh]" src="" frameborder="0"></iframe>
        </div>
        <div class="p-4 border-t border-gray-700 flex justify-end">
            <a href="#" id="pdfDownloadLink" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition">
                <i class="fas fa-download mr-2"></i>Download
            </a>
        </div>
    </div>
</div>

<script>
// Auto-hide flash message after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.transform = 'translateX(100%)';
            setTimeout(() => {
                flashMessage.remove();
            }, 300);
        }, 5000);
    }
});

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => content.classList.add('hidden'));
    
    // Remove active class from all tab buttons
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-400');
        button.classList.add('border-transparent', 'text-gray-400');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab button
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.classList.add('active', 'border-blue-500', 'text-blue-400');
    activeButton.classList.remove('border-transparent', 'text-gray-400');
}

// Copy to clipboard functionality
function copyToClipboard(text, label) {
    navigator.clipboard.writeText(text).then(function() {
        showToast(label + ' copied to clipboard!');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        showToast('Failed to copy to clipboard');
    });
}

// Toast notification
function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    toastMessage.textContent = message;
    toast.classList.remove('translate-x-full');
    
    setTimeout(() => {
        toast.classList.add('translate-x-full');
    }, 3000);
}

// Document modal functions
function openDocumentModal(documentUrl, type, title, fileName) {
    const modal = document.getElementById('documentPreviewModal');
    const titleEl = document.getElementById('documentModalTitle');
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const imageContainer = document.getElementById('imagePreviewContainer');
    const pdfContainer = document.getElementById('pdfPreviewContainer');
    const otherContainer = document.getElementById('otherPreviewContainer');
    const downloadLink = document.getElementById('downloadLink');

    // Reset all containers
    imageContainer.classList.add('hidden');
    pdfContainer.classList.add('hidden');
    otherContainer.classList.add('hidden');

    // Set title
    titleEl.textContent = title;

    // Determine download URL
    const baseUrl = '<?= rtrim($basePath, '/') ?>';
    const relativePath = documentUrl.replace(baseUrl + '/', '');
    const downloadUrl = baseUrl + '/download.php?file=' + encodeURIComponent(relativePath);

    // Set download link
    downloadLink.href = downloadUrl;
    downloadLink.download = fileName;

    // Handle preview based on type
    switch(type) {
        case 'image':
            imagePreview.src = documentUrl;
            imageContainer.classList.remove('hidden');
            break;
        case 'pdf':
            pdfPreview.src = documentUrl;
            pdfContainer.classList.remove('hidden');
            break;
        default:
            otherContainer.classList.remove('hidden');
    }

    // Show modal
    modal.classList.remove('hidden');
}

function closeDocumentModal() {
    const modal = document.getElementById('documentPreviewModal');
    modal.classList.add('hidden');
    
    // Reset sources to prevent unnecessary loading
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    imagePreview.src = '';
    pdfPreview.src = '';
}

// PDF modal functions
function openPdfModal(type, employeeId) {
    const modal = document.getElementById('pdfPreviewModal');
    const pdfFrame = document.getElementById('pdfPreviewFrame');
    const downloadLink = document.getElementById('pdfDownloadLink');
    const titleEl = document.getElementById('pdfModalTitle');
    const loadingEl = document.getElementById('pdfLoadingIndicator');

    // Set title based on type
    titleEl.textContent = type === 'id_card' ? 'ID Card' : 'Resume';

    // Resume is now handled as a direct link, no button state management needed

    // Show modal and loading
    modal.classList.remove('hidden');
    loadingEl.classList.remove('hidden');
    pdfFrame.classList.add('hidden');
    
    // Generate PDF
    const formData = new FormData();
    formData.append('action', 'generate_pdf');
    formData.append('type', type);
    formData.append('employee_id', employeeId);

    // Set timeout for PDF generation
    const timeoutId = setTimeout(() => {
        loadingEl.classList.add('hidden');
        alert('PDF generation timed out. Please try again.');
        modal.classList.add('hidden');
        resetButtonState(type);
    }, 30000); // 30 second timeout

    fetch('actions/employee_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearTimeout(timeoutId);
        
        if (data.success && data.pdf_url) {
            loadingEl.classList.add('hidden');
            pdfFrame.src = data.pdf_url;
            downloadLink.href = data.pdf_url;
            pdfFrame.classList.remove('hidden');
            
            // Log performance info if available
            if (data.cached) {
                console.log('PDF loaded from cache');
            } else if (data.generation_time_ms) {
                console.log(`PDF generated in ${data.generation_time_ms}ms`);
            }
        } else {
            loadingEl.classList.add('hidden');
            alert('Failed to generate PDF: ' + (data.error || 'Unknown error'));
            modal.classList.add('hidden');
        }
        
        // Reset button state
        resetButtonState(type);
    })
    .catch(error => {
        clearTimeout(timeoutId);
        loadingEl.classList.add('hidden');
        console.error('Error:', error);
        
        if (error.name === 'AbortError') {
            alert('PDF generation timed out. Please try again.');
        } else {
            alert('An error occurred while generating the PDF: ' + error.message);
        }
        modal.classList.add('hidden');
        
        // Reset button state
        resetButtonState(type);
    });
}

function resetButtonState(type) {
    // Resume is now handled as a direct link, no button state management needed
}

function closePdfModal() {
    const modal = document.getElementById('pdfPreviewModal');
    const pdfFrame = document.getElementById('pdfPreviewFrame');
    const loadingEl = document.getElementById('pdfLoadingIndicator');
    
    // Hide modal and loading
    modal.classList.add('hidden');
    loadingEl.classList.add('hidden');
    
    // Clear iframe source
    pdfFrame.src = '';
    
    // Reset all button states
    resetButtonState('id_card');
    resetButtonState('resume');
}

// Close modal when clicking outside the modal content
document.getElementById('documentPreviewModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeDocumentModal();
    }
});

// Ensure download works by creating a temporary link
document.getElementById('downloadLink').addEventListener('click', function(event) {
    event.preventDefault();
    const url = this.href;
    const filename = this.download;
    
    // Create a temporary anchor element
    const tempLink = document.createElement('a');
    tempLink.href = url;
    tempLink.download = filename;
    
    // Append to body, click, and remove
    document.body.appendChild(tempLink);
    tempLink.click();
    document.body.removeChild(tempLink);
});
</script>

<style>
/* Email overflow handling */
.email-container {
    max-width: 100%;
    overflow: hidden;
}

.email-link {
    word-break: break-all;
    hyphens: auto;
    max-width: 100%;
}

/* Summary strip email handling */
.summary-email {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Detail section email handling */
.detail-email {
    word-break: break-all;
    hyphens: auto;
    line-height: 1.4;
    max-height: 2.8em; /* Allow up to 2 lines */
    overflow: hidden;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .summary-email {
        font-size: 1.1rem; /* Slightly smaller on mobile */
    }
    
    .detail-email {
        font-size: 0.875rem;
    }
}

/* Tab styles */
.tab-button {
    transition: all 0.2s ease;
}

.tab-button:hover {
    color: #d1d5db;
}

.tab-button.active {
    color: #60a5fa;
    border-bottom-color: #3b82f6;
}

/* Toast animation */
#toast {
    backdrop-filter: blur(5px);
}

/* Modal backdrop */
#documentPreviewModal, #pdfPreviewModal {
    backdrop-filter: blur(5px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-col.lg\\:flex-row {
        flex-direction: column;
    }
}
</style>
