<?php
global $company_settings;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($company_settings['company_name'] ?? 'GuardSys'); ?></title>
    <?php if (!empty($company_settings['favicon_path'])): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($company_settings['favicon_path']); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://rsms.me/inter/inter.css');
        html { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center antialiased">
    <div class="max-w-md w-full bg-gray-800 rounded-xl shadow-2xl p-8">
        <div class="text-center mb-8">
            <?php if (!empty($company_settings['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars($company_settings['logo_path']); ?>" alt="Company Logo" class="mx-auto h-16 w-auto mb-4">
            <?php endif; ?>
            <h1 class="text-4xl font-bold text-white"><?php echo htmlspecialchars($company_settings['company_name'] ?? 'GuardSys'); ?></h1>
            <p class="text-gray-400">Welcome back! Please login to your account.</p>
        </div>
        
        <div id="message-area" class="mb-4"></div>

        <form id="loginForm">
            <div class="mb-4">
                <label for="email_id" class="block text-sm font-medium text-gray-400 mb-2">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-envelope text-gray-500"></i>
                    </span>
                    <input type="email" id="email_id" name="email_id" required placeholder="you@example.com" class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-400 mb-2">Password</label>
                 <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-lock text-gray-500"></i>
                    </span>
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div>
                <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-colors">
                    Login
                </button>
            </div>
        </form>
         
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email_id').value;
            const password = document.getElementById('password').value;
            const messageArea = document.getElementById('message-area');
            const submitButton = this.querySelector('button[type="submit"]');

            // Show loading state
            messageArea.innerHTML = '';
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> &nbsp; Logging In...';

            try {
                const response = await fetch('index.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email_id: email,
                        password: password
                    })
                });

                let result;
                const responseClone = response.clone(); // Clone the response

                try {
                    result = await response.json();
                } catch (e) {
                    // Handle non-JSON responses (e.g., HTML error pages for 500 errors)
                    const errorText = await responseClone.text(); // Read from the clone
                    console.error("Failed to parse JSON response. Server returned:", errorText);
                    throw new Error('A critical server error occurred. Please try again later.');
                }
                
                if (!response.ok) {
                    throw new Error(result.error || `Server responded with status ${response.status}`);
                }
                
                if (result.success) {
                    messageArea.innerHTML = `<div class="bg-green-500/20 border border-green-500 text-green-300 px-4 py-3 rounded-lg" role="alert">${result.message || 'Login successful! Redirecting...'}</div>`;
                    // Redirect after a short delay to allow user to see the message
                    setTimeout(() => {
                        window.location.href = 'index.php?page=dashboard';
                    }, 1000);
                } else {
                    // This case might be redundant if !response.ok is caught above, but good for safety
                    throw new Error(result.error || 'An unknown login error occurred.');
                }

            } catch (error) {
                messageArea.innerHTML = `<div class="bg-red-500/20 border border-red-500 text-red-300 px-4 py-3 rounded-lg" role="alert">${error.message}</div>`;
                 // Restore button state only after an error
                submitButton.disabled = false;
                submitButton.innerHTML = 'Login';
            }
            // We don't restore button state on success, as the page will redirect
        });

        // Display messages from URL params (e.g., session updated)
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const reason = params.get('reason');
            const messageArea = document.getElementById('message-area');
            if (reason === 'session_updated') {
                 messageArea.innerHTML = `<div class="bg-blue-500/20 border border-blue-500 text-blue-300 px-4 py-3 rounded-lg" role="alert">Your session has been updated. Please log in again.</div>`;
            }
        });
    </script>
</body>
</html> 