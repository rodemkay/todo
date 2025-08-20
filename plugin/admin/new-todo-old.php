<?php
/**
 * Neue Aufgabe - WSJ Style Admin Page
 * 
 * @package WP_Project_Todos
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current todo if editing
$todo = null;
$is_editing = false;
$is_continue = false;
$continue_from_id = 0;

if (isset($_GET['id'])) {
    $todo = $this->model->get_by_id(intval($_GET['id']));
    $is_editing = !empty($todo);
}

if (isset($_GET['action']) && $_GET['action'] === 'continue') {
    $is_continue = true;
    if (isset($_GET['continue_from'])) {
        $continue_from_id = intval($_GET['continue_from']);
    }
}

// Load attachments for editing mode
$attachments = [];
if ($is_editing) {
    $attachments = $this->attachment_model->get_by_todo_id($todo->id);
}

// Default values
$defaults = [
    'title' => '',
    'description' => '',
    'status' => 'offen',
    'priority' => 'mittel',
    'bearbeiten' => 1,
    'scope' => 'todo-plugin',
    'claude_mode' => 'bypass_permissions',
    'agents' => [],
    'execution_mode' => 'parallel',
    'use_playwright' => 1,
    'cron_enabled' => 0,
    'cron_schedule' => 'daily',
    'mcp_servers' => [],
    'working_directory' => '/home/rodemkay/www/react/'
];

// Set values from existing todo or defaults
$current = $defaults;
if ($todo) {
    $current = [
        'title' => $todo->title,
        'description' => $todo->description,
        'status' => $todo->status,
        'priority' => $todo->priority,
        'bearbeiten' => $todo->bearbeiten,
        'scope' => $todo->scope,
        'claude_mode' => $todo->claude_mode ?: 'bypass_permissions',
        'agents' => !empty($todo->agents) ? explode(',', $todo->agents) : [],
        'execution_mode' => $todo->execution_mode ?: 'parallel',
        'use_playwright' => $todo->use_playwright,
        'cron_enabled' => $todo->cron_enabled,
        'cron_schedule' => $todo->cron_schedule ?: 'daily',
        'mcp_servers' => !empty($todo->mcp_servers) ? explode(',', $todo->mcp_servers) : [],
        'working_directory' => $todo->working_directory,
        'claude_notes' => $todo->claude_notes,
        'bemerkungen' => $todo->bemerkungen
    ];
}
?>

<div class="wrap wsj-admin-page">
    <!-- WSJ Header -->
    <div class="wsj-admin-header">
        <h1 class="wsj-admin-title">
            <?php echo $is_editing ? __('Aufgabe Bearbeiten', 'wp-project-todos') : __('Neue Aufgabe', 'wp-project-todos'); ?>
        </h1>
        <p class="wsj-admin-subtitle">
            <?php echo $is_continue ? __('Aufgabe Weiterführen', 'wp-project-todos') : __('ForexSignale Magazine To-Do System', 'wp-project-todos'); ?>
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" id="wsj-todo-form">
        <?php wp_nonce_field('wp_project_todos_edit', 'wp_project_todos_nonce'); ?>
        
        <!-- Hidden field for continue_from functionality -->
        <?php if ($continue_from_id > 0): ?>
        <input type="hidden" name="continue_from" value="<?php echo esc_attr($continue_from_id); ?>">
        <?php endif; ?>

        <!-- 1. STATUS BUTTONS -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Aufgaben-Status', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-button-group">
                <label class="wsj-button-group-label"><?php _e('Status auswählen', 'wp-project-todos'); ?></label>
                <div class="wsj-status-buttons">
                    <button type="button" class="wsj-status-btn <?php echo (!$is_editing || $current['status'] === 'offen') ? 'active' : ''; ?>" 
                            data-status="pending" data-value="offen">
                        <?php _e('Offen', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['status'] === 'in_progress' ? 'active' : ''; ?>" 
                            data-status="in_progress" data-value="in_progress">
                        <?php _e('In Bearbeitung', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['status'] === 'completed' ? 'active' : ''; ?>" 
                            data-status="completed" data-value="completed">
                        <?php _e('Abgeschlossen', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['status'] === 'blocked' ? 'active' : ''; ?>" 
                            data-status="blocked" data-value="blocked">
                        <?php _e('Blockiert', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['status'] === 'cancelled' ? 'active' : ''; ?>" 
                            data-status="cancelled" data-value="cancelled">
                        <?php _e('Abgebrochen', 'wp-project-todos'); ?>
                    </button>
                </div>
                <input type="hidden" name="status" id="status-input" value="<?php echo esc_attr($current['status'] ?: 'offen'); ?>">
            </div>
        </div>

        <!-- 2. PRIORITÄT & CLAUDE BEARBEITEN -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Priorität & Bearbeitung', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-form-row">
                <div class="wsj-form-group">
                    <label class="wsj-button-group-label"><?php _e('Priorität', 'wp-project-todos'); ?></label>
                    <div class="wsj-priority-buttons">
                        <button type="button" class="wsj-priority-btn <?php echo $current['priority'] === 'niedrig' ? 'active' : ''; ?>" 
                                data-priority="niedrig" data-value="niedrig">
                            <?php _e('Niedrig', 'wp-project-todos'); ?>
                        </button>
                        <button type="button" class="wsj-priority-btn <?php echo $current['priority'] === 'mittel' ? 'active' : ''; ?>" 
                                data-priority="mittel" data-value="mittel">
                            <?php _e('Medium', 'wp-project-todos'); ?>
                        </button>
                        <button type="button" class="wsj-priority-btn <?php echo $current['priority'] === 'hoch' ? 'active' : ''; ?>" 
                                data-priority="hoch" data-value="hoch">
                            <?php _e('Hoch', 'wp-project-todos'); ?>
                        </button>
                        <button type="button" class="wsj-priority-btn <?php echo $current['priority'] === 'kritisch' ? 'active' : ''; ?>" 
                                data-priority="kritisch" data-value="kritisch" style="background: #dc3545; color: white;">
                            <?php _e('Kritisch', 'wp-project-todos'); ?>
                        </button>
                    </div>
                    <input type="hidden" name="priority" id="priority-input" value="<?php echo esc_attr($current['priority']); ?>">
                </div>

                <div class="wsj-form-group">
                    <div class="wsj-claude-toggle">
                        <input type="checkbox" name="bearbeiten" id="claude-bearbeiten" class="wsj-claude-checkbox" 
                               value="1" <?php checked($current['bearbeiten'], 1); ?>>
                        <label for="claude-bearbeiten" class="wsj-claude-label">
                            <?php _e('Claude bearbeitet diese Aufgabe', 'wp-project-todos'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. PROJEKT BUTTONS -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Projekt-Bereich', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-button-group">
                <label class="wsj-button-group-label"><?php _e('Projekt auswählen', 'wp-project-todos'); ?></label>
                <div class="wsj-status-buttons">
                    <button type="button" class="wsj-status-btn <?php echo $current['scope'] === 'todo-plugin' ? 'active' : ''; ?>" 
                            data-value="todo-plugin">
                        <?php _e('To-Do Plugin', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['scope'] === 'forexsignale' ? 'active' : ''; ?>" 
                            data-value="forexsignale">
                        <?php _e('ForexSignale', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['scope'] === 'homepage' ? 'active' : ''; ?>" 
                            data-value="homepage">
                        <?php _e('Homepage', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['scope'] === 'article-builder' ? 'active' : ''; ?>" 
                            data-value="article-builder">
                        <?php _e('Article Builder', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn" data-value="custom" id="scope-custom-btn">
                        <?php _e('Neu...', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn wsj-set-default-btn" 
                            onclick="setDefaultProject()" 
                            style="background: #28a745; color: white; margin-left: 10px;">
                        <?php _e('Als Standard', 'wp-project-todos'); ?>
                    </button>
                </div>
                <input type="hidden" name="scope" id="scope-input" value="<?php echo esc_attr($current['scope']); ?>">
                <input type="text" name="scope_custom" id="scope-custom-input" class="wsj-form-input wsj-hidden" 
                       placeholder="<?php _e('Neuer Projektbereich...', 'wp-project-todos'); ?>">
            </div>
        </div>

        <!-- 4. CLAUDE MODUS BUTTONS -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Claude Ausführungs-Modus', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-button-group">
                <label class="wsj-button-group-label"><?php _e('Berechtigungen & Sicherheit', 'wp-project-todos'); ?></label>
                <div class="wsj-status-buttons">
                    <button type="button" class="wsj-status-btn <?php echo $current['claude_mode'] === 'bypass_permissions' ? 'active' : ''; ?>" 
                            data-value="bypass_permissions">
                        <?php _e('Bypass Permissions', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['claude_mode'] === 'safe_mode' ? 'active' : ''; ?>" 
                            data-value="safe_mode">
                        <?php _e('Safe Mode', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['claude_mode'] === 'read_only' ? 'active' : ''; ?>" 
                            data-value="read_only">
                        <?php _e('Read Only', 'wp-project-todos'); ?>
                    </button>
                    <button type="button" class="wsj-status-btn <?php echo $current['claude_mode'] === 'admin_approval' ? 'active' : ''; ?>" 
                            data-value="admin_approval">
                        <?php _e('Admin Approval', 'wp-project-todos'); ?>
                    </button>
                </div>
                <input type="hidden" name="claude_mode" id="claude-mode-input" value="<?php echo esc_attr($current['claude_mode']); ?>">
            </div>
        </div>

        <!-- 5. TITEL EINGABEFELD -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Aufgaben-Details', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-form-group">
                <label for="todo-title" class="wsj-form-label"><?php _e('Titel', 'wp-project-todos'); ?></label>
                <input type="text" name="title" id="todo-title" class="wsj-form-input" 
                       value="<?php echo isset($current['title']) ? esc_attr($current['title']) : ''; ?>" 
                       placeholder="<?php _e('Aufgaben-Titel eingeben...', 'wp-project-todos'); ?>" required>
            </div>
        </div>

        <!-- 6. BESCHREIBUNG EINGABEFELD (IMMER LEER BEI NEUEN TASKS) -->
        <div class="wsj-form-section">
            <div class="wsj-form-group">
                <label for="todo-description" class="wsj-form-label"><?php _e('Beschreibung', 'wp-project-todos'); ?></label>
                <textarea name="description" id="todo-description" class="wsj-form-textarea" rows="5" 
                          placeholder="<?php _e('Beschreibung der Aufgabe...', 'wp-project-todos'); ?>"><?php echo isset($current['description']) ? esc_textarea($current['description']) : ''; ?></textarea>
                <p class="wsj-form-help"><?php _e('Kurze Beschreibung der Aufgabe. Dieses Feld bleibt bei neuen Tasks leer.', 'wp-project-todos'); ?></p>
            </div>
        </div>

        <!-- 7. PROMPT EINGABEFELD (FÜR AGENT-SETTINGS UND MCP-CONFIG) -->
        <div class="wsj-form-section">
            <div class="wsj-form-group">
                <label for="todo-prompt" class="wsj-form-label"><?php _e('Claude Prompt / Agent-Konfiguration', 'wp-project-todos'); ?></label>
                <textarea name="claude_notes" id="todo-prompt" class="wsj-form-textarea" rows="8" 
                          placeholder="<?php _e('Hier werden automatisch Agent-Settings, MCP-Server und andere Konfigurationen eingefügt...', 'wp-project-todos'); ?>"><?php echo isset($current['claude_notes']) ? esc_textarea($current['claude_notes']) : ''; ?></textarea>
                <p class="wsj-form-help"><?php _e('Dieses Feld wird automatisch mit Agent-Einstellungen, MCP-Server-Konfiguration und anderen technischen Details befüllt.', 'wp-project-todos'); ?></p>
            </div>
        </div>

        <!-- 8. MCP SERVER BEREICH -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('MCP Server Integration', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-agent-checkboxes">
                <div class="wsj-agent-checkboxes-title"><?php _e('Verfügbare MCP Server', 'wp-project-todos'); ?></div>
                <div class="wsj-agent-grid">
                    <?php 
                    $mcp_servers = [
                        'context7' => 'Context7 - Dokumentation',
                        'shadcn-ui' => 'shadcn/ui - UI Komponenten',
                        'playwright' => 'Playwright - Browser Automation'
                    ];
                    
                    foreach ($mcp_servers as $server_id => $server_name): 
                    ?>
                    <div class="wsj-agent-item">
                        <input type="checkbox" name="mcp_servers[]" id="mcp-<?php echo $server_id; ?>" 
                               class="wsj-agent-checkbox" value="<?php echo $server_id; ?>"
                               <?php checked(in_array($server_id, $current['mcp_servers'])); ?>>
                        <label for="mcp-<?php echo $server_id; ?>" class="wsj-agent-label">
                            <?php echo esc_html($server_name); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 8. AGENTEN BEREICH -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Claude Multi-Agent System', 'wp-project-todos'); ?></h2>
            
            <!-- Agenten-Anzahl Auswahl -->
            <div class="wsj-form-group">
                <label class="wsj-button-group-label"><?php _e('Anzahl Agenten auswählen', 'wp-project-todos'); ?></label>
                <div class="wsj-agent-count-grid">
                    <?php 
                    $agent_counts = [1, 2, 3, 5, 10, 15, 20, 25, 30];
                    $current_agent_count = !empty($current['agents']) ? (int)$current['agents'][0] : 1;
                    foreach ($agent_counts as $count): 
                    ?>
                    <label class="wsj-agent-count-option">
                        <input type="checkbox" name="agent_count" value="<?php echo $count; ?>" 
                               <?php checked($current_agent_count === $count); ?> class="wsj-agent-count-checkbox">
                        <span class="wsj-agent-count-label"><?php echo $count; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="wsj-form-help"><?php _e('Wählen Sie die Anzahl der parallel arbeitenden Claude-Agenten. Mehr Agenten = schnellere Bearbeitung komplexer Aufgaben.', 'wp-project-todos'); ?></p>
            </div>

            <!-- Ausführungs-Modus -->
            <div class="wsj-form-group">
                <label class="wsj-button-group-label"><?php _e('Koordinations-Modus', 'wp-project-todos'); ?></label>
                <div class="wsj-execution-mode-options">
                    <label class="wsj-execution-mode-option">
                        <input type="radio" name="execution_mode" value="parallel" 
                               <?php checked($current['execution_mode'], 'parallel'); ?> class="wsj-execution-mode-radio">
                        <span class="wsj-execution-mode-label">
                            <strong><?php _e('Parallel', 'wp-project-todos'); ?></strong>
                            <small><?php _e('Alle Agenten arbeiten gleichzeitig an verschiedenen Teilaufgaben', 'wp-project-todos'); ?></small>
                        </span>
                    </label>
                    
                    <label class="wsj-execution-mode-option">
                        <input type="radio" name="execution_mode" value="hierarchical" 
                               <?php checked($current['execution_mode'], 'hierarchical'); ?> class="wsj-execution-mode-radio">
                        <span class="wsj-execution-mode-label">
                            <strong><?php _e('Hierarchisch', 'wp-project-todos'); ?></strong>
                            <small><?php _e('Ein Master-Agent koordiniert und verteilt Aufgaben an Sub-Agenten', 'wp-project-todos'); ?></small>
                        </span>
                    </label>
                    
                    <label class="wsj-execution-mode-option">
                        <input type="radio" name="execution_mode" value="default" 
                               <?php checked($current['execution_mode'], 'default'); ?> class="wsj-execution-mode-radio">
                        <span class="wsj-execution-mode-label">
                            <strong><?php _e('Standard', 'wp-project-todos'); ?></strong>
                            <small><?php _e('Claude entscheidet automatisch basierend auf der Aufgaben-Komplexität', 'wp-project-todos'); ?></small>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Agent Settings als JSON (versteckt) -->
            <input type="hidden" name="agent_settings" id="agent-settings-input" value="">
            <input type="hidden" name="agents" id="agents-input" value="<?php echo esc_attr(implode(',', $current['agents'])); ?>">
            
        </div>

        <!-- 9. HINWEISBEREICH MIT PLAYWRIGHT -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Zusätzliche Optionen', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-form-row">
                <div class="wsj-form-group">
                    <div class="wsj-claude-toggle">
                        <input type="checkbox" name="use_playwright" id="use-playwright" class="wsj-claude-checkbox" 
                               value="1" <?php checked($current['use_playwright'], 1); ?>>
                        <label for="use-playwright" class="wsj-claude-label">
                            <?php _e('Playwright MCP für Browser-Tests verwenden', 'wp-project-todos'); ?>
                        </label>
                    </div>
                    <p class="wsj-form-help"><?php _e('Aktiviert automatische Browser-Tests und Screenshots für UI-Aufgaben.', 'wp-project-todos'); ?></p>
                </div>

                <div class="wsj-form-group">
                    <label for="working-directory" class="wsj-form-label"><?php _e('Arbeitsverzeichnis (optional)', 'wp-project-todos'); ?></label>
                    <input type="text" name="working_directory" id="working-directory" class="wsj-form-input" 
                           value="<?php echo isset($current['working_directory']) ? esc_attr($current['working_directory']) : ''; ?>" 
                           placeholder="/home/rodemkay/www/react/wp-project-todos">
                    <p class="wsj-form-help"><?php _e('Spezifisches Verzeichnis für diese Aufgabe. Leer lassen für Standard-Verzeichnis.', 'wp-project-todos'); ?></p>
                </div>
            </div>
        </div>

        <!-- 10. ANHÄNGE BEREICH -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Dateien & Anhänge', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-form-group">
                <label for="todo-attachments" class="wsj-form-label"><?php _e('Dateien hochladen', 'wp-project-todos'); ?></label>
                <input type="file" name="attachments[]" id="todo-attachments" class="wsj-form-input" multiple
                       accept=".txt,.md,.php,.js,.css,.html,.json,.yaml,.yml,.xml,.csv,.pdf,.png,.jpg,.jpeg,.gif">
                <p class="wsj-form-help"><?php _e('Unterstützte Formate: Text, Code, Bilder, PDFs. Mehrere Dateien möglich.', 'wp-project-todos'); ?></p>
            </div>

            <?php if (!empty($attachments)): ?>
            <div class="wsj-form-group">
                <label class="wsj-form-label"><?php _e('Vorhandene Anhänge', 'wp-project-todos'); ?></label>
                <ul class="attachment-list">
                    <?php foreach ($attachments as $attachment): ?>
                    <li class="attachment-item">
                        <div class="attachment-info">
                            <div class="attachment-icon">📎</div>
                            <div class="attachment-details">
                                <div class="attachment-name"><?php echo esc_html($attachment->original_filename); ?></div>
                                <div class="attachment-meta">
                                    <?php echo $this->attachment_model->format_size($attachment->file_size); ?> | 
                                    <?php echo esc_html($attachment->display_name); ?> | 
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($attachment->uploaded_at)); ?>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos-new&id=' . $todo->id . '&delete_attachment=1&attachment_id=' . $attachment->id), 'delete_attachment_' . $attachment->id); ?>" 
                           class="delete-attachment" 
                           onclick="return confirm('<?php _e('Anhang wirklich löschen?', 'wp-project-todos'); ?>');">
                            <?php _e('Löschen', 'wp-project-todos'); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- 11. CRON BEREICH -->
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Automatisierung & Wiederholung', 'wp-project-todos'); ?></h2>
            
            <div class="wsj-form-row">
                <div class="wsj-form-group">
                    <div class="wsj-claude-toggle">
                        <input type="checkbox" name="cron_enabled" id="cron-enabled" class="wsj-claude-checkbox" 
                               value="1" <?php checked($current['cron_enabled'], 1); ?>>
                        <label for="cron-enabled" class="wsj-claude-label">
                            <?php _e('Automatische Wiederholung aktivieren', 'wp-project-todos'); ?>
                        </label>
                    </div>
                </div>

                <div class="wsj-form-group">
                    <label for="cron-schedule" class="wsj-form-label"><?php _e('Zeitplan', 'wp-project-todos'); ?></label>
                    <select name="cron_schedule" id="cron-schedule" class="wsj-form-select">
                        <option value="hourly" <?php selected($current['cron_schedule'], 'hourly'); ?>><?php _e('Stündlich', 'wp-project-todos'); ?></option>
                        <option value="daily" <?php selected($current['cron_schedule'], 'daily'); ?>><?php _e('Täglich', 'wp-project-todos'); ?></option>
                        <option value="weekly" <?php selected($current['cron_schedule'], 'weekly'); ?>><?php _e('Wöchentlich', 'wp-project-todos'); ?></option>
                        <option value="monthly" <?php selected($current['cron_schedule'], 'monthly'); ?>><?php _e('Monatlich', 'wp-project-todos'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 12. VERSIONSVERLAUF (nur bei Bearbeitung mit Version History) -->
        <?php if ($is_editing && !empty($todo->version_history)): ?>
        <div class="wsj-form-section">
            <h2 class="wsj-form-section-title"><?php _e('Version History', 'wp-project-todos'); ?></h2>
            <?php echo $this->render_version_history($todo->id); ?>
        </div>
        <?php endif; ?>
        
        <!-- 12b. EINFACHER BEARBEITUNGSVERLAUF (nur bei Bearbeitung ohne Version History) -->
        <?php if ($is_editing && empty($todo->version_history) && !empty($todo->claude_notes)): ?>
        <div class="wsj-version-history">
            <div class="wsj-version-header" onclick="toggleVersionHistory()">
                <h2 class="wsj-version-title"><?php _e('Bearbeitungsverlauf', 'wp-project-todos'); ?></h2>
                <button type="button" class="wsj-version-toggle collapsed" id="version-toggle"></button>
            </div>
            <div class="wsj-version-content" id="version-content">
                <div class="wsj-version-item">
                    <div class="wsj-version-meta">
                        <span class="wsj-version-date"><?php echo wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($todo->updated_at . ' UTC')); ?></span>
                        <span class="wsj-version-status <?php echo esc_attr($todo->status); ?>"><?php echo esc_html($todo->status); ?></span>
                    </div>
                    <?php if (!empty($todo->claude_notes)): ?>
                    <div class="wsj-version-description">
                        <strong><?php _e('Claude Notizen:', 'wp-project-todos'); ?></strong><br>
                        <?php echo nl2br(esc_html($todo->claude_notes)); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($todo->bemerkungen)): ?>
                    <div class="wsj-version-description">
                        <strong><?php _e('Bemerkungen:', 'wp-project-todos'); ?></strong><br>
                        <?php echo nl2br(esc_html($todo->bemerkungen)); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 13. SCHWEBENDE BUTTONS - TEMPORÄR DEAKTIVIERT -->
        <?php /* Temporär deaktiviert wegen Overlay-Problem
        <div class="wsj-floating-save-group">
            <!-- Speichern Button -->
            <button type="submit" name="submit" class="wsj-floating-save wsj-floating-save-regular" id="floating-save-btn">
                <span class="wsj-button-icon">💾</span>
                <span class="wsj-button-text"><?php echo $is_editing ? __('Aktualisieren', 'wp-project-todos') : __('Speichern', 'wp-project-todos'); ?></span>
            </button>
            
            <?php if (!$is_editing): ?>
            <!-- Direkt Ausführen Button -->
            <button type="button" class="wsj-floating-save wsj-floating-execute" id="floating-execute-btn" 
                    data-action="execute">
                <span class="wsj-button-icon">🚀</span>
                <span class="wsj-button-text"><?php _e('Speichern & Ausführen', 'wp-project-todos'); ?></span>
            </button>
            <?php endif; ?>
        </div>
        */ ?>
        
        <!-- Loading Overlay für Direkt-Ausführung - DEAKTIVIERT -->
        <div id="wsj-execute-overlay" class="wsj-execute-overlay wsj-hidden" style="display: none !important;">
            <div class="wsj-execute-modal">
                <div class="wsj-execute-spinner"></div>
                <h3><?php _e('Aufgabe wird gespeichert und ausgeführt...', 'wp-project-todos'); ?></h3>
                <p id="wsj-execute-status"><?php _e('Bitte warten...', 'wp-project-todos'); ?></p>
            </div>
        </div>

        <!-- 14. NORMALER SPEICHERN-BUTTON -->
        <div class="wsj-form-section">
            <div class="wsj-form-row">
                <div class="wsj-form-group">
                    <button type="submit" name="submit" class="button button-primary button-large">
                        <?php echo $is_editing ? __('Aufgabe aktualisieren', 'wp-project-todos') : __('Aufgabe erstellen', 'wp-project-todos'); ?>
                    </button>
                    
                    <button type="submit" name="save_only" class="button button-primary button-large" style="margin-left: 10px; background: #28a745;">
                        💾 <?php _e('Nur Speichern (ohne Redirect)', 'wp-project-todos'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button button-secondary button-large" style="margin-left: 10px;">
                        <?php _e('Zurück zur Liste', 'wp-project-todos'); ?>
                    </a>
                    
                    <?php if ($is_editing): ?>
                    <button type="button" class="button button-secondary button-large send-single-todo" 
                            data-todo-id="<?php echo $todo->id; ?>" style="margin-left: 10px; background: #667eea; color: white;">
                        📤 <?php _e('An Claude senden', 'wp-project-todos'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Additional WSJ Styles for this page -->
<style>
.wsj-floating-save-group {
    position: fixed;
    bottom: var(--wsj-spacing-xl);
    right: var(--wsj-spacing-xl);
    z-index: 9999;
    display: flex;
    gap: var(--wsj-spacing-sm);
    flex-direction: column;
    align-items: flex-end;
}

.wsj-floating-save-group .wsj-floating-save {
    position: static;
    width: 180px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--wsj-spacing-xs);
    padding: var(--wsj-spacing-md) var(--wsj-spacing-lg);
    border-radius: 50px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: all 0.3s ease;
    box-shadow: var(--wsj-shadow-lg);
    border: none;
    cursor: pointer;
}

.wsj-floating-save-regular {
    background: var(--wsj-accent);
    color: var(--wsj-text-white);
}

.wsj-floating-save-regular:hover {
    background: var(--wsj-accent-hover);
    transform: translateY(-2px);
    box-shadow: 0 15px 25px rgba(0, 0, 0, 0.15);
}

.wsj-floating-execute {
    background: var(--wsj-success);
    color: var(--wsj-text-white);
}

.wsj-floating-execute:hover {
    background: #38a169;
    transform: translateY(-2px);
    box-shadow: 0 15px 25px rgba(56, 161, 105, 0.3);
}

.wsj-floating-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.wsj-button-icon {
    font-size: 16px;
}

.wsj-button-text {
    font-size: var(--wsj-font-size-sm);
}

/* Execute Overlay */
.wsj-execute-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    display: none; /* Standardmäßig versteckt */
    align-items: center;
    justify-content: center;
}

