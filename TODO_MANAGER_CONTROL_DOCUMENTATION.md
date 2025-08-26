# 🎮 TODO-MANAGER DASHBOARD CONTROL

## ✅ IMPLEMENTIERT am 22.08.2025

### 🎯 FEATURES

#### 1. **Dashboard Control Panel**
- **Visueller Status:** Grüner/Roter Punkt zeigt ob Manager läuft
- **PID-Anzeige:** Zeigt Prozess-ID wenn aktiv
- **Start/Stop/Restart Buttons:** Volle Kontrolle über Dashboard
- **Auto-Refresh:** Status wird alle 10 Sekunden aktualisiert

#### 2. **Control Script**
**Pfad:** `/home/rodemkay/www/react/plugin-todo/todo-manager-control.sh`

**Befehle:**
```bash
./todo-manager-control.sh start    # Manager starten
./todo-manager-control.sh stop     # Manager stoppen
./todo-manager-control.sh restart  # Manager neustarten
./todo-manager-control.sh status   # Status anzeigen
./todo-manager-control.sh check    # Für Cron: Start wenn nicht läuft
./todo-manager-control.sh json     # JSON Status für AJAX
```

#### 3. **WordPress Integration**
- **AJAX Endpoints:** 
  - `todo_manager_start`
  - `todo_manager_stop`
  - `todo_manager_restart`
  - `todo_manager_status`
- **Sicherheit:** Nur Admins können steuern
- **Nonce-Protection:** CSRF-Schutz

#### 4. **Optionaler Cron-Job**
**Setup-Script:** `./setup-cron.sh`

Optionen:
- Alle 5 Minuten prüfen
- Alle 10 Minuten prüfen
- Alle 30 Minuten prüfen
- Stündlich prüfen

Der Cron-Job startet den Manager automatisch neu, falls er nicht läuft.

## 📁 GEÄNDERTE DATEIEN

1. **Neue Dateien:**
   - `/todo-manager-control.sh` - Control Script
   - `/setup-cron.sh` - Cron Setup Script
   - Diese Dokumentation

2. **Geänderte Dateien:**
   - `/plugins/todo/todo.php` - AJAX Handler hinzugefügt
   - `/templates/wsj-dashboard.php` - Control Panel UI & JavaScript

## 🚀 VERWENDUNG

### Im Dashboard:
1. **Grüner Punkt = Manager läuft** - Tasks werden automatisch bearbeitet
2. **Roter Punkt = Manager gestoppt** - Keine automatische Bearbeitung
3. **START:** Startet den Manager
4. **STOP:** Stoppt den Manager (wartet bis aktuelle Task fertig)
5. **RESTART:** Neustart des Managers

### Im Terminal:
```bash
# Status prüfen
./todo-manager-control.sh status

# Manager stoppen
./todo-manager-control.sh stop

# Manager starten
./todo-manager-control.sh start
```

### Als Cron-Job (Optional):
```bash
# Interaktives Setup
./setup-cron.sh

# Oder manuell in crontab
*/5 * * * * /home/rodemkay/www/react/plugin-todo/todo-manager-control.sh check
```

## 🔧 TECHNISCHE DETAILS

### Manager-Prozess:
- **Script:** `intelligent_todo_monitor_fixed.sh`
- **Intervall:** Prüft alle 30 Sekunden auf neue Tasks
- **PID-File:** `/tmp/todo_manager.pid`
- **Status-File:** `/tmp/todo_manager_status.json`
- **Log-File:** `/tmp/todo_manager.log`

### Graceful Shutdown:
- SIGTERM Signal für sauberes Beenden
- Wartet bis aktuelle Task abgeschlossen
- Timeout nach 10 Sekunden, dann SIGKILL

### Auto-Recovery:
- Bei Cron-Setup: Automatischer Neustart wenn Manager abstürzt
- Dashboard zeigt immer aktuellen Status

## 💡 VORTEILE

✅ **Volle Kontrolle:** Start/Stop direkt im Dashboard
✅ **Kein SSH nötig:** Alles über Web-Interface
✅ **Status immer sichtbar:** Live-Anzeige ob Manager läuft
✅ **Flexibel:** Als Service ODER Cron-Job nutzbar
✅ **Sicher:** Nur Admins können steuern
✅ **Robust:** Graceful Shutdown, PID-Tracking, Auto-Recovery

## 🎯 STATUS

Der Todo-Manager läuft aktuell mit **PID 219159** und überwacht automatisch neue Tasks!