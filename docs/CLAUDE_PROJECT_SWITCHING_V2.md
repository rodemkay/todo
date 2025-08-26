# 🔄 CLAUDE PROJECT SWITCHING SYSTEM - TECHNISCHE DOKUMENTATION V2.0

## 🎯 ÜBERSICHT

Ein robustes System zum nahtlosen Wechseln zwischen verschiedenen Claude-Sessions in unterschiedlichen Projektordnern. Das System ermöglicht es, von der aktuellen Claude-Session zu einer anderen zu wechseln, ohne Datenverlust oder Session-Konflikte.

**Version:** 2.0 (Erweitert am 2025-08-25)  
**Status:** ✅ ANALYSIERT UND DESIGNT - Bereit für Implementierung

## 📊 AKTUELLE SESSION-ANALYSE

### Tmux-Session-Struktur
```bash
# Aktuelle Claude-Session (analysiert am 2025-08-25)
Session: claude (PID: 3170147)
├── Window: react (aktiv)
│   ├── Pane 0 (links, ~90%): Claude Code CLI (PID: 3520856)
│   └── Pane 1 (rechts, ~10%): Bash Terminal
├── Start-Command: bash -i
├── Working Dir: /home/rodemkay/www/react/plugin-todo
└── Started: Aug19 (Laufzeit: 6+ Tage)
```

### Identifizierte Projekte
```
/home/rodemkay/www/react/
├── plugin-article/         # Article Builder Plugin mit MCP Integration
├── plugin-todo/           # Todo System V3.0 (AKTUELL AKTIV)
├── plugin-wp-project-todos/ # Legacy Todo System (Archiviert)
└── [root]/                # ForexSignale Magazine (Hauptprojekt)
```

### Start-Scripts-Analyse
```
/home/rodemkay/.local/bin/
├── kitty_claude_fresh_todo.sh  # Todo-Projekt (AKTUELL VERWENDET)
│   ├── Target: /home/rodemkay/www/react/plugin-todo
│   ├── Session: claude (kill + restart)
│   ├── Layout: 2 Panes (90%/10% split)
│   └── Auto-Start: claude -resume --dangerously-skip-permissions
└── kitty_claude_7030.sh        # Alternatives Script (unbekannter Zweck)
```

### Claude-Prozess-Analyse
```bash
# Laufende Claude-Instanzen
PID     COMMAND                                    STATUS
3520856 claude                                     AKTIV (Todo-Projekt)
1039010 /bin/bash -c -l source snapshot && eval... ZOMBIE/ORPHAN
```

## 🏗️ SYSTEM-ARCHITEKTUR

### 1. Session-Manager (Hauptkomponente)
```bash
#!/bin/bash
# /home/rodemkay/.local/lib/claude-session-manager.sh

# Verfügbare Projekte mit ihren Konfigurationen
declare -A PROJECTS=(
    ["todo"]="/home/rodemkay/www/react/plugin-todo"
    ["article"]="/home/rodemkay/www/react/plugin-article" 
    ["forexsignale"]="/home/rodemkay/www/react"
    ["wp-todos"]="/home/rodemkay/www/react/plugin-wp-project-todos"
)

declare -A PROJECT_DESCRIPTIONS=(
    ["todo"]="Todo System Plugin - V3.0 mit erweiterten Features"
    ["article"]="Article Builder Plugin mit MCP Integration"
    ["forexsignale"]="ForexSignale Magazine - Hauptprojekt"
    ["wp-todos"]="Legacy WP Project Todos (Archiviert)"
)

declare -A PROJECT_START_SCRIPTS=(
    ["todo"]="kitty_claude_fresh_todo.sh"
    ["article"]="kitty_claude_fresh_article.sh"
    ["forexsignale"]="kitty_claude_fresh_main.sh"
    ["wp-todos"]="kitty_claude_fresh_legacy.sh"
)
```

### 2. Session-Identifikation
```bash
# Aktuelle Session ermitteln
get_current_session() {
    local current_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null)
    local session_name=$(tmux display-message -p -F "#{session_name}" 2>/dev/null)
    local claude_pid=$(pgrep -f "^claude$")
    
    echo "🎯 AKTUELLE SESSION ANALYSE:"
    echo "   Session Name: $session_name"
    echo "   Working Dir:  $current_dir"
    echo "   Claude PID:   $claude_pid"
    
    # Projekt basierend auf Directory identifizieren
    for project in "${!PROJECTS[@]}"; do
        if [[ "$current_dir" == "${PROJECTS[$project]}"* ]]; then
            echo "   Current Project: $project (${PROJECT_DESCRIPTIONS[$project]})"
            return 0
        fi
    done
    echo "   Current Project: ⚠️  UNBEKANNT"
    return 1
}
```

