<?php
/**
 * Output Capture class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Output_Capture {
    
    private $model;
    private $buffer = [];
    private $current_todo_id = null;
    
    public function __construct() {
        $this->model = new Todo_Model();
    }
    
    public function capture($todo_id, $message, $type = 'info') {
        $this->current_todo_id = $todo_id;
        
        $this->buffer[] = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
        ];
        
        // Flush if buffer is large
        if (count($this->buffer) >= 10) {
            $this->flush();
        }
    }
    
    public function flush() {
        if (empty($this->buffer) || !$this->current_todo_id) {
            return;
        }
        
        foreach ($this->buffer as $entry) {
            $this->model->append_output($this->current_todo_id, $entry);
        }
        
        $this->buffer = [];
    }
}