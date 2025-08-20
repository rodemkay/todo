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

// Default project handling
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

<div class="wrap wp-project-todos-new">
    <h1 class="wp-heading-inline">
        <?php echo $todo ? 'Aufgabe bearbeiten' : 'Neue Aufgabe erstellen'; ?>
    </h1>
    
    <?php if (isset($_GET['message'])): ?>
        <?php if ($_GET['message'] === 'saved'): ?>
            <div class="notice notice-success is-dismissible">
                <p>Aufgabe erfolgreich gespeichert!</p>
            </div>
        <?php elseif ($_GET['message'] === 'created'): ?>
            <div class="notice notice-success is-dismissible">
                <p>Neue Aufgabe erfolgreich erstellt!</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" class="todo-form">
        <?php wp_nonce_field('wp_project_todos_edit', 'wp_project_todos_nonce'); ?>
        <?php if ($todo): ?>
            <input type="hidden" name="todo_id" value="<?php echo esc_attr($todo->id); ?>">
        <?php endif; ?>

        <div class="form-section">
            <h2>📝 Grundinformationen</h2>
            
            <div class="form-group">
                <label for="title">Titel *</label>
                <input type="text" id="title" name="title" class="large-text" 
                       value="<?php echo esc_attr($todo->title ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Beschreibung</label>
                <textarea id="description" name="description" rows="5" class="large-text"><?php 
                    echo esc_textarea($todo->description ?? ''); 
                ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="regular-text">
                        <option value="offen" <?php selected($todo->status ?? 'offen', 'offen'); ?>>Offen</option>
                        <option value="in_progress" <?php selected($todo->status ?? '', 'in_progress'); ?>>In Bearbeitung</option>
                        <option value="completed" <?php selected($todo->status ?? '', 'completed'); ?>>Abgeschlossen</option>
                        <option value="blocked" <?php selected($todo->status ?? '', 'blocked'); ?>>Blockiert</option>
                    </select>
                </div>

                <div class="form-group half">
                    <label for="priority">Priorität</label>
                    <select id="priority" name="priority" class="regular-text">
                        <option value="0" <?php selected($todo->priority ?? 0, 0); ?>>Niedrig</option>
                        <option value="1" <?php selected($todo->priority ?? 0, 1); ?>>Normal</option>
                        <option value="2" <?php selected($todo->priority ?? 0, 2); ?>>Hoch</option>
                        <option value="3" <?php selected($todo->priority ?? 0, 3); ?>>Sehr hoch</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>🎯 Projekt-Einstellungen</h2>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="scope">Bereich</label>
                    <input type="text" id="scope" name="scope" class="regular-text" 
                           value="<?php echo esc_attr($todo->scope ?? $default_scope); ?>"
                           placeholder="z.B. wordpress, theme, plugin">
                </div>

                <div class="form-group half">
                    <label for="working_directory">Arbeitsverzeichnis</label>
                    <input type="text" id="working_directory" name="working_directory" class="regular-text"
                           value="<?php echo esc_attr($todo->working_directory ?? $default_working_dir); ?>"
                           placeholder="z.B. /var/www/forexsignale/staging">
                </div>
            </div>

            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="set_as_default_project" value="1">
                    <span>Als Standard-Projekt für neue Aufgaben setzen</span>
                </label>
                <?php if ($default_project): ?>
                    <?php 
                    $default_info = $wpdb->get_row($wpdb->prepare(
                        "SELECT title, scope FROM $table WHERE id = %d", 
                        $default_project
                    )); 
                    if ($default_info): ?>
                        <p class="description">
                            Aktuelles Standard-Projekt: <strong><?php echo esc_html($default_info->title); ?></strong>
                            (<?php echo esc_html($default_info->scope); ?>)
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>🤖 Claude-Integration</h2>
            
            <div class="form-group">
                <label for="claude_notes">Claude Notizen</label>
                <textarea id="claude_notes" name="claude_notes" rows="4" class="large-text"
                          placeholder="Spezielle Anweisungen für Claude..."><?php 
                    echo esc_textarea($todo->claude_notes ?? ''); 
                ?></textarea>
            </div>

            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="bearbeiten" value="1" 
                           <?php checked($todo->bearbeiten ?? 1, 1); ?>>
                    <span>🤖 Von Claude bearbeiten lassen</span>
                </label>
                <p class="description">Aktiviert diese Aufgabe für die automatische Bearbeitung durch Claude</p>
            </div>
        </div>

        <div class="form-section">
            <h2>⏰ Wiederkehrende Aufgabe (CRON)</h2>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="is_recurring" id="is_recurring" value="1"
                           <?php checked($todo->is_recurring ?? 0, 1); ?>
                           onchange="toggleRecurringOptions(this)">
                    <span>Dies ist eine wiederkehrende Aufgabe</span>
                </label>
            </div>

            <div id="recurring_options" style="display: <?php echo ($todo->is_recurring ?? 0) ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="recurring_type">Wiederholungsintervall</label>
                    <select id="recurring_type" name="recurring_type" class="regular-text">
                        <option value="manual" <?php selected($todo->recurring_type ?? 'manual', 'manual'); ?>>Manuell</option>
                        <option value="hourly" <?php selected($todo->recurring_type ?? '', 'hourly'); ?>>Stündlich</option>
                        <option value="daily" <?php selected($todo->recurring_type ?? '', 'daily'); ?>>Täglich</option>
                        <option value="weekly" <?php selected($todo->recurring_type ?? '', 'weekly'); ?>>Wöchentlich</option>
                        <option value="monthly" <?php selected($todo->recurring_type ?? '', 'monthly'); ?>>Monatlich</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>📋 Zusätzliche Informationen</h2>
            
            <div class="form-group">
                <label for="bemerkungen">Bemerkungen</label>
                <textarea id="bemerkungen" name="bemerkungen" rows="3" class="large-text"><?php 
                    echo esc_textarea($todo->bemerkungen ?? ''); 
                ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="save_todo" class="button button-primary button-large">
                <?php echo $todo ? '💾 Änderungen speichern' : '➕ Aufgabe erstellen'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" 
               class="button button-secondary button-large">
                Abbrechen
            </a>
        </div>
    </form>
</div>

<style>
.wp-project-todos-new {
    max-width: 800px;
    margin: 20px 0;
}

.todo-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.form-section {
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h2 {
    margin: 0 0 20px 0;
    font-size: 1.3em;
    color: #23282d;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #23282d;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    max-width: 100%;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group.half {
    flex: 1;
    margin-bottom: 0;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    font-weight: normal;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 8px;
}

.checkbox-group span {
    font-weight: 600;
}

.description {
    margin-top: 5px;
    color: #666;
    font-size: 13px;
    font-style: italic;
}

#recurring_options {
    margin-top: 15px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 5px;
}

.form-actions {
    padding: 20px;
    background: #f1f1f1;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
}

.wsj-filter-btn.cron {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
}

.wsj-filter-btn.claude-toggle {
    background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    color: white !important;
}
</style>

<script>
function toggleRecurringOptions(checkbox) {
    document.getElementById('recurring_options').style.display = checkbox.checked ? 'block' : 'none';
}
</script>
