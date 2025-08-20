<?php
/**
 * Cleanup Handler for Todos and related files
 */

namespace WP_Project_Todos;

class Cleanup {
    
    /**
     * Delete todo with all related data and files
     */
    public static function delete_todo_with_cleanup($todo_id) {
        global $wpdb;
        
        // 1. Get all versions for this todo
        $versions_table = $wpdb->prefix . 'project_todo_versions';
        $versions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $versions_table WHERE todo_id = %d",
            $todo_id
        ));
        
        // 2. Delete files from versions
        foreach ($versions as $version) {
            if (!empty($version->attachments)) {
                $files = json_decode($version->attachments, true);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        self::delete_file_safe($file);
                    }
                }
            }
        }
        
        // 3. Get attachments from attachment table
        $attachments_table = $wpdb->prefix . 'project_todo_attachments';
        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $attachments_table WHERE todo_id = %d",
            $todo_id
        ));
        
        // 4. Delete attachment files
        foreach ($attachments as $attachment) {
            if (!empty($attachment->file_path)) {
                self::delete_file_safe($attachment->file_path);
            }
        }
        
        // 5. Delete temporary JSON files
        $temp_files = glob("/tmp/todo_{$todo_id}_*.json");
        if ($temp_files) {
            foreach ($temp_files as $file) {
                @unlink($file);
            }
        }
        
        // 6. Delete session files
        $session_files = glob("/tmp/session_todo_{$todo_id}_*");
        if ($session_files) {
            foreach ($session_files as $file) {
                @unlink($file);
            }
        }
        
        // 7. Delete from database (cascades will handle versions and attachments)
        $todos_table = $wpdb->prefix . 'project_todos';
        $result = $wpdb->delete($todos_table, ['id' => $todo_id]);
        
        // Log cleanup
        error_log("Todo #$todo_id deleted with " . count($versions) . " versions and " . count($attachments) . " attachments");
        
        return $result !== false;
    }
    
    /**
     * Delete version with cleanup
     */
    public static function delete_version_with_cleanup($version_id) {
        global $wpdb;
        
        $versions_table = $wpdb->prefix . 'project_todo_versions';
        
        // Get version data
        $version = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $versions_table WHERE id = %d",
            $version_id
        ));
        
        if (!$version) {
            return false;
        }
        
        // Delete associated files
        if (!empty($version->attachments)) {
            $files = json_decode($version->attachments, true);
            if (is_array($files)) {
                foreach ($files as $file) {
                    self::delete_file_safe($file);
                }
            }
        }
        
        // Delete version from database
        $result = $wpdb->delete($versions_table, ['id' => $version_id]);
        
        error_log("Version #$version_id deleted from Todo #$version->todo_id");
        
        return $result !== false;
    }
    
    /**
     * Safely delete a file
     */
    private static function delete_file_safe($file_path) {
        // Security check - only delete files in allowed directories
        $allowed_dirs = [
            '/var/www/forexsignale/staging/wp-content/uploads/',
            '/tmp/',
            '/home/rodemkay/www/react/wp-project-todos/temp/'
        ];
        
        $is_allowed = false;
        foreach ($allowed_dirs as $dir) {
            if (strpos($file_path, $dir) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            error_log("Attempted to delete file outside allowed directories: $file_path");
            return false;
        }
        
        if (file_exists($file_path)) {
            return @unlink($file_path);
        }
        
        return false;
    }
    
    /**
     * Cleanup orphaned files (cron job)
     */
    public static function cleanup_orphaned_files() {
        $count = 0;
        
        // Delete old temp files (older than 7 days)
        $old_files = glob("/tmp/todo_*");
        if ($old_files) {
            foreach ($old_files as $file) {
                if (filemtime($file) < strtotime('-7 days')) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        // Delete old session files
        $session_files = glob("/tmp/session_todo_*");
        if ($session_files) {
            foreach ($session_files as $file) {
                if (filemtime($file) < strtotime('-2 days')) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        // Delete orphaned upload files
        global $wpdb;
        $attachments_table = $wpdb->prefix . 'project_todo_attachments';
        $upload_dir = '/var/www/forexsignale/staging/wp-content/uploads/todos/';
        
        if (is_dir($upload_dir)) {
            $files = glob($upload_dir . '*');
            foreach ($files as $file) {
                $filename = basename($file);
                
                // Check if file is still referenced
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $attachments_table WHERE file_path LIKE %s",
                    '%' . $filename . '%'
                ));
                
                if (!$exists && filemtime($file) < strtotime('-30 days')) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        
        error_log("Cleanup: Deleted $count orphaned files");
        
        return $count;
    }
    
    /**
     * Register cleanup cron
     */
    public static function register_cleanup_cron() {
        if (!wp_next_scheduled('wp_project_todos_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_project_todos_cleanup');
        }
    }
    
    /**
     * Unregister cleanup cron
     */
    public static function unregister_cleanup_cron() {
        wp_clear_scheduled_hook('wp_project_todos_cleanup');
    }
}