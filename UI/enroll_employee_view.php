<!-- 
This component uses CSS classes that should be migrated to main.css.
The following Tailwind classes might need custom equivalents:
- bg-gray-800/bg-gray-900: Use .card, .card-dark, or .surface-dark from main.css
- rounded-lg: Use .rounded from main.css
- text-white/text-gray-400: Use .text-primary/.text-muted from main.css
- hover:bg-gray-700: Use .hover-highlight from main.css
-->
<?php
$user_schema = include 'schema/users.php';
$user_type_enums = [];
if (preg_match("/ENUM\((.*?)\)/", $user_schema['users']['columns']['user_type'], $matches)) {
    $user_type_enums = array_map(fn($item) => trim($item, " '"), explode(',', $matches[1]));
}
?>
<h1 class="text-3xl font-bold mb-6 text-white">Enroll New Employee</h1>

<div class="bg-gray-800 rounded-xl shadow-2xl p-4 sm:p-6 lg:p-8">
    <!-- Progress Bar -->
    <div class="w-full mb-8">
        <div class="flex items-center justify-between text-xs text-gray-400">
            <span>Personal</span>
            <span>Documents</span>
            <span>Employment</span>
            <span>Family</span>
            <span>Account</span>
        </div>
        <div class="relative pt-2">
            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-700">
                <div id="progress-bar" style="width: 25%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 transition-all duration-500"></div>
            </div>
        </div>
    </div>
    
    <div id="message-area" class="mb-4"></div>

    <form id="enrollmentForm" enctype="multipart/form-data">
        <!-- Section 1: Personal Information -->
        <div class="form-section active" data-section="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-gray-400">First Name*</label><input type="text" name="first_name" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                <div><label class="text-gray-400">Surname*</label><input type="text" name="surname" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                <div><label class="text-gray-400">Date of Birth*</label><input type="date" name="date_of_birth" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none text-gray-400"></div>
                <div><label class="text-gray-400">Gender*</label><select name="gender" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"><option>Male</option><option>Female</option></select></div>
                <div><label class="text-gray-400">Mobile Number*</label><input type="tel" name="mobile_number" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                <div><label class="text-gray-400">Email ID*</label><input type="email" name="email_id" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                <div class="md:col-span-2"><label class="text-gray-400">Current Address*</label><textarea name="address" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></textarea></div>
                <!-- Address Section -->
                <div class="md:col-span-2 flex items-center">
                    <input type="checkbox" id="same_as_current" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="same_as_current" class="ml-2 block text-sm text-gray-400">Permanent address is the same as current</label>
                </div>
                <div class="md:col-span-2"><label class="text-gray-400">Permanent Address*</label><textarea name="permanent_address" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></textarea></div>
            </div>
            <div class="flex justify-end mt-6"><button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition">Next <i class="fas fa-arrow-right ml-2"></i></button></div>
        </div>

        <!-- Section 2: Identification & Documents -->
        <div class="form-section" data-section="2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-gray-400">Aadhar Number*</label><input type="text" name="aadhar_number" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">PAN Number*</label><input type="text" name="pan_number" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">ESIC Number</label><input type="text" name="esic_number" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">UAN Number</label><input type="text" name="uan_number" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div class="md:col-span-2"><label class="text-gray-400">PF Number</label><input type="text" name="pf_number" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">Voter ID Number</label><input type="text" name="voter_id_number" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">Passport Number</label><input type="text" name="passport_number" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div class="md:col-span-2"><label class="text-gray-400">Highest Qualification*</label><input type="text" name="highest_qualification" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                
                <!-- Upload Documents Section -->
                <div class="md:col-span-2 mt-4">
                    <h3 class="text-lg font-semibold mb-4 text-gray-300">Upload Documents</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php
                        $docs = [
                            'profile_photo' => ['icon' => 'fa-camera', 'label' => 'Profile Photo'],
                            'aadhar_card_scan' => ['icon' => 'fa-id-card', 'label' => 'Aadhar Card'],
                            'pan_card_scan' => ['icon' => 'fa-credit-card', 'label' => 'PAN Card'],
                            'bank_passbook_scan' => ['icon' => 'fa-passport', 'label' => 'Bank Passbook'],
                            'police_verification_document' => ['icon' => 'fa-file-alt', 'label' => 'Police Verification'],
                            'ration_card_scan' => ['icon' => 'fa-file-alt', 'label' => 'Ration Card'],
                            'light_bill_scan' => ['icon' => 'fa-file-alt', 'label' => 'Light Bill'],
                            'voter_id_scan' => ['icon' => 'fa-id-badge', 'label' => 'Voter ID Card'],
                            'passport_scan' => ['icon' => 'fa-book-open', 'label' => 'Passport']
                        ];
                        
                        foreach ($docs as $field_name => $doc_info):
                        ?>
                        <div class="drop-zone" data-field-name="<?= $field_name ?>">
                            <span class="drop-zone__prompt">
                                <i class="fas <?= $doc_info['icon'] ?> fa-2x text-gray-400 mb-2"></i><br>
                                <?= $doc_info['label'] ?>
                            </span>
                            <input type="file" name="<?= $field_name ?>" class="drop-zone__input" accept="image/*,.pdf">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="flex justify-between mt-6">
                <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-bold px-6 py-3 rounded-lg transition"><i class="fas fa-arrow-left mr-2"></i> Previous</button>
                <button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition">Next <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- Section 3: Employment & Bank -->
        <div class="form-section" data-section="3">
            <h3 class="text-xl font-semibold text-white border-b border-gray-700 pb-2 mb-4">Employment & Financials</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-gray-400">Date of Joining*</label><input type="date" name="date_of_joining" required value="<?= date('Y-m-d') ?>" class="w-full bg-gray-700 p-3 rounded mt-1 text-gray-400"></div>
                <div>
                    <label class="text-gray-400">User Type*</label>
                    <select name="user_type" required class="w-full bg-gray-700 p-3 rounded mt-1">
                        <?php foreach ($user_type_enums as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="text-gray-400">Salary*</label><input type="number" step="0.01" name="salary" required class="w-full bg-gray-700 p-3 rounded mt-1"></div>
                <div class="md:col-span-2"><label class="text-gray-400">Bank Account Number*</label><input type="text" name="bank_account_number" required class="w-full bg-gray-700 p-3 rounded mt-1"></div>
                <div><label class="text-gray-400">IFSC Code*</label><input type="text" name="ifsc_code" required class="w-full bg-gray-700 p-3 rounded mt-1"></div>
                <div><label class="text-gray-400">Bank Name*</label><input type="text" name="bank_name" required class="w-full bg-gray-700 p-3 rounded mt-1"></div>
            </div>
            <div class="flex justify-between mt-6">
                <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-bold px-6 py-3 rounded-lg transition"><i class="fas fa-arrow-left mr-2"></i> Previous</button>
                <button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition">Next <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- Section 4: Family Details -->
        <div class="form-section" data-section="4">
            <h3 class="text-xl font-semibold text-white border-b border-gray-700 pb-2 mb-4">Family Details</h3>
            
            <!-- Family Reference 1 -->
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-blue-400 mb-4">Family Reference 1 (Required)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="text-gray-400">Name*</label><input type="text" name="family_ref_1_name" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Relation*</label><input type="text" name="family_ref_1_relation" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Primary Mobile Number*</label><input type="tel" name="family_ref_1_mobile_primary" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Alternate Mobile Number</label><input type="tel" name="family_ref_1_mobile_secondary" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div class="md:col-span-2"><label class="text-gray-400">Address*</label><textarea name="family_ref_1_address" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></textarea></div>
                </div>
            </div>
            
            <!-- Family Reference 2 -->
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-blue-400 mb-4">Family Reference 2 (Optional)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="text-gray-400">Name</label><input type="text" name="family_ref_2_name" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Relation</label><input type="text" name="family_ref_2_relation" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Primary Mobile Number</label><input type="tel" name="family_ref_2_mobile_primary" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div><label class="text-gray-400">Alternate Mobile Number</label><input type="tel" name="family_ref_2_mobile_secondary" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></div>
                    <div class="md:col-span-2"><label class="text-gray-400">Address</label><textarea name="family_ref_2_address" class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600 focus:border-blue-500 outline-none"></textarea></div>
                </div>
            </div>
            
            <div class="flex justify-between mt-6">
                <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-bold px-6 py-3 rounded-lg transition"><i class="fas fa-arrow-left mr-2"></i> Previous</button>
                <button type="button" class="next-section-btn bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition">Next <i class="fas fa-arrow-right ml-2"></i></button>
            </div>
        </div>

        <!-- Section 5: Account Setup -->
        <div class="form-section" data-section="5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-gray-400">Password*</label><input type="password" name="password" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
                <div><label class="text-gray-400">Confirm Password*</label><input type="password" id="confirm_password" required class="w-full bg-gray-700 p-3 rounded mt-1 border border-gray-600"></div>
            </div>
            <div class="flex items-center mt-6"><input type="checkbox" id="web_access" name="web_access" class="h-5 w-5 rounded" checked><label for="web_access" class="ml-3 text-gray-300">Allow Web Access</label></div>
            <div class="flex items-center mt-3"><input type="checkbox" id="mobile_access" name="mobile_access" class="h-5 w-5 rounded" checked><label for="mobile_access" class="ml-3 text-gray-300">Allow Mobile Access</label></div>
            <div class="flex justify-between mt-6">
                 <button type="button" class="prev-section-btn bg-gray-600 hover:bg-gray-500 text-white font-bold px-6 py-3 rounded-lg transition"><i class="fas fa-arrow-left mr-2"></i> Previous</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-lg transition"><i class="fas fa-check-circle mr-2"></i> Submit Enrollment</button>
            </div>
        </div>
    </form>
</div>

<!-- Form Section Navigation JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('enrollmentForm');
    const sections = form.querySelectorAll('.form-section');
    const progressBar = document.getElementById('progress-bar');
    const messageArea = document.getElementById('message-area');

    // Next and Previous Section Buttons
    const nextButtons = form.querySelectorAll('.next-section-btn');
    const prevButtons = form.querySelectorAll('.prev-section-btn');

    // Validation function for each section
    function validateSection(section) {
        const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('border-red-500');
                isValid = false;
            } else {
                input.classList.remove('border-red-500');
            }
        });

        return isValid;
    }

    // Update progress bar
    function updateProgressBar(currentSection) {
        const sectionIndex = parseInt(currentSection.dataset.section);
        const progressPercentage = (sectionIndex - 1) * 20; // 5 sections = 20% each
        progressBar.style.width = `${progressPercentage}%`;
    }

    // Show specific section
    function showSection(sectionNumber) {
        sections.forEach(section => {
            section.classList.remove('active');
            if (section.dataset.section == sectionNumber) {
                section.classList.add('active');
                updateProgressBar(section);
            }
        });
    }

    // Next section button handler
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentSection = this.closest('.form-section');
            
            // Validate current section
            if (!validateSection(currentSection)) {
                messageArea.innerHTML = `
                    <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                        Please fill in all required fields.
                    </div>
                `;
                return;
            }

            // Clear any previous messages
            messageArea.innerHTML = '';

            // Move to next section
            const nextSectionNumber = parseInt(currentSection.dataset.section) + 1;
            showSection(nextSectionNumber);
        });
    });

    // Previous section button handler
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentSection = this.closest('.form-section');
            const prevSectionNumber = parseInt(currentSection.dataset.section) - 1;
            showSection(prevSectionNumber);
        });
    });

    // Address same as current checkbox
    const sameAsCurrentCheckbox = document.getElementById('same_as_current');
    const currentAddressInput = form.querySelector('textarea[name="address"]');
    const permanentAddressInput = form.querySelector('textarea[name="permanent_address"]');

    sameAsCurrentCheckbox.addEventListener('change', function() {
        if (this.checked) {
            permanentAddressInput.value = currentAddressInput.value;
            permanentAddressInput.disabled = true;
        } else {
            permanentAddressInput.disabled = false;
        }
    });
});
</script>

