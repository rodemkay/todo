<?php
/**
 * HTML Output Page
 * Shows formatted HTML output for todos
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions - allow all logged in users to view
if (!is_user_logged_in()) {
    wp_die(__('Du bist nicht berechtigt, auf diese Seite zuzugreifen.', 'wp-project-todos'));
}

// Get todo ID from URL
$todo_id = isset($_GET['todo_id']) ? intval($_GET['todo_id']) : 0;

if (!$todo_id) {
    echo '<div class="wrap"><h1>HTML Output</h1><p>Keine Todo-ID angegeben.</p></div>';
    return;
}

// Get todo from database
global $wpdb;
$table = $wpdb->prefix . 'project_todos';
$todo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $todo_id));

if (!$todo) {
    echo '<div class="wrap"><h1>HTML Output</h1><p>Todo nicht gefunden.</p></div>';
    return;
}
?>

<div class="wrap">
    <h1>HTML Output - Todo #<?php echo esc_html($todo_id); ?></h1>
    
    <div style="background: white; padding: 20px; border: 1px solid #ccc; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <!-- ANFORDERUNG SECTION (Oben) -->
        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #2196F3;">
            <h2 style="margin-top: 0; color: #2196F3;">📋 Ursprüngliche Anforderung</h2>
            
            <div style="margin: 15px 0;">
                <h3 style="color: #333; margin-bottom: 10px;"><?php echo esc_html($todo->title); ?></h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 15px 0; background: white; padding: 15px; border-radius: 5px;">
                    <div><strong>Status:</strong> <span style="padding: 3px 8px; background: #e3f2fd; border-radius: 3px;"><?php echo esc_html($todo->status); ?></span></div>
                    <div><strong>Priorität:</strong> <span style="padding: 3px 8px; background: #fff3e0; border-radius: 3px;"><?php echo esc_html($todo->priority); ?></span></div>
                    <div><strong>Bereich:</strong> <span style="padding: 3px 8px; background: #f3e5f5; border-radius: 3px;"><?php echo esc_html($todo->scope); ?></span></div>
                    <div><strong>Erstellt:</strong> <?php echo date('d.m.Y H:i', strtotime($todo->created_at)); ?></div>
                    <?php if ($todo->completed_date): ?>
                    <div><strong>Abgeschlossen:</strong> <?php echo date('d.m.Y H:i', strtotime($todo->completed_date)); ?></div>
                    <?php endif; ?>
                    <?php if ($todo->working_directory): ?>
                    <div style="grid-column: 1 / -1;"><strong>Arbeitsverzeichnis:</strong> <code style="background: #f5f5f5; padding: 2px 5px; border-radius: 3px;"><?php echo esc_html($todo->working_directory); ?></code></div>
                    <?php endif; ?>
                </div>
                
                <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #e0e0e0;">
                    <h4 style="margin-top: 0; color: #555;">Beschreibung:</h4>
                    <div style="line-height: 1.6;">
                        <?php echo wp_kses_post(nl2br($todo->description)); ?>
                    </div>
                </div>
                
                <?php if ($todo->bemerkungen): ?>
                <div style="background: #fffbf0; padding: 15px; border-radius: 5px; margin-top: 15px; border: 1px solid #ffecb3;">
                    <h4 style="margin-top: 0; color: #ff9800;">💡 Bemerkungen:</h4>
                    <?php echo wp_kses_post(nl2br($todo->bemerkungen)); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CLAUDE ZUSAMMENFASSUNG SECTION (Unten) -->
        <?php if ($todo->claude_notes || $todo->claude_output): ?>
        <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; margin-top: 25px; border-left: 4px solid #4caf50;">
            <h2 style="margin-top: 0; color: #4caf50;">🤖 Claude's Zusammenfassung</h2>
            
            <?php if ($todo->claude_notes): ?>
            <div style="background: white; padding: 15px; border-radius: 5px; margin-top: 15px; border: 1px solid #c8e6c9;">
                <h4 style="margin-top: 0; color: #555;">Notizen & Fortschritt:</h4>
                <div style="line-height: 1.6;">
                    <?php echo wp_kses_post($todo->claude_notes); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($todo->claude_output): ?>
            <div style="background: white; padding: 15px; border-radius: 5px; margin-top: 15px; border: 1px solid #c8e6c9;">
                <h4 style="margin-top: 0; color: #555;">Detaillierte Ausgabe:</h4>
                <div style="line-height: 1.6; font-family: 'Courier New', monospace; font-size: 13px; background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto;">
                    <?php echo wp_kses_post(nl2br($todo->claude_output)); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($todo->status === 'completed'): ?>
            <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 15px; text-align: center;">
                <strong style="color: #2e7d32;">✅ Aufgabe erfolgreich abgeschlossen</strong>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="background: #fafafa; padding: 20px; border-radius: 8px; margin-top: 25px; border: 1px dashed #ccc; text-align: center;">
            <p style="color: #999; margin: 0;">⏳ Claude's Zusammenfassung wird nach Bearbeitung hier angezeigt...</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ACTION BUTTONS -->
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button">
            ← Zurück zur Liste
        </a>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $todo_id); ?>" class="button">
            ✏️ Bearbeiten
        </a>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-claude&todo_id=' . $todo_id); ?>" class="button">
            📊 Claude Output
        </a>
        
        <!-- NEUER WIEDERVORLAGE BUTTON -->
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&continue_from=' . $todo_id); ?>" 
           class="button button-primary" 
           style="background: #4caf50; border-color: #4caf50;">
            🔄 Wiedervorlage (Neue Aufgabe basierend hierauf)
        </a>
    </div>
</div>