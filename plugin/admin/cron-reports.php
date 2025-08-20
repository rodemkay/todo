<?php
/**
 * Enhanced Cron Reports Page
 * Dashboard mit Status-Filtern und nächster Ausführungszeit
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('Du bist nicht berechtigt, auf diese Seite zuzugreifen.', 'wp-project-todos'));
}

global $wpdb;
$reports_table = $wpdb->prefix . 'project_todo_cron_reports';
$todos_table = $wpdb->prefix . 'project_todos';

// Get filter parameters
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
$filter_todo_id = isset($_GET['todo_id']) ? intval($_GET['todo_id']) : 0;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Function to calculate next execution time
function calculate_next_execution($cron_type, $last_executed = null) {
    $now = current_time('timestamp');
    $last = $last_executed ? strtotime($last_executed) : $now;
    
    switch($cron_type) {
        case 'hourly':
            return date('Y-m-d H:00:00', strtotime('+1 hour', $last));
        case 'daily':
            return date('Y-m-d 00:00:00', strtotime('+1 day', $last));
        case 'weekly':
            return date('Y-m-d 00:00:00', strtotime('next monday', $last));
        case 'monthly':
            return date('Y-m-01 00:00:00', strtotime('+1 month', $last));
        default:
            return 'Manuell';
    }
}

// Get statistics
$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table"),
    'success' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'completed'"),
    'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'failed'"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'"),
    'running' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'running'")
];

// Build query
$where_parts = [];
if ($filter_status !== 'all') {
    $where_parts[] = $wpdb->prepare("status = %s", $filter_status);
}
if ($filter_todo_id) {
    $where_parts[] = $wpdb->prepare("todo_id = %d", $filter_todo_id);
}
$where = !empty($where_parts) ? " WHERE " . implode(" AND ", $where_parts) : "";

// Get total count
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table" . $where);

// Get reports with enhanced data
$reports = $wpdb->get_results(
    "SELECT r.*, t.title as todo_title, t.recurring_type, t.last_executed,
            t.priority, t.scope, t.bearbeiten
     FROM $reports_table r
     LEFT JOIN $todos_table t ON r.todo_id = t.id" . 
    $where . 
    " ORDER BY r.execution_date DESC" .
    " LIMIT $per_page OFFSET $offset"
);

// Get unique todos for filter dropdown
$todos_with_reports = $wpdb->get_results(
    "SELECT DISTINCT t.id, t.title, t.recurring_type
     FROM {$wpdb->prefix}project_todos t
     WHERE t.is_recurring = 1
     ORDER BY t.title"
);

// Get recurring tasks that haven't run yet today
$pending_cron_tasks = $wpdb->get_results(
    "SELECT t.*, 
            (SELECT MAX(execution_date) FROM $reports_table WHERE todo_id = t.id) as last_run
     FROM $todos_table t
     WHERE t.is_recurring = 1 
     AND t.status = 'offen'
     ORDER BY t.priority DESC"
);
?>

<div class="wrap">
    <style>
        .wsj-dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .wsj-dashboard-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .wsj-stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        
        .wsj-stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .wsj-stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .wsj-stat-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .wsj-filter-bar {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-button {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-button:hover {
            background: #f0f0f0;
            border-color: #999;
        }
        
        .filter-button.active {
            background: #333;
            color: white;
            border-color: #333;
        }
        
        .wsj-next-execution {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        
        .wsj-pending-tasks {
            background: #dbeafe;
            border: 1px solid #60a5fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-running { background: #cfe2ff; color: #084298; }
    </style>
    
    <div class="wsj-dashboard-header">
        <h1 class="wsj-dashboard-title">📊 CRON Dashboard</h1>
        <div>
            <a href="<?php echo admin_url('admin.php?page=wp-project-todos'); ?>" class="button">
                📋 Zum normalen Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new'); ?>" class="button button-primary">
                ➕ Neue CRON-Aufgabe
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="wsj-stats-grid">
        <div class="wsj-stat-card">
            <div class="wsj-stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="wsj-stat-label">Gesamt</div>
        </div>
        <div class="wsj-stat-card" style="border-left: 3px solid #10b981;">
            <div class="wsj-stat-number" style="color: #10b981;"><?php echo number_format($stats['success']); ?></div>
            <div class="wsj-stat-label">Erfolgreich</div>
        </div>
        <div class="wsj-stat-card" style="border-left: 3px solid #ef4444;">
            <div class="wsj-stat-number" style="color: #ef4444;"><?php echo number_format($stats['failed']); ?></div>
            <div class="wsj-stat-label">Fehlgeschlagen</div>
        </div>
        <div class="wsj-stat-card" style="border-left: 3px solid #f59e0b;">
            <div class="wsj-stat-number" style="color: #f59e0b;"><?php echo number_format($stats['pending']); ?></div>
            <div class="wsj-stat-label">Ausstehend</div>
        </div>
        <div class="wsj-stat-card" style="border-left: 3px solid #3b82f6;">
            <div class="wsj-stat-number" style="color: #3b82f6;"><?php echo number_format($stats['running']); ?></div>
            <div class="wsj-stat-label">Läuft</div>
        </div>
    </div>
    
    <!-- Pending CRON Tasks -->
    <?php if (!empty($pending_cron_tasks)): ?>
    <div class="wsj-pending-tasks">
        <h3>🕐 Anstehende CRON-Tasks</h3>
        <table class="wp-list-table widefat" style="background: white;">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Typ</th>
                    <th>Letzte Ausführung</th>
                    <th>Nächste Ausführung</th>
                    <th>Priorität</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_cron_tasks as $task): ?>
                <tr>
                    <td><strong><?php echo esc_html($task->title); ?></strong></td>
                    <td>
                        <span class="status-badge status-pending">
                            <?php echo $task->recurring_type ?: 'Manuell'; ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if ($task->last_run) {
                            echo date('d.m.Y H:i', strtotime($task->last_run));
                        } else {
                            echo '<em>Noch nie</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <strong>
                        <?php 
                        $next = calculate_next_execution($task->recurring_type, $task->last_run);
                        if ($next !== 'Manuell') {
                            $next_time = strtotime($next);
                            $diff = $next_time - time();
                            if ($diff < 3600) {
                                echo '<span style="color: #ef4444;">In ' . round($diff/60) . ' Minuten</span>';
                            } elseif ($diff < 86400) {
                                echo '<span style="color: #f59e0b;">In ' . round($diff/3600) . ' Stunden</span>';
                            } else {
                                echo date('d.m.Y H:i', $next_time);
                            }
                        } else {
                            echo $next;
                        }
                        ?>
                        </strong>
                    </td>
                    <td>
                        <?php 
                        $priority_colors = [
                            'high' => '#ef4444',
                            'medium' => '#f59e0b', 
                            'low' => '#10b981'
                        ];
                        $color = $priority_colors[$task->priority] ?? '#6b7280';
                        ?>
                        <span style="color: <?php echo $color; ?>; font-weight: bold;">
                            <?php echo ucfirst($task->priority ?: 'Normal'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Filter Bar -->
    <div class="wsj-filter-bar">
        <form method="get" action="" style="display: flex; gap: 15px; align-items: center;">
            <input type="hidden" name="page" value="wp-project-todos-cron-reports">
            
            <div>
                <strong>Status-Filter:</strong><br>
                <a href="?page=wp-project-todos-cron-reports&filter_status=all" 
                   class="filter-button <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                    Alle (<?php echo $stats['total']; ?>)
                </a>
                <a href="?page=wp-project-todos-cron-reports&filter_status=completed" 
                   class="filter-button <?php echo $filter_status === 'completed' ? 'active' : ''; ?>">
                    ✅ Erfolgreich (<?php echo $stats['success']; ?>)
                </a>
                <a href="?page=wp-project-todos-cron-reports&filter_status=failed" 
                   class="filter-button <?php echo $filter_status === 'failed' ? 'active' : ''; ?>">
                    ❌ Fehlgeschlagen (<?php echo $stats['failed']; ?>)
                </a>
                <a href="?page=wp-project-todos-cron-reports&filter_status=pending" 
                   class="filter-button <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                    ⏳ Ausstehend (<?php echo $stats['pending']; ?>)
                </a>
                <a href="?page=wp-project-todos-cron-reports&filter_status=running" 
                   class="filter-button <?php echo $filter_status === 'running' ? 'active' : ''; ?>">
                    🔄 Läuft (<?php echo $stats['running']; ?>)
                </a>
            </div>
            
            <div style="margin-left: auto;">
                <label for="todo_id">
                    <strong>Task-Filter:</strong><br>
                    <select name="todo_id" id="todo_id" onchange="this.form.submit()" style="min-width: 200px;">
                        <option value="">Alle CRON-Tasks</option>
                        <?php foreach ($todos_with_reports as $todo): ?>
                            <option value="<?php echo $todo->id; ?>" <?php selected($filter_todo_id, $todo->id); ?>>
                                <?php echo esc_html($todo->title); ?>
                                <?php if ($todo->recurring_type): ?>
                                    (<?php echo $todo->recurring_type; ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                
                <?php if ($filter_todo_id || $filter_status !== 'all'): ?>
                    <a href="?page=wp-project-todos-cron-reports" class="button">Filter zurücksetzen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Reports Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="60">ID</th>
                <th>Task</th>
                <th width="100">Typ</th>
                <th width="180">Ausführung</th>
                <th width="100">Status</th>
                <th width="100">Dauer</th>
                <th>Zusammenfassung</th>
                <th width="150">Nächste Ausführung</th>
                <th width="100">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports): ?>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td>#<?php echo $report->id; ?></td>
                    <td>
                        <strong><?php echo esc_html($report->todo_title ?: $report->title); ?></strong><br>
                        <small>
                            Todo #<?php echo $report->todo_id; ?>
                            <?php if ($report->priority): ?>
                                | Priorität: <?php echo ucfirst($report->priority); ?>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <span class="status-badge status-pending">
                            <?php echo $report->recurring_type ?: 'Manuell'; ?>
                        </span>
                    </td>
                    <td>
                        <?php echo date('d.m.Y H:i:s', strtotime($report->execution_date)); ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $report->status; ?>">
                            <?php 
                            $status_labels = [
                                'completed' => 'Erfolgreich',
                                'failed' => 'Fehlgeschlagen',
                                'pending' => 'Ausstehend',
                                'running' => 'Läuft'
                            ];
                            echo $status_labels[$report->status] ?? $report->status;
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if ($report->execution_time) {
                            echo number_format($report->execution_time, 2) . 's';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($report->summary) {
                            echo esc_html(substr($report->summary, 0, 100));
                            if (strlen($report->summary) > 100) echo '...';
                        } else {
                            echo '<em>Keine Zusammenfassung</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($report->recurring_type) {
                            $next = calculate_next_execution($report->recurring_type, $report->execution_date);
                            if ($next !== 'Manuell') {
                                echo '<small>' . date('d.m.Y H:i', strtotime($next)) . '</small>';
                            } else {
                                echo '<small>Manuell</small>';
                            }
                        } else {
                            echo '<small>-</small>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($report->output_path): ?>
                            <a href="<?php echo admin_url('admin.php?page=wp-project-todos-html-output&report_id=' . $report->id); ?>" 
                               class="button button-small">
                                📄 Details
                            </a>
                        <?php endif; ?>
                        <button class="button button-small" onclick="runCronTask(<?php echo $report->todo_id; ?>)">
                            ▶️ Ausführen
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <em>Keine CRON-Reports gefunden.</em>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_items > $per_page): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $total_pages = ceil($total_items / $per_page);
            $base_url = admin_url('admin.php?page=wp-project-todos-cron-reports');
            if ($filter_status !== 'all') $base_url .= '&filter_status=' . $filter_status;
            if ($filter_todo_id) $base_url .= '&todo_id=' . $filter_todo_id;
            
            echo paginate_links(array(
                'base' => $base_url . '%_%',
                'format' => '&paged=%#%',
                'current' => $page,
                'total' => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;'
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function runCronTask(todoId) {
    if (confirm('Diese CRON-Task jetzt manuell ausführen?')) {
        jQuery.post(ajaxurl, {
            action: 'run_cron_task',
            todo_id: todoId,
            _wpnonce: '<?php echo wp_create_nonce('run_cron_task'); ?>'
        }, function(response) {
            if (response.success) {
                alert('CRON-Task wurde gestartet!');
                location.reload();
            } else {
                alert('Fehler: ' + response.data);
            }
        });
    }
}
</script>