<!-- File Upload Preview JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize drop zones for file uploads
    const dropZones = document.querySelectorAll('.drop-zone');
    
    dropZones.forEach(zone => {
        const input = zone.querySelector('.drop-zone__input');
        const prompt = zone.querySelector('.drop-zone__prompt');
        
        // Click on zone to trigger file input
        zone.addEventListener('click', (e) => {
            // Prevent opening view link if it exists
            if (e.target.classList.contains('view-link')) return;
            
            input.click();
        });
        
        // Handle file selection
        input.addEventListener('change', () => {
            if (input.files.length) {
                updateThumbnail(zone, input.files[0]);
            }
        });
        
        // Handle drag and drop
        zone.addEventListener('dragover', e => {
            e.preventDefault();
            zone.classList.add('drop-zone--over');
        });
        
        ['dragleave', 'dragend'].forEach(type => {
            zone.addEventListener(type, () => {
                zone.classList.remove('drop-zone--over');
            });
        });
        
        zone.addEventListener('drop', e => {
            e.preventDefault();
            
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateThumbnail(zone, e.dataTransfer.files[0]);
            }
            
            zone.classList.remove('drop-zone--over');
        });
    });
    
    // Function to update thumbnail preview
    function updateThumbnail(dropZone, file) {
        // Remove any existing thumbnail
        const existingThumb = dropZone.querySelector('.drop-zone__thumb');
        if (existingThumb) {
            existingThumb.remove();
        }
        
        // Remove prompt if it exists
        const prompt = dropZone.querySelector('.drop-zone__prompt');
        if (prompt) {
            prompt.remove();
        }
        
        // Create new thumbnail element
        const thumbnailElement = document.createElement('div');
        thumbnailElement.classList.add('drop-zone__thumb');
        
        // Set the label to the file name
        thumbnailElement.dataset.label = file.name;
        
        // Show thumbnail for image files
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            
            reader.readAsDataURL(file);
            reader.onload = () => {
                thumbnailElement.style.backgroundImage = `url('${reader.result}')`;
                
                // Add view link
                const viewLink = document.createElement('a');
                viewLink.href = reader.result;
                viewLink.target = '_blank';
                viewLink.classList.add('view-link');
                viewLink.textContent = 'View Current';
                thumbnailElement.appendChild(viewLink);
                
                dropZone.appendChild(thumbnailElement);
            };
        } else if (file.type === 'application/pdf') {
            // Use PDF icon for PDF files
            thumbnailElement.style.backgroundImage = "url('./assets/pdf-icon.png')";
            
            // Add view link
            const viewLink = document.createElement('a');
            viewLink.href = URL.createObjectURL(file);
            viewLink.target = '_blank';
            viewLink.classList.add('view-link');
            viewLink.textContent = 'View Current';
            thumbnailElement.appendChild(viewLink);
            
            dropZone.appendChild(thumbnailElement);
        } else {
            // Fallback for other file types
            thumbnailElement.textContent = file.name;
            dropZone.appendChild(thumbnailElement);
        }
    }
});
</script>

