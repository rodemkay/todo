# 📋 TODO-FORMULAR - VOLLSTÄNDIGE TECHNISCHE DOKUMENTATION V2.0

## 📑 INHALTSVERZEICHNIS
1. [Übersicht](#übersicht)
2. [Dateistruktur](#dateistruktur)
3. [Datenbank-Schema](#datenbank-schema)
4. [Formular-Felder Detailbeschreibung](#formular-felder-detailbeschreibung)
5. [Datenfluss](#datenfluss)
6. [JavaScript-Funktionalitäten](#javascript-funktionalitäten)
7. [AJAX-Endpoints](#ajax-endpoints)
8. [Fehlerdiagnose](#fehlerdiagnose)
9. [Häufige Probleme und Lösungen](#häufige-probleme-und-lösungen)
10. [Speicher-Prozess Schritt für Schritt](#speicher-prozess-schritt-für-schritt)
11. [Wartung & Optimierung](#wartung--optimierung)

---

## 🎯 ÜBERSICHT

Das TODO-Formular ist das zentrale Element des TODO-Plugins und ermöglicht das Erstellen und Bearbeiten von Aufgaben. Es befindet sich unter:
- **URL:** `/wp-admin/admin.php?page=todo-new`
- **Hauptdatei:** `/wp-content/plugins/todo/admin/new-todo-v2.php`
- **Dateigröße:** ~4500 Zeilen
- **Mount-Pfad:** `/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/admin/new-todo-v2.php`

### Formular-Modi:
1. **Neu-Erstellung:** Ohne `todo_id` Parameter
2. **Bearbeitung:** Mit `?todo_id=X` oder `?id=X` Parameter
3. **Nach Speichern:** Bleibt im Edit-Modus mit Erfolgsmeldung

---

## 📁 DATEISTRUKTUR

### Haupt-Dateien:
```
/wp-content/plugins/todo/
├── admin/
│   ├── new-todo-v2.php         # Hauptformular (4500+ Zeilen)
│   ├── agent-outputs.php       # Agent-Output Viewer
│   └── backups/                # Backup-Versionen
├── includes/
│   ├── class-admin.php         # Admin-Handler & AJAX
│   ├── class-todo-model.php    # Datenbank-Model
│   ├── class-attachment-handler.php # Datei-Uploads
│   ├── class-media-manager.php # Neue Medien-Verwaltung
│   └── class-summary-manager.php # Auto-Zusammenfassungen
├── js/
│   ├── prompt-generator.js     # Prompt-Generierung
│   ├── todo-form-ajax.js       # AJAX-Handler
│   └── prompt-output-handler.js # Output-Management
└── css/
    └── wsj-dashboard.css        # Styles
```

---

## 🗄️ DATENBANK-SCHEMA

### Haupttabelle: `stage_project_todos`

```sql
CREATE TABLE stage_project_todos (
    -- Primärschlüssel
    id int(11) NOT NULL AUTO_INCREMENT,
    
    -- Versions-Management
    version varchar(10) DEFAULT '1.00',
    version_history longtext,
    
    -- Basis-Informationen
    title varchar(255) NOT NULL,
    description text,
    
    -- Projekt & Bereich
    scope varchar(255) DEFAULT 'todo-plugin',
    project_id int(11) DEFAULT NULL,
    project_name varchar(255) DEFAULT NULL,
    working_directory text,
    
    -- Status & Priorität
    status enum('offen','in_progress','completed','blocked','cancelled','cron') DEFAULT 'offen',
    priority enum('niedrig','mittel','hoch','kritisch') DEFAULT 'mittel',
    
    -- Claude/Agent Einstellungen
    bearbeiten tinyint(1) DEFAULT 0,  -- Von Claude bearbeiten
    mode enum('plan','execute','hybrid') DEFAULT 'execute',
    agent_count int(11) DEFAULT 0,
    save_agent_outputs tinyint(1) DEFAULT 1,
    development_area varchar(50) DEFAULT 'fullstack',
    
    -- MCP & Playwright
    playwright_check tinyint(1) DEFAULT 0,
    mcp_servers text,  -- JSON Array der ausgewählten MCP Server
    
    -- Agent-Konfiguration
    execution_mode enum('parallel','hierarchical','default') DEFAULT 'default',
    agent_settings text,
    subagent_instructions text,
    default_project varchar(255),
    
    -- Inhalte & Notizen
    plan longtext,  -- Ausführlicher Plan
    claude_notes longtext,  -- Claude-spezifische Notizen
    bemerkungen longtext,  -- Allgemeine Bemerkungen
    claude_html_output longtext,  -- HTML-formatierter Output
    claude_prompt longtext,  -- Zusätzliche Anweisungen
    prompt_output longtext,  -- Generierter Prompt
    
    -- Zeitstempel
    created_at timestamp DEFAULT current_timestamp(),
    updated_at timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    started_at timestamp NULL DEFAULT NULL,
    execution_started_at timestamp NULL DEFAULT NULL,
    
    -- Benutzer-Tracking
    created_by int(11) DEFAULT NULL,
    updated_by int(11) DEFAULT NULL,
    
    -- Wiederkehrende Aufgaben
    is_recurring tinyint(1) DEFAULT 0,
    recurring_type varchar(50) DEFAULT NULL,
    last_executed datetime DEFAULT NULL,
    
    -- Anhänge & Outputs
    attachment_count int(11) DEFAULT 0,
    
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_scope (scope),
    KEY idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Zusätzliche Tabellen:

#### `stage_project_todo_attachments`
```sql
CREATE TABLE stage_project_todo_attachments (
    id int(11) NOT NULL AUTO_INCREMENT,
    todo_id int(11) NOT NULL,
    file_name varchar(255) NOT NULL,
    file_path text NOT NULL,
    file_size int(11),
    mime_type varchar(100),
    uploaded_at timestamp DEFAULT current_timestamp(),
    uploaded_by int(11),
    PRIMARY KEY (id),
    KEY idx_todo (todo_id),
    FOREIGN KEY (todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE
);
```

---

## 📝 FORMULAR-FELDER DETAILBESCHREIBUNG

### 1. TITEL (Pflichtfeld) ⚠️
```html
<input type="text" id="title" name="title" required>
```
- **POST-Feld:** `$_POST['title']`
- **Datenbank-Feld:** `title` (VARCHAR 255)
- **Validierung:** Pflichtfeld, max. 255 Zeichen
- **Sanitization:** `sanitize_text_field($_POST['title'])`
- **Speicher-Code (Zeile 91):**
  ```php
  'title' => sanitize_text_field($_POST['title'])
  ```
- **Fehlerquellen:**
  - Leer → Datenbank-Fehler "Column 'title' cannot be null"
  - Über 255 Zeichen → Wird abgeschnitten ohne Warnung
  - Sonderzeichen → Werden escaped (< wird zu &lt;)

### 2. BESCHREIBUNG
```html
<textarea id="description" name="description" rows="3"></textarea>
```
- **POST-Feld:** `$_POST['description']`
- **Datenbank-Feld:** `description` (TEXT)
- **Validierung:** Optional
- **Sanitization:** `wp_kses_post($_POST['description'])`
- **Speicher-Code (Zeile 92):**
  ```php
  'description' => wp_kses_post($_POST['description'])
  ```
- **Features:** Auto-Resize, erlaubt sicheres HTML

### 3. STATUS
```html
<input type="radio" name="status" value="offen|in_progress|completed|blocked">
```
- **POST-Feld:** `$_POST['status']`
- **Datenbank-Feld:** `status` (ENUM)
- **Default:** 'offen'
- **Speicher-Code (Zeile 93):**
  ```php
  'status' => in_array($_POST['status'], ['offen','in_progress','completed','blocked']) 
              ? $_POST['status'] : 'offen'
  ```
- **Trigger:** 
  - `in_progress` → setzt `execution_started_at = NOW()`
  - `completed` → setzt `completion_time = NOW()` und triggert Zusammenfassungen

### 4. PRIORITÄT
```html
<input type="radio" name="priority" value="niedrig|mittel|hoch|kritisch">
```
- **POST-Feld:** `$_POST['priority']`
- **Datenbank-Feld:** `priority` (ENUM)
- **Default:** 'mittel'
- **Speicher-Code (Zeile 94):**
  ```php
  'priority' => in_array($_POST['priority'], ['niedrig','mittel','hoch','kritisch']) 
                ? $_POST['priority'] : 'mittel'
  ```

### 5. PROJEKT-AUSWAHL
```html
<input type="hidden" name="project_id" id="project_id">
<input type="hidden" name="project_name" id="project_name">
```
- **POST-Felder:** `$_POST['project_id']`, `$_POST['project_name']`
- **Datenbank-Felder:** `project_id` (INT), `project_name` (VARCHAR 255)
- **Speicher-Code (Zeilen 95-96):**
  ```php
  'project_id' => intval($_POST['project_id'] ?? 0),
  'project_name' => sanitize_text_field($_POST['project_name'] ?? '')
  ```
- **JavaScript-Sync:**
  ```javascript
  $('.wsj-project-btn').on('click', function() {
      $('#project_id').val($(this).data('project-id'));
      $('#project_name').val($(this).data('project-name'));
  });
  ```

### 6. ARBEITSVERZEICHNIS
```html
<select name="working_directory" id="working_directory">
    <option value="/home/rodemkay/www/react/plugin-todo/">(Standard) Todo-Plugin</option>
</select>
```
- **POST-Feld:** `$_POST['working_directory']`
- **Datenbank-Feld:** `working_directory` (TEXT)
- **Default:** `/home/rodemkay/www/react/plugin-todo/`
- **Speicher-Code (Zeile 97):**
  ```php
  'working_directory' => sanitize_text_field($_POST['working_directory'] ?? '/home/rodemkay/www/react/plugin-todo/')
  ```

### 7. ENTWICKLUNGSBEREICH
```html
<input type="radio" name="development_area" value="frontend|backend|fullstack|devops|design">
```
- **POST-Feld:** `$_POST['development_area']`
- **Datenbank-Feld:** `development_area` (VARCHAR 50)
- **Default:** 'fullstack'
- **Speicher-Code (Zeile 98):**
  ```php
  'development_area' => sanitize_text_field($_POST['development_area'] ?? 'fullstack')
  ```

### 8. CLAUDE BEARBEITEN (Toggle) ⚠️ KRITISCH
```html
<input type="checkbox" id="bearbeiten_checkbox" value="1">
<input type="hidden" name="bearbeiten" id="bearbeiten_hidden" value="0">
```
- **POST-Feld:** `$_POST['bearbeiten']` (Hidden Field!)
- **Datenbank-Feld:** `bearbeiten` (TINYINT 1)
- **Default:** 1 (NEU: standardmäßig aktiviert)
- **JavaScript-Sync (Zeile 698):**
  ```javascript
  document.getElementById('bearbeiten_checkbox').addEventListener('change', function() {
      document.getElementById('bearbeiten_hidden').value = this.checked ? '1' : '0';
  });
  ```
- **KRITISCHER FIX in Speicher-Code (Zeile 168):**
  ```php
  // ALT (FEHLERHAFT - speichert immer 1):
  'bearbeiten' => isset($_POST['bearbeiten']) ? 1 : 0,
  
  // NEU (KORREKT - prüft den Wert):
  'bearbeiten' => isset($_POST['bearbeiten']) && $_POST['bearbeiten'] == '1' ? 1 : 0,
  ```
- **Fehlerursache:** Hidden Field ist IMMER vorhanden (auch mit Wert "0"), daher muss der WERT geprüft werden, nicht die EXISTENZ!

### 9. AGENT-ANZAHL
```html
<input type="radio" name="agent_count" value="0|1|3|5|10|15|20|25|30">
```
- **POST-Feld:** `$_POST['agent_count']`
- **Datenbank-Feld:** `agent_count` (INT)
- **Default:** 0
- **Speicher-Code (Zeile 99):**
  ```php
  'agent_count' => intval($_POST['agent_count'] ?? 0)
  ```
- **Validierung:** Min 0, Max 30

### 10. AGENT-OUTPUTS SPEICHERN
```html
<input type="checkbox" id="save_agent_outputs_checkbox" value="1">
<input type="hidden" name="save_agent_outputs" id="save_agent_outputs_hidden" value="1">
```
- **POST-Feld:** `$_POST['save_agent_outputs']`
- **Datenbank-Feld:** `save_agent_outputs` (TINYINT 1)
- **Default:** 1 (aktiviert)
- **Speicher-Code (Zeile 100):**
  ```php
  'save_agent_outputs' => isset($_POST['save_agent_outputs']) && $_POST['save_agent_outputs'] == '1' ? 1 : 0
  ```
- **Auswirkung:** Erstellt Ordner `/wp-content/uploads/agent-outputs/todo-{ID}/`

### 11. MCP-SERVER AUSWAHL (Multiple)
```html
<input type="checkbox" id="mcp_playwright" name="mcp_servers[]" value="playwright">
<input type="checkbox" id="mcp_filesystem" name="mcp_servers[]" value="filesystem">
<input type="checkbox" id="mcp_puppeteer" name="mcp_servers[]" value="puppeteer">
<input type="checkbox" id="mcp_context7" name="mcp_servers[]" value="context7">
<input type="checkbox" id="mcp_shadcn" name="mcp_servers[]" value="shadcn">
```
- **POST-Feld:** `$_POST['mcp_servers']` (Array!)
- **Datenbank-Feld:** `mcp_servers` (TEXT/JSON)
- **Speicher-Code (Zeilen 101-105):**
  ```php
  $mcp_servers = [];
  if (isset($_POST['mcp_servers']) && is_array($_POST['mcp_servers'])) {
      $mcp_servers = array_map('sanitize_text_field', $_POST['mcp_servers']);
  }
  $todo_data['mcp_servers'] = json_encode($mcp_servers);
  ```
- **Laden aus DB:**
  ```php
  $mcp_servers = json_decode($todo->mcp_servers, true) ?: [];
  ```

### 12. SUBAGENT-ANWEISUNGEN
```html
<textarea id="subagent_instructions" name="subagent_instructions"></textarea>
```
- **POST-Feld:** `$_POST['subagent_instructions']`
- **Datenbank-Feld:** `subagent_instructions` (TEXT)
- **Speicher-Code (Zeile 106):**
  ```php
  'subagent_instructions' => wp_kses_post($_POST['subagent_instructions'] ?? '')
  ```

### 13. PLAN (WYSIWYG Editor)
```html
<textarea name="plan" id="plan"><?php echo esc_textarea($todo->plan ?? ''); ?></textarea>
```
- **POST-Feld:** `$_POST['plan']`
- **Datenbank-Feld:** `plan` (LONGTEXT)
- **Speicher-Code (Zeile 107):**
  ```php
  'plan' => wp_kses_post($_POST['plan'] ?? '')
  ```
- **Editor:** TinyMCE mit Custom-Toolbar
- **Auto-Save:** Nach 2 Sekunden Inaktivität

### 14. CLAUDE NOTIZEN
```html
<textarea name="claude_notes" id="claude_notes" rows="4"></textarea>
```
- **POST-Feld:** `$_POST['claude_notes']`
- **Datenbank-Feld:** `claude_notes` (LONGTEXT)
- **Speicher-Code (Zeile 108):**
  ```php
  'claude_notes' => wp_kses_post($_POST['claude_notes'] ?? '')
  ```

### 15. BEMERKUNGEN
```html
<textarea name="bemerkungen" id="bemerkungen" rows="4"></textarea>
```
- **POST-Feld:** `$_POST['bemerkungen']`
- **Datenbank-Feld:** `bemerkungen` (LONGTEXT)
- **Speicher-Code (Zeile 109):**
  ```php
  'bemerkungen' => wp_kses_post($_POST['bemerkungen'] ?? '')
  ```

### 16. ZUSÄTZLICHE ANWEISUNGEN (Claude Prompt)
```html
<textarea name="claude_prompt" id="claude_prompt" rows="4"></textarea>
```
- **POST-Feld:** `$_POST['claude_prompt']`
- **Datenbank-Feld:** `claude_prompt` (LONGTEXT)
- **Speicher-Code (Zeile 110):**
  ```php
  'claude_prompt' => wp_kses_post($_POST['claude_prompt'] ?? '')
  ```

### 17. DATEI-UPLOADS (Multiple)
```html
<input type="file" id="file_1" name="attachments[]" multiple>
```
- **POST-Feld:** `$_FILES['attachments']` (Array!)
- **Speicherort:** `/wp-content/uploads/agent-outputs/todo-{ID}/`
- **Verarbeitung (Zeilen 224-290):**
  ```php
  if (!empty($_FILES['attachments']['name'][0])) {
      require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-media-manager.php';
      $media_manager = new Todo_Media_Manager();
      
      foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
          if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
              $file_data = [
                  'name' => $_FILES['attachments']['name'][$key],
                  'type' => $_FILES['attachments']['type'][$key],
                  'tmp_name' => $tmp_name,
                  'size' => $_FILES['attachments']['size'][$key]
              ];
              $media_manager->upload_file($todo_id, $file_data, 'user');
          }
      }
  }
  ```
- **Erlaubte Typen:** txt, php, js, css, html, md, pdf, png, jpg, jpeg, zip, json, xml, csv
- **Max. Größe:** 50MB

### 18. WIEDERKEHRENDE AUFGABE
```html
<input type="checkbox" id="is_recurring" name="is_recurring" value="1">
```
- **POST-Feld:** `$_POST['is_recurring']`
- **Datenbank-Feld:** `is_recurring` (TINYINT 1)
- **Speicher-Code (Zeile 111):**
  ```php
  'is_recurring' => isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1' ? 1 : 0
  ```

### 19. AUSFÜHRUNGSMODUS
```html
<input type="radio" name="execution_mode" value="default|parallel|hierarchical">
```
- **POST-Feld:** `$_POST['execution_mode']`
- **Datenbank-Feld:** `execution_mode` (ENUM)
- **Default:** 'default'
- **Speicher-Code (Zeile 112):**
  ```php
  'execution_mode' => in_array($_POST['execution_mode'], ['default','parallel','hierarchical']) 
                      ? $_POST['execution_mode'] : 'default'
  ```

### 20. PROMPT OUTPUT (Auto-Generiert)
```html
<div id="prompt-output-display" class="wsj-prompt-output"></div>
<input type="hidden" name="prompt_output" id="prompt_output_hidden">
```
- **POST-Feld:** `$_POST['prompt_output']`
- **Datenbank-Feld:** `prompt_output` (LONGTEXT)
- **Generierung:** JavaScript aus allen Formularfeldern
- **Speicher-Code (Zeile 113):**
  ```php
  'prompt_output' => wp_kses_post($_POST['prompt_output'] ?? '')
  ```
- **Auto-Save via AJAX:** Alle 2 Sekunden

---

## 🔄 DATENFLUSS

### KOMPLETTER SPEICHER-PROZESS (Schritt für Schritt)

#### SCHRITT 1: Formular-Submit
```html
<!-- Formular wird abgeschickt -->
<form method="post" enctype="multipart/form-data" id="todo-form">
    <?php wp_nonce_field('save_todo', 'todo_nonce'); ?>
    <!-- Alle Formularfelder -->
    <button type="submit" name="save_todo">Speichern</button>
</form>
```

#### SCHRITT 2: POST-Daten empfangen (new-todo-v2.php Zeile 65)
```php
if (isset($_POST['save_todo'])) {
    // POST-Daten sind jetzt in $_POST verfügbar
    error_log('POST-Daten empfangen: ' . print_r($_POST, true));
}
```

#### SCHRITT 3: Nonce-Verifikation (Zeile 85-89)
```php
if (!isset($_POST['todo_nonce']) || !wp_verify_nonce($_POST['todo_nonce'], 'save_todo')) {
    wp_die('<div class="notice notice-error">
            <p><strong>Security Check Failed!</strong></p>
            <p>Bitte laden Sie die Seite neu und versuchen Sie es erneut.</p>
            </div>');
}
```

#### SCHRITT 4: Daten sammeln und sanitizen (Zeilen 91-120)
```php
$todo_data = array(
    'title' => sanitize_text_field($_POST['title']),
    'description' => wp_kses_post($_POST['description']),
    'status' => in_array($_POST['status'], ['offen','in_progress','completed','blocked']) 
                ? $_POST['status'] : 'offen',
    'priority' => in_array($_POST['priority'], ['niedrig','mittel','hoch','kritisch']) 
                  ? $_POST['priority'] : 'mittel',
    'project_id' => intval($_POST['project_id'] ?? 0),
    'project_name' => sanitize_text_field($_POST['project_name'] ?? ''),
    'working_directory' => sanitize_text_field($_POST['working_directory'] ?? '/home/rodemkay/www/react/plugin-todo/'),
    'development_area' => sanitize_text_field($_POST['development_area'] ?? 'fullstack'),
    'bearbeiten' => isset($_POST['bearbeiten']) && $_POST['bearbeiten'] == '1' ? 1 : 0,
    'agent_count' => intval($_POST['agent_count'] ?? 0),
    'save_agent_outputs' => isset($_POST['save_agent_outputs']) && $_POST['save_agent_outputs'] == '1' ? 1 : 0,
    'subagent_instructions' => wp_kses_post($_POST['subagent_instructions'] ?? ''),
    'plan' => wp_kses_post($_POST['plan'] ?? ''),
    'claude_notes' => wp_kses_post($_POST['claude_notes'] ?? ''),
    'bemerkungen' => wp_kses_post($_POST['bemerkungen'] ?? ''),
    'claude_prompt' => wp_kses_post($_POST['claude_prompt'] ?? ''),
    'prompt_output' => wp_kses_post($_POST['prompt_output'] ?? ''),
    'is_recurring' => isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1' ? 1 : 0,
    'execution_mode' => in_array($_POST['execution_mode'], ['default','parallel','hierarchical']) 
                        ? $_POST['execution_mode'] : 'default'
);

// MCP-Server separat verarbeiten (Array zu JSON)
$mcp_servers = [];
if (isset($_POST['mcp_servers']) && is_array($_POST['mcp_servers'])) {
    $mcp_servers = array_map('sanitize_text_field', $_POST['mcp_servers']);
}
$todo_data['mcp_servers'] = json_encode($mcp_servers);
```

#### SCHRITT 5: Datenbank-Operation (Zeilen 186-201)
```php
global $wpdb;
$table_name = $wpdb->prefix . 'project_todos';

// Prüfe ob Update oder Insert
$todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : 0;

if ($todo_id > 0) {
    // UPDATE bestehende Aufgabe
    $todo_data['updated_at'] = current_time('mysql');
    $todo_data['updated_by'] = get_current_user_id();
    
    $result = $wpdb->update(
        $table_name,
        $todo_data,
        array('id' => $todo_id),
        null,  // Format für Werte (automatisch)
        array('%d')  // Format für WHERE (id ist integer)
    );
    
    error_log("UPDATE Query: " . $wpdb->last_query);
    
} else {
    // INSERT neue Aufgabe
    $todo_data['created_at'] = current_time('mysql');
    $todo_data['created_by'] = get_current_user_id();
    
    $result = $wpdb->insert(
        $table_name,
        $todo_data,
        null  // Format automatisch erkennen
    );
    
    if ($result !== false) {
        $todo_id = $wpdb->insert_id;
    }
    
    error_log("INSERT Query: " . $wpdb->last_query);
}
```

#### SCHRITT 6: Fehlerbehandlung (Zeilen 203-210)
```php
if ($result === false) {
    // Datenbank-Fehler aufgetreten
    $error_message = $wpdb->last_error;
    error_log("DB Error: " . $error_message);
    error_log("Last Query: " . $wpdb->last_query);
    
    wp_die('<div class="notice notice-error">
            <p><strong>Datenbank-Fehler!</strong></p>
            <p>' . esc_html($error_message) . '</p>
            <p>Query: <code>' . esc_html($wpdb->last_query) . '</code></p>
            </div>');
}
```

#### SCHRITT 7: Status-basierte Aktionen (Zeilen 212-220)
```php
// Spezielle Aktionen basierend auf Status
if ($todo_data['status'] == 'in_progress' && empty($todo->execution_started_at)) {
    // Setze Start-Zeit wenn Status auf in_progress wechselt
    $wpdb->update(
        $table_name,
        array('execution_started_at' => current_time('mysql')),
        array('id' => $todo_id)
    );
}

if ($todo_data['status'] == 'completed') {
    // Generiere automatische Zusammenfassung
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-summary-manager.php';
    $summary_manager = new Todo_Summary_Manager();
    $summary_manager->generate_summary($todo_id);
}
```

#### SCHRITT 8: Datei-Upload verarbeiten (Zeilen 224-290)
```php
if (!empty($_FILES['attachments']['name'][0])) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-media-manager.php';
    $media_manager = new Todo_Media_Manager();
    
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/agent-outputs/todo-' . $todo_id . '/';
    
    // Ordner erstellen falls nicht vorhanden
    if (!file_exists($target_dir)) {
        wp_mkdir_p($target_dir);
    }
    
    // Jede Datei verarbeiten
    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
            $file_name = sanitize_file_name($_FILES['attachments']['name'][$key]);
            $target_file = $target_dir . $file_name;
            
            // Datei verschieben
            if (move_uploaded_file($tmp_name, $target_file)) {
                // In Datenbank speichern
                $wpdb->insert(
                    $wpdb->prefix . 'project_todo_attachments',
                    array(
                        'todo_id' => $todo_id,
                        'file_name' => $file_name,
                        'file_path' => $target_file,
                        'file_size' => $_FILES['attachments']['size'][$key],
                        'mime_type' => $_FILES['attachments']['type'][$key],
                        'uploaded_at' => current_time('mysql'),
                        'uploaded_by' => get_current_user_id()
                    )
                );
            }
        }
    }
}
```

#### SCHRITT 9: Redirect oder Nachricht (Zeilen 292-300)
```php
if (isset($_POST['save_without_redirect'])) {
    // Bleibe auf der Seite mit Erfolgsmeldung
    $success_message = 'TODO erfolgreich gespeichert!';
    // Lade TODO neu für Anzeige
    $todo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d", 
        $todo_id
    ));
} else {
    // Redirect zur Dashboard-Seite
    wp_redirect(admin_url('admin.php?page=wp-project-todos&message=saved'));
    exit;
}
```

---

## 🎮 JAVASCRIPT-FUNKTIONALITÄTEN

### 1. TOGGLE-SYNCHRONISATION (Kritisch für Claude-Toggle!)
```javascript
// KRITISCH: Synchronisiert Checkbox mit Hidden Field
// Datei: new-todo-v2.php, Zeilen 698-732

// Claude Toggle
document.addEventListener('DOMContentLoaded', function() {
    const bearbeitenCheckbox = document.getElementById('bearbeiten_checkbox');
    const bearbeitenHidden = document.getElementById('bearbeiten_hidden');
    
    if (bearbeitenCheckbox && bearbeitenHidden) {
        // Initial-Sync beim Laden
        bearbeitenHidden.value = bearbeitenCheckbox.checked ? '1' : '0';
        
        // Event-Listener für Änderungen
        bearbeitenCheckbox.addEventListener('change', function() {
            bearbeitenHidden.value = this.checked ? '1' : '0';
            console.log('Claude bearbeiten geändert zu:', bearbeitenHidden.value);
        });
    }
    
    // Agent-Outputs Toggle
    const agentOutputsCheckbox = document.getElementById('save_agent_outputs_checkbox');
    const agentOutputsHidden = document.getElementById('save_agent_outputs_hidden');
    
    if (agentOutputsCheckbox && agentOutputsHidden) {
        agentOutputsHidden.value = agentOutputsCheckbox.checked ? '1' : '0';
        
        agentOutputsCheckbox.addEventListener('change', function() {
            agentOutputsHidden.value = this.checked ? '1' : '0';
        });
    }
});
```

### 2. PROJEKT-AUSWAHL
```javascript
// Button-Click Handler für Projekt-Auswahl
// Zeilen 530-540

jQuery(document).ready(function($) {
    $('.wsj-project-btn').on('click', function(e) {
        e.preventDefault();
        
        // Hole Daten vom Button
        const projectId = $(this).data('project-id');
        const projectName = $(this).data('project-name');
        
        // Setze Hidden Fields
        $('#project_id').val(projectId);
        $('#project_name').val(projectName);
        
        // Visual Feedback
        $('.wsj-project-btn').removeClass('active');
        $(this).addClass('active');
        
        console.log('Projekt gewählt:', projectName, '(ID:', projectId, ')');
    });
});
```

### 3. STATUS & PRIORITÄT BUTTONS
```javascript
// Radio-Button-ähnliches Verhalten für Status/Priorität
// Zeilen 733-780

document.querySelectorAll('.status-group button').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Alle Buttons deaktivieren
        document.querySelectorAll('.status-group button').forEach(b => {
            b.classList.remove('active');
        });
        
        // Aktuellen aktivieren
        this.classList.add('active');
        
        // Hidden Input setzen
        const statusValue = this.dataset.value;
        document.querySelector('input[name="status"]').value = statusValue;
        
        console.log('Status geändert zu:', statusValue);
    });
});
```

### 4. FORMULAR-VALIDIERUNG VOR SUBMIT
```javascript
// Validierung beim Absenden
// Zeilen 541-556

document.getElementById('todo-form').addEventListener('submit', function(e) {
    // Titel prüfen (Pflichtfeld)
    const title = document.getElementById('title').value.trim();
    if (!title) {
        e.preventDefault();
        alert('Bitte geben Sie einen Titel ein!');
        document.getElementById('title').focus();
        return false;
    }
    
    // Synchronisiere alle Toggles vor Submit
    const toggles = ['bearbeiten', 'save_agent_outputs'];
    toggles.forEach(toggle => {
        const checkbox = document.getElementById(toggle + '_checkbox');
        const hidden = document.getElementById(toggle + '_hidden');
        if (checkbox && hidden) {
            hidden.value = checkbox.checked ? '1' : '0';
        }
    });
    
    console.log('Formular wird abgeschickt...');
});
```

---

## 🔌 AJAX-ENDPOINTS

### 1. AUTO-SAVE PROMPT OUTPUT
```php
// Handler in class-admin.php
add_action('wp_ajax_save_prompt_output', 'ajax_save_prompt_output');

function ajax_save_prompt_output() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'todo_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $todo_id = intval($_POST['todo_id']);
    $prompt_output = wp_kses_post($_POST['prompt_output']);
    
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'project_todos',
        array('prompt_output' => $prompt_output),
        array('id' => $todo_id)
    );
    
    if ($result !== false) {
        wp_send_json_success('Saved');
    } else {
        wp_send_json_error($wpdb->last_error);
    }
}
```

### 2. MCP-SERVER DEFAULTS SPEICHERN
```php
add_action('wp_ajax_save_mcp_defaults', 'ajax_save_mcp_defaults');

function ajax_save_mcp_defaults() {
    if (!wp_verify_nonce($_POST['nonce'], 'todo_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $servers = isset($_POST['servers']) ? $_POST['servers'] : array();
    update_user_meta(get_current_user_id(), 'default_mcp_servers', $servers);
    
    wp_send_json_success('Defaults saved');
}
```

---

## 🔍 FEHLERDIAGNOSE

### DEBUGGING-ARSENAL (Füge diese in new-todo-v2.php ein)

#### 1. POST-DATEN DEBUGGEN (Nach Zeile 65)
```php
if (isset($_POST['save_todo'])) {
    // Debug-Output für POST-Daten
    error_log('========== TODO SAVE DEBUG START ==========');
    error_log('POST-Daten: ' . print_r($_POST, true));
    error_log('FILES-Daten: ' . print_r($_FILES, true));
    error_log('User: ' . wp_get_current_user()->user_login);
    error_log('Nonce erhalten: ' . $_POST['todo_nonce']);
    error_log('Nonce gültig: ' . (wp_verify_nonce($_POST['todo_nonce'], 'save_todo') ? 'JA' : 'NEIN'));
    
    // Spezifische Felder prüfen
    error_log('Titel: "' . $_POST['title'] . '"');
    error_log('Status: "' . $_POST['status'] . '"');
    error_log('Bearbeiten RAW: "' . $_POST['bearbeiten'] . '"');
    error_log('Bearbeiten wird zu: ' . (isset($_POST['bearbeiten']) && $_POST['bearbeiten'] == '1' ? '1' : '0'));
    error_log('MCP-Server: ' . print_r($_POST['mcp_servers'], true));
}
```

#### 2. DATENBANK-QUERIES DEBUGGEN (Nach Zeile 201)
```php
// Nach $wpdb->insert oder $wpdb->update
error_log('========== DB OPERATION DEBUG ==========');
error_log('Operation: ' . ($todo_id > 0 ? 'UPDATE' : 'INSERT'));
error_log('Tabelle: ' . $table_name);
error_log('Daten: ' . print_r($todo_data, true));
error_log('Last Query: ' . $wpdb->last_query);
error_log('Rows affected: ' . $wpdb->rows_affected);
if ($wpdb->last_error) {
    error_log('DB ERROR: ' . $wpdb->last_error);
}
error_log('Neue/Aktuelle ID: ' . $todo_id);
```

#### 3. JAVASCRIPT-DEBUGGING (In Browser-Console)
```javascript
// Formular-Daten prüfen
function debugFormData() {
    const formData = new FormData(document.getElementById('todo-form'));
    console.log('=== FORM DEBUG ===');
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
    
    // Hidden Fields speziell prüfen
    console.log('=== HIDDEN FIELDS ===');
    console.log('project_id:', document.getElementById('project_id').value);
    console.log('project_name:', document.getElementById('project_name').value);
    console.log('bearbeiten_hidden:', document.getElementById('bearbeiten_hidden').value);
    console.log('save_agent_outputs_hidden:', document.getElementById('save_agent_outputs_hidden').value);
    
    // Checkboxen prüfen
    console.log('=== CHECKBOXES ===');
    console.log('bearbeiten_checkbox:', document.getElementById('bearbeiten_checkbox').checked);
    console.log('save_agent_outputs_checkbox:', document.getElementById('save_agent_outputs_checkbox').checked);
}

// Vor Submit aufrufen
document.getElementById('todo-form').addEventListener('submit', debugFormData);
```

---

## ⚠️ HÄUFIGE PROBLEME UND LÖSUNGEN

### PROBLEM 1: "Claude bearbeiten" wird nicht korrekt gespeichert ⚠️

**SYMPTOM:** Toggle wird beim Editieren nicht übernommen oder speichert falschen Wert

**URSACHE:** Hidden Field wird immer mit POST gesendet (auch mit Wert "0")

**DIAGNOSE:**
```php
// Debug-Output einfügen
error_log('Bearbeiten POST-Wert: "' . $_POST['bearbeiten'] . '"');
error_log('Bearbeiten isset: ' . (isset($_POST['bearbeiten']) ? 'JA' : 'NEIN'));
error_log('Bearbeiten == 1: ' . ($_POST['bearbeiten'] == '1' ? 'JA' : 'NEIN'));
```

**LÖSUNG:**
```php
// KORREKTE Implementierung (Zeile 168):
'bearbeiten' => isset($_POST['bearbeiten']) && $_POST['bearbeiten'] == '1' ? 1 : 0,

// NICHT:
'bearbeiten' => isset($_POST['bearbeiten']) ? 1 : 0,  // FALSCH!
```

**JAVASCRIPT-SYNC SICHERSTELLEN:**
```javascript
// Muss beim Laden UND bei Änderung synchronisieren
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('bearbeiten_checkbox');
    const hidden = document.getElementById('bearbeiten_hidden');
    
    // Initial-Sync WICHTIG!
    if (checkbox && hidden) {
        hidden.value = checkbox.checked ? '1' : '0';
        
        checkbox.addEventListener('change', function() {
            hidden.value = this.checked ? '1' : '0';
            console.log('Sync: Checkbox=' + this.checked + ', Hidden=' + hidden.value);
        });
    }
});
```

### PROBLEM 2: MCP-Server werden nicht gespeichert

**SYMPTOM:** Ausgewählte MCP-Server verschwinden nach Speichern

**URSACHE:** Array wird nicht korrekt zu JSON konvertiert

**DIAGNOSE:**
```php
error_log('MCP POST: ' . print_r($_POST['mcp_servers'], true));
error_log('MCP JSON: ' . json_encode($_POST['mcp_servers']));
```

**LÖSUNG:**
```php
// Beim Speichern (Zeilen 101-105):
$mcp_servers = [];
if (isset($_POST['mcp_servers']) && is_array($_POST['mcp_servers'])) {
    $mcp_servers = array_map('sanitize_text_field', $_POST['mcp_servers']);
}
$todo_data['mcp_servers'] = json_encode($mcp_servers);

// Beim Laden aus DB:
$mcp_servers = json_decode($todo->mcp_servers, true) ?: [];

// Im HTML:
foreach ($mcp_servers as $server) {
    echo '<input type="checkbox" name="mcp_servers[]" value="' . esc_attr($server) . '" checked>';
}
```

### PROBLEM 3: Datei-Upload schlägt fehl

**SYMPTOM:** Dateien werden nicht hochgeladen, keine Fehlermeldung

**DIAGNOSE-CHECKLISTE:**

1. **Form-Tag prüfen:**
```html
<!-- MUSS enctype haben! -->
<form method="post" enctype="multipart/form-data">
```

2. **PHP-Limits prüfen:**
```php
error_log('upload_max_filesize: ' . ini_get('upload_max_filesize'));
error_log('post_max_size: ' . ini_get('post_max_size'));
error_log('max_file_uploads: ' . ini_get('max_file_uploads'));
```

3. **$_FILES Array debuggen:**
```php
error_log('FILES: ' . print_r($_FILES, true));
if (isset($_FILES['attachments']['error'])) {
    foreach ($_FILES['attachments']['error'] as $error) {
        if ($error !== UPLOAD_ERR_OK) {
            error_log('Upload-Fehler Code: ' . $error);
        }
    }
}
```

4. **Ordner-Permissions prüfen:**
```bash
ls -la /var/www/forexsignale/staging/wp-content/uploads/agent-outputs/
# Sollte www-data:www-data mit 755 oder 775 sein
```

**LÖSUNG:**
```php
// Vollständige Error-Behandlung:
if (!empty($_FILES['attachments']['name'][0])) {
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/agent-outputs/todo-' . $todo_id . '/';
    
    // Ordner mit korrekten Permissions erstellen
    if (!file_exists($target_dir)) {
        wp_mkdir_p($target_dir);
        chmod($target_dir, 0775);
    }
    
    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
        $error = $_FILES['attachments']['error'][$key];
        
        if ($error === UPLOAD_ERR_OK) {
            $file_name = sanitize_file_name($_FILES['attachments']['name'][$key]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($tmp_name, $target_file)) {
                error_log('Datei erfolgreich hochgeladen: ' . $target_file);
            } else {
                error_log('move_uploaded_file fehlgeschlagen für: ' . $file_name);
            }
        } else {
            error_log('Upload-Fehler ' . $error . ' für Datei ' . $key);
        }
    }
}
```

### PROBLEM 4: Status wird nicht aktualisiert

**SYMPTOM:** Status-Änderung wird nicht gespeichert

**DIAGNOSE:**
```javascript
// In Browser-Console:
document.querySelector('input[name="status"]').value
// Sollte einen der gültigen Werte zeigen
```

**LÖSUNG:**
```javascript
// Status-Button Handler korrekt implementieren:
document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Hidden Input MUSS gesetzt werden!
        const statusInput = document.querySelector('input[name="status"]');
        statusInput.value = this.dataset.status;
        
        // Visual Feedback
        document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        console.log('Status gesetzt auf:', statusInput.value);
    });
});
```

### PROBLEM 5: Formular wird nicht gespeichert (generell)

**SYSTEMATISCHE FEHLERSUCHE:**

1. **Nonce prüfen:**
```php
error_log('Nonce im POST: ' . (isset($_POST['todo_nonce']) ? 'JA' : 'NEIN'));
error_log('Nonce-Wert: ' . $_POST['todo_nonce']);
error_log('Nonce gültig: ' . (wp_verify_nonce($_POST['todo_nonce'], 'save_todo') ? 'JA' : 'NEIN'));
```

2. **Pflichtfelder prüfen:**
```php
if (empty($_POST['title'])) {
    error_log('FEHLER: Titel ist leer!');
    wp_die('Titel ist ein Pflichtfeld');
}
```

3. **Datenbank-Verbindung prüfen:**
```php
global $wpdb;
if (!$wpdb->check_connection()) {
    error_log('KRITISCH: Keine DB-Verbindung!');
    wp_die('Datenbankverbindung verloren');
}
```

4. **Tabellen-Existenz prüfen:**
```php
$table = $wpdb->prefix . 'project_todos';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
error_log('Tabelle ' . $table . ' existiert: ' . ($table_exists ? 'JA' : 'NEIN'));
```

---

## 🛠️ WARTUNG & OPTIMIERUNG

### PERFORMANCE-OPTIMIERUNGEN

#### 1. Transaktionen für Multiple Updates:
```php
$wpdb->query('START TRANSACTION');
try {
    // Multiple Updates
    $wpdb->update(...);
    $wpdb->insert(...);
    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    error_log('Transaction failed: ' . $e->getMessage());
}
```

#### 2. Prepared Statements verwenden:
```php
// IMMER prepare verwenden für Sicherheit und Performance
$todo = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM %i WHERE id = %d", 
    $table_name,
    $todo_id
));
```

#### 3. Caching implementieren:
```php
// Transient API für häufige Queries
$projects = get_transient('todo_projects_list');
if ($projects === false) {
    $projects = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}projects");
    set_transient('todo_projects_list', $projects, HOUR_IN_SECONDS);
}
```

### SICHERHEITS-CHECKS

#### Komplette Sicherheits-Checkliste:
```php
// 1. Nonce IMMER prüfen
if (!wp_verify_nonce($_POST['todo_nonce'], 'save_todo')) {
    wp_die('Security check failed');
}

