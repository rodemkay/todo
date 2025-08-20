<?php
/**
 * AJAX Handler class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Ajax {
    
    private $model;
    
    public function __construct() {
        $this->model = new Todo_Model();
        $this->register_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_handlers() {
        add_action('wp_ajax_wp_project_todos_quick_edit', [$this, 'quick_edit']);
        add_action('wp_ajax_wp_project_todos_bulk_action', [$this, 'bulk_action']);
        add_action('wp_ajax_wp_project_todos_update_status', [$this, 'update_status']);
        add_action('wp_ajax_wp_project_todos_get_output', [$this, 'get_output']);
        add_action('wp_ajax_send_single_todo', [$this, 'send_single_todo']);
        add_action('wp_ajax_trigger_todo', [$this, 'trigger_todo']);
    }
    
    public function quick_edit() {
        check_ajax_referer('wp_project_todos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = intval($_POST['id']);
        $field = sanitize_key($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        
        $allowed_fields = ['title', 'status', 'priority', 'scope', 'working_directory'];
        
        if (!in_array($field, $allowed_fields)) {
            wp_send_json_error('Invalid field');
        }
        
        $result = $this->model->update($id, [$field => $value]);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Updated');
        }
    }
    
    public function bulk_action() {
        check_ajax_referer('wp_project_todos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $action = sanitize_key($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);
        
        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $this->model->delete($id);
                }
                break;
            case 'complete':
                foreach ($ids as $id) {
                    $this->model->update_status($id, 'completed');
                }
                break;
            case 'reset':
                foreach ($ids as $id) {
                    $this->model->update_status($id, 'pending');
                }
                break;
            case 'block':
                foreach ($ids as $id) {
                    $this->model->update_status($id, 'blocked');
                }
                break;
            default:
                wp_send_json_error('Invalid action');
                return;
        }
        
        wp_send_json_success('Bulk action completed');
    }
    
    public function update_status() {
        check_ajax_referer('wp_project_todos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = intval($_POST['id']);
        $status = sanitize_key($_POST['status']);
        
        $allowed_statuses = ['pending', 'in_progress', 'completed', 'blocked'];
        
        if (!in_array($status, $allowed_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        $result = $this->model->update_status($id, $status);
        
        if ($result) {
            wp_send_json_success('Status updated');
        } else {
            wp_send_json_error('Update failed');
        }
    }
    
    public function get_output() {
        check_ajax_referer('wp_project_todos', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = intval($_POST['id']);
        $todo = $this->model->get($id);
        
        if (!$todo) {
            wp_send_json_error('Todo not found');
        }
        
        $output = json_decode($todo->claude_output ?: '[]', true);
        
        wp_send_json_success([
            'status' => $todo->status,
            'output' => $output,
            'started_at' => $todo->started_at,
            'completed_at' => $todo->completed_at
        ]);
    }
    
    /**
     * Send single todo to Claude
     */
    public function send_single_todo() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : 0;
        
        if (!$todo_id) {
            wp_send_json_error('Invalid todo ID');
        }
        
        // Trigger via webhook
        $result = $this->trigger_webhook();
        
        if ($result) {
            wp_send_json_success('Todo #' . $todo_id . ' sent to Claude');
        } else {
            wp_send_json_error('Failed to send todo to Claude');
        }
    }
    
    /**
     * Trigger todo execution
     */
    public function trigger_todo() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Trigger via webhook
        $result = $this->trigger_webhook();
        
        if ($result) {
            wp_send_json_success('Todo triggered successfully');
        } else {
            wp_send_json_error('Failed to trigger todo');
        }
    }
    
    /**
     * Call webhook to trigger todo
     */
    private function trigger_webhook() {
        // Webhook configuration
        $webhook_url = 'http://100.89.207.122:9999/trigger';
        $webhook_token = 'secure_token_change_me_123456';
        
        // Make HTTP request
        $response = wp_remote_post($webhook_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $webhook_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 5,
            'body' => json_encode(['source' => 'wordpress'])
        ]);
        
        if (is_wp_error($response)) {
            error_log('Webhook error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }
}