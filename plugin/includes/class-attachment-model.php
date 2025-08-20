<?php
/**
 * Attachment Model class for handling file uploads
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Attachment_Model {
    
    /**
     * Database table name
     */
    private $table;
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Upload directory path
     */
    private $upload_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'project_todo_attachments';
        
        // Set upload directory
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/todo-attachments';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    /**
     * Handle file upload
     */
    public function upload($todo_id, $file) {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('Datei-Upload fehlgeschlagen', 'wp-project-todos'));
        }
        
        // Check file size (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', __('Datei ist zu groß (max. 10MB)', 'wp-project-todos'));
        }
        
        // Allowed file types
        $allowed_types = [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 
            'xls', 'xlsx', 'txt', 'zip', 'rar', 'csv', 'mp4', 'mov'
        ];
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types)) {
            return new \WP_Error('invalid_type', __('Dateityp nicht erlaubt', 'wp-project-todos'));
        }
        
        // Generate unique filename
        $filename = time() . '_' . sanitize_file_name($file['name']);
        $todo_dir = $this->upload_dir . '/' . $todo_id;
        
        // Create todo-specific directory
        if (!file_exists($todo_dir)) {
            wp_mkdir_p($todo_dir);
        }
        
        $filepath = $todo_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new \WP_Error('move_failed', __('Datei konnte nicht gespeichert werden', 'wp-project-todos'));
        }
        
        // Save to database
        $result = $this->wpdb->insert($this->table, [
            'todo_id' => $todo_id,
            'file_name' => $file['name'],
            'file_path' => $filepath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'uploaded_by' => get_current_user_id()
        ]);
        
        if ($result === false) {
            // Delete file if database insert fails
            unlink($filepath);
            return new \WP_Error('db_error', __('Datenbankfehler', 'wp-project-todos'));
        }
        
        // Update attachment count
        $this->update_attachment_count($todo_id);
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get attachments for a todo
     */
    public function get_by_todo($todo_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT a.*, u.display_name 
                 FROM {$this->table} a
                 LEFT JOIN {$this->wpdb->users} u ON a.uploaded_by = u.ID
                 WHERE a.todo_id = %d 
                 ORDER BY a.uploaded_at DESC",
                $todo_id
            )
        );
    }
    
    /**
     * Delete attachment
     */
    public function delete($attachment_id) {
        $attachment = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $attachment_id
            )
        );
        
        if (!$attachment) {
            return new \WP_Error('not_found', __('Anhang nicht gefunden', 'wp-project-todos'));
        }
        
        // Delete file
        if (file_exists($attachment->file_path)) {
            unlink($attachment->file_path);
        }
        
        // Delete from database
        $result = $this->wpdb->delete($this->table, ['id' => $attachment_id]);
        
        if ($result !== false) {
            // Update attachment count
            $this->update_attachment_count($attachment->todo_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Update attachment count for a todo
     */
    private function update_attachment_count($todo_id) {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE todo_id = %d",
                $todo_id
            )
        );
        
        $this->wpdb->update(
            $this->wpdb->prefix . 'project_todos',
            ['attachment_count' => $count],
            ['id' => $todo_id]
        );
    }
    
    /**
     * Get attachment URL
     */
    public function get_url($attachment) {
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $attachment->file_path);
        return $upload_dir['baseurl'] . $relative_path;
    }
    
    /**
     * Check if file is image
     */
    public function is_image($attachment) {
        $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($attachment->file_type, $image_types);
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
}