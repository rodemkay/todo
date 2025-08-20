<?php
/**
 * Plan Management für WP Project To-Dos
 */

class WP_Project_Plans {
    
    private $plans_dir;
    
    public function __construct() {
        $this->plans_dir = plugin_dir_path(dirname(__FILE__)) . 'plans/';
        
        // Erstelle Plans-Verzeichnis wenn nicht vorhanden
        if (!file_exists($this->plans_dir)) {
            wp_mkdir_p($this->plans_dir);
        }
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_create_todo_from_plan', array($this, 'ajax_create_todo_from_plan'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'project-todos',
            'Claude Pläne',
            'Claude Pläne',
            'manage_options',
            'project-plans',
            array($this, 'render_plans_page')
        );
    }
    
    /**
     * Scripts einbinden
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'project-todos_page_project-plans') {
            return;
        }
        
        wp_enqueue_style('plans-style', plugin_dir_url(dirname(__FILE__)) . 'assets/plans.css');
        wp_enqueue_script('plans-script', plugin_dir_url(dirname(__FILE__)) . 'assets/plans.js', array('jquery'));
        wp_localize_script('plans-script', 'plans_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plans_nonce')
        ));
    }
    
    /**
     * Plans-Seite rendern
     */
    public function render_plans_page() {
        ?>
        <div class="wrap">
            <h1>📋 Claude Pläne</h1>
            
            <div class="plans-container">
                <?php $this->display_plans(); ?>
            </div>
            
            <div class="plan-viewer" id="plan-viewer" style="display:none;">
                <div class="plan-header">
                    <h2 id="plan-title"></h2>
                    <button class="button" onclick="closePlanViewer()">Schließen</button>
                </div>
                <iframe id="plan-frame" style="width:100%; height:600px; border:1px solid #ddd;"></iframe>
            </div>
        </div>
        
        <style>
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .plan-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .plan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .plan-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .plan-meta {
            color: #666;
            font-size: 12px;
            margin: 10px 0;
        }
        
        .plan-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .plan-viewer {
            position: fixed;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 1200px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            z-index: 10000;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        </style>
        
        <script>
        function viewPlan(file) {
            const viewer = document.getElementById('plan-viewer');
            const frame = document.getElementById('plan-frame');
            const title = document.getElementById('plan-title');
            
            title.textContent = 'Plan: ' + file;
            frame.src = '<?php echo plugin_dir_url(dirname(__FILE__)); ?>plans/' + file;
            viewer.style.display = 'block';
        }
        
        function closePlanViewer() {
            document.getElementById('plan-viewer').style.display = 'none';
        }
        
        function createTodoFromPlan(planFile, todoId) {
            if (confirm('Neues Todo aus diesem Plan erstellen?')) {
                jQuery.post(plans_ajax.ajax_url, {
                    action: 'create_todo_from_plan',
                    plan_file: planFile,
                    original_todo: todoId,
                    nonce: plans_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        alert('Todo erstellt! ID: ' + response.data.todo_id);
                        window.location.href = 'admin.php?page=project-todos&action=edit&id=' + response.data.todo_id;
                    } else {
                        alert('Fehler: ' + response.data);
                    }
                });
            }
        }
        
        function executeWithBypass(todoId) {
            const command = 'claude --approval-policy bypassPermissions "Todo #' + todoId + ' ausführen"';
            prompt('Kopiere diesen Befehl:', command);
        }
        </script>
        <?php
    }
    
    /**
     * Pläne anzeigen
     */
    private function display_plans() {
        $plans = $this->get_plans();
        
        if (empty($plans)) {
            echo '<p>Keine Pläne vorhanden. Nutze <code>./todo-plan</code> um einen Plan zu erstellen.</p>';
            return;
        }
        
        echo '<div class="plans-grid">';
        
        foreach ($plans as $plan) {
            $this->render_plan_card($plan);
        }
        
        echo '</div>';
    }
    
    /**
     * Plan-Karte rendern
     */
    private function render_plan_card($plan) {
        $filename = basename($plan);
        
        // Parse Dateiname für Todo-ID
        preg_match('/plan_(\d+)_/', $filename, $matches);
        $todo_id = isset($matches[1]) ? $matches[1] : 0;
        
        // Hole Todo-Infos aus DB
        global $wpdb;
        $todo = null;
        if ($todo_id) {
            $todo = $wpdb->get_row($wpdb->prepare(
                "SELECT title, description, status FROM {$wpdb->prefix}project_todos WHERE id = %d",
                $todo_id
            ));
        }
        
        ?>
        <div class="plan-card">
            <h3><?php echo $todo ? esc_html($todo->title) : 'Plan #' . $todo_id; ?></h3>
            
            <?php if ($todo): ?>
                <div class="plan-meta">
                    <strong>Todo #<?php echo $todo_id; ?></strong><br>
                    Status: <?php echo esc_html($todo->status); ?><br>
                    Datei: <?php echo esc_html($filename); ?>
                </div>
            <?php endif; ?>
            
            <div class="plan-meta">
                Erstellt: <?php echo date('d.m.Y H:i', filemtime($plan)); ?><br>
                Größe: <?php echo number_format(filesize($plan) / 1024, 1); ?> KB
            </div>
            
            <div class="plan-actions">
                <button class="button button-primary" onclick="viewPlan('<?php echo esc_js($filename); ?>')">
                    👁️ Anzeigen
                </button>
                <button class="button" onclick="createTodoFromPlan('<?php echo esc_js($filename); ?>', <?php echo $todo_id; ?>)">
                    📝 Todo erstellen
                </button>
                <button class="button" onclick="executeWithBypass(<?php echo $todo_id; ?>)">
                    🚀 Ausführen
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Alle Pläne abrufen
     */
    private function get_plans() {
        $plans = glob($this->plans_dir . 'plan_*.html');
        
        // Sortiere nach Datum (neueste zuerst)
        usort($plans, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $plans;
    }
    
    /**
     * AJAX: Todo aus Plan erstellen
     */
    public function ajax_create_todo_from_plan() {
        check_ajax_referer('plans_nonce', 'nonce');
        
        $plan_file = sanitize_text_field($_POST['plan_file']);
        $original_todo = intval($_POST['original_todo']);
        
        // Hole Original-Todo
        global $wpdb;
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_todos WHERE id = %d",
            $original_todo
        ));
        
        if (!$original) {
            wp_send_json_error('Original-Todo nicht gefunden');
        }
        
        // Erstelle neues Todo
        $new_todo_id = $wpdb->insert(
            $wpdb->prefix . 'project_todos',
            array(
                'title' => '[PLAN] ' . $original->title,
                'description' => "Ausführung des Plans für Todo #$original_todo\n\nOriginal: " . $original->description,
                'scope' => $original->scope,
                'status' => 'pending',
                'priority' => 'high',
                'bearbeiten' => 1,
                'working_directory' => $original->working_directory,
                'assigned_to' => 'claude',
                'related_files' => $this->plans_dir . $plan_file,
                'dependencies' => "Todo #$original_todo",
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'claude_mode' => 'bypass'
            )
        );
        
        if ($new_todo_id) {
            wp_send_json_success(array('todo_id' => $wpdb->insert_id));
        } else {
            wp_send_json_error('Fehler beim Erstellen des Todos');
        }
    }
}

// Initialisiere Klasse
new WP_Project_Plans();