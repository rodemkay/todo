<?php
/**
 * Sync plugin to remote server script
 * Used by ./todo sync command
 */

require_once dirname(__DIR__) . '/core/api/todo_manager.php';

try {
    $manager = new Todo_Manager();
    
    echo "🔄 SYNCING PLUGIN TO REMOTE SERVER\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
    // Start sync
    $result = $manager->sync_plugin();
    
    echo "📡 SYNC DETAILS:\n";
    
    if (strpos($result, 'rsync') !== false) {
        // Parse rsync output
        $lines = explode("\n", $result);
        $files_synced = 0;
        $bytes_transferred = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Count files
            if (preg_match('/^[<>ch\.]/', $line)) {
                $files_synced++;
                echo "   📄 " . basename($line) . "\n";
            }
            
            // Extract bytes transferred
            if (preg_match('/sent (\d+) bytes.*received (\d+) bytes/', $line, $matches)) {
                $bytes_transferred = intval($matches[1]);
            }
        }
        
        echo "\n📊 SYNC SUMMARY:\n";
        echo sprintf("   📁 Files synced: %d\n", $files_synced);
        
        if ($bytes_transferred > 0) {
            if ($bytes_transferred > 1024 * 1024) {
                echo sprintf("   💾 Data transferred: %.2f MB\n", $bytes_transferred / (1024 * 1024));
            } elseif ($bytes_transferred > 1024) {
                echo sprintf("   💾 Data transferred: %.2f KB\n", $bytes_transferred / 1024);
            } else {
                echo sprintf("   💾 Data transferred: %d bytes\n", $bytes_transferred);
            }
        }
        
        if ($files_synced > 0) {
            echo "   ✅ Sync completed successfully\n";
        } else {
            echo "   ℹ️  No changes to sync (already up to date)\n";
        }
        
    } else {
        echo "   📝 " . trim($result) . "\n";
        echo "   ✅ Sync completed\n";
    }
    
    echo "\n💡 Plugin files are now synchronized with the remote server\n";
    echo "💡 Remote control functionality should be fully operational\n";
    
    echo "═══════════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    error_log("Sync error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
    echo "\n";
    echo "🔧 TROUBLESHOOTING:\n";
    echo "   • Check SSH connection to 159.69.157.54\n";
    echo "   • Verify rsync is installed\n";
    echo "   • Ensure remote directory permissions are correct\n";
    echo "   • Check .env file for correct SSH credentials\n";
    exit(1);
}