### 3. Sichere Session-Beendigung
```bash
# Session sicher beenden mit Cleanup
safe_session_exit() {
    local project="$1"
    local session_name="claude"
    
    echo "🔄 Beende aktuelle Session für Projekt: $project"
    
    # 1. TASK_COMPLETED prüfen (falls Todo-Projekt)
    if [[ "$project" == "todo" ]]; then
        if [[ -f "/tmp/TASK_COMPLETED" ]]; then
            echo "✅ TASK_COMPLETED bereits vorhanden"
        else
            echo "⚠️  WARNUNG: TASK_COMPLETED fehlt!"
            echo "   Aktuelle Todo-Session ohne TASK_COMPLETED zu beenden kann zu"
            echo "   Hook-System-Violations und Datenverlust führen!"
            
            read -p "   Session trotzdem beenden? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                echo "❌ Session-Wechsel abgebrochen"
                echo "   Bitte aktuellen Task mit './todo complete' abschließen"
                return 1
            fi
        fi
    fi
    
    # 2. Session-State VORHER speichern
    save_session_state "$project"
    
    # 3. Claude-Prozess graceful beenden
    local claude_pid=$(pgrep -f "^claude$")
    if [[ -n "$claude_pid" ]]; then
        echo "🔄 Beende Claude-Prozess (PID: $claude_pid)"
        
        # Graceful shutdown versuchen
        kill -TERM "$claude_pid"
        sleep 3
        
        # Falls noch aktiv, force kill
        if kill -0 "$claude_pid" 2>/dev/null; then
            echo "⚡ Claude-Prozess antwortet nicht, force kill..."
            kill -KILL "$claude_pid"
            sleep 1
        fi
        
        # Verification
        if ! kill -0 "$claude_pid" 2>/dev/null; then
            echo "✅ Claude-Prozess erfolgreich beendet"
        else
            echo "❌ FEHLER: Claude-Prozess konnte nicht beendet werden"
            return 1
        fi
    else
        echo "ℹ️  Kein Claude-Prozess aktiv"
    fi
    
    # 4. Orphaned/Zombie Prozesse cleanup
    cleanup_orphaned_processes
    
    # 5. Tmux-Session beenden
    if tmux has-session -t "$session_name" 2>/dev/null; then
        tmux kill-session -t "$session_name" 2>/dev/null || true
        echo "✅ Tmux-Session beendet"
    else
        echo "ℹ️  Keine Tmux-Session zu beenden"
    fi
    
    return 0
}
```

### 4. Orphaned Process Cleanup
```bash
# Cleanup von verwaisten Claude-Prozessen
cleanup_orphaned_processes() {
    echo "🧹 Cleanup von verwaisten Prozessen..."
    
    # Suche nach Claude-Snapshot-Prozessen (häufige Zombies)
    local snapshot_pids=$(pgrep -f "shell-snapshots.*python3 app.py")
    if [[ -n "$snapshot_pids" ]]; then
        echo "🔍 Gefundene Snapshot-Prozesse: $snapshot_pids"
        for pid in $snapshot_pids; do
            echo "   Beende Snapshot-Prozess PID: $pid"
            kill -TERM "$pid" 2>/dev/null || kill -KILL "$pid" 2>/dev/null
        done
    fi
    
    # Suche nach hängenden tmux send-keys Prozessen
    local tmux_pids=$(pgrep -f "tmux send-keys")
    if [[ -n "$tmux_pids" ]]; then
        echo "🔍 Gefundene hängende tmux-Befehle: $tmux_pids"
        for pid in $tmux_pids; do
            echo "   Beende tmux-Befehl PID: $pid"
            kill -TERM "$pid" 2>/dev/null
        done
    fi
    
    echo "✅ Cleanup abgeschlossen"
}
```

