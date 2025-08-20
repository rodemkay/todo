<?php
/**
 * Todo Model class for database operations
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Todo_Model {
    
    /**
     * Database table name
     */
    private $table;
    
    /**
     * History table name
     */
    private $history_table;
    
    /**
     * Comments table name
     */
    private $comments_table;
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'project_todos';
        $this->history_table = $wpdb->prefix . 'project_todo_history';
        $this->comments_table = $wpdb->prefix . 'project_todo_comments';
    }
    
    /**
     * Get all todos with filters
     */
    public function get_all($args = []) {
        $defaults = [
            'status' => '',
            'scope' => '',
            'priority' => '',
            'assigned_to' => '',
            'working_directory' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['scope'])) {
            $where[] = 'scope = %s';
            $values[] = $args['scope'];
        }
        
        if (!empty($args['priority'])) {
            $where[] = 'priority = %s';
            $values[] = $args['priority'];
        }
        
        if (!empty($args['assigned_to'])) {
            $where[] = 'assigned_to = %s';
            $values[] = $args['assigned_to'];
        }
        
        if (!empty($args['working_directory'])) {
            $where[] = 'working_directory = %s';
            $values[] = $args['working_directory'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(title LIKE %s OR description LIKE %s OR claude_notes LIKE %s OR tags LIKE %s)';
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('ORDER BY %s %s', 
            esc_sql($args['orderby']), 
            esc_sql($args['order'])
        );
        
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = sprintf('LIMIT %d OFFSET %d', 
                intval($args['limit']), 
                intval($args['offset'])
            );
        }
        
        $query = "SELECT * FROM {$this->table} WHERE $where_clause $order_clause $limit_clause";
        
        if (!empty($values)) {
            $query = $this->wpdb->prepare($query, $values);
        }
        
        return $this->wpdb->get_results($query);
    }
    
    /**
     * Get single todo by ID
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );
    }
    
    /**
     * Get next pending todo for Claude
     */
    public function get_next_pending() {
        return $this->wpdb->get_row(
            "SELECT * FROM {$this->table} 
             WHERE status = 'pending' 
             AND assigned_to = 'claude'
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1"
        );
    }
    
    /**
     * Create new todo
     */
    public function create($data) {
        $defaults = [
            'title' => '',
            'description' => '',
            'scope' => 'other',
            'status' => 'offen',
            'priority' => 'medium',
            'bearbeiten' => 0,
            'bemerkungen' => '',
            'claude_mode' => 'bypass',
            'mcp_servers' => '',
            'working_directory' => get_option('wp_project_todos_default_working_directory', '/home/rodemkay/www/react/'),
            'assigned_to' => 'claude',
            'due_date' => null,
            'claude_notes' => '',
            'claude_output' => '',
            'related_files' => '',
            'dependencies' => '',
            'estimated_hours' => null,
            'tags' => '',
            'created_by' => get_current_user_id(),
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['title'])) {
            return new \WP_Error('missing_title', __('Titel ist erforderlich', 'wp-project-todos'));
        }
        
        // Prepare data for insertion
        $insert_data = [
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'scope' => sanitize_key($data['scope']),
            'status' => sanitize_key($data['status']),
            'priority' => sanitize_key($data['priority']),
            'bearbeiten' => intval($data['bearbeiten']),
            'bemerkungen' => sanitize_textarea_field($data['bemerkungen']),
            'claude_mode' => sanitize_key($data['claude_mode']),
            'mcp_servers' => sanitize_text_field($data['mcp_servers']),
            'working_directory' => sanitize_text_field($data['working_directory']),
            'assigned_to' => sanitize_text_field($data['assigned_to']),
            'due_date' => $data['due_date'] ? sanitize_text_field($data['due_date']) : null,
            'claude_notes' => wp_kses_post($data['claude_notes']),
            'claude_output' => wp_kses_post($data['claude_output']),
            'related_files' => is_array($data['related_files']) ? json_encode($data['related_files']) : $data['related_files'],
            'dependencies' => is_array($data['dependencies']) ? json_encode($data['dependencies']) : $data['dependencies'],
            'estimated_hours' => floatval($data['estimated_hours']),
            'tags' => sanitize_text_field($data['tags']),
            'created_by' => intval($data['created_by']),
        ];
        
        $result = $this->wpdb->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Datenbankfehler beim Erstellen', 'wp-project-todos'));
        }
        
        $todo_id = $this->wpdb->insert_id;
        
        // Add creation comment
        $this->add_comment($todo_id, __('Aufgabe erstellt', 'wp-project-todos'));
        
        // Trigger action
        do_action('wp_project_todos_created', $todo_id, $insert_data);
        
        return $todo_id;
    }
    
    /**
     * Update todo
     */
    public function update($id, $data) {
        $old_data = $this->get_by_id($id);
        
        if (!$old_data) {
            return new \WP_Error('not_found', __('Aufgabe nicht gefunden', 'wp-project-todos'));
        }
        
        // Prepare update data
        $update_data = [];
        $history_entries = [];
        
        // Track changes for history
        $fields = [
            'title', 'description', 'scope', 'status', 'priority',
            'working_directory', 'assigned_to', 'due_date', 'claude_notes',
            'estimated_hours', 'actual_hours', 'tags', 'bearbeiten', 'bemerkungen', 'claude_mode',
            'mcp_servers', 'is_planning_mode', 'plan_html', 'plan_created_at'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] != $old_data->$field) {
                $update_data[$field] = $data[$field];
                $history_entries[] = [
                    'field_name' => $field,
                    'old_value' => $old_data->$field,
                    'new_value' => $data[$field],
                ];
            }
        }
        
        // Special handling for claude_output (append, don't replace)
        if (isset($data['claude_output'])) {
            $current_output = $old_data->claude_output ? json_decode($old_data->claude_output, true) : [];
            $new_output = is_array($data['claude_output']) ? $data['claude_output'] : [$data['claude_output']];
            $merged_output = array_merge($current_output, $new_output);
            $update_data['claude_output'] = json_encode($merged_output);
        }
        
        // Handle related_files and dependencies
        if (isset($data['related_files']) && is_array($data['related_files'])) {
            $update_data['related_files'] = json_encode($data['related_files']);
        }
        
        if (isset($data['dependencies']) && is_array($data['dependencies'])) {
            $update_data['dependencies'] = json_encode($data['dependencies']);
        }
        
        // Set completed_date on ANY status change (for tracking last status change)
        if (isset($update_data['status']) && $update_data['status'] !== $old_data->status) {
            $update_data['completed_date'] = current_time('mysql');
        }
        
        // Track status changes for report generation
        $old_status = $old_data->status;
        $new_status = isset($update_data['status']) ? $update_data['status'] : $old_status;
        
        // Update timestamps
        $update_data['updated_by'] = get_current_user_id();
        
        // Perform update
        $result = $this->wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Datenbankfehler beim Aktualisieren', 'wp-project-todos'));
        }
        
        // Add history entries
        foreach ($history_entries as $entry) {
            $this->add_history($id, $entry['field_name'], $entry['old_value'], $entry['new_value']);
        }
        
        // Trigger action
        do_action('wp_project_todos_updated', $id, $update_data, $old_data);
        
        // Trigger status change action if status changed
        if ($old_status !== $new_status) {
            do_action('wp_project_todos_status_changed', $id, $old_status, $new_status);
        }
        
        return true;
    }
    
    /**
     * Delete todo
     */
    public function delete($id) {
        $todo = $this->get_by_id($id);
        
        if (!$todo) {
            return new \WP_Error('not_found', __('Aufgabe nicht gefunden', 'wp-project-todos'));
        }
        
        // Delete attachment files before deleting the todo
        $attachments = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}project_todo_attachments WHERE todo_id = %d",
                $id
            )
        );
        
        foreach ($attachments as $attachment) {
            if (file_exists($attachment->file_path)) {
                unlink($attachment->file_path);
            }
        }
        
        // Delete the todo directory if it exists
        $upload_dir = wp_upload_dir();
        $todo_dir = $upload_dir['basedir'] . '/todo-attachments/' . $id;
        if (is_dir($todo_dir)) {
            // Remove directory (should be empty now)
            @rmdir($todo_dir);
        }
        
        // Delete todo (history and comments will be deleted via CASCADE)
        $result = $this->wpdb->delete($this->table, ['id' => $id]);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Datenbankfehler beim Löschen', 'wp-project-todos'));
        }
        
        // Trigger action
        do_action('wp_project_todos_deleted', $id, $todo);
        
        return true;
    }
    
    /**
     * Update todo status
     */
    public function update_status($id, $status) {
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Append Claude output
     */
    public function append_output($id, $output) {
        $todo = $this->get_by_id($id);
        
        if (!$todo) {
            return false;
        }
        
        $current_output = $todo->claude_output ? json_decode($todo->claude_output, true) : [];
        
        $output_entry = [
            'timestamp' => current_time('mysql'),
            'type' => isset($output['type']) ? $output['type'] : 'info',
            'message' => isset($output['message']) ? $output['message'] : $output,
        ];
        
        $current_output[] = $output_entry;
        
        // Limit output size
        $max_size = get_option('wp_project_todos_max_output_size', 1048576);
        $json_output = json_encode($current_output);
        
        if (strlen($json_output) > $max_size) {
            // Remove oldest entries until size is acceptable
            while (strlen($json_output) > $max_size && count($current_output) > 1) {
                array_shift($current_output);
                $json_output = json_encode($current_output);
            }
        }
        
        $this->wpdb->update(
            $this->table,
            ['claude_output' => $json_output],
            ['id' => $id]
        );
        
        // Broadcast update via action
        do_action('wp_project_todos_output_appended', $id, $output_entry);
        
        return true;
    }
    
    /**
     * Add history entry
     */
    public function add_history($todo_id, $field_name, $old_value, $new_value) {
        return $this->wpdb->insert($this->history_table, [
            'todo_id' => $todo_id,
            'field_name' => $field_name,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'changed_by' => get_current_user_id(),
        ]);
    }
    
    /**
     * Get history for todo
     */
    public function get_history($todo_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT h.*, u.display_name 
                 FROM {$this->history_table} h
                 LEFT JOIN {$this->wpdb->users} u ON h.changed_by = u.ID
                 WHERE h.todo_id = %d 
                 ORDER BY h.changed_at DESC",
                $todo_id
            )
        );
    }
    
    /**
     * Add comment
     */
    public function add_comment($todo_id, $comment, $is_claude = false) {
        return $this->wpdb->insert($this->comments_table, [
            'todo_id' => $todo_id,
            'comment' => $comment,
            'comment_by' => get_current_user_id() ?: null,
            'is_claude_note' => $is_claude,
        ]);
    }
    
    /**
     * Get comments for todo
     */
    public function get_comments($todo_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT c.*, u.display_name 
                 FROM {$this->comments_table} c
                 LEFT JOIN {$this->wpdb->users} u ON c.comment_by = u.ID
                 WHERE c.todo_id = %d 
                 ORDER BY c.comment_at DESC",
                $todo_id
            )
        );
    }
    
    /**
     * Get statistics
     */
    public function get_stats() {
        $stats = [];
        
        // Total by status
        $status_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table} 
             GROUP BY status"
        );
        
        foreach ($status_counts as $row) {
            $stats['status_' . $row->status] = $row->count;
        }
        
        // Total by scope
        $scope_counts = $this->wpdb->get_results(
            "SELECT scope, COUNT(*) as count 
             FROM {$this->table} 
             GROUP BY scope"
        );
        
        foreach ($scope_counts as $row) {
            $stats['scope_' . $row->scope] = $row->count;
        }
        
        // Total todos
        $stats['total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        
        // Completed this week
        $stats['completed_this_week'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE status = 'completed' 
             AND completed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Overdue
        $stats['overdue'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE status NOT IN ('completed', 'cancelled') 
             AND due_date < CURDATE()"
        );
        
        // Average completion time (hours)
        $stats['avg_completion_hours'] = $this->wpdb->get_var(
            "SELECT AVG(actual_hours) FROM {$this->table} 
             WHERE actual_hours IS NOT NULL 
             AND status = 'completed'"
        );
        
        return $stats;
    }
    
    /**
     * Search todos
     */
    public function search($query) {
        return $this->get_all(['search' => $query]);
    }
    
    /**
     * Get todos by scope
     */
    public function get_by_scope($scope) {
        return $this->get_all(['scope' => $scope]);
    }
    
    /**
     * Get todos by status
     */
    public function get_by_status($status) {
        return $this->get_all(['status' => $status]);
    }
    
    /**
     * Get todos by working directory
     */
    public function get_by_directory($directory) {
        return $this->get_all(['working_directory' => $directory]);
    }
    
    /**
     * Bulk update status
     */
    public function bulk_update_status($ids, $status) {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }
        
        $ids = array_map('intval', $ids);
        $ids_string = implode(',', $ids);
        
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} 
                 SET status = %s, updated_by = %d 
                 WHERE id IN ($ids_string)",
                $status,
                get_current_user_id()
            )
        );
        
        // Add history for each
        foreach ($ids as $id) {
            $this->add_history($id, 'status', '', $status);
        }
        
        return $result;
    }
}