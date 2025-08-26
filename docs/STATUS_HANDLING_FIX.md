# 🔧 Status-Handling Fix - Task #253

## 🐛 Problem

Wenn Claude gerade eine Aufgabe bearbeitet und eine neue Aufgabe durch den Todo-Manager getriggert wird:
1. Meldung erscheint: "● Previous query still processing. Please try again."
2. **FEHLER:** Status wurde trotzdem auf "in_progress" gesetzt
3. **FOLGE:** Task wird nach Beendigung des aktiven Tasks nicht mehr aufgerufen

## ✅ Lösung implementiert

### 1. **Lock-Check verbessert** (`todo_manager.py`)
```python
# ALT: Status wurde direkt geändert und handle_completion() aufgerufen
if Path(CONFIG["paths"]["current_todo"]).exists():
    handle_completion()  # Das war falsch!

# NEU: Keine Status-Änderung bei aktivem Todo
if Path(CONFIG["paths"]["current_todo"]).exists():
    print("● Previous query still processing. Please try again.")
    return None  # Status bleibt auf 'offen'!
```

### 2. **Status-Änderung verzögert**
Die Status-Änderung erfolgt jetzt in dieser Reihenfolge:

1. **Lock-File erstellen** (`/tmp/CURRENT_TODO_ID`)
2. **Verifizierung** der gespeicherten ID
3. **NUR bei Erfolg:** Status auf "in_progress" setzen

```python
# 1. ID speichern (Lock erstellen)
with open(CONFIG["paths"]["current_todo"], 'w') as f:
    f.write(str(todo['id']))

# 2. Verifizieren
with open(CONFIG["paths"]["current_todo"]) as f:
    saved_id = f.read().strip()
if saved_id != str(todo['id']):
    # Bei Fehler: KEIN Status-Update!
    Path(CONFIG["paths"]["current_todo"]).unlink()
    return None

# 3. Erst jetzt Status ändern
set_todo_status(todo['id'], 'in_progress')
print("✅ Todo successfully loaded and status changed to: in_progress")
```

## 🔄 Neuer Workflow

### Szenario 1: Claude ist frei
```
1. Todo laden → Lock erstellen → Status ändern → Bearbeitung
```

### Szenario 2: Claude ist beschäftigt
```
1. Todo laden → Lock existiert → Abbruch
2. Status bleibt 'offen'
3. Todo wird beim nächsten Mal wieder geladen
```

## 📊 Vorteile

1. **Keine verlorenen Tasks mehr**
   - Status bleibt 'offen' wenn Claude beschäftigt ist
   - Task wird automatisch beim nächsten Mal geladen

2. **Klare Status-Semantik**
   - 'offen' = Noch nicht bearbeitet
   - 'in_progress' = Claude arbeitet AKTUELL daran
   - 'completed' = Fertig bearbeitet

3. **Bessere Fehlerbehandlung**
   - Lock-File verhindert parallele Bearbeitung
   - Verifizierung stellt sicher, dass Todo übernommen wurde

## 🔍 Testing

### Test 1: Parallele Anfragen
```bash
# Terminal 1: Starte lange Aufgabe
./todo -id 250  # Lange Aufgabe

# Terminal 2: Versuche neue Aufgabe
./todo -id 253  # Sollte abgelehnt werden mit "Previous query still processing"

# Status prüfen
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
  wp db query 'SELECT id, status FROM stage_project_todos WHERE id IN (250,253)'"
# 253 sollte noch 'offen' sein!
```

### Test 2: Nach Abschluss
```bash
# Nach Abschluss von 250
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED

# 253 sollte jetzt automatisch geladen werden
./todo  # Sollte 253 laden
```

## 📋 Geänderte Dateien

1. **`/hooks/todo_manager.py`**
   - Zeilen 260-268: Lock-Check ohne Status-Änderung
   - Zeilen 313-334: Status erst nach Verifizierung (specific todo)
   - Zeilen 375-400: Status erst nach Verifizierung (next todo)

## 🎯 Zusammenfassung

**Problem gelöst:** Tasks werden nicht mehr verloren, wenn Claude beschäftigt ist.

Der Status wird NUR geändert wenn:
1. Kein anderes Todo aktiv ist (Lock-File)
2. Todo erfolgreich übernommen wurde (Verifizierung)
3. Claude tatsächlich mit der Bearbeitung beginnt

---

*Fix für Task #253 - Implementiert am 2025-08-21*