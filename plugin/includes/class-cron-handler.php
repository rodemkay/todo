<?php
/**
 * Cron Task Handler
 * Verwaltet wiederkehrende Aufgaben direkt in WordPress
 */

namespace WP_Project_Todos;

class Cron_Handler {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'project_todos';
        
        // Hooks registrieren
        add_action('wp_project_todos_task_completed', [$this, 'handle_task_completion'], 10, 1);
        add_action('wp_ajax_reset_cron_task', [$this, 'ajax_reset_cron_task']);
        add_action('wp_ajax_activate_cron_task', [$this, 'ajax_activate_cron_task']);
    }
    
    /**
     * Handle task completion - reset cron tasks back to cron status
     */
    public function handle_task_completion($todo_id) {
        global $wpdb;
        
        // Hole Task-Details
        $todo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $todo_id
        ));
        
        if (!$todo) {
            return;
        }
        
        // Prüfe ob es ein Cron-Task ist
        if ($todo->is_cron == 1) {
            $this->reset_cron_task($todo_id, $todo);
        }
    }
    
    /**
     * Reset cron task back to cron status
     */
    private function reset_cron_task($todo_id, $todo) {
        global $wpdb;
        
        // Erstelle Bericht bevor Reset
        $this->create_cron_report($todo);
        
        // Reset Task zu Cron-Status
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'cron',
                'bearbeiten' => 0,
                'completed_date' => null,
                'claude_notes' => '', // Optional: Claude-Notizen löschen für nächsten Run
                'claude_output' => ''
            ],
            ['id' => $todo_id]
        );
        
        if ($result !== false) {
            // Log the reset
            $this->log_cron_reset($todo);
            
            // Trigger WordPress Action für andere Plugins
            do_action('wp_project_todos_cron_reset', $todo_id, $todo);
        }
        
        return $result;
    }
    
    /**
     * Create report for completed cron task
     */
    private function create_cron_report($todo) {
        global $wpdb;
        
        // Erstelle Eintrag in separater Cron-Reports Tabelle
        $report_data = [
            'todo_id' => $todo->id,
            'title' => $todo->title,
            'execution_date' => current_time('mysql'),
            'status' => 'completed',
            'claude_notes' => $todo->claude_notes,
            'claude_output' => $todo->claude_output,
            'duration' => $this->calculate_duration($todo->updated_at, $todo->completed_date)
        ];
        
        // Speichere in Cron-Reports Tabelle (wird bei Aktivierung erstellt)
        $wpdb->insert(
            $wpdb->prefix . 'project_todo_cron_reports',
            $report_data
        );
    }
    
    /**
     * Calculate task duration
     */
    private function calculate_duration($start, $end) {
        if (!$start || !$end) {
            return 0;
        }
        
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        
        return $end_time - $start_time;
    }
    
    /**
     * Log cron reset event
     */
    private function log_cron_reset($todo) {
        // WordPress Transient für Quick-Access Log
        $log_key = 'cron_reset_log';
        $log = get_transient($log_key) ?: [];
        
        $log[] = [
            'todo_id' => $todo->id,
            'title' => $todo->title,
            'reset_time' => current_time('mysql'),
            'notes' => substr($todo->claude_notes, 0, 200)
        ];
        
        // Behalte nur die letzten 50 Einträge
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        
        set_transient($log_key, $log, DAY_IN_SECONDS);
    }
    
    /**
     * AJAX: Activate cron task (set to 'offen')
     */
    public function ajax_activate_cron_task() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'offen',
                'bearbeiten' => 1,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $todo_id]
        );
        
        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Cron-Task aktiviert',
                'todo_id' => $todo_id
            ]);
        } else {
            wp_send_json_error('Fehler beim Aktivieren des Cron-Tasks');
        }
    }
    
    /**
     * AJAX: Manually reset task to cron
     */
    public function ajax_reset_cron_task() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        
        global $wpdb;
        $todo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $todo_id
        ));
        
        if ($todo) {
            $result = $this->reset_cron_task($todo_id, $todo);
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => 'Task zu Cron-Status zurückgesetzt',
                    'todo_id' => $todo_id
                ]);
            }
        }
        
        wp_send_json_error('Fehler beim Zurücksetzen');
    }
    
    /**
     * Get cron execution history
     */
    public function get_cron_history($todo_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}project_todo_cron_reports";
        
        if ($todo_id) {
            $query .= $wpdb->prepare(" WHERE todo_id = %d", $todo_id);
        }
        
        $query .= " ORDER BY execution_date DESC LIMIT 100";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get next scheduled cron tasks
     */
    public function get_scheduled_cron_tasks() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'cron' AND is_cron = 1
             ORDER BY priority DESC, title ASC"
        );
    }
}

// Initialisiere Cron Handler
add_action('init', function() {
    new Cron_Handler();
});