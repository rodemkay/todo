<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'project_todos';

// Get todo if editing
$todo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$todo = null;
if ($todo_id) {
    $todo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $todo_id));
}

// Include WSJ Dashboard CSS
wp_enqueue_style('todo-wsj', WP_PROJECT_TODOS_PLUGIN_URL . 'admin/css/wsj-dashboard.css', [], WP_PROJECT_TODOS_VERSION);
?>

<div class="wsj-dashboard-container">
    <!-- Header Section -->
    <div class="wsj-dashboard-header">
        <h1 class="wsj-dashboard-title">
            <?php echo $todo ? 'AUFGABE BEARBEITEN' : 'NEUE AUFGABE ERSTELLEN'; ?>
        </h1>
        <a href="<?php echo admin_url('admin.php?page=todo'); ?>" class="wsj-btn wsj-btn-secondary">
            ← ZURÜCK ZUR LISTE
        </a>
    </div>
    
    <div class="wsj-form-container">
        <form method="post" class="wsj-form" enctype="multipart/form-data" id="newTodoForm">
            <?php wp_nonce_field('todo_edit', 'todo_nonce'); ?>
            <?php if ($todo): ?>
                <input type="hidden" name="todo_id" value="<?php echo esc_attr($todo->id); ?>">
            <?php endif; ?>

            <!-- Titel Section -->
            <div class="wsj-form-section">
                <div class="wsj-form-group">
                    <label for="title" class="wsj-form-label">TITEL</label>
                    <input type="text" id="title" name="title" class="wsj-input wsj-title-input" 
                           placeholder="Titel der Aufgabe eingeben..."
                           value="<?php echo esc_attr($todo->title ?? ''); ?>" required>
                </div>

                <div class="wsj-form-group">
                    <label for="description" class="wsj-form-label">BESCHREIBUNG</label>
                    <textarea id="description" name="description" rows="6" class="wsj-textarea"
                              placeholder="Detaillierte Beschreibung der Aufgabe..."><?php 
                        echo esc_textarea($todo->description ?? ''); 
                    ?></textarea>
                </div>
            </div>

            <!-- Aufgaben-Einstellungen -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">AUFGABEN-EINSTELLUNGEN</div>
                
                <!-- Bereich/Scope -->
                <div class="wsj-form-group">
                    <label class="wsj-form-label">BEREICH</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="scope_backend" name="scope" value="backend" <?php checked($todo->scope ?? 'backend', 'backend'); ?>>
                        <label for="scope_backend">Backend</label>
                        
                        <input type="radio" id="scope_frontend" name="scope" value="frontend" <?php checked($todo->scope ?? '', 'frontend'); ?>>
                        <label for="scope_frontend">Frontend</label>
                        
                        <input type="radio" id="scope_database" name="scope" value="database" <?php checked($todo->scope ?? '', 'database'); ?>>
                        <label for="scope_database">Database</label>
                        
                        <input type="radio" id="scope_other" name="scope" value="other" <?php checked($todo->scope ?? '', 'other'); ?>>
                        <label for="scope_other">Other</label>
                    </div>
                </div>
                
                <!-- Status -->
                <div class="wsj-form-group">
                    <label class="wsj-form-label">STATUS</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="status_offen" name="status" value="offen" <?php checked($todo->status ?? 'offen', 'offen'); ?>>
                        <label for="status_offen">Offen</label>
                        
                        <input type="radio" id="status_progress" name="status" value="in_progress" <?php checked($todo->status ?? '', 'in_progress'); ?>>
                        <label for="status_progress">In Bearbeitung</label>
                        
                        <input type="radio" id="status_completed" name="status" value="completed" <?php checked($todo->status ?? '', 'completed'); ?>>
                        <label for="status_completed">Abgeschlossen</label>
                        
                        <input type="radio" id="status_blocked" name="status" value="blocked" <?php checked($todo->status ?? '', 'blocked'); ?>>
                        <label for="status_blocked">Blockiert</label>
                    </div>
                </div>

                <!-- Priorität -->
                <div class="wsj-form-group">
                    <label class="wsj-form-label">PRIORITÄT</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="priority_low" name="priority" value="low" <?php checked($todo->priority ?? 'medium', 'low'); ?>>
                        <label for="priority_low">Niedrig</label>
                        
                        <input type="radio" id="priority_medium" name="priority" value="medium" <?php checked($todo->priority ?? 'medium', 'medium'); ?>>
                        <label for="priority_medium">Mittel</label>
                        
                        <input type="radio" id="priority_high" name="priority" value="high" <?php checked($todo->priority ?? '', 'high'); ?>>
                        <label for="priority_high">Hoch</label>
                        
                        <input type="radio" id="priority_critical" name="priority" value="critical" <?php checked($todo->priority ?? '', 'critical'); ?>>
                        <label for="priority_critical" class="wsj-priority-critical">Kritisch</label>
                    </div>
                </div>

                <!-- Claude bearbeiten Checkbox -->
                <div class="wsj-form-group wsj-checkbox-group">
                    <label>
                        <input type="checkbox" name="bearbeiten" value="1" 
                               <?php checked($todo->bearbeiten ?? 1, 1); ?>>
                        <span>🤖 Von Claude bearbeiten</span>
                    </label>
                    <p class="wsj-description">Wenn aktiviert, wird Claude diese Aufgabe bearbeiten.</p>
                </div>

                <!-- Cron Checkbox -->
                <div class="wsj-form-group wsj-checkbox-group">
                    <label>
                        <input type="checkbox" name="is_cron" value="1" 
                               <?php checked($todo->is_cron ?? 0, 1); ?>>
                        <span>⏰ Wiederkehrende Aufgabe (Cron)</span>
                    </label>
                    <p class="wsj-description">Task geht nach Abschluss automatisch zurück in Cron-Status.</p>
                </div>

                <!-- Claude Modus -->
                <div class="wsj-form-group">
                    <label for="claude_mode" class="wsj-form-label">CLAUDE MODUS</label>
                    <select name="claude_mode" id="claude_mode" class="wsj-select">
                        <option value="bypass" <?php selected($todo->claude_mode ?? 'bypass', 'bypass'); ?>>Bypass Permissions (Standard)</option>
                        <option value="auto" <?php selected($todo->claude_mode ?? '', 'auto'); ?>>Auto-Accept</option>
                        <option value="plan" <?php selected($todo->claude_mode ?? '', 'plan'); ?>>Plan Mode</option>
                        <option value="normal" <?php selected($todo->claude_mode ?? '', 'normal'); ?>>Normal (mit Bestätigung)</option>
                    </select>
                    <p class="wsj-description">Steuert wie Claude mit Berechtigungen umgeht</p>
                </div>
            </div>

            <!-- Arbeitsverzeichnis -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">ARBEITSVERZEICHNIS</div>
                <div class="wsj-form-group">
                    <input type="text" name="working_directory" id="working_directory" class="wsj-input"
                           value="<?php echo esc_attr($todo->working_directory ?? '/home/rodemkay/www/react/todo/'); ?>"
                           placeholder="/home/rodemkay/www/react/todo/">
                    <p class="wsj-description">Verzeichnis wird automatisch vom Projekt-Template gesetzt oder manuell eingegeben</p>
                </div>
            </div>

            <!-- MCP Server Integration -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">MCP SERVER INTEGRATION</div>
                <div class="wsj-mcp-grid">
                    <label class="wsj-mcp-item <?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'playwright') !== false) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_servers[]" value="playwright" <?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'playwright') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'playwright') !== false) ? '✓' : ''; ?></span>
                        <span>🎭 Playwright MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'context7') !== false) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_servers[]" value="context7" <?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'context7') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? 'playwright,context7', 'context7') !== false) ? '✓' : ''; ?></span>
                        <span>📚 Context7 MCP</span>
                    </label>
                    <label class="wsj-mcp-item">
                        <input type="checkbox" name="mcp_servers[]" value="shadcn" <?php echo (strpos($todo->mcp_servers ?? '', 'shadcn') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? '', 'shadcn') !== false) ? '✓' : ''; ?></span>
                        <span>🎨 Shadcn UI MCP</span>
                    </label>
                    <label class="wsj-mcp-item">
                        <input type="checkbox" name="mcp_servers[]" value="github" <?php echo (strpos($todo->mcp_servers ?? '', 'github') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? '', 'github') !== false) ? '✓' : ''; ?></span>
                        <span>🐙 GitHub MCP</span>
                    </label>
                    <label class="wsj-mcp-item">
                        <input type="checkbox" name="mcp_servers[]" value="filesystem" <?php echo (strpos($todo->mcp_servers ?? '', 'filesystem') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? '', 'filesystem') !== false) ? '✓' : ''; ?></span>
                        <span>📁 Filesystem MCP</span>
                    </label>
                    <label class="wsj-mcp-item">
                        <input type="checkbox" name="mcp_servers[]" value="docker" <?php echo (strpos($todo->mcp_servers ?? '', 'docker') !== false) ? 'checked' : ''; ?>>
                        <span class="wsj-mcp-checkmark"><?php echo (strpos($todo->mcp_servers ?? '', 'docker') !== false) ? '✓' : ''; ?></span>
                        <span>🐳 Docker MCP</span>
                    </label>
                </div>
                
                <p class="wsj-description">Wähle die MCP Server, die Claude für diese Aufgabe verwenden soll. Aktive Server können sofort verwendet werden.</p>
            </div>

            <!-- Bemerkungen für Claude -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">BEMERKUNGEN FÜR CLAUDE</div>
                <div class="wsj-form-group">
                    <textarea name="claude_notes" rows="4" class="wsj-textarea"
                              placeholder="Claude wird hier seine Bemerkungen nach Abschluss der Aufgabe eintragen..."><?php 
                        echo esc_textarea($todo->claude_notes ?? ''); 
                    ?></textarea>
                    <p class="wsj-description">Claude wird hier seine Bemerkungen nach Abschluss der Aufgabe eintragen.</p>
                </div>
            </div>

            <!-- Anhänge -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">ANHÄNGE</div>
                <div class="wsj-form-group">
                    <input type="file" name="attachments[]" multiple 
                           accept=".jpg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.csv,.mp4,.mov" 
                           class="wsj-file-input">
                    <p class="wsj-description">Screenshots, Dokumente oder andere Dateien (max. 10MB pro Datei). Erlaubte Formate: jpg, png, gif, pdf, doc, docx, xls, xlsx, txt, zip, csv, mp4, mov</p>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="wsj-form-actions">
                <button type="submit" name="submit" class="wsj-btn wsj-btn-primary">
                    💾 Speichern
                </button>
                <a href="<?php echo admin_url('admin.php?page=todo'); ?>" 
                   class="wsj-btn wsj-btn-secondary">
                    ← Zurück zur Liste
                </a>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional form-specific styling */