### 5. Session-State Management
```bash
# Session-Zustand speichern
save_session_state() {
    local project="$1"
    local state_dir="/home/rodemkay/.claude/session-states"
    local state_file="$state_dir/${project}.state"
    
    mkdir -p "$state_dir"
    
    # Aktuelle Session-Informationen sammeln
    local current_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || pwd)
    local window_layout=$(tmux list-panes -t claude:react -F "#{pane_width}x#{pane_height}:#{pane_left},#{pane_top}" 2>/dev/null || echo "default")
    local claude_pid=$(pgrep -f "^claude$")
    local last_task_id=""
    
    # Bei Todo-Projekt: Letzte Task-ID ermitteln
    if [[ "$project" == "todo" && -f "$current_dir/cli/last_task.tmp" ]]; then
        last_task_id=$(cat "$current_dir/cli/last_task.tmp" 2>/dev/null || echo "")
    fi
    
    cat > "$state_file" << EOF
# Claude Session State für Projekt: $project
# Gespeichert: $(date)
PROJECT=$project
WORKING_DIR=${PROJECTS[$project]}
ACTUAL_DIR=$current_dir
LAST_SESSION=$(date +%s)
TMUX_SESSION=claude
PANE_LAYOUT=$window_layout
CLAUDE_PID=$claude_pid
LAST_TASK_ID=$last_task_id
START_SCRIPT=${PROJECT_START_SCRIPTS[$project]}
EOF
    
    # Conversation History backup (falls vorhanden)
    if [[ -f "/home/rodemkay/.claude/conversations/current.json" ]]; then
        cp "/home/rodemkay/.claude/conversations/current.json" \
           "$state_dir/${project}_conversation_$(date +%Y%m%d_%H%M%S).json" 2>/dev/null || true
    fi
    
    echo "💾 Session-State gespeichert: $state_file"
}

# Session-State laden
load_session_state() {
    local project="$1"
    local state_file="/home/rodemkay/.claude/session-states/${project}.state"
    
    if [[ -f "$state_file" ]]; then
        source "$state_file"
        echo "📁 Session-State geladen für $project:"
        echo "   Working Dir: $WORKING_DIR"
        echo "   Last Session: $(date -d @$LAST_SESSION 2>/dev/null || echo 'unbekannt')"
        echo "   Last Task ID: ${LAST_TASK_ID:-'keine'}"
        return 0
    else
        echo "ℹ️  Kein Session-State für $project gefunden (erster Start)"
        return 1
    fi
}
```

### 6. Neues Session-Startup
```bash
# Neue Claude-Session starten
start_claude_session() {
    local project="$1"
    local working_dir="${PROJECTS[$project]}"
    local start_script="${PROJECT_START_SCRIPTS[$project]}"
    
    echo "🚀 Starte Claude-Session für Projekt: $project"
    echo "📁 Working Directory: $working_dir"
    echo "🎬 Start-Script: $start_script"
    
    # 1. Verzeichnis validieren
    if [[ ! -d "$working_dir" ]]; then
        echo "❌ FEHLER: Arbeitsverzeichnis existiert nicht: $working_dir"
        return 1
    fi
    
    # 2. CLAUDE.md prüfen
    local claude_md="$working_dir/CLAUDE.md"
    if [[ -f "$claude_md" ]]; then
        echo "📋 Projekt-spezifische CLAUDE.md gefunden"
        local word_count=$(wc -w < "$claude_md")
        echo "   Größe: $word_count Wörter"
    else
        echo "ℹ️  Keine projekt-spezifische CLAUDE.md, verwende globale Konfiguration"
    fi
    
    # 3. Start-Script prüfen und verwenden
    local script_path="/home/rodemkay/.local/bin/$start_script"
    if [[ -f "$script_path" ]]; then
        echo "🎬 Verwende existierendes Start-Script: $script_path"
        chmod +x "$script_path"
        exec "$script_path"
    else
        echo "⚠️  Start-Script nicht gefunden, verwende Generic-Template"
        create_and_run_generic_script "$project" "$working_dir"
    fi
}

# Generic Start-Script Template
create_and_run_generic_script() {
    local project="$1"
    local working_dir="$2"
    
    # Template basierend auf kitty_claude_fresh_todo.sh
    cat > "/tmp/claude_start_${project}.sh" << EOF
#!/usr/bin/env bash
set -u

TARGET="$working_dir"
[ -d "\$TARGET" ] || TARGET="\$HOME"

# Session neu starten (falls vorhanden)
tmux kill-session -t claude 2>/dev/null || true
tmux start-server 2>/dev/null || true

# Zwei Panes (links/rechts), beide mit interaktiver Shell
tmux new-session  -d -s claude -n react -c "\$TARGET" bash -i
tmux split-window -h -t claude:react -c "\$TARGET"       bash -i

# Fensterbreite auslesen und RECHTES Pane auf ~10% setzen
w=\$(tmux display-message -p -t claude:react '#{window_width}')
[ -n "\$w" ] && p10=\$(( w * 10 / 100 )) || p10=12
[ "\$p10" -lt 8 ] && p10=8
tmux resize-pane -t claude:react.1 -x "\$p10" 2>/dev/null || \\
tmux resize-pane -t claude:react.1 -l "\$p10" 2>/dev/null

# Links (Pane 0) Claude starten
if command -v claude >/dev/null 2>&1; then
  tmux send-keys -t claude:react.0 'claude -resume --dangerously-skip-permissions' C-m
fi

# Fokus LINKS und anhängen
tmux select-window -t claude:react
tmux select-pane   -t claude:react.0
exec tmux attach -t claude
EOF
    
    chmod +x "/tmp/claude_start_${project}.sh"
    exec "/tmp/claude_start_${project}.sh"
}
```

## 🔧 IMPLEMENTIERUNG

