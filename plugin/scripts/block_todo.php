<?php
/**
 * Block todo script
 * Used by ./todo block command
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
    
    $reason = $argv[1] ?? 'No reason provided';
    
    // Block the todo
    $next_todo = $manager->block_todo($current_todo['id'], $reason);
    
    // Output block message
    echo "🚫 BLOCKED: " . $current_todo['title'] . "\n";
    echo "📝 REASON: " . $reason . "\n";
    
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
        echo "\n⏸️  NO MORE TODOS AVAILABLE\n";
        echo "💡 All remaining todos may be blocked or completed\n";
        echo "💡 Use ./todo stats to see status breakdown\n";
    }
    
} catch (Exception $e) {
    error_log("Todo block error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
    exit(1);
}