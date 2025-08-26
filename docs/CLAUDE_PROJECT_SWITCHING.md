# 🔄 CLAUDE PROJECT SWITCHING SYSTEM - TECHNISCHE DOKUMENTATION

## 🎯 ÜBERSICHT

Ein robustes System zum nahtlosen Wechseln zwischen verschiedenen Claude-Sessions in unterschiedlichen Projektordnern. Das System ermöglicht es, von der aktuellen Claude-Session zu einer anderen zu wechseln, ohne Datenverlust oder Session-Konflikte.

**Version:** 2.0 (Erweitert am 2025-08-25)  
**Status:** ✅ ANALYSIERT UND DESIGNT - Bereit für Implementierung

## 📊 AKTUELLE SESSION-ANALYSE

### Tmux-Session-Struktur
```bash
# Aktuelle Claude-Session (analysiert am 2025-08-25)
Session: claude
├── Window: react (aktiv)
│   ├── Pane 0 (links, ~90%): Claude Code CLI (PID: 3520856)
│   └── Pane 1 (rechts, ~10%): Bash Terminal
```

### Identifizierte Projekte
```
/home/rodemkay/www/react/
├── plugin-article/         # Article Builder Plugin
├── plugin-todo/           # Todo System (AKTUELL AKTIV)
├── plugin-wp-project-todos/ # Legacy Todo System
└── [root]/                # ForexSignale Magazine (Hauptprojekt)
```

### Start-Scripts
```
/home/rodemkay/.local/bin/
├── kitty_claude_fresh_todo.sh  # Todo-Projekt (AKTUELL)
└── kitty_claude_7030.sh        # Alternatives Start-Script
```

## 🏗️ SYSTEM-ARCHITEKTUR

### 1. Session-Manager (Hauptkomponente)

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
tmux new-session -s claude-todo -c /home/rodemkay/www/react/plugin-todo
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
/home/rodemkay/www/react/plugin-todo/scripts/claude-switch-project.sh
```

Features:
- Vordefinierte Projekt-Liste
- Custom Path Option
- Automatisches Claude Code Restart
- Farbiges Interface

### claude-tmux-manager.sh
Fortgeschrittenes TMUX Management:
```bash
/home/rodemkay/www/react/plugin-todo/scripts/claude-tmux-manager.sh
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
/home/rodemkay/www/react/plugin-todo/scripts/setup-claude-aliases.sh

# Oder manuell zu ~/.bashrc hinzufügen:
alias claude-todo='cd /home/rodemkay/www/react/plugin-todo && claude --resume --dangerously-skip-permissions'
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
| Todo Plugin | `/home/rodemkay/www/react/plugin-todo` |
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
**Script-Verzeichnis:** `/home/rodemkay/www/react/plugin-todo/scripts/`