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

// Predefined working directories
$predefined_dirs = [
    '/home/rodemkay/www/react/todo/',
    '/var/www/forexsignale/staging/',
    '/var/www/forexsignale/staging/wp-content/plugins/',
    '/var/www/forexsignale/staging/wp-content/themes/',
    '/home/rodemkay/www/react/todo/plugin/',
    '/home/rodemkay/www/react/documentation/',
    '/home/rodemkay/www/react/'
];

// Default values
$default_project = get_option('wp_project_todos_default_project');
$default_scope = '';
$default_working_dir = '';
if ($default_project && !$todo_id) {
    $default_todo = $wpdb->get_row($wpdb->prepare(
        "SELECT scope, working_directory FROM $table WHERE id = %d",
        $default_project
    ));
    if ($default_todo) {
        $default_scope = $default_todo->scope;
        $default_working_dir = $default_todo->working_directory;
    }
}
?>

<!-- Include WSJ Dashboard CSS -->
<link rel="stylesheet" href="<?php echo plugin_dir_url(__DIR__) . 'css/wsj-dashboard.css'; ?>">

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
    
    <?php if (isset($_GET['message'])): ?>
        <?php if ($_GET['message'] === 'saved'): ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Aufgabe erfolgreich gespeichert!</p>
            </div>
        <?php elseif ($_GET['message'] === 'created'): ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Neue Aufgabe erfolgreich erstellt!</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="wsj-form-container">
        <form method="post" class="wsj-form" enctype="multipart/form-data" id="newTodoForm">
            <?php wp_nonce_field('wp_project_todos_edit', 'wp_project_todos_nonce'); ?>
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

            <!-- Dateien & Anhänge -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">DATEIEN & ANHÄNGE</div>
                <div class="wsj-file-upload-area">
                    <div class="wsj-file-upload-item">
                        <input type="file" id="file_upload_1" name="attachments[]" accept=".txt,.php,.js,.css,.html,.md,.pdf,.png,.jpg,.jpeg" style="display: none;">
                        <label for="file_upload_1" class="wsj-file-upload-btn">
                            📎 Datei auswählen
                        </label>
                        <span class="file-name" id="file_name_1"></span>
                    </div>
                    <button type="button" class="wsj-btn wsj-btn-secondary" onclick="addFileUpload()" style="margin-top: 15px;">
                        + Weiteren Anhang hinzufügen
                    </button>
                    <p class="wsj-description">Erlaubte Formate: Text, Code, Bilder, PDFs (max. 10MB pro Datei)</p>
                </div>
            </div>

            <!-- Aufgaben-Einstellungen -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">AUFGABEN-EINSTELLUNGEN</div>
                
                <!-- Status Buttons -->
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

                <!-- Priorität Buttons -->
                <div class="wsj-form-group">
                    <label class="wsj-form-label">PRIORITÄT</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="priority_0" name="priority" value="0" <?php checked($todo->priority ?? 0, 0); ?>>
                        <label for="priority_0">Niedrig</label>
                        
                        <input type="radio" id="priority_1" name="priority" value="1" <?php checked($todo->priority ?? 0, 1); ?>>
                        <label for="priority_1">Mittel</label>
                        
                        <input type="radio" id="priority_2" name="priority" value="2" <?php checked($todo->priority ?? 0, 2); ?>>
                        <label for="priority_2">Hoch</label>
                        
                        <input type="radio" id="priority_3" name="priority" value="3" <?php checked($todo->priority ?? 0, 3); ?>>
                        <label for="priority_3" class="wsj-priority-critical">Kritisch</label>
                    </div>
                </div>

                <!-- Projekt Buttons -->
                <div class="wsj-form-group">
                    <label class="wsj-form-label">PROJEKT</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="project_todo" name="project" value="To-Do Plugin" <?php checked($todo->project ?? '', 'To-Do Plugin'); ?>>
                        <label for="project_todo">To-Do Plugin</label>
                        
                        <input type="radio" id="project_forex" name="project" value="ForexSignale" <?php checked($todo->project ?? '', 'ForexSignale'); ?>>
                        <label for="project_forex">ForexSignale</label>
                        
                        <input type="radio" id="project_homepage" name="project" value="Homepage" <?php checked($todo->project ?? '', 'Homepage'); ?>>
                        <label for="project_homepage">Homepage</label>
                        
                        <input type="radio" id="project_article" name="project" value="Article Builder" <?php checked($todo->project ?? '', 'Article Builder'); ?>>
                        <label for="project_article">Article Builder</label>
                        
                        <input type="radio" id="project_new" name="project" value="Neu" <?php checked($todo->project ?? '', 'Neu'); ?>>
                        <label for="project_new">+ Neu</label>
                    </div>
                </div>

                <!-- Arbeitsverzeichnis -->
                <div class="wsj-form-group">
                    <label for="working_directory" class="wsj-form-label">ARBEITSVERZEICHNIS</label>
                    <div class="wsj-directory-wrapper">
                        <input type="text" id="working_directory" name="working_directory" class="wsj-input"
                               value="<?php echo esc_attr($todo->working_directory ?? $default_working_dir); ?>"
                               placeholder="/home/rodemkay/www/react/todo/">
                        <select id="predefined_dirs" class="wsj-select" onchange="selectPredefinedDir(this.value)">
                            <option value="">-- Vordefinierte Pfade --</option>
                            <?php foreach ($predefined_dirs as $dir): ?>
                                <option value="<?php echo esc_attr($dir); ?>"><?php echo esc_html($dir); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Claude bearbeiten Checkbox -->
                <div class="wsj-form-group wsj-checkbox-group">
                    <label>
                        <input type="checkbox" name="bearbeiten" value="1" 
                               <?php checked($todo->bearbeiten ?? 1, 1); ?>>
                        <span>🤖 Claude bearbeiten</span>
                    </label>
                </div>
            </div>

            <!-- Entwicklungsbereich -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">ENTWICKLUNGSBEREICH</div>
                <div class="wsj-tab-buttons">
                    <input type="radio" id="dev_frontend" name="dev_area" value="Frontend" <?php checked($todo->dev_area ?? '', 'Frontend'); ?>>
                    <label for="dev_frontend" class="wsj-tab-btn">Frontend</label>
                    
                    <input type="radio" id="dev_backend" name="dev_area" value="Backend" <?php checked($todo->dev_area ?? '', 'Backend'); ?>>
                    <label for="dev_backend" class="wsj-tab-btn">Backend</label>
                    
                    <input type="radio" id="dev_fullstack" name="dev_area" value="Full-Stack" <?php checked($todo->dev_area ?? 'Full-Stack', 'Full-Stack'); ?>>
                    <label for="dev_fullstack" class="wsj-tab-btn">Full-Stack</label>
                    
                    <input type="radio" id="dev_devops" name="dev_area" value="DevOps" <?php checked($todo->dev_area ?? '', 'DevOps'); ?>>
                    <label for="dev_devops" class="wsj-tab-btn">DevOps</label>
                    
                    <input type="radio" id="dev_design" name="dev_area" value="Design" <?php checked($todo->dev_area ?? '', 'Design'); ?>>
                    <label for="dev_design" class="wsj-tab-btn">Design</label>
                </div>
            </div>

            <!-- Claude Multi-Agent System -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">CLAUDE MULTI-AGENT SYSTEM</div>
                
                <div class="wsj-form-group">
                    <label class="wsj-form-label">ANZAHL AGENTS</label>
                    <div class="wsj-agent-numbers">
                        <input type="radio" id="agents_0" name="agent_count" value="0" <?php checked($todo->agent_count ?? '1', '0'); ?>>
                        <label for="agents_0" class="wsj-agent-number">0</label>
                        <input type="radio" id="agents_1" name="agent_count" value="1" <?php checked($todo->agent_count ?? '1', '1'); ?>>
                        <label for="agents_1" class="wsj-agent-number">1</label>
                        <input type="radio" id="agents_2" name="agent_count" value="2" <?php checked($todo->agent_count ?? '', '2'); ?>>
                        <label for="agents_2" class="wsj-agent-number">2</label>
                        <input type="radio" id="agents_3" name="agent_count" value="3" <?php checked($todo->agent_count ?? '', '3'); ?>>
                        <label for="agents_3" class="wsj-agent-number">3</label>
                        <input type="radio" id="agents_4" name="agent_count" value="4" <?php checked($todo->agent_count ?? '', '4'); ?>>
                        <label for="agents_4" class="wsj-agent-number">4</label>
                        <input type="radio" id="agents_5" name="agent_count" value="5" <?php checked($todo->agent_count ?? '', '5'); ?>>
                        <label for="agents_5" class="wsj-agent-number">5</label>
                        <input type="radio" id="agents_10" name="agent_count" value="10" <?php checked($todo->agent_count ?? '', '10'); ?>>
                        <label for="agents_10" class="wsj-agent-number">10</label>
                        <input type="radio" id="agents_15" name="agent_count" value="15" <?php checked($todo->agent_count ?? '', '15'); ?>>
                        <label for="agents_15" class="wsj-agent-number">15</label>
                        <input type="radio" id="agents_20" name="agent_count" value="20" <?php checked($todo->agent_count ?? '', '20'); ?>>
                        <label for="agents_20" class="wsj-agent-number">20</label>
                        <input type="radio" id="agents_25" name="agent_count" value="25" <?php checked($todo->agent_count ?? '', '25'); ?>>
                        <label for="agents_25" class="wsj-agent-number">25</label>
                        <input type="radio" id="agents_30" name="agent_count" value="30" <?php checked($todo->agent_count ?? '', '30'); ?>>
                        <label for="agents_30" class="wsj-agent-number">30</label>
                    </div>
                </div>

                <div class="wsj-form-group">
                    <label class="wsj-form-label">AUSFÜHRUNGS-MODUS</label>
                    <div class="wsj-button-group">
                        <input type="radio" id="mode_standard" name="execution_mode" value="Standard" <?php checked($todo->execution_mode ?? 'Standard', 'Standard'); ?>>
                        <label for="mode_standard">Standard</label>
                        
                        <input type="radio" id="mode_parallel" name="execution_mode" value="Parallel" <?php checked($todo->execution_mode ?? '', 'Parallel'); ?>>
                        <label for="mode_parallel">Parallel</label>
                        
                        <input type="radio" id="mode_hierarchical" name="execution_mode" value="Hierarchisch" <?php checked($todo->execution_mode ?? '', 'Hierarchisch'); ?>>
                        <label for="mode_hierarchical">Hierarchisch</label>
                    </div>
                </div>
            </div>

            <!-- MCP Server Integration -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">MCP SERVER INTEGRATION</div>
                <div class="wsj-mcp-grid">
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_context7 ?? 1) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_context7" value="1" <?php checked($todo->mcp_context7 ?? 1, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_context7 ?? 1) ? '✓' : ''; ?></span>
                        <span>Context7 MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_playwright ?? 1) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_playwright" value="1" <?php checked($todo->mcp_playwright ?? 1, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_playwright ?? 1) ? '✓' : ''; ?></span>
                        <span>Playwright MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_puppeteer ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_puppeteer" value="1" <?php checked($todo->mcp_puppeteer ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_puppeteer ?? 0) ? '✓' : ''; ?></span>
                        <span>Puppeteer MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_github ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_github" value="1" <?php checked($todo->mcp_github ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_github ?? 0) ? '✓' : ''; ?></span>
                        <span>GitHub MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_filesystem ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_filesystem" value="1" <?php checked($todo->mcp_filesystem ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_filesystem ?? 0) ? '✓' : ''; ?></span>
                        <span>Filesystem MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_docker ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_docker" value="1" <?php checked($todo->mcp_docker ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_docker ?? 0) ? '✓' : ''; ?></span>
                        <span>Docker MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_youtube ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_youtube" value="1" <?php checked($todo->mcp_youtube ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_youtube ?? 0) ? '✓' : ''; ?></span>
                        <span>YouTube Transcript</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_database ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_database" value="1" <?php checked($todo->mcp_database ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_database ?? 0) ? '✓' : ''; ?></span>
                        <span>Database MCP</span>
                    </label>
                    <label class="wsj-mcp-item <?php echo ($todo->mcp_shadcn ?? 0) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="mcp_shadcn" value="1" <?php checked($todo->mcp_shadcn ?? 0, 1); ?>>
                        <span class="wsj-mcp-checkmark"><?php echo ($todo->mcp_shadcn ?? 0) ? '✓' : ''; ?></span>
                        <span>Shadcn UI MCP</span>
                    </label>
                </div>
                
                <!-- Zusätzliche Optionen -->
                <div class="wsj-form-group wsj-checkbox-group" style="margin-top: 20px;">
                    <label>
                        <input type="checkbox" name="playwright_browser_tests" value="1" <?php checked($todo->playwright_browser_tests ?? 0, 1); ?>>
                        <span>Playwright MCP für Browser-Tests aktivieren</span>
                    </label>
                </div>
            </div>

            <!-- Claude Prompt / Agent-Konfiguration -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">CLAUDE PROMPT / AGENT-KONFIGURATION</div>
                <div class="wsj-form-group">
                    <label for="claude_notes" class="wsj-form-label">TECHNISCHE DETAILS</label>
                    <textarea id="claude_notes" name="claude_notes" rows="6" class="wsj-textarea"
                              placeholder="Optionale technische Details und Konfigurationen für die Ausführung..."><?php 
                        echo esc_textarea($todo->claude_notes ?? ''); 
                    ?></textarea>
                </div>
            </div>

            <!-- Wiederkehrende Aufgabe -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">WIEDERKEHRENDE AUFGABE (CRON)</div>
                
                <div class="wsj-form-group wsj-checkbox-group">
                    <label>
                        <input type="checkbox" name="is_recurring" id="is_recurring" value="1"
                               <?php checked($todo->is_recurring ?? 0, 1); ?>
                               onchange="toggleRecurringOptions(this)">
                        <span>Dies ist eine wiederkehrende Aufgabe</span>
                    </label>
                </div>

                <div id="recurring_options" class="wsj-recurring-options" style="display: <?php echo ($todo->is_recurring ?? 0) ? 'block' : 'none'; ?>;">
                    <div class="wsj-form-group">
                        <label for="recurring_type" class="wsj-form-label">WIEDERHOLUNGSINTERVALL</label>
                        <select id="recurring_type" name="recurring_type" class="wsj-select">
                            <option value="manual" <?php selected($todo->recurring_type ?? 'manual', 'manual'); ?>>Manuell</option>
                            <option value="hourly" <?php selected($todo->recurring_type ?? '', 'hourly'); ?>>Stündlich</option>
                            <option value="daily" <?php selected($todo->recurring_type ?? '', 'daily'); ?>>Täglich</option>
                            <option value="weekly" <?php selected($todo->recurring_type ?? '', 'weekly'); ?>>Wöchentlich</option>
                            <option value="monthly" <?php selected($todo->recurring_type ?? '', 'monthly'); ?>>Monatlich</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Zusätzliche Informationen -->
            <div class="wsj-form-section">
                <div class="wsj-section-header">ZUSÄTZLICHE INFORMATIONEN</div>
                <div class="wsj-form-group">
                    <label for="bemerkungen" class="wsj-form-label">BEMERKUNGEN</label>
                    <textarea id="bemerkungen" name="bemerkungen" rows="3" class="wsj-textarea"
                              placeholder="Zusätzliche Bemerkungen oder Notizen..."><?php 
                        echo esc_textarea($todo->bemerkungen ?? ''); 
                    ?></textarea>
                </div>
            </div>

            <!-- Aktions-Buttons -->
            <div class="wsj-form-actions">
                <button type="submit" name="save_todo" class="wsj-btn wsj-btn-primary">
                    🚀 Aufgabe erstellen
                </button>
                <button type="submit" name="save_without_redirect" class="wsj-btn wsj-btn-success">
                    💾 Nur Speichern (ohne Redirect)
                </button>
                <a href="<?php echo admin_url('admin.php?page=todo'); ?>" 
                   class="wsj-btn wsj-btn-secondary">
                    ← Zurück zur Liste
                </a>
            </div>
        </form>
    </div> <!-- End wsj-form-container -->
