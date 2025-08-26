# 🚀 TMUX SESSION MANAGER - Quick Reference

## 🔥 HÄUFIGSTE BEFEHLE

### Session-Wechsel
```bash
# Zu anderem Projekt wechseln (komplett)
./tmux-session-manager.sh switch todo
./tmux-session-manager.sh switch article
./tmux-session-manager.sh switch forexsignale

# Dashboard anzeigen (Übersicht)
./tmux-session-manager.sh dashboard

# System-Status prüfen
./tmux-session-manager.sh health
```

### Session-Management  
```bash
# Neue Session erstellen (ohne Wechsel)
./tmux-session-manager.sh create todo

# Session beenden (sicher)
./tmux-session-manager.sh kill todo

# Zu existierender Session verbinden
./tmux-session-manager.sh attach todo

# Alle Claude-Sessions auflisten
./tmux-session-manager.sh list
```

### Monitoring & Status
```bash
# Aktuelle Session-Info
./tmux-session-manager.sh status

# Spezifisches Projekt prüfen  
./tmux-session-manager.sh status todo

# Session-State anzeigen
./tmux-session-manager.sh state todo
```

## 🚨 NOTFALL-BEFEHLE

### Problem-Diagnose
```bash
# Crashed Sessions/Zombie-Prozesse erkennen
./session-recovery.sh detect

# System vollständig bereinigen
./tmux-session-manager.sh cleanup

# System-Validierung  
./session-recovery.sh validate
```

### Emergency Recovery
```bash
# Backup vor kritischen Operationen
./session-recovery.sh backup all

# NUCLEAR OPTION: Komplette System-Recovery
./session-recovery.sh emergency

# Aus Backup wiederherstellen
./session-recovery.sh restore /path/to/backup.tar.gz
```

## 📁 VERFÜGBARE PROJEKTE

| Projekt      | Pfad                                                    | Beschreibung              |
|-------------|--------------------------------------------------------|---------------------------|
| `todo`      | `/home/rodemkay/www/react/plugin-todo`                | Todo System V3.0          |  
| `article`   | `/home/rodemkay/www/react/plugin-article`             | Article Builder MCP        |
| `forexsignale` | `/home/rodemkay/www/react`                          | ForexSignale Magazine      |
| `staging`   | `/home/rodemkay/www/react/mounts/hetzner/.../staging` | Staging Environment        |
| `development` | `/home/rodemkay/www/react/development`               | Development Sandbox       |

## ⚡ WORKFLOW-TIPPS

### Täglicher Workflow
```bash
# 1. System-Check beim Start
./tmux-session-manager.sh health

# 2. Dashboard für Übersicht
./tmux-session-manager.sh dashboard  

# 3. Zu Projekt wechseln
./tmux-session-manager.sh switch todo

# 4. Nach Arbeit: Session sicher beenden
./tmux-session-manager.sh kill todo
```

### Bei Problemen
```bash
# 1. Problem erkennen
./session-recovery.sh detect

# 2. Backup erstellen
./session-recovery.sh backup all

# 3. System bereinigen
./tmux-session-manager.sh cleanup

# 4. Im Notfall: Nuclear Option
./session-recovery.sh emergency
```

## 📊 SESSION-LAYOUT

```
┌─────────────────────────────────┬─────────┐
│        CLAUDE CODE CLI          │  BASH   │
│         (90% Width)             │ (10%)   │
│                                 │         │
│  • claude -resume --danger...   │ Command │
│  • Projekt-spezifische Context  │ Line    │
│  • CLAUDE.md automatisch        │ Ready   │
│                                 │         │
└─────────────────────────────────┴─────────┘
```

## 🔧 ERWEITERTE FEATURES

### Befehl an Session senden
```bash
# Todo-Befehl remote ausführen
./tmux-session-manager.sh send todo "./todo"

# Status-Check remote
./tmux-session-manager.sh send todo "pwd && ls -la"
```

### Backup-Management
```bash
# Verfügbare Backups anzeigen
./session-recovery.sh list-backups

# Alte Backups bereinigen (7+ Tage)
./session-recovery.sh cleanup-backups 7
```

## 📍 WICHTIGE PFADE

```bash
# Scripts
/home/rodemkay/www/react/plugin-todo/scripts/
├── tmux-session-manager.sh     # Haupt-Manager
└── session-recovery.sh         # Emergency System

# States & Backups  
~/.claude/session-states/       # Session-Zustände
~/.claude/backups/              # Backup-Archive

# Logs
/tmp/tmux-session-manager.log   # Manager-Logs
/tmp/session-recovery.log       # Recovery-Logs
```

## 🎯 SESSION-NAMEN

- **Format:** `claude-<projekt>`
- **Beispiele:** `claude-todo`, `claude-article`  
- **Window:** Immer "react"
- **Panes:** 0 (links/Claude), 1 (rechts/Bash)

## ❓ HÄUFIGE FRAGEN

**Q: Session reagiert nicht?**  
A: `./session-recovery.sh detect` → `./tmux-session-manager.sh cleanup`

**Q: Claude startet nicht?**  
A: `./tmux-session-manager.sh health` → PATH prüfen

**Q: Lock-File blockiert?**  
A: `./session-recovery.sh emergency` (Nuclear Option)

**Q: Wie zurück zur vorherigen Session?**  
A: `./tmux-session-manager.sh switch <vorheriges-projekt>`

**Q: Session-Backup vor wichtiger Änderung?**  
A: `./session-recovery.sh backup all`

---

## 🚀 QUICK-START

```bash
# 1. Executable machen
chmod +x scripts/*.sh

# 2. Health-Check
./scripts/tmux-session-manager.sh health

# 3. Dashboard anzeigen
./scripts/tmux-session-manager.sh dashboard

# 4. Zu Projekt wechseln  
./scripts/tmux-session-manager.sh switch todo
```

**Fertig! Das System ist einsatzbereit.** 🎉