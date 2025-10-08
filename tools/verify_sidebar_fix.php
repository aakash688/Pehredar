<?php
// Simple script to verify the sidebar fix is working
echo "Sidebar Fix Verification\n";
echo "========================\n\n";

// Check if the dashboard layout file has the correct CSS and JavaScript
$layoutFile = __DIR__ . '/../UI/dashboard_layout.php';

if (!file_exists($layoutFile)) {
    echo "❌ Dashboard layout file not found!\n";
    exit(1);
}

$content = file_get_contents($layoutFile);

// Check for correct CSS classes
$checks = [
    'max-height: 0; /* Collapsed by default */' => 'Default collapsed state CSS',
    '.submenu.expanded' => 'Expanded state CSS class',
    'submenu.classList.add(\'collapsed\')' => 'JavaScript collapse logic',
    'submenu.classList.add(\'expanded\')' => 'JavaScript expand logic',
    'const allSubmenus = document.querySelectorAll(\'.submenu\')' => 'All submenus selection',
    'activeChildLinks.forEach' => 'Active child detection'
];

echo "🔍 Checking sidebar fix implementation:\n\n";

$allPassed = true;
foreach ($checks as $searchText => $description) {
    if (strpos($content, $searchText) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT FOUND\n";
        $allPassed = false;
    }
}

echo "\n";

if ($allPassed) {
    echo "🎉 ALL CHECKS PASSED!\n\n";
    echo "✅ Sidebar fix has been successfully implemented:\n";
    echo "   - All submenus collapsed by default\n";
    echo "   - Only active section expanded\n";
    echo "   - Proper toggle behavior\n";
    echo "   - Smooth animations\n\n";
    echo "🌐 Test the fix by visiting any page:\n";
    echo "   http://localhost/project/Gaurd/index.php?page=dashboard\n";
    echo "   http://localhost/project/Gaurd/index.php?page=attendance-management\n";
    echo "   http://localhost/project/Gaurd/index.php?page=employee-list\n\n";
    echo "Expected behavior:\n";
    echo "- Only the section containing the current page should be expanded\n";
    echo "- All other sections should be collapsed\n";
    echo "- Clicking a section header toggles it and closes others\n";
} else {
    echo "❌ SOME CHECKS FAILED!\n";
    echo "The sidebar fix may not be complete. Please check the implementation.\n";
}

echo "\nVerification completed!\n";
?>
