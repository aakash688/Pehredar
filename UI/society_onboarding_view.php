<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
require_once __DIR__ . '/../helpers/database.php';

// Fetch client types from database
try {
    $db = new Database();
    $client_types = $db->query("SELECT id, type_name FROM client_types ORDER BY type_name ASC")->fetchAll();
} catch (Exception $e) {
    $client_types = [];
    echo "<div class='bg-red-600 text-white p-4 rounded mb-4'>Error loading client types: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-semibold text-gray-200 mb-6">Client Onboarding</h1>

    <!-- Progress Bar -->
    <div class="w-full mb-8">
        <div class="flex items-center justify-between text-sm text-gray-400 mb-2">
            <span class="text-blue-400 font-medium">Client Details</span>
            <span class="text-gray-400">Services</span>
            <span class="text-gray-400">Client User Account</span>
        </div>
        <div class="w-full bg-gray-700 rounded-full h-2">
            <div id="progress-bar" class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: 33.33%"></div>
        </div>
    </div>
    
    <div id="message-area" class="mb-6"></div>

    <form id="onboardingForm" enctype="multipart/form-data">
        <!-- Section 1: Client Details -->
        <div class="form-section active" data-section="1">
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-semibold text-white mb-6">Client Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    
                    <!-- LEFT COLUMN -->
                    <div class="space-y-6">
                        <!-- Client Name -->
                        <div>
                            <label for="society_name" class="block text-sm font-medium text-gray-300 mb-2">Client Name*</label>
                            <input type="text" id="society_name" name="society_name" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <!-- Client Type -->
                <div>
                            <label for="client_type_id" class="block text-sm font-medium text-gray-300 mb-2">Client Type*</label>
                            <select id="client_type_id" name="client_type_id" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">Select a client type</option>
                        <?php foreach ($client_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                        <!-- City/District -->
                        <div class="grid grid-cols-2 gap-x-6">
                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-300 mb-2">City*</label>
                                <input type="text" id="city" name="city" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="district" class="block text-sm font-medium text-gray-300 mb-2">District*</label>
                                <input type="text" id="district" name="district" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                        <!-- Latitude/Longitude -->
                        <div class="grid grid-cols-2 gap-x-6">
                            <div>
                                <label for="latitude" class="block text-sm font-medium text-gray-300 mb-2">Latitude*</label>
                                <input type="text" id="latitude" name="latitude" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="longitude" class="block text-sm font-medium text-gray-300 mb-2">Longitude*</label>
                                <input type="text" id="longitude" name="longitude" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                        <!-- Onboarding/Contract Dates -->
                         <div class="grid grid-cols-2 gap-x-6">
                            <div>
                                <label for="onboarding_date" class="block text-sm font-medium text-gray-300 mb-2">Onboarding Date*</label>
                                <input type="date" id="onboarding_date" name="onboarding_date" required class="w-full bg-gray-700 border-gray-600 text-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="contract_expiry_date" class="block text-sm font-medium text-gray-300 mb-2">Contract Expiry</label>
                                <input type="date" id="contract_expiry_date" name="contract_expiry_date" class="w-full bg-gray-700 border-gray-600 text-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="space-y-6">
                        <!-- Street Address -->
                        <div>
                            <label for="street_address" class="block text-sm font-medium text-gray-300 mb-2">Street Address*</label>
                            <textarea id="street_address" name="street_address" required rows="4" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>
                        <!-- State/Pin Code -->
                        <div class="grid grid-cols-2 gap-x-6">
                            <div>
                                <label for="state" class="block text-sm font-medium text-gray-300 mb-2">State*</label>
                                <input type="text" id="state" name="state" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="pin_code" class="block text-sm font-medium text-gray-300 mb-2">Pin Code*</label>
                                <input type="text" id="pin_code" name="pin_code" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                        <!-- GST Number -->
                        <div>
                            <label for="gst_number" class="block text-sm font-medium text-gray-300 mb-2">GST Number</label>
                            <input type="text" id="gst_number" name="gst_number" maxlength="50" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <!-- Fetch Location Button -->
                         <div class="flex items-end">
                            <button type="button" id="fetch-location-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg transition flex items-center justify-center text-sm">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Fetch Location
                            </button>
                        </div>
                         <!-- Compliance Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Compliance Status</label>
                            <div class="flex items-center space-x-6 h-10">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="compliance_status" value="1" class="form-radio h-4 w-4 text-blue-600 bg-gray-700 border-gray-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-300">Compliant</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="compliance_status" value="0" checked class="form-radio h-4 w-4 text-blue-600 bg-gray-700 border-gray-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-300">Non-Compliant</span>
                                </label>
                    </div>
                </div>
                         <!-- QR Code -->
                        <div>
                             <label for="qr_code" class="block text-sm font-medium text-gray-300 mb-2">QR Code</label>
                             <div class="flex items-center space-x-3">
                                <input type="text" id="qr_code" name="qr_code" class="w-full bg-gray-900 border-gray-600 text-gray-400 rounded-lg px-3 py-2.5 text-sm" readonly>
                                <button type="button" id="generate-qr-btn" class="bg-teal-600 hover:bg-teal-700 text-white font-medium py-2.5 px-4 rounded-lg transition h-10 flex items-center justify-center text-sm flex-shrink-0">
                                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h6v6H4V4zm8 0h6v6h-6V4zM4 14h6v6H4v-6zm8 2h2v2h-2v-2zm4-2h-2v2h2v-2zm-2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm2-4h2v2h-2v-2zm-4 2h2v2h-2v-2zm2 2h2v2h-2v-2zm-2-4h2v2h-2v-2z" fill="currentColor"></svg>
                                    Generate
                        </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="flex justify-end mt-6">
                <button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2.5 rounded-lg transition flex items-center text-sm">
                    Next 
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Section 2: Service Requirements -->
        <div class="form-section" data-section="2">
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-semibold text-white mb-6">Service Requirements</h3>
                <div class="bg-gray-900/50 rounded-lg">
                <!-- Header Row -->
                <div class="grid grid-cols-3 gap-4 px-4 py-3 font-semibold text-gray-300 border-b border-gray-700">
                    <span class="text-sm">Service</span>
                    <span class="text-sm text-center">Count</span>
                    <span class="text-sm text-center">Client Rate</span>
                </div>
                 <!-- Service Rows -->
                    <div class="space-y-3 p-4">
                <?php
                $services = [
                        'guards' => 'Guards', 'dogs' => 'Dogs', 'armed_guards' => 'Armed Guards', 'housekeeping' => 'Housekeeping', 
                        'bouncers' => 'Bouncers', 'site_supervisors' => 'Site Supervisors', 'supervisors' => 'Supervisors'
                ];
                foreach ($services as $field => $label):
                    $client_rate_field_name = ($field === 'armed_guards' ? 'armed' : rtrim($field, 's')) . '_client_rate';
                ?>
                <div class="grid grid-cols-3 gap-4 items-center">
                        <label class="font-medium text-gray-300 text-sm"><?= $label ?></label>
                        <input type="number" name="<?= $field ?>" value="0" class="w-full text-center bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <input type="number" step="0.01" name="<?= $client_rate_field_name ?>" value="0.00" class="w-full text-center bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Service Charges Section -->
            <div class="bg-gray-900/50 rounded-lg mt-6 p-4">
                <h4 class="text-lg font-semibold text-white mb-4">Service Charges</h4>
                <div class="space-y-4">
                    <!-- Service Charges Toggle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Service Charges Applicable?</label>
                        <div class="flex items-center space-x-6 h-10">
                            <label class="inline-flex items-center">
                                <input type="radio" name="service_charges_enabled" value="0" checked class="form-radio h-4 w-4 text-blue-600 bg-gray-700 border-gray-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">No</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="service_charges_enabled" value="1" class="form-radio h-4 w-4 text-blue-600 bg-gray-700 border-gray-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Yes</span>
                            </label>
                        </div>
                    </div>
                    <!-- Service Charge Percentage -->
                    <div id="service_charge_percentage_group" style="display:none;">
                        <label for="service_charges_percentage" class="block text-sm font-medium text-gray-300 mb-2">Service Charge Percentage (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="service_charges_percentage" id="service_charges_percentage" class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="e.g., 10.00">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between mt-8">
                    <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-medium px-5 py-2.5 rounded-lg transition flex items-center text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Previous
                    </button>
                    <button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2.5 rounded-lg transition flex items-center text-sm">
                        Next 
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 3: Primary Client User -->
        <div class="form-section" data-section="3">
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-semibold text-white mb-6">Primary Client User Account</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Full Name*</label>
                        <input type="text" name="client_name" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Phone Number*</label>
                        <input type="tel" name="client_phone" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Email Address*</label>
                        <input type="email" name="client_email" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Username*</label>
                        <input type="text" name="client_username" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Password*</label>
                        <input type="password" name="client_password" required class="w-full bg-gray-700 border-gray-600 text-white rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
            </div>
            <div class="flex justify-between mt-8">
                    <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-medium px-5 py-2.5 rounded-lg transition flex items-center text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Previous
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium px-5 py-2.5 rounded-lg transition flex items-center text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Onboard Client
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nextButtons = document.querySelectorAll('.next-section-btn');
    const prevButtons = document.querySelectorAll('.prev-section-btn');
    const formSections = document.querySelectorAll('.form-section');
    const progressBar = document.getElementById('progress-bar');
    let currentSection = 1;

    function updateProgressBar(sectionNumber) {
        const progress = ((sectionNumber - 1) / (formSections.length - 1)) * 100;
        progressBar.style.width = `${progress}%`;
        
        // Update active states for progress indicators
        const progressTexts = document.querySelectorAll('.progress-text');
        progressTexts.forEach((text, index) => {
            if (index + 1 === sectionNumber) {
                text.classList.add('text-blue-400', 'font-medium');
            } else if (index + 1 < sectionNumber) {
                text.classList.add('text-green-400');
                text.classList.remove('text-gray-400');
            } else {
                text.classList.remove('text-blue-400', 'text-green-400', 'font-medium');
                text.classList.add('text-gray-400');
            }
        });
    }

    function goToSection(sectionNumber) {
        if (sectionNumber < 1 || sectionNumber > formSections.length) {
            return;
        }

        formSections.forEach(section => {
            section.classList.remove('active');
        });

        const newSection = document.querySelector(`.form-section[data-section="${sectionNumber}"]`);
        if (newSection) {
            newSection.classList.add('active');
            currentSection = sectionNumber;
            updateProgressBar(sectionNumber);
            
            // Scroll to top of the form
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    }

    nextButtons.forEach(button => {
        button.addEventListener('click', () => {
            goToSection(currentSection + 1);
        });
    });

    prevButtons.forEach(button => {
        button.addEventListener('click', () => {
            goToSection(currentSection - 1);
        });
    });
    
    // QR Code Generation
    const generateQrBtn = document.getElementById('generate-qr-btn');
    if(generateQrBtn) {
        generateQrBtn.addEventListener('click', () => {
            const societyName = document.querySelector('input[name="society_name"]').value;
            if (!societyName) {
                alert('Please enter a society name first.');
                return;
            }
            
            // Generate a random 10-character alphanumeric string
            const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            let result = 'YL-';
            for (let i = 0; i < 10; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('qr_code').value = result;
        });
    }

    // Geolocation Fetch
    const fetchLocationBtn = document.getElementById('fetch-location-btn');
    if(fetchLocationBtn){
        fetchLocationBtn.addEventListener('click', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                    document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
                }, error => {
                    alert(`Error fetching location: ${error.message}`);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        });
    }

    // Service Charges Toggle
    document.querySelectorAll('input[name="service_charges_enabled"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const percentageGroup = document.getElementById('service_charge_percentage_group');
            const percentageInput = document.getElementById('service_charges_percentage');
            
            if (this.value === '1') {
                percentageGroup.style.display = 'block';
                percentageInput.required = true;
            } else {
                percentageGroup.style.display = 'none';
                percentageInput.required = false;
                percentageInput.value = ''; // Clear the value when disabled
            }
        });
    });

    const form = document.getElementById('onboardingForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const messageArea = document.getElementById('message-area');
        const clientTypeSelect = document.getElementById('client_type_id');
        
        // Validate client type
        if (!clientTypeSelect.value) {
            messageArea.innerHTML = `
                <div class="p-4 bg-red-500 text-white rounded-lg">
                    <strong>Error:</strong> Please select a client type. This field is mandatory.
                </div>
            `;
            clientTypeSelect.classList.add('border-red-500', 'ring-red-500');
            clientTypeSelect.focus();
            return;
        }
        
        const formData = new FormData(form);
        
        // Optional: Add a loading indicator
        messageArea.innerHTML = '<div class="p-4 bg-blue-500 text-white rounded-lg">Submitting, please wait...</div>';
        
        fetch('actions/society_controller.php?action=onboard_society', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageArea.innerHTML = `<div class="p-4 bg-green-500 text-white rounded-lg">${data.message}</div>`;
                form.reset();
                goToSection(1); // Go back to the first section
                
                // Reset client type select styling
                clientTypeSelect.classList.remove('border-red-500', 'ring-red-500');
            } else {
                messageArea.innerHTML = `<div class="p-4 bg-red-500 text-white rounded-lg">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            messageArea.innerHTML = `<div class="p-4 bg-red-500 text-white rounded-lg">A network error occurred. Please try again.</div>`;
            console.error('Submission error:', error);
        });
    });
});
</script>

 