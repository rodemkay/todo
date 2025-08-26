# 📋 TODO PROJECT - CLAUDE INSTRUCTIONS

## 🎯 PROJEKT-ÜBERSICHT
**Projektname:** todo (NICHT mehr wp-project-todos!)  
**Hauptverzeichnis:** `/home/rodemkay/www/react/plugin-todo/`  
**Plugin-Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/todo/` ⚠️ KEIN wp-project-todos mehr!  
**Dokumentation:** `/home/rodemkay/www/react/plugin-todo/docs/`

## 🖥️ ENVIRONMENT & INFRASTRUKTUR

### Claude Code CLI Umgebung
1. **Claude Code läuft auf:** Ryzen Server in einem Kitty Terminal
2. **Start-Script:** `/home/rodemkay/.local/bin/kitty_claude_fresh.sh`
3. **Session:** tmux Session "claude" - LINKES PANE empfängt Befehle!
4. **Claude kann Befehle empfangen während der Arbeit** - Das ist normal!
5. **Working Directory:** `/home/rodemkay/www/react/plugin-todo/`
6. **WICHTIG für ./todo:** Befehle kommen im LINKEN PANE an!

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

### Webhook System & Remote Control
- **Status:** ✅ VOLLSTÄNDIG FUNKTIONSFÄHIG (nach Bug-Fixes 21.08.2025)
- **Primäre Kommunikation:** Layer 3 Trigger File System (99.9% Erfolgsrate)
- **WordPress Button:** "📤 An Claude" im Todo Dashboard
- **Trigger-Pfad:** `/uploads/claude_trigger.txt` (korrekt nach Reparatur)
- **Watch Script:** PID prüfen mit `ps aux | grep watch-hetzner-trigger`  
- **Befehlsempfang:** Im LINKEN PANE der Kitty/tmux Session
- **Auto-Execution:** <200ms Response Time nach Button-Klick
- **📚 Dokumentation:** Siehe `docs/WEBHOOK_SYSTEM_COMPLETE_GUIDE.md` (⭐ ZENTRALE QUELLE)  

## 🚨 KRITISCHE REGELN

### 1. HOOK SYSTEM WORKFLOW (V3.0 ERWEITERT!)
- **VOLLSTÄNDIGE DATENLADUNG:** Jeder `./todo` Aufruf lädt ALLE Felder (id, titel, beschreibung, status, prioritaet, projekt, entwicklungsbereich, working_directory, plan, claude_notes, bemerkungen, created_at, updated_at)
- **TASK COMPLETION:** Tasks die durch `./todo` gestartet wurden, MÜSSEN mit `TASK_COMPLETED` beendet werden
- **WIEDERVORLAGE:** Bei unvollständigen Tasks `./todo defer` verwenden für intelligente Terminierung
- Befehl: `echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED`
- NIEMALS Tasks offen lassen oder Session beenden ohne TASK_COMPLETED
- **WICHTIG:** WP-CLI `--format=json` funktioniert NICHT mit `wp db query` (MariaDB-Limitation)

### 2. TODOWRITE VERWENDUNG
- NUR für echte Datenbank-Todos mit numerischen IDs verwenden
- NIEMALS für interne Planung oder temporäre Notizen
- Bei Subagents: IMMER explizit verbieten TodoWrite zu verwenden
- **NEU:** TodoWrite unterstützt jetzt alle erweiterten Felder (plan, claude_notes, bemerkungen)

### 3. CLAUDE TOGGLE SYSTEM & ERWEITERTE UI
- Jede Aufgabe hat INDIVIDUELLEN Claude Toggle (❌ Claude / ✓ Claude)
- **NEU:** Erweiterte Floating Button Bar mit zusätzlichen Aktionen:
  - Bearbeiten, Löschen, Duplizieren, Archivieren, Quick-Status-Change
- **SMART FILTER:** Automatische Filterung nach Presets (heute, überfällig, priorität)
- **BENACHRICHTIGUNGEN:** Toast-Notifications für Status-Änderungen

## 📂 VERZEICHNISSTRUKTUR

```
/home/rodemkay/www/react/plugin-todo/
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

