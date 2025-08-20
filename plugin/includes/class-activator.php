<?php
/**
 * Fired during plugin activation
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::create_default_directories();
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main todos table
        $table_todos = $wpdb->prefix . 'project_todos';
        $sql_todos = "CREATE TABLE IF NOT EXISTS $table_todos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            scope ENUM('frontend', 'backend', 'database', 'n8n', 'mt5', 'server', 'content', 'seo', 'analytics', 'other') NOT NULL DEFAULT 'other',
            status ENUM('pending', 'in_progress', 'completed', 'blocked', 'cancelled') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            working_directory VARCHAR(500) DEFAULT NULL,
            assigned_to VARCHAR(100) DEFAULT 'claude',
            due_date DATE DEFAULT NULL,
            completed_date DATETIME DEFAULT NULL,
            claude_notes TEXT,
            claude_output LONGTEXT,
            related_files TEXT,
            dependencies TEXT,
            estimated_hours DECIMAL(5,2) DEFAULT NULL,
            actual_hours DECIMAL(5,2) DEFAULT NULL,
            tags VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            INDEX idx_status (status),
            INDEX idx_scope (scope),
            INDEX idx_priority (priority),
            INDEX idx_due_date (due_date),
            INDEX idx_working_directory (working_directory)
        ) $charset_collate;";
        
        // History table
        $table_history = $wpdb->prefix . 'project_todo_history';
        $sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            todo_id INT NOT NULL,
            field_name VARCHAR(50),
            old_value TEXT,
            new_value TEXT,
            changed_by INT DEFAULT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_todo_id (todo_id)
        ) $charset_collate;";
        
        // Comments table
        $table_comments = $wpdb->prefix . 'project_todo_comments';
        $sql_comments = "CREATE TABLE IF NOT EXISTS $table_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            todo_id INT NOT NULL,
            comment TEXT NOT NULL,
            comment_by INT DEFAULT NULL,
            comment_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_claude_note BOOLEAN DEFAULT FALSE,
            INDEX idx_todo_id (todo_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_todos);
        dbDelta($sql_history);
        dbDelta($sql_comments);
        
        // Store version for future updates
        update_option('wp_project_todos_db_version', '1.0.0');
        
        // Insert sample todos for testing
        self::insert_sample_todos();
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'default_working_directory' => '/home/rodemkay/www/react/',
            'claude_enabled' => true,
            'auto_reload_configs' => true,
            'compacting_check_interval' => 300, // 5 minutes
            'output_capture_enabled' => true,
            'max_output_size' => 1048576, // 1MB
            'notification_email' => get_option('admin_email'),
            'slack_webhook_url' => '',
            'directory_presets' => [
                'React Project' => '/home/rodemkay/www/react/',
                'n8n Workflows' => '/home/rodemkay/n8n-workflows/',
                'MT5 Development' => '/home/rodemkay/mt5/',
                'Staging Server' => '/var/www/forexsignale/staging/',
                'Kommandozentrale' => '/home/rodemkay/kommandozentrale/',
            ],
            'scope_colors' => [
                'frontend' => '#3498db',
                'backend' => '#2ecc71',
                'database' => '#e74c3c',
                'n8n' => '#f39c12',
                'mt5' => '#9b59b6',
                'server' => '#34495e',
                'content' => '#16a085',
                'seo' => '#e67e22',
                'analytics' => '#95a5a6',
                'other' => '#7f8c8d',
            ],
            'api_settings' => [
                'rate_limit' => 100, // requests per minute
                'require_auth' => true,
                'allowed_ips' => [],
            ],
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option('wp_project_todos_' . $key) === false) {
                update_option('wp_project_todos_' . $key, $value);
            }
        }
        
        // Set plugin version
        update_option('wp_project_todos_version', WP_PROJECT_TODOS_VERSION);
    }
    
    /**
     * Create default directory structure
     */
    private static function create_default_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/wp-project-todos';
        
        $directories = [
            $plugin_dir,
            $plugin_dir . '/exports',
            $plugin_dir . '/imports',
            $plugin_dir . '/claude-outputs',
            $plugin_dir . '/attachments',
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add .htaccess for security
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "Options -Indexes\nDeny from all");
                }
            }
        }
    }
    
    /**
     * Insert sample todos for testing
     */
    private static function insert_sample_todos() {
        global $wpdb;
        $table = $wpdb->prefix . 'project_todos';
        
        // Check if table is empty
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return; // Don't insert if data exists
        }
        
        $sample_todos = [
            [
                'title' => 'Homepage Kategorien-Filter implementieren',
                'description' => 'Dropdown-Filter für Kategorienauswahl in der linken Spalte der Homepage hinzufügen. Sollte alle verfügbaren Kategorien anzeigen und bei Auswahl die Artikel filtern.',
                'scope' => 'frontend',
                'status' => 'pending',
                'priority' => 'high',
                'working_directory' => '/home/rodemkay/www/react/',
                'assigned_to' => 'claude',
                'estimated_hours' => 3.00,
                'related_files' => json_encode(['front-page.php', 'functions.php', 'style-wsj.css']),
                'tags' => 'homepage,filter,kategorien,ui',
                'created_by' => 1,
            ],
            [
                'title' => 'RSS Feed Automation Workflow erstellen',
                'description' => 'n8n Workflow für automatischen Import von Forex News via RSS Feeds. Sollte stündlich laufen und Duplikate vermeiden.',
                'scope' => 'n8n',
                'status' => 'pending',
                'priority' => 'medium',
                'working_directory' => '/home/rodemkay/n8n-workflows/',
                'assigned_to' => 'rodemkay',
                'estimated_hours' => 5.00,
                'tags' => 'automation,rss,news,import',
                'created_by' => 1,
            ],
            [
                'title' => 'Breakout Brain EA Backtest durchführen',
                'description' => 'Backtest des Breakout Brain Expert Advisors auf EURUSD mit 2024 Daten. Optimierung der Parameter und Performance-Report erstellen.',
                'scope' => 'mt5',
                'status' => 'pending',
                'priority' => 'low',
                'working_directory' => '/home/rodemkay/mt5/',
                'assigned_to' => 'trading-team',
                'estimated_hours' => 2.00,
                'tags' => 'trading,backtest,ea,eurusd',
                'created_by' => 1,
            ],
            [
                'title' => 'Nginx Cache Configuration optimieren',
                'description' => 'FastCGI Cache für WordPress optimieren. Cache-Zeiten anpassen, Ausnahmen definieren und Purge-Mechanismus implementieren.',
                'scope' => 'server',
                'status' => 'pending',
                'priority' => 'medium',
                'working_directory' => '/var/www/forexsignale/',
                'assigned_to' => 'claude',
                'estimated_hours' => 4.00,
                'related_files' => json_encode(['nginx.conf', 'sites-available/forexsignale.trade']),
                'tags' => 'server,nginx,cache,performance',
                'created_by' => 1,
            ],
            [
                'title' => 'Dashboard Widget für Trading Performance',
                'description' => 'Echtzeit-Performance-Anzeige für Trading-Ergebnisse im WordPress Dashboard implementieren. Sollte Gewinn/Verlust, offene Positionen und Statistiken zeigen.',
                'scope' => 'backend',
                'status' => 'pending',
                'priority' => 'high',
                'working_directory' => '/home/rodemkay/kommandozentrale/',
                'assigned_to' => 'claude',
                'estimated_hours' => 6.00,
                'tags' => 'dashboard,widget,trading,performance',
                'created_by' => 1,
            ],
        ];
        
        foreach ($sample_todos as $todo) {
            $wpdb->insert($table, $todo);
            
            // Add initial comment
            $comment_table = $wpdb->prefix . 'project_todo_comments';
            $wpdb->insert($comment_table, [
                'todo_id' => $wpdb->insert_id,
                'comment' => 'Aufgabe erstellt und wartet auf Bearbeitung.',
                'comment_by' => 1,
                'is_claude_note' => false,
            ]);
        }
    }
}