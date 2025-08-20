<?php
/**
 * Shortcodes class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Shortcodes {
    
    private $model;
    
    public function __construct() {
        $this->model = new Todo_Model();
    }
    
    public function register() {
        add_shortcode('project_todos', [$this, 'render_todos_list']);
        add_shortcode('project_todo_stats', [$this, 'render_stats']);
        add_shortcode('claude_current_task', [$this, 'render_current_task']);
    }
    
    public function render_todos_list($atts) {
        $atts = shortcode_atts([
            'scope' => '',
            'status' => 'pending',
            'limit' => 10,
        ], $atts);
        
        $todos = $this->model->get_all($atts);
        
        ob_start();
        ?>
        <div class="wp-project-todos-list">
            <?php foreach ($todos as $todo): ?>
            <div class="todo-item">
                <h3><?php echo esc_html($todo->title); ?></h3>
                <p>Status: <?php echo esc_html($todo->status); ?> | Scope: <?php echo esc_html($todo->scope); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_stats($atts) {
        $stats = $this->model->get_stats();
        
        ob_start();
        ?>
        <div class="wp-project-todos-stats">
            <p>Total: <?php echo $stats['total']; ?></p>
            <p>Completed this week: <?php echo $stats['completed_this_week']; ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_current_task($atts) {
        $claude = new Claude_Integration();
        return $claude->get_current_status();
    }
    
    public function enqueue_styles() {
        // Enqueue styles if needed
    }
    
    public function enqueue_scripts() {
        // Enqueue scripts if needed
    }
}