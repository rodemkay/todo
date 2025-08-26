# 📁 TODO Media Management System - Vollständige Dokumentation

## 📋 Inhaltsverzeichnis
1. [Übersicht](#übersicht)
2. [Architektur](#architektur)
3. [Technische Implementierung](#technische-implementierung)
4. [Ordnerstruktur](#ordnerstruktur)
5. [Komponenten](#komponenten)
6. [API-Referenz](#api-referenz)
7. [Sicherheit](#sicherheit)
8. [Workflow](#workflow)
9. [Troubleshooting](#troubleshooting)
10. [Entwicklungshistorie](#entwicklungshistorie)

---

## 🎯 Übersicht

Das TODO Media Management System ist eine umfassende Lösung zur Verwaltung von Medien und Anhängen für TODO-Aufgaben. Es bietet automatische Ordnererstellung, Upload-Funktionalität, Datei-Verwaltung und automatisches Cleanup.

### Hauptfunktionen
- ✅ **Automatische Ordnererstellung** beim TODO-Laden
- ✅ **Drag & Drop Upload** mit Multiple File Support
- ✅ **Kategorisierte Datei-Verwaltung** (Documents, Screenshots, Outputs, Attachments)
- ✅ **Automatisches Cleanup** beim TODO-Löschen
- ✅ **Sicherheitsmechanismen** gegen Path-Traversal und unbefugte Zugriffe

### Use Cases
- **Claude Outputs:** Automatische Speicherung von generierten Inhalten
- **Screenshots:** Playwright/Browser-Screenshots für Dokumentation
- **User Uploads:** Manuelle Datei-Anhänge (PDFs, Bilder, Dokumente)
- **Agent Outputs:** Automatisch generierte Berichte und Analysen

---

## 🏗️ Architektur

### System-Komponenten

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Frontend                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │Upload Button │  │Files Button  │  │Delete Button │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
└─────────┼──────────────────┼──────────────────┼─────────┘
          │                  │                  │
          ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────┐
│                    AJAX Layer                           │
│  ┌──────────────────────────────────────────────────┐  │
│  │  wp_ajax_upload_todo_media                       │  │
│  │  wp_ajax_list_todo_media                         │  │
│  │  wp_ajax_delete_todo_media                       │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────┐
│                Todo_Media_Manager Class                 │
│  ┌──────────────────────────────────────────────────┐  │
│  │  • create_todo_folders()                         │  │
│  │  • delete_todo_folders()                         │  │
│  │  • upload_file()                                 │  │
│  │  • delete_file()                                 │  │
│  │  • list_todo_files()                             │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────┐
│                    File System                          │
│  /wp-content/uploads/agent-outputs/todo-{ID}/          │
│  ├── documents/                                        │
│  ├── screenshots/                                      │
│  ├── outputs/                                          │
│  └── attachments/                                      │
└─────────────────────────────────────────────────────────┘
```

### Datenfluss

1. **Upload-Prozess:**
   ```
   User → Upload Button → Modal → File Selection → AJAX Upload → Media Manager → Filesystem
   ```

2. **Anzeige-Prozess:**
   ```
   User → Files Button → AJAX List → Media Manager → Filesystem → JSON Response → Modal Display
   ```

3. **Lösch-Prozess:**
   ```
   User → Delete Button → Confirmation → AJAX Delete → Media Manager → Filesystem Cleanup
   ```

---

## 💻 Technische Implementierung

### 1. PHP Backend-Klasse: `Todo_Media_Manager`

**Datei:** `/wp-content/plugins/todo/includes/class-media-manager.php`

```php
class Todo_Media_Manager {
    
    private static $base_upload_dir = 'agent-outputs';
    private static $subdirs = ['documents', 'screenshots', 'outputs', 'attachments'];
    
    /**
     * Erstellt die komplette Ordnerstruktur für ein TODO
     * @param int $todo_id Die TODO-ID
     * @return array Status und Pfade
     */
    public static function create_todo_folders($todo_id) {
        $todo_id = intval($todo_id);
        if ($todo_id <= 0) {
            return ['success' => false, 'message' => 'Invalid TODO ID'];
        }
        
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/' . self::$base_upload_dir . '/todo-' . $todo_id;
        
        // Hauptordner erstellen
        if (!file_exists($base_path)) {
            wp_mkdir_p($base_path);
        }
        
        // Unterordner erstellen
        $created_paths = [];
        foreach (self::$subdirs as $subdir) {
            $subdir_path = $base_path . '/' . $subdir;
            if (!file_exists($subdir_path)) {
                wp_mkdir_p($subdir_path);
                
                // .htaccess für Sicherheit
                self::create_htaccess($subdir_path);
                
                // README für Dokumentation
                self::create_readme($subdir_path, $subdir);
            }
            $created_paths[] = $subdir_path;
        }
        
        return [
            'success' => true,
            'base_path' => $base_path,
            'subdirs' => $created_paths
        ];
    }
    
    /**
     * Löscht alle Ordner und Dateien eines TODOs
     * @param int $todo_id Die TODO-ID
     * @return bool Erfolgsstatus
     */
    public static function delete_todo_folders($todo_id) {
        $todo_id = intval($todo_id);
        if ($todo_id <= 0) return false;
        
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/' . self::$base_upload_dir . '/todo-' . $todo_id;
        
        if (file_exists($base_path)) {
            return self::recursive_delete($base_path);
        }
        
        return true;
    }
    
    /**
     * Lädt eine Datei für ein TODO hoch
     * @param int $todo_id Die TODO-ID
     * @param array $file $_FILES Array-Element
     * @param string $category Kategorie (documents/screenshots/outputs/attachments)
     * @return array Upload-Status
     */
    public static function upload_file($todo_id, $file, $category = 'attachments') {
        // Validierung
        if (!in_array($category, self::$subdirs)) {
            $category = 'attachments';
        }
        
        // Dateityp-Prüfung
        $allowed_types = [
            'pdf', 'doc', 'docx', 'txt', 'md',
            'jpg', 'jpeg', 'png', 'gif',
            'php', 'js', 'css', 'html', 'json', 'xml',
            'zip', 'csv'
        ];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types)) {
            return ['success' => false, 'message' => 'Dateityp nicht erlaubt'];
        }
        
        // Größenlimit (50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Datei zu groß (max. 50MB)'];
        }
        
        // Zielordner
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . self::$base_upload_dir . 
                      '/todo-' . $todo_id . '/' . $category;
        
        // Ordner erstellen falls nicht vorhanden
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Eindeutiger Dateiname
        $filename = wp_unique_filename($target_dir, sanitize_file_name($file['name']));
        $target_path = $target_dir . '/' . $filename;
        
        // Upload
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Datenbank-Eintrag
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'todo_attachments',
                [
                    'todo_id' => $todo_id,
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'file_type' => $file['type'],
                    'file_size' => $file['size'],
                    'category' => $category,
                    'uploaded_at' => current_time('mysql')
                ]
            );
            
            return [
                'success' => true,
                'filename' => $filename,
                'url' => $upload_dir['baseurl'] . '/' . self::$base_upload_dir . 
                        '/todo-' . $todo_id . '/' . $category . '/' . $filename
            ];
        }
        
        return ['success' => false, 'message' => 'Upload fehlgeschlagen'];
    }
    
    /**
     * Listet alle Dateien eines TODOs
     * @param int $todo_id Die TODO-ID
     * @param string $category Optional: Nur bestimmte Kategorie
     * @return array Liste der Dateien
     */
    public static function list_todo_files($todo_id, $category = null) {
        $todo_id = intval($todo_id);
        $files = [];
        
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/' . self::$base_upload_dir . '/todo-' . $todo_id;
        $base_url = $upload_dir['baseurl'] . '/' . self::$base_upload_dir . '/todo-' . $todo_id;
        
        $dirs_to_scan = $category ? [$category] : self::$subdirs;
        
        foreach ($dirs_to_scan as $dir) {
            $dir_path = $base_path . '/' . $dir;
            if (!file_exists($dir_path)) continue;
            
            $dir_files = scandir($dir_path);
            foreach ($dir_files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'README.txt') {
                    continue;
                }
                
                $file_path = $dir_path . '/' . $file;
                $files[] = [
                    'filename' => $file,
                    'category' => $dir,
                    'size' => filesize($file_path),
                    'size_formatted' => size_format(filesize($file_path)),
                    'modified' => filemtime($file_path),
                    'url' => $base_url . '/' . $dir . '/' . $file,
                    'is_image' => @getimagesize($file_path) !== false
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * Rekursives Löschen von Verzeichnissen
     */
    private static function recursive_delete($dir) {
        if (!file_exists($dir)) return true;
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            
            if (!self::recursive_delete($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Erstellt .htaccess für Ordnerschutz
     */
    private static function create_htaccess($path) {
        $htaccess_content = "# Protect directory\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch '\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$'>\n";
        $htaccess_content .= "    Order Deny,Allow\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        
        file_put_contents($path . '/.htaccess', $htaccess_content);
    }
    
    /**
     * Erstellt README für Dokumentation
     */
    private static function create_readme($path, $type) {
        $descriptions = [
            'documents' => 'Dokumentationen, PDFs und Textdateien',
            'screenshots' => 'Screenshots und Bilder',
            'outputs' => 'Generierte Outputs und Reports',
            'attachments' => 'User-Uploads und sonstige Dateien'
        ];
        
        $content = "TODO Media Directory\n";
        $content .= "===================\n\n";
        $content .= "Type: " . ucfirst($type) . "\n";
        $content .= "Purpose: " . ($descriptions[$type] ?? 'Media files') . "\n";
        $content .= "Created: " . date('Y-m-d H:i:s') . "\n";
        
        file_put_contents($path . '/README.txt', $content);
    }
}
```

### 2. AJAX-Handler in WordPress Admin

**Datei:** `/wp-content/plugins/todo/includes/class-admin.php`

```php
class ProjectTodos_Admin {
    
    public function __construct() {
        // AJAX-Handler registrieren
        add_action('wp_ajax_upload_todo_media', [$this, 'ajax_upload_todo_media']);
        add_action('wp_ajax_list_todo_media', [$this, 'ajax_list_todo_media']);
        add_action('wp_ajax_delete_todo_media', [$this, 'ajax_delete_todo_media']);
    }
    
    /**
     * AJAX: Datei-Upload Handler
     */
    public function ajax_upload_todo_media() {
        // Nonce-Verifikation
        if (!wp_verify_nonce($_POST['nonce'], 'upload_todo_media')) {
            wp_die(json_encode(['success' => false, 'data' => 'Sicherheitsprüfung fehlgeschlagen']));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(['success' => false, 'data' => 'Keine Berechtigung']));
        }
        
        $todo_id = intval($_POST['todo_id']);
        if (!$todo_id) {
            wp_die(json_encode(['success' => false, 'data' => 'Ungültige TODO-ID']));
        }
        
        // Media Manager laden
        if (!class_exists('Todo_Media_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-media-manager.php';
        }
        
        // Ordner erstellen falls nicht vorhanden
        Todo_Media_Manager::create_todo_folders($todo_id);
        
        $uploaded = 0;
        $errors = [];
        
        // Alle hochgeladenen Dateien verarbeiten
        if (!empty($_FILES['files'])) {
            $files = $_FILES['files'];
            
            // Multiple File Upload handling
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $result = Todo_Media_Manager::upload_file($todo_id, $file, 'attachments');
                    if ($result['success']) {
                        $uploaded++;
                    } else {
                        $errors[] = $file['name'] . ': ' . $result['message'];
                    }
                }
            }
        }
        
        wp_die(json_encode([
            'success' => true,
            'data' => [
                'uploaded' => $uploaded,
                'errors' => $errors
            ]
        ]));
    }
    
    /**
     * AJAX: Dateien auflisten
     */
    public function ajax_list_todo_media() {
        // Nonce-Verifikation
        if (!wp_verify_nonce($_POST['nonce'], 'list_todo_media')) {
            wp_die(json_encode(['success' => false, 'data' => 'Sicherheitsprüfung fehlgeschlagen']));
        }
        
        $todo_id = intval($_POST['todo_id']);
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (!class_exists('Todo_Media_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-media-manager.php';
        }
        
        $files = Todo_Media_Manager::list_todo_files($todo_id, $category ?: null);
        
        // Zusätzliche Metadaten hinzufügen
        foreach ($files as &$file) {
            $file['icon'] = $this->get_file_icon($file['filename']);
            $file['preview_url'] = $file['is_image'] ? $file['url'] : '';
            $file['download_url'] = $file['url'];
            $file['created_at'] = date('d.m.Y H:i', $file['modified']);
        }
        
        wp_die(json_encode([
            'success' => true,
            'data' => [
                'files' => $files,
                'count' => count($files)
            ]
        ]));
    }
    
    /**
     * AJAX: Datei löschen
     */
    public function ajax_delete_todo_media() {
        // Nonce-Verifikation
        if (!wp_verify_nonce($_POST['nonce'], 'delete_todo_media')) {
            wp_die(json_encode(['success' => false, 'data' => 'Sicherheitsprüfung fehlgeschlagen']));
        }
        
        // Berechtigung prüfen
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(['success' => false, 'data' => 'Keine Berechtigung']));
        }
        
        $todo_id = intval($_POST['todo_id']);
        $filename = sanitize_file_name($_POST['filename']);
        
        if (!class_exists('Todo_Media_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-media-manager.php';
        }
        
        // Datei in allen Kategorien suchen und löschen
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/agent-outputs/todo-' . $todo_id;
        
        $deleted = false;
        foreach (['documents', 'screenshots', 'outputs', 'attachments'] as $dir) {
            $file_path = $base_path . '/' . $dir . '/' . $filename;
            if (file_exists($file_path)) {
                unlink($file_path);
                $deleted = true;
                
                // Datenbank-Eintrag löschen
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'todo_attachments',
                    [
                        'todo_id' => $todo_id,
                        'filename' => $filename
                    ]
                );
                break;
            }
        }
        
        wp_die(json_encode([
            'success' => $deleted,
            'data' => $deleted ? 'Datei gelöscht' : 'Datei nicht gefunden'
        ]));
    }
    
    /**
     * Hilfsfunktion: File Icon ermitteln
     */
    private function get_file_icon($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $icons = [
            'pdf' => '📄',
            'doc' => '📝', 'docx' => '📝', 'txt' => '📃', 'md' => '📃',
            'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
            'php' => '💻', 'js' => '💻', 'css' => '🎨', 'html' => '🌐',
            'json' => '📊', 'xml' => '📊', 'csv' => '📊',
            'zip' => '📦'
        ];
        
        return $icons[$ext] ?? '📎';
    }
}
```

### 3. Frontend JavaScript Implementation

**Datei:** `/wp-content/plugins/todo/templates/wsj-dashboard.php`

```javascript
// Media Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    
    // Upload Modal Functions
    let currentUploadTodoId = null;
    let selectedFiles = [];
    
    window.openUploadModal = function(todoId) {
        currentUploadTodoId = todoId;
        selectedFiles = [];
        
        // Reset form
        document.getElementById('modal-file-input').value = '';
        document.getElementById('selected-files-list').style.display = 'none';
        document.getElementById('modal-upload-btn').disabled = true;
        document.getElementById('upload-progress').style.display = 'none';
        
        // Show modal
        document.getElementById('upload-modal').style.display = 'flex';
        
        // Setup drag and drop
        setupDragAndDrop();
    };
    
    window.closeUploadModal = function() {
        document.getElementById('upload-modal').style.display = 'none';
        currentUploadTodoId = null;
        selectedFiles = [];
    };
    
    function setupDragAndDrop() {
        const dropArea = document.getElementById('drop-area');
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.classList.remove('dragover');
            }, false);
        });
        
        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }
    
    function handleFiles(files) {
        selectedFiles = Array.from(files);
        displaySelectedFiles();
    }
    
    function displaySelectedFiles() {
        const listContainer = document.getElementById('selected-files-list');
        const uploadBtn = document.getElementById('modal-upload-btn');
        
        if (selectedFiles.length === 0) {
            listContainer.style.display = 'none';
            uploadBtn.disabled = true;
            return;
        }
        
        listContainer.style.display = 'block';
        uploadBtn.disabled = false;
        
        // Create file list display
        let html = '<div class="file-list">';
        selectedFiles.forEach((file, index) => {
            html += `
                <div class="file-item">
                    <span class="file-icon">${getFileIcon(file.type)}</span>
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${formatFileSize(file.size)}</span>
                    <button type="button" class="remove-file" onclick="removeFile(${index})">×</button>
                </div>
            `;
        });
        html += '</div>';
        
        listContainer.innerHTML = html;
    }
    
    window.uploadModalFiles = function() {
        if (!currentUploadTodoId || selectedFiles.length === 0) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'upload_todo_media');
        formData.append('todo_id', currentUploadTodoId);
        formData.append('nonce', todoAjax.nonces.upload_todo_media);
        
        // Add all selected files
        selectedFiles.forEach((file) => {
            formData.append('files[]', file);
        });
        
        // Show progress
        const progress = document.getElementById('upload-progress');
        progress.style.display = 'block';
        
        // Disable upload button
        document.getElementById('modal-upload-btn').disabled = true;
        
        // Upload via AJAX
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${data.data.uploaded} Datei(en) erfolgreich hochgeladen!`);
                closeUploadModal();
                location.reload(); // Reload to show new attachments
            } else {
                alert('Fehler beim Upload: ' + (data.data || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            alert('Netzwerkfehler beim Upload');
        })
        .finally(() => {
            progress.style.display = 'none';
            document.getElementById('modal-upload-btn').disabled = false;
        });
    };
    
    // Files Modal Functions
    let currentViewTodoId = null;
    
    window.openFilesModal = function(todoId) {
        currentViewTodoId = todoId;
        
        // Show modal
        document.getElementById('files-modal').style.display = 'flex';
        
        // Load files
        loadTodoMedia(todoId);
    };
    
    window.closeFilesModal = function() {
        document.getElementById('files-modal').style.display = 'none';
        currentViewTodoId = null;
    };
    
    function loadTodoMedia(todoId) {
        const content = document.getElementById('modal-media-content');
        content.innerHTML = '<div class="wsj-loading">Dateien werden geladen...</div>';
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'list_todo_media',
                todo_id: todoId,
                nonce: todoAjax.nonces.list_todo_media
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTodoFiles(data.data.files);
            } else {
                content.innerHTML = '<div class="error">Fehler beim Laden der Dateien</div>';
            }
        })
        .catch(error => {
            console.error('Load error:', error);
            content.innerHTML = '<div class="error">Netzwerkfehler beim Laden</div>';
        });
    }
    
    function displayTodoFiles(files) {
        const content = document.getElementById('modal-media-content');
        
        if (files.length === 0) {
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📁</div>
                    <h3>Keine Dateien vorhanden</h3>
                    <p>Für dieses TODO wurden noch keine Dateien hochgeladen.</p>
                </div>
            `;
            return;
        }
        
        // Group files by category
        const grouped = {};
        files.forEach(file => {
            if (!grouped[file.category]) {
                grouped[file.category] = [];
            }
            grouped[file.category].push(file);
        });
        
        // Create tabbed interface
        let html = '<div class="media-tabs">';
        html += '<div class="tab-buttons">';
        html += '<button class="tab-btn active" onclick="showCategory(\'all\')">Alle</button>';
        
        for (const category in grouped) {
            html += `<button class="tab-btn" onclick="showCategory('${category}')">${category}</button>`;
        }
        html += '</div>';
        
        // Display files
        html += '<div class="media-grid" id="media-grid">';
        files.forEach(file => {
            html += createFileCard(file);
        });
        html += '</div></div>';
        
        content.innerHTML = html;
    }
    
    function createFileCard(file) {
        const preview = file.is_image ? 
            `<img src="${file.preview_url}" class="file-preview">` :
            `<div class="file-icon-large">${file.icon}</div>`;
            
        return `
            <div class="media-item" data-category="${file.category}">
                ${preview}
                <div class="media-info">
                    <div class="media-name">${file.filename}</div>
                    <div class="media-meta">
                        ${file.size_formatted} • ${file.created_at}
                    </div>
                    <div class="media-actions">
                        <a href="${file.download_url}" class="btn-download" download>
                            📥 Download
                        </a>
                        <button class="btn-delete" onclick="deleteFile('${file.filename}')">
                            🗑️ Löschen
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    window.deleteFile = function(filename) {
        if (!confirm('Datei wirklich löschen?')) {
            return;
        }
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'delete_todo_media',
                todo_id: currentViewTodoId,
                filename: filename,
                nonce: todoAjax.nonces.delete_todo_media
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload files
                loadTodoMedia(currentViewTodoId);
            } else {
                alert('Fehler beim Löschen: ' + data.data);
            }
        });
    };
    
    // Helper functions
    function getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType === 'application/pdf') return '📄';
        if (mimeType.includes('word')) return '📝';
        if (mimeType.includes('sheet')) return '📊';
        if (mimeType.includes('zip')) return '📦';
        return '📎';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
});
```

### 4. Python Integration für CLI

**Datei:** `/home/rodemkay/www/react/plugin-todo/hooks/todo_manager.py`

```python
def handle_todo_execution(todo_id, output_handler):
    """Führt ein TODO aus mit automatischer Ordnererstellung"""
    
    # Ordner erstellen via PHP
    create_result = run_ssh_command(f"""
        cd /var/www/forexsignale/staging && php -r "
            require_once 'wp-load.php';
            require_once 'wp-content/plugins/todo/includes/class-media-manager.php';
            
            \\$result = Todo_Media_Manager::create_todo_folders({todo_id});
            echo json_encode(\\$result);
        "
    """)
    
    if create_result:
        try:
            folder_data = json.loads(create_result)
            if folder_data.get('success'):
                output_handler.add_output(
                    f"✅ Ordnerstruktur erstellt für TODO #{todo_id}:\n"
                    f"   📁 {folder_data['base_path']}\n"
                    f"   ├── 📄 documents/\n"
                    f"   ├── 📸 screenshots/\n"
                    f"   ├── 📊 outputs/\n"
                    f"   └── 📎 attachments/\n"
                )
        except json.JSONDecodeError:
            pass
    
    # Rest der TODO-Ausführung...
```

---

## 📂 Ordnerstruktur

### Basis-Struktur
```
/wp-content/uploads/agent-outputs/
└── todo-{ID}/
    ├── documents/      # Dokumentationen, PDFs, Textdateien
    ├── screenshots/    # Bilder, Screenshots von Tests
    ├── outputs/        # Generierte Reports, Analysen
    └── attachments/    # User-Uploads, sonstige Dateien
```

### Sicherheits-Dateien
Jeder Unterordner enthält:
- `.htaccess` - Verhindert direkten PHP-Zugriff
- `README.txt` - Dokumentation des Ordner-Zwecks

### Beispiel für TODO #123
```
/wp-content/uploads/agent-outputs/todo-123/
├── documents/
│   ├── .htaccess
│   ├── README.txt
│   ├── implementation-plan.pdf
│   └── technical-spec.md
├── screenshots/
│   ├── .htaccess
│   ├── README.txt
│   ├── before-fix.png
│   └── after-fix.png
├── outputs/
│   ├── .htaccess
│   ├── README.txt
│   ├── test-results.json
│   └── performance-report.html
└── attachments/
    ├── .htaccess
    ├── README.txt
    ├── client-requirements.docx
    └── reference-design.jpg
```

---

## 🔌 API-Referenz

### PHP-Funktionen

#### `Todo_Media_Manager::create_todo_folders($todo_id)`
Erstellt die komplette Ordnerstruktur für ein TODO.

**Parameter:**
- `$todo_id` (int): Die ID des TODOs

**Return:**
```php
[
    'success' => true,
    'base_path' => '/full/path/to/todo-123',
    'subdirs' => ['/path/documents', '/path/screenshots', ...]
]
```

#### `Todo_Media_Manager::upload_file($todo_id, $file, $category)`
Lädt eine Datei hoch und speichert sie in der richtigen Kategorie.

**Parameter:**
- `$todo_id` (int): Die TODO-ID
- `$file` (array): $_FILES Array-Element
- `$category` (string): 'documents'|'screenshots'|'outputs'|'attachments'

**Return:**
```php
[
    'success' => true,
    'filename' => 'unique-filename.pdf',
    'url' => 'https://domain.com/wp-content/uploads/agent-outputs/todo-123/documents/file.pdf'
]
```

#### `Todo_Media_Manager::list_todo_files($todo_id, $category = null)`
Listet alle Dateien eines TODOs auf.

**Parameter:**
- `$todo_id` (int): Die TODO-ID
- `$category` (string|null): Optional: Nur bestimmte Kategorie

**Return:**
```php
[
    [
        'filename' => 'document.pdf',
        'category' => 'documents',
        'size' => 1234567,
        'size_formatted' => '1.2 MB',
        'modified' => 1234567890,
        'url' => 'https://...',
        'is_image' => false
    ],
    // ...
]
```

#### `Todo_Media_Manager::delete_todo_folders($todo_id)`
Löscht alle Ordner und Dateien eines TODOs.

**Parameter:**
- `$todo_id` (int): Die TODO-ID

**Return:**
- `true` bei Erfolg
- `false` bei Fehler

### JavaScript-Funktionen

#### `openUploadModal(todoId)`
Öffnet das Upload-Modal für ein TODO.

```javascript
openUploadModal(123); // Öffnet Upload für TODO #123
```

#### `openFilesModal(todoId)`
Öffnet das Datei-Ansicht-Modal für ein TODO.

```javascript
openFilesModal(123); // Zeigt Dateien von TODO #123
```

#### `uploadModalFiles()`
Führt den Upload der ausgewählten Dateien durch.

#### `deleteFile(filename)`
Löscht eine einzelne Datei.

```javascript
deleteFile('document.pdf'); // Löscht die Datei
```

### AJAX-Endpoints

#### `wp_ajax_upload_todo_media`
**POST Parameter:**
- `action`: 'upload_todo_media'
- `todo_id`: TODO-ID
- `files[]`: Datei-Array
- `nonce`: Security Token

**Response:**
```json
{
    "success": true,
    "data": {
        "uploaded": 3,
        "errors": []
    }
}
```

#### `wp_ajax_list_todo_media`
**POST Parameter:**
- `action`: 'list_todo_media'
- `todo_id`: TODO-ID
- `category`: Optional - Kategorie-Filter
- `nonce`: Security Token

**Response:**
```json
{
    "success": true,
    "data": {
        "files": [...],
        "count": 10
    }
}
```

#### `wp_ajax_delete_todo_media`
**POST Parameter:**
- `action`: 'delete_todo_media'
- `todo_id`: TODO-ID
- `filename`: Dateiname
- `nonce`: Security Token

**Response:**
```json
{
    "success": true,
    "data": "Datei gelöscht"
}
```

---

## 🔒 Sicherheit

### Implementierte Sicherheitsmaßnahmen

1. **Nonce-Verification**
   - Alle AJAX-Requests verwenden WordPress Nonces
   - Verhindert CSRF-Attacken

2. **Capability Checks**
   - Nur Admins (`manage_options`) können uploaden/löschen
   - Read-Only für andere Benutzer

3. **File Type Validation**
   - Whitelist erlaubter Dateitypen
   - MIME-Type Überprüfung

4. **File Size Limits**
   - Maximum 50MB pro Datei
   - Verhindert DoS durch große Uploads

5. **Path Traversal Protection**
   - Numerische TODO-ID Validierung
   - Sanitization von Dateinamen

6. **Directory Protection**
   - `.htaccess` verhindert PHP-Ausführung
   - Directory Listing deaktiviert

7. **SQL Injection Protection**
   - Prepared Statements für alle DB-Queries
   - WordPress `$wpdb` Abstraction Layer

### Erlaubte Dateitypen
```php
$allowed_types = [
    // Dokumente
    'pdf', 'doc', 'docx', 'txt', 'md',
    // Bilder
    'jpg', 'jpeg', 'png', 'gif',
    // Code
    'php', 'js', 'css', 'html', 'json', 'xml',
    // Archive
    'zip',
    // Daten
    'csv'
];
```

---

## 🔄 Workflow

### 1. TODO-Erstellung Workflow
```
TODO erstellt → ID generiert → Noch keine Ordner
```

### 2. TODO-Laden Workflow
```
./todo → TODO geladen → create_todo_folders() → Ordnerstruktur erstellt
```

### 3. Upload Workflow
```
User klickt Upload → Modal öffnet → Datei auswählen/Drag&Drop → 
AJAX Upload → Server Validation → Datei speichern → 
DB-Eintrag → Response → Modal schließen → Page Reload
```

### 4. Anzeige Workflow
```
User klickt Dateien → Modal öffnet → AJAX List Request → 
Server liest Dateien → JSON Response → Frontend rendert Grid
```

### 5. Lösch Workflow
```
User klickt Löschen → Confirmation → AJAX Delete Request → 
Server löscht Datei → DB-Eintrag löschen → Response → Grid Update
```

### 6. TODO-Löschung Workflow
```
TODO löschen → delete_todo_folders() → Rekursiv alle Dateien löschen → 
Ordner löschen → DB-Einträge löschen
```

---

## 🐛 Troubleshooting

### Problem: Upload-Button wird nicht angezeigt
**Lösung:**
1. Cache leeren (Browser + WordPress)
2. Prüfen ob JavaScript-Fehler in Console
3. Verify: `grep "openUploadModal" /path/to/wsj-dashboard.php`

### Problem: Upload schlägt fehl
**Mögliche Ursachen:**
1. **PHP Upload Limits:**
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   max_file_uploads = 20
   ```

2. **Ordner-Berechtigungen:**
   ```bash
   chmod 755 /wp-content/uploads/agent-outputs/
   ```

3. **Nonce expired:**
   - Seite neu laden

### Problem: Dateien werden nicht angezeigt
**Lösung:**
1. Prüfen ob Dateien physisch existieren:
   ```bash
   ls -la /wp-content/uploads/agent-outputs/todo-*/
   ```

2. AJAX-Response prüfen:
   - Browser DevTools → Network → Response

### Problem: Ordner werden nicht erstellt
**Lösung:**
1. **WordPress Upload-Dir prüfen:**
   ```php
   $upload_dir = wp_upload_dir();
   var_dump($upload_dir['basedir']);
   ```

2. **Schreibrechte prüfen:**
   ```bash
   sudo chown -R www-data:www-data /wp-content/uploads/
   ```

### Debug-Modus aktivieren
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Logs anzeigen
tail -f /wp-content/debug.log
```

---

## 📅 Entwicklungshistorie

### Version 1.0 (2024-01-27)
- **Initial Release**
- Basis-Upload-Funktionalität
- Ordnererstellung
- Datei-Anzeige

### Geplante Features (v2.0)
- [ ] Thumbnail-Generierung für Bilder
- [ ] ZIP-Download aller Dateien
- [ ] Versioning von Dateien
- [ ] S3-Integration für Cloud Storage
- [ ] Automatische Virus-Scans
- [ ] OCR für gescannte Dokumente
- [ ] Metadaten-Extraktion

---

## 📝 Code-Beispiele

### Datei programmatisch hochladen
```php
// PHP-Beispiel
$media_manager = new Todo_Media_Manager();
$file = [
    'name' => 'report.pdf',
    'type' => 'application/pdf',
    'tmp_name' => '/tmp/uploaded_file',
    'size' => 1234567
];
$result = $media_manager->upload_file(123, $file, 'documents');
```

### Alle Screenshots eines TODOs abrufen
```php
// PHP-Beispiel
$screenshots = Todo_Media_Manager::list_todo_files(123, 'screenshots');
foreach ($screenshots as $screenshot) {
    echo "<img src='{$screenshot['url']}' />";
}
```

### JavaScript: Upload mit Progress
```javascript
// Extended upload with progress tracking
const xhr = new XMLHttpRequest();

xhr.upload.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
        const percentComplete = (e.loaded / e.total) * 100;
        updateProgressBar(percentComplete);
    }
});

xhr.onload = function() {
    if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        handleUploadSuccess(response);
    }
};

xhr.open('POST', ajaxurl);
xhr.send(formData);
```

---

## 📌 Best Practices

1. **Immer Ordner vor Upload erstellen**
   ```php
   Todo_Media_Manager::create_todo_folders($todo_id);
   ```

2. **Dateinamen sanitizen**
   ```php
   $safe_filename = sanitize_file_name($original_name);
   ```

3. **MIME-Type verifizieren**
   ```php
   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $mime = finfo_file($finfo, $file['tmp_name']);
   ```

4. **Cleanup bei Fehlern**
   ```php
   try {
       // Upload
   } catch (Exception $e) {
       // Cleanup partial uploads
       if (file_exists($target_path)) {
           unlink($target_path);
       }
   }
   ```

5. **Responsive File Grid**
   ```css
   .media-grid {
       display: grid;
       grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
       gap: 20px;
   }
   ```

---

## 🔗 Weitere Ressourcen

- [WordPress Upload Handling](https://developer.wordpress.org/reference/functions/wp_handle_upload/)
- [WordPress AJAX in Plugins](https://codex.wordpress.org/AJAX_in_Plugins)
- [File Upload Security Best Practices](https://owasp.org/www-community/vulnerabilities/Unrestricted_File_Upload)
- [WordPress Filesystem API](https://developer.wordpress.org/reference/classes/wp_filesystem/)

---

**Letzte Aktualisierung:** 2025-01-27
**Version:** 1.0.0
**Autor:** Claude (Anthropic) & TODO System Team