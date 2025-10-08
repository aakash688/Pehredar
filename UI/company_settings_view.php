<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
// UI/company_settings_view.php
// Form to manage company branding settings
global $page_data;
$settings = $page_data['company_settings'] ?? null;
?>
<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50"></div>

<div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-6 flex items-center"><i class="fas fa-building mr-3"></i>Company Settings</h1>

    <form id="company-settings-form" action="index.php?action=save_company_settings" method="POST" enctype="multipart/form-data">
        <div class="space-y-6">
            <!-- Company Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="company_name" class="block text-sm font-medium text-gray-300 mb-2">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" id="company_name" name="company_name" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="GuardSys Pvt. Ltd." value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="gst_number" class="block text-sm font-medium text-gray-300 mb-2">GST Number</label>
                    <input type="text" id="gst_number" name="gst_number" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="22AAAAA0000A1Z5" value="<?php echo htmlspecialchars($settings['gst_number'] ?? ''); ?>">
                </div>
            </div>

            <!-- Address Section -->
            <div class="bg-gray-900 p-4 rounded-lg">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Company Address</h3>
                
                <div class="form-group mb-4">
                    <label for="street_address" class="block text-sm font-medium text-gray-300 mb-2">Street Address</label>
                    <input type="text" id="street_address" name="street_address" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="123 Business Street" value="<?php echo htmlspecialchars($settings['street_address'] ?? ''); ?>">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="form-group">
                        <label for="city" class="block text-sm font-medium text-gray-300 mb-2">City</label>
                        <input type="text" id="city" name="city" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Mumbai" value="<?php echo htmlspecialchars($settings['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="state" class="block text-sm font-medium text-gray-300 mb-2">State</label>
                        <input type="text" id="state" name="state" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Maharashtra" value="<?php echo htmlspecialchars($settings['state'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="pincode" class="block text-sm font-medium text-gray-300 mb-2">Pincode</label>
                        <input type="text" id="pincode" name="pincode" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="400001" value="<?php echo htmlspecialchars($settings['pincode'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="form-group">
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" id="email" name="email" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="contact@example.com" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone_number" class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="+91 1234567890" value="<?php echo htmlspecialchars($settings['phone_number'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="secondary_phone" class="block text-sm font-medium text-gray-300 mb-2">Secondary Phone (Optional)</label>
                    <input type="tel" id="secondary_phone" name="secondary_phone" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="+91 9876543210" value="<?php echo htmlspecialchars($settings['secondary_phone'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Branding Section -->
            <div class="bg-gray-900 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Branding</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="logo" class="block text-sm font-medium text-gray-300 mb-2">Change Logo (PNG/JPG)</label>
                        <input type="file" id="logo" name="logo" accept="image/png, image/jpeg" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                        <div id="logo-preview" class="mt-4">
                            <?php if (!empty($settings['logo_path'])): ?>
                                <?php 
                                $config = require __DIR__ . '/../config.php';
                                $baseUrl = rtrim($config['base_url'], '/');
                                ?>
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($settings['logo_path']); ?>" class="w-32 h-32 object-contain rounded-lg border border-gray-700" alt="Current Logo">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="favicon" class="block text-sm font-medium text-gray-300 mb-2">Change Favicon (ICO/PNG)</label>
                        <input type="file" id="favicon" name="favicon" accept="image/x-icon, image/png" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                        <div id="favicon-preview" class="mt-4">
                            <?php if (!empty($settings['favicon_path'])): ?>
                                <?php 
                                $config = require __DIR__ . '/../config.php';
                                $baseUrl = rtrim($config['base_url'], '/');
                                ?>
                                <img src="<?php echo $baseUrl . '/' . htmlspecialchars($settings['favicon_path']); ?>" class="w-32 h-32 object-contain rounded-lg border border-gray-700" alt="Current Favicon">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="primary_color" class="block text-sm font-medium text-gray-300 mb-2">Primary Brand Color</label>
                    <div class="flex items-center">
                        <input type="color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4f46e5'); ?>" class="w-16 h-10 p-1 border-gray-700 rounded shadow-inner bg-gray-700">
                        <span class="ml-3 text-gray-400">Click to select a color</span>
                    </div>
                </div>
            </div>

            <!-- Signature for Invoice -->
            <div class="bg-gray-900 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Authorized Signature for Invoice</h3>
                
                <div class="form-group">
                    <label for="signature_image" class="block text-sm font-medium text-gray-300 mb-2">Upload Signature Image (PNG/JPG)</label>
                    <input type="file" id="signature_image" name="signature_image" accept="image/png, image/jpeg" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-400 mt-1">Upload a clear image of the authorized signatory's signature to appear on invoices.</p>
                    <div id="signature-preview" class="mt-4">
                        <?php if (!empty($settings['signature_image'])): ?>
                            <?php 
                            $config = require __DIR__ . '/../config.php';
                            $baseUrl = rtrim($config['base_url'], '/');
                            ?>
                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($settings['signature_image']); ?>" class="h-20 object-contain rounded-lg border border-gray-700" alt="Current Signature">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Bank Details Section -->
            <div class="bg-gray-900 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Bank Details for Invoice</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="bank_name" class="block text-sm font-medium text-gray-300 mb-2">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., State Bank of India" value="<?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bank_account_number" class="block text-sm font-medium text-gray-300 mb-2">Account Number</label>
                        <input type="text" id="bank_account_number" name="bank_account_number" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., 1234567890" value="<?php echo htmlspecialchars($settings['bank_account_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                    <div class="form-group">
                        <label for="bank_ifsc_code" class="block text-sm font-medium text-gray-300 mb-2">IFSC Code</label>
                        <input type="text" id="bank_ifsc_code" name="bank_ifsc_code" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., SBIN0123456" value="<?php echo htmlspecialchars($settings['bank_ifsc_code'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bank_branch" class="block text-sm font-medium text-gray-300 mb-2">Branch Name</label>
                        <input type="text" id="bank_branch" name="bank_branch" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Main Branch" value="<?php echo htmlspecialchars($settings['bank_branch'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bank_account_type" class="block text-sm font-medium text-gray-300 mb-2">Account Type</label>
                        <select id="bank_account_type" name="bank_account_type" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Account Type</option>
                            <option value="Savings" <?php echo ($settings['bank_account_type'] ?? '') === 'Savings' ? 'selected' : ''; ?>>Savings</option>
                            <option value="Current" <?php echo ($settings['bank_account_type'] ?? '') === 'Current' ? 'selected' : ''; ?>>Current</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Configuration -->
            <div class="bg-gray-900 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Invoice Configuration</h3>
                
                <!-- Watermark Section -->
                <div class="form-group mb-6">
                    <label for="watermark_image" class="block text-sm font-medium text-gray-300 mb-2">Invoice Watermark Image (PNG/JPG)</label>
                    <input type="file" id="watermark_image" name="watermark_image" accept="image/png, image/jpeg" class="bg-gray-700 text-white w-full p-2.5 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-400 mt-1">Upload a watermark image to appear behind invoice content with 30% opacity.</p>
                    <div id="watermark-preview" class="mt-4">
                        <?php if (!empty($settings['watermark_image_path'])): ?>
                            <?php 
                            $config = require __DIR__ . '/../config.php';
                            $baseUrl = rtrim($config['base_url'], '/');
                            ?>
                            <img src="<?php echo $baseUrl . '/' . htmlspecialchars($settings['watermark_image_path']); ?>" class="w-32 h-32 object-contain rounded-lg border border-gray-700 opacity-30" alt="Current Watermark">
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Service Charges Information -->
                <div class="bg-blue-900/20 border border-blue-600/30 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h4 class="text-blue-400 font-medium text-sm">Service Charges Management</h4>
                            <p class="text-blue-200 text-xs mt-1">Service charges are now configured individually for each client in the <strong>Society Onboarding</strong> module. This provides better control and flexibility for different client requirements.</p>
                            <p class="text-blue-200 text-xs mt-2">
                                <strong>To configure service charges:</strong> Go to Clients → Clients  List → Edit Client → Service Requirements section
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Notes & Terms -->
            <div class="bg-gray-900 p-4 rounded-lg mb-6">
                <h3 class="text-lg font-medium text-gray-300 mb-4">Invoice Notes & Terms</h3>
                
                <div class="form-group mb-4">
                    <label for="invoice_notes" class="block text-sm font-medium text-gray-300 mb-2">Invoice Notes</label>
                    <textarea id="invoice_notes" name="invoice_notes" rows="3" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Thank you for your business. Payment due within 30 days."><?php echo htmlspecialchars($settings['invoice_notes'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">These notes will appear at the bottom of each invoice.</p>
                </div>
                
                <div class="form-group">
                    <label for="invoice_terms" class="block text-sm font-medium text-gray-300 mb-2">Terms & Conditions</label>
                    <textarea id="invoice_terms" name="invoice_terms" rows="4" class="bg-gray-700 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Late payments are subject to a 2% fee. This is a computer-generated invoice."><?php echo htmlspecialchars($settings['invoice_terms'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">These terms will appear at the bottom of each invoice.</p>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" id="submit-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Toast Template -->
<template id="toast-template">
    <div class="flex items-center p-4 mb-4 rounded-lg shadow-lg {{ type === 'success' ? 'bg-green-800 text-green-200' : 'bg-red-800 text-red-200' }}">
        <i class="fas {{ type === 'success' ? 'fa-check-circle' : 'fa-times-circle' }} mr-2"></i>
        <span>{{ message }}</span>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('company-settings-form');
    const toastContainer = document.getElementById('toast-container');
    const toastTemplate = document.getElementById('toast-template').innerHTML;
    
    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            
            // Submit form
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Settings saved successfully!', 'success');
            } else {
                showToast(result.message || 'Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred while saving settings', 'error');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
    
    
    // Function to show toast message
    function showToast(message, type = 'success') {
        // Remove any existing toasts
        while (toastContainer.firstChild) {
            toastContainer.removeChild(toastContainer.firstChild);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `flex items-center p-4 mb-4 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-800 text-green-200' : 'bg-red-800 text-red-200'}`;
        
        // Add icon based on type
        const icon = document.createElement('i');
        icon.className = `fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'} mr-3`;
        
        // Add message
        const messageElement = document.createElement('span');
        messageElement.textContent = message;
        
        // Build toast
        toast.appendChild(icon);
        toast.appendChild(messageElement);
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Auto-remove toast after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            
            // Remove from DOM after fade out
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000);
    }
});
</script>

 