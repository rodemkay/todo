<?php
/**
 * Report Generator für Todo HTML-Berichte
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Report_Generator {
    
    private $wpdb;
    private $table;
    private $reports_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'project_todos';
        $this->reports_table = $wpdb->prefix . 'project_todo_reports';
    }
    
    /**
     * Generiert einen vollständigen HTML-Bericht für ein Todo
     */
    public function generate_html_report($todo_id, $type = 'progress') {
        $todo = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $todo_id
        ));
        
        if (!$todo) {
            return new \WP_Error('todo_not_found', 'Todo nicht gefunden');
        }
        
        // Hole Fortsetzungs-Historie
        $continuations = $this->get_continuation_history($todo_id);
        
        // Hole verwandte Todos
        $related_todos = $this->get_related_todos($todo_id);
        
        // Generiere HTML
        $html = $this->build_html_report($todo, $continuations, $related_todos, $type);
        
        // Speichere Bericht
        $report_id = $this->save_report($todo_id, $html, $type);
        
        return [
            'report_id' => $report_id,
            'html' => $html,
            'url' => $this->get_report_url($report_id)
        ];
    }
    
    /**
     * Baut den HTML-Bericht
     */
    private function build_html_report($todo, $continuations, $related_todos, $type) {
        $status_colors = [
            'pending' => '#ffc107',
            'in_progress' => '#17a2b8',
            'completed' => '#28a745',
            'blocked' => '#dc3545',
            'cancelled' => '#6c757d'
        ];
        
        $priority_colors = [
            'low' => '#6c757d',
            'medium' => '#17a2b8',
            'high' => '#ffc107',
            'critical' => '#dc3545'
        ];
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Todo #<?php echo $todo->id; ?>: <?php echo esc_html($todo->title); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    padding: 20px;
                }
                
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                
                .header {
                    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
                    color: white;
                    padding: 40px;
                    position: relative;
                }
                
                .header h1 {
                    font-size: 2.5em;
                    margin-bottom: 10px;
                    font-weight: 300;
                }
                
                .header .meta {
                    display: flex;
                    gap: 20px;
                    flex-wrap: wrap;
                    margin-top: 20px;
                }
                
                .badge {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 0.9em;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .status-badge {
                    background: <?php echo $status_colors[$todo->status] ?? '#6c757d'; ?>;
                    color: white;
                }
                
                .priority-badge {
                    background: <?php echo $priority_colors[$todo->priority] ?? '#6c757d'; ?>;
                    color: white;
                }
                
                .content {
                    padding: 40px;
                }
                
                .section {
                    margin-bottom: 40px;
                    padding-bottom: 30px;
                    border-bottom: 1px solid #e0e0e0;
                }
                
                .section:last-child {
                    border-bottom: none;
                }
                
                .section h2 {
                    color: #2c3e50;
                    margin-bottom: 20px;
                    font-size: 1.8em;
                    font-weight: 400;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .section h2::before {
                    content: '';
                    width: 4px;
                    height: 24px;
                    background: #667eea;
                    border-radius: 2px;
                }
                
                .description-box {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #667eea;
                    white-space: pre-wrap;
                    font-family: 'JetBrains Mono', 'Courier New', monospace;
                    font-size: 0.95em;
                }
                
                .timeline {
                    position: relative;
                    padding-left: 40px;
                }
                
                .timeline::before {
                    content: '';
                    position: absolute;
                    left: 10px;
                    top: 0;
                    bottom: 0;
                    width: 2px;
                    background: #e0e0e0;
                }
                
                .timeline-item {
                    position: relative;
                    margin-bottom: 25px;
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                }
                
                .timeline-item::before {
                    content: '';
                    position: absolute;
                    left: -34px;
                    top: 20px;
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    background: #667eea;
                    border: 3px solid white;
                    box-shadow: 0 0 0 2px #e0e0e0;
                }
                
                .timeline-date {
                    color: #6c757d;
                    font-size: 0.85em;
                    margin-bottom: 5px;
                }
                
                .code-block {
                    background: #1e1e1e;
                    color: #d4d4d4;
                    padding: 20px;
                    border-radius: 8px;
                    overflow-x: auto;
                    font-family: 'JetBrains Mono', 'Courier New', monospace;
                    font-size: 0.9em;
                    line-height: 1.5;
                }
                
                .action-buttons {
                    display: flex;
                    gap: 15px;
                    flex-wrap: wrap;
                    margin-top: 30px;
                }
                
                .btn {
                    padding: 12px 24px;
                    border: none;
                    border-radius: 6px;
                    font-size: 1em;
                    font-weight: 500;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.3s ease;
                }
                
                .btn-primary {
                    background: #667eea;
                    color: white;
                }
                
                .btn-primary:hover {
                    background: #5a67d8;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                
                .btn-success {
                    background: #28a745;
                    color: white;
                }
                
                .btn-warning {
                    background: #ffc107;
                    color: #333;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                
                .stat-card {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    text-align: center;
                }
                
                .stat-value {
                    font-size: 2em;
                    font-weight: 600;
                    color: #667eea;
                    margin-bottom: 5px;
                }
                
                .stat-label {
                    color: #6c757d;
                    font-size: 0.9em;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .related-todos {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                
                .related-todo-card {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    border: 1px solid #e0e0e0;
                    transition: all 0.3s ease;
                }
                
                .related-todo-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                
                .footer {
                    background: #f8f9fa;
                    padding: 20px 40px;
                    text-align: center;
                    color: #6c757d;
                    font-size: 0.9em;
                }
                
                @media (max-width: 768px) {
                    .header {
                        padding: 30px 20px;
                    }
                    
                    .header h1 {
                        font-size: 1.8em;
                    }
                    
                    .content {
                        padding: 20px;
                    }
                    
                    .stats-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Todo #<?php echo $todo->id; ?>: <?php echo esc_html($todo->title); ?></h1>
                    <div class="meta">
                        <span class="badge status-badge"><?php echo ucfirst(str_replace('_', ' ', $todo->status)); ?></span>
                        <span class="badge priority-badge"><?php echo ucfirst($todo->priority); ?> Priority</span>
                        <span class="badge"><?php echo ucfirst($todo->scope); ?></span>
                        <?php if ($todo->bearbeiten): ?>
                            <span class="badge" style="background: #28a745;">Claude Active</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="content">
                    <!-- Statistiken -->
                    <div class="section">
                        <h2>📊 Übersicht</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todo->continuation_count ?? 0; ?></div>
                                <div class="stat-label">Fortsetzungen</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todo->attachment_count ?? 0; ?></div>
                                <div class="stat-label">Anhänge</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todo->estimated_hours ?? '0'; ?>h</div>
                                <div class="stat-label">Geschätzt</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todo->actual_hours ?? '0'; ?>h</div>
                                <div class="stat-label">Tatsächlich</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Beschreibung -->
                    <div class="section">
                        <h2>📝 Beschreibung</h2>
                        <div class="description-box"><?php echo esc_html($todo->description); ?></div>
                    </div>
                    
                    <!-- Claude Notes -->
                    <?php if (!empty($todo->claude_notes)): ?>
                    <div class="section">
                        <h2>🤖 Claude Notes</h2>
                        <div class="timeline">
                            <?php 
                            $notes = explode("\n", $todo->claude_notes);
                            foreach ($notes as $note):
                                if (preg_match('/^\[(.*?)\](.*)/', $note, $matches)):
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo esc_html($matches[1]); ?></div>
                                <div><?php echo esc_html($matches[2]); ?></div>
                            </div>
                            <?php 
                                else:
                                    echo '<div class="timeline-item">' . esc_html($note) . '</div>';
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Claude Output -->
                    <?php if (!empty($todo->claude_output)): ?>
                    <div class="section">
                        <h2>💻 Claude Output</h2>
                        <div class="code-block"><?php echo esc_html($todo->claude_output); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Fortsetzungshinweise -->
                    <?php if (!empty($todo->continuation_notes)): ?>
                    <div class="section">
                        <h2>🔄 Fortsetzungshinweise</h2>
                        <div class="description-box"><?php echo esc_html($todo->continuation_notes); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Verwandte Todos -->
                    <?php if (!empty($related_todos)): ?>
                    <div class="section">
                        <h2>🔗 Verwandte Todos</h2>
                        <div class="related-todos">
                            <?php foreach ($related_todos as $related): ?>
                            <div class="related-todo-card">
                                <h3>Todo #<?php echo $related->id; ?></h3>
                                <p><strong><?php echo esc_html($related->title); ?></strong></p>
                                <p><?php echo esc_html(substr($related->description, 0, 100)); ?>...</p>
                                <span class="badge" style="background: <?php echo $status_colors[$related->status]; ?>;">
                                    <?php echo ucfirst($related->status); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Fortsetzungs-Historie -->
                    <?php if (!empty($continuations)): ?>
                    <div class="section">
                        <h2>📜 Fortsetzungs-Historie</h2>
                        <div class="timeline">
                            <?php foreach ($continuations as $cont): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo esc_html($cont->created_at); ?></div>
                                <div>
                                    <strong>Von Todo #<?php echo $cont->original_todo_id; ?> zu #<?php echo $cont->continued_todo_id; ?></strong><br>
                                    <?php echo esc_html($cont->continuation_reason); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="section">
                        <h2>🚀 Aktionen</h2>
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="continueTodo()">
                                📝 Weiterführen
                            </button>
                            <button class="btn btn-success" onclick="createNewTodo()">
                                ➕ Neues Todo erstellen
                            </button>
                            <button class="btn btn-warning" onclick="createPlan()">
                                📋 Als Plan speichern
                            </button>
                            <button class="btn" onclick="window.print()">
                                🖨️ Drucken
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Generiert am <?php echo date('d.m.Y H:i:s'); ?> | Todo-System v2.0</p>
                    <p>Bericht-Typ: <?php echo ucfirst($type); ?> | Todo ID: <?php echo $todo->id; ?></p>
                </div>
            </div>
            
            <script>
                function continueTodo() {
                    window.location.href = '<?php echo admin_url('admin.php?page=wp-project-todos&action=continue&id=' . $todo->id); ?>';
                }
                
                function createNewTodo() {
                    window.location.href = '<?php echo admin_url('admin.php?page=wp-project-todos-new&parent_id=' . $todo->id); ?>';
                }
                
                function createPlan() {
                    if (confirm('Möchten Sie diesen Bericht als Plan speichern?')) {
                        // AJAX call to save as plan
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=save_as_plan&todo_id=<?php echo $todo->id; ?>&nonce=<?php echo wp_create_nonce('save_plan'); ?>'
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Plan erfolgreich gespeichert!');
                            }
                        });
                    }
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Holt Fortsetzungs-Historie
     */
    private function get_continuation_history($todo_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}project_todo_continuations 
             WHERE original_todo_id = %d OR continued_todo_id = %d 
             ORDER BY created_at DESC",
            $todo_id, $todo_id
        ));
    }
    
    /**
     * Holt verwandte Todos
     */
    private function get_related_todos($todo_id) {
        $todo = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT parent_todo_id FROM {$this->table} WHERE id = %d",
            $todo_id
        ));
        
        if (!$todo || !$todo->parent_todo_id) {
            return [];
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, title, description, status FROM {$this->table} 
             WHERE parent_todo_id = %d AND id != %d
             ORDER BY created_at DESC LIMIT 5",
            $todo->parent_todo_id, $todo_id
        ));
    }
    
    /**
     * Speichert den Bericht in der Datenbank
     */
    private function save_report($todo_id, $html, $type) {
        $summary = $this->generate_summary($html);
        
        $this->wpdb->insert(
            $this->reports_table,
            [
                'todo_id' => $todo_id,
                'report_type' => $type,
                'report_html' => $html,
                'report_summary' => $summary,
                'created_by' => get_current_user_id()
            ]
        );
        
        $report_id = $this->wpdb->insert_id;
        
        // Update Todo mit Report URL
        $report_url = admin_url('admin.php?page=todo-report&id=' . $report_id);
        $this->wpdb->update(
            $this->table,
            ['report_url' => $report_url],
            ['id' => $todo_id]
        );
        
        return $report_id;
    }
    
    /**
     * Generiert eine Zusammenfassung des Berichts
     */
    private function generate_summary($html) {
        // Entferne HTML-Tags und kürze auf 500 Zeichen
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        return substr($text, 0, 500) . '...';
    }
    
    /**
     * Gibt die URL zum Bericht zurück
     */
    public function get_report_url($report_id) {
        return admin_url('admin.php?page=todo-report&id=' . $report_id);
    }
}