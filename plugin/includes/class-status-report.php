<?php
/**
 * Status Report Generator
 * Generates HTML reports when todo status changes from in_progress
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Status_Report {
    
    private $wpdb;
    private $model;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->model = new Todo_Model();
        
        // Hook into status changes
        add_action('wp_project_todos_status_changed', [$this, 'generate_report_on_status_change'], 10, 3);
        
        // Add AJAX handlers
        add_action('wp_ajax_generate_status_report', [$this, 'ajax_generate_report']);
        add_action('wp_ajax_create_followup_from_report', [$this, 'ajax_create_followup']);
    }
    
    /**
     * Generate report when status changes from in_progress
     */
    public function generate_report_on_status_change($todo_id, $old_status, $new_status) {
        // Only generate report when moving FROM in_progress
        if ($old_status !== 'in_progress') {
            return;
        }
        
        $todo = $this->model->get_by_id($todo_id);
        if (!$todo) {
            return;
        }
        
        $report_html = $this->generate_html_report($todo, $old_status, $new_status);
        
        // Save report to database
        $this->save_report($todo_id, $report_html);
        
        // Update todo with report reference
        $this->wpdb->update(
            $this->wpdb->prefix . 'project_todos',
            ['last_report' => current_time('mysql')],
            ['id' => $todo_id]
        );
    }
    
    /**
     * Generate HTML report
     */
    public function generate_html_report($todo, $old_status, $new_status) {
        $status_emoji = [
            'pending' => '⏳',
            'offen' => '📝',
            'in_progress' => '🔄',
            'completed' => '✅',
            'blocked' => '🚫'
        ];
        
        $html = '<div class="todo-status-report" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 800px; margin: 20px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
        
        // Header
        $html .= '<div style="border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">';
        $html .= '<h1 style="margin: 0; color: #2c3e50; font-size: 28px;">📊 Status-Änderungsbericht</h1>';
        $html .= '<p style="color: #7f8c8d; margin-top: 10px;">Todo #' . $todo->id . ' - ' . date('d.m.Y H:i') . '</p>';
        $html .= '</div>';
        
        // Status Change
        $html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">';
        $html .= '<h2 style="margin: 0 0 10px 0; font-size: 20px;">Status-Änderung</h2>';
        $html .= '<div style="display: flex; align-items: center; gap: 20px; font-size: 18px;">';
        $html .= '<span>' . $status_emoji[$old_status] . ' ' . ucfirst($old_status) . '</span>';
        $html .= '<span>→</span>';
        $html .= '<span>' . $status_emoji[$new_status] . ' ' . ucfirst($new_status) . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Todo Details
        $html .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">';
        $html .= '<h2 style="color: #2c3e50; margin-top: 0;">📋 Aufgabendetails</h2>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px 0; color: #7f8c8d;">Titel:</td><td style="padding: 8px 0; font-weight: bold;">' . esc_html($todo->title) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; color: #7f8c8d;">Bereich:</td><td style="padding: 8px 0;">' . esc_html($todo->scope) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; color: #7f8c8d;">Priorität:</td><td style="padding: 8px 0;">' . ucfirst($todo->priority) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px 0; color: #7f8c8d;">Arbeitsverzeichnis:</td><td style="padding: 8px 0;"><code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . esc_html($todo->working_directory) . '</code></td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Description
        if (!empty($todo->description)) {
            $html .= '<div style="margin-bottom: 30px;">';
            $html .= '<h2 style="color: #2c3e50;">📝 Beschreibung</h2>';
            $html .= '<div style="background: #fff; border: 1px solid #dee2e6; padding: 20px; border-radius: 8px;">';
            $html .= '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: inherit;">' . esc_html($todo->description) . '</pre>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // What was achieved
        if ($new_status === 'completed' && !empty($todo->claude_notes)) {
            $html .= '<div style="margin-bottom: 30px;">';
            $html .= '<h2 style="color: #2c3e50;">✅ Was wurde erreicht</h2>';
            $html .= '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px;">';
            $html .= '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; color: #155724;">' . esc_html($todo->claude_notes) . '</pre>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // What was planned (if blocked or not completed)
        if ($new_status === 'blocked') {
            $html .= '<div style="margin-bottom: 30px;">';
            $html .= '<h2 style="color: #2c3e50;">🚫 Blockierung</h2>';
            $html .= '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 8px;">';
            $html .= '<p style="margin: 0; color: #721c24;">Diese Aufgabe wurde blockiert und konnte nicht fortgesetzt werden.</p>';
            if (!empty($todo->bemerkungen)) {
                $html .= '<p style="margin: 10px 0 0 0; color: #721c24;"><strong>Grund:</strong> ' . esc_html($todo->bemerkungen) . '</p>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Related files
        if (!empty($todo->related_files)) {
            $html .= '<div style="margin-bottom: 30px;">';
            $html .= '<h2 style="color: #2c3e50;">📁 Betroffene Dateien</h2>';
            $html .= '<div style="background: #fff; border: 1px solid #dee2e6; padding: 20px; border-radius: 8px;">';
            $html .= '<pre style="margin: 0; font-family: \'JetBrains Mono\', monospace; font-size: 13px;">' . esc_html($todo->related_files) . '</pre>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Action buttons
        $html .= '<div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #dee2e6;">';
        $html .= '<h2 style="color: #2c3e50;">🚀 Nächste Schritte</h2>';
        $html .= '<div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;">';
        
        // Create follow-up button
        $html .= '<button onclick="createFollowupTodo(' . $todo->id . ')" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: bold;">➕ Folge-Todo erstellen</button>';
        
        // Copy report button
        $html .= '<button onclick="copyReportToClipboard(' . $todo->id . ')" style="background: #007bff; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: bold;">📋 Bericht kopieren</button>';
        
        // Download as HTML
        $html .= '<button onclick="downloadReport(' . $todo->id . ')" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: bold;">💾 Als HTML speichern</button>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add JavaScript for buttons
        $html .= '<script>
        function createFollowupTodo(todoId) {
            if (confirm("Möchten Sie ein Folge-Todo mit den Informationen aus diesem Bericht erstellen?")) {
                jQuery.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "create_followup_from_report",
                        todo_id: todoId,
                        nonce: "' . wp_create_nonce('create_followup') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Folge-Todo #" + response.data.new_todo_id + " wurde erstellt!");
                            window.location.href = response.data.edit_url;
                        }
                    }
                });
            }
        }
        
        function copyReportToClipboard(todoId) {
            const reportContent = document.querySelector(".todo-status-report").innerText;
            navigator.clipboard.writeText(reportContent).then(() => {
                alert("Bericht wurde in die Zwischenablage kopiert!");
            });
        }
        
        function downloadReport(todoId) {
            const reportHtml = document.querySelector(".todo-status-report").outerHTML;
            const blob = new Blob([reportHtml], { type: "text/html" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "todo-" + todoId + "-report.html";
            a.click();
            URL.revokeObjectURL(url);
        }
        </script>';
        
        return $html;
    }
    
    /**
     * Save report to database
     */
    private function save_report($todo_id, $html) {
        $table_name = $this->wpdb->prefix . 'project_todo_reports';
        
        // Create table if not exists
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            todo_id mediumint(9) NOT NULL,
            report_html longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY todo_id (todo_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert report
        $this->wpdb->insert(
            $table_name,
            [
                'todo_id' => $todo_id,
                'report_html' => $html,
                'created_at' => current_time('mysql')
            ]
        );
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * AJAX handler to generate report
     */
    public function ajax_generate_report() {
        check_ajax_referer('generate_report', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $old_status = sanitize_text_field($_POST['old_status']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        $todo = $this->model->get_by_id($todo_id);
        if (!$todo) {
            wp_send_json_error('Todo nicht gefunden');
        }
        
        $html = $this->generate_html_report($todo, $old_status, $new_status);
        $report_id = $this->save_report($todo_id, $html);
        
        wp_send_json_success([
            'report_id' => $report_id,
            'html' => $html
        ]);
    }
    
    /**
     * AJAX handler to create follow-up todo
     */
    public function ajax_create_followup() {
        check_ajax_referer('create_followup', 'nonce');
        
        $todo_id = intval($_POST['todo_id']);
        $original_todo = $this->model->get_by_id($todo_id);
        
        if (!$original_todo) {
            wp_send_json_error('Original-Todo nicht gefunden');
        }
        
        // Create new todo with report information
        $new_todo_data = [
            'title' => '[FOLGE] ' . $original_todo->title,
            'description' => $this->build_followup_description($original_todo),
            'scope' => $original_todo->scope,
            'status' => 'pending',
            'priority' => $original_todo->priority,
            'bearbeiten' => 1,
            'working_directory' => $original_todo->working_directory,
            'assigned_to' => 'claude',
            'parent_todo_id' => $todo_id,
            'related_files' => $original_todo->related_files
        ];
        
        $new_todo_id = $this->model->create($new_todo_data);
        
        wp_send_json_success([
            'new_todo_id' => $new_todo_id,
            'edit_url' => admin_url('admin.php?page=wp-project-todos-new&id=' . $new_todo_id)
        ]);
    }
    
    /**
     * Build follow-up description
     */
    private function build_followup_description($original_todo) {
        $description = "=== FOLGE-TODO VON #" . $original_todo->id . " ===\n\n";
        $description .= "URSPRÜNGLICHER PROMPT:\n";
        $description .= $original_todo->description . "\n\n";
        
        if (!empty($original_todo->claude_notes)) {
            $description .= "BISHERIGE ARBEIT:\n";
            $description .= $original_todo->claude_notes . "\n\n";
        }
        
        if (!empty($original_todo->bemerkungen)) {
            $description .= "NOTIZEN:\n";
            $description .= $original_todo->bemerkungen . "\n\n";
        }
        
        $description .= "=== NEUE ANFORDERUNGEN ===\n";
        $description .= "[Hier neue Anforderungen eingeben]\n";
        
        return $description;
    }
    
    /**
     * Get latest report for a todo
     */
    public function get_latest_report($todo_id) {
        $table_name = $this->wpdb->prefix . 'project_todo_reports';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE todo_id = %d ORDER BY created_at DESC LIMIT 1",
            $todo_id
        ));
    }
}