</div>

<style>
/* Additional form-specific styling */
.wsj-dashboard-container {
    background: #f5f5f5;
    padding: 20px;
    min-height: 100vh;
}

/* File Name Display */
.file-name {
    font-size: 13px;
    color: var(--wsj-text-secondary);
    font-style: italic;
    flex: 1;
}

/* Override for radio buttons in groups */
.wsj-button-group input[type="radio"] {
    display: none;
}

.wsj-agent-numbers input[type="radio"] {
    display: none;
}

.wsj-tab-buttons input[type="radio"] {
    display: none;
}

/* Ensure proper spacing */
.wsj-form {
    width: 100%;
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
function toggleRecurringOptions(checkbox) {
    document.getElementById('recurring_options').style.display = checkbox.checked ? 'block' : 'none';
}

function selectPredefinedDir(path) {
    if (path) {
        document.getElementById('working_directory').value = path;
    }
}

let fileUploadCounter = 1;

function addFileUpload() {
    fileUploadCounter++;
    const uploadArea = document.querySelector('.wsj-file-upload-area');
    const addButton = uploadArea.querySelector('.wsj-btn');
    
    const newUploadItem = document.createElement('div');
    newUploadItem.className = 'wsj-file-upload-item';
    newUploadItem.innerHTML = `
        <input type="file" id="file_upload_${fileUploadCounter}" name="attachments[]" accept=".txt,.php,.js,.css,.html,.md,.pdf,.png,.jpg,.jpeg" style="display: none;">
        <label for="file_upload_${fileUploadCounter}" class="wsj-file-upload-btn">
            📎 Datei auswählen
        </label>
        <span class="file-name" id="file_name_${fileUploadCounter}"></span>
        <button type="button" class="wsj-btn wsj-btn-secondary" style="margin-left: auto; min-width: auto; padding: 6px 12px;" onclick="removeFileUpload(this)">×</button>
    `;
    
    uploadArea.insertBefore(newUploadItem, addButton);
    
    // Event listener für neuen file input
    const newFileInput = newUploadItem.querySelector('input[type="file"]');
    newFileInput.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : '';
        document.getElementById(`file_name_${fileUploadCounter}`).textContent = fileName;
    });
}