<style>
/* Drop Zone Styles */
.drop-zone {
    max-width: 100%;
    height: 150px;
    padding: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    cursor: pointer;
    color: #cccccc;
    border: 2px dashed #4B5563;
    border-radius: 10px;
    background-color: #374151;
    transition: all 0.3s ease;
}

.drop-zone--over {
    border-color: #3B82F6;
    background-color: #1F2937;
}

.drop-zone__input {
    display: none;
}

.drop-zone__thumb {
    width: 100%;
    height: 100%;
    border-radius: 10px;
    overflow: hidden;
    background-color: #1F2937;
    background-size: cover;
    background-position: center;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.drop-zone__thumb::after {
    content: attr(data-label);
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 5px 0;
    color: #ffffff;
    background: rgba(0, 0, 0, 0.75);
    font-size: 14px;
    text-align: center;
}

.view-link {
    display: inline-block;
    margin-top: 10px;
    padding: 5px 10px;
    background-color: #3B82F6;
    color: white;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
}
</style>

<!-- Form Submission Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('enrollmentForm');
    const messageArea = document.getElementById('message-area');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Clear previous messages
        messageArea.innerHTML = '';

        // Validate all required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('border-red-500');
                isValid = false;
            } else {
                field.classList.remove('border-red-500');
            }
        });

        // Password validation
        const password = form.querySelector('input[name="password"]');
        const confirmPassword = form.querySelector('#confirm_password');
        if (password.value !== confirmPassword.value) {
            messageArea.innerHTML = `
                <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                    Passwords do not match.
                </div>
            `;
            return;
        }

        // Validate file uploads (optional)
        const fileInputs = form.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            if (input.files.length > 0) {
                const file = input.files[0];
                const maxSizeInBytes = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

                if (file.size > maxSizeInBytes) {
                    messageArea.innerHTML = `
                        <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                            File ${input.name} exceeds 5MB limit.
                        </div>
                    `;
                    isValid = false;
                }

                if (!allowedTypes.includes(file.type)) {
                    messageArea.innerHTML = `
                        <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                            Invalid file type for ${input.name}. Allowed: JPEG, PNG, GIF, PDF.
                        </div>
                    `;
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            return;
        }

        // Prepare form data
        const formData = new FormData(form);

        // Show loading indicator
        messageArea.innerHTML = `
            <div class="bg-blue-900 text-blue-300 p-4 rounded-lg flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing your request...
            </div>
        `;

        // Send form data
        fetch('actions/employee_controller.php?action=enroll_employee', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Raw Response:', response);
            console.log('Response Status:', response.status);
            console.log('Response Headers:', Object.fromEntries(response.headers.entries()));
            
            // Check if response is OK
            if (!response.ok) {
                // Try to parse error response
                return response.text().then(text => {
                    console.error('Error response text:', text);
                    throw new Error(`HTTP error! status: ${response.status}, text: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Full Response Data:', data);
            console.log('Employee ID:', data.employee_id);
            
            if (data.success) {
                messageArea.innerHTML = `
                    <div class="bg-green-900 text-green-300 p-4 rounded-lg">
                        ${data.message || 'Employee enrolled successfully!'}
                    </div>
                `;
                
                // Redirect to view employee page with the new employee ID
                setTimeout(() => {
                    // Ensure we use the actual employee ID from the server response
                    console.log('Redirecting to employee ID:', data.employee_id);
                    window.location.href = `index.php?page=view-employee&id=${data.employee_id}`;
                }, 2000);
            } else {
                // Server-side validation or processing error
                messageArea.innerHTML = `
                    <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                        ${data.message || 'An unexpected error occurred. Please try again.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Submission Error:', error);
            messageArea.innerHTML = `
                <div class="bg-red-900 text-red-300 p-4 rounded-lg">
                    An unexpected error occurred. Please check your network connection and try again.
                    Error details: ${error.message}
                </div>
            `;
        });
    });

    // Real-time validation for required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('border-red-500');
            } else {
                this.classList.add('border-red-500');
            }
        });
    });

    // Password match validation
    const password = form.querySelector('input[name="password"]');
    const confirmPassword = form.querySelector('#confirm_password');
    
    [password, confirmPassword].forEach(field => {
        field.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.classList.add('border-red-500');
            } else {
                confirmPassword.classList.remove('border-red-500');
            }
        });
    });
});
</script>

<!-- Address Handling Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sameAsCurrentCheckbox = document.getElementById('same_as_current');
    const currentAddressInput = document.querySelector('textarea[name="address"]');
    const permanentAddressInput = document.querySelector('textarea[name="permanent_address"]');

    // Initial setup
    sameAsCurrentCheckbox.addEventListener('change', function() {
        if (this.checked) {
            // Copy current address to permanent address
            permanentAddressInput.value = currentAddressInput.value;
            permanentAddressInput.disabled = true;
        } else {
            // Clear permanent address and enable input
            permanentAddressInput.disabled = false;
            permanentAddressInput.value = '';
        }
    });

    // Sync current address changes when checkbox is checked
    currentAddressInput.addEventListener('input', function() {
        if (sameAsCurrentCheckbox.checked) {
            permanentAddressInput.value = this.value;
        }
    });
});
</script>


 