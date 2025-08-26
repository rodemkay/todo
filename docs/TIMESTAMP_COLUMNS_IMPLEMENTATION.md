# 🕐 Zeitstempel-Implementierung - Task #258

## 📋 Anforderung
- **"Geändert"** → **"Gestartet"** umbenennen
- **"Abgeschlossen"** Spalte hinzufügen
- Automatisches Setzen der Zeitstempel bei Status-Änderungen

## ✅ Implementierte Lösung

### 1. **Datenbank-Erweiterung**
```sql
ALTER TABLE stage_project_todos 
ADD COLUMN completed_at DATETIME DEFAULT NULL 
AFTER execution_started_at;
```

Vorhandene Spalten:
- `created_at` - Wann erstellt
- `execution_started_at` - Wann gestartet (Status → in_progress)
- `completed_at` - Wann abgeschlossen (Status → completed)

### 2. **Dashboard-Anzeige** (`wsj-dashboard.php`)

#### Spaltenüberschrift geändert:
```html
<th class="wsj-col-5">Erstellt / Gestartet / Abgeschlossen</th>
```

#### Zeitstempel-Anzeige:
```php
<div class="wsj-timestamps" style="font-size: 11px;">
    <!-- Erstellt (immer vorhanden) -->
    <div>
        <span style="color: #666;">Erstellt:</span><br>
        <?php echo date('d.m.y H:i', strtotime($todo->created_at)); ?>
    </div>
    
    <!-- Gestartet (wenn vorhanden) -->
    <?php if ($todo->execution_started_at) : ?>
    <div style="margin-top: 4px;">
        <span style="color: #0066cc;">Gestartet:</span><br>
        <?php echo date('d.m.y H:i', strtotime($todo->execution_started_at)); ?>
    </div>
    <?php endif; ?>
    
    <!-- Abgeschlossen (wenn vorhanden) -->
    <?php if ($todo->completed_at) : ?>
    <div style="margin-top: 4px;">
        <span style="color: #00aa00;">Abgeschlossen:</span><br>
        <?php echo date('d.m.y H:i', strtotime($todo->completed_at)); ?>
    </div>
    <?php endif; ?>
</div>
```

### 3. **Automatisches Setzen bei Status-Änderung**

#### Dashboard Status-Update Handler:
```php
if ($new_status === 'in_progress' && !$current_todo->execution_started_at) {
    $update_data['execution_started_at'] = current_time('mysql');
}

if ($new_status === 'completed') {
    $update_data['completed_at'] = current_time('mysql');
}
```

#### CLI Tool (`todo`):
```bash
# Bei Start (in_progress):
UPDATE stage_project_todos 
SET status='in_progress', 
    execution_started_at=NOW() 
WHERE id=$todo_id 
  AND execution_started_at IS NULL

# Bei Abschluss (completed):
UPDATE stage_project_todos 
SET status='completed', 
    completed_at=NOW() 
WHERE id=$todo_id
```

#### Python Hook System (`todo_manager.py`):
```python
def set_todo_status(todo_id, status):
    updates = [f"status='{status}'", "updated_at=NOW()"]
    
    if status == 'in_progress':
        updates.append("execution_started_at=IFNULL(execution_started_at, NOW())")
    
    if status == 'completed':
        updates.append("completed_at=NOW()")
```

## 📊 Geänderte Dateien

1. **Database:**
   - Neue Spalte `completed_at` hinzugefügt

2. **`/staging/wp-content/plugins/todo/templates/wsj-dashboard.php`**
   - Zeile 17-42: Erweiterter Status-Update Handler
   - Zeile 651: Neue Spaltenüberschrift
   - Zeile 740-761: Neue Zeitstempel-Anzeige

3. **`/home/rodemkay/www/react/plugin-todo/cli/todo`**
   - Zeile 38: execution_started_at bei get_next_todo
   - Zeile 63: execution_started_at bei get_todo_by_id
   - Zeile 87: completed_at bei complete_todo

4. **`/home/rodemkay/www/react/plugin-todo/hooks/todo_manager.py`**
   - Zeile 209-230: Erweiterte set_todo_status Funktion

## 🎯 Ergebnis

✅ **Zeitstempel werden automatisch gesetzt:**
- "Gestartet" erscheint wenn Status auf in_progress wechselt
- "Abgeschlossen" erscheint wenn Status auf completed wechselt
- Alle Zeitstempel werden dauerhaft in DB gespeichert
- Kompakte Darstellung mit farblicher Kennzeichnung

## 💡 Vorteile

1. **Transparenz:** Kompletter Lebenszyklus eines Todos sichtbar
2. **Automatisierung:** Keine manuellen Zeitstempel nötig
3. **Konsistenz:** Alle Entry-Points (Dashboard, CLI, Hooks) synchron
4. **Performance-Tracking:** Dauer zwischen Start und Abschluss messbar

---

*Implementiert für Task #258 - 2025-08-22*