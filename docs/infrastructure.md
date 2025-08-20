# WP PROJECT TODOS - INFRASTRUKTUR DOKUMENTATION

## 📋 SYSTEM-ÜBERSICHT

Das WP Project Todos System ist eine komplexe WordPress-Plugin-Infrastruktur mit Remote Control über Claude CLI und Hook System Integration.

### 🏗️ ARCHITEKTUR-KOMPONENTEN

#### 1. **WordPress Plugin Structure**
```
wp-project-todos/
├── includes/
│   ├── class-admin.php           # Admin Interface & Dashboard
│   ├── class-todo-model.php      # Datenbank-Model & Queries  
│   └── class-remote-control.php  # Claude CLI Integration
├── templates/
│   └── wsj-dashboard.php         # WSJ-Style Dashboard Template
├── scripts/
│   ├── documentation_template.sh # Auto-Dokumentation
│   └── change_detector.sh        # File Change Detection
└── wp-project-todos.php          # Main Plugin File
```

#### 2. **Mount Points & Dateizugriff**
- **Hetzner Staging Mount:** `/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/`
- **Plugin Pfad:** `wp-content/plugins/wp-project-todos/`
- **Direct File Access:** Über Mount für Entwicklung und Debugging

## 🎨 WSJ-DASHBOARD TEMPLATE SYSTEM

### **Datei:** `/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/templates/wsj-dashboard.php`

#### **Design-System:**
- **Wall Street Journal Style:** Professionelle Farben und Typography
- **CSS-Klassen:** 
  - `wsj-filter-btn` für alle Filter-Buttons
  - `wsj-card` für Karten-Layout
  - `wsj-button` für Action-Buttons

#### **Button-Implementierung (Zeilen 194-195):**

##### **CRON Button:**
```php
<button class="wsj-filter-btn" onclick="filterTodos('recurring')">
    ⏰ CRON (<?php echo $recurring_count; ?>)
</button>
```
- **Styling:** Lila Gradient
- **Funktion:** Zeigt wiederkehrende Tasks (is_recurring=1)
- **Counter:** Dynamische Anzahl der CRON-Tasks

##### **Claude Toggle Button:**
```php
<button class="wsj-filter-btn" id="claude-toggle-all">
    🤖 Claude Toggle
</button>
```
- **Styling:** Pink-Lila Gradient  
- **Funktion:** Massenänderung der bearbeiten-Flags
- **JavaScript:** Event-Handler für AJAX-Request

## 🔧 FILTER-SYSTEM

### **Standard-Filter:**
1. **Alle:** Zeigt alle Todos unabhängig vom Status
2. **Offen:** status='offen' 
3. **In Bearbeitung:** status='in_progress'
4. **Abgeschlossen:** status='completed'
5. **Blockiert:** status='blocked'

### **Erweiterte Filter:**
6. **CRON:** is_recurring=1 (Wiederkehrende Tasks)
7. **Claude Aktiv:** bearbeiten=1 (Claude kann bearbeiten)

### **Filter-Implementierung:**
```javascript
function filterTodos(status) {
    // GET-Parameter für PHP-Backend
    window.location.href = '?page=wp-project-todos&filter_status=' + status;
}
```

## 🤖 HOOK SYSTEM INTEGRATION

### **Hook-Verzeichnis:** `/home/rodemkay/.claude/hooks/`

#### **Wichtige Hook-Dateien:**
- `consistency_validator.py` - Validiert Todo-Operationen
- `session_manager.py` - Session-Management  
- `audit_logger.py` - Logging und Debugging

#### **Bug-Fix (19.08.2025):**
**Datei:** `/home/rodemkay/.claude/hooks/consistency_validator.py`
**Zeile 74 - Kritischer Fix:**

```python
# ❌ FEHLERHAFT (konnte nie funktionieren):
if "TASK_COMPLETED" in command and "echo" not in command:

# ✅ KORRIGIERT (erkennt alle TASK_COMPLETED):  
if "TASK_COMPLETED" in command:
```

**Auswirkung:** Hook-System erkennt jetzt TASK_COMPLETED korrekt und beendet Sessions sauber.

## 🔄 ./TODO SYSTEM WORKFLOW

### **Befehls-Syntax:**
```bash
./todo              # Lädt nächstes Todo mit status='offen' UND bearbeiten=1
./todo -id 67       # Lädt spezifisches Todo (ignoriert Status/bearbeiten)  
./todo complete     # Schließt aktuelles Todo ab
./todo help         # Zeigt Hilfe
```

### **Status-Management:**
- `'offen'` - Offene Tasks (wird von ./todo geladen)
- `'pending'` - Ausstehend/wartend
- `'in_progress'` - In Bearbeitung (wird von Claude gesetzt)
- `'completed'` - Abgeschlossen
- `'blocked'` - Blockiert

