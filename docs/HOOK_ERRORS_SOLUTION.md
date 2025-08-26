# Todo #163: Hook Fehlermeldungen - RICHTIGE LÖSUNG

## Problem
Die gelben Hook-Fehlermeldungen von Claude Code stören:
```
PostToolUse:Read [/home/rodemkay/.claude/hooks/test_posttooluse_detailed.py] failed with non-blocking status code 127
```

## Warum passiert das?
Claude Code sucht nach Hook-Dateien in `/home/rodemkay/.claude/hooks/` für verschiedene Events:
- post-tool-use
- pre-tool-use  
- user-prompt-submit
- etc.

## Schlechte Lösung (Dummy-Dateien)
Dummy-Dateien erstellen die nichts tun → Das ist Müll!

## RICHTIGE LÖSUNG

### Option 1: Hook-System komplett deaktivieren
Claude Code sollte eine Einstellung haben um Hooks zu deaktivieren.

### Option 2: Eigenes Hook-System nutzen
Unser `/home/rodemkay/www/react/plugin-todo/hooks/` System funktioniert bereits gut:
- todo_manager.py
- output_collector.py
- monitor.py

### Option 3: Claude Code Settings anpassen
In `.claude/settings.json` oder Environment Variables hooks deaktivieren.

## Was wir NICHT machen sollten:
- ❌ Dummy-Dateien erstellen
- ❌ Fehler einfach ignorieren
- ❌ Fake-Hooks die nichts tun

## Was wir machen sollten:
- ✅ Das richtige Setting finden um Hooks zu deaktivieren
- ✅ Oder die Hooks richtig implementieren mit echtem Nutzen
- ✅ Unser eigenes Hook-System verwenden das bereits funktioniert