.wsj-execute-overlay:not(.wsj-hidden) {
    display: flex; /* Nur anzeigen wenn wsj-hidden entfernt wurde */
}

.wsj-execute-modal {
    background: var(--wsj-bg-white);
    border-radius: var(--wsj-radius-lg);
    padding: var(--wsj-spacing-xl);
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: var(--wsj-shadow-lg);
}

.wsj-execute-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--wsj-border-light);
    border-left: 4px solid var(--wsj-success);
    border-radius: 50%;
    animation: wsj-spin 1s linear infinite;
    margin: 0 auto var(--wsj-spacing-md) auto;
}

@keyframes wsj-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.wsj-execute-modal h3 {
    color: var(--wsj-text-primary);
    margin: 0 0 var(--wsj-spacing-sm) 0;
    font-size: var(--wsj-font-size-lg);
}

.wsj-execute-modal p {
    color: var(--wsj-text-secondary);
    margin: 0;
    font-size: var(--wsj-font-size-sm);
}

.attachment-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.attachment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--wsj-spacing-sm);
    border: 1px solid var(--wsj-border-light);
    border-radius: var(--wsj-radius-sm);
    margin-bottom: var(--wsj-spacing-xs);
    background: var(--wsj-bg-light);
}

.attachment-info {
    display: flex;
    align-items: center;
    gap: var(--wsj-spacing-sm);
}

