<?php
/**
 * Auto-continue monitoring script
 * Runs in background to automatically load next todo when current is completed
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

class AutoContinueMonitor {
    private $manager;
    private $config;
    private $logger;
    private $running = true;
    
    public function __construct() {
        $this->manager = new Todo_Manager();
        $this->config = $this->load_config();
        $this->logger = new Logger('AutoContinue');
        
        // Set up signal handlers for graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
    }
    
    public function run() {
        $this->logger->info("Auto-continue monitor started");
        
        $check_interval = 5; // Check every 5 seconds
        $last_check = 0;
        
        while ($this->running) {
            pcntl_signal_dispatch();
            
            $current_time = time();
            if ($current_time - $last_check >= $check_interval) {
                $this->check_for_completion();
                $this->check_for_triggers();
                $last_check = $current_time;
            }
            
            sleep(1);
        }
        
        $this->logger->info("Auto-continue monitor stopped");
    }
    
    private function check_for_completion() {
        $task_completed_file = dirname(__DIR__) . '/temp/TASK_COMPLETED';
        
        if (file_exists($task_completed_file)) {
            $this->logger->info("Task completion detected");
            
            // Read completion data
            $completion_data = json_decode(file_get_contents($task_completed_file), true);
            
            // Remove the file to prevent re-processing
            unlink($task_completed_file);
            
            // Check if there's a next todo
            if (isset($completion_data['next_todo_id']) && $completion_data['next_todo_id']) {
                $this->logger->info("Next todo available: " . $completion_data['next_todo_title']);
                
                // Optionally send notification or trigger action
                $this->send_continuation_notification($completion_data);
            } else {
                $this->logger->info("No more todos available after completion");
                
                // Send completion notification
                $this->send_completion_notification($completion_data);
            }
        }
    }
    
    private function check_for_triggers() {
        try {
            $trigger_result = $this->manager->check_remote_triggers();
            
            if ($trigger_result) {
                $this->logger->info("Remote trigger processed successfully");
                
                // Log trigger details
                if (is_array($trigger_result)) {
                    $this->logger->info("Trigger result: " . json_encode($trigger_result));
                } else {
                    $this->logger->info("Trigger result: " . $trigger_result);
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Error checking triggers: " . $e->getMessage());
        }
    }
    
    private function send_continuation_notification($completion_data) {
        // Create notification file for external monitoring
        $notification_data = [
            'type' => 'continuation',
            'timestamp' => time(),
            'completed_todo' => [
                'id' => $completion_data['completed_todo_id'],
                'title' => $completion_data['completed_todo_title']
            ],
            'next_todo' => [
                'id' => $completion_data['next_todo_id'],
                'title' => $completion_data['next_todo_title']
            ],
            'message' => 'Task completed, next task loaded automatically'
        ];
        
        $notification_file = dirname(__DIR__) . '/temp/auto_continue_notification.json';
        file_put_contents($notification_file, json_encode($notification_data, JSON_PRETTY_PRINT));
        
        $this->logger->info("Continuation notification sent");
    }
    
    private function send_completion_notification($completion_data) {
        // Create completion notification
        $notification_data = [
            'type' => 'all_completed',
            'timestamp' => time(),
            'last_completed_todo' => [
                'id' => $completion_data['completed_todo_id'],
                'title' => $completion_data['completed_todo_title']
            ],
            'message' => 'All todos completed! Great work!'
        ];
        
        $notification_file = dirname(__DIR__) . '/temp/all_completed_notification.json';
        file_put_contents($notification_file, json_encode($notification_data, JSON_PRETTY_PRINT));
        
        $this->logger->info("All completed notification sent");
        
        // Consider stopping the monitor since there's nothing left to do
        // $this->running = false;
    }
    
    public function shutdown($signal = null) {
        $this->logger->info("Shutdown signal received" . ($signal ? " (signal: $signal)" : ""));
        $this->running = false;
        
        // Clean up PID file
        $pid_file = dirname(__DIR__) . '/temp/auto-continue.pid';
        if (file_exists($pid_file)) {
            unlink($pid_file);
        }
    }
    
    private function load_config() {
        $config_file = dirname(__DIR__) . '/config/app.json';
        return json_decode(file_get_contents($config_file), true);
    }
}

// Ensure we can use process control functions
if (!function_exists('pcntl_signal')) {
    echo "PCNTL extension not available, using basic monitoring mode\n";
    
    // Fallback to simple loop without signal handling
    $manager = new Todo_Manager();
    $logger = new Logger('AutoContinue');
    
    while (true) {
        $task_completed_file = dirname(__DIR__) . '/temp/TASK_COMPLETED';
        
        if (file_exists($task_completed_file)) {
            $logger->info("Task completion detected (simple mode)");
            unlink($task_completed_file);
        }
        
        // Check for triggers
        try {
            $manager->check_remote_triggers();
        } catch (Exception $e) {
            $logger->error("Error in simple monitoring mode: " . $e->getMessage());
        }
        
        sleep(5);
    }
} else {
    // Use full-featured monitoring with signal handling
    $monitor = new AutoContinueMonitor();
    $monitor->run();
}