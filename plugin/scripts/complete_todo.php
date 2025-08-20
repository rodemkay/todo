<?php
/**
 * Complete todo script
 * Used by ./todo complete command
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

try {
    $manager = new Todo_Manager();
    
    // Get current todo first
    $current_todo = $manager->get_current_todo();
    
    if (!$current_todo) {
        echo "❌ NO_CURRENT_TODO";
        exit(1);
    }
    
    $notes = $argv[1] ?? '';
    
    // Complete the todo
    $next_todo = $manager->complete_todo($current_todo['id'], $notes);
    
    // Output completion message
    echo "✅ COMPLETED: " . $current_todo['title'] . "\n";
    
    if ($notes) {
        echo "📝 NOTES: " . $notes . "\n";
    }
    
    // Check if there's a next todo
    if ($next_todo) {
        echo "\n🔄 NEXT TODO LOADED:\n";
        echo "📋 ID: " . $next_todo['id'] . "\n";
        echo "📝 TITLE: " . $next_todo['title'] . "\n";
        echo "📊 STATUS: " . strtoupper($next_todo['status']) . "\n";
        
        if (!empty($next_todo['description'])) {
            $short_desc = substr($next_todo['description'], 0, 100);
            if (strlen($next_todo['description']) > 100) {
                $short_desc .= '...';
            }
            echo "📄 DESCRIPTION: " . $short_desc . "\n";
        }
        
        echo "\n💡 Continue working or use ./todo for full details\n";
    } else {
        echo "\n🎉 ALL TODOS COMPLETED! Great work!\n";
        echo "💡 Use ./todo to check for new tasks\n";
    }
    
    // Create TASK_COMPLETED file for external monitoring
    $task_completed_file = dirname(__DIR__) . '/temp/TASK_COMPLETED';
    $completion_data = [
        'timestamp' => time(),
        'completed_todo_id' => $current_todo['id'],
        'completed_todo_title' => $current_todo['title'],
        'notes' => $notes,
        'next_todo_id' => $next_todo['id'] ?? null,
        'next_todo_title' => $next_todo['title'] ?? null
    ];
    
    file_put_contents($task_completed_file, json_encode($completion_data, JSON_PRETTY_PRINT));
    
} catch (Exception $e) {
    error_log("Todo completion error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
    exit(1);
}