.wsj-dashboard-container {
    background: #f5f5f5;
    padding: 20px;
    min-height: 100vh;
}

/* Override for radio buttons in groups */
.wsj-button-group input[type="radio"] {
    display: none;
}

.wsj-button-group label {
    display: inline-block;
    padding: 8px 16px;
    margin-right: 8px;
    background: white;
    border: 2px solid var(--wsj-border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.wsj-button-group input[type="radio"]:checked + label {
    background: var(--wsj-accent);
    color: white;
    border-color: var(--wsj-accent);
}

.wsj-button-group label:hover {
    border-color: var(--wsj-accent);
    background: var(--wsj-bg-hover);
}

/* File input styling */
.wsj-file-input {
    width: 100%;
    padding: 10px;
    border: 2px dashed var(--wsj-border);
    border-radius: 6px;
    background: white;
}

.wsj-file-input:hover {
    border-color: var(--wsj-accent);
}

/* Success/Error messages */
.notice {
    margin: 20px 0;
    padding: 12px 15px;
    border-left: 4px solid #00a32a;
    background: #f0f6fc;
    border-radius: 4px;
}

.notice-success {
    border-left-color: #00a32a;
}

.notice p {
    margin: 0;
    font-weight: 500;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize MCP checkboxes
    initializeMcpCheckboxes();
    
    // Radio button handlers for visual state
    document.addEventListener('change', function(e) {
        if (e.target.type === 'radio') {
            // Remove active class from all labels in the same group
            const groupName = e.target.name;
            const allLabels = document.querySelectorAll(`input[name="${groupName}"] + label`);
            allLabels.forEach(label => label.classList.remove('active'));
            
            // Add active class to selected label
            const selectedLabel = document.querySelector(`input[name="${groupName}"]:checked + label`);
            if (selectedLabel) {
                selectedLabel.classList.add('active');
            }
        }
    });
    
    // Initialize active states on page load
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        const label = radio.nextElementSibling;
        if (label) {
            label.classList.add('active');
        }
    });
});

// Initialize MCP checkboxes styling
function initializeMcpCheckboxes() {
    const mcpItems = document.querySelectorAll('.wsj-mcp-item');
    
    mcpItems.forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const checkmark = item.querySelector('.wsj-mcp-checkmark');
        
        if (checkbox && checkmark) {
            // Set initial state
            if (checkbox.checked) {
                item.classList.add('checked');
                checkmark.textContent = '✓';
            } else {
                item.classList.remove('checked');
                checkmark.textContent = '';
            }
            
            // Add click handler
            item.addEventListener('click', function(e) {
                // Don't double-trigger if clicking the actual checkbox
                if (e.target.type !== 'checkbox') {
                    checkbox.checked = !checkbox.checked;
                }
                
                // Update visual state
                if (checkbox.checked) {
                    item.classList.add('checked');
                    checkmark.textContent = '✓';
                } else {
                    item.classList.remove('checked');
                    checkmark.textContent = '';
                }
            });
        }
    });
}
</script>