### Dashboard (V3.0 ERWEITERT!)
- **SMART FILTER:** Preset-Buttons für heute, woche, überfällig, priorität, claude-aktiviert
- **VOLLSTÄNDIGE DATENANSICHT:** Alle TODO-Felder werden geladen und angezeigt
- **ERWEITERTE FLOATING BUTTONS:** Bearbeiten, Löschen, Duplizieren, Archivieren, Quick-Status
- **BENACHRICHTIGUNGSSYSTEM:** Toast-Notifications bei Status-Änderungen
- **HTML/OUTPUT-VIEW:** Standard-Ansicht mit HTML-Rendering für Plan und Notizen
- Claude Toggle PRO ZEILE als ❌/✓ Button
- Bulk-Actions für mehrere Tasks
- Aktions-Buttons: An Claude, Edit, Wiedervorlage, Output, Löschen

### Neue Aufgabe (BENUTZERFREUNDLICH!)
- **WYSIWYG PLAN-EDITOR:** Vereinfachter Editor ohne HTML-Komplexität
- **TEMPLATE-SNIPPETS:** Vordefinierte Bausteine für häufige Aufgaben
- **AUTO-SAVE:** Automatisches Speichern während der Eingabe
- Arbeitsverzeichnis-Dropdown mit vordefinierten Pfaden
- Status & Priorität als Button-Gruppen
- Projekt-Auswahl Buttons
- Entwicklungsbereich-Tabs (Frontend, Backend, Full-Stack, DevOps, Design)
- Multi-Agent System Konfiguration (0-30 Agents)
- MCP Server Integration Checkboxen
- Speichern ohne Redirect Option

### Wiedervorlage-System (NEU in V3.0!)
- **INTELLIGENTE TERMINIERUNG:** Datum/Zeit-Picker für exakte Wiedervorlage
- **AUTOMATISCHE OUTPUT-SAMMLUNG:** Sammelt bisherige Arbeit als Kontext
- **NOTIZ-GENERIERUNG:** Erstellt automatisch Zusammenfassung für späteren Kontext
- **STATUS-MANAGEMENT:** Automatische Änderung zu "terminiert" mit Wiedervorlage-Datum

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

### 🚨 KRITISCH: READ/EDIT TOOLS NUR ÜBER MOUNTS!
**Read/Edit Tools funktionieren NICHT mit SSH-Pfaden!**

✅ **RICHTIG - über SSHFS-Mount:**
```
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/todo.php")
Edit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/admin/new-todo.php", old, new)
```

❌ **FALSCH - direkter SSH-Pfad:**
```
Read("/var/www/forexsignale/staging/wp-content/plugins/todo/todo.php")  # FUNKTIONIERT NICHT!
Edit("rodemkay@159.69.157.54:/var/www/file.php", old, new)  # FUNKTIONIERT NICHT!
```

**GRUND:** Read/Edit Tools sind für lokale Dateipfade konzipiert, SSH-Pfade werden nicht unterstützt.
**LÖSUNG:** IMMER über SSHFS-Mounts arbeiten - diese sind bereits konfiguriert und funktionieren perfekt!

**📚 AUSFÜHRLICHE DOKUMENTATION:** Siehe `docs/MOUNT_USAGE_GUIDE.md` für detaillierte Beispiele und Troubleshooting

