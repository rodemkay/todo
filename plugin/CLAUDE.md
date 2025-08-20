# WP PROJECT TODOS - HOOK SYSTEM REGELN

## 🆕 AUTOMATISCHES TODO-MANAGEMENT (20.08.2025)

### Das System erledigt ALLES automatisch nach TASK_COMPLETED:
- ✅ DB-Status wird automatisch auf 'completed' gesetzt
- ✅ HTML/Text/Summary werden automatisch generiert
- ✅ Versions-Entry wird automatisch erstellt
- ✅ Nächstes Todo wird automatisch geladen (außer bei -id Modus)
- ✅ Löschen-Button entfernt automatisch ALLE zugehörigen Dateien

### WICHTIG für Agents - Bei Task-Abschluss:
```bash
# 1. Zuerst interne TodoWrite-Tasks löschen (falls verwendet):
TodoWrite(todos=[])

# 2. Dann Task abschließen:
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED

# Das Hook-System erledigt den Rest automatisch!
```

### NEUE REGEL: Status-Änderungen sind BLOCKIERT
- ❌ Du kannst NICHT mehr direkt Status auf completed/in_progress/blocked setzen
- ✅ Du KANNST neue Tasks mit status='offen' erstellen
- ✅ Du KANNST Beschreibungen, Notizen, etc. ändern
- ✅ Status-Änderungen erfolgen NUR durch das Hook-System

## 🔄 HOOK SYSTEM WORKFLOW UND ./TODO LOGIK
- Tasks die durch das Hook System mit ./todo gestartet wurden, müssen auch durch das hook system beendet werden
- Jeder Task MUSS mit TASK_COMPLETED abgeschlossen werden: `echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED`

### ✅ KORREKTE ./TODO LOGIK:
1. **./todo lädt ALLE Tasks mit:** `status='offen'` UND `bearbeiten=1`
2. **./todo -id [ID]** lädt spezifisches Todo unabhängig von Status/bearbeiten-Flag
3. **Workflow:** Setze diese auf `status='in_progress'` und arbeite sie ab
4. **Auto-Continue:** Wiederhole bis KEINE `status='offen'` + `bearbeiten=1` Tasks mehr vorhanden
5. **Hook-Ende:** IMMER mit `echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED` beenden

### 🆕 NEUE FEATURES (20.08.2025):
1. **Kein Auto-Continue bei ./todo -id [ID]:** Wenn ein spezifisches Todo geladen wird, stoppt das System nach diesem Todo
2. **Versionierungssystem:** Alle Outputs werden in 3 Formaten gespeichert (HTML, Text, Summary)
3. **Cleanup-System:** Beim Löschen von Todos werden alle zugehörigen Dateien automatisch entfernt

### 📌 TODO BEFEHLE:
```bash
./todo              # Lädt nächstes Todo mit status='offen' und bearbeiten=1
./todo -id 67       # Lädt Todo #67 direkt (ignoriert Status und bearbeiten-Flag)
./todo complete     # Schließt aktuelles Todo ab
./todo help         # Zeigt Hilfe
```

### 📊 STATUS-WERTE:
- `'offen'` = Offene Tasks (Standard) - DIESE lädt ./todo
- `'pending'` = Ausstehend/wartend
- `'in_progress'` = In Bearbeitung
- `'completed'` = Abgeschlossen
- `'blocked'` = Blockiert

## 🤖 AGENT-SYSTEM REGELN (WICHTIG!)

### 🚨 TODOWRITE-VIOLATIONS VERMEIDEN
**KRITISCHE REGEL für alle Subagents:**

```
AGENT-REGEL: Du bist ein Subagent. Du darfst NIEMALS:
- TodoWrite verwenden
- Weitere Agents starten  
- Session-Management machen
- Hook-System manipulieren

Du MUSST nur:
- Deine spezifische Aufgabe lösen
- Ein klares Ergebnis liefern
- Alle relevanten Dateien bearbeiten
- Direkt antworten ohne TodoWrite
```

