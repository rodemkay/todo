# 🔧 Parallel Todos Problem - Task #261

## 🐛 Problem

Es wurden 18 Todos gleichzeitig mit Status "in_progress" gefunden!
- Tasks wurden auf "in_progress" gesetzt
- Bei Unterbrechung/Fehler wurden sie nie auf "completed" gesetzt
- Diese "stuck" Todos blockierten das System

## 🔍 Analyse

```sql
SELECT COUNT(*) FROM stage_project_todos 
WHERE status = 'in_progress'
-- Ergebnis: 18
```

Problematische Kategorien:
1. **16 Todos ohne execution_started_at** - Nie richtig gestartet
2. **2 Todos mit altem execution_started_at** - Hängengeblieben

## ✅ Implementierte Lösung

### 1. **Sofort-Cleanup durchgeführt**
```sql
-- Reset todos ohne Startzeit
UPDATE stage_project_todos 
SET status = 'offen' 
WHERE status = 'in_progress' 
  AND execution_started_at IS NULL
-- 16 Todos zurückgesetzt

-- Reset spezifische stuck todos
UPDATE stage_project_todos 
SET status = 'offen', execution_started_at = NULL 
WHERE id IN (245, 256, 260)
-- 3 weitere Todos zurückgesetzt
```

### 2. **Cleanup-Script erstellt**
`/home/rodemkay/www/react/plugin-todo/scripts/cleanup_stuck_todos.sh`

Features:
- Zeigt alle stuck Todos
- Bietet verschiedene Cleanup-Optionen
- Interaktive Auswahl
- Sicheres Zurücksetzen

### 3. **Präventionsmaßnahmen**

#### Lock-File-System verbessert
- Nur EIN Todo kann gleichzeitig "in_progress" sein
- Lock-File `/tmp/CURRENT_TODO_ID` verhindert Parallelladen

#### Timeout-Mechanismus empfohlen
```bash
# Automatisches Cleanup alter Todos (Cron-Job)
*/30 * * * * wp db query "UPDATE stage_project_todos 
  SET status='offen', execution_started_at=NULL 
  WHERE status='in_progress' 
  AND TIMESTAMPDIFF(HOUR, execution_started_at, NOW()) > 2"
```

## 📊 Ergebnis

**Vorher:**
- 18 Todos mit status='in_progress'
- System blockiert und verwirrend

**Nachher:**
- 1 Todo mit status='in_progress' (aktuelles #261)
- System sauber und funktionsfähig

## 🛠️ Cleanup-Script Verwendung

```bash
# Script ausführen
/home/rodemkay/www/react/plugin-todo/scripts/cleanup_stuck_todos.sh

# Optionen:
1) Alte Todos (>1h) zurücksetzen
2) Todos ohne execution_started_at zurücksetzen
3) Alle außer aktuellem zurücksetzen
4) Spezifische IDs zurücksetzen
5) Nur anzeigen
```

## 💡 Lessons Learned

1. **Status-Management ist kritisch**
   - Immer cleanup bei Fehler/Unterbrechung
   - Zeitstempel nutzen für Timeout-Detection

2. **Lock-Files sind essentiell**
   - Verhindert paralleles Laden
   - Klare Single-Task-Policy

3. **Regelmäßige Cleanup-Jobs**
   - Automatisches Aufräumen stuck Todos
   - Verhindert Ansammlung von "Zombies"

## 🚀 Empfohlene Verbesserungen

1. **Auto-Cleanup in Hook-System**
```python
# Bei jedem Start prüfen
def cleanup_stuck_todos():
    query = """UPDATE stage_project_todos 
               SET status='offen' 
               WHERE status='in_progress' 
               AND TIMESTAMPDIFF(HOUR, execution_started_at, NOW()) > 2"""
    ssh_command(f'wp db query "{query}"')
```

2. **Status-Monitoring Dashboard**
- Widget für stuck Todos
- Alert bei >5 in_progress
- One-Click-Cleanup Button

---

*Fix für Task #261 - Implementiert am 2025-08-22*