.attachment-icon {
    font-size: 20px;
}

.attachment-name {
    font-weight: 600;
    color: var(--wsj-text-primary);
}

.attachment-meta {
    font-size: var(--wsj-font-size-xs);
    color: var(--wsj-text-muted);
}

.delete-attachment {
    color: var(--wsj-danger);
    text-decoration: none;
    font-size: var(--wsj-font-size-xs);
    padding: 4px 8px;
    border-radius: var(--wsj-radius-sm);
    transition: all 0.3s ease;
}

.delete-attachment:hover {
    background: var(--wsj-danger);
    color: white;
}

/* Agent System Styles */
.wsj-agent-count-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
    gap: var(--wsj-spacing-xs);
    margin-bottom: var(--wsj-spacing-sm);
}

.wsj-agent-count-option {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.wsj-agent-count-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.wsj-agent-count-label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 40px;
    border: 2px solid var(--wsj-border-light);
    border-radius: var(--wsj-radius-sm);
    background: var(--wsj-bg-white);
    color: var(--wsj-text-secondary);
    font-weight: 600;
    transition: all 0.3s ease;
}

.wsj-agent-count-checkbox:checked + .wsj-agent-count-label {
    background: var(--wsj-primary);
    color: white;
    border-color: var(--wsj-primary);
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.wsj-agent-count-option:hover .wsj-agent-count-label {
    border-color: var(--wsj-primary);
    transform: translateY(-1px);
}

.wsj-execution-mode-options {
    display: flex;
    flex-direction: column;
    gap: var(--wsj-spacing-sm);
}

.wsj-execution-mode-option {
    display: flex;
    align-items: flex-start;
    padding: var(--wsj-spacing-sm);
    border: 2px solid var(--wsj-border-light);
    border-radius: var(--wsj-radius-sm);
    background: var(--wsj-bg-white);
    cursor: pointer;
    transition: all 0.3s ease;
}

.wsj-execution-mode-option:hover {
    border-color: var(--wsj-primary);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.wsj-execution-mode-radio {
    margin-right: var(--wsj-spacing-sm);
    margin-top: 2px;
}

.wsj-execution-mode-radio:checked + .wsj-execution-mode-label {
    color: var(--wsj-primary);
}

.wsj-execution-mode-option:has(.wsj-execution-mode-radio:checked) {
    border-color: var(--wsj-primary);
    background: var(--wsj-bg-light);
}

.wsj-execution-mode-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.wsj-execution-mode-label strong {
    font-weight: 600;
    color: var(--wsj-text-primary);
}

.wsj-execution-mode-label small {
    color: var(--wsj-text-muted);
    font-size: var(--wsj-font-size-sm);
    line-height: 1.4;
}

.wsj-agent-info-box {
    background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
    border: 1px solid #d4e6ff;
    border-radius: var(--wsj-radius-md);
    padding: var(--wsj-spacing-md);
    margin-top: var(--wsj-spacing-md);
}

.wsj-agent-info-box h4 {
    margin: 0 0 var(--wsj-spacing-sm) 0;
    color: var(--wsj-primary);
    font-size: var(--wsj-font-size-md);
}

.wsj-agent-info-box ul {
    margin: 0;
    padding-left: var(--wsj-spacing-md);
}

.wsj-agent-info-box li {
    margin-bottom: var(--wsj-spacing-xs);
    color: var(--wsj-text-secondary);
    font-size: var(--wsj-font-size-sm);
    line-height: 1.5;
}

.wsj-agent-info-box li strong {
    color: var(--wsj-text-primary);
}

@media (max-width: 768px) {
    .wsj-floating-save-group {
        position: static;
        margin-top: var(--wsj-spacing-lg);
        flex-direction: row;
        justify-content: center;
    }
    
    .wsj-floating-save-group .wsj-floating-save {
        width: auto;
        flex: 1;
    }
    
    .wsj-agent-count-grid {
        grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
    }
    
    .wsj-execution-mode-options {
        gap: var(--wsj-spacing-xs);
    }
    
    .wsj-execution-mode-option {
        padding: var(--wsj-spacing-xs);
    }
}
</style>

<!-- JavaScript for Interactive Elements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bei neuen Tasks sicherstellen, dass Status gesetzt ist
    const statusInput = document.querySelector('#status-input');
    if (statusInput && !statusInput.value) {
        statusInput.value = 'offen';
        // Aktiviere den "Offen" Button
        const offenBtn = document.querySelector('.wsj-status-btn[data-value="offen"]');
        if (offenBtn) {
            offenBtn.classList.add('active');
        }
    }
    
    // Status Button Handling
    initButtonGroup('.wsj-status-buttons .wsj-status-btn', '#status-input');
    initButtonGroup('.wsj-priority-buttons .wsj-priority-btn', '#priority-input');
    initButtonGroup('[data-value="bypass_permissions"], [data-value="safe_mode"], [data-value="read_only"], [data-value="admin_approval"]', '#claude-mode-input');
    initButtonGroup('[data-value="parallel"], [data-value="sequential"]', '#execution-mode-input');
    
    // Scope Button Handling with Custom Input
    initScopeButtons();
    
    // Agent Count Handling
    initAgentCounts();
    
    // Agent Settings Management
    initAgentSettings();
    
    // Form Validation
    initFormValidation();
    
    // Auto-save functionality
    initAutoSave();
});

