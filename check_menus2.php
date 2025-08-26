<?php
// Check WordPress menus - run after admin_menu hook
require_once '/var/www/forexsignale/staging/wp-load.php';

// Simulate admin area
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Load admin
require_once '/var/www/forexsignale/staging/wp-admin/includes/admin.php';

// Trigger admin_menu hook
do_action('admin_menu');

global $menu, $submenu;

echo "=== CHECKING WORDPRESS MENUS AFTER ADMIN_MENU HOOK ===\n\n";

echo "All menus with 'todo' or 'To-Do':\n";
echo "--------------------------------\n";

$found_todos = 0;
if (is_array($menu)) {
    foreach ($menu as $position => $item) {
        if (isset($item[0]) && isset($item[2])) {
            $title = wp_strip_all_tags($item[0]);
            $slug = $item[2];
            
            if (stripos($title, 'to-do') !== false || stripos($title, 'todo') !== false || 
                stripos($slug, 'todo') !== false) {
                $found_todos++;
                echo "Menu #$found_todos:\n";
                echo "  Title: $title\n";
                echo "  Slug: $slug\n";
                echo "  Capability: " . ($item[1] ?? 'none') . "\n";
                echo "  Function: " . ($item[3] ?? 'none') . "\n";
                echo "  Icon: " . ($item[6] ?? 'none') . "\n";
                echo "  Position: $position\n";
                
                // Check submenus
                if (isset($submenu[$slug])) {
                    echo "  Submenus:\n";
                    foreach ($submenu[$slug] as $sub) {
                        echo "    - " . wp_strip_all_tags($sub[0]) . " (" . $sub[2] . ")\n";
                    }
                }
                echo "---\n";
            }
        }
    }
}

echo "\nTotal Todo menus found: $found_todos\n";

// Check if functions exist
echo "\n=== CHECKING FUNCTIONS ===\n";
echo "todo_admin_menu exists: " . (function_exists('todo_admin_menu') ? 'YES' : 'NO') . "\n";
echo "todo_admin_page exists: " . (function_exists('todo_admin_page') ? 'YES' : 'NO') . "\n";
echo "todo_new_page exists: " . (function_exists('todo_new_page') ? 'YES' : 'NO') . "\n";