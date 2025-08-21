<?php
/**
 * Planning Mode Handler
 * 
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Planning_Mode {
    
    private $todo_model;
    
    public function __construct() {
        $this->todo_model = new Todo_Model();
        add_action('wp_ajax_save_plan_html', [$this, 'ajax_save_plan_html']);
        add_action('wp_ajax_get_plan_html', [$this, 'ajax_get_plan_html']);
        add_action('wp_ajax_create_followup_todo', [$this, 'ajax_create_followup_todo']);
        
        // Neue AJAX-Handler für strukturierten Editor
        add_action('wp_ajax_save_structured_plan', [$this, 'ajax_save_structured_plan']);
        add_action('wp_ajax_generate_plan_preview', [$this, 'ajax_generate_plan_preview']);
        add_action('wp_ajax_load_structured_plan_editor', [$this, 'ajax_load_structured_plan_editor']);
    }
    
    /**
     * AJAX handler to save plan HTML
     */
    public function ajax_save_plan_html() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $plan_html = wp_kses_post($_POST['plan_html']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        $result = $wpdb->update(
            $table,
            [
                'plan_html' => $plan_html,
                'plan_created_at' => current_time('mysql'),
                'is_planning_mode' => 1
            ],
            ['id' => $todo_id]
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Plan gespeichert']);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }
    }
    
    /**
     * AJAX handler to get plan HTML
     */
    public function ajax_get_plan_html() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        $todo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $todo_id
        ));
        
        if ($todo && $todo->plan_html) {
            wp_send_json_success([
                'html' => $todo->plan_html,
                'title' => $todo->title,
                'created_at' => $todo->plan_created_at
            ]);
        } else {
            wp_send_json_error(['message' => 'Kein Plan vorhanden']);
        }
    }
    
    /**
     * Create followup todo with context
     */
    public function ajax_create_followup_todo() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $parent_id = intval($_POST['parent_id']);
        $title = sanitize_text_field($_POST['title']);
        $include_plan = $_POST['include_plan'] === 'true';
        
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        // Get parent todo
        $parent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $parent_id
        ));
        
        if (!$parent) {
            wp_send_json_error(['message' => 'Parent Todo nicht gefunden']);
            return;
        }
        
        // Create description with context
        $description = "=== KONTEXT AUS TODO #$parent_id ===\n\n";
        $description .= "Ursprünglicher Titel: " . $parent->title . "\n";
        $description .= "Ursprüngliche Beschreibung:\n" . $parent->description . "\n\n";
        
        if ($include_plan && $parent->plan_html) {
            $description .= "=== CLAUDE PLAN ===\n\n";
            $description .= strip_tags($parent->plan_html) . "\n\n";
        }
        
        if ($parent->claude_notes) {
            $description .= "=== CLAUDE NOTIZEN ===\n\n";
            $description .= $parent->claude_notes . "\n\n";
        }
        
        $description .= "=== NEUE ANFORDERUNGEN ===\n\n";
        $description .= $_POST['new_requirements'] ?? '';
        
        // Insert new todo
        $result = $wpdb->insert(
            $table,
            [
                'title' => $title,
                'description' => $description,
                'scope' => $parent->scope,
                'status' => 'pending',
                'priority' => $parent->priority,
                'bearbeiten' => 1,
                'is_planning_mode' => $_POST['continue_planning'] === 'true' ? 1 : 0,
                'created_at' => current_time('mysql'),
                'arbeitsverzeichnis' => $parent->arbeitsverzeichnis
            ]
        );
        
        if ($result) {
            $new_id = $wpdb->insert_id;
            wp_send_json_success([
                'message' => 'Followup Todo erstellt',
                'new_id' => $new_id,
                'redirect' => admin_url('admin.php?page=wp-project-todos-new&id=' . $new_id)
            ]);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Erstellen']);
        }
    }
    
    /**
     * Render plan viewer
     */
    public function render_plan_viewer($todo_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        $todo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $todo_id
        ));
        
        if (!$todo || !$todo->plan_html) {
            return '<p>Kein Plan vorhanden</p>';
        }
        
        ?>
        <div class="plan-viewer" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>📋 Claude Plan für: <?php echo esc_html($todo->title); ?></h3>
                <div>
                    <button class="button button-secondary" onclick="copyPlanToClipboard()">📋 Plan kopieren</button>
                    <button class="button button-primary" onclick="openPlanInNewTab()">🔗 In neuem Tab öffnen</button>
                    <button class="button button-primary" onclick="createFollowupTodo()">➡️ Weiterführen</button>
                </div>
            </div>
            
            <div id="plan-content" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 3px; max-height: 500px; overflow-y: auto;">
                <?php echo wp_kses_post($todo->plan_html); ?>
            </div>
            
            <script>
            function copyPlanToClipboard() {
                const planText = document.getElementById('plan-content').innerText;
                navigator.clipboard.writeText(planText).then(function() {
                    alert('Plan in Zwischenablage kopiert!');
                });
            }
            
            function openPlanInNewTab() {
                const planHtml = document.getElementById('plan-content').innerHTML;
                const newWindow = window.open('', '_blank');
                newWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Claude Plan - <?php echo esc_js($todo->title); ?></title>
                        <style>
                            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
                            h1, h2, h3 { color: #333; }
                            code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
                            pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
                            .action-buttons { position: fixed; top: 20px; right: 20px; background: white; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 5px; }
                            .action-buttons button { margin: 5px; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer; }
                            .copy-btn { background: #667eea; color: white; }
                            .followup-btn { background: #48bb78; color: white; }
                        </style>
                    </head>
                    <body>
                        <div class="action-buttons">
                            <button class="copy-btn" onclick="copyToClipboard()">📋 Kopieren</button>
                            <button class="followup-btn" onclick="window.opener.createFollowupTodo(); window.close();">➡️ Weiterführen</button>
                        </div>
                        <h1>Claude Plan: <?php echo esc_js($todo->title); ?></h1>
                        <p><em>Erstellt: <?php echo esc_js($todo->plan_created_at); ?></em></p>
                        <hr>
                        ${planHtml}
                        <script>
                            function copyToClipboard() {
                                const content = document.body.innerText;
                                navigator.clipboard.writeText(content).then(() => {
                                    alert('Plan wurde in die Zwischenablage kopiert!');
                                });
                            }
                        </script>
                    </body>
                    </html>
                `);
                newWindow.document.close();
            }
            
            function createFollowupTodo() {
                const title = prompt('Titel für Followup-Todo:', 'Weiterführung: <?php echo esc_js($todo->title); ?>');
                if (!title) return;
                
                const includePlan = confirm('Claude Plan in neues Todo übernehmen?');
                const continuePlanning = confirm('Im Planungsmodus fortfahren?');
                const newRequirements = prompt('Zusätzliche Anforderungen/Notizen:');
                
                jQuery.post(ajaxurl, {
                    action: 'create_followup_todo',
                    nonce: '<?php echo wp_create_nonce('wp_project_todos_nonce'); ?>',
                    parent_id: <?php echo $todo->id; ?>,
                    title: title,
                    include_plan: includePlan,
                    continue_planning: continuePlanning,
                    new_requirements: newRequirements
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.href = response.data.redirect;
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX handler für strukturierten Plan-Editor
     */
    public function ajax_save_structured_plan() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $plan_data = $_POST['plan_data'];
        
        if (!$todo_id || !$plan_data) {
            wp_send_json_error(['message' => 'Fehlende Daten']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        // Plan-Parser laden
        if (!class_exists('WP_Project_Todos\Plan_Parser')) {
            require_once plugin_dir_path(__FILE__) . 'class-plan-parser.php';
        }
        $parser = new Plan_Parser();
        
        $plan_html = '';
        $structure_json = '';
        
        if ($plan_data['mode'] === 'structured') {
            // Strukturierte Daten zu HTML konvertieren
            $structure = $plan_data['structure'];
            $plan_html = $parser->structure_to_html($structure);
            $structure_json = json_encode($structure, JSON_UNESCAPED_UNICODE);
        } else {
            // Direktes HTML
            $plan_html = wp_kses_post($plan_data['html']);
            // Versuche HTML zu strukturieren für Backup
            $structure = $parser->parse_html_to_structure($plan_html);
            $structure_json = json_encode($structure, JSON_UNESCAPED_UNICODE);
        }
        
        $result = $wpdb->update(
            $table,
            [
                'plan_html' => $plan_html,
                'plan_structure' => $structure_json, // Neue Spalte für strukturierte Daten
                'plan_created_at' => current_time('mysql'),
                'is_planning_mode' => 1
            ],
            ['id' => $todo_id]
        );
        
        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Plan erfolgreich gespeichert',
                'html' => $plan_html
            ]);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }
    }
    
    /**
     * AJAX handler für Plan-Vorschau Generierung
     */
    public function ajax_generate_plan_preview() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $structure = $_POST['structure'];
        
        if (!$structure) {
            wp_send_json_error(['message' => 'Keine Struktur-Daten erhalten']);
            return;
        }
        
        // Plan-Parser laden
        if (!class_exists('WP_Project_Todos\Plan_Parser')) {
            require_once plugin_dir_path(__FILE__) . 'class-plan-parser.php';
        }
        $parser = new Plan_Parser();
        
        // Struktur zu HTML konvertieren
        $html = $parser->structure_to_html($structure);
        
        wp_send_json_success([
            'html' => $html
        ]);
    }
    
    /**
     * AJAX handler für Laden des strukturierten Editors
     */
    public function ajax_load_structured_plan_editor() {
        check_ajax_referer('wp_project_todos_nonce', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        
        if (!$todo_id) {
            wp_send_json_error(['message' => 'Keine Todo-ID erhalten']);
            return;
        }
        
        $html = $this->render_structured_plan_editor($todo_id);
        
        wp_send_json_success([
            'html' => $html
        ]);
    }
    
    /**
     * Render strukturierten Plan-Editor
     */
    public function render_structured_plan_editor($todo_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        $todo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $todo_id
        ));
        
        if (!$todo) {
            return '<p>Todo nicht gefunden</p>';
        }
        
        // Template für strukturierten Editor laden
        $template_path = plugin_dir_path(__FILE__) . '../admin/views/structured-plan-editor.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            return '<p>Editor-Template nicht gefunden: ' . $template_path . '</p>';
        }
    }
}