<?php
/**
 * Claude Output Page
 * Shows Claude's output and work for todos
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
    echo '<div class="wrap"><h1>Claude Output</h1><p>Keine Todo-ID angegeben.</p></div>';
    return;
}

// Get todo from database
global $wpdb;
$table = $wpdb->prefix . 'project_todos';
$todo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $todo_id));

if (!$todo) {
    echo '<div class="wrap"><h1>Claude Output</h1><p>Todo nicht gefunden.</p></div>';
    return;
}
?>

<div class="wrap">
    <h1>Claude Output - Todo #<?php echo esc_html($todo_id); ?></h1>
    <h2><?php echo esc_html($todo->title); ?></h2>
    
    <style>
        .claude-output-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .no-output {
            background: #f5f5f5;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            color: #666;
        }
        .output-section {
            margin-bottom: 30px;
        }
        .output-section h3 {
            color: #4ec9b0;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .output-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
    
    <?php if ($todo->claude_notes || $todo->bemerkungen): ?>
        
        <?php if ($todo->claude_notes): ?>
        <div class="output-section">
            <h3>📝 Claude Notizen</h3>
            <div class="claude-output-viewer">
                <div class="output-content"><?php echo esc_html($todo->claude_notes); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($todo->bemerkungen): ?>
        <div class="output-section">
            <h3>💭 Bemerkungen</h3>
            <div class="claude-output-viewer">
                <div class="output-content"><?php echo esc_html($todo->bemerkungen); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="output-section">
            <h3>📊 Status Information</h3>
            <div style="background: white; padding: 15px; border-radius: 8px;">
                <p><strong>Status:</strong> 
                    <span style="padding: 5px 10px; background: <?php 
                        echo $todo->status === 'completed' ? '#4caf50' : 
                             ($todo->status === 'in_progress' ? '#2196f3' : 
                             ($todo->status === 'blocked' ? '#f44336' : '#ff9800')); 
                    ?>; color: white; border-radius: 4px;">
                        <?php echo esc_html($todo->status); ?>
                    </span>
                </p>
                <p><strong>Priorität:</strong> <?php echo esc_html($todo->priority); ?></p>
                <p><strong>Claude bearbeitet:</strong> <?php echo $todo->bearbeiten ? '✅ Ja' : '❌ Nein'; ?></p>
                <p><strong>Erstellt:</strong> <?php echo esc_html($todo->created_at); ?></p>
                <?php if ($todo->completed_date): ?>
                <p><strong>Abgeschlossen:</strong> <?php echo esc_html($todo->completed_date); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        
        <div class="no-output">
            <h2>📭 Keine Claude-Ausgabe vorhanden</h2>
            <p>Für diese Aufgabe wurde noch keine Ausgabe von Claude generiert.</p>
            <p style="margin-top: 20px;">
                <button class="button button-primary send-single-todo" 
                        data-todo-id="<?php echo $todo_id; ?>">
                    📤 An Claude senden
                </button>
            </p>
        </div>
        
    <?php endif; ?>
    
    <p style="margin-top: 30px;">
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button">
            ← Zurück zur Liste
        </a>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $todo_id); ?>" class="button">
            ✏️ Bearbeiten
        </a>
        <a href="<?php echo admin_url('admin.php?page=wp-project-todos-html&todo_id=' . $todo_id); ?>" class="button">
            📄 HTML Ansicht
        </a>
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    $('.send-single-todo').on('click', function() {
        const todoId = $(this).data('todo-id');
        const $button = $(this);
        
        $button.prop('disabled', true).text('📡 Sende...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_specific_todo_to_claude',
                todo_id: todoId,
                nonce: '<?php echo wp_create_nonce('send_todo_to_claude'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('✅ Gesendet!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    $button.text('❌ Fehler').prop('disabled', false);
                }
            },
            error: function() {
                $button.text('❌ Fehler').prop('disabled', false);
            }
        });
    });
});
</script>