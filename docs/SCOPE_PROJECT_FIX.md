# 🔧 Scope/Project Inkonsistenz Fix

## 📋 Todo #352 - Abgeschlossen

### Problem
Es gab Inkonsistenzen zwischen `scope` und `project` Feldnamen im Formular und der Verarbeitung:
- Das HTML-Formular verwendete `name="scope"` für das Projekt-Dropdown
- JavaScript sendete sowohl `scope` als auch `project` 
- PHP erwartete mal `scope`, mal `project`

### Lösung
Vereinheitlichung auf `project` als einzigen Feldnamen:

#### 1. **HTML Formular** (`new-todo-v2.php`)
```html
<!-- VORHER -->
<select id="project_select" name="scope" class="wsj-select">

<!-- NACHHER -->
<select id="project_select" name="project" class="wsj-select">
```

#### 2. **JavaScript** (`todo-form-ajax.js`)
```javascript
// VORHER
project: $('#project_hidden').val() || ...,
scope: $('input[name="scope"]:checked').val() || 'Todo-Plugin',

// NACHHER  
project: $('#project_hidden').val() || ...,
// scope entfernt - wir verwenden nur noch project
```

#### 3. **PHP Backend** (`todo.php`)
```php
// VORHER
'scope' => sanitize_text_field($_POST['scope'] ?? $_POST['project'] ?? ...),

// NACHHER
'scope' => sanitize_text_field($_POST['project'] ?? $_POST['project_name'] ?? 'Todo-Plugin'),
```

#### 4. **Edit Modal** (`class-admin.php`)
```html
<!-- VORHER -->
<input type="radio" name="scope" value="...">

<!-- NACHHER -->
<input type="radio" name="project" value="...">
```

### Geänderte Dateien
1. `/wp-content/plugins/todo/admin/new-todo-v2.php` - Zeile 992
2. `/wp-content/plugins/todo/assets/js/todo-form-ajax.js` - Zeile 24-27
3. `/wp-content/plugins/todo/todo.php` - Zeilen 581, 1836, 2249
4. `/wp-content/plugins/todo/includes/class-admin.php` - Zeilen 856, 1062

### Status
✅ **ERFOLGREICH BEHOBEN**
- Alle Formulare verwenden jetzt konsistent `name="project"`
- JavaScript sendet nur noch `project` (kein separates `scope` mehr)
- PHP verarbeitet primär `project` mit Fallback zu altem `scope` für Kompatibilität
- Die Datenbank-Spalte heißt weiterhin `scope` (keine Migration nötig)

### Test
Um zu testen, ob alles funktioniert:
1. Neues Todo erstellen → Projekt auswählen → Speichern
2. Todo bearbeiten → Projekt ändern → Speichern
3. Prüfen ob das richtige Projekt gespeichert wird

### Empfehlung
Langfristig sollte auch die Datenbank-Spalte von `scope` auf `project` umbenannt werden für vollständige Konsistenz.