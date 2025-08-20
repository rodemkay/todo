<?php
/**
 * Auto-Complete Handler for Claude Integration
 * 
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Auto_Complete {
    
    private $model;
    private $claude;
    
    public function __construct() {
        $this->model = new Todo_Model();
        
        // Hook into todo completion
        add_action('wp_project_todos_todo_completing', [$this, 'handle_todo_completion'], 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_complete_and_next', [$this, 'ajax_complete_and_next']);
        add_action('wp_ajax_nopriv_complete_and_next', [$this, 'ajax_complete_and_next']);
    }
    
    /**
     * Handle automatic completion and load next todo
     */
    public function handle_todo_completion($todo_id, $completion_data) {
        $todo = $this->model->get_by_id($todo_id);
        
        if (!$todo) {
            return false;
        }
        
        // Save any final notes
        if (!empty($completion_data['notes'])) {
            $this->model->update($todo_id, [
                'bemerkungen' => $completion_data['notes'],
                'claude_notes' => $completion_data['claude_notes'] ?? ''
            ]);
        }
        
        // Mark as completed
        $this->model->update_status($todo_id, 'completed');
        
        // Add completion timestamp
        $this->model->update($todo_id, [
            'completed_date' => current_time('mysql'),
            'actual_hours' => $completion_data['actual_hours'] ?? null
        ]);
        
        // Log completion
        $this->model->add_comment($todo_id, 'Aufgabe automatisch abgeschlossen', true);
        
        // Get next todo
        $next_todo = $this->model->get_next_pending();
        
        if ($next_todo) {
            // Trigger next todo load
            do_action('wp_project_todos_load_next', $next_todo);
            
            return [
                'completed' => $todo_id,
                'next' => $next_todo->id,
                'next_title' => $next_todo->title,
                'message' => 'Aufgabe abgeschlossen, nächste Aufgabe wird geladen...'
            ];
        } else {
            return [
                'completed' => $todo_id,
                'next' => null,
                'message' => 'Alle Aufgaben abgeschlossen!'
            ];
        }
    }
    
    /**
     * AJAX handler for complete and load next
     */
    public function ajax_complete_and_next() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $claude_notes = sanitize_textarea_field($_POST['claude_notes'] ?? '');
        $actual_hours = floatval($_POST['actual_hours'] ?? 0);
        
        $result = $this->handle_todo_completion($todo_id, [
            'notes' => $notes,
            'claude_notes' => $claude_notes,
            'actual_hours' => $actual_hours
        ]);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Abschließen der Aufgabe']);
        }
    }
    
    /**
     * Auto-complete when Claude signals completion
     */
    public function auto_complete_on_signal($todo_id) {
        // This can be called when Claude sends a specific signal
        // For example, when output contains "TASK_COMPLETED" or similar
        
        $todo = $this->model->get_by_id($todo_id);
        
        if ($todo && $todo->status === 'in_progress') {
            // Extract completion notes from output if available
            $output = json_decode($todo->claude_output, true);
            $final_notes = '';
            
            if (is_array($output)) {
                // Look for completion markers in output
                foreach (array_reverse($output) as $entry) {
                    if (strpos($entry['message'], 'COMPLETED') !== false ||
                        strpos($entry['message'], 'FERTIG') !== false ||
                        strpos($entry['message'], 'ABGESCHLOSSEN') !== false) {
                        $final_notes = $entry['message'];
                        break;
                    }
                }
            }
            
            // Complete the task
            return $this->handle_todo_completion($todo_id, [
                'notes' => $final_notes,
                'claude_notes' => 'Automatisch abgeschlossen nach Signal',
                'actual_hours' => null
            ]);
        }
        
        return false;
    }
    
    /**
     * Check if current output indicates completion
     */
    public function check_completion_signals($todo_id) {
        $todo = $this->model->get_by_id($todo_id);
        
        if (!$todo || $todo->status !== 'in_progress') {
            return false;
        }
        
        // Completion keywords
        $completion_keywords = [
            'TASK_COMPLETED',
            'AUFGABE_ABGESCHLOSSEN',
            '✅ Fertig',
            '✅ Abgeschlossen',
            'Todo wurde erfolgreich',
            'erfolgreich implementiert',
            'Feature ist vollständig',
            'Implementierung abgeschlossen'
        ];
        
        $output = json_decode($todo->claude_output, true);
        
        if (is_array($output) && !empty($output)) {
            // Check last few entries for completion signals
            $recent_entries = array_slice($output, -5);
            
            foreach ($recent_entries as $entry) {
                foreach ($completion_keywords as $keyword) {
                    if (stripos($entry['message'], $keyword) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
}