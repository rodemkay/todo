<?php
/**
 * Get todo statistics script
 * Used by ./todo stats command
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

try {
    $manager = new Todo_Manager();
    
    // Get statistics
    $stats = $manager->get_stats();
    
    // Display statistics
    echo "📊 TODO STATISTICS\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    // Total overview
    echo sprintf("📈 TOTAL TODOS: %d\n", $stats['total']);
    echo "\n";
    
    // Status breakdown
    echo "📋 STATUS BREAKDOWN:\n";
    echo sprintf("   ⏳ Pending:     %3d (%2.0f%%)\n", 
        $stats['pending'], 
        $stats['total'] > 0 ? ($stats['pending'] / $stats['total']) * 100 : 0
    );
    
    echo sprintf("   🔄 In Progress: %3d (%2.0f%%)\n", 
        $stats['in_progress'], 
        $stats['total'] > 0 ? ($stats['in_progress'] / $stats['total']) * 100 : 0
    );
    
    echo sprintf("   ✅ Completed:   %3d (%2.0f%%)\n", 
        $stats['completed'], 
        $stats['total'] > 0 ? ($stats['completed'] / $stats['total']) * 100 : 0
    );
    
    echo sprintf("   🚫 Blocked:     %3d (%2.0f%%)\n", 
        $stats['blocked'], 
        $stats['total'] > 0 ? ($stats['blocked'] / $stats['total']) * 100 : 0
    );
    
    echo "\n";
    
    // Progress indicator
    $completion_rate = $stats['total'] > 0 ? ($stats['completed'] / $stats['total']) * 100 : 0;
    $progress_bar = str_repeat('█', (int)($completion_rate / 5));
    $remaining_bar = str_repeat('░', 20 - (int)($completion_rate / 5));
    
    echo "🎯 COMPLETION PROGRESS:\n";
    echo sprintf("   [%s%s] %.1f%%\n", $progress_bar, $remaining_bar, $completion_rate);
    
    echo "\n";
    
    // Actionable insights
    if ($stats['in_progress'] > 0) {
        echo "💡 You have " . $stats['in_progress'] . " task(s) in progress\n";
        echo "   Use: ./todo to continue working\n";
    } elseif ($stats['pending'] > 0) {
        echo "💡 You have " . $stats['pending'] . " task(s) waiting to start\n";
        echo "   Use: ./todo to start the next task\n";
    } elseif ($stats['blocked'] > 0 && $stats['pending'] == 0 && $stats['in_progress'] == 0) {
        echo "⚠️  All remaining tasks are blocked\n";
        echo "   Review blocked tasks and unblock them to continue\n";
    } elseif ($stats['total'] == $stats['completed']) {
        echo "🎉 Congratulations! All tasks completed!\n";
        echo "   Great work on finishing everything!\n";
    } else {
        echo "🤔 No active tasks found\n";
        echo "   Consider adding new tasks to your list\n";
    }
    
    if ($stats['blocked'] > 0) {
        echo "\n";
        echo "⚠️  ATTENTION: " . $stats['blocked'] . " task(s) are blocked\n";
        echo "   Review and unblock them when possible\n";
    }
    
    echo "═══════════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
    exit(1);
}