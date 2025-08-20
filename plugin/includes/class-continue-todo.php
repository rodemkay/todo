<?php
/**
 * Continue Todo Handler - Weiterführung von Todos
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Continue_Todo {
    
    private $model;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->model = new Todo_Model();
        
        // Register admin page
        add_action('admin_menu', [$this, 'add_admin_page']);
        
        // Register AJAX handlers
        add_action('wp_ajax_continue_todo', [$this, 'ajax_continue_todo']);
        add_action('wp_ajax_generate_report', [$this, 'ajax_generate_report']);
    }
    
    /**
     * Add admin page for continuation
     */
    public function add_admin_page() {
        add_submenu_page(
            null, // Hidden from menu
            'Todo weiterführen',
            'Todo weiterführen',
            'read', // Jeder eingeloggte User kann darauf zugreifen
            'wp-project-todos-continue',
            [$this, 'render_continue_page']
        );
        
        // Report viewer page
        add_submenu_page(
            null, // Hidden from menu
            'Todo Bericht',
            'Todo Bericht',
            'read', // Jeder eingeloggte User kann darauf zugreifen
            'wp-project-todos-report',
            [$this, 'render_report_page']
        );
    }
    
    /**
     * Render continuation form
     */
    public function render_continue_page() {
        $todo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$todo_id) {
            wp_die('Keine Todo-ID angegeben');
        }
        
        $todo = $this->model->get_by_id($todo_id);
        
        if (!$todo) {
            wp_die('Todo nicht gefunden');
        }
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['continue_nonce'], 'continue_todo_' . $todo_id)) {
            $this->handle_continuation($todo_id, $_POST);
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>📝 Todo #<?php echo $todo->id; ?> weiterführen</h1>
            
            <style>
                .continue-form {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-top: 20px;
                }
                
                .todo-info {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 30px;
                    border-left: 4px solid #667eea;
                }
                
                .todo-info h2 {
                    margin-top: 0;
                    color: #2c3e50;
                }
                
                .history-section {
                    background: #fff;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                    max-height: 300px;
                    overflow-y: auto;
                }
                
                .form-section {
                    margin-bottom: 25px;
                }
                
                .form-section label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #2c3e50;
                }
                
                .form-section textarea {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 14px;
                }
                
                .checkbox-group {
                    display: flex;
                    gap: 20px;
                    margin-top: 10px;
                }
                
                .checkbox-group label {
                    display: flex;
                    align-items: center;
                    font-weight: normal;
                }
                
                .checkbox-group input[type="checkbox"] {
                    margin-right: 8px;
                }
                
                .button-group {
                    display: flex;
                    gap: 10px;
                    margin-top: 30px;
                }
                
                .button-primary {
                    background: #667eea !important;
                    border-color: #667eea !important;
                    padding: 8px 20px !important;
                }
                
                .button-success {
                    background: #28a745 !important;
                    border-color: #28a745 !important;
                    color: white !important;
                    padding: 8px 20px !important;
                }
            </style>
            
            <div class="continue-form">
                <!-- Todo Info -->
                <div class="todo-info">
                    <h2><?php echo esc_html($todo->title); ?></h2>
                    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $todo->status)); ?></p>
                    <p><strong>Priorität:</strong> <?php echo ucfirst($todo->priority); ?></p>
                    <p><strong>Beschreibung:</strong></p>
                    <div style="background: white; padding: 15px; border-radius: 4px; margin-top: 10px;">
                        <?php echo nl2br(esc_html($todo->description)); ?>
                    </div>
                </div>
                
                <!-- Historie -->
                <?php if (!empty($todo->claude_notes)): ?>
                <div class="form-section">
                    <label>📜 Bisherige Historie:</label>
                    <div class="history-section">
                        <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($todo->claude_notes); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Continuation Form -->
                <form method="post">
                    <?php wp_nonce_field('continue_todo_' . $todo_id, 'continue_nonce'); ?>
                    
                    <div class="form-section">
                        <label for="continuation_reason">🔄 Grund für Weiterführung:</label>
                        <textarea name="continuation_reason" id="continuation_reason" rows="3" required 
                                  placeholder="z.B. Fehler aufgetreten, zusätzliche Features benötigt, etc."><?php echo isset($_POST['continuation_reason']) ? esc_textarea($_POST['continuation_reason']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <label for="continuation_notes">📝 Weiterführungshinweise / Neuer Prompt:</label>
                        <textarea name="continuation_notes" id="continuation_notes" rows="6" required 
                                  placeholder="Beschreiben Sie, was als nächstes getan werden soll. Neue Ideen, Korrekturen, zusätzliche Anforderungen..."><?php echo isset($_POST['continuation_notes']) ? esc_textarea($_POST['continuation_notes']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <label for="additional_context">🎯 Zusätzlicher Kontext (optional):</label>
                        <textarea name="additional_context" id="additional_context" rows="4" 
                                  placeholder="Weitere wichtige Informationen, die Claude wissen sollte..."><?php echo isset($_POST['additional_context']) ? esc_textarea($_POST['additional_context']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-section">
                        <label>⚙️ Optionen:</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="create_new_todo" value="1" checked>
                                Neues Todo erstellen (behält Original)
                            </label>
                            <label>
                                <input type="checkbox" name="plan_mode" value="1">
                                Im Planungsmodus starten
                            </label>
                            <label>
                                <input type="checkbox" name="high_priority" value="1">
                                Hohe Priorität setzen
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <label for="claude_mode">🤖 Claude Modus:</label>
                        <select name="claude_mode" id="claude_mode" class="regular-text">
                            <option value="bypass">Bypass (Automatische Ausführung)</option>
                            <option value="plan" <?php echo isset($_POST['plan_mode']) && $_POST['plan_mode'] ? 'selected' : ''; ?>>Plan (Nur Planung)</option>
                            <option value="default">Default (Mit Bestätigungen)</option>
                        </select>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" name="submit" class="button button-primary">
                            🚀 Todo weiterführen
                        </button>
                        <button type="submit" name="generate_prompt" class="button button-success">
                            📋 Nur Prompt generieren
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button">
                            ❌ Abbrechen
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Generated Prompt Display -->
            <?php if (isset($_POST['generate_prompt'])): ?>
            <div class="continue-form" style="margin-top: 20px;">
                <h2>📋 Generierter Claude-Prompt:</h2>
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: 'JetBrains Mono', monospace;">
                    <pre style="margin: 0; white-space: pre-wrap;"><?php echo $this->generate_claude_prompt($todo, $_POST); ?></pre>
                </div>
                <button onclick="copyToClipboard()" class="button" style="margin-top: 15px;">📋 In Zwischenablage kopieren</button>
            </div>
            
            <script>
            function copyToClipboard() {
                const text = document.querySelector('pre').textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Prompt wurde in die Zwischenablage kopiert!');
                });
            }
            </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle continuation submission
     */
    private function handle_continuation($original_todo_id, $data) {
        $original_todo = $this->model->get_by_id($original_todo_id);
        
        if ($data['create_new_todo']) {
            // Create new todo based on original
            $new_todo_data = [
                'title' => '[FORTSETZUNG] ' . $original_todo->title,
                'description' => $this->build_continued_description($original_todo, $data),
                'scope' => $original_todo->scope,
                'status' => 'pending',
                'priority' => isset($data['high_priority']) ? 'high' : $original_todo->priority,
                'bearbeiten' => 1,
                'working_directory' => $original_todo->working_directory,
                'assigned_to' => 'claude',
                'parent_todo_id' => $original_todo_id,
                'is_continuation' => 1,
                'continuation_notes' => $data['continuation_notes'],
                'claude_mode' => $data['claude_mode'] ?? 'bypass',
                'related_files' => $original_todo->related_files,
                'dependencies' => "Fortsetzung von Todo #$original_todo_id"
            ];
            
            $new_todo_id = $this->model->create($new_todo_data);
            
            // Log continuation
            $this->wpdb->insert(
                $this->wpdb->prefix . 'project_todo_continuations',
                [
                    'original_todo_id' => $original_todo_id,
                    'continued_todo_id' => $new_todo_id,
                    'continuation_reason' => $data['continuation_reason'],
                    'continuation_notes' => $data['continuation_notes'],
                    'created_by' => get_current_user_id()
                ]
            );
            
            // Update original todo
            $this->wpdb->update(
                $this->wpdb->prefix . 'project_todos',
                ['continuation_count' => $original_todo->continuation_count + 1],
                ['id' => $original_todo_id]
            );
            
            // Generate report for original todo
            $report_generator = new Report_Generator();
            $report = $report_generator->generate_html_report($original_todo_id, 'progress');
            
            ?>
            <div class="notice notice-success">
                <h2>✅ Todo erfolgreich fortgeführt!</h2>
                <p>Neues Todo #<?php echo $new_todo_id; ?> wurde erstellt.</p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $new_todo_id); ?>" class="button button-primary">
                        Todo bearbeiten
                    </a>
                    <a href="<?php echo $report['url']; ?>" class="button" target="_blank">
                        📄 Bericht anzeigen
                    </a>
                </p>
                
                <h3>📋 Claude-Prompt:</h3>
                <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    <pre style="margin: 0;"><?php echo $this->generate_claude_prompt($original_todo, $data); ?></pre>
                </div>
            </div>
            <?php
            
        } else {
            // Update existing todo
            $update_data = [
                'status' => 'in_progress',
                'continuation_notes' => $original_todo->continuation_notes . "\n\n--- " . date('Y-m-d H:i:s') . " ---\n" . $data['continuation_notes'],
                'claude_notes' => $original_todo->claude_notes . "\n[" . date('Y-m-d H:i:s') . "] Weiterführung: " . $data['continuation_reason']
            ];
            
            $this->model->update($original_todo_id, $update_data);
            
            echo '<div class="notice notice-success"><p>Todo aktualisiert!</p></div>';
        }
    }
    
    /**
     * Build continued description
     */
    private function build_continued_description($original_todo, $data) {
        $description = "=== FORTSETZUNG VON TODO #{$original_todo->id} ===\n\n";
        $description .= "GRUND: " . $data['continuation_reason'] . "\n\n";
        $description .= "NEUE ANFORDERUNGEN:\n" . $data['continuation_notes'] . "\n\n";
        
        if (!empty($data['additional_context'])) {
            $description .= "ZUSÄTZLICHER KONTEXT:\n" . $data['additional_context'] . "\n\n";
        }
        
        $description .= "=== URSPRÜNGLICHE BESCHREIBUNG ===\n";
        $description .= $original_todo->description;
        
        return $description;
    }
    
    /**
     * Generate Claude prompt
     */
    private function generate_claude_prompt($todo, $data) {
        $prompt = "Todo #{$todo->id}: {$todo->title}\n\n";
        
        if (isset($data['plan_mode']) && $data['plan_mode']) {
            $prompt .= "🎯 WICHTIG: Dies ist eine PLANUNGS-SESSION!\n";
            $prompt .= "- KEINE Änderungen durchführen\n";
            $prompt .= "- NUR Planung und Analyse\n";
            $prompt .= "- Ausgabe als strukturierter HTML-Bericht\n\n";
        }
        
        $prompt .= "AKTUELLE AUFGABE:\n";
        $prompt .= $data['continuation_notes'] . "\n\n";
        
        if (!empty($data['continuation_reason'])) {
            $prompt .= "GRUND FÜR FORTSETZUNG:\n";
            $prompt .= $data['continuation_reason'] . "\n\n";
        }
        
        if (!empty($data['additional_context'])) {
            $prompt .= "ZUSÄTZLICHER KONTEXT:\n";
            $prompt .= $data['additional_context'] . "\n\n";
        }
        
        $prompt .= "BISHERIGE HISTORIE:\n";
        $prompt .= $todo->claude_notes . "\n\n";
        
        $prompt .= "URSPRÜNGLICHE BESCHREIBUNG:\n";
        $prompt .= $todo->description . "\n\n";
        
        $prompt .= "ARBEITSVERZEICHNIS: {$todo->working_directory}\n";
        $prompt .= "PRIORITÄT: {$todo->priority}\n";
        $prompt .= "SCOPE: {$todo->scope}\n";
        
        if (!empty($todo->related_files)) {
            $prompt .= "\nRELEVANTE DATEIEN:\n{$todo->related_files}\n";
        }
        
        return $prompt;
    }
    
    /**
     * Render report page
     */
    public function render_report_page() {
        $todo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$todo_id) {
            // Try to get report ID instead
            $report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
            if ($report_id) {
                $report = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM {$this->wpdb->prefix}project_todo_reports WHERE id = %d",
                    $report_id
                ));
                
                if ($report) {
                    echo $report->report_html;
                    exit;
                }
            }
            wp_die('Kein Bericht gefunden');
        }
        
        // Generate new report
        $report_generator = new Report_Generator();
        $report = $report_generator->generate_html_report($todo_id, 'progress');
        
        echo $report['html'];
        exit;
    }
    
    /**
     * AJAX handler for continuing todo
     */
    public function ajax_continue_todo() {
        check_ajax_referer('continue_todo', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $data = $_POST;
        
        $this->handle_continuation($todo_id, $data);
        
        wp_send_json_success(['message' => 'Todo erfolgreich fortgeführt']);
    }
    
    /**
     * AJAX handler for generating report
     */
    public function ajax_generate_report() {
        check_ajax_referer('generate_report', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $report_generator = new Report_Generator();
        $report = $report_generator->generate_html_report($todo_id, 'progress');
        
        wp_send_json_success(['url' => $report['url']]);
    }
}