// 2. Capabilities prüfen
if (!current_user_can('edit_posts')) {
    wp_die('Insufficient permissions');
}

// 3. Referrer prüfen
if (!check_admin_referer('save_todo', 'todo_nonce')) {
    wp_die('Invalid referrer');
}

// 4. Sanitize ALLE Inputs
$title = sanitize_text_field($_POST['title']);
$content = wp_kses_post($_POST['content']);
$id = intval($_POST['id']);

// 5. Escape bei Output
echo esc_html($title);
echo esc_attr($attribute);
echo esc_url($url);
echo esc_textarea($text);

// 6. SQL Injection verhindern
$wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table, $id);
```

---

## 📊 SQL-REFERENZ

### Wichtigste Queries:

```sql
-- Neues TODO erstellen
INSERT INTO stage_project_todos 
(title, description, status, priority, bearbeiten, created_at, created_by) 
VALUES 
('Titel', 'Beschreibung', 'offen', 'mittel', 1, NOW(), 1);

-- TODO aktualisieren
UPDATE stage_project_todos 
SET 
    title = 'Neuer Titel',
    status = 'in_progress',
    bearbeiten = 1,
    updated_at = NOW(),
    updated_by = 1
WHERE id = 123;

-- TODO mit allen Details laden
SELECT * FROM stage_project_todos WHERE id = 123;