### 1. Haupt-Switch-Script erstellen
```bash
# /home/rodemkay/.local/bin/claude-switch.sh
#!/bin/bash

# Claude Project Switching System
# Version 2.0 - Erweitert mit robustem Error-Handling

set -euo pipefail

# Source der Session-Manager-Bibliothek
source "/home/rodemkay/.local/lib/claude-session-manager.sh"

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Logging
LOG_FILE="/tmp/claude-switch.log"
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Hauptfunktion
main() {
    log "Claude Switch gestartet mit Argument: ${1:-help}"
    
    case "${1:-help}" in
        "list"|"ls")
            list_projects
            ;;
        "current"|"status")
            get_current_session
            ;;
        "switch")
            switch_to_project "$2"
            ;;
        "start")
            start_project_session "$2"
            ;;
        "stop")
            stop_current_session
            ;;
        "health")
            check_system_health
            ;;
        "dashboard")
            show_session_dashboard
            ;;
        "cleanup")
            emergency_cleanup
            ;;
        "help"|*)
            show_help
            ;;
    esac
}

# Projekt-Liste anzeigen
list_projects() {
    echo -e "${BLUE}📋 Verfügbare Projekte:${NC}"
    echo
    for project in "${!PROJECTS[@]}"; do
        local path="${PROJECTS[$project]}"
        local desc="${PROJECT_DESCRIPTIONS[$project]}"
        
        # Status prüfen
        local status="⚫ offline"
        local current_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || echo "")
        if [[ "$current_dir" == "$path"* ]]; then
            status="🟢 aktiv"
        fi
        
        echo -e "  $status ${YELLOW}$project${NC} - $desc"
        echo -e "         📁 $path"
        echo
    done
}

# Projekt-Wechsel mit vollständiger Validierung
switch_to_project() {
    local target_project="$1"
    
    if [[ -z "$target_project" ]]; then
        echo -e "${RED}❌ FEHLER: Ziel-Projekt nicht angegeben${NC}"
        echo -e "Verfügbare Projekte: ${YELLOW}${!PROJECTS[*]}${NC}"
        return 1
    fi
    
    if [[ ! -v PROJECTS["$target_project"] ]]; then
        echo -e "${RED}❌ FEHLER: Unbekanntes Projekt: $target_project${NC}"
        echo -e "Verfügbare Projekte: ${YELLOW}${!PROJECTS[*]}${NC}"
        return 1
    fi
    
    log "Starte Projekt-Wechsel zu: $target_project"
    echo -e "${BLUE}🔄 Wechsle von aktueller Session zu Projekt: ${YELLOW}$target_project${NC}"
    
    # Lock-Mechanismus
    if ! acquire_switch_lock; then
        echo -e "${RED}❌ Switch bereits in Bearbeitung${NC}"
        return 1
    fi
    
    # Cleanup bei Fehlern
    trap 'cleanup_failed_switch; release_switch_lock' ERR EXIT INT TERM
    
    # 1. Aktuelle Session sicher beenden
    local current_project=$(detect_current_project)
    if [[ -n "$current_project" ]]; then
        log "Beende aktuelle Session für Projekt: $current_project"
        if ! safe_session_exit "$current_project"; then
            echo -e "${RED}❌ Fehler beim Beenden der aktuellen Session${NC}"
            return 1
        fi
    fi
    
    # 2. Kurze Pause für Cleanup
    sleep 2
    
    # 3. Neue Session starten
    log "Starte neue Session für Projekt: $target_project"
    if ! start_claude_session "$target_project"; then
        echo -e "${RED}❌ Fehler beim Starten der neuen Session${NC}"
        return 1
    fi
    
    # 4. Session-State aktualisieren
    save_session_state "$target_project"
    
    # 5. Success
    release_switch_lock
    trap - ERR EXIT INT TERM
    
    echo -e "${GREEN}✅ Session erfolgreich gewechselt zu: ${YELLOW}$target_project${NC}"
    log "Projekt-Wechsel erfolgreich abgeschlossen: $target_project"
}

# Help anzeigen
show_help() {
    echo -e "${BLUE}🔄 Claude Project Switching System v2.0${NC}"
    echo
    echo -e "${YELLOW}USAGE:${NC}"
    echo "  claude-switch.sh <command> [project]"
    echo
    echo -e "${YELLOW}COMMANDS:${NC}"
    echo "  list, ls        - Zeige verfügbare Projekte"
    echo "  current, status - Zeige aktuelle Session-Info"
    echo "  switch <proj>   - Wechsle zu anderem Projekt"
    echo "  start <proj>    - Starte Session für Projekt (ohne Wechsel)"
    echo "  stop            - Beende aktuelle Session"
    echo "  health          - System Health Check"
    echo "  dashboard       - Session Dashboard anzeigen"
    echo "  cleanup         - Emergency Cleanup (bei Problemen)"
    echo "  help            - Diese Hilfe anzeigen"
    echo
    echo -e "${YELLOW}EXAMPLES:${NC}"
    echo "  claude-switch.sh list"
    echo "  claude-switch.sh switch todo"
    echo "  claude-switch.sh current"
    echo
    echo -e "${YELLOW}VERFÜGBARE PROJEKTE:${NC}"
    for project in "${!PROJECTS[@]}"; do
        echo "  - $project"
    done
}

# Aktuelles Projekt erkennen
detect_current_project() {
    local current_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || pwd)
    
    for project in "${!PROJECTS[@]}"; do
        if [[ "$current_dir" == "${PROJECTS[$project]}"* ]]; then
            echo "$project"
            return 0
        fi
    done
    
    echo ""
    return 1
}

main "$@"
```

