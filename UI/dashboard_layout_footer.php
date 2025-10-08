    </div>
    <script>
        // Global JavaScript for dashboard layout
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle user menu
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('hidden');
                });

                // Close user menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!userMenu.contains(e.target) && !userMenuButton.contains(e.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html> 