function initButtonGroup(selector, inputId) {
    document.querySelectorAll(selector).forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from siblings
            this.parentElement.querySelectorAll('.wsj-status-btn, .wsj-priority-btn').forEach(sibling => {
                sibling.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Update hidden input
            const input = document.querySelector(inputId);
            if (input) {
                input.value = this.dataset.value;
            }
        });
    });
}

function initScopeButtons() {
    const scopeButtons = document.querySelectorAll('[data-value="To-Do Plugin"], [data-value="ForexSignale"], [data-value="Homepage Control"], [data-value="Article Builder"]');
    const customButton = document.querySelector('#scope-custom-btn');
    const customInput = document.querySelector('#scope-custom-input');
    const scopeInput = document.querySelector('#scope-input');
    
    scopeButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            scopeButtons.forEach(b => b.classList.remove('active'));
            customButton.classList.remove('active');
            this.classList.add('active');
            customInput.classList.add('wsj-hidden');
            scopeInput.value = this.dataset.value;
        });
    });
    
    if (customButton) {
        customButton.addEventListener('click', function(e) {
            e.preventDefault();
            scopeButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            customInput.classList.remove('wsj-hidden');
            customInput.focus();
        });
    }
    
    if (customInput) {
        customInput.addEventListener('input', function() {
            scopeInput.value = this.value;
        });
    }
}

