#!/bin/bash

# tmux Controller für Claude Session System
# Version: 1.0
# Datum: 2025-01-21

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') TMUX-CONTROLLER: $*"
}

# Error Handler
error_exit() {
    echo -e "${RED}FEHLER: $1${NC}" >&2
    log "ERROR: $1"
    exit 1
}

# Aktuelle Session anzeigen
show_current() {
    if tmux list-sessions 2>/dev/null | grep -q "(attached)"; then
        tmux display-message -p '#S' 2>/dev/null || echo "none"
    else
        # Erste verfügbare Session
        tmux list-sessions -F '#{session_name}' 2>/dev/null | head -1 || echo "none"
    fi
}

# Health Check durchführen
health_check() {
    local health=0
    
    # tmux verfügbar?
    if ! command -v tmux >/dev/null 2>&1; then
        echo "FAILED: tmux not available"
        return 1
    fi
    
    # tmux server läuft?
    if ! tmux list-sessions >/dev/null 2>&1; then
        echo "WARNING: no active sessions"
        health=1
    fi
    
    # Claude Session Check - SIMPLIFIED (ohne störenden Test)
    if tmux has-session -t "claude" 2>/dev/null; then
        # Prüfe nur ob Session existiert, KEIN störender echo-Test mehr
        local pane_count=$(tmux list-panes -t "claude" 2>/dev/null | wc -l)
        if [ "$pane_count" -gt 0 ]; then
            echo "OK"
            return 0
        else
            echo "WARNING: claude session has no panes"
            health=1
        fi
    else
        echo "INFO: no claude session"
    fi
    
    if (( health == 0 )); then
        echo "OK"
        return 0
    else
        echo "WARNING"
        return 1
    fi
}

# Alle Sessions beenden
kill_all_sessions() {
    if tmux list-sessions >/dev/null 2>&1; then
        log "Beende alle tmux Sessions"
        
        # Graceful: Erst alle Sessions einzeln beenden
        while IFS= read -r session; do
            if [[ -n "$session" ]]; then
                log "Beende Session: $session"
                tmux kill-session -t "$session" 2>/dev/null || true
            fi
        done < <(tmux list-sessions -F '#{session_name}' 2>/dev/null)
        
        # Falls immer noch Sessions da sind: Server beenden
        if tmux list-sessions >/dev/null 2>&1; then
            log "Beende tmux Server"
            tmux kill-server 2>/dev/null || true
        fi
        
        echo "All sessions terminated"
        log "Alle Sessions beendet"
    else
        echo "No active sessions to terminate"
        log "Keine aktiven Sessions gefunden"
    fi
}

# Session reparieren
repair_sessions() {
    log "Starte Session-Reparatur"
    
    # 1. Hängende tmux Prozesse beenden
    if pgrep -f "tmux.*claude" >/dev/null 2>&1; then
        log "Beende hängende tmux Prozesse"
        pkill -f "tmux.*claude" 2>/dev/null || true
        sleep 2
    fi
    
    # 2. Stale socket files entfernen
    local socket_dirs=("/tmp/tmux-$UID" "/var/folders/*/T/tmux-$UID" "/tmp/tmux-*")
    for pattern in "${socket_dirs[@]}"; do
        for dir in $pattern; do
            if [[ -d "$dir" ]]; then
                log "Bereinige Socket-Verzeichnis: $dir"
                find "$dir" -name "default" -type s -mmin +60 -delete 2>/dev/null || true
            fi
        done
    done
    
    # 3. Lock-Dateien bereinigen
    for lock_file in /tmp/claude_session_lock*; do
        if [[ -f "$lock_file" ]]; then
            local age=$(stat -c %Y "$lock_file" 2>/dev/null || echo "0")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            
            if (( age_min > 30 )); then
                log "Entferne alten Lock: $lock_file (${age_min}min)"
                rm -f "$lock_file"
            fi
        fi
    done
    
    # 4. TASK_COMPLETED bereinigen
    if [[ -f "/tmp/TASK_COMPLETED" ]]; then
        local age=$(stat -c %Y "/tmp/TASK_COMPLETED" 2>/dev/null || echo "0")
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        
        if (( age_min > 10 )); then
            log "Entferne alten TASK_COMPLETED (${age_min}min)"
            rm -f "/tmp/TASK_COMPLETED"
        fi
    fi
    
    # 5. tmux Server neu starten falls nötig
    if ! tmux list-sessions >/dev/null 2>&1; then
        log "tmux Server neu gestartet"
        # Server wird automatisch beim nächsten tmux-Befehl gestartet
    fi
    
    echo "Session repair completed"
    log "Session-Reparatur abgeschlossen"
}

