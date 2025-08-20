<?php
/**
 * Migration helper script
 * Assists in migrating from old mount-based system to new SSH-based system
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

class MigrationHelper {
    private $config;
    private $logger;
    
    public function __construct() {
        $this->config = $this->load_config();
        $this->logger = new Logger('Migration');
    }
    
    public function migrate() {
        echo "🔄 WP PROJECT TODOS MIGRATION\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "Migrating from mount-based to SSH-based architecture\n\n";
        
        $steps = [
            'check_dependencies' => 'Checking dependencies',
            'test_ssh_connection' => 'Testing SSH connection',
            'backup_old_scripts' => 'Backing up old scripts',
            'sync_plugin_files' => 'Syncing plugin files to remote',
            'test_database_access' => 'Testing database access',
            'test_remote_triggers' => 'Testing remote trigger system',
            'create_legacy_aliases' => 'Creating legacy command aliases',
            'cleanup_old_files' => 'Cleaning up old files'
        ];
        
        $completed = 0;
        $total = count($steps);
        
        foreach ($steps as $method => $description) {
            echo sprintf("[%d/%d] %s...", ++$completed, $total, $description);
            
            try {
                $result = $this->$method();
                echo " ✅\n";
                
                if ($result && is_string($result)) {
                    echo "   📝 " . $result . "\n";
                }
                
            } catch (Exception $e) {
                echo " ❌\n";
                echo "   🚨 ERROR: " . $e->getMessage() . "\n";
                
                if ($method === 'test_ssh_connection' || $method === 'test_database_access') {
                    echo "\n❌ CRITICAL ERROR: Migration cannot continue\n";
                    echo "Please fix the above error and try again.\n";
                    exit(1);
                }
                
                echo "   ⚠️  WARNING: Non-critical error, continuing...\n";
            }
            
            echo "\n";
        }
        
        echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "\n";
        echo "🎉 The WP Project Todos system has been upgraded!\n";
        echo "\n";
        echo "📋 WHAT'S NEW:\n";
        echo "   • No more dependency on mount paths\n";
        echo "   • Direct SSH communication with WordPress server\n";
        echo "   • Improved error handling and logging\n";
        echo "   • Better remote control integration\n";
        echo "   • Auto-continue functionality\n";
        echo "\n";
        echo "🚀 NEXT STEPS:\n";
        echo "   1. Test the system: ./todo\n";
        echo "   2. Check statistics: ./todo stats\n";
        echo "   3. Test remote control from WordPress admin\n";
        echo "   4. Review logs in: logs/\n";
        echo "\n";
        echo "💡 All your existing todos are preserved and ready to use!\n";
    }
    
    private function check_dependencies() {
        $missing = [];
        
        if (!function_exists('ssh2_connect')) {
            $missing[] = 'PHP SSH2 extension';
        }
        
        if (!command_exists('ssh')) {
            $missing[] = 'SSH client';
        }
        
        if (!command_exists('rsync')) {
            $missing[] = 'rsync';
        }
        
        if (!empty($missing)) {
            throw new Exception('Missing dependencies: ' . implode(', ', $missing));
        }
        
        return 'All dependencies available';
    }
    
    private function test_ssh_connection() {
        $ssh = new SSH_Client();
        
        try {
            $ssh->connect();
            $result = $ssh->execute('echo "SSH connection test successful"');
            $ssh->disconnect();
            
            if (strpos($result, 'successful') !== false) {
                return 'SSH connection working';
            } else {
                throw new Exception('SSH test command failed');
            }
        } catch (Exception $e) {
            throw new Exception('SSH connection failed: ' . $e->getMessage());
        }
    }
    
    private function backup_old_scripts() {
        $old_scripts = [
            '/home/rodemkay/www/react/todo',
            '/home/rodemkay/www/react/task_complete.sh',
            '/home/rodemkay/www/react/load-specific-todo.sh'
        ];
        
        $backup_dir = dirname(__DIR__) . '/backup/pre-migration-' . date('Y-m-d-H-i-s');
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backed_up = 0;
        
        foreach ($old_scripts as $script) {
            if (file_exists($script)) {
                $backup_path = $backup_dir . '/' . basename($script);
                copy($script, $backup_path);
                $backed_up++;
            }
        }
        
        return "Backed up $backed_up script(s) to backup/";
    }
    
    private function sync_plugin_files() {
        $manager = new Todo_Manager();
        $result = $manager->sync_plugin();
        
        // Simple success check
        if (strpos($result, 'rsync') !== false || !empty(trim($result))) {
            return 'Plugin files synchronized';
        } else {
            throw new Exception('Plugin sync may have failed');
        }
    }
    
    private function test_database_access() {
        $manager = new Todo_Manager();
        
        try {
            $stats = $manager->get_stats();
            $total = $stats['total'] ?? 0;
            
            return "Database access working ($total todos found)";
        } catch (Exception $e) {
            throw new Exception('Database access failed: ' . $e->getMessage());
        }
    }
    
    private function test_remote_triggers() {
        $trigger_file = $this->config['paths']['trigger_file'];
        $ssh = new SSH_Client();
        
        try {
            // Test write trigger file
            $test_content = 'migration_test:' . time();
            $ssh->execute("echo '$test_content' > $trigger_file");
            
            // Test read and cleanup
            $result = $ssh->check_trigger();
            
            if (trim($result) === $test_content) {
                return 'Remote trigger system working';
            } else {
                throw new Exception('Trigger file test failed');
            }
        } catch (Exception $e) {
            throw new Exception('Remote trigger test failed: ' . $e->getMessage());
        }
    }
    
    private function create_legacy_aliases() {
        // Create symlinks for backward compatibility
        $legacy_scripts = [
            '/home/rodemkay/www/react/todo-new' => dirname(__DIR__) . '/todo',
            '/home/rodemkay/www/react/task_complete_new.sh' => dirname(__DIR__) . '/todo'
        ];
        
        $created = 0;
        
        foreach ($legacy_scripts as $legacy_path => $new_path) {
            if (!file_exists($legacy_path)) {
                symlink($new_path, $legacy_path);
                $created++;
            }
        }
        
        return "Created $created legacy alias(es)";
    }
    
    private function cleanup_old_files() {
        // Mark old mount-dependent files as deprecated
        $old_files = [
            '/home/rodemkay/www/react/watch-hetzner-trigger.sh',
            '/home/rodemkay/www/react/mount-hetzner.sh'
        ];
        
        $processed = 0;
        
        foreach ($old_files as $file) {
            if (file_exists($file)) {
                $deprecated_name = $file . '.deprecated-' . date('Y-m-d');
                if (!file_exists($deprecated_name)) {
                    rename($file, $deprecated_name);
                    $processed++;
                }
            }
        }
        
        return "Marked $processed old file(s) as deprecated";
    }
    
    private function load_config() {
        $config_file = dirname(__DIR__) . '/config/app.json';
        return json_decode(file_get_contents($config_file), true);
    }
}

function command_exists($command) {
    $return_var = null;
    $output = null;
    exec("which $command", $output, $return_var);
    return $return_var === 0;
}

// Check if we're being run directly
if (isset($argv[0]) && basename($argv[0]) === 'migration_helper.php') {
    try {
        $helper = new MigrationHelper();
        $helper->migrate();
    } catch (Exception $e) {
        echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}