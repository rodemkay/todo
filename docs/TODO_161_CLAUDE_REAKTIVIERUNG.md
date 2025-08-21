# Todo #161: Claude Reaktivierung - Analyse & Lösung

## Problem
Es scheint so dass Claude sich nach Deaktivierung nach einer gewissen Zeit reaktiviert!
- User hatte im Dashboard unter "Offen" alles deaktiviert was Claude angeht
- Nach einer Weile waren 2 Projekte wieder aktiviert

## Analyse

### 1. Aktuelle Beobachtungen
- Im Dashboard sehe ich 3 offene Todos mit aktiviertem Claude:
  - #166 TODO-Plugin: ✅ Claude aktiviert
  - #163 ForexSignale: ✅ Claude aktiviert  
  - #161 ForexSignale: ✅ Claude aktiviert
- Nur 2 Todos mit deaktiviertem Claude:
  - #113 Analytics: ❌ Claude deaktiviert
  - #64 Other: ❌ Claude deaktiviert
  - #65 Backend: ❌ Claude deaktiviert

### 2. Potenzielle Ursachen

#### A) Standardwert bei neuen Todos
- Neue Todos könnten standardmäßig mit Claude aktiviert erstellt werden
- In der Edit-Ansicht ist die Checkbox standardmäßig aktiviert (checked)

#### B) Auto-Reaktivierung durch JavaScript
- Möglicherweise gibt es JavaScript-Code der die Checkbox automatisch aktiviert
- Bei bestimmten Aktionen könnte Claude wieder aktiviert werden

#### C) Datenbank-Default
- Die Datenbank-Spalte `bearbeiten` könnte einen Default-Wert von 1 haben

### 3. Technische Untersuchung

#### Gefundene Probleme:
1. **Default-Wert in neuen Todos:** Die Checkbox ist standardmäßig aktiviert
2. **Kein persistenter Toggle:** Der Toggle ändert nur die Anzeige, nicht den Datenbank-Wert permanent
3. **Batch-Aktionen:** Bei Bulk-Aktionen könnte Claude für mehrere Todos aktiviert werden

## Lösung

### Sofortige Maßnahmen:
1. Default-Wert für neue Todos auf 0 (deaktiviert) setzen
2. AJAX-Toggle sicherstellen dass er persistent speichert
3. Bulk-Aktionen prüfen und korrigieren

### Code-Fixes benötigt:

#### 1. In `admin/new-todo.php`:
```php
// Zeile wo Checkbox generiert wird - default auf unchecked setzen
$bearbeiten = isset($todo->bearbeiten) ? $todo->bearbeiten : 0; // Statt 1
```

#### 2. In `includes/class-admin.php`:
```php
// AJAX Toggle Handler sicherstellen
public function handle_claude_toggle() {
    $todo_id = intval($_POST['todo_id']);
    $new_value = $_POST['value'] === 'true' ? 1 : 0;
    
    // Direkt in DB speichern
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'project_todos',
        ['bearbeiten' => $new_value, 'updated_at' => current_time('mysql')],
        ['id' => $todo_id],
        ['%d', '%s'],
        ['%d']
    );
    
    wp_send_json_success(['new_value' => $new_value]);
}
```

#### 3. Datenbank-Schema prüfen:
```sql
ALTER TABLE stage_project_todos 
MODIFY COLUMN bearbeiten TINYINT(1) DEFAULT 0;
```

## Status
- **Todo #161:** In Bearbeitung
- **Priorität:** Hoch (User-Frustration)
- **Nächste Schritte:** Code-Fixes implementieren