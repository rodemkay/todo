<?php
/**
 * Claude Output View
 * 
 * @package WP_Project_Todos
 */

// Ensure we have a todo object
if (!isset($todo)) {
    return;
}
?>

<div class="wrap">
    <h1><?php echo esc_html($todo->title); ?> - Claude Output</h1>
    
    <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        
        <?php if (!empty($todo->bemerkungen)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: #667eea;">📝 Bemerkungen</h2>
            <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #667eea; white-space: pre-wrap;">
                <?php echo esc_html($todo->bemerkungen); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($todo->claude_notes)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: #28a745;">🤖 Claude Notizen</h2>
            <div style="background: #f0fff4; padding: 15px; border-left: 4px solid #28a745;">
                <?php echo wp_kses_post($todo->claude_notes); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($output) && is_array($output)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: #007cba;">💻 Claude Output</h2>
            <?php foreach($output as $entry): ?>
                <div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 3px;">
                    <?php if (isset($entry['timestamp'])): ?>
                        <small style="color: #666;"><?php echo esc_html($entry['timestamp']); ?></small>
                    <?php endif; ?>
                    <?php if (isset($entry['type'])): ?>
                        <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 3px; margin-left: 10px;">
                            <?php echo esc_html($entry['type']); ?>
                        </span>
                    <?php endif; ?>
                    <div style="margin-top: 10px;">
                        <?php 
                        $message = isset($entry['message']) ? $entry['message'] : $entry;
                        echo '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html($message) . '</pre>'; 
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (!empty($todo->claude_output)): ?>
        <div style="margin-bottom: 30px;">
            <h2 style="color: #007cba;">💻 Claude Output (Raw)</h2>
            <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #007cba;">
                <pre style="white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($todo->claude_output); ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($todo->bemerkungen) && empty($todo->claude_notes) && empty($todo->claude_output)): ?>
        <p style="color: #999; text-align: center; padding: 40px;">
            Keine Ausgaben für diese Aufgabe vorhanden.
        </p>
        <?php endif; ?>
        
    </div>
    
    <p>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button">
            ← Zurück zur Übersicht
        </a>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $todo->id); ?>" class="button">
            ✏️ Bearbeiten
        </a>
    </p>
</div>