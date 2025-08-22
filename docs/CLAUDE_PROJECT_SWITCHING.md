# 🔄 Claude Code Projekt-Wechsel Guide

## 📋 Übersicht
Dieser Guide zeigt verschiedene Methoden zum Wechseln zwischen Projekten in Claude Code.

## 🚀 Quick Start

### Sofort verfügbare Scripts:
```bash
# Einfacher Projekt-Wechsel
/home/rodemkay/www/react/todo/scripts/claude-switch-project.sh

# TMUX Manager für mehrere Sessions
/home/rodemkay/www/react/todo/scripts/claude-tmux-manager.sh

# Aliases einrichten
/home/rodemkay/www/react/todo/scripts/setup-claude-aliases.sh
```

## 📂 Methode 1: Manueller Wechsel

### Schritt-für-Schritt:
1. **Claude Code beenden:**
   ```bash
   exit
   # oder
   Ctrl+D
   ```

2. **Zum neuen Projekt wechseln:**
   ```bash
   cd /pfad/zum/neuen/projekt
   ```

3. **Claude Code neu starten:**
   ```bash
   claude --resume --dangerously-skip-permissions
   ```

4. **Session auswählen** aus der angezeigten Liste

## 🖥️ Methode 2: TMUX Multi-Session

### Vorteile:
- Mehrere Claude Sessions parallel
- Schneller Wechsel zwischen Projekten
- Sessions bleiben im Hintergrund aktiv

### TMUX Befehle:

#### Neue Session erstellen:
```bash
tmux new-session -s claude-todo -c /home/rodemkay/www/react/todo
```

#### Zwischen Sessions wechseln:
```bash
# Session verlassen (bleibt aktiv)
Ctrl+b d

# Zu Session wechseln
tmux attach -t claude-todo

# Session Liste anzeigen
tmux list-sessions
```

#### TMUX Keyboard Shortcuts:
- **Prefix:** `Ctrl+b` (zuerst drücken, dann Befehl)
- **Neues Pane:** `Ctrl+b %` (vertikal) oder `Ctrl+b "` (horizontal)
- **Pane wechseln:** `Ctrl+b` + Pfeiltasten
- **Session verlassen:** `Ctrl+b d`
- **Session Liste:** `Ctrl+b s`

## 🛠️ Methode 3: Script-basierter Wechsel

### claude-switch-project.sh
Interaktives Menü für Projekt-Wechsel:
```bash
/home/rodemkay/www/react/todo/scripts/claude-switch-project.sh
```

Features:
- Vordefinierte Projekt-Liste
- Custom Path Option
- Automatisches Claude Code Restart
- Farbiges Interface

### claude-tmux-manager.sh
Fortgeschrittenes TMUX Management:
```bash
/home/rodemkay/www/react/todo/scripts/claude-tmux-manager.sh
```

Features:
- Session Management (create/switch/kill)
- Schnellstart für häufige Projekte
- Session Übersicht
- TMUX Cheatsheet integriert

## 🎯 Methode 4: Shell Aliases

### Setup:
```bash
# Aliases automatisch einrichten
/home/rodemkay/www/react/todo/scripts/setup-claude-aliases.sh

# Oder manuell zu ~/.bashrc hinzufügen:
alias claude-todo='cd /home/rodemkay/www/react/todo && claude --resume --dangerously-skip-permissions'
alias claude-forex='cd /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging && claude --resume --dangerously-skip-permissions'
```

### Verwendung:
```bash
# Direkt zu Todo-Projekt wechseln
claude-todo

# TMUX Manager öffnen
claude-tmux

# Claude Sessions anzeigen
claude-list
```

## 📍 Wichtige Projekt-Pfade

| Projekt | Pfad |
|---------|------|
| Todo Plugin | `/home/rodemkay/www/react/todo` |
| ForexSignale | `/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging` |
| Breakout Brain | `/home/rodemkay/www/react/breakout-brain` |
| Development | `/home/rodemkay/www/react/development` |

## 💡 Best Practices

### Für einzelne Projekte:
- Nutze den manuellen Wechsel oder Aliases
- Einfach und schnell für gelegentliche Wechsel

### Für mehrere Projekte gleichzeitig:
- Nutze TMUX Sessions
- Ideal für parallele Entwicklung
- Sessions bleiben im Hintergrund erhalten

### Für häufige Wechsel:
- Richte Aliases ein
- Nutze die Script-Tools
- Erstelle eigene Shortcuts

## 🔧 Troubleshooting

### Problem: Claude Code startet nicht neu
```bash
# Prüfe ob alte Prozesse laufen
ps aux | grep claude

# Beende hängende Prozesse
pkill -f claude
```

### Problem: TMUX Session hängt
```bash
# Force-Kill der Session
tmux kill-session -t session-name

# Alle Sessions beenden
tmux kill-server
```

### Problem: Verzeichnis nicht gefunden
```bash
# Prüfe ob Mount aktiv ist
mount | grep sshfs

# Remount wenn nötig
/home/rodemkay/www/react/scripts/mount-servers.sh
```

## 📝 Notizen

- Claude Code behält die Session-History
- Mit `--resume` wird die letzte Session fortgesetzt
- `--dangerously-skip-permissions` überspringt die Permissions-Abfrage
- TMUX Sessions überleben SSH-Disconnects

## 🚨 Wichtig

- Immer nur EINE Claude Instance pro Projekt
- Bei Problemen erst `exit` dann neu starten
- TMUX Sessions regelmäßig aufräumen (`tks session-name`)

---

**Erstellt:** 2025-08-22
**Script-Verzeichnis:** `/home/rodemkay/www/react/todo/scripts/`