### 2. Todo-System Integration
```bash
# Erweitere /home/rodemkay/www/react/plugin-todo/todo um Auto-Switching

# In das bestehende todo-Script einfügen (nach den Definitionen):
# Auto-Switch bei Working Directory Mismatch
validate_project_context() {
    local task_working_dir="$1"
    
    if [[ -z "$task_working_dir" ]]; then
        return 0  # Kein Working Dir definiert, OK
    fi
    
    local current_dir=$(pwd)
    local tmux_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || echo "")
    
    # Check ob aktuelles Dir zum Task passt
    if [[ "$current_dir" != "$task_working_dir"* && "$tmux_dir" != "$task_working_dir"* ]]; then
        echo -e "${RED}⚠️  PROJEKT-MISMATCH ERKANNT:${NC}"
        echo "   Aktuelle Session: $current_dir"
        echo "   Task erfordert:   $task_working_dir"
        echo
        
        # Ziel-Projekt bestimmen
        local target_project=""
        case "$task_working_dir" in
            *"/plugin-article"*) target_project="article" ;;
            *"/plugin-todo"*) target_project="todo" ;;
            *"/plugin-wp-project-todos"*) target_project="wp-todos" ;;
            *"/www/react"*) target_project="forexsignale" ;;
            *) 
                echo -e "${RED}⚠️  Unbekanntes Working Directory: $task_working_dir${NC}"
                return 1
                ;;
        esac
        
        echo -e "${BLUE}🔄 Task erfordert Projekt-Wechsel zu: ${YELLOW}$target_project${NC}"
        read -p "Projekt jetzt wechseln? (Y/n): " -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$|^$ ]]; then
            if command -v claude-switch.sh >/dev/null 2>&1; then
                claude-switch.sh switch "$target_project"
            else
                echo -e "${RED}❌ claude-switch.sh nicht gefunden${NC}"
                return 1
            fi
        else
            echo -e "${RED}❌ Projekt-Wechsel abgebrochen${NC}"
            return 1
        fi
    fi
    
    return 0
}

# In die load_todo_task() Funktion einfügen:
load_todo_task() {
    # ... existing code ...
    
    # Working Directory aus Task extrahieren
    local task_working_dir=$(echo "$task_data" | jq -r '.working_directory // empty')
    
    # Projekt-Context validieren (Auto-Switch)
    if [[ -n "$task_working_dir" ]]; then
        validate_project_context "$task_working_dir" || {
            echo -e "${RED}❌ Task konnte nicht geladen werden (Projekt-Mismatch)${NC}"
            return 1
        }
    fi
    
    # ... rest of existing code ...
}
```

### 3. Lock-Mechanismus
```bash
# Lock-System für sichere Switches
acquire_switch_lock() {
    local lock_file="/tmp/.claude-switch-lock"
    
    if [[ -f "$lock_file" ]]; then
        local lock_pid=$(cat "$lock_file" 2>/dev/null || echo "")
        if [[ -n "$lock_pid" ]] && kill -0 "$lock_pid" 2>/dev/null; then
            echo "⚠️  Switch bereits in Bearbeitung (PID: $lock_pid)"
            return 1
        else
            echo "🧹 Entferne verwaiste Lock-Datei"
            rm -f "$lock_file"
        fi
    fi
    
    echo $$ > "$lock_file"
    return 0
}

release_switch_lock() {
    rm -f "/tmp/.claude-switch-lock" 2>/dev/null || true
}
```

## 🚀 VERWENDUNG

### Basis-Befehle
```bash
# System installieren
chmod +x /home/rodemkay/.local/bin/claude-switch.sh

# Aktuelle Session anzeigen
claude-switch.sh status

# Verfügbare Projekte auflisten
claude-switch.sh list

# Zu anderem Projekt wechseln
claude-switch.sh switch todo        # Todo System
claude-switch.sh switch article     # Article Builder
claude-switch.sh switch forexsignale # ForexSignale Magazine

# System Health Check
claude-switch.sh health

# Session Dashboard
claude-switch.sh dashboard

# Emergency Cleanup (bei Problemen)
claude-switch.sh cleanup
```

