<?php
/**
 * Load current todo script
 * Used by ./todo command to fetch active todos
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

try {
    $manager = new Todo_Manager();
    
    // Get current todo
    $todo = $manager->get_current_todo();
    
    if (!$todo) {
        echo "NO_TODO";
        exit(0);
    }
    
    // Format todo output
    echo "🎯 CURRENT TODO\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "📋 ID: " . $todo['id'] . "\n";
    echo "📝 TITLE: " . $todo['title'] . "\n";
    echo "📊 STATUS: " . strtoupper($todo['status']) . "\n";
    echo "🔥 PRIORITY: " . ($todo['priority'] ?? 5) . "/10\n";
    
    if (!empty($todo['description'])) {
        echo "📄 DESCRIPTION:\n";
        echo "   " . str_replace("\n", "\n   ", $todo['description']) . "\n";
    }
    
    if (!empty($todo['claude_notes'])) {
        echo "🤖 CLAUDE NOTES:\n";
        echo "   " . str_replace("\n", "\n   ", $todo['claude_notes']) . "\n";
    }
    
    if (!empty($todo['created_at'])) {
        echo "📅 CREATED: " . $todo['created_at'] . "\n";
    }
    
    if (!empty($todo['started_at'])) {
        echo "▶️  STARTED: " . $todo['started_at'] . "\n";
    }
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "💡 Use: ./todo complete [notes] | ./todo block [reason]\n";
    
} catch (Exception $e) {
    error_log("Todo load error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
    exit(1);
}