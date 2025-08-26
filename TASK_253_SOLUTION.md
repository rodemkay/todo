# ✅ Task #253 - Status-Problem gelöst!

## 🐛 Problem
Tasks wurden auf `in_progress` gesetzt BEVOR geprüft wurde, ob Claude verfügbar ist. 
Wenn Claude busy war ("Previous query still processing"), blieb der Task stuck in `in_progress`.

## 🔧 Lösung implementiert

### 1. **Lock-File Mechanismus**
- `/tmp/claude_processing.lock` verhindert parallele Task-Verarbeitung
- Lock wird erstellt wenn Task an Claude übergeben wird
- Lock wird freigegeben bei TASK_COMPLETED

### 2. **Zweistufiger Prozess**
```python
# VORHER: Status sofort ändern
set_todo_status(todo['id'], 'in_progress')  # ❌ Zu früh!

# NACHHER: Erst prüfen, dann ändern
lock_file = Path("/tmp/claude_processing.lock")
if lock_file.exists():
    # Claude ist busy
    print("● Previous query still processing. Todo remains in 'offen' status.")
    return None  # Task bleibt in 'offen'
    
# Claude ist frei - jetzt sicher Status ändern
lock_file.touch()  # Lock erstellen
set_todo_status(todo['id'], 'in_progress')  # ✅ Sicher!
```

### 3. **Stale Lock Detection**
- Locks älter als 5 Minuten werden automatisch entfernt
- Verhindert Deadlocks bei Crashes

### 4. **Clean Completion**
```python
def complete_todo(todo_id, ...):
    # Lock freigeben bei Completion
    lock_file = Path("/tmp/claude_processing.lock")
    if lock_file.exists():
        lock_file.unlink()
```

## 📁 Geänderte Dateien

### `/home/rodemkay/www/react/plugin-todo/hooks/todo_manager.py`
- **Zeilen 339-353:** Lock-Check für spezifisches Todo
- **Zeilen 427-442:** Lock-Check für nächstes Todo
- **Zeilen 234-237:** Lock-Release bei Completion
- **Zeile 11:** `import time` hinzugefügt

## 🧪 Test-Szenarien

### ✅ Test 1: Claude ist busy
```bash
touch /tmp/claude_processing.lock
./todo -id 250
# Ergebnis: "Previous query still processing. Todo remains in 'offen' status."
# Status bleibt 'offen' ✅
```

### ✅ Test 2: Claude ist frei
```bash
rm -f /tmp/claude_processing.lock
./todo -id 250
# Ergebnis: Task wird geladen und Status → 'in_progress'
```

### ✅ Test 3: Stale Lock
```bash
touch -t 202508220100 /tmp/claude_processing.lock  # Alter Lock
./todo -id 250
# Ergebnis: "Stale lock detected, removing..."
# Task wird normal verarbeitet
```

## 🎯 Vorteile der Lösung

1. **Keine stuck Tasks mehr** - Status wird nur geändert wenn Claude wirklich verfügbar
2. **Tasks bleiben wiederaufrufbar** - Bleiben in 'offen' wenn nicht verarbeitet
3. **Automatische Recovery** - Stale locks werden entfernt
4. **Backward Compatible** - Bestehende Funktionalität bleibt erhalten

## 📊 Verifikation

```bash
# Prüfe Lock-Status
ls -la /tmp/claude_processing.lock

# Prüfe Task-Status in DB
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
  wp db query 'SELECT id, title, status FROM stage_project_todos WHERE status = \"in_progress\"'"
```

## 🚀 Deployment
Die Änderungen sind bereits aktiv und funktionieren!