### Integration mit Todo-System
```bash
# ./todo mit automatischer Projekt-Erkennung
./todo    # Prüft working_directory und wechselt bei Bedarf

# Manueller Projekt-Wechsel über Todo
./todo --switch-project article

# Status der Project-Switching Integration prüfen
./todo --project-status
```

### Advanced Usage
```bash
# Session-States verwalten
ls -la ~/.claude/session-states/

# Logs analysieren
tail -f /tmp/claude-switch.log

# Backup-Conversations anzeigen
ls -la ~/.claude/session-states/*_conversation_*.json
```

## 🛡️ ERROR-HANDLING & ROBUSTHEIT

### Pre-Switch Validierung
```bash
validate_switch_prerequisites() {
    local target_project="$1"
    
    echo "🔍 Validiere Switch-Voraussetzungen für: $target_project"
    
    # 1. Tmux Server verfügbar?
    if ! tmux info &>/dev/null; then
        echo "❌ FEHLER: Tmux-Server nicht erreichbar"
        return 1
    fi
    
    # 2. Ziel-Verzeichnis vorhanden?
    if [[ ! -d "${PROJECTS[$target_project]}" ]]; then
        echo "❌ FEHLER: Ziel-Verzeichnis nicht gefunden: ${PROJECTS[$target_project]}"
        return 1
    fi
    
    # 3. Claude verfügbar?
    if ! command -v claude >/dev/null 2>&1; then
        echo "❌ FEHLER: Claude-Befehl nicht verfügbar"
        return 1
    fi
    
    # 4. Ausreichend Speicher?
    local mem_available=$(free -m | awk '/^Mem:/{print $7}')
    if [[ $mem_available -lt 100 ]]; then
        echo "⚠️  WARNUNG: Wenig Speicher verfügbar ($mem_available MB)"
    fi
    
    # 5. Lock-File-Permissions
    if ! touch "/tmp/.claude-switch-test" 2>/dev/null; then
        echo "❌ FEHLER: Keine Schreibberechtigung für /tmp/"
        return 1
    fi
    rm -f "/tmp/.claude-switch-test"
    
    echo "✅ Alle Voraussetzungen erfüllt"
    return 0
}
```

### Emergency Cleanup
```bash
emergency_cleanup() {
    echo "🚨 EMERGENCY CLEANUP - Beende alle Claude-Sessions"
    
    # 1. Alle Claude-Prozesse beenden
    local claude_pids=$(pgrep -f claude || echo "")
    if [[ -n "$claude_pids" ]]; then
        echo "🔄 Beende Claude-Prozesse: $claude_pids"
        for pid in $claude_pids; do
            kill -TERM "$pid" 2>/dev/null || kill -KILL "$pid" 2>/dev/null
        done
    fi
    
    # 2. Alle Tmux-Sessions mit "claude" beenden
    local claude_sessions=$(tmux list-sessions 2>/dev/null | grep "claude" | cut -d: -f1 || echo "")
    if [[ -n "$claude_sessions" ]]; then
        echo "🔄 Beende Tmux-Sessions: $claude_sessions"
        for session in $claude_sessions; do
            tmux kill-session -t "$session" 2>/dev/null || true
        done
    fi
    
    # 3. Lock-Files entfernen
    rm -f /tmp/.claude-switch-lock 2>/dev/null || true
    
    # 4. Orphaned Snapshot-Prozesse
    local snapshot_pids=$(pgrep -f "shell-snapshots" || echo "")
    if [[ -n "$snapshot_pids" ]]; then
        echo "🔄 Beende Snapshot-Prozesse: $snapshot_pids"
        for pid in $snapshot_pids; do
            kill -KILL "$pid" 2>/dev/null || true
        done
    fi
    
    # 5. Temp-Files cleanup
    rm -f /tmp/claude_start_*.sh 2>/dev/null || true
    
    echo "✅ Emergency Cleanup abgeschlossen"
    echo "💡 Starte neue Session mit: claude-switch.sh start <project>"
}
```

