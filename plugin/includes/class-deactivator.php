<?php
/**
 * Fired during plugin deactivation
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('wp_project_todos_check_compacting');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}