function initAgentCounts() {
    const agentCheckboxes = document.querySelectorAll('.wsj-agent-count-checkbox');
    const agentsInput = document.querySelector('#agents-input');
    
    agentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Uncheck all other checkboxes (single selection)
            agentCheckboxes.forEach(cb => {
                if (cb !== this) {
                    cb.checked = false;
                }
            });
            
            // Update agents input with selected count
            if (this.checked) {
                agentsInput.value = this.value;
            } else {
                agentsInput.value = '1'; // Default to 1 agent
            }
            
            updateAgentSettings();
        });
    });
}

// Global function for updating agent settings
function updateAgentSettings(skipPromptUpdate = false) {
    const selectedCount = document.querySelector('.wsj-agent-count-checkbox:checked');
    const selectedMode = document.querySelector('.wsj-execution-mode-radio:checked');
    const agentSettingsInput = document.querySelector('#agent-settings-input');
    
    const settings = {
        agent_count: selectedCount ? parseInt(selectedCount.value) : 1,
        execution_mode: selectedMode ? selectedMode.value : 'default',
        timestamp: new Date().toISOString()
    };
    
    if (agentSettingsInput) {
        agentSettingsInput.value = JSON.stringify(settings);
    }
    
    // Add to prompt automatically - aber nur wenn nicht explizit übersprungen
    // UND nur wenn es ein manuelle Änderung war (nicht beim initialen Laden)
    if (!skipPromptUpdate && window.userChangedAgentSettings === true) {
        addAgentSettingsToPrompt(settings);
    }
}