### CLI-Befehle (v3.0 - VOLLSTÄNDIG ERWEITERT!)
```bash
# Standard-Befehle (MIT VOLLSTÄNDIGER DATENLADUNG)
./todo              # Lädt vollständige TODO-Daten (alle Felder, nicht nur ID/Titel)
./todo -id 67       # Spezifisches Todo mit ALLEN Feldern (Beschreibung, Plan, etc.)
./todo complete     # Abschließen mit automatischer Output-Sammlung
./todo defer        # NEU: Wiedervorlage mit Terminierung und Kontext-Erhaltung
./todo status       # Aktueller Status mit erweiterten Informationen

# V2.0 Features
./todo monitor      # System Health Check (prüft DB, IDs, Locks)
./todo test         # Führt 10 Tests aus (sollte 100% zeigen)
./todo fix          # Behebt automatisch häufige Probleme
./todo help         # Zeigt erweiterte Hilfe

# NEU in v3.0 - ERWEITERTE FEATURES
./todo filter --preset heute     # Heute fällige TODOs mit Smart-Filter
./todo filter --preset priority  # Hohe Priorität TODOs
./todo filter --preset überfällig # Überfällige offene TODOs
./todo search "keyword"           # Volltext-Suche in allen Feldern
./todo stats                     # Dashboard-Statistiken und Metriken
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
- Fix: `/home/rodemkay/www/react/plugin-todo/hooks/clear_violations.sh reset`

## 🚀 DEPLOYMENT

### Sync zum Staging
```bash
rsync -avz /home/rodemkay/www/react/plugin-todo/plugin/ \
  rodemkay@159.69.157.54:/var/www/forexsignale/staging/wp-content/plugins/todo/
