<?php
/**
 * Check remote triggers script
 * Monitors WordPress trigger file for remote control commands
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

try {
    $manager = new Todo_Manager();
    
    // Check for remote triggers
    $trigger_result = $manager->check_remote_triggers();
    
    if (!$trigger_result) {
        echo "NO_TRIGGERS";
        exit(0);
    }
    
    // Process and display trigger result
    if (is_array($trigger_result)) {
        echo "🔔 REMOTE TRIGGER EXECUTED\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        if (isset($trigger_result['title'])) {
            // New todo created
            echo "📋 NEW TODO CREATED\n";
            echo "📝 TITLE: " . $trigger_result['title'] . "\n";
            echo "📊 STATUS: IN_PROGRESS\n";
            
            if (!empty($trigger_result['description'])) {
                echo "📄 DESCRIPTION:\n";
                echo "   " . str_replace("\n", "\n   ", $trigger_result['description']) . "\n";
            }
            
        } else if (isset($trigger_result['id'])) {
            // Existing todo loaded
            echo "📋 TODO LOADED\n";
            echo "📝 TITLE: " . $trigger_result['title'] . "\n";
            echo "📊 STATUS: " . strtoupper($trigger_result['status']) . "\n";
            echo "🔥 PRIORITY: " . ($trigger_result['priority'] ?? 5) . "/10\n";
            
            if (!empty($trigger_result['description'])) {
                echo "📄 DESCRIPTION:\n";
                echo "   " . str_replace("\n", "\n   ", $trigger_result['description']) . "\n";
            }
        }
        
        echo "═══════════════════════════════════════════════════════════\n";
        echo "💡 Todo is now active in Claude session\n";
        
        // Send response back to WordPress
        $response_data = [
            'status' => 'success',
            'message' => 'Todo loaded in Claude session',
            'todo_id' => $trigger_result['id'] ?? null,
            'todo_title' => $trigger_result['title'] ?? null
        ];
        
        $manager->send_response($response_data);
        
    } else {
        echo "🔔 REMOTE TRIGGER: " . $trigger_result . "\n";
    }
    
} catch (Exception $e) {
    error_log("Trigger check error: " . $e->getMessage());
    
    // Try to send error response back to WordPress
    try {
        $manager = new Todo_Manager();
        $error_response = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => time()
        ];
        $manager->send_response($error_response);
    } catch (Exception $inner_e) {
        // Ignore inner exception
    }
    
    echo "ERROR: " . $e->getMessage();
    exit(1);
}