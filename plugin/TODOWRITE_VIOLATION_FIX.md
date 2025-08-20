# TodoWrite Violation Fix - 20.08.2025

## Problem
Das Session-Validation-System meldete wiederholt kritische Violations für TodoWrite-Einträge, die niemals via TASK_COMPLETED abgeschlossen wurden. Diese IDs waren Phantom-Einträge (String-IDs wie "version-system-implementation"), die nie in der Datenbank existierten.

## Ursache
- TodoWrite wurde fälschlicherweise für interne Aufgabenplanung verwendet
- Selbst erfundene String-IDs statt echter numerischer Datenbank-IDs
- Diese Phantom-IDs konnten nie via TASK_COMPLETED abgeschlossen werden
- Session-Validation erkannte dies als kritische Violations

## Implementierte Lösung

### 1. CLAUDE.md Update
Neue kritische Sektion hinzugefügt:
- Klare Regeln für TodoWrite-Verwendung
- NUR für numerische Datenbank-IDs
- NIEMALS für interne Planung
- Warnung vor Session-Violations

### 2. Plugin-Validierung
`class-todo-model.php`: Neue Funktion `validate_todo_id()`:
```php
public function validate_todo_id($id) {
    // Nur numerische IDs erlauben
    if (!is_numeric($id)) {
        throw new Exception("Invalid Todo ID: Must be numeric database ID");
    }
    // Prüfung ob in DB existiert
}
```

### 3. Hook-System-Verbesserung
`task_complete.sh`: Validierung für numerische IDs:
```bash
if ! [[ "$CURRENT_TODO_ID" =~ ^[0-9]+$ ]]; then
    echo "❌ Ungültige Todo-ID - Muss numerisch sein!"
    exit 1
fi
```

## Getestete Szenarien
✅ String-ID Ablehnung: "version-system-implementation" wird korrekt abgelehnt
✅ Numerische IDs: Funktionieren weiterhin normal
✅ Fehlermeldungen: Klare Hinweise für User

## Verhinderte Violations
Diese Änderungen verhindern zukünftig:
- TodoWrite-Missbrauch für interne Planung
- Phantom-IDs in Session-Validation
- Endlosschleifen durch nicht-existente Todos
- Verwirrung über String vs. Numerische IDs

## Status
✅ CLAUDE.md aktualisiert mit kritischen Regeln
✅ Plugin-Validierung implementiert
✅ Hook-System gehärtet gegen String-IDs
✅ Tests erfolgreich durchgeführt
✅ Session als RESOLVED markiert