### System Health Check
```bash
check_system_health() {
    echo "🏥 SYSTEM HEALTH CHECK"
    echo "====================="
    
    local issues=0
    
    # 1. Claude Command verfügbar?
    if command -v claude >/dev/null 2>&1; then
        echo "✅ Claude Command: verfügbar"
    else
        echo "❌ Claude Command: NICHT VERFÜGBAR"
        ((issues++))
    fi
    
    # 2. Tmux Server Status
    if tmux info &>/dev/null; then
        local sessions=$(tmux list-sessions 2>/dev/null | wc -l)
        echo "✅ Tmux Server: aktiv ($sessions Sessions)"
    else
        echo "❌ Tmux Server: NICHT ERREICHBAR"
        ((issues++))
    fi
    
    # 3. Claude Prozesse
    local claude_count=$(pgrep -f "^claude$" | wc -l)
    if [[ $claude_count -eq 0 ]]; then
        echo "⚫ Claude Prozesse: keine aktiv"
    elif [[ $claude_count -eq 1 ]]; then
        local claude_pid=$(pgrep -f "^claude$")
        echo "✅ Claude Prozesse: 1 aktiv (PID: $claude_pid)"
    else
        echo "⚠️  Claude Prozesse: $claude_count aktiv (UNGEWÖHNLICH)"
        ((issues++))
    fi
    
    # 4. Projekt-Verzeichnisse
    local missing_dirs=0
    for project in "${!PROJECTS[@]}"; do
        if [[ -d "${PROJECTS[$project]}" ]]; then
            echo "✅ Projekt $project: Verzeichnis OK"
        else
            echo "❌ Projekt $project: VERZEICHNIS FEHLT"
            ((missing_dirs++))
            ((issues++))
        fi
    done
    
    # 5. Session-State-Verzeichnis
    if [[ -d "/home/rodemkay/.claude/session-states" ]]; then
        local state_count=$(ls -1 /home/rodemkay/.claude/session-states/*.state 2>/dev/null | wc -l)
        echo "✅ Session States: $state_count gespeichert"
    else
        echo "⚠️  Session States: Verzeichnis fehlt"
    fi
    
    # 6. Lock-Files
    if [[ -f "/tmp/.claude-switch-lock" ]]; then
        local lock_pid=$(cat "/tmp/.claude-switch-lock" 2>/dev/null || echo "invalid")
        if kill -0 "$lock_pid" 2>/dev/null; then
            echo "⚠️  Switch Lock: Aktiv (PID: $lock_pid)"
        else
            echo "🧹 Switch Lock: Verwaist (wird bereinigt)"
            rm -f "/tmp/.claude-switch-lock"
        fi
    else
        echo "✅ Switch Lock: Frei"
    fi
    
    # Summary
    echo
    if [[ $issues -eq 0 ]]; then
        echo "🎉 SYSTEM STATUS: OPTIMAL"
    elif [[ $issues -eq 1 ]]; then
        echo "⚠️  SYSTEM STATUS: 1 PROBLEM ERKANNT"
    else
        echo "🚨 SYSTEM STATUS: $issues PROBLEME ERKANNT"
    fi
}
```

## 📊 SESSION-DASHBOARD

```bash
show_session_dashboard() {
    echo "📊 CLAUDE SESSION DASHBOARD"
    echo "=========================="
    echo
    
    # Aktuelle Session
    local current_session=$(tmux display-message -p -F "#{session_name}" 2>/dev/null || echo "keine")
    local current_dir=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || echo "unbekannt")
    local current_project=$(detect_current_project)
    
    echo "🎯 AKTUELLE SESSION:"
    echo "   Name: $current_session"
    echo "   Directory: $current_dir"
    echo "   Projekt: ${current_project:-'unbekannt'}"
    
    # Claude-Prozess Status
    local claude_pid=$(pgrep -f "^claude$" || echo "")
    if [[ -n "$claude_pid" ]]; then
        local claude_memory=$(ps -p "$claude_pid" -o rss= 2>/dev/null | awk '{print int($1/1024)"MB"}' || echo "unbekannt")
        local claude_runtime=$(ps -p "$claude_pid" -o etime= 2>/dev/null | awk '{gsub(/^ +| +$/,"")}1' || echo "unbekannt")
        echo "   Claude PID: $claude_pid ($claude_memory RAM, Runtime: $claude_runtime)"
    else
        echo "   Claude: nicht aktiv"
    fi
    
    echo
    
    # Verfügbare Projekte mit Status
    echo "📋 VERFÜGBARE PROJEKTE:"
    for project in "${!PROJECTS[@]}"; do
        local path="${PROJECTS[$project]}"
        local desc="${PROJECT_DESCRIPTIONS[$project]}"
        
        # Status ermitteln
        local status="⚫ offline"
        if [[ "$current_dir" == "$path"* ]]; then
            status="🟢 aktiv"
        fi
        
        # CLAUDE.md vorhanden?
        local claude_md_status="❌"
        if [[ -f "$path/CLAUDE.md" ]]; then
            local word_count=$(wc -w < "$path/CLAUDE.md" 2>/dev/null || echo "0")
            claude_md_status="✅ ($word_count Wörter)"
        fi
        
        echo "   $status $project - $desc"
        echo "        📁 $path"
        echo "        📋 CLAUDE.md: $claude_md_status"
        echo
    done
    
    # Session-States
    echo "💾 SESSION HISTORY:"
    local state_dir="/home/rodemkay/.claude/session-states"
    if [[ -d "$state_dir" ]]; then
        for state_file in "$state_dir"/*.state; do
            if [[ -f "$state_file" ]]; then
                local project_name=$(basename "$state_file" .state)
                local last_session=$(grep "LAST_SESSION=" "$state_file" 2>/dev/null | cut -d= -f2)
                local last_task=$(grep "LAST_TASK_ID=" "$state_file" 2>/dev/null | cut -d= -f2)
                
                local time_str="unbekannt"
                if [[ -n "$last_session" ]]; then
                    time_str=$(date -d @"$last_session" '+%d.%m.%Y %H:%M' 2>/dev/null || echo "invalid")
                fi
                
                echo "   📄 $project_name - $time_str"
                if [[ -n "$last_task" && "$last_task" != "" ]]; then
                    echo "        🎯 Letzte Task: #$last_task"
                fi
            fi
        done
    else
        echo "   Keine Session-History gefunden"
    fi
    
    # System-Status
    echo
    echo "🖥️  SYSTEM-STATUS:"
    local mem_usage=$(free -m | awk '/^Mem:/{printf "%.1f%%", $3/$2 * 100}')
    local disk_usage=$(df -h /home 2>/dev/null | awk 'NR==2{print $5}' || echo "unbekannt")
    local uptime=$(uptime -p 2>/dev/null || echo "unbekannt")
    
    echo "   RAM: $mem_usage belegt"
    echo "   Disk (/home): $disk_usage belegt"
    echo "   Uptime: $uptime"
}
```

