#!/bin/bash

# Session Manager for Claude Todo System
# Manages tmux sessions for different projects

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Pfade und Konfiguration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECTS_BASE="/home/rodemkay/www/react"
LOCK_FILE="/tmp/claude_session_lock"

# Logging
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') SESSION-MANAGER: $*" >> "$SCRIPT_DIR/logs/session-manager.log"
}

# Error Handler
error_exit() {
    echo -e "${RED}FEHLER: $1${NC}" >&2
    log "ERROR: $1"
    exit 1
}

# Session erstellen
create_session() {
    local session_name="$1"
    local working_dir="$2"
    
    log "Creating session: $session_name in $working_dir"
    
    # Prüfen ob Session bereits existiert
    if tmux has-session -t "$session_name" 2>/dev/null; then
        echo -e "${YELLOW}Session '$session_name' bereits vorhanden${NC}"
        return 0
    fi
    
    # Arbeitsverzeichnis prüfen
    if [[ ! -d "$working_dir" ]]; then
        error_exit "Arbeitsverzeichnis nicht gefunden: $working_dir"
    fi
    
    # Session erstellen
    if tmux new-session -d -s "$session_name" -c "$working_dir"; then
        echo -e "${GREEN}✓ Session '$session_name' erstellt${NC}"
        log "Session created: $session_name"
        
        # Grundkonfiguration für Claude-Session
        if [[ "$session_name" == "claude" ]]; then
            tmux send-keys -t "$session_name" "clear" Enter
            tmux send-keys -t "$session_name" "echo 'Claude Session bereit in $(pwd)'" Enter
        fi
        
        return 0
    else
        error_exit "Session konnte nicht erstellt werden: $session_name"
    fi
}

# Session wechseln
switch_session() {
    local project_name="$1"
    local session_name="claude"  # Immer "claude" Session verwenden
    
    log "Switching to project: $project_name"
    
    # Projekt-Verzeichnis bestimmen
    local project_dir
    case "$project_name" in
        "plugin-todo")
            project_dir="$PROJECTS_BASE/plugin-todo"
            ;;
        "plugin-article")
            project_dir="$PROJECTS_BASE/plugin-article"
            ;;
        "forexsignale-magazine")
            project_dir="$PROJECTS_BASE/forexsignale-magazine"
            ;;
        *)
            project_dir="$PROJECTS_BASE/$project_name"
            ;;
    esac
    
    # Verzeichnis prüfen
    if [[ ! -d "$project_dir" ]]; then
        error_exit "Projekt-Verzeichnis nicht gefunden: $project_dir"
    fi
    
    # Lock setzen
    echo "$project_name" > "$LOCK_FILE"
    
    # Session erstellen falls nicht vorhanden
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        create_session "$session_name" "$project_dir"
    else
        # Existierende Session zu neuem Verzeichnis wechseln
        echo -e "${BLUE}Wechsle Arbeitsverzeichnis zu: $project_dir${NC}"
        tmux send-keys -t "$session_name" "cd '$project_dir'" Enter
        tmux send-keys -t "$session_name" "clear" Enter
        tmux send-keys -t "$session_name" "echo 'Switched to project: $project_name'" Enter
        tmux send-keys -t "$session_name" "pwd" Enter
    fi
    
    log "Switch completed: $project_name"
    echo -e "${GREEN}✓ Session zu Projekt '$project_name' gewechselt${NC}"
    
    return 0
}

# Session attach
attach_session() {
    local session_name="${1:-claude}"
    
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        error_exit "Session '$session_name' nicht gefunden"
    fi
    
    echo -e "${BLUE}Attaching to session: $session_name${NC}"
    tmux attach-session -t "$session_name"
}

# Aktuelle Session anzeigen
current_session() {
    if tmux list-sessions 2>/dev/null | grep -q "claude"; then
        echo "claude"
        return 0
    fi
    
    local active=$(tmux display-message -p '#S' 2>/dev/null || echo "none")
    echo "$active"
}

# Session beenden
kill_session() {
    local session_name="${1:-claude}"
    
    if tmux has-session -t "$session_name" 2>/dev/null; then
        tmux kill-session -t "$session_name"
        echo -e "${GREEN}✓ Session '$session_name' beendet${NC}"
        log "Session killed: $session_name"
        
        # Lock entfernen
        rm -f "$LOCK_FILE"
    else
        echo -e "${YELLOW}Session '$session_name' nicht gefunden${NC}"
    fi
}

# Alle Sessions beenden
kill_all_sessions() {
    if tmux list-sessions >/dev/null 2>&1; then
        tmux kill-server
        echo -e "${GREEN}✓ Alle Sessions beendet${NC}"
        log "All sessions killed"
        
        # Alle Locks entfernen
        rm -f "$LOCK_FILE"
        rm -f /tmp/claude_*.lock
    else
        echo -e "${YELLOW}Keine aktiven Sessions gefunden${NC}"
    fi
}

