# ⚠️ KRITISCHE TODO-AUSWAHL-REGEL

## ABSOLUT VERPFLICHTEND: NUR status='offen' UND bearbeiten=1

### Die Regel
Der `./todo` Befehl darf **AUSSCHLIESSLICH** TODOs laden, die **BEIDE** Bedingungen erfüllen:
1. `status = 'offen'`
2. `bearbeiten = 1`

### Warum diese Regel?
- **in_progress** TODOs sind bereits in Bearbeitung oder wurden unterbrochen
- **completed** TODOs sind abgeschlossen
- **bearbeiten=0** TODOs sind für manuelle Bearbeitung vorgesehen
- Nur **offene** TODOs mit **bearbeiten=1** sind für Claude bestimmt

### Wo ist diese Regel implementiert?

#### 1. CLAUDE.md (Hauptdokumentation)
- Regel #1 in "KRITISCHE REGELN"
- CLI-Befehle Sektion
- Standard-Workflow
- Version 3.0.2

#### 2. todo_manager.py (Hook System)
- Zeile 49-54: Funktions-Dokumentation mit Warnung
- Zeile 61-62: SQL Query mit Kommentar
- Funktion `get_next_todo()`

#### 3. auto-check-todos.sh
- Zeile 24-25: Auto-Check Query

#### 4. hooks/README.md
- Loop-Modus Beschreibung

### SQL Query (KORREKT)
```sql
SELECT * FROM stage_project_todos 
WHERE status='offen' AND bearbeiten=1 
ORDER BY priority DESC, id ASC 
LIMIT 1
```

### FALSCHE Queries (NIEMALS VERWENDEN!)
```sql
-- FALSCH: Erlaubt in_progress
WHERE (status='offen' OR status='in_progress') AND bearbeiten=1

-- FALSCH: Ignoriert bearbeiten Flag  
WHERE status='offen'

-- FALSCH: Ignoriert Status
WHERE bearbeiten=1
```

### Ausnahme: Spezifisches TODO
Mit `./todo -id [nummer]` kann ein spezifisches TODO geladen werden, unabhängig vom Status oder bearbeiten-Flag. Dies ist für Debugging und spezielle Fälle.

## ⚠️ DIESE REGEL DARF NIEMALS GEÄNDERT WERDEN!

Datum der Regel: 2025-01-27
Version: 1.0
Autor: System Administrator