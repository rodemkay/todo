# Media Folder Delete Fix - TODO #377 & #378

## Problem
Wenn TODOs über das Dashboard gelöscht wurden, blieben die zugehörigen Media-Ordner bestehen.

## Ursache
Die Delete-Funktion in `wsj-dashboard.php` nutzte direkt `$wpdb->delete()` statt `Todo_Model->delete()`, welche die Media-Ordner-Bereinigung beinhaltet.

## Lösung (26.08.2025)

### 1. Delete-Funktion korrigiert
**Datei:** `/staging/wp-content/plugins/todo/templates/wsj-dashboard.php`
```php
// ALT (Zeile 8-32):
$wpdb->delete($wpdb->prefix . 'project_todos', ['id' => $todo_id]);

// NEU:
$model = new \WP_Project_Todos\Todo_Model();
$result = $model->delete($todo_id);
```

### 2. Nonce-Fehler behoben
**Problem:** updateManagerStatus() sendete 'todo_nonce', Server erwartete 'manage_service'
**Fix:** Data-nonce Attribute auf 'manage_service' geändert (Zeilen 695-697)

### 3. Error-Logging implementiert
**Neue Funktion in todo.php:** `handle_ajax_error_logging()` (Zeilen 2839-2872)
- Loggt Fehler in wp-content/debug.log
- Zusätzliche Datei: todo-ajax-errors.log
- JavaScript console.error() statt alert()

## Test-Status

### TODO #377
- ✅ Aus Datenbank gelöscht
- ❌ Ordner existiert noch (altes Problem vor Fix)
- **Pfad:** `/uploads/agent-outputs/todo-377/`

### TODO #378 (Löschtest)
- ✅ Noch in Datenbank (Status: in_progress)
- ✅ Test-Datei erstellt: `loeschtest-datei.txt`
- **Test:** Wenn über Dashboard gelöscht → Ordner sollte mitgelöscht werden

## Nächste Schritte
1. TODO #378 über Dashboard löschen
2. Verifizieren dass der Ordner `/uploads/agent-outputs/todo-378/` gelöscht wird
3. Manuell todo-377 Ordner entfernen (Altlast)

## Implementierte Dateien
- `wsj-dashboard.php`: Delete-Fix & Nonce-Fix
- `todo.php`: Error-Logging Handler
- `class-todo-model.php`: Bereits korrekt (delete_todo_folders)
- `class-media-manager.php`: Bereits korrekt (recursive_delete)