function initAgentSettings() {
    const agentCountCheckboxes = document.querySelectorAll('.wsj-agent-count-checkbox');
    const executionModeRadios = document.querySelectorAll('.wsj-execution-mode-radio');
    
    // Flag to track manual changes
    window.userChangedAgentSettings = false;
    
    // Listen for changes
    agentCountCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            window.userChangedAgentSettings = true;
            updateAgentSettings();
        });
    });
    
    executionModeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            window.userChangedAgentSettings = true;
            updateAgentSettings();
        });
    });
    
    // Initial update - NUR wenn es ein NEUER Todo ist (keine ID in URL)
    const urlParams = new URLSearchParams(window.location.search);
    const todoId = urlParams.get('id');
    // NIEMALS automatisch Agent-Settings einfügen - weder bei neuen noch bestehenden Todos
    // Description-Feld bleibt leer, Prompt-Feld nur bei manueller Änderung befüllen
    updateAgentSettings(true); // true = skipPromptUpdate
}


function addAgentSettingsToPrompt(settings) {
    // WICHTIG: Jetzt nutzen wir das PROMPT-Feld (#todo-prompt), nicht die Description!
    const promptTextarea = document.querySelector('#todo-prompt');
    if (!promptTextarea) return;
    
    // Die Description-Textarea (#todo-description) bleibt IMMER unberührt!
    const descriptionTextarea = document.querySelector('#todo-description');
    if (descriptionTextarea) {
        console.log('[DEBUG] Description field exists, keeping it untouched');
    }
    
    const currentPrompt = promptTextarea.value;
    
    // Nur bei manueller Änderung der Agent-Settings hinzufügen
    if (currentPrompt.trim() && !currentPrompt.includes('--- AGENT CONFIGURATION ---')) {
        console.log('[DEBUG] Prompt field has content, not overwriting:', currentPrompt.substring(0, 50));
        return;
    }
    
    const agentPrompt = generateAgentPrompt(settings);
    
    // Check if agent settings already exist in prompt
    const agentSectionRegex = /\n\n--- AGENT CONFIGURATION ---[\s\S]*?--- END AGENT CONFIG ---/;
    
    let newPrompt;
    if (agentSectionRegex.test(currentPrompt)) {
        // Replace existing agent configuration
        newPrompt = currentPrompt.replace(agentSectionRegex, agentPrompt);
    } else {
        // Add agent configuration to end
        newPrompt = currentPrompt + (currentPrompt.trim() ? '\n\n' : '') + agentPrompt;
    }
    
    promptTextarea.value = newPrompt;
}
function generateAgentPrompt(settings) {
    let prompt = '\n\n--- AGENT CONFIGURATION ---\n';
    
    if (settings.agent_count > 1) {
        prompt += `Verwende ${settings.agent_count} parallele Agenten für diese Aufgabe.\n\n`;
        
        switch (settings.execution_mode) {
            case 'parallel':
                prompt += 'PARALLEL MODUS:\n';
                prompt += '- Teile die Aufgabe in unabhängige Teilaufgaben auf\n';
                prompt += '- Alle Agenten arbeiten gleichzeitig\n';
                prompt += '- Koordiniere die Ergebnisse am Ende\n';
                break;
                
            case 'hierarchical':
                prompt += 'HIERARCHISCHER MODUS:\n';
                prompt += '- Agent 1 fungiert als Master-Koordinator\n';
                prompt += '- Master-Agent verteilt Aufgaben an Sub-Agenten\n';
                prompt += '- Regelmäßige Synchronisation und Qualitätskontrolle\n';
                break;
                
            case 'default':
                prompt += 'AUTOMATISCHER MODUS:\n';
                prompt += '- Analysiere die Aufgaben-Komplexität\n';
                prompt += '- Wähle die optimale Koordinationsstrategie\n';
                prompt += '- Nutze alle verfügbaren Agenten effizient\n';
                break;
        }
        
        prompt += '\nAgent-Verteilung je nach Aufgabe:\n';
        if (settings.agent_count >= 5) {
            prompt += '- Frontend/UI: 2 Agenten\n';
            prompt += '- Backend/API: 2 Agenten\n';
            prompt += '- Testing/QA: 1 Agent\n';
        }
        if (settings.agent_count >= 10) {
            prompt += '- Dokumentation: 1 Agent\n';
            prompt += '- Performance: 1 Agent\n';
            prompt += '- Security Review: 1 Agent\n';
        }
        if (settings.agent_count >= 15) {
            prompt += '- Code Review: 2 Agenten\n';
            prompt += '- Integration: 1 Agent\n';
        }
    } else {
        prompt += 'SINGLE-AGENT MODUS:\n';
        prompt += '- Ein Agent bearbeitet alle Teilaufgaben sequenziell\n';
        prompt += '- Fokus auf Qualität und Gründlichkeit\n';
    }
    
    prompt += '\n--- END AGENT CONFIG ---';
    
    return prompt;
}

