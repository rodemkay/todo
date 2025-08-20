<?php
/**
 * Playwright Screenshots Manager - Dedicated for playwright-screenshots directory
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Playwright_Screenshots {
    
    /**
     * Local screenshots directory - now uses WordPress uploads directly
     */
    private $local_dir = null;  // Will be set to upload_dir in constructor
    
    /**
     * WordPress upload directory for screenshots
     */
    private $upload_dir;
    private $upload_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/playwright-screenshots';
        $this->upload_url = $upload['baseurl'] . '/playwright-screenshots';
        
        // Use upload directory as local directory (screenshots are already there)
        $this->local_dir = $this->upload_dir . '/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    /**
     * Get all screenshots from local directory
     */
    public function get_local_screenshots() {
        $screenshots = [];
        
        if (!is_dir($this->local_dir)) {
            return $screenshots;
        }
        
        $files = scandir($this->local_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $this->local_dir . $file;
            if (!is_file($filepath)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                continue;
            }
            
            $screenshots[] = [
                'filename' => $file,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'extension' => $ext,
            ];
        }
        
        // Sort by modified date (newest first)
        usort($screenshots, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $screenshots;
    }
    
    /**
     * Get all uploaded screenshots
     */
    public function get_uploaded_screenshots() {
        $screenshots = [];
        
        if (!is_dir($this->upload_dir)) {
            return $screenshots;
        }
        
        $files = scandir($this->upload_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $this->upload_dir . '/' . $file;
            if (!is_file($filepath)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                continue;
            }
            
            $screenshots[] = [
                'filename' => $file,
                'filepath' => $filepath,
                'url' => $this->upload_url . '/' . $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'extension' => $ext,
            ];
        }
        
        // Sort by modified date (newest first)
        usort($screenshots, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $screenshots;
    }
    
    /**
     * Upload single screenshot from local to server
     * Note: Screenshots are already in upload directory, so just verify they exist
     */
    public function upload_screenshot($filename) {
        $filepath = $this->upload_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return new \WP_Error('not_found', 'Datei nicht gefunden: ' . $filename);
        }
        
        // File already exists in upload directory
        return [
            'filename' => $filename,
            'url' => $this->upload_url . '/' . $filename,
        ];
    }
    
    /**
     * Upload all local screenshots
     */
    public function upload_all_screenshots() {
        $local = $this->get_local_screenshots();
        $uploaded = 0;
        $errors = [];
        
        foreach ($local as $screenshot) {
            $result = $this->upload_screenshot($screenshot['filename']);
            if (is_wp_error($result)) {
                $errors[] = $screenshot['filename'] . ': ' . $result->get_error_message();
            } else {
                $uploaded++;
            }
        }
        
        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'total' => count($local),
        ];
    }
    
    /**
     * Delete single uploaded screenshot
     */
    public function delete_screenshot($filename) {
        $filepath = $this->upload_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return new \WP_Error('not_found', 'Datei nicht gefunden');
        }
        
        if (unlink($filepath)) {
            return true;
        }
        
        return new \WP_Error('delete_failed', 'Löschen fehlgeschlagen');
    }
    
    /**
     * Delete all uploaded screenshots
     */
    public function delete_all_screenshots() {
        $screenshots = $this->get_uploaded_screenshots();
        $deleted = 0;
        $errors = [];
        
        foreach ($screenshots as $screenshot) {
            $result = $this->delete_screenshot($screenshot['filename']);
            if (is_wp_error($result)) {
                $errors[] = $screenshot['filename'] . ': ' . $result->get_error_message();
            } else {
                $deleted++;
            }
        }
        
        return [
            'deleted' => $deleted,
            'errors' => $errors,
            'total' => count($screenshots),
        ];
    }
    
    /**
     * Format file size
     */
    public function format_size($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Check if local directory exists
     */
    public function local_directory_exists() {
        return is_dir($this->local_dir);
    }
    
    /**
     * Get local directory path
     */
    public function get_local_directory() {
        return $this->local_dir;
    }
    
    /**
     * Get upload directory URL
     */
    public function get_upload_url() {
        return $this->upload_url;
    }
}