-- Attachments für TODO laden
SELECT * FROM stage_project_todo_attachments 
WHERE todo_id = 123 
ORDER BY uploaded_at DESC;

-- Projekte für Dropdown laden
SELECT id, name, color, icon 
FROM stage_projects 
WHERE active = 1 
ORDER BY display_order ASC;

-- Status-Update mit Timestamp
UPDATE stage_project_todos 
SET 
    status = 'in_progress',
    execution_started_at = CASE 
        WHEN execution_started_at IS NULL THEN NOW() 
        ELSE execution_started_at 
    END
WHERE id = 123;
```

---

## 🔄 HOOKS & FILTER REFERENZ

### Verfügbare WordPress Hooks:

```php
// Nach TODO-Speicherung
do_action('todo_saved', $todo_id, $todo_data);

// Nach Status-Änderung
do_action('todo_status_changed', $todo_id, $new_status, $old_status);

// Nach Datei-Upload
do_action('todo_file_uploaded', $todo_id, $file_path);

// Vor TODO-Löschung
do_action('before_todo_delete', $todo_id);

// Filter für erlaubte Dateitypen
$allowed = apply_filters('todo_allowed_file_types', array('txt', 'pdf', 'jpg'));

// Filter für Max-Upload-Size
$max_size = apply_filters('todo_max_upload_size', 50 * MB_IN_BYTES);
```

---

## 📝 LOGGING & MONITORING

### Debug-Log aktivieren (wp-config.php):
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Custom Log-Funktion:
```php
function todo_log($message, $data = null) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $log .= ' | Data: ' . print_r($data, true);
    }
    error_log($log, 3, WP_CONTENT_DIR . '/todo-debug.log');
}