# Session Health Check
health_check() {
    local health=true
    
    echo -e "${BLUE}🏥 Session Health Check${NC}"
    echo
    
    # tmux verfügbar?
    if ! command -v tmux >/dev/null 2>&1; then
        echo -e "${RED}❌ tmux nicht verfügbar${NC}"
        health=false
    else
        echo -e "${GREEN}✅ tmux verfügbar${NC}"
    fi
    
    # Claude Session Check
    if tmux has-session -t "claude" 2>/dev/null; then
        echo -e "${GREEN}✅ Claude Session aktiv${NC}"
        
        # Session responsiveness test
        if timeout 3 tmux send-keys -t "claude" "echo test" Enter 2>/dev/null; then
            echo -e "${GREEN}✅ Session reagiert${NC}"
        else
            echo -e "${RED}❌ Session hängt${NC}"
            health=false
        fi
    else
        echo -e "${YELLOW}⚠️  Keine Claude Session${NC}"
    fi
    
    # Lock Status
    if [[ -f "$LOCK_FILE" ]]; then
        local lock_content=$(cat "$LOCK_FILE" 2>/dev/null || echo "unreadable")
        echo -e "${BLUE}🔒 Session Lock: $lock_content${NC}"
    else
        echo -e "${GREEN}🔓 Kein Session Lock${NC}"
    fi
    
    echo
    if $health; then
        echo -e "${GREEN}🟢 Session Health: OK${NC}"
        return 0
    else
        echo -e "${RED}🔴 Session Health: PROBLEME${NC}"
        return 1
    fi
}

# Session reparieren
repair_session() {
    echo -e "${YELLOW}🔧 Repariere Session...${NC}"
    log "Session repair initiated"
    
    # Hung session beenden
    if tmux has-session -t "claude" 2>/dev/null; then
        if ! timeout 3 tmux send-keys -t "claude" "echo test" Enter 2>/dev/null; then
            echo -e "${YELLOW}Beende hängende Claude Session...${NC}"
            tmux kill-session -t "claude" 2>/dev/null || true
        fi
    fi
    
    # Alte Locks entfernen
    if [[ -f "$LOCK_FILE" ]]; then
        local age=$(stat -c %Y "$LOCK_FILE")
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        
        if (( age_min > 30 )); then
            echo -e "${YELLOW}Entferne alten Lock (${age_min}m)...${NC}"
            rm -f "$LOCK_FILE"
        fi
    fi
    
    # TASK_COMPLETED sicherstellen
    if [[ ! -f "/tmp/TASK_COMPLETED" ]]; then
        echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
        echo -e "${GREEN}TASK_COMPLETED gesetzt${NC}"
    fi
    
    log "Session repair completed"
    echo -e "${GREEN}✓ Session-Reparatur abgeschlossen${NC}"
}

# Sessions auflisten
list_sessions() {
    echo -e "${BLUE}📋 Aktive tmux Sessions:${NC}"
    echo
    
    if ! tmux list-sessions 2>/dev/null; then
        echo -e "${YELLOW}Keine aktiven Sessions${NC}"
        return 0
    fi
}

# Hilfe anzeigen
show_help() {
    cat << 'EOF'
Session Manager - Hilfe

VERWENDUNG:
  ./session-manager.sh [BEFEHL] [OPTIONEN]

BEFEHLE:
  create <name> <dir>   Erstellt neue Session mit Namen und Arbeitsverzeichnis
  switch <projekt>      Wechselt zur Claude Session für angegebenes Projekt
  attach [session]      Attached zu Session (default: claude)
  current               Zeigt aktuelle Session
  kill [session]        Beendet Session (default: claude)  
  kill-all              Beendet alle Sessions
  list                  Listet aktive Sessions
  health                Führt Health Check durch
  repair                Repariert Session-Probleme
  help                  Zeigt diese Hilfe

BEISPIELE:
  ./session-manager.sh switch plugin-todo
  ./session-manager.sh create test-session /tmp
  ./session-manager.sh attach claude
  ./session-manager.sh health

EOF
}

# Hauptfunktion
main() {
    # Logs-Verzeichnis sicherstellen
    mkdir -p "$SCRIPT_DIR/logs"
    
    local command="${1:-help}"
    
    case "$command" in
        "create")
            if [[ -z "${2:-}" || -z "${3:-}" ]]; then
                error_exit "Session-Name und Arbeitsverzeichnis erforderlich"
            fi
            create_session "$2" "$3"
            ;;
        "switch")
            if [[ -z "${2:-}" ]]; then
                error_exit "Projekt-Name erforderlich"
            fi
            switch_session "$2"
            ;;
        "attach")
            attach_session "${2:-claude}"
            ;;
        "current")
            current_session
            ;;
        "kill")
            kill_session "${2:-claude}"
            ;;
        "kill-all")
            kill_all_sessions
            ;;
        "list")
            list_sessions
            ;;
        "health")
            health_check
            ;;
        "repair")
            repair_session
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            echo -e "${RED}Unbekannter Befehl: $command${NC}" >&2
            show_help
            exit 1
            ;;
    esac
}

# Script ausführen
main "$@"