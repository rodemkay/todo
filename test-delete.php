#!/usr/bin/env php
<?php
// Test script für die Delete-Funktion

// Simuliere WordPress-Umgebung
define('WP_USE_THEMES', false);
define('WP_DEBUG', true);

// Include the model file directly
require_once __DIR__ . '/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/includes/class-todo-model.php';

// Mock WordPress functions
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return ['basedir' => '/var/www/forexsignale/staging/wp-content/uploads'];
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag) {
        // Mock function
    }
}

class WP_Error {
    private $code;
    private $message;
    
    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }
    
    public function get_error_message() {
        return $this->message;
    }
}

// Mock wpdb class for testing
class wpdb {
    public $prefix = 'stage_';
    
    public function get_results($query) {
        // Return empty for attachments
        return [];
    }
    
    public function get_row($query) {
        // Return a mock todo object
        return (object) [
            'id' => 367,
            'title' => 'Test TODO für Löschfunktion',
            'status' => 'offen'
        ];
    }
    
    public function prepare($query) {
        return $query;
    }
    
    public function delete($table, $where) {
        echo "Would delete from $table with conditions: " . json_encode($where) . "\n";
        return 1;
    }
}

// Create mock wpdb instance
global $wpdb;
$wpdb = new wpdb();

// Test the delete function
echo "Testing TODO delete function...\n";
echo "================================\n\n";

// Check if directories exist before deletion
$agent_outputs_dir = '/home/rodemkay/www/react/plugin-todo/agent-outputs/todo-367';
$attachments_dir = '/var/www/forexsignale/staging/wp-content/uploads/todo-attachments/367';

echo "Before deletion:\n";
echo "Agent outputs dir exists: " . (is_dir($agent_outputs_dir) ? 'YES' : 'NO') . "\n";
if (is_dir($agent_outputs_dir)) {
    echo "  Files: " . implode(', ', array_diff(scandir($agent_outputs_dir), ['.', '..'])) . "\n";
}
echo "Attachments dir exists: " . (is_dir($attachments_dir) ? 'YES' : 'NO') . "\n\n";

// Create Todo_Model instance and test delete
$model = new Todo_Model();
$result = $model->delete(367);

echo "\nDelete result: " . ($result === true ? 'SUCCESS' : 'FAILED') . "\n\n";

echo "After deletion:\n";
echo "Agent outputs dir exists: " . (is_dir($agent_outputs_dir) ? 'YES' : 'NO') . "\n";
echo "Attachments dir exists: " . (is_dir($attachments_dir) ? 'YES' : 'NO') . "\n";

if (is_dir($agent_outputs_dir)) {
    echo "\n⚠️  Agent outputs directory was NOT deleted!\n";
} else {
    echo "\n✅ Agent outputs directory was successfully deleted!\n";
}