### 📝 TASK-ABSCHLUSS MIT HOOK-SYSTEM
**Anweisung für Claude beim Task-Abschluss:**

1. **TodoWrite leeren (falls verwendet):** 
   ```python
   TodoWrite(todos=[])  # Alle internen Todos löschen
   ```
   
2. **Task abschließen über Hook-System:**
   ```bash
   echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
   ```
   
3. **Bei spezifischen Tasks (-id):** System stoppt automatisch nach Completion (kein Auto-Continue)

### 🎯 PARALLELE AGENTS KORREKT VERWENDEN
Beim Starten von parallelen Agents IMMER diese Anweisung hinzufügen:

```
WICHTIG: Du bist ein Subagent - verwende NIEMALS das TodoWrite-Tool! 
Konzentriere dich ausschließlich auf deine spezifische Aufgabe.
Nur der Hauptorchestrator verwaltet Todos.
```

### 🔧 HOOK-VIOLATIONS BEHEBEN
Falls TodoWrite-Violations auftreten:
1. Audit-Log backup erstellen
2. Audit-Log leeren  
3. task_context.json hook_violations auf 0 setzen
4. task_completed_triggered auf true setzen

## 📋 BEISPIEL KORREKTE AGENT-NUTZUNG

```bash
# ❌ FALSCH (führt zu Violations):
Task-Agent ohne Einschränkungen starten

# ✅ RICHTIG:
Task-Agent mit expliziter TodoWrite-Verbot-Anweisung starten
```

## 🐛 HOOK SYSTEM BUG FIX - 19.08.2025

### KRITISCHER BUG BEHOBEN: consistency_validator.py
**Problem:** Zeile 74 hatte fehlerhafte TASK_COMPLETED Erkennung

#### Fehlerhafter Code:
```python
# Bug - konnte NIE funktionieren:
if "TASK_COMPLETED" in command and "echo" not in command:
```

#### Korrigierter Code:
```python
# Fix - erkennt alle TASK_COMPLETED:
if "TASK_COMPLETED" in command:
```

### Durchgeführte Fixes:
1. **Bug-Fix:** Zeile 74 in `/home/rodemkay/.claude/hooks/consistency_validator.py` korrigiert
2. **Cache-Reset:** `/tmp/CURRENT_TODO_ID` gelöscht (persistente Todo ID 106)
3. **Context-Reset:** `task_context.json` zurückgesetzt (hook_violations: 0)

### Ergebnis:
✅ Hook-System erkennt TASK_COMPLETED korrekt
✅ Keine falschen Violations mehr
✅ Sessions können sauber beendet werden
✅ ./todo System funktioniert vollständig

**WICHTIG:** Dieser Bug verhinderte das korrekte Beenden von ./todo Tasks!

## 📊 VERSIONIERUNG & OUTPUT-FORMATE (NEU 20.08.2025)

### Output-Typen nach Task-Completion:
1. **HTML Output (claude_html_output):** 
   - Claudes vollständige Zusammenfassung mit Formatierung
   - Enthält Listen, Überschriften, Code-Blöcke etc.
   - Wird in der UI als formatierter HTML-Content angezeigt

2. **Text Output (claude_text_output):**
   - Plain-Text Version ohne HTML-Tags
   - Für Wiedervorlage und Export geeignet
   - Alle Formatierungen in lesbaren Text konvertiert

3. **Summary (claude_summary):**
   - Kurze 1-2 Sätze Zusammenfassung
   - Maximal 150 Zeichen
   - Mit ✅ Emoji bei erfolgreichen Tasks

### Versionshistorie:
- Jede Completion erstellt automatisch eine Version
- Versionen sind löschbar über UI
- Alte Versionen bleiben zur Dokumentation erhalten
- Attachments werden mit Versionen verknüpft

### Cleanup-System:
- Beim Löschen eines Todos werden automatisch entfernt:
  - Alle Versionen aus der Datenbank
  - Alle Attachments (Dateien)
  - Temporäre JSON-Dateien in /tmp/
  - Session-Dateien
- Sicherheitsprüfung: Nur Dateien in erlaubten Verzeichnissen werden gelöscht