### **Auto-Continue Logic:**
1. System lädt ALLE Tasks mit `status='offen'` UND `bearbeiten=1`
2. Setzt Status auf `'in_progress'` 
3. Arbeitet Tasks nacheinander ab
4. Wiederholt bis keine offenen Tasks mehr vorhanden
5. Beendet mit `echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED`

## 💾 DATENBANK-SCHEMA

### **Tabelle:** `stage_project_todos`

#### **Standard-Spalten:**
- `id` (AUTO_INCREMENT PRIMARY KEY)
- `title` (VARCHAR 255)
- `description` (TEXT)
- `status` (ENUM: offen, pending, in_progress, completed, blocked)
- `priority` (INT)
- `scope` (VARCHAR 100)
- `bearbeiten` (TINYINT 1) - Claude-Bearbeitung aktiviert

#### **Wiederkehrende Tasks (18.08.2025):**
- `is_recurring` (TINYINT 1) - Markiert wiederkehrende Tasks
- `recurring_type` (VARCHAR 50) - Art der Wiederholung  
- `last_executed` (DATETIME) - Letzte Ausführung

#### **Debugging-Spalten:**
- `claude_notes` (TEXT) - Claude-Notizen
- `bemerkungen` (TEXT) - Allgemeine Bemerkungen
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## 🛠️ ENTWICKLUNGSTOOLS

### **Automatisierung Scripts:**

#### **1. Documentation Template (`scripts/documentation_template.sh`):**
```bash
#!/bin/bash
# Erstellt strukturierte Session-Dokumentation
# Analysiert geänderte Dateien und generiert Changelog
```

#### **2. Change Detector (`scripts/change_detector.sh`):**
```bash
#!/bin/bash  
# Überwacht wichtige Dateien auf Änderungen
# Erstellt Diff-Reports für Modified Files
```

### **Wiederkehrende System-Tasks:**
1. **Session-Dokumentation erstellen**
2. **Änderungs-Detektor ausführen**  
3. **Plugin-Änderungen dokumentieren**
4. **.env Updates prüfen**

## 🔐 SICHERHEIT & ZUGRIFF

### **Remote Control Sicherheit:**
- **Nonce-Validation:** WordPress-Standard für AJAX-Requests
- **Permission-Checks:** current_user_can('manage_options')
- **SSH-Key-Authentication:** Für Server-Zugriff

### **File-System Zugriff:**
- **Read-Only Mounts:** Für sichere Dateieinsicht
- **Write-Access:** Nur über authentifizierte Endpoints
- **Backup-System:** Automatische Sicherung vor Änderungen

## 📊 MONITORING & LOGGING

### **Log-Dateien:**
- `/tmp/claude_trigger.log` - Trigger-System Logs
- `/tmp/task_context.json` - Hook-System Context
- `/tmp/TASK_COMPLETED` - Task-Completion Signal

### **Performance-Metriken:**
- **Hook-Violations:** Tracking von TodoWrite-Violations
- **Session-Dauer:** Automatische Session-Zeiterfassung
- **Task-Completion-Rate:** Erfolgreiche vs. fehlerhafte Tasks

## 🚀 ZUKÜNFTIGE ERWEITERUNGEN

### **Geplante Features:**
1. **API-Endpoints:** REST-API für externe Integrations
2. **Notification-System:** Email/Slack bei Task-Updates
3. **Analytics-Dashboard:** Produktivitäts-Metriken
4. **Multi-User-Support:** Team-Kollaboration
5. **Mobile-App-Integration:** React Native App

### **Technische Verbesserungen:**
1. **Redis-Caching:** Für bessere Performance
2. **WebSocket-Support:** Real-time Updates
3. **GraphQL-API:** Moderne Daten-Abfrage
4. **Docker-Integration:** Container-basierte Entwicklung

---

## 📞 SUPPORT & WARTUNG

### **Wichtige Kontakte:**
- **Entwicklung:** Claude Code CLI Integration
- **Server:** Hetzner VPS (159.69.157.54)
- **Mount-System:** Tailscale Network

### **Regelmäßige Wartungsaufgaben:**
1. **Hook-System Updates:** Monatlich
2. **Database-Cleanup:** Wöchentlich  
3. **Log-Rotation:** Täglich
4. **Security-Updates:** Bei Verfügbarkeit

### **Troubleshooting:**
```bash
# Hook-System Status prüfen:
ps aux | grep consistency_validator

# Mount-Points verifizieren:
ls -la /home/rodemkay/www/react/mounts/

# Plugin-Status prüfen:
wp plugin status wp-project-todos --path=/var/www/forexsignale/staging
```

**Letzte Aktualisierung:** 20.08.2025 - Hook System Bug-Fix implementiert