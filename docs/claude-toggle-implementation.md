# 🔘 CLAUDE TOGGLE IMPLEMENTATION

## 📍 STATUS
**Datum:** 2025-08-20  
**Problem:** Claude Toggle erscheint als globaler Button statt individual pro Task  
**Lösung:** Individual-Buttons mit AJAX implementieren

## 🎯 ZIEL-DESIGN
Basierend auf: `docs/screenshots/todo-dashboard-ziel.png`
- Jede Zeile hat eigenen Claude Toggle Button
- Format: `❌ Claude` (disabled) oder `✓ Claude` (enabled)
- Kein globaler Toggle-Button in der Filter-Leiste

## 📂 BETROFFENE DATEIEN

### Template-Datei (Frontend)
**Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/templates/wsj-dashboard.php`
- Zeile ~476-485: Claude Toggle Button HTML
- Zeile ~455-472: JavaScript toggleSelectedClaude() Funktion

### PHP-Handler (Backend)
**Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/includes/class-admin.php`
- Methode: `handle_form_submissions()` - POST-Handler für Toggle
- Neue Methode benötigt: `ajax_toggle_claude_single()` für AJAX

### JavaScript (AJAX)
**Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/admin/js/wsj-dashboard.js`
- Neue Funktion: `toggleClaudeSingle(todoId)` für AJAX-Calls

## 🔧 IMPLEMENTATION

### 1. HTML-Button Structure (wsj-dashboard.php)
```php
<button type="button" 
        class="claude-toggle-btn"
        data-todo-id="<?php echo $todo->id; ?>"
        onclick="toggleClaudeSingle(<?php echo $todo->id; ?>)">
    <?php echo $todo->bearbeiten ? '✓ Claude' : '❌ Claude'; ?>
</button>
```

### 2. JavaScript AJAX Function
```javascript
function toggleClaudeSingle(todoId) {
    jQuery.post(ajaxurl, {
        action: 'toggle_claude_single',
        todo_id: todoId,
        nonce: '<?php echo wp_create_nonce("toggle_claude"); ?>'
    }, function(response) {
        if(response.success) {
            // Update button text
            var btn = jQuery('[data-todo-id="' + todoId + '"]');
            btn.text(response.data.enabled ? '✓ Claude' : '❌ Claude');
            btn.toggleClass('active');
        }
    });
}
```

### 3. PHP AJAX Handler (class-admin.php)
```php
public function ajax_toggle_claude_single() {
    check_ajax_referer('toggle_claude', 'nonce');
    
    $todo_id = intval($_POST['todo_id']);
    $current = $this->wpdb->get_var(
        $this->wpdb->prepare(
            "SELECT bearbeiten FROM {$this->table_name} WHERE id = %d",
            $todo_id
        )
    );
    
    $new_value = $current ? 0 : 1;
    $this->wpdb->update(
        $this->table_name,
        ['bearbeiten' => $new_value],
        ['id' => $todo_id]
    );
    
    wp_send_json_success(['enabled' => $new_value]);
}
```

## 🐛 AKTUELLES PROBLEM
Der Claude Toggle Button existiert im Template, aber:
1. Verwendet Form-Submit statt AJAX
2. Kein visuelles Feedback
3. Page-Reload bei jedem Toggle

## ✅ LÖSUNG
1. Button von Form-Submit zu onclick umstellen
2. AJAX-Handler in class-admin.php hinzufügen
3. JavaScript für Live-Update implementieren

## 📊 TESTING
- Dashboard-URL: https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos
- Test mit mehreren Tasks
- Verify: Kein Page-Reload
- Check: Button-Text ändert sich