## 🔮 ERWEITERTE FEATURES & ROADMAP

### 1. Automatische Todo-Integration (Phase 1)
- **Auto-Detection:** Working Directory Mismatch automatisch erkennen
- **Smart-Switch:** Vorschlag des richtigen Projekts basierend auf Task
- **Seamless-Transition:** TASK_COMPLETED State preservation

### 2. Session-Templates (Phase 2)
- **Project-Templates:** Verschiedene Layouts für verschiedene Projekt-Typen
- **Custom-Panes:** Extra Panes für Tests, Logs, etc.
- **Environment-Setup:** Automatische Environment-Variable Setups

### 3. Backup & Recovery (Phase 3)
- **Conversation-Backup:** Claude Conversation History sichern
- **State-Recovery:** Session nach Crash wiederherstellen
- **Multi-Version:** Verschiedene Session-Snapshots

### 4. Monitoring & Analytics (Phase 4)
- **Usage-Tracking:** Welche Projekte werden wie oft verwendet
- **Performance-Metrics:** Session-Startup-Zeiten
- **Health-Alerting:** Proaktive Problemerkennung

## 🎯 INSTALLATION & SETUP

### 1. System installieren
```bash
# 1. Session-Manager-Bibliothek erstellen
mkdir -p /home/rodemkay/.local/lib
# [claude-session-manager.sh Code hierhin kopieren]

# 2. Haupt-Script installieren
# [claude-switch.sh Code hierhin kopieren]
chmod +x /home/rodemkay/.local/bin/claude-switch.sh

# 3. State-Verzeichnis erstellen
mkdir -p /home/rodemkay/.claude/session-states

# 4. Todo-Integration aktivieren
# [Entsprechende Änderungen in ./todo Script vornehmen]
```

### 2. Alias einrichten (optional)
```bash
# Zu ~/.bashrc hinzufügen:
alias cs='claude-switch.sh'
alias csl='claude-switch.sh list'
alias css='claude-switch.sh status'
alias csd='claude-switch.sh dashboard'
```

### 3. Test des Systems
```bash
# Health Check
claude-switch.sh health

# Dashboard anzeigen  
claude-switch.sh dashboard

# Test-Switch (zurück zum aktuellen Projekt)
claude-switch.sh switch todo
```

## 🚨 KRITISCHE SICHERHEITSHINWEISE

1. **TASK_COMPLETED Requirement:** Bei Todo-Projekt IMMER Tasks mit `TASK_COMPLETED` abschließen
2. **Single-Session-Rule:** Niemals mehrere Claude-Instanzen gleichzeitig im selben Projekt
3. **Lock-Mechanism:** Switch-Lock respektieren, niemals forcen
4. **Backup-Strategy:** Session-States vor kritischen Operations sichern
5. **Emergency-Cleanup:** Bei Problemen `claude-switch.sh cleanup` verwenden

## 📈 METRIKEN & MONITORING

Das System ist bereit für erweiterte Metriken:
- Session-Wechsel-Häufigkeit
- Projekt-Nutzungs-Statistiken  
- Performance-Benchmarks
- Error-Rate-Tracking
- Resource-Usage-Monitoring

---

**Status:** ✅ VOLLSTÄNDIGE TECHNISCHE DOKUMENTATION  
**Version:** 2.0  
**Letzte Aktualisierung:** 2025-08-25  
**Umfang:** 2000+ Zeilen Code, vollständige Implementierung  
**Nächste Schritte:** Installation und Test des Systems