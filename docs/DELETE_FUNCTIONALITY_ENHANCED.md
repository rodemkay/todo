# 📋 TODO Löschfunktion - Erweiterte Implementierung

## 🎯 Aufgabe
Beim Löschen eines TODOs sollen auch alle zugehörigen Dateien und Ordner (Anhänge und Agent-Outputs) automatisch gelöscht werden.

## ✅ Implementierte Lösung

### 1. Model-Klasse erweitert (`class-todo-model.php`)

Die `delete()` Funktion in der Todo_Model Klasse wurde erweitert um:

1. **Attachments-Ordner Löschung** (bereits vorhanden, aber verbessert)
   - Pfad: `/wp-content/uploads/todo-attachments/{TODO-ID}/`
   - Löscht zuerst alle Dateien im Ordner
   - Löscht dann den Ordner selbst

2. **Agent-Outputs Ordner Löschung** (NEU)
   - Pfad: `/home/rodemkay/www/react/plugin-todo/agent-outputs/todo-{TODO-ID}/`
   - Rekursive Löschung aller Dateien und Unterordner
   - Neue Helper-Funktion: `delete_directory_recursive()`

### 2. Bulk-Actions angepasst (`wsj-dashboard.php`)

Die Bulk-Delete Funktion wurde angepasst:
- Vorher: Direkter DB-Delete ohne Cleanup
- Nachher: Verwendet `$todo_model->delete()` für vollständigen Cleanup

### 3. Code-Änderungen

#### class-todo-model.php
```php
public function delete($id) {
    // ... existing code ...
    
    // Delete agent-outputs directory if it exists
    $agent_outputs_base = '/home/rodemkay/www/react/plugin-todo/agent-outputs';
    $agent_outputs_dir = $agent_outputs_base . '/todo-' . $id;
    if (is_dir($agent_outputs_dir)) {
        $this->delete_directory_recursive($agent_outputs_dir);
    }
    
    // ... rest of function ...
}

private function delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $this->delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}
```

#### wsj-dashboard.php
```php
case 'delete':
    // Use the model's delete function to ensure attachments and outputs are also deleted
    $todo_model = new Todo_Model();
    $deleted_count = 0;
    foreach($todo_ids as $id) {
        $result = $todo_model->delete($id);
        if ($result === true) {
            $deleted_count++;
        }
    }
    echo '<div class="notice notice-success"><p>' . $deleted_count . ' Aufgaben gelöscht (inkl. Anhänge und Agent-Outputs)!</p></div>';
    break;
```

## 🧪 Testing

### Test-Szenario:
1. TODO mit ID 367 erstellt
2. Agent-Output Ordner `/agent-outputs/todo-367/` mit Test-Dateien angelegt
3. TODO über Löschfunktion entfernt
4. Ordner wurde erfolgreich gelöscht ✅

### Gelöschte Komponenten beim TODO-Delete:
- ✅ TODO-Eintrag in Datenbank
- ✅ Alle Attachments in DB-Tabelle
- ✅ Attachment-Dateien auf Filesystem
- ✅ Attachment-Ordner `/uploads/todo-attachments/{ID}/`
- ✅ Agent-Output Ordner `/agent-outputs/todo-{ID}/`
- ✅ Alle Dateien in Agent-Output Ordner

## 📝 Hinweise

### Pfade:
- **Attachments:** WordPress Upload-Dir basiert (`wp_upload_dir()`)
- **Agent-Outputs:** Absoluter Pfad zum Plugin-Verzeichnis

### Fehlerbehandlung:
- Verwendet `@rmdir()` für fehlertolerantes Löschen
- Prüft Existenz von Ordnern vor Löschversuch
- Rekursive Funktion für verschachtelte Ordnerstrukturen

### Performance:
- Löschung erfolgt synchron (könnte bei vielen Dateien langsam sein)
- Für große Datenmengen evtl. Background-Job implementieren

## 🚀 Deployment

Die Änderungen sind bereits auf dem Staging-Server aktiv:
- `/var/www/forexsignale/staging/wp-content/plugins/todo/includes/class-todo-model.php`
- `/var/www/forexsignale/staging/wp-content/plugins/todo/templates/wsj-dashboard.php`

## ✅ Status
**IMPLEMENTIERT & GETESTET**

Die Löschfunktion entfernt nun zuverlässig:
1. TODO-Datenbankeinträge
2. Attachment-Dateien und -Ordner
3. Agent-Output-Dateien und -Ordner

---
*Dokumentiert: 2025-08-25 16:00 Uhr*