# Session attach
attach_session() {
    local session_name="${1:-claude}"
    
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        error_exit "Session '$session_name' nicht gefunden"
    fi
    
    log "Attaching zu Session: $session_name"
    echo "Attaching to session: $session_name"
    
    # Im Hintergrund attachen um Script nicht zu blockieren
    exec tmux attach-session -t "$session_name"
}

# Session erstellen
create_session() {
    local session_name="${1:-claude}"
    local working_dir="${2:-$PWD}"
    
    if tmux has-session -t "$session_name" 2>/dev/null; then
        echo "Session '$session_name' bereits vorhanden"
        return 0
    fi
    
    if [[ ! -d "$working_dir" ]]; then
        error_exit "Arbeitsverzeichnis nicht gefunden: $working_dir"
    fi
    
    log "Erstelle Session: $session_name in $working_dir"
    
    if tmux new-session -d -s "$session_name" -c "$working_dir"; then
        echo "Session '$session_name' erstellt"
        log "Session erstellt: $session_name"
        
        # Grundkonfiguration
        tmux send-keys -t "$session_name" "clear" Enter
        tmux send-keys -t "$session_name" "echo 'tmux session ready in $(pwd)'" Enter
        
        return 0
    else
        error_exit "Session konnte nicht erstellt werden: $session_name"
    fi
}

# Sessions auflisten
list_sessions() {
    if tmux list-sessions >/dev/null 2>&1; then
        tmux list-sessions
    else
        echo "No active sessions"
    fi
}

# Hilfe anzeigen
show_help() {
    cat << 'EOF'
tmux Controller - Hilfe

VERWENDUNG:
  ./tmux-controller.sh [BEFEHL] [OPTIONEN]

BEFEHLE:
  current               Zeigt aktuelle Session
  health                Führt Health Check durch
  kill-all              Beendet alle Sessions
  repair                Repariert Session-Probleme
  attach [session]      Attached zu Session (default: claude)
  create <name> [dir]   Erstellt neue Session
  list                  Listet alle Sessions
  help                  Zeigt diese Hilfe

BEISPIELE:
  ./tmux-controller.sh current
  ./tmux-controller.sh health
  ./tmux-controller.sh attach claude
  ./tmux-controller.sh create test-session /tmp
  ./tmux-controller.sh repair

RÜCKGABEWERTE:
  0    Erfolgreich
  1    Fehler aufgetreten

EOF
}

# Hauptfunktion
main() {
    local command="${1:-help}"
    
    case "$command" in
        "current")
            show_current
            ;;
        "health")
            health_check
            ;;
        "kill-all")
            kill_all_sessions
            ;;
        "repair")
            repair_sessions
            ;;
        "attach")
            attach_session "${2:-claude}"
            ;;
        "create")
            if [[ -z "${2:-}" ]]; then
                error_exit "Session-Name erforderlich"
            fi
            create_session "$2" "${3:-$PWD}"
            ;;
        "list")
            list_sessions
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            echo -e "${RED}Unbekannter Befehl: $command${NC}" >&2
            echo
            show_help
            exit 1
            ;;
    esac
}

# Script ausführen
main "$@"