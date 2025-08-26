<?php
// Check WordPress menus
require_once '/var/www/forexsignale/staging/wp-load.php';

global $menu, $submenu;

echo "=== CHECKING WORDPRESS MENUS ===\n\n";

echo "All menus with 'todo' or 'To-Do':\n";
echo "--------------------------------\n";

foreach ($menu as $item) {
    if (isset($item[0]) && isset($item[2])) {
        $title = $item[0];
        $slug = $item[2];
        
        if (stripos($title, 'to-do') !== false || stripos($title, 'todo') !== false || 
            stripos($slug, 'todo') !== false) {
            echo "Menu: $title\n";
            echo "Slug: $slug\n";
            echo "Capability: " . ($item[1] ?? 'none') . "\n";
            echo "Icon: " . ($item[6] ?? 'none') . "\n";
            echo "Position: " . (array_search($item, $menu)) . "\n";
            echo "---\n";
        }
    }
}

echo "\n=== CHECKING HOOKS ===\n\n";

global $wp_filter;
if (isset($wp_filter['admin_menu'])) {
    echo "Admin menu hooks:\n";
    foreach ($wp_filter['admin_menu'] as $priority => $hooks) {
        foreach ($hooks as $hook) {
            $function_name = '';
            if (is_array($hook['function'])) {
                if (is_object($hook['function'][0])) {
                    $function_name = get_class($hook['function'][0]) . '::' . $hook['function'][1];
                } else {
                    $function_name = $hook['function'][0] . '::' . $hook['function'][1];
                }
            } else {
                $function_name = $hook['function'];
            }
            
            if (stripos($function_name, 'todo') !== false) {
                echo "Priority $priority: $function_name\n";
            }
        }
    }
}