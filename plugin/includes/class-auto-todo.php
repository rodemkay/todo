<?php
/**
 * Auto Todo Execution System
 * Automatically executes ./todo when status changes from "offen"
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Auto_Todo {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into status changes
        add_action('wp_project_todos_status_changed', [$this, 'handle_status_change'], 10, 3);
        
        // Add AJAX handler for triggering todos
        add_action('wp_ajax_trigger_auto_todo', [$this, 'ajax_trigger_auto_todo']);
        add_action('wp_ajax_nopriv_trigger_auto_todo', [$this, 'ajax_trigger_auto_todo']);
    }
    
    /**
     * Handle status change events
     */
    public function handle_status_change($todo_id, $old_status, $new_status) {
        // Only trigger if changing FROM "offen" to something else
        if ($old_status !== 'offen') {
            return;
        }
        
        // Log the status change
        error_log("Todo #$todo_id status changed from $old_status to $new_status - triggering auto todo");
        
        // Check if there are more "offen" todos
        $model = new Todo_Model();
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        $open_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
             WHERE status = 'offen' 
             AND bearbeiten = 1"
        );
        
        if ($open_count > 0) {
            // Trigger ./todo via the existing trigger file system
            $this->trigger_todo_execution();
            
            // Also add a visible notification in the admin
            add_action('admin_notices', function() use ($open_count) {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong>🔄 Auto-Todo aktiviert!</strong><br>
                        Status wurde von "offen" geändert. Es gibt noch <?php echo $open_count; ?> offene Todo(s).<br>
                        ./todo wird automatisch ausgeführt...
                    </p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Trigger todo execution via trigger file
     */
    private function trigger_todo_execution() {
        // Write to the trigger file that the watch script monitors
        $trigger_file = WP_CONTENT_DIR . '/uploads/claude_trigger.txt';
        $trigger_dir = dirname($trigger_file);
        
        // Ensure directory exists
        if (!file_exists($trigger_dir)) {
            wp_mkdir_p($trigger_dir);
        }
        
        // Write trigger with timestamp and command
        $content = date('Y-m-d H:i:s') . " - AUTO_TODO - ./todo\n";
        
        // Append to file
        file_put_contents($trigger_file, $content, FILE_APPEND | LOCK_EX);
        
        // Also try to send via SSH directly (backup method)
        $this->send_todo_command_ssh();
    }
    
    /**
     * Send todo command via SSH as backup
     */
    private function send_todo_command_ssh() {
        // Try to send command directly to tmux session
        $ssh_command = 'ssh -o ConnectTimeout=2 -o StrictHostKeyChecking=no rodemkay@100.89.207.122 "tmux send-keys -t claude \"./todo\" C-m" 2>/dev/null';
        
        // Execute in background to not block the request
        exec($ssh_command . ' > /dev/null 2>&1 &');
    }
    
    /**
     * AJAX handler for manual trigger
     */
    public function ajax_trigger_auto_todo() {
        // Check nonce if needed
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $this->trigger_todo_execution();
        
        wp_send_json_success([
            'message' => 'Todo execution triggered successfully'
        ]);
    }
    
    /**
     * Get count of open todos
     */
    public static function get_open_todo_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
             WHERE status = 'offen' 
             AND bearbeiten = 1"
        );
    }
}