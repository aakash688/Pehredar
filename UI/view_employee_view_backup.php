<?php
// The $page_data and $config variables are available here from index.php
global $page_data, $config;
$employee = $page_data['employee'];
$basePath = rtrim($config['base_url'], '/');

function display_info($label, $value, $format = null) {
    $formatted_value = 'N/A'; // Default to N/A

    if ($value !== null) {
        switch ($format) {
            case 'currency':
                $formatted_value = '₹' . number_format(floatval($value), 2);
                break;
            case 'date':
                if (!empty($value) && strtotime($value) !== false) {
                    $formatted_value = date('F j, Y', strtotime($value));
                }
                // else it remains N/A
                break;
            case 'boolean':
                $formatted_value = $value ? 
                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-200 text-green-800">Enabled</span>' : 
                    '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-200 text-red-800">Disabled</span>';
                break;
            default: // No format or unknown format
                $formatted_value = htmlspecialchars($value);
                break;
        }
    }
    echo "<div class='py-3 sm:py-4 sm:grid sm:grid-cols-3 sm:gap-4'><dt class='text-sm font-medium text-gray-400'>{$label}</dt><dd class='mt-1 text-sm text-white sm:mt-0 sm:col-span-2'>{$formatted_value}</dd></div>";
}

function display_doc_link($label, $path, $basePath) {
    echo '<li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">';
    echo '<div class="w-0 flex-1 flex items-center"><i class="fas fa-paperclip text-gray-400 mr-2"></i><span class="ml-2 flex-1 w-0 truncate text-gray-300">' . $label . '</span></div>';
    echo '<div class="ml-4 flex-shrink-0">';
    if ($path) {
        // Ensure the path is a full URL or prepend base URL if it's a relative path
        $fullPath = str_starts_with($path, 'http') ? $path : $basePath . '/' . ltrim($path, '/');
        $relativePath = ltrim($path, '/');
        $downloadUrl = $basePath . '/download.php?file=' . urlencode($relativePath);
        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fileName = basename($path);
        
        // Determine icon and preview type
        $icon = 'fa-file-alt';
        $previewType = 'other';
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $icon = 'fa-image';
            $previewType = 'image';
        } elseif ($fileExtension === 'pdf') {
            $icon = 'fa-file-pdf';
            $previewType = 'pdf';
        }
        
        echo '<div class="flex space-x-2">';
        echo '<a href="#" onclick="openDocumentModal(\'' . htmlspecialchars($fullPath) . '\', \'' . $previewType . '\', \'' . htmlspecialchars($label) . '\', \'' . htmlspecialchars($fileName) . '\'); return false;" class="font-medium text-blue-400 hover:text-blue-300 mr-2">';
        echo '<i class="fas ' . $icon . ' mr-1"></i>View</a>';
        echo '<a href="' . htmlspecialchars($downloadUrl) . '" class="font-medium text-green-400 hover:text-green-300">';
        echo '<i class="fas fa-download mr-1"></i>Download</a>';
        echo '</div>';
    } else {
        echo '<span class="font-medium text-gray-500">Not Uploaded</span>';
    }
    echo '</div></li>';
}
?>

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
                <img id="imagePreview" class="max-w-full max-h-[70vh] mx-auto object-contain" src="" alt="Document Preview">
            </div>
            <div id="pdfPreviewContainer" class="hidden">
                <iframe id="pdfPreview" class="w-full h-[70vh]" src="" frameborder="0"></iframe>
            </div>
            <div id="otherPreviewContainer" class="hidden text-center text-gray-300 py-8">
                <i class="fas fa-file-alt text-6xl mb-4"></i>
                <p>Preview not available for this file type.</p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-700 flex justify-end">
            <a href="#" id="downloadLink" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition" download>
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

    // Determine download URL (use download.php)
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

function openPdfModal(type, employeeId) {
    const modal = document.getElementById('pdfPreviewModal');
    const pdfFrame = document.getElementById('pdfPreviewFrame');
    const downloadLink = document.getElementById('pdfDownloadLink');
    const titleEl = document.getElementById('pdfModalTitle');
    const loadingEl = document.getElementById('pdfLoadingIndicator');

    // Set title based on type
    titleEl.textContent = type === 'id_card' ? 'ID Card' : 'Resume';

    // Update button state for ID Card
    if (type === 'id_card') {
        const idCardBtn = document.getElementById('idCardBtn');
        const idCardIcon = document.getElementById('idCardIcon');
        const idCardText = document.getElementById('idCardText');
        
        if (idCardBtn && idCardIcon && idCardText) {
            idCardBtn.classList.add('opacity-75', 'cursor-not-allowed');
            idCardBtn.onclick = null; // Disable clicking
            idCardIcon.className = 'fas fa-spinner fa-spin mr-2';
            idCardText.textContent = 'Generating...';
        }
    }

    // Show modal with loading state
    modal.classList.remove('hidden');
    loadingEl.classList.remove('hidden');
    pdfFrame.classList.add('hidden');
    
    // Clear previous PDF
    pdfFrame.src = '';
    
    // Add timeout for long requests
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout

    // Fetch PDF via AJAX with optimized request
    fetch(`index.php?action=generate_pdf_fast&type=${type}&id=${employeeId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Set PDF source and download link
            const baseUrl = '<?= rtrim($basePath, '/') ?>';
            const pdfUrl = baseUrl + '/' + data.pdfPath + '?t=' + Date.now(); // Cache busting
            
            pdfFrame.src = pdfUrl;
            
            // Set download link
            const downloadUrl = baseUrl + '/download.php?file=' + encodeURIComponent(data.pdfPath);
            downloadLink.href = downloadUrl;
            downloadLink.download = data.filename;
            
            // Hide loading, show PDF
            loadingEl.classList.add('hidden');
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
    if (type === 'id_card') {
        const idCardBtn = document.getElementById('idCardBtn');
        const idCardIcon = document.getElementById('idCardIcon');
        const idCardText = document.getElementById('idCardText');
        
        if (idCardBtn && idCardIcon && idCardText) {
            idCardBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            idCardBtn.onclick = function() { openPdfModal('id_card', <?= $employee['id'] ?>); return false; };
            idCardIcon.className = 'fas fa-id-card mr-2';
            idCardText.textContent = 'ID Card';
        }
    }
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
#documentPreviewModal {
    backdrop-filter: blur(5px);
}
#pdfPreviewModal {
    backdrop-filter: blur(5px);
}
</style>

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-gray-800 rounded-xl shadow-2xl px-8 py-6 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['surname']) ?></h1>
            <p class="text-lg text-blue-400"><?= htmlspecialchars($employee['user_type']) ?></p>
        </div>
        <div class="flex-shrink-0 flex flex-wrap gap-2">
             <a href="index.php?page=edit-employee&id=<?= $employee['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
            <a href="#" onclick="openPdfModal('id_card', <?= $employee['id'] ?>); return false;" id="idCardBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm">
                <i class="fas fa-id-card mr-2" id="idCardIcon"></i><span id="idCardText">ID Card</span>
            </a>
            <a href="#" onclick="openPdfModal('resume', <?= $employee['id'] ?>); return false;" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg transition text-sm">
                <i class="fas fa-file-alt mr-2"></i>Resume
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Profile Pic & Docs -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 text-center">
                 <img class="h-40 w-40 rounded-full object-cover ring-4 ring-gray-700 mx-auto" 
                         src="<?= $employee['profile_photo'] ? htmlspecialchars($basePath . '/' . ltrim($employee['profile_photo'], '/')) : 'https://via.placeholder.com/150' ?>" 
                         alt="Profile Photo">
            </div>
            <div class="bg-gray-800 rounded-xl shadow-2xl">
                 <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Uploaded Documents</h3></div>
                 <div class="border-t border-gray-700">
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

        <!-- Right Column - All Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-800 rounded-xl shadow-2xl">
                <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Personal Information</h3></div>
                <div class="border-t border-gray-700 px-4 py-5 sm:px-6">
                    <dl class="divide-y divide-gray-700">
                        <?php display_info('Full Name', $employee['first_name'] . ' ' . $employee['surname']); ?>
                        <?php display_info('Email Address', $employee['email_id']); ?>
                        <?php display_info('Mobile Number', $employee['mobile_number']); ?>
                        <?php display_info('Date of Birth', $employee['date_of_birth'], 'date'); ?>
                        <?php display_info('Gender', $employee['gender']); ?>
                        <?php display_info('Current Address', $employee['address']); ?>
                        <?php display_info('Permanent Address', $employee['permanent_address']); ?>
                        <?php display_info('ESIC Number', $employee['esic_number'] ?? null); ?>
                        <?php display_info('UAN Number', $employee['uan_number'] ?? null); ?>
                        <?php display_info('PF Number', $employee['pf_number'] ?? null); ?>
                    </dl>
                </div>
            </div>
             <div class="bg-gray-800 rounded-xl shadow-2xl">
                <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Employment & Financial Details</h3></div>
                <div class="border-t border-gray-700 px-4 py-5 sm:px-6">
                    <dl class="divide-y divide-gray-700">
                        <?php display_info('Highest Qualification', $employee['highest_qualification']); ?>
                        <?php display_info('Date of Joining', $employee['date_of_joining'], 'date'); ?>
                        <?php display_info('Shift Hours', $employee['shift_hours'] ?? null); ?>
                        <?php display_info('Aadhar Number', $employee['aadhar_number']); ?>
                        <?php display_info('PAN Number', $employee['pan_number']); ?>
                        <?php display_info('Voter ID Number', $employee['voter_id_number'] ?? null); ?>
                        <?php display_info('Passport Number', $employee['passport_number'] ?? null); ?>
                        <?php display_info('Salary', $employee['salary'], 'currency'); ?>
                        <?php display_info('Advance Salary', $employee['advance_salary'] ?? null, 'currency'); ?>
                        <?php display_info('Bank Name', $employee['bank_name'] ?? ''); ?>
                        <?php display_info('Bank Account Number', $employee['bank_account_number'] ?? ''); ?>
                        <?php display_info('IFSC Code', $employee['ifsc_code'] ?? ''); ?>
                    </dl>
                </div>
            </div>
            <div class="bg-gray-800 rounded-xl shadow-2xl">
                <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Access & Permissions</h3></div>
                <div class="border-t border-gray-700 px-4 py-5 sm:px-6">
                    <dl class="divide-y divide-gray-700">
                        <?php display_info('User Role', $employee['user_type']); ?>
                        <?php display_info('Web Access', $employee['web_access'], 'boolean'); ?>
                        <?php display_info('Mobile Access', $employee['mobile_access'], 'boolean'); ?>
                    </dl>
                </div>
            </div>
            
            <!-- Family Details Section -->
            <div class="bg-gray-800 rounded-xl shadow-2xl">
                <div class="px-4 py-5 sm:px-6"><h3 class="text-lg leading-6 font-medium text-white">Family Details</h3></div>
                <div class="border-t border-gray-700 px-4 py-5 sm:px-6">
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
                        <?php if ($ref1): ?>
                            <div class="mb-6">
                                <h4 class="text-md font-semibold text-blue-400 mb-3">Family Reference 1</h4>
                                <dl class="divide-y divide-gray-700">
                                    <?php display_info('Name', $ref1['name']); ?>
                                    <?php display_info('Relation', $ref1['relation']); ?>
                                    <?php display_info('Primary Mobile', $ref1['mobile_primary']); ?>
                                    <?php display_info('Alternate Mobile', $ref1['mobile_secondary']); ?>
                                    <?php display_info('Address', $ref1['address']); ?>
                                </dl>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Family Reference 2 (Optional) -->
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-blue-400 mb-3">Family Reference 2</h4>
                            <?php if ($ref2): ?>
                                <dl class="divide-y divide-gray-700">
                                    <?php display_info('Name', $ref2['name']); ?>
                                    <?php display_info('Relation', $ref2['relation']); ?>
                                    <?php display_info('Primary Mobile', $ref2['mobile_primary']); ?>
                                    <?php display_info('Alternate Mobile', $ref2['mobile_secondary']); ?>
                                    <?php display_info('Address', $ref2['address']); ?>
                                </dl>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-minus-circle text-gray-500 text-2xl mb-2"></i>
                                    <p class="text-gray-400">Not provided</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-gray-500 text-4xl mb-4"></i>
                            <p class="text-gray-400">No family details provided</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div> 