function initFormValidation() {
    const form = document.querySelector('#wsj-todo-form');
    const titleInput = document.querySelector('#todo-title');
    
    form.addEventListener('submit', function(e) {
        // DEBUG: Log textarea value before submit
        const descTextarea = document.querySelector('#todo-prompt');
        if (descTextarea) {
            console.log('[SUBMIT DEBUG] Description value:', descTextarea.value);
            console.log('[SUBMIT DEBUG] Description length:', descTextarea.value.length);
        }
        
        let isValid = true;
        
        // Title validation
        if (!titleInput.value.trim()) {
            alert('<?php _e('Bitte geben Sie einen Titel ein.', 'wp-project-todos'); ?>');
            titleInput.focus();
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
}

function initAutoSave() {
    const form = document.querySelector('#wsj-todo-form');
    // const saveBtn = document.querySelector('#floating-save-btn'); // Deaktiviert - Button entfernt
    
    // Save form data to localStorage periodically
    setInterval(function() {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem('wsj_todo_autosave', JSON.stringify(data));
    }, 30000); // Save every 30 seconds
    
    // Load from localStorage on page load
    const savedData = localStorage.getItem('wsj_todo_autosave');
    if (savedData && !<?php echo $is_editing ? 'true' : 'false'; ?>) {
        try {
            const data = JSON.parse(savedData);
            if (confirm('<?php _e('Möchten Sie die zuletzt gespeicherten Daten wiederherstellen?', 'wp-project-todos'); ?>')) {
                restoreFormData(data);
            }
        } catch (e) {
            console.log('Failed to restore autosave data');
        }
    }
}

function restoreFormData(data) {
    Object.keys(data).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = data[key] === '1';
            } else {
                element.value = data[key];
            }
        }
    });
}

