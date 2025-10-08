<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section { display: none; }
        .form-section.active { display: block; }
        .progress-step.active { background-color: #3b82f6; color: white; }
        .progress-step.completed { background-color: #10b981; color: white; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-blue-600 py-4 px-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Employee Registration</h1>
                    <p class="text-blue-100">Please fill in all required fields</p>
                </div>
                <a href="index.php?page=login" class="text-sm text-white hover:underline">Already have an account? Login</a>
            </div>
            <div class="flex justify-between px-6 py-4 border-b">
                <div class="progress-step active w-8 h-8 rounded-full flex items-center justify-center bg-blue-500 text-white font-bold" data-step="1">1</div>
                <div class="progress-step w-8 h-8 rounded-full flex items-center justify-center bg-gray-200 text-gray-600 font-bold" data-step="2">2</div>
                <div class="progress-step w-8 h-8 rounded-full flex items-center justify-center bg-gray-200 text-gray-600 font-bold" data-step="3">3</div>
                <div class="progress-step w-8 h-8 rounded-full flex items-center justify-center bg-gray-200 text-gray-600 font-bold" data-step="4">4</div>
            </div>
            <div class="p-6">
                <div id="message-area"></div>
                <form id="registrationForm">
                    <!-- Section 1: Personal Information -->
                    <div class="form-section active" data-section="1">
                        <h2 class="text-xl font-semibold mb-4 text-gray-800">Personal Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name*</label>
                                <input type="text" id="first_name" name="first_name" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="surname" class="block text-sm font-medium text-gray-700 mb-1">Surname*</label>
                                <input type="text" id="surname" name="surname" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth*</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender*</label>
                                <select id="gender" name="gender" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number*</label>
                                <input type="tel" id="mobile_number" name="mobile_number" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="email_id" class="block text-sm font-medium text-gray-700 mb-1">Email ID*</label>
                                <input type="email" id="email_id" name="email_id" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end mt-6">
                            <button type="button" class="next-section-btn px-6 py-2 bg-blue-600 text-white rounded-md">Next</button>
                        </div>
                    </div>
                    <!-- Section 2: Identification -->
                    <div class="form-section" data-section="2">
                        <h2 class="text-xl font-semibold mb-4 text-gray-800">Identification</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="aadhar_number" class="block text-sm font-medium text-gray-700 mb-1">Aadhar Number*</label>
                                <input type="text" id="aadhar_number" name="aadhar_number" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="pan_number" class="block text-sm font-medium text-gray-700 mb-1">PAN Number*</label>
                                <input type="text" id="pan_number" name="pan_number" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="highest_qualification" class="block text-sm font-medium text-gray-700 mb-1">Highest Qualification*</label>
                                <input type="text" id="highest_qualification" name="highest_qualification" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="flex justify-between mt-6">
                            <button type="button" class="prev-section-btn px-6 py-2 bg-gray-300 rounded-md">Previous</button>
                            <button type="button" class="next-section-btn px-6 py-2 bg-blue-600 text-white rounded-md">Next</button>
                        </div>
                    </div>
                    <!-- Section 3: Employment Details -->
                    <div class="form-section" data-section="3">
                        <h2 class="text-xl font-semibold mb-4">Employment Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                <label for="date_of_joining" class="block text-sm font-medium text-gray-700">Date of Joining*</label>
                                <input type="date" id="date_of_joining" name="date_of_joining" required class="w-full px-4 py-2 border rounded-md">
                            </div>
                            <div>
                                <label for="user_type" class="block text-sm font-medium text-gray-700">User Type*</label>
                                <select id="user_type" name="user_type" required class="w-full px-4 py-2 border rounded-md">
                                    <option value="Guard" selected>Guard</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <label for="salary" class="block text-sm font-medium text-gray-700">Salary*</label>
                                <input type="number" step="0.01" id="salary" name="salary" required class="w-full px-4 py-2 border rounded-md">
                            </div>
                        </div>
                        <div class="flex justify-between mt-6">
                             <button type="button" class="prev-section-btn px-6 py-2 bg-gray-300 rounded-md">Previous</button>
                            <button type="button" class="next-section-btn px-6 py-2 bg-blue-600 text-white rounded-md">Next</button>
                        </div>
                    </div>
                    <!-- Section 4: Account Setup -->
                    <div class="form-section" data-section="4">
                        <h2 class="text-xl font-semibold mb-4">Account Setup</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password*</label>
                                <input type="password" id="password" name="password" required class="w-full px-4 py-2 border rounded-md">
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password*</label>
                                <input type="password" id="confirm_password" required class="w-full px-4 py-2 border rounded-md">
                            </div>
                        </div>
                        <div class="mt-6">
                            <div class="flex items-center"><input type="checkbox" id="mobile_access" name="mobile_access" class="h-4 w-4 rounded" checked><label for="mobile_access" class="ml-2">Allow Mobile Access</label></div>
                            <div class="flex items-center mt-2"><input type="checkbox" id="web_access" name="web_access" class="h-4 w-4 rounded" checked><label for="web_access" class="ml-2">Allow Web Access</label></div>
                        </div>
                        <div class="flex justify-between mt-6">
                            <button type="button" class="prev-section-btn px-6 py-2 bg-gray-300 rounded-md">Previous</button>
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md">Submit Registration</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('registrationForm');
            const sections = form.querySelectorAll('.form-section');
            const progressSteps = document.querySelectorAll('.progress-step');
            let currentSection = 1;

            const updateView = () => {
                sections.forEach(s => s.classList.remove('active'));
                form.querySelector(`[data-section="${currentSection}"]`).classList.add('active');
                progressSteps.forEach(p => {
                    p.classList.remove('active', 'completed');
                    if (parseInt(p.dataset.step) < currentSection) p.classList.add('completed');
                    if (parseInt(p.dataset.step) === currentSection) p.classList.add('active');
                });
            };

            form.addEventListener('click', e => {
                if (e.target.matches('.next-section-btn')) {
                    currentSection++;
                    updateView();
                } else if (e.target.matches('.prev-section-btn')) {
                    currentSection--;
                    updateView();
                }
            });

            form.addEventListener('submit', async e => {
                e.preventDefault();
                const messageArea = document.getElementById('message-area');
                
                if (document.getElementById('password').value !== document.getElementById('confirm_password').value) {
                    messageArea.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Passwords do not match.</div>`;
                    return;
                }

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                
                // Convert checkboxes
                data.mobile_access = formData.has('mobile_access') ? 1 : 0;
                data.web_access = formData.has('web_access') ? 1 : 0;
                data.action = 'register';

                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    messageArea.innerHTML = `<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">Registration successful! You can now <a href="index.php?page=login" class="font-bold underline">log in</a>.</div>`;
                    form.reset();
                    currentSection = 1;
                    updateView();
                } else {
                    messageArea.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">${result.error || 'An unknown error occurred.'}</div>`;
                }
            });
        });
    </script>
</body>
</html> 