function removeFileUpload(button) {
    button.parentElement.remove();
}

// Event listeners für file inputs
document.addEventListener('DOMContentLoaded', function() {
    // Initialize MCP checkboxes
    initializeMcpCheckboxes();
    
    // File upload change handlers
    document.addEventListener('change', function(e) {
        if (e.target.type === 'file') {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            const fileNameSpan = e.target.parentElement.querySelector('.file-name');
            if (fileNameSpan) {
                fileNameSpan.textContent = fileName;
            }
        }
    });
    
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
    
    // Form validation
    document.getElementById('newTodoForm').addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        if (!title) {
            e.preventDefault();
            alert('Bitte geben Sie einen Titel für die Aufgabe ein.');
            document.getElementById('title').focus();
            return false;
        }
        
        // Show loading state
        const submitButtons = this.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(button => {
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '⏳ Wird gespeichert...';
            
            // Restore after 10 seconds as fallback
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
            }, 10000);
        });
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

// AJAX form submission for save without redirect
function saveWithoutRedirect() {
    const form = document.getElementById('newTodoForm');
    const formData = new FormData(form);
    formData.append('save_without_redirect', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Show success message
        const existingNotice = document.querySelector('.notice');
        if (existingNotice) {
            existingNotice.remove();
        }
        
        const notice = document.createElement('div');
        notice.className = 'notice notice-success is-dismissible';
        notice.innerHTML = '<p>✅ Aufgabe erfolgreich gespeichert!</p>';
        
        const heading = document.querySelector('.wsj-dashboard-title');
        heading.parentNode.insertBefore(notice, heading.nextSibling);
        
        // Auto-hide notice after 3 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 3000);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fehler beim Speichern der Aufgabe. Bitte versuchen Sie es erneut.');
    });
}
</script>