<?php
/**
 * Claude Integration class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Claude_Integration {
    
    /**
     * Todo model instance
     */
    private $model;
    
    /**
     * Current task ID
     */
    private $current_task_id = null;
    
    /**
     * Output buffer
     */
    private $output_buffer = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->model = new Todo_Model();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Claude command
        add_filter('claude_commands', [$this, 'register_todo_command']);
        
        // Output capture hooks
        add_action('claude_output', [$this, 'capture_output'], 10, 2);
        add_action('claude_error', [$this, 'capture_error'], 10, 2);
        
        // Context reload after compacting - DEAKTIVIERT wegen Speicherproblemen
        // add_action('claude_post_compact', [$this, 'reload_after_compact']);
    }
    
    /**
     * Register /todo command for Claude
     */
    public function register_todo_command($commands) {
        $commands['todo'] = [
            'description' => 'Nächste offene Aufgabe abrufen und bearbeiten',
            'callback' => [$this, 'handle_todo_command'],
        ];
        return $commands;
    }
    
    /**
     * Handle /todo command
     * This is called when user types /todo in Claude Code CLI
     */
    public function handle_todo_command($args = []) {
        // Check if we should continue automatically
        $auto_continue = isset($args['auto']) ? $args['auto'] : true;
        
        // Get next pending todo
        $todo = $this->model->get_next_pending();
        
        if (!$todo) {
            if ($auto_continue) {
                // Prüfe ob es blockierte oder andere Todos gibt
                global $wpdb;
                $table = $wpdb->prefix . 'project_todos';
                $blocked = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'blocked'");
                $in_progress = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'in_progress'");
                
                $message = "📭 KEINE WEITEREN TODOS VORHANDEN!\n";
                $message .= "═══════════════════════════════════════\n\n";
                $message .= "🎉 Alle Aufgaben wurden bearbeitet!\n\n";
                
                if ($blocked > 0) {
                    $message .= "⚠️ Es gibt $blocked blockierte Aufgaben.\n";
                }
                if ($in_progress > 0) {
                    $message .= "🔄 Es gibt $in_progress Aufgaben in Bearbeitung.\n";
                }
                
                $message .= "\n💡 Erstelle neue Aufgaben im WordPress Admin oder\n";
                $message .= "   löse die blockierten Aufgaben.\n\n";
                $message .= "🛑 AUTOMATIK-MODUS BEENDET.";
                
                return $this->format_response($message, 'success');
            }
            
            return $this->format_response(
                "📭 Keine offenen Aufgaben vorhanden!\n\n" .
                "Alle Aufgaben sind entweder in Bearbeitung oder abgeschlossen.\n" .
                "Erstelle neue Aufgaben im WordPress Admin unter 'Project To-Dos'.",
                'info'
            );
        }
        
        // Set current task
        $this->current_task_id = $todo->id;
        
        // Update status to in_progress
        $this->model->update_status($todo->id, 'in_progress');
        $this->model->add_comment($todo->id, 'Claude hat die Bearbeitung begonnen', true);
        
        // Start output capture
        $this->start_output_capture($todo->id);
        
        // Prepare working directory command
        $cd_command = '';
        if (!empty($todo->working_directory)) {
            $cd_command = "cd {$todo->working_directory}";
            $this->capture_output("📁 Wechsle zu: {$todo->working_directory}", 'info');
        }
        
        // Reload configurations - DEAKTIVIERT
        // $this->reload_configurations();
        // $this->capture_output("🔄 Konfigurationen neu geladen (CLAUDE.md, .env)", 'info');
        
        // Check if todo is in planning mode
        if ($todo->is_planning_mode && empty($todo->plan_html)) {
            // Todo ist im Planungsmodus aber hat noch keinen Plan
            $output = $this->format_planning_mode_display($todo);
        } else {
            // Normal task display
            $output = $this->format_task_display($todo);
        }
        
        // Capture initial output
        $this->capture_output($output, 'info');
        
        // Return formatted response for Claude CLI
        return [
            'success' => true,
            'output' => $output,
            'commands' => [
                'change_directory' => $cd_command,
                'reload_configs' => true,
            ],
            'task' => [
                'id' => $todo->id,
                'title' => $todo->title,
                'working_directory' => $todo->working_directory,
                'is_planning_mode' => $todo->is_planning_mode,
            ],
        ];
    }
    
    /**
     * Format planning mode display for CLI
     */
    private function format_planning_mode_display($todo) {
        $output = "╔════════════════════════════════════════════════════════════════╗\n";
        $output .= "║               📋 PLANUNGSMODUS AKTIVIERT                      ║\n";
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        
        // Title
        $title = $this->format_line("Titel", $todo->title);
        $output .= "║ $title ║\n";
        
        // Scope & Priority
        $scope_priority = $this->format_line("Bereich/Priorität", 
            $this->get_scope_emoji($todo->scope) . " " . ucfirst($todo->scope) . 
            " | " . $this->get_priority_emoji($todo->priority) . " " . ucfirst($todo->priority)
        );
        $output .= "║ $scope_priority ║\n";
        
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        $output .= "║ 🎯 PLANUNGSMODUS                                              ║\n";
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        $output .= "║                                                                ║\n";
        $output .= "║ Diese Aufgabe befindet sich im PLANUNGSMODUS.                 ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ Bitte erstelle einen detaillierten Plan mit folgenden         ║\n";
        $output .= "║ Punkten:                                                       ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ 1. ANALYSE DER ANFORDERUNGEN                                  ║\n";
        $output .= "║    - Was soll erreicht werden?                                ║\n";
        $output .= "║    - Welche Constraints gibt es?                              ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ 2. TECHNISCHE ÜBERLEGUNGEN                                    ║\n";
        $output .= "║    - Welche Technologien/Tools werden benötigt?               ║\n";
        $output .= "║    - Gibt es Dependencies?                                    ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ 3. IMPLEMENTATION SCHRITTE                                    ║\n";
        $output .= "║    - Schritt-für-Schritt Anleitung                           ║\n";
        $output .= "║    - Geschätzte Zeit pro Schritt                              ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ 4. TESTING & VALIDIERUNG                                      ║\n";
        $output .= "║    - Wie wird getestet?                                       ║\n";
        $output .= "║    - Erfolgs-Kriterien                                        ║\n";
        $output .= "║                                                                ║\n";
        $output .= "║ 5. POTENZIELLE PROBLEME                                       ║\n";
        $output .= "║    - Mögliche Hindernisse                                     ║\n";
        $output .= "║    - Fallback-Strategien                                      ║\n";
        $output .= "║                                                                ║\n";
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        
        // Description
        if (!empty($todo->description)) {
            $output .= "║ AUFGABENBESCHREIBUNG:                                         ║\n";
            $desc_lines = wordwrap($todo->description, 62, "\n", true);
            foreach (explode("\n", $desc_lines) as $line) {
                $formatted = str_pad($line, 64);
                $output .= "║ $formatted ║\n";
            }
            $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        }
        
        $output .= "║ 💡 Nach Erstellung des Plans:                                 ║\n";
        $output .= "║    - Der Plan wird automatisch gespeichert                    ║\n";
        $output .= "║    - Du kannst dann mit der Implementierung beginnen          ║\n";
        $output .= "║    - Der Plan bleibt für spätere Referenz verfügbar          ║\n";
        $output .= "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        $output .= "🚀 Erstelle jetzt einen detaillierten Plan für diese Aufgabe!\n";
        
        return $output;
    }
    
    /**
     * Format task display for CLI
     */
    private function format_task_display($todo) {
        $output = "╔════════════════════════════════════════════════════════════════╗\n";
        $output .= "║                    📋 NEUE AUFGABE GELADEN                    ║\n";
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        
        // Title
        $title = $this->format_line("Titel", $todo->title);
        $output .= "║ $title ║\n";
        
        // Scope & Priority
        $scope_priority = $this->format_line("Bereich/Priorität", 
            $this->get_scope_emoji($todo->scope) . " " . ucfirst($todo->scope) . 
            " | " . $this->get_priority_emoji($todo->priority) . " " . ucfirst($todo->priority)
        );
        $output .= "║ $scope_priority ║\n";
        
        // Working Directory
        if (!empty($todo->working_directory)) {
            $dir = $this->format_line("Arbeitsverzeichnis", $todo->working_directory);
            $output .= "║ $dir ║\n";
        }
        
        // Estimated Hours
        if (!empty($todo->estimated_hours)) {
            $hours = $this->format_line("Geschätzte Zeit", $todo->estimated_hours . " Stunden");
            $output .= "║ $hours ║\n";
        }
        
        $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        
        // Description
        if (!empty($todo->description)) {
            $output .= "║ BESCHREIBUNG:                                                  ║\n";
            $desc_lines = wordwrap($todo->description, 62, "\n", true);
            foreach (explode("\n", $desc_lines) as $line) {
                $formatted = str_pad($line, 64);
                $output .= "║ $formatted ║\n";
            }
            $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        }
        
        // Related Files
        if (!empty($todo->related_files)) {
            $files = json_decode($todo->related_files, true);
            if (!empty($files)) {
                $output .= "║ DATEIEN:                                                       ║\n";
                foreach ($files as $file) {
                    $file_line = $this->format_line("  •", $file);
                    $output .= "║ $file_line ║\n";
                }
                $output .= "╠════════════════════════════════════════════════════════════════╣\n";
            }
        }
        
        // Tags
        if (!empty($todo->tags)) {
            $tags = $this->format_line("Tags", "🏷️  " . $todo->tags);
            $output .= "║ $tags ║\n";
            $output .= "╠════════════════════════════════════════════════════════════════╣\n";
        }
        
        $output .= "║ STATUS: 🔄 IN BEARBEITUNG                                      ║\n";
        $output .= "╚════════════════════════════════════════════════════════════════╝\n\n";
        
        $output .= "💡 Automatik-Modus aktiviert:\n";
        $output .= "  • Bei JEDEM Status-Wechsel wird das nächste Todo geladen\n";
        $output .= "  • Abgeschlossen → Nächstes Todo\n";
        $output .= "  • Blockiert → Nächstes Todo\n";
        $output .= "  • Abgebrochen → Nächstes Todo\n";
        $output .= "  • Übersprungen → Nächstes Todo\n";
        $output .= "  • Arbeite solange bis keine Todos mehr da sind!\n\n";
        
        $output .= "🚀 Beginne jetzt mit der Bearbeitung...\n";
        
        return $output;
    }
    
    /**
     * Format line for box display
     */
    private function format_line($label, $value, $width = 64) {
        $content = "$label: $value";
        if (strlen($content) > $width) {
            $content = substr($content, 0, $width - 3) . "...";
        }
        return str_pad($content, $width);
    }
    
    /**
     * Get scope emoji
     */
    private function get_scope_emoji($scope) {
        $emojis = [
            'frontend' => '🎨',
            'backend' => '⚙️',
            'database' => '🗄️',
            'n8n' => '🔄',
            'mt5' => '📊',
            'server' => '🖥️',
            'content' => '📝',
            'seo' => '🔍',
            'analytics' => '📈',
            'other' => '📌',
        ];
        return $emojis[$scope] ?? '📌';
    }
    
    /**
     * Get priority emoji
     */
    private function get_priority_emoji($priority) {
        $emojis = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'critical' => '🔴',
        ];
        return $emojis[$priority] ?? '⚪';
    }
    
    /**
     * Start output capture for a task
     */
    public function start_output_capture($todo_id) {
        $this->current_task_id = $todo_id;
        $this->output_buffer = [];
        
        // Register shutdown function to save buffered output
        register_shutdown_function([$this, 'flush_output_buffer']);
    }
    
    /**
     * Capture output
     */
    public function capture_output($message, $type = 'info') {
        if (!$this->current_task_id) {
            return;
        }
        
        // Add to buffer
        $this->output_buffer[] = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
        ];
        
        // Flush buffer if it gets too large
        if (count($this->output_buffer) >= 10) {
            $this->flush_output_buffer();
        }
    }
    
    /**
     * Capture error
     */
    public function capture_error($message, $code = '') {
        $this->capture_output("❌ ERROR: $message" . ($code ? " (Code: $code)" : ""), 'error');
    }
    
    /**
     * Flush output buffer to database
     */
    public function flush_output_buffer() {
        if (empty($this->output_buffer) || !$this->current_task_id) {
            return;
        }
        
        foreach ($this->output_buffer as $output) {
            $this->model->append_output($this->current_task_id, $output);
        }
        
        $this->output_buffer = [];
    }
    
    /**
     * Reload configurations after compacting
     */
    public function reload_after_compact() {
        $this->reload_configurations();
        $this->capture_output("♻️ Configurations reloaded after compacting", 'info');
    }
    
    /**
     * Reload CLAUDE.md and .env files
     */
    private function reload_configurations() {
        // DEAKTIVIERT: Claude Code liest diese Dateien selbst ein
        // Das Plugin muss sie nicht nochmal laden, das verursacht Speicherprobleme
        
        // Store reload timestamp trotzdem
        update_option('wp_project_todos_last_config_reload', current_time('mysql'));
        
        return ['Claude Code liest Configs selbst'];
    }
    
    /**
     * Complete current task and auto-load next
     */
    public function complete_current_task($actual_hours = null, $final_notes = '') {
        if (!$this->current_task_id) {
            return $this->format_response("❌ Keine aktive Aufgabe vorhanden", 'error');
        }
        
        // Flush any remaining output
        $this->flush_output_buffer();
        
        // Update task
        $update_data = [
            'status' => 'completed',
            'completed_date' => current_time('mysql'),
        ];
        
        if ($actual_hours !== null) {
            $update_data['actual_hours'] = $actual_hours;
        }
        
        // Save final notes
        if (!empty($final_notes)) {
            $update_data['bemerkungen'] = $final_notes;
            $this->model->add_comment($this->current_task_id, $final_notes, true);
        }
        
        $result = $this->model->update($this->current_task_id, $update_data);
        
        if (is_wp_error($result)) {
            return $this->format_response("❌ Fehler: " . $result->get_error_message(), 'error');
        }
        
        // Add completion comment
        $this->model->add_comment($this->current_task_id, 'Aufgabe von Claude abgeschlossen', true);
        
        // Clear current task
        $completed_id = $this->current_task_id;
        $this->current_task_id = null;
        
        // Get next task
        $next_todo = $this->model->get_next_pending();
        
        $response = "✅ Aufgabe #$completed_id erfolgreich abgeschlossen!\n";
        $response .= "═══════════════════════════════════════\n\n";
        
        if ($next_todo) {
            // Automatisch das nächste Todo laden
            $response .= "📋 LADE NÄCHSTE AUFGABE AUTOMATISCH...\n\n";
            
            // Simulate loading next todo
            $next_response = $this->handle_todo_command();
            
            // Combine responses
            if (is_array($next_response)) {
                return $next_response;
            }
            
            $response .= "Nächste Aufgabe: {$next_todo->title}\n";
            $response .= "Nutze /todo um sie zu laden.";
        } else {
            $response .= "🎉 ALLE AUFGABEN ERLEDIGT! \n\n";
            $response .= "Großartige Arbeit! Es sind keine weiteren Todos vorhanden.\n";
            $response .= "Erstelle neue Aufgaben im WordPress Admin.";
        }
        
        return $this->format_response($response, 'success');
    }
    
    /**
     * Block current task and auto-load next
     */
    public function block_current_task($reason = '') {
        if (!$this->current_task_id) {
            return $this->format_response("❌ Keine aktive Aufgabe vorhanden", 'error');
        }
        
        // Flush output
        $this->flush_output_buffer();
        
        // Update status
        $this->model->update_status($this->current_task_id, 'blocked');
        
        // Add blocking reason
        $comment = "Aufgabe blockiert" . ($reason ? ": $reason" : "");
        $this->model->add_comment($this->current_task_id, $comment, true);
        
        // Clear current task
        $blocked_id = $this->current_task_id;
        $this->current_task_id = null;
        
        $response = "⚠️ Aufgabe #$blocked_id wurde als blockiert markiert.\n";
        $response .= "═══════════════════════════════════════\n\n";
        
        // Auto-load next task
        $next_todo = $this->model->get_next_pending();
        
        if ($next_todo) {
            $response .= "📋 LADE NÄCHSTE AUFGABE AUTOMATISCH...\n\n";
            $next_response = $this->handle_todo_command();
            if (is_array($next_response)) {
                return $next_response;
            }
        } else {
            $response .= "📭 Keine weiteren Todos vorhanden.\n";
            $response .= "Alle Aufgaben sind bearbeitet oder blockiert.";
        }
        
        return $this->format_response($response, 'info');
    }
    
    /**
     * Cancel current task and auto-load next
     */
    public function cancel_current_task($reason = '') {
        if (!$this->current_task_id) {
            return $this->format_response("❌ Keine aktive Aufgabe vorhanden", 'error');
        }
        
        // Flush output
        $this->flush_output_buffer();
        
        // Update status
        $this->model->update_status($this->current_task_id, 'cancelled');
        
        // Add cancellation reason
        $comment = "Aufgabe abgebrochen" . ($reason ? ": $reason" : "");
        $this->model->add_comment($this->current_task_id, $comment, true);
        
        // Clear current task
        $cancelled_id = $this->current_task_id;
        $this->current_task_id = null;
        
        $response = "❌ Aufgabe #$cancelled_id wurde abgebrochen.\n";
        $response .= "═══════════════════════════════════════\n\n";
        
        // Auto-load next task
        $next_todo = $this->model->get_next_pending();
        
        if ($next_todo) {
            $response .= "📋 LADE NÄCHSTE AUFGABE AUTOMATISCH...\n\n";
            $next_response = $this->handle_todo_command();
            if (is_array($next_response)) {
                return $next_response;
            }
        } else {
            $response .= "📭 Keine weiteren Todos vorhanden.\n";
            $response .= "Alle Aufgaben sind bearbeitet.";
        }
        
        return $this->format_response($response, 'info');
    }
    
    /**
     * Skip current task and auto-load next
     */
    public function skip_current_task() {
        if (!$this->current_task_id) {
            // Kein aktives Todo, lade einfach das nächste
            return $this->handle_todo_command();
        }
        
        // Mark current as skipped (back to pending)
        $this->model->update_status($this->current_task_id, 'pending');
        $this->model->add_comment($this->current_task_id, 'Aufgabe übersprungen', true);
        
        $skipped_id = $this->current_task_id;
        $this->current_task_id = null;
        
        $response = "⏭️ Aufgabe #$skipped_id wurde übersprungen.\n";
        $response .= "═══════════════════════════════════════\n\n";
        
        // Auto-load next task
        $next_todo = $this->model->get_next_pending();
        
        if ($next_todo) {
            $response .= "📋 LADE NÄCHSTE AUFGABE AUTOMATISCH...\n\n";
            $next_response = $this->handle_todo_command();
            if (is_array($next_response)) {
                return $next_response;
            }
        } else {
            $response .= "📭 Keine weiteren Todos vorhanden.\n";
        }
        
        return $this->format_response($response, 'info');
    }
    
    /**
     * Get current task status
     */
    public function get_current_status() {
        if (!$this->current_task_id) {
            return $this->format_response("📭 Keine aktive Aufgabe", 'info');
        }
        
        $todo = $this->model->get_by_id($this->current_task_id);
        
        if (!$todo) {
            return $this->format_response("❌ Aufgabe nicht gefunden", 'error');
        }
        
        return $this->format_task_status($todo);
    }
    
    /**
     * Format task status
     */
    private function format_task_status($todo) {
        $output = "📊 AKTUELLER STATUS\n";
        $output .= "═══════════════════════════════════════\n";
        $output .= "ID: #{$todo->id}\n";
        $output .= "Titel: {$todo->title}\n";
        $output .= "Status: " . $this->format_status($todo->status) . "\n";
        $output .= "Bereich: " . $this->get_scope_emoji($todo->scope) . " " . ucfirst($todo->scope) . "\n";
        
        if ($todo->actual_hours) {
            $output .= "Bisherige Zeit: {$todo->actual_hours} Stunden\n";
        }
        
        if ($todo->estimated_hours) {
            $progress = $todo->actual_hours ? 
                round(($todo->actual_hours / $todo->estimated_hours) * 100) : 0;
            $output .= "Fortschritt: " . $this->format_progress_bar($progress) . " $progress%\n";
        }
        
        return $output;
    }
    
    /**
     * Format status with emoji
     */
    private function format_status($status) {
        $statuses = [
            'pending' => '⏳ Ausstehend',
            'in_progress' => '🔄 In Bearbeitung',
            'completed' => '✅ Abgeschlossen',
            'blocked' => '🚫 Blockiert',
            'cancelled' => '❌ Abgebrochen',
        ];
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Format progress bar
     */
    private function format_progress_bar($percentage, $width = 20) {
        $filled = round(($percentage / 100) * $width);
        $empty = $width - $filled;
        return '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
    }
    
    /**
     * Format response for CLI
     */
    private function format_response($message, $type = 'info') {
        return [
            'success' => $type !== 'error',
            'type' => $type,
            'message' => $message,
        ];
    }
    
    /**
     * Render output page for admin
     */
    public function render_output_page() {
        $todo_id = isset($_GET['todo_id']) ? intval($_GET['todo_id']) : 0;
        
        if ($todo_id) {
            $todo = $this->model->get_by_id($todo_id);
            // Check for any output: claude_output, claude_notes, or bemerkungen
            if ($todo && ($todo->claude_output || $todo->claude_notes || $todo->bemerkungen)) {
                $output = $todo->claude_output ? json_decode($todo->claude_output, true) : [];
                include WP_PROJECT_TODOS_PLUGIN_DIR . 'admin/views/claude-output.php';
            } else {
                echo '<div class="notice notice-warning"><p>' . 
                     __('Keine Claude-Ausgabe für diese Aufgabe vorhanden.', 'wp-project-todos') . 
                     '</p></div>';
            }
        } else {
            // Show list of todos with Claude output
            $todos = $this->model->get_all(['orderby' => 'updated_at']);
            include WP_PROJECT_TODOS_PLUGIN_DIR . 'admin/views/claude-output-list.php';
        }
    }
}