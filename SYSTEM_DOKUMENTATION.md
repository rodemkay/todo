# 📚 TODO SYSTEM - UMFASSENDE DOKUMENTATION

## 🎯 SYSTEMÜBERSICHT

Das **WordPress Todo System** ist eine integrierte Lösung zur Aufgabenverwaltung, die WordPress, Claude CLI und automatisches Monitoring verbindet.

### Kernkomponenten:
1. **WordPress Plugin** - Web-Interface für Todo-Verwaltung
2. **Claude CLI Integration** - Automatische Aufgabenbearbeitung
3. **Intelligent Monitor** - 24/7 Überwachung und Automation
4. **Remote Control** - Webhook-basierte Kommunikation

---

## 🏗️ ARCHITEKTUR

```
┌─────────────────────────────────────────────────────┐
│                  HETZNER SERVER                      │
│  ┌─────────────────────────────────────────────┐    │
│  │     WordPress Todo Plugin (PHP)              │    │
│  │  • Dashboard UI                              │    │
│  │  • Datenbank (stage_project_todos)          │    │
│  │  • Remote Control Webhook                   │    │
│  └──────────────┬──────────────────────────────┘    │
│                  │                                   │
│                  ▼ Trigger-Datei                     │
│         /uploads/claude_trigger.txt                 │
└─────────────────────┼────────────────────────────────┘
                      │
                      │ SSHFS Mount
                      ▼
┌─────────────────────────────────────────────────────┐
│                 RYZEN SERVER                         │
│  ┌─────────────────────────────────────────────┐    │
│  │     Claude CLI (tmux Session)               │    │
│  │  • ./todo Command                           │    │
│  │  • Task-Bearbeitung                         │    │
│  │  • TASK_COMPLETED Signal                    │    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │     Intelligent Monitor (Bash)              │    │
│  │  • 30-Sekunden-Checks                       │    │
│  │  • Auto-Completion                          │    │
│  │  • Qualitätskontrolle                       │    │
│  └─────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────┘
```

---

## 💾 DATENBANK-STRUKTUR

**Tabelle:** `stage_project_todos`

### Wichtigste Felder:
- `id` - Eindeutige ID
- `title` - Aufgabentitel  
- `status` - offen/in_progress/completed/blockiert
- `priority` - kritisch/hoch/mittel/niedrig
- `bearbeiten` - 1=An Claude senden, 0=Manuell
- `claude_prompt` - Anweisungen für Claude
- `mcp_servers` - JSON-Array der MCP-Server
- `agent_count` - Anzahl der Agents (0-30)
- `started_date` - Wann gestartet
- `completed_date` - Wann abgeschlossen
- `claude_html_output` - Claude's Dokumentation
- `claude_notes` - Claude's Notizen

---

## 🔄 WORKFLOW

### 1. **NEUE AUFGABE ERSTELLEN**
```
WordPress Dashboard → "Neue Aufgabe" → Formular ausfüllen
↓
Datenbank-Eintrag (status='offen', bearbeiten=1)
```

### 2. **AUFGABE AN CLAUDE SENDEN**
```
Button "An Claude senden" → Remote Control
↓
Trigger-Datei: ./todo -id [ID]
↓
Claude CLI empfängt Befehl
```

### 3. **CLAUDE BEARBEITET**
```
Claude lädt Todo-Daten → Führt Aufgabe aus
↓
Speichert Output in Datenbank
↓
Signalisiert: TASK_COMPLETED
```

### 4. **MONITORING ÜBERWACHT**
```
Alle 30 Sekunden:
• Claude aktiv? → Warten
• Unvollständige Tasks? → Reparieren
• Nächstes Todo? → Automatisch starten
```

---

## 🛠️ KRITISCHE FIXES (SESSION HISTORY)

### **Problem 1: 86% Datenverlust**
**Symptom:** Formularfelder kamen nicht in Datenbank an
**Fix:** MCP-Server-Verarbeitung vor $data Array verschoben
```php
// VORHER: Nach $data Array
// JETZT: VOR $data Array (Zeilen 264-276)
$mcp_servers = [];
if (isset($_POST['mcp_context7'])) $mcp_servers[] = 'context7';
$mcp_servers_json = json_encode($mcp_servers);
```