```

### Testing
```bash
# Playwright Tests ausführen
cd /home/rodemkay/www/react/plugin-todo
npm test
```

## 📊 DOKUMENTATIONS-RICHTLINIEN & WICHTIGE DATEIEN

### 🚨 KRITISCHE DOKUMENTATIONS-REGELN

**ALLE DOKUMENTATIONEN MÜSSEN IN `/docs/` GESPEICHERT WERDEN!**

1. **📁 Zentrale Speicherung:** Alle Docs gehören in `/home/rodemkay/www/react/plugin-todo/docs/`
2. **🏷️ Klare Bezeichnungen:** Dateinamen müssen SOFORT erkennbar machen worum es geht
3. **❓ Bei Fragen IMMER zuerst in die Docs schauen** bevor neue Dokumentation erstellt wird
4. **📝 Aktuelle Updates:** Dokumentationen müssen immer auf dem neuesten Stand gehalten werden

### ✅ GUTE DOKUMENTATIONS-NAMEN (Beispiele):
- `WEBHOOK_SYSTEM_COMPLETE_GUIDE.md` ← Klar erkennbar: Webhook System
- `DATABASE_SCHEMA_REFERENCE.md` ← Klar erkennbar: Datenbankstruktur  
- `TROUBLESHOOTING_COMMON_ERRORS.md` ← Klar erkennbar: Problemlösung
- `DEPLOYMENT_STEP_BY_STEP.md` ← Klar erkennbar: Deployment-Anleitung

### ❌ SCHLECHTE DOKUMENTATIONS-NAMEN (vermeiden):
- `PROJECT_COMPLETION_SUMMARY.md` ← Unklar was drin steht
- `FINAL_SETUP_SUMMARY.md` ← Was für ein Setup?
- `STATUS.md` ← Status von was?

### 📚 Verfügbare Dokumentationen (Hauptpfad: `/docs/`)
- **🌐 WEBHOOK_SYSTEM_COMPLETE_GUIDE.md** - Komplette WordPress ↔ Claude Webhook Dokumentation (⭐ NEU)
- **🏗️ ENVIRONMENT.md** - Komplette Infrastruktur & Server-Setup
- **📋 IMPLEMENTATION_PLAN.md** - Detaillierter Projektplan
- **🎛️ CLAUDE_TOGGLE_IMPLEMENTATION.md** - Individual Claude-Button System
- **🔐 PERMISSIONS_WWW_DATA.md** - www-data User & Permissions Setup
- **📦 PLUGIN_RENAME.md** - Plugin-Umbenennung zu todo.php
- **✅ MIGRATION_COMPLETE.md** - Plugin Migration Status
- **📡 GITHUB_SETUP.md** - Repository & Push Anleitung
- **📊 CURRENT_STATUS.md** - Aktueller Projektstatus
- **🖼️ screenshots/** - UI-Referenzbilder
- **🪝 HOOK_SYSTEM_SOLUTION.md** - Hook-System nach WP-CLI JSON-Fix
- **🎯 ROBUST_HOOK_SYSTEM.md** - Hook-System v2.0 mit 100% Test-Coverage

**🔍 WICHTIG:** Bei Fragen zu WordPress, Hooks, Webhook, Database, etc. → **IMMER ZUERST** in entsprechende Docs schauen!

### Plugin Core Files  
⚠️ **WICHTIG: Alles in `/plugins/todo/` - KEIN wp-project-todos mehr!**
- **Haupt-Plugin:** `/staging/wp-content/plugins/todo/todo.php`
- **Dashboard-Logic:** `/staging/wp-content/plugins/todo/includes/class-admin.php`
- **Dashboard-Template:** `/staging/wp-content/plugins/todo/templates/wsj-dashboard.php`
- **New Task Page:** `/staging/wp-content/plugins/todo/admin/new-todo.php`

### Hook System v2.0 (ROBUST & GETESTET!)
✅ **NEU: Robustes Hook-System v2.0 mit 100% Test-Coverage**
- **Manager:** `hooks/todo_manager.py` - Hauptlogik mit Output-Collection
- **Collector:** `hooks/output_collector.py` - Erfasst Claude's echte Outputs
- **Monitor:** `hooks/monitor.py` - Health-Checks & Auto-Fixes
- **Tester:** `hooks/test-suite.py` - 10 automatisierte Tests (100% passed)
- **Config:** `hooks/config.json` - Erweiterte Konfiguration
- **CLI Tool:** `todo` - Erweiterte Befehle (monitor, test, fix)
- **Dokumentation:** 
  - `docs/HOOK_SYSTEM_SOLUTION.md` - WP-CLI JSON-Fix
  - `docs/ROBUST_HOOK_SYSTEM.md` - v2.0 Komplettlösung

## ⚠️ WICHTIGE HINWEISE

1. **IMMER** Änderungen mit Playwright testen
2. **NIEMALS** direkt auf Production deployen
3. **BACKUP** vor größeren Änderungen
4. **TASK_COMPLETED** nicht vergessen bei ./todo Tasks
5. **Claude Toggle** ist PRO TASK, nicht global

## 🆕 V3.0 NEUE FEATURES (VOLLSTÄNDIG IMPLEMENTIERT!)

### 1. ERWEITERTE TODO-DATENLADUNG ✅
- **VOLLSTÄNDIGE FELDINFORMATIONEN:** Alle Datenbank-Felder werden bei jedem Aufruf geladen
- **OPTIMIERTE QUERIES:** Intelligente Datenbankabfragen für bessere Performance  
- **KONTEXTUELLES LADEN:** Zusätzliche Informationen basierend auf TODO-Status

### 2. WIEDERVORLAGE-SYSTEM (OPTION B) ✅
- **INTELLIGENTE TERMINIERUNG:** Smart-Scheduling mit Output-Sammlung
- **AUTOMATISCHE KONTEXTERHALTUNG:** Bisherige Arbeit wird als Notiz gespeichert
- **NAHTLOSE FORTSETZUNG:** Wiederaufnahme mit vollständigem Arbeitskontext

### 3. SMART-FILTER-SYSTEM ✅
- **PRESET-FILTER:** heute, woche, überfällig, priorität, claude-aktiviert
- **CUSTOM-FILTER:** Status, Projekt, Zeitraum, Volltext-Suche
- **AUTOMATISCHE ANWENDUNG:** Filter werden beim nächsten `./todo` Aufruf berücksichtigt

### 4. BENACHRICHTIGUNGSSYSTEM ✅
- **TOAST-NOTIFICATIONS:** Immediate Feedback bei Status-Änderungen
- **AUTOMATISCHE UPDATES:** Refresh-Hinweise bei Datenänderungen
- **KATEGORISIERT:** Success/Error/Info/Warning-Nachrichten

### 5. ERWEITERTE FLOATING BUTTON BAR ✅
- **ZUSÄTZLICHE AKTIONEN:** Löschen, Duplizieren, Archivieren
- **QUICK-STATUS-CHANGE:** Sofortige Status-Änderung ohne Edit-Modal
- **BULK-ACTIONS:** Multi-Select für mehrere TODOs gleichzeitig

### 6. BENUTZERFREUNDLICHER PLAN-EDITOR ✅
- **WYSIWYG-EDITOR:** Keine HTML-Kenntnisse erforderlich
- **TEMPLATE-SYSTEM:** Vordefinierte Bausteine für häufige Szenarien
- **AUTO-SAVE:** Verhindert Datenverlust bei unbeabsichtigtem Schließen
- **MARKDOWN-SUPPORT:** Einfache Formatierung mit Markdown-Syntax

### 7. HTML/OUTPUT-VIEW ALS STANDARD ✅
- **AUTOMATISCHES HTML-RENDERING:** Plan-Inhalte werden als formatierter Text angezeigt
- **SYNTAX-HIGHLIGHTING:** Code-Blöcke werden farbig hervorgehoben
- **FALTBARE SECTIONS:** Bessere Übersicht bei langen TODOs
- **PRINT-OPTIMIERT:** Professionelle Darstellung für Dokumentation

## 🔄 WORKFLOW V3.0

### Standard-Workflow:
1. `./todo` lädt nächstes TODO mit **allen Datenfeldern**
2. Bearbeitung mit **vollständigem Kontext** (Plan, Notizen, Arbeitsverzeichnis)
3. `./todo complete` für **Abschluss mit Output-Sammlung** ODER
4. `./todo defer` für **intelligente Terminierung mit Kontext-Erhaltung**

### Wiedervorlage-Workflow:
1. **Status-Assessment** während Bearbeitung
2. `./todo defer` öffnet **Terminierungs-Dialog** mit Datum/Zeit-Picker
3. **Automatische Output-Sammlung** der bisherigen Arbeit
4. **Notiz-Generierung** für späteren Kontext
5. **Status-Update** zu "terminiert" mit Wiedervorlage-Information
6. **Automatische Wiederaufnahme** wenn Termin erreicht ist

### Filter-Workflow:
```bash
# Smart-Filter verwenden
./todo filter --preset heute      # Nur heute fällige TODOs
./todo filter --preset überfällig # Überfällige offene TODOs
./todo filter --reset            # Alle Filter zurücksetzen
./todo search "documentation"     # Volltext-Suche
```

## 🎯 AKTUELLER STATUS (V3.0 VOLLSTÄNDIG!)

### ✅ ABGESCHLOSSEN (100%):
1. ✅ Verzeichnisstruktur migriert 
2. ✅ Hook-System v2.0 mit 100% Test-Coverage
3. ✅ Erweiterte TODO-Datenladung implementiert
4. ✅ Wiedervorlage-System (Option B) funktionsfähig
5. ✅ Smart-Filter-System mit Presets
6. ✅ Benachrichtigungssystem für UI-Feedback
7. ✅ Erweiterte Floating Button Bar
8. ✅ Benutzerfreundlicher Plan-Editor ohne HTML
9. ✅ HTML/Output-View als Standard-Ansicht
10. ✅ Claude Toggle Individual-Buttons

### 🔮 ROADMAP (ZUKUNFT):
- **Phase 4:** Multi-Agent-System Integration
- **Phase 5:** KI-basierte TODO-Priorisierung
- **Phase 6:** API-Endpoints für externe Integration
- **Phase 7:** Mobile App Development

---

**Letzte Aktualisierung:** 2025-01-21 (V3.0 KOMPLETT!)  
**Version:** 3.0.0 - VOLLSTÄNDIGE FEATURE-IMPLEMENTIERUNG