function toggleVersionHistory() {
    const content = document.querySelector('#version-content');
    const toggle = document.querySelector('#version-toggle');
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        toggle.classList.remove('expanded');
        toggle.classList.add('collapsed');
    } else {
        content.classList.add('expanded');
        toggle.classList.remove('collapsed');
        toggle.classList.add('expanded');
    }
}

// Debug form submission
document.querySelector('#wsj-todo-form').addEventListener('submit', function(e) {
    // Debug: Check all form values before submit
    const titleField = document.querySelector('#todo-title');
    const descField = document.querySelector('#todo-description');
    const statusField = document.querySelector('#status-input');
    
    console.log('=== FORM SUBMIT DEBUG ===');
    console.log('Title:', titleField ? titleField.value : 'FIELD NOT FOUND');
    console.log('Description:', descField ? descField.value : 'FIELD NOT FOUND');
    console.log('Status:', statusField ? statusField.value : 'FIELD NOT FOUND');
    
    // Create hidden debug field to track what was submitted
    const debugInput = document.createElement('input');
    debugInput.type = 'hidden';
    debugInput.name = 'debug_description_length';
    debugInput.value = descField ? descField.value.length : 0;
    this.appendChild(debugInput);
    
    // Don't prevent submit, but log it
    console.log('Form is being submitted with description length:', debugInput.value);
    
    localStorage.removeItem('wsj_todo_autosave');
});

// Direct Execute Functionality - DEAKTIVIERT wegen fehlenden floating buttons
document.addEventListener('DOMContentLoaded', function() {
    const executeBtn = null; // document.querySelector('#floating-execute-btn'); // Deaktiviert
    const overlay = document.querySelector('#wsj-execute-overlay');
    const statusElement = document.querySelector('#wsj-execute-status');
    const form = document.querySelector('#wsj-todo-form');
    
    if (executeBtn) { // Wird nie ausgeführt da executeBtn = null
        executeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Validate form first
            const titleInput = document.querySelector('#todo-title');
            if (!titleInput.value.trim()) {
                alert('<?php _e('Bitte geben Sie einen Titel ein.', 'wp-project-todos'); ?>');
                titleInput.focus();
                return;
            }
            
            // Show overlay
            overlay.classList.remove('wsj-hidden');
            executeBtn.disabled = true;
            
            // Update status
            statusElement.textContent = '<?php _e('Aufgabe wird gespeichert...', 'wp-project-todos'); ?>';
            
            // Create FormData for AJAX submission
            const formData = new FormData(form);
            formData.append('submit_and_execute', '1');
            formData.append('ajax_execute', '1');
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusElement.textContent = '<?php _e('Aufgabe gespeichert, wird an Claude gesendet...', 'wp-project-todos'); ?>';
                    
                    // Wait a moment then redirect or update status
                    setTimeout(() => {
                        if (data.redirect) {
                            // Redirect to the appropriate status page
                            const statusInput = document.querySelector('#status-input');
                            const currentStatus = statusInput ? statusInput.value : 'in_progress';
                            let redirectUrl = data.redirect;
                            
                            // Add status filter to redirect URL
                            if (redirectUrl.includes('?')) {
                                redirectUrl += '&filter_status=' + currentStatus;
                            } else {
                                redirectUrl += '?filter_status=' + currentStatus;
                            }
                            
                            window.location.href = redirectUrl;
                        } else {
                            overlay.classList.add('wsj-hidden');
                            executeBtn.disabled = false;
                            
                            // Show success message
                            showSuccessMessage(data.message || '<?php _e('Aufgabe erfolgreich erstellt und gesendet!', 'wp-project-todos'); ?>');
                        }
                    }, 1500);
                } else {
                    overlay.classList.add('wsj-hidden');
                    executeBtn.disabled = false;
                    alert(data.message || '<?php _e('Fehler beim Speichern der Aufgabe.', 'wp-project-todos'); ?>');
                }
            })
            .catch(error => {
                console.error('Execute error:', error);
                overlay.classList.add('wsj-hidden');
                executeBtn.disabled = false;
                alert('<?php _e('Netzwerkfehler beim Speichern der Aufgabe.', 'wp-project-todos'); ?>');
            });
        });
    }
    
    // Helper function to show success message
    function showSuccessMessage(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'notice notice-success is-dismissible';
        successDiv.innerHTML = '<p>' + message + '</p>';
        
        const wrap = document.querySelector('.wrap');
        if (wrap) {
            wrap.insertBefore(successDiv, wrap.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                successDiv.remove();
            }, 5000);
        }
    }
    
    // Handle ESC key to close overlay
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !overlay.classList.contains('wsj-hidden')) {
            overlay.classList.add('wsj-hidden');
            executeBtn.disabled = false;
        }
    });
    
    // Visual feedback for floating buttons on scroll - DEAKTIVIERT
    /* let scrollTimeout;
    window.addEventListener('scroll', function() {
        const floatingGroup = document.querySelector('.wsj-floating-save-group');
        if (floatingGroup) {
            floatingGroup.style.opacity = '0.7';
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                floatingGroup.style.opacity = '1';
            }, 150);
        }
    }); */
    
    // Handle normal save button to redirect to correct status page - DEAKTIVIERT
    const normalSaveBtn = null; // document.querySelector('#floating-save-btn');
    if (normalSaveBtn) { // Wird nie ausgeführt da normalSaveBtn = null
        form.addEventListener('submit', function(e) {
            // Only handle if it's the floating save button
            if (e.submitter === normalSaveBtn) {
                // Add status to form action for correct redirect
                const statusInput = document.querySelector('#status-input');
                if (statusInput && statusInput.value) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'redirect_status';
                    hiddenInput.value = statusInput.value;
                    form.appendChild(hiddenInput);
                }
            }
        });
    }
});
</script>