### **Problem 2: Class Not Found**
**Symptom:** TodoRemoteControl nicht gefunden
**Fix:** Korrekte Namespace-Verwendung
```php
// FALSCH: new TodoRemoteControl()
// RICHTIG: new Todo\Remote_Control()
```

### **Problem 3: SQL Syntax Errors**
**Symptom:** Mehrzeilige SQL in Bash fehlerhaft
**Fix:** Einzeilige SQL-Queries
```bash
# FALSCH: Multi-line
# RICHTIG: Single-line mit escaped quotes
```

---

## 🚀 INTELLIGENT MONITORING

### **Features:**
- ✅ **Keine Störung** bei aktiver Claude-Arbeit
- ✅ **Auto-Reparatur** unvollständiger Tasks
- ✅ **Prioritäts-Queue** (kritisch→hoch→mittel→niedrig)
- ✅ **Stale Detection** (>30 Min in_progress)

### **Befehle:**
```bash
./monitor start    # Monitor starten
./monitor status   # Status prüfen
./monitor follow   # Logs live verfolgen
./monitor stop     # Monitor stoppen
```

### **Monitoring-Zyklus:**
1. **Claude aktiv?** → Ja: Warten | Nein: Weiter
2. **Qualitätskontrolle** → Fehlende Felder ergänzen
3. **Stale Tasks** → Nach TASK_COMPLETED suchen
4. **Nächstes Todo** → Automatisch starten

---

## 📁 WICHTIGE DATEIEN

### **WordPress Plugin:**
- `/staging/wp-content/plugins/todo/todo.php` - Hauptdatei
- `/staging/wp-content/plugins/todo/includes/class-admin.php` - Dashboard
- `/staging/wp-content/plugins/todo/includes/class-remote-control.php` - Webhook

### **Monitoring System:**
- `intelligent_todo_monitor_fixed.sh` - Hauptskript
- `monitor` - Management-Tool
- `/tmp/intelligent_todo_monitor.log` - Logs

### **Claude CLI:**
- `todo` - CLI-Tool für Todo-Verwaltung
- `/tmp/TASK_COMPLETED` - Completion-Signal

---

## 🔍 DEBUGGING

### **Logs prüfen:**
```bash
# Monitor-Logs
tail -f /tmp/intelligent_todo_monitor.log

# WordPress Debug
tail -f /var/www/forexsignale/staging/wp-content/debug.log

# Claude Trigger
cat /uploads/claude_trigger.txt
```

### **Datenbank-Check:**
```sql
-- Aktuelle in_progress
SELECT id, title, started_date 
FROM stage_project_todos 
WHERE status='in_progress';

-- Unvollständige Tasks
SELECT id, title 
FROM stage_project_todos 
WHERE status='completed' 
AND claude_html_output IS NULL;
```

---

## ⚡ QUICK REFERENCE

### **Todo starten:**
```bash
./todo              # Nächstes Todo
./todo -id 123      # Spezifisches Todo
./todo complete     # Todo abschließen
```

### **Monitor-Kontrolle:**
```bash
./monitor start     # Automation starten
./monitor stop      # Automation stoppen
./monitor status    # Status prüfen
```

### **Manuelle Korrektur:**
```sql
-- Todo manuell abschließen
UPDATE stage_project_todos 
SET status='completed', 
    completed_date=NOW() 
WHERE id=123;
```

---

## 📊 SYSTEM-METRIKEN

- **Check-Intervall:** 30 Sekunden
- **Auto-Repair:** Letzte Stunde
- **Stale-Threshold:** 30 Minuten
- **Prioritäten:** 4 Stufen (kritisch→niedrig)
- **Field-Transmission:** 100% (nach Fix)

---

## 🎯 ZUSAMMENFASSUNG

Das System arbeitet **vollautomatisch**:
1. WordPress verwaltet Todos im Web
2. Claude CLI bearbeitet Aufgaben
3. Monitor überwacht 24/7
4. Qualität wird automatisch sichergestellt

**Keine manuelle Intervention nötig** - alles läuft selbstständig!

---

*Dokumentation erstellt: 2025-08-21*  
*System-Status: ✅ VOLL FUNKTIONSFÄHIG*