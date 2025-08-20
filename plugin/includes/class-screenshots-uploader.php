<?php
/**
 * Screenshots Uploader class - Lädt lokale Screenshots auf den Server
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Screenshots_Uploader {
    
    /**
     * Upload directory path in WordPress
     */
    private $upload_base_dir;
    private $upload_base_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_base_dir = $upload_dir['basedir'] . '/playwright-screenshots';
        $this->upload_base_url = $upload_dir['baseurl'] . '/playwright-screenshots';
        
        // Create directory if it doesn't exist
        if (!file_exists($this->upload_base_dir)) {
            wp_mkdir_p($this->upload_base_dir);
        }
    }
    
    /**
     * Get all screenshots from WordPress uploads directory
     */
    public function get_uploaded_screenshots($search = '') {
        $screenshots = [];
        
        if (!is_dir($this->upload_base_dir)) {
            return $screenshots;
        }
        
        $files = scandir($this->upload_base_dir);
        if ($files === false) {
            return $screenshots;
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $this->upload_base_dir . '/' . $file;
            
            if (!is_file($filepath)) {
                continue;
            }
            
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            if (!in_array($extension, $allowed)) {
                continue;
            }
            
            // Apply search filter
            if (!empty($search) && stripos($file, $search) === false) {
                continue;
            }
            
            $screenshots[] = [
                'filename' => $file,
                'filepath' => $filepath,
                'url' => $this->upload_base_url . '/' . $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'extension' => $extension,
            ];
        }
        
        // Sort by modified date (newest first)
        usort($screenshots, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $screenshots;
    }
    
    /**
     * Upload screenshot from local to server
     */
    public function upload_screenshot($local_path) {
        if (!file_exists($local_path)) {
            return new \WP_Error('not_found', __('Lokale Datei nicht gefunden', 'wp-project-todos'));
        }
        
        $filename = basename($local_path);
        $target_path = $this->upload_base_dir . '/' . $filename;
        
        // Add number suffix if file exists
        $counter = 1;
        while (file_exists($target_path)) {
            $info = pathinfo($filename);
            $new_filename = $info['filename'] . '-' . $counter . '.' . $info['extension'];
            $target_path = $this->upload_base_dir . '/' . $new_filename;
            $counter++;
        }
        
        // Copy file
        if (copy($local_path, $target_path)) {
            return [
                'path' => $target_path,
                'url' => $this->upload_base_url . '/' . basename($target_path),
                'filename' => basename($target_path),
            ];
        }
        
        return new \WP_Error('upload_failed', __('Upload fehlgeschlagen', 'wp-project-todos'));
    }
    
    /**
     * Delete uploaded screenshot
     */
    public function delete_screenshot($filename) {
        $filepath = $this->upload_base_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return new \WP_Error('not_found', __('Datei nicht gefunden', 'wp-project-todos'));
        }
        
        if (unlink($filepath)) {
            return true;
        }
        
        return new \WP_Error('delete_failed', __('Löschen fehlgeschlagen', 'wp-project-todos'));
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
     * Bulk upload all local screenshots
     */
    public function bulk_upload_local_screenshots() {
        $local_dirs = [
            '/home/rodemkay/www/react/screenshots/',
            '/home/rodemkay/www/react/.playwright-mcp/',
            '/home/rodemkay/www/react/playwright-screenshots/',
        ];
        
        $uploaded = [];
        $errors = [];
        
        foreach ($local_dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filepath = $dir . $file;
                if (!is_file($filepath)) {
                    continue;
                }
                
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                if (!in_array($extension, $allowed)) {
                    continue;
                }
                
                $result = $this->upload_screenshot($filepath);
                if (is_wp_error($result)) {
                    $errors[] = $file . ': ' . $result->get_error_message();
                } else {
                    $uploaded[] = $result['filename'];
                }
            }
        }
        
        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'count' => count($uploaded),
        ];
    }
}