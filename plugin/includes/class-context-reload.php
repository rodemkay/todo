<?php
/**
 * Context Reload class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Context_Reload {
    
    public function check_and_reload() {
        // Check if compacting occurred
        $last_check = get_option('wp_project_todos_last_compact_check', 0);
        $current_time = time();
        
        // Check every 5 minutes
        if ($current_time - $last_check > 300) {
            update_option('wp_project_todos_last_compact_check', $current_time);
            
            // Reload configurations
            $this->reload_configurations();
        }
    }
    
    private function reload_configurations() {
        // DEAKTIVIERT: Claude Code liest diese Dateien selbst ein
        // Redundantes Einlesen verursacht Speicherprobleme nach Compacting
        
        update_option('wp_project_todos_last_config_reload', current_time('mysql'));
    }
}