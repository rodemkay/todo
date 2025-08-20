<?php
/**
 * Screenshots Manager class
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Screenshots {
    
    /**
     * Screenshot directories to scan
     */
    private $screenshot_dirs = [
        '/home/rodemkay/www/react/',  // Hauptverzeichnis
        '/home/rodemkay/www/react/screenshots/',
        '/home/rodemkay/www/react/.playwright-mcp/',
        '/home/rodemkay/www/react/playwright-screenshots/',
    ];
    
    /**
     * Allowed image extensions
     */
    private $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Nothing to initialize
    }
    
    /**
     * Get all screenshots from directories
     */
    public function get_all_screenshots($filter_dir = null, $search = '') {
        $screenshots = [];
        $seen_files = []; // Vermeidet Duplikate
        
        // Filter nach bestimmtem Verzeichnis
        $dirs_to_scan = $filter_dir ? [$filter_dir] : $this->screenshot_dirs;
        
        foreach ($dirs_to_scan as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            // Für Hauptverzeichnis nur direkte Dateien, keine Rekursion
            $is_root = ($dir === '/home/rodemkay/www/react/');
            
            $files = scandir($dir);
            if ($files === false) {
                continue;
            }
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filepath = $dir . $file;
                
                // Skip subdirectories in root
                if ($is_root && is_dir($filepath)) {
                    continue;
                }
                
                if (!is_file($filepath)) {
                    continue;
                }
                
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($extension, $this->allowed_extensions)) {
                    continue;
                }
                
                // Suchfilter anwenden
                if (!empty($search) && stripos($file, $search) === false) {
                    continue;
                }
                
                // Duplikate vermeiden
                $file_hash = md5($filepath);
                if (isset($seen_files[$file_hash])) {
                    continue;
                }
                $seen_files[$file_hash] = true;
                
                $screenshots[] = [
                    'filename' => $file,
                    'filepath' => $filepath,
                    'directory' => $dir,
                    'directory_name' => basename($dir) ?: 'react',
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                    'created' => filectime($filepath),
                    'extension' => $extension,
                    'url' => $this->get_screenshot_url($filepath),
                ];
            }
        }
        
        // Sort by modified date (newest first)
        usort($screenshots, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $screenshots;
    }
    
    /**
     * Get URL for screenshot
     */
    private function get_screenshot_url($filepath) {
        // Try to convert to URL if in uploads directory
        $upload_dir = wp_upload_dir();
        if (strpos($filepath, $upload_dir['basedir']) === 0) {
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath);
        }
        
        // For other directories, we'll need to serve them via PHP
        return admin_url('admin-ajax.php?action=wp_project_todos_serve_screenshot&file=' . base64_encode($filepath) . '&_wpnonce=' . wp_create_nonce('serve_screenshot'));
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
     * Delete screenshot
     */
    public function delete($filepath) {
        // Security check - only allow deletion from our directories
        $allowed = false;
        foreach ($this->screenshot_dirs as $dir) {
            if (strpos($filepath, $dir) === 0) {
                $allowed = true;
                break;
            }
        }
        
        if (!$allowed) {
            return new \WP_Error('not_allowed', __('Löschen nicht erlaubt', 'wp-project-todos'));
        }
        
        if (!file_exists($filepath)) {
            return new \WP_Error('not_found', __('Datei nicht gefunden', 'wp-project-todos'));
        }
        
        if (unlink($filepath)) {
            return true;
        }
        
        return new \WP_Error('delete_failed', __('Löschen fehlgeschlagen', 'wp-project-todos'));
    }
    
    /**
     * Serve screenshot file
     */
    public function serve_screenshot() {
        if (!isset($_GET['file']) || !isset($_GET['_wpnonce'])) {
            wp_die('Invalid request');
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'serve_screenshot')) {
            wp_die('Invalid nonce');
        }
        
        $filepath = base64_decode($_GET['file']);
        
        // Security check
        $allowed = false;
        foreach ($this->screenshot_dirs as $dir) {
            if (strpos($filepath, $dir) === 0) {
                $allowed = true;
                break;
            }
        }
        
        if (!$allowed || !file_exists($filepath)) {
            wp_die('File not found');
        }
        
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $mime_types = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        
        $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
        
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: public, max-age=3600');
        readfile($filepath);
        exit;
    }
    
    /**
     * Copy screenshot to WordPress uploads
     */
    public function copy_to_uploads($filepath) {
        if (!file_exists($filepath)) {
            return new \WP_Error('not_found', __('Datei nicht gefunden', 'wp-project-todos'));
        }
        
        $upload_dir = wp_upload_dir();
        $screenshots_dir = $upload_dir['basedir'] . '/playwright-screenshots';
        
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
        }
        
        $filename = basename($filepath);
        $new_path = $screenshots_dir . '/' . $filename;
        
        // Add number if file exists
        $counter = 1;
        while (file_exists($new_path)) {
            $info = pathinfo($filename);
            $new_path = $screenshots_dir . '/' . $info['filename'] . '-' . $counter . '.' . $info['extension'];
            $counter++;
        }
        
        if (copy($filepath, $new_path)) {
            return [
                'path' => $new_path,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_path),
            ];
        }
        
        return new \WP_Error('copy_failed', __('Kopieren fehlgeschlagen', 'wp-project-todos'));
    }
}