// Verwendung:
todo_log('TODO gespeichert', array('id' => $todo_id, 'title' => $title));
```

### Log-Dateien überwachen:
```bash
# WordPress Debug-Log
tail -f /var/www/forexsignale/staging/wp-content/debug.log

# Custom TODO-Log
tail -f /var/www/forexsignale/staging/wp-content/todo-debug.log

# PHP Error-Log
tail -f /var/log/php/error.log
```

---

## 🚀 BEST PRACTICES

1. **IMMER Nonces verwenden** - Sicherheit geht vor!
2. **Daten IMMER sanitizen** - Traue keinem Input!
3. **Escapen bei Output** - XSS verhindern!
4. **Prepared Statements** - SQL-Injection vermeiden!
5. **Error Logging** - Probleme schnell finden!
6. **Transaktionen** - Datenintegrität sichern!
7. **Caching** - Performance optimieren!
8. **Backups** - Vor großen Änderungen!
9. **Testing** - In Staging testen!
10. **Dokumentation** - Immer aktuell halten!

---

## 📚 WEITERE RESSOURCEN

- **Plugin-Hauptdatei:** `/plugins/todo/todo.php`
- **Admin-Klasse:** `/plugins/todo/includes/class-admin.php`
- **Model-Klasse:** `/plugins/todo/includes/class-todo-model.php`
- **Media-Manager:** `/plugins/todo/includes/class-media-manager.php`
- **Summary-Manager:** `/plugins/todo/includes/class-summary-manager.php`
- **Hook-System:** `/docs/HOOK_SYSTEM_SOLUTION.md`
- **Agent-Outputs:** `/docs/AGENT_OUTPUT_MANAGEMENT_CENTRALIZED.md`
- **WordPress Codex:** https://codex.wordpress.org/
- **PHP Dokumentation:** https://www.php.net/manual/

---

## 🔍 QUICK-REFERENCE CHEAT SHEET

### POST-Felder → DB-Felder Mapping:
```
$_POST['title']              → title
$_POST['description']        → description
$_POST['status']             → status
$_POST['priority']           → priority
$_POST['project_id']         → project_id
$_POST['project_name']       → project_name
$_POST['working_directory']  → working_directory
$_POST['development_area']   → development_area
$_POST['bearbeiten']         → bearbeiten (ACHTUNG: Hidden Field!)
$_POST['agent_count']        → agent_count
$_POST['save_agent_outputs'] → save_agent_outputs
$_POST['mcp_servers'][]      → mcp_servers (als JSON)
$_POST['plan']               → plan
$_POST['claude_notes']       → claude_notes
$_POST['bemerkungen']        → bemerkungen
$_POST['claude_prompt']      → claude_prompt
$_POST['prompt_output']      → prompt_output
$_FILES['attachments']       → /uploads/agent-outputs/todo-{ID}/
```

### Kritische Zeilen in new-todo-v2.php:
```
Zeile 65:   POST-Check Beginn
Zeile 85:   Nonce-Verifikation
Zeile 91:   Daten-Sammlung Start
Zeile 168:  Claude-Toggle FIX!
Zeile 186:  DB-Operation Start
Zeile 224:  File-Upload Start
Zeile 698:  JavaScript Toggle-Sync
```

---

*Letzte Aktualisierung: 2025-08-25*
*Version: 2.0.0 - Erweiterte Fassung mit detaillierter Fehlerdiagnose*
*Autor: Claude (KI-Assistant)*
*Fokus: Vollständige technische Dokumentation mit Schwerpunkt auf Datenfluss und Fehlerquellen*