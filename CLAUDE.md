# 📋 TODO PROJECT - CLAUDE INSTRUCTIONS

## 🎯 PROJEKT-ÜBERSICHT
**Projektname:** todo (früher wp-project-todos)  
**Hauptverzeichnis:** `/home/rodemkay/www/react/todo/`  
**Plugin-Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/todo/`  
**Dokumentation:** `/home/rodemkay/www/react/todo/docs/`

## 🖥️ ENVIRONMENT & INFRASTRUKTUR

### Claude Code CLI Umgebung
1. **Claude Code läuft auf:** Ryzen Server in einem Kitty Terminal
2. **Session:** tmux Session "claude"
3. **Claude kann Befehle empfangen während der Arbeit** - Das ist normal!
4. **Working Directory:** `/home/rodemkay/www/react/todo/`

### Server-Architektur
- **Ryzen Server (Development):** 
  - Tailscale IP: `100.89.207.122`
  - Claude Code CLI läuft hier
  - Webhook Server auf Port 8089
  - Mount zu Hetzner via SSHFS

- **Hetzner Server (Production/Staging):**
  - Public IP: `159.69.157.54`
  - Tailscale IP: `100.67.210.46`
  - WordPress läuft hier
  - **WICHTIG:** Nur im `/staging/` Ordner arbeiten!

### ⚠️ KRITISCHE NETZWERK-REGEL
**NIEMALS `localhost` verwenden!** Immer die Tailscale IPs nutzen:
- Ryzen: `100.89.207.122`
- Hetzner: `100.67.210.46`

### Webhook System
- **Webhook Server:** Läuft auf Ryzen Server Port 8089
- **Endpoint:** `http://100.89.207.122:8089/webhook`
- **Empfängt Befehle:** `./todo` und `./todo -id [ID]`
- **Dokumentation:** Siehe `REMOTE_CONTROL_ARCHITECTURE.md`  

## 🚨 KRITISCHE REGELN

### 1. HOOK SYSTEM WORKFLOW
- Tasks die durch `./todo` gestartet wurden, MÜSSEN mit `TASK_COMPLETED` beendet werden
- Befehl: `echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED`
- NIEMALS Tasks offen lassen oder Session beenden ohne TASK_COMPLETED

### 2. TODOWRITE VERWENDUNG
- NUR für echte Datenbank-Todos mit numerischen IDs verwenden
- NIEMALS für interne Planung oder temporäre Notizen
- Bei Subagents: IMMER explizit verbieten TodoWrite zu verwenden

### 3. CLAUDE TOGGLE SYSTEM
- Jede Aufgabe hat INDIVIDUELLEN Claude Toggle (❌ Claude / ✓ Claude)
- KEIN globaler Toggle-Button
- Toggle beeinflusst ob Task an Claude gesendet wird

## 📂 VERZEICHNISSTRUKTUR

```
/home/rodemkay/www/react/todo/
├── docs/                    # Dokumentation & Screenshots
│   ├── screenshots/         # UI-Referenzbilder
│   └── IMPLEMENTATION_PLAN.md
├── plugin/                  # WordPress Plugin Code
├── hooks/                   # Hook-System
├── cli/                     # CLI-Tools (./todo)
├── tests/                   # Playwright Tests
└── scripts/                 # Utility Scripts
```

## 🖥️ UI/UX REFERENZ

### Dashboard (todo-dashboard-ziel.png)
- Filter-Buttons: Alle, Offen, In Bearbeitung, Abgeschlossen, Blockiert, ⏰ Cron
- Claude Toggle PRO ZEILE als ❌/✓ Button
- Bulk-Actions für mehrere Tasks
- Aktions-Buttons: An Claude, Edit, Wiedervorlage, Output, Löschen

### Neue Aufgabe (todo-newtask-*.png)
- Arbeitsverzeichnis-Dropdown mit vordefinierten Pfaden
- Status & Priorität als Button-Gruppen
- Projekt-Auswahl Buttons
- Entwicklungsbereich-Tabs (Frontend, Backend, Full-Stack, DevOps, Design)
- Multi-Agent System Konfiguration (0-30 Agents)
- MCP Server Integration Checkboxen
- Speichern ohne Redirect Option

## 🔧 TECHNISCHE DETAILS

### Datenbank
- **Name:** staging_forexsignale
- **Prefix:** stage_
- **Haupttabelle:** stage_project_todos
- **User:** ForexSignale
- **phpMyAdmin:** https://forexsignale.trade/staging/phpmyadmin

### SSH-Zugang
- **Host:** 159.69.157.54 (oder Tailscale: 100.67.210.46)
- **User:** rodemkay
- **WordPress Staging:** /var/www/forexsignale/staging/
- **Plugin-Verzeichnis:** /var/www/forexsignale/staging/wp-content/plugins/todo/

### Mount Points (Ryzen Server)
- **Hetzner Mount:** /home/rodemkay/www/react/mounts/hetzner/
- **Staging Mount:** /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/
- **Live Mount:** /home/rodemkay/www/react/mounts/hetzner/forexsignale/ (READ-ONLY!)

### CLI-Befehle
```bash
./todo              # Nächstes Todo laden (status='offen', bearbeiten=1)
./todo -id 67       # Spezifisches Todo #67 laden
./todo complete     # Aktuelles Todo abschließen
./todo list         # Alle Todos anzeigen
./todo status       # Aktuellen Status zeigen
```

## 🐛 BEKANNTE PROBLEME & FIXES

### 1. Save ohne Redirect
- Problem: Speichern-Button leitet immer weiter
- Fix: AJAX-Handler implementieren für save_without_redirect

### 2. Claude Toggle Visibility
- Problem: Toggle erscheint als globaler Button
- Fix: Individual-Toggles pro Zeile mit AJAX

### 3. Hook Violations
- Problem: TodoWrite mit non-numeric IDs
- Fix: `/home/rodemkay/www/react/todo/hooks/clear_violations.sh reset`

## 🚀 DEPLOYMENT

### Sync zum Staging
```bash
rsync -avz /home/rodemkay/www/react/todo/plugin/ \
  rodemkay@159.69.157.54:/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/
```

### Testing
```bash
# Playwright Tests ausführen
cd /home/rodemkay/www/react/todo
npm test
```

## 📊 WICHTIGE DATEIEN

### Plugin Core
- `plugin/wp-project-todos.php` - Haupt-Plugin-Datei
- `plugin/includes/class-admin.php` - Dashboard-Logik
- `plugin/templates/wsj-dashboard.php` - Dashboard-Template
- `plugin/admin/new-todo.php` - Neue Aufgabe Seite

### Hook System
- `hooks/consistency_validator.py` - Session-Validierung
- `hooks/clear_violations.sh` - Violations bereinigen
- `cli/todo` - Haupt-CLI-Script

## ⚠️ WICHTIGE HINWEISE

1. **IMMER** Änderungen mit Playwright testen
2. **NIEMALS** direkt auf Production deployen
3. **BACKUP** vor größeren Änderungen
4. **TASK_COMPLETED** nicht vergessen bei ./todo Tasks
5. **Claude Toggle** ist PRO TASK, nicht global

## 🎯 AKTUELLE PRIORITÄTEN

1. ✅ Verzeichnisstruktur migriert
2. ⏳ Claude Toggle als Individual-Buttons implementieren
3. ⏳ Arbeitsverzeichnis-Dropdown funktionsfähig machen
4. ⏳ Save ohne Redirect reparieren
5. ⏳ CRON-Tasks vollständig integrieren
6. ⏳ Hook-System stabilisieren

---

**Letzte Aktualisierung:** 2025-08-20  
**Version:** 1.0.0