<?php
/**
 * REST API class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class API {
    
    /**
     * API namespace
     */
    private $namespace = 'todos/v1';
    
    /**
     * Todo model instance
     */
    private $model;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->model = new Todo_Model();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Claude-specific endpoints (HIGHEST PRIORITY)
        register_rest_route($this->namespace, '/claude/next', [
            'methods' => 'GET',
            'callback' => [$this, 'get_next_todo_for_claude'],
            'permission_callback' => [$this, 'check_claude_permission'],
        ]);
        
        register_rest_route($this->namespace, '/claude/output', [
            'methods' => 'POST',
            'callback' => [$this, 'save_claude_output'],
            'permission_callback' => [$this, 'check_claude_permission'],
            'args' => [
                'todo_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'output' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'info',
                    'enum' => ['info', 'success', 'warning', 'error'],
                ],
            ],
        ]);
        
        register_rest_route($this->namespace, '/claude/status', [
            'methods' => 'POST',
            'callback' => [$this, 'update_claude_status'],
            'permission_callback' => [$this, 'check_claude_permission'],
            'args' => [
                'todo_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['pending', 'in_progress', 'completed', 'blocked', 'cancelled'],
                ],
                'note' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'actual_hours' => [
                    'required' => false,
                    'type' => 'number',
                ],
            ],
        ]);
        
        register_rest_route($this->namespace, '/claude/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_claude_todo'],
            'permission_callback' => [$this, 'check_claude_permission'],
            'args' => [
                'todo_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'actual_hours' => [
                    'required' => false,
                    'type' => 'number',
                ],
                'final_notes' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);
        
        register_rest_route($this->namespace, '/claude/reload', [
            'methods' => 'POST',
            'callback' => [$this, 'reload_claude_context'],
            'permission_callback' => [$this, 'check_claude_permission'],
        ]);
        
        // Standard todo endpoints
        register_rest_route($this->namespace, '/list', [
            'methods' => 'GET',
            'callback' => [$this, 'get_todos'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'status' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'scope' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'priority' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'search' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                ],
            ],
        ]);
        
        register_rest_route($this->namespace, '/todo/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_todo'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_todo'],
                'permission_callback' => [$this, 'check_edit_permission'],
                'args' => $this->get_todo_args(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_todo'],
                'permission_callback' => [$this, 'check_delete_permission'],
            ],
        ]);
        
        register_rest_route($this->namespace, '/todo', [
            'methods' => 'POST',
            'callback' => [$this, 'create_todo'],
            'permission_callback' => [$this, 'check_create_permission'],
            'args' => $this->get_todo_args(true),
        ]);
        
        register_rest_route($this->namespace, '/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_action'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['update_status', 'delete', 'assign'],
                ],
                'ids' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'value' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);
        
        // Integration endpoints
        register_rest_route($this->namespace, '/n8n/pending', [
            'methods' => 'GET',
            'callback' => [$this, 'get_n8n_pending'],
            'permission_callback' => [$this, 'check_integration_permission'],
        ]);
        
        register_rest_route($this->namespace, '/mt5/result', [
            'methods' => 'POST',
            'callback' => [$this, 'update_mt5_result'],
            'permission_callback' => [$this, 'check_integration_permission'],
        ]);
        
        register_rest_route($this->namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * Get next todo for Claude
     */
    public function get_next_todo_for_claude($request) {
        $todo = $this->model->get_next_pending();
        
        if (!$todo) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Keine offenen Aufgaben vorhanden', 'wp-project-todos'),
            ], 404);
        }
        
        // Update status to in_progress
        $this->model->update_status($todo->id, 'in_progress');
        $this->model->add_comment($todo->id, 'Claude hat die Bearbeitung begonnen', true);
        
        // Prepare response with all necessary information
        $response_data = [
            'success' => true,
            'todo' => [
                'id' => $todo->id,
                'title' => $todo->title,
                'description' => $todo->description,
                'scope' => $todo->scope,
                'priority' => $todo->priority,
                'working_directory' => $todo->working_directory,
                'related_files' => json_decode($todo->related_files, true),
                'estimated_hours' => $todo->estimated_hours,
                'tags' => $todo->tags,
                'created_at' => $todo->created_at,
            ],
            'instructions' => [
                'change_directory' => !empty($todo->working_directory) ? "cd {$todo->working_directory}" : null,
                'reload_configs' => true,
                'capture_output' => true,
            ],
        ];
        
        // Trigger action for other plugins
        do_action('wp_project_todos_claude_started', $todo->id, $todo);
        
        return new \WP_REST_Response($response_data, 200);
    }
    
    /**
     * Save Claude output
     */
    public function save_claude_output($request) {
        $todo_id = $request->get_param('todo_id');
        $output = $request->get_param('output');
        $type = $request->get_param('type');
        
        $result = $this->model->append_output($todo_id, [
            'message' => $output,
            'type' => $type,
        ]);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Fehler beim Speichern der Ausgabe', 'wp-project-todos'),
            ], 500);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Ausgabe gespeichert', 'wp-project-todos'),
        ], 200);
    }
    
    /**
     * Update Claude status
     */
    public function update_claude_status($request) {
        $todo_id = $request->get_param('todo_id');
        $status = $request->get_param('status');
        $note = $request->get_param('note');
        $actual_hours = $request->get_param('actual_hours');
        
        $update_data = ['status' => $status];
        
        if ($actual_hours !== null) {
            $update_data['actual_hours'] = $actual_hours;
        }
        
        $result = $this->model->update($todo_id, $update_data);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        if ($note) {
            $this->model->add_comment($todo_id, $note, true);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Status aktualisiert', 'wp-project-todos'),
        ], 200);
    }
    
    /**
     * Complete Claude todo
     */
    public function complete_claude_todo($request) {
        $todo_id = $request->get_param('todo_id');
        $actual_hours = $request->get_param('actual_hours');
        $final_notes = $request->get_param('final_notes');
        
        $update_data = [
            'status' => 'completed',
            'completed_date' => current_time('mysql'),
        ];
        
        if ($actual_hours !== null) {
            $update_data['actual_hours'] = $actual_hours;
        }
        
        if ($final_notes) {
            $update_data['claude_notes'] = $final_notes;
        }
        
        $result = $this->model->update($todo_id, $update_data);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        $this->model->add_comment($todo_id, 'Aufgabe von Claude abgeschlossen', true);
        
        // Get next todo
        $next_todo = $this->model->get_next_pending();
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Aufgabe abgeschlossen', 'wp-project-todos'),
            'next_todo' => $next_todo ? [
                'id' => $next_todo->id,
                'title' => $next_todo->title,
            ] : null,
        ], 200);
    }
    
    /**
     * Reload Claude context
     */
    public function reload_claude_context($request) {
        // Trigger context reload
        do_action('wp_project_todos_reload_context');
        
        // Get current configuration
        $config = [
            'default_directory' => get_option('wp_project_todos_default_working_directory'),
            'claude_enabled' => get_option('wp_project_todos_claude_enabled'),
            'auto_reload' => get_option('wp_project_todos_auto_reload_configs'),
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Kontext neu geladen', 'wp-project-todos'),
            'config' => $config,
        ], 200);
    }
    
    /**
     * Get todos
     */
    public function get_todos($request) {
        $args = [
            'status' => $request->get_param('status'),
            'scope' => $request->get_param('scope'),
            'priority' => $request->get_param('priority'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('per_page'),
            'offset' => ($request->get_param('page') - 1) * $request->get_param('per_page'),
        ];
        
        $todos = $this->model->get_all($args);
        $total = $this->model->get_all(array_merge($args, ['limit' => -1]));
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $todos,
            'meta' => [
                'total' => count($total),
                'page' => $request->get_param('page'),
                'per_page' => $request->get_param('per_page'),
            ],
        ], 200);
    }
    
    /**
     * Get single todo
     */
    public function get_todo($request) {
        $id = $request->get_param('id');
        $todo = $this->model->get_by_id($id);
        
        if (!$todo) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Aufgabe nicht gefunden', 'wp-project-todos'),
            ], 404);
        }
        
        // Get history and comments
        $todo->history = $this->model->get_history($id);
        $todo->comments = $this->model->get_comments($id);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $todo,
        ], 200);
    }
    
    /**
     * Create todo
     */
    public function create_todo($request) {
        $data = $request->get_params();
        $todo_id = $this->model->create($data);
        
        if (is_wp_error($todo_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $todo_id->get_error_message(),
            ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Aufgabe erstellt', 'wp-project-todos'),
            'data' => ['id' => $todo_id],
        ], 201);
    }
    
    /**
     * Update todo
     */
    public function update_todo($request) {
        $id = $request->get_param('id');
        $data = $request->get_params();
        unset($data['id']);
        
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Aufgabe aktualisiert', 'wp-project-todos'),
        ], 200);
    }
    
    /**
     * Delete todo
     */
    public function delete_todo($request) {
        $id = $request->get_param('id');
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Aufgabe gelöscht', 'wp-project-todos'),
        ], 200);
    }
    
    /**
     * Bulk action
     */
    public function bulk_action($request) {
        $action = $request->get_param('action');
        $ids = $request->get_param('ids');
        $value = $request->get_param('value');
        
        switch ($action) {
            case 'update_status':
                $result = $this->model->bulk_update_status($ids, $value);
                break;
            
            case 'delete':
                $result = 0;
                foreach ($ids as $id) {
                    if ($this->model->delete($id)) {
                        $result++;
                    }
                }
                break;
            
            case 'assign':
                $result = 0;
                foreach ($ids as $id) {
                    if ($this->model->update($id, ['assigned_to' => $value])) {
                        $result++;
                    }
                }
                break;
            
            default:
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Unbekannte Aktion', 'wp-project-todos'),
                ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('%d Aufgaben bearbeitet', 'wp-project-todos'), $result),
        ], 200);
    }
    
    /**
     * Get n8n pending todos
     */
    public function get_n8n_pending($request) {
        $todos = $this->model->get_all([
            'scope' => 'n8n',
            'status' => 'pending',
        ]);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $todos,
        ], 200);
    }
    
    /**
     * Update MT5 result
     */
    public function update_mt5_result($request) {
        $todo_id = $request->get_param('todo_id');
        $result = $request->get_param('result');
        
        $this->model->append_output($todo_id, [
            'message' => 'MT5 Result: ' . json_encode($result),
            'type' => 'success',
        ]);
        
        $this->model->add_comment($todo_id, 'MT5 Ergebnis erhalten', false);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('MT5 Ergebnis gespeichert', 'wp-project-todos'),
        ], 200);
    }
    
    /**
     * Get statistics
     */
    public function get_stats($request) {
        $stats = $this->model->get_stats();
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $stats,
        ], 200);
    }
    
    /**
     * Get todo args for validation
     */
    private function get_todo_args($required = false) {
        return [
            'title' => [
                'required' => $required,
                'type' => 'string',
            ],
            'description' => [
                'required' => false,
                'type' => 'string',
            ],
            'scope' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['frontend', 'backend', 'database', 'n8n', 'mt5', 'server', 'content', 'seo', 'analytics', 'other'],
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['pending', 'in_progress', 'completed', 'blocked', 'cancelled'],
            ],
            'priority' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['low', 'medium', 'high', 'critical'],
            ],
            'working_directory' => [
                'required' => false,
                'type' => 'string',
            ],
            'assigned_to' => [
                'required' => false,
                'type' => 'string',
            ],
            'due_date' => [
                'required' => false,
                'type' => 'string',
            ],
            'estimated_hours' => [
                'required' => false,
                'type' => 'number',
            ],
            'tags' => [
                'required' => false,
                'type' => 'string',
            ],
        ];
    }
    
    /**
     * Check permission
     */
    public function check_permission($request) {
        return current_user_can('view_project_todos') || $this->is_valid_api_key($request);
    }
    
    /**
     * Check edit permission
     */
    public function check_edit_permission($request) {
        return current_user_can('edit_project_todos') || $this->is_valid_api_key($request);
    }
    
    /**
     * Check create permission
     */
    public function check_create_permission($request) {
        return current_user_can('edit_project_todos') || $this->is_valid_api_key($request);
    }
    
    /**
     * Check delete permission
     */
    public function check_delete_permission($request) {
        return current_user_can('delete_project_todos') || $this->is_valid_api_key($request);
    }
    
    /**
     * Check Claude permission
     */
    public function check_claude_permission($request) {
        // Allow access via API key or special Claude token
        $claude_token = $request->get_header('X-Claude-Token');
        $expected_token = get_option('wp_project_todos_claude_token', 'claude-' . wp_hash('claude-todos'));
        
        if ($claude_token === $expected_token) {
            return true;
        }
        
        return current_user_can('edit_project_todos') || $this->is_valid_api_key($request);
    }
    
    /**
     * Check integration permission
     */
    public function check_integration_permission($request) {
        return $this->is_valid_api_key($request) || current_user_can('manage_project_todos');
    }
    
    /**
     * Check if request has valid API key
     */
    private function is_valid_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (!$api_key) {
            return false;
        }
        
        $valid_key = get_option('wp_project_todos_api_key');
        
        if (!$valid_key) {
            // Generate and save a key if none exists
            $valid_key = wp_generate_password(32, false);
            update_option('wp_project_todos_api_key', $valid_key);
        }
        
        return hash_equals($valid_key, $api_key);
    }
}