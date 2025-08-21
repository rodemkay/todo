# 🐛 Hook-System Bugfix - Mehrfaches Todo-Laden

## Problem
Das Hook-System lud mehrere Todos gleichzeitig statt nacheinander.

### Symptom
Bei `./todo` wurden 2-3 Todos gleichzeitig geladen:
```
Loading Todo #157...
Loading Todo #156...  
Loading Todo #159...
```

### Ursache
In `todo_manager.py` Funktion `load_todo()`:
1. Wenn ein Todo aktiv war, wurde `handle_completion()` aufgerufen
2. `handle_completion()` schließt das Todo ab UND lädt automatisch das nächste
3. ABER: Der Code lief weiter und lud NOCHMAL ein Todo
4. Resultat: Doppeltes/Dreifaches Laden

### Lösung
```python
def load_todo(todo_id=None):
    if Path(CONFIG["paths"]["current_todo"]).exists():
        handle_completion()
        return None  # ← FIX: Hier aufhören!
```

Nach `handle_completion()` muss die Funktion beendet werden, da `handle_completion()` bereits das nächste Todo lädt.

## Prinzip
**Ein Todo nach dem anderen:**
1. Todo laden
2. Claude arbeitet
3. TASK_COMPLETED
4. Todo abschließen
5. Nächstes Todo laden
6. Repeat

**NIEMALS** mehrere Todos gleichzeitig!

---
**Behoben:** 2025-08-20
**Datei:** `/home/rodemkay/www/react/todo/hooks/todo_manager.py`