<?php
// UI/mobile_app_settings_view.php
// Mobile App Settings View - follows the same theme as other pages
global $page_data;

// Get mobile app configuration
require_once __DIR__ . '/../actions/mobile_app_settings_controller.php';
$controller = new MobileAppSettingsController();
$configData = $controller->getMobileAppConfig();
$mobileConfig = $configData['success'] ? $configData['data'] : null;
?>

<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6 flex items-center">
        <i class="fas fa-mobile-alt mr-3"></i>Mobile App Settings
        <?php if (isset($configData['sync_status'])): ?>
            <span class="ml-4 text-sm font-normal">
                <?php if ($configData['sync_status'] === 'synced'): ?>
                    <span class="bg-green-600 text-white px-3 py-1 rounded-full text-xs">
                        <i class="fas fa-sync-alt mr-1"></i>Synced with Remote Server
                    </span>
                <?php elseif ($configData['sync_status'] === 'offline'): ?>
                    <span class="bg-yellow-600 text-white px-3 py-1 rounded-full text-xs">
                        <i class="fas fa-wifi mr-1"></i>Offline Mode
                    </span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </h1>
    
    <?php if (isset($configData['sync_message']) && $configData['sync_status'] === 'offline'): ?>
        <div class="bg-yellow-900 border border-yellow-600 text-yellow-200 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="font-medium">Remote Server Unavailable</span>
            </div>
            <p class="text-sm mt-1"><?php echo htmlspecialchars($configData['sync_message']); ?></p>
        </div>
    <?php endif; ?>

    <form id="mobile-app-form" method="POST" enctype="multipart/form-data">
        <div class="space-y-6">
            <!-- Mobile App Configuration Section -->
            <div class="bg-gray-900 p-6 rounded-lg">
                <h3 class="text-lg font-medium text-gray-300 mb-4 flex items-center">
                    <i class="fas fa-cog mr-2"></i>Mobile Application Configuration
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="Clientid" class="block text-sm font-medium text-gray-300 mb-2">
                            Client ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="Clientid" name="Clientid" 
                               class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                               placeholder="Enter Client ID" 
                               value="<?php echo htmlspecialchars($mobileConfig['Clientid'] ?? ''); ?>" 
                               required>
                        <p class="text-xs text-gray-400 mt-1">Unique identifier for your mobile application (3-10 characters, alphanumeric and underscore only)</p>
                        
                        <!-- Validation Error Display -->
                        <div id="clientIdError" class="hidden mt-2 p-3 bg-red-900 border border-red-600 text-red-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="font-medium">Client ID Validation Error</span>
                            </div>
                            <p id="clientIdErrorMessage" class="text-sm mt-1"></p>
                            <div id="clientIdSuggestion" class="text-sm mt-2 text-yellow-200 hidden">
                                <strong>Suggestion:</strong> <span id="clientIdSuggestionText"></span>
                            </div>
                            <div id="clientIdCriteria" class="text-xs mt-2 text-gray-300 hidden">
                                <strong>Requirements:</strong>
                                <ul id="clientIdCriteriaList" class="mt-1 ml-4 list-disc"></ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="APIKey" class="block text-sm font-medium text-gray-300 mb-2">
                            API Key <span class="text-yellow-500">(Auto-generated)</span>
                        </label>
                        <input type="text" id="APIKey" name="APIKey" 
                               class="bg-gray-600 text-gray-300 w-full p-3 rounded-lg cursor-not-allowed" 
                               placeholder="API Key will be auto-generated during installation" 
                               value="<?php echo htmlspecialchars($mobileConfig['APIKey'] ?? ''); ?>" 
                               readonly>
                        <p class="text-xs text-gray-400 mt-1">API key is automatically generated during installation and cannot be edited</p>
                    </div>
                </div>
                
                <div class="form-group mt-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        App Logo <span class="text-red-500">*</span>
                    </label>
                    
                    <!-- Upload/URL Toggle -->
                    <div class="flex space-x-4 mb-4">
                        <button type="button" id="uploadToggle" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-upload mr-2"></i>Upload Image
                        </button>
                        <button type="button" id="urlToggle" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-link mr-2"></i>Enter URL
                        </button>
                    </div>
                    
                    <!-- Upload Section -->
                    <div id="uploadSection" class="mb-4">
                        <input type="file" id="logo_upload" name="logo_upload" 
                               accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml" 
                               class="bg-gray-700 text-white w-full p-3 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                        <p class="text-xs text-gray-400 mt-1">Upload PNG, JPG, GIF, or SVG image (max 5MB)</p>
                    </div>
                    
                    <!-- URL Section -->
                    <div id="urlSection" class="mb-4" style="display: none;">
                        <input type="url" id="App_logo_url" name="App_logo_url" 
                               class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                               placeholder="Enter logo URL (e.g., assets/images/logo.png or https://example.com/logo.png)">
                        <p class="text-xs text-gray-400 mt-1">Enter direct URL to your mobile app logo image</p>
                    </div>
                    
                    <!-- Logo Preview -->
                    <div class="mt-4 p-4 bg-gray-800 rounded-lg border-2 border-dashed border-gray-600" id="logoPreview">
                        <div class="text-center text-gray-400" id="logoPlaceholder">
                            <i class="fas fa-image text-4xl mb-2"></i>
                            <p>Logo preview will appear here</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-700">
                <div class="flex space-x-4">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Save Configuration
                    </button>
                    
                    <button type="button" id="syncBtn" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Sync with Remote
                    </button>
                    
                    <button type="button" id="resetBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fas fa-undo mr-2"></i>
                        Reset to Default
                    </button>
                </div>
                
                <div class="text-sm text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Changes are saved automatically
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mobile-app-form');
    const resetBtn = document.getElementById('resetBtn');
    const syncBtn = document.getElementById('syncBtn');
    const logoPreview = document.getElementById('logoPreview');
    const logoPlaceholder = document.getElementById('logoPlaceholder');
    const logoUrlInput = document.getElementById('App_logo_url');
    const logoUploadInput = document.getElementById('logo_upload');
    const uploadSection = document.getElementById('uploadSection');
    const urlSection = document.getElementById('urlSection');
    const uploadToggle = document.getElementById('uploadToggle');
    const urlToggle = document.getElementById('urlToggle');
    
    let currentMode = 'upload'; // 'upload' or 'url'
    
    // Toggle between upload and URL modes
    uploadToggle.addEventListener('click', function() {
        currentMode = 'upload';
        uploadSection.style.display = 'block';
        urlSection.style.display = 'none';
        uploadToggle.className = 'bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
        urlToggle.className = 'bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
        
        // Clear URL field when switching to upload mode
        logoUrlInput.value = '';
        updateLogoPreview();
    });
    
    urlToggle.addEventListener('click', function() {
        currentMode = 'url';
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
        uploadToggle.className = 'bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
        urlToggle.className = 'bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
        
        // Clear file input when switching to URL mode
        logoUploadInput.value = '';
        updateLogoPreview();
    });
    
    // Show logo preview
    function updateLogoPreview() {
        let logoUrl = '';
        
        if (currentMode === 'url') {
            logoUrl = logoUrlInput.value.trim();
        } else if (currentMode === 'upload' && logoUploadInput.files.length > 0) {
            logoUrl = URL.createObjectURL(logoUploadInput.files[0]);
        }
        
        if (logoUrl) {
            logoPreview.innerHTML = `
                <div class="text-center">
                    <img src="${logoUrl}" alt="Logo Preview" 
                         class="max-w-48 max-h-48 mx-auto rounded-lg border border-gray-600"
                         onerror="this.parentElement.innerHTML='<div class=\\'text-center text-red-400\\'><i class=\\'fas fa-exclamation-triangle text-2xl mb-2\\'></i><p>Invalid image</p></div>'">
                </div>
            `;
        } else {
            logoPreview.innerHTML = `
                <div class="text-center text-gray-400" id="logoPlaceholder">
                    <i class="fas fa-image text-4xl mb-2"></i>
                    <p>Logo preview will appear here</p>
                </div>
            `;
        }
    }
    
    // Update logo preview on input change
    logoUrlInput.addEventListener('input', updateLogoPreview);
    logoUploadInput.addEventListener('change', updateLogoPreview);
    
    // Initialize with current logo if exists
    const currentLogoUrl = '<?php echo htmlspecialchars($mobileConfig['App_logo_url'] ?? ''); ?>';
    if (currentLogoUrl) {
        logoUrlInput.value = currentLogoUrl;
        currentMode = 'url';
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
        uploadToggle.className = 'bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
        urlToggle.className = 'bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium';
    }
    
    // Initial logo preview
    updateLogoPreview();
    
    // Show toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-600' : 
            type === 'error' ? 'bg-red-600' : 
            'bg-blue-600'
        }`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>
                ${message}
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        console.log('Form submission started, mode:', currentMode);
        
        const formData = new FormData(form);
        formData.append('action', 'update_config');
        
        // Add current mode to form data
        formData.append('logo_mode', currentMode);
        
        // Debug form data
        console.log('Form data contents:');
        for (let [key, value] of formData.entries()) {
            if (value instanceof File) {
                console.log(key + ':', value.name, '(' + value.size + ' bytes, ' + value.type + ')');
            } else {
                console.log(key + ':', value);
            }
        }
        
        // Check if file is present in upload mode
        if (currentMode === 'upload') {
            const fileInput = document.getElementById('logo_upload');
            if (fileInput.files.length === 0) {
                showToast('Please select an image file to upload', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            console.log('File selected:', fileInput.files[0].name);
            
            // Clear the URL field when in upload mode to avoid conflicts
            formData.delete('App_logo_url');
        } else if (currentMode === 'url') {
            // Clear the file input when in URL mode to avoid conflicts
            formData.delete('logo_upload');
            
            // Validate URL field
            const urlValue = formData.get('App_logo_url');
            if (!urlValue || urlValue.trim() === '') {
                showToast('Please enter a valid logo URL', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        submitBtn.disabled = true;
        
        fetch('actions/mobile_app_settings_controller.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showToast(data.message, 'success');
                // Update logo preview with new URL if provided
                if (data.logo_url) {
                    if (currentMode === 'url') {
                        logoUrlInput.value = data.logo_url;
                    }
                    updateLogoPreview();
                }
                
                // Clear form fields after successful submission
                if (currentMode === 'upload') {
                    logoUploadInput.value = '';
                } else if (currentMode === 'url') {
                    // Keep the URL value as it was submitted
                }
            } else {
                // Handle validation errors
                if (data.validation_error && data.field === 'Clientid') {
                    showClientIdValidationError(data);
                } else {
                    let errorMessage = data.message || 'An error occurred';
                    if (data.errors && data.errors.length > 0) {
                        errorMessage = data.errors.join('<br>');
                    }
                    showToast(errorMessage, 'error');
                }
                console.error('Submission error:', data);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showToast('Network error: ' + error.message, 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Sync with remote server
    syncBtn.addEventListener('click', function() {
        const originalText = syncBtn.innerHTML;
        syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...';
        syncBtn.disabled = true;
        
        fetch('actions/mobile_app_settings_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_config'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.source === 'remote') {
                    showToast('Successfully synced with remote server', 'success');
                    
                    // Update form fields with remote data
                    if (data.data) {
                        document.getElementById('Clientid').value = data.data.Clientid || '';
                        document.getElementById('APIKey').value = data.data.APIKey || '';
                        document.getElementById('App_logo_url').value = data.data.App_logo_url || '';
                        updateLogoPreview();
                    }
                } else {
                    showToast('Using local data - remote server unavailable', 'info');
                }
            } else {
                showToast('Sync failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            showToast('Sync failed: ' + error.message, 'error');
        })
        .finally(() => {
            syncBtn.innerHTML = originalText;
            syncBtn.disabled = false;
        });
    });
    
    // Reset to default
    resetBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to reset to default values? (API Key will remain unchanged)')) {
            document.getElementById('Clientid').value = 'default_client';
            // API Key is readonly and should not be reset
            document.getElementById('App_logo_url').value = 'assets/images/mobile-app-logo.png';
            updateLogoPreview();
            showToast('Form reset to default values (API Key preserved)', 'info');
        }
    });
    
    // Show Client ID validation error
    function showClientIdValidationError(data) {
        const errorDiv = document.getElementById('clientIdError');
        const messageEl = document.getElementById('clientIdErrorMessage');
        const suggestionEl = document.getElementById('clientIdSuggestion');
        const suggestionTextEl = document.getElementById('clientIdSuggestionText');
        const criteriaEl = document.getElementById('clientIdCriteria');
        const criteriaListEl = document.getElementById('clientIdCriteriaList');
        
        // Show error message
        messageEl.textContent = data.message;
        
        // Show suggestion if available
        if (data.suggestion) {
            suggestionTextEl.textContent = data.suggestion;
            suggestionEl.classList.remove('hidden');
        } else {
            suggestionEl.classList.add('hidden');
        }
        
        // Show criteria if available
        if (data.criteria) {
            criteriaListEl.innerHTML = '';
            if (data.criteria.max_length) {
                criteriaListEl.innerHTML += `<li>Maximum length: ${data.criteria.max_length} characters</li>`;
            }
            if (data.criteria.min_length) {
                criteriaListEl.innerHTML += `<li>Minimum length: ${data.criteria.min_length} characters</li>`;
            }
            if (data.criteria.allowed_characters) {
                criteriaListEl.innerHTML += `<li>Allowed characters: ${data.criteria.allowed_characters}</li>`;
            }
            if (data.criteria.format) {
                criteriaListEl.innerHTML += `<li>Format: ${data.criteria.format}</li>`;
            }
            if (data.criteria.reserved_prefixes) {
                criteriaListEl.innerHTML += `<li>Cannot start with: ${data.criteria.reserved_prefixes.join(', ')}</li>`;
            }
            criteriaEl.classList.remove('hidden');
        } else {
            criteriaEl.classList.add('hidden');
        }
        
        // Show the error div
        errorDiv.classList.remove('hidden');
        
        // Focus on the Client ID field
        document.getElementById('Clientid').focus();
        
        // Hide error when user starts typing
        const clientIdInput = document.getElementById('Clientid');
        clientIdInput.addEventListener('input', function() {
            errorDiv.classList.add('hidden');
        }, { once: true });
    }
    
    // Client ID format validation on input
    const clientIdInput = document.getElementById('Clientid');
    clientIdInput.addEventListener('input', function() {
        const value = this.value;
        
        // Basic format validation
        if (value.length > 10) {
            this.style.borderColor = '#ef4444';
            this.title = 'Client ID cannot exceed 10 characters';
        } else if (value.length > 0 && value.length < 3) {
            this.style.borderColor = '#f59e0b';
            this.title = 'Client ID must be at least 3 characters';
        } else if (value.length > 0 && !/^[A-Za-z0-9_]+$/.test(value)) {
            this.style.borderColor = '#ef4444';
            this.title = 'Client ID can only contain letters, numbers, and underscores';
        } else if (value.length > 0 && /^(CLI_|INST_|API_)/.test(value)) {
            this.style.borderColor = '#ef4444';
            this.title = 'Client ID cannot start with reserved prefixes (CLI_, INST_, API_)';
        } else {
            this.style.borderColor = '';
            this.title = '';
        }
    });
});
</script>
