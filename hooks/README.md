# Neues Todo Hook-System

## Übersicht
Dieses neue System ersetzt das problematische offizielle Claude Code Hook-System mit einer einfachen, zuverlässigen Lösung.

## Installation
Das System ist bereits installiert und aktiv. Das alte Hook-System wurde nach `/home/rodemkay/.claude/hooks.backup_*` verschoben.

## Verwendung

### Todos laden
```bash
# Nächstes Todo laden (Loop-Modus)
./todo

# Spezifisches Todo laden (Einzel-Modus)
./todo -id 159
```

### Todo abschließen
```bash
# Option 1: Via CLI
./todo complete

# Option 2: Via TASK_COMPLETED
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
```

### Status prüfen
```bash
./todo status
```

## Verhalten

### Loop-Modus (`./todo`)
1. Lädt nächstes Todo mit `status='offen'` UND `bearbeiten=1`
2. Setzt Status auf `in_progress`
3. Nach Abschluss: Automatisch nächstes Todo
4. Läuft bis ALLE offenen Todos abgearbeitet sind

### Einzel-Modus (`./todo -id`)
1. Lädt spezifisches Todo, egal welcher Status
2. Setzt Status auf `in_progress`
3. Nach Abschluss: Session endet, KEIN neues Todo

### Bei Todo-Abschluss
1. Claude-HTML-Zusammenfassung wird gespeichert
2. Text-Output wird generiert
3. Kurze Summary (max 150 Zeichen) wird erstellt
4. Timestamp wird aktualisiert
5. Status wird auf `completed` gesetzt

## Dateien

### Konfiguration
- `config.json` - Zentrale Konfiguration
- `todo-manager.py` - Python-Backend
- `completion-handler.sh` - TASK_COMPLETED Watcher

### Temporäre Dateien
- `/tmp/CURRENT_TODO_ID` - Aktuelle Todo-ID
- `/tmp/TASK_COMPLETED` - Completion-Trigger
- `/tmp/SPECIFIC_TODO_MODE` - Marker für Einzel-Modus

### Logs
- `logs/todo_YYYYMMDD.log` - Tägliche Logs
- `logs/completion.log` - Completion-Events

## Vorteile gegenüber altem System
✅ Keine Blockierungen bei DB-Updates
✅ Keine versteckten Violations
✅ Klare, nachvollziehbare Logs
✅ Einfache Fehlersuche
✅ Konsistentes Verhalten
✅ WordPress-Integration funktioniert

## Troubleshooting

### Todo wird nicht geladen
```bash
# Prüfe SSH-Verbindung
ssh rodemkay@159.69.157.54 "echo OK"

# Prüfe WordPress
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1'"
```

### Completion funktioniert nicht
```bash
# Prüfe ob Todo aktiv ist
cat /tmp/CURRENT_TODO_ID

# Manuell abschließen
python3 /home/rodemkay/www/react/plugin-todo/hooks/todo-manager.py complete
```

## Migration vom alten System
Das alte Hook-System wurde automatisch deaktiviert und gesichert. Bei Bedarf kann es unter `/home/rodemkay/.claude/hooks.backup_*` gefunden werden.

---

**Version:** 1.0.0
**Datum:** 2025-08-20
**Autor:** Claude Code mit User-Spezifikationen