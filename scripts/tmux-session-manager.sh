#!/bin/bash

# TMUX SESSION MANAGER - Robuste Claude-Projekt-Wechsel-Lösung
# Version: 3.0 - Fokus auf tmux-spezifische Aspekte
# Datum: 2025-08-24

set -euo pipefail

# =============================================================================
# KONFIGURATION & CONSTANTS
# =============================================================================

readonly SCRIPT_NAME="$(basename "$0")"
readonly LOG_FILE="/tmp/tmux-session-manager.log"
readonly STATE_DIR="/home/rodemkay/.claude/session-states"
readonly LOCK_FILE="/tmp/.tmux-session-manager.lock"

# Farben für bessere UX
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly PURPLE='\033[0;35m'
readonly NC='\033[0m' # No Color

# tmux-spezifische Konfiguration
readonly TMUX_SESSION_PREFIX="claude"
readonly DEFAULT_WINDOW_NAME="react"
readonly LEFT_PANE_WIDTH=90  # Prozent für das linke Pane
readonly RIGHT_PANE_WIDTH=10 # Prozent für das rechte Pane

# Projekt-Definitionen (erweitert)
declare -A PROJECTS=(
    ["todo"]="/home/rodemkay/www/react/plugin-todo"
    ["article"]="/home/rodemkay/www/react/plugin-article"
    ["forexsignale"]="/home/rodemkay/www/react"
    ["wp-todos"]="/home/rodemkay/www/react/plugin-wp-project-todos"
    ["development"]="/home/rodemkay/www/react/development"
    ["staging"]="/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging"
)

declare -A PROJECT_DESCRIPTIONS=(
    ["todo"]="Todo System Plugin V3.0 - Hauptentwicklung"
    ["article"]="Article Builder Plugin - MCP Integration"
    ["forexsignale"]="ForexSignale Magazine - Root Projekt"
    ["wp-todos"]="Legacy WP Project Todos - Archiviert"
    ["development"]="Development Environment - Testing"
    ["staging"]="Staging Environment - Live Tests"
)

# =============================================================================
# LOGGING & ERROR HANDLING
# =============================================================================

log() {
    local level="$1"
    shift
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $*" | tee -a "$LOG_FILE"
}

log_info() { log "INFO" "$@"; }
log_warn() { log "WARN" "$@"; }
log_error() { log "ERROR" "$@"; }
log_debug() { log "DEBUG" "$@"; }

die() {
    log_error "$@"
    echo -e "${RED}❌ FATAL: $*${NC}" >&2
    cleanup_on_exit
    exit 1
}

warn() {
    log_warn "$@"
    echo -e "${YELLOW}⚠️  WARNING: $*${NC}" >&2
}

info() {
    log_info "$@"
    echo -e "${GREEN}ℹ️  INFO: $*${NC}"
}

debug() {
    [[ "${DEBUG:-0}" == "1" ]] && {
        log_debug "$@"
        echo -e "${BLUE}🐛 DEBUG: $*${NC}" >&2
    }
}

# Cleanup bei Script-Ende
cleanup_on_exit() {
    [[ -f "$LOCK_FILE" ]] && rm -f "$LOCK_FILE"
    debug "Cleanup completed"
}

trap cleanup_on_exit EXIT INT TERM

# =============================================================================
# LOCK-MECHANISMUS für atomare Operationen
# =============================================================================

acquire_lock() {
    local timeout="${1:-30}"
    local count=0
    
    while [[ $count -lt $timeout ]]; do
        if (set -C; echo $$ > "$LOCK_FILE") 2>/dev/null; then
            debug "Lock acquired (PID: $$)"
            return 0
        fi
        
        # Prüfe ob Lock-Prozess noch existiert
        if [[ -f "$LOCK_FILE" ]]; then
            local lock_pid
            lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
            if [[ -n "$lock_pid" ]] && ! kill -0 "$lock_pid" 2>/dev/null; then
                warn "Removing stale lock file (dead PID: $lock_pid)"
                rm -f "$LOCK_FILE"
                continue
            fi
        fi
        
        sleep 1
        ((count++))
    done
    
    die "Could not acquire lock after $timeout seconds"
}

release_lock() {
    [[ -f "$LOCK_FILE" ]] && rm -f "$LOCK_FILE"
    debug "Lock released"
}

# =============================================================================
# TMUX SESSION KONTROLLE
# =============================================================================

# Session-Namen generieren
generate_session_name() {
    local project="$1"
    echo "${TMUX_SESSION_PREFIX}-${project}"
}

# Prüfe ob tmux-Server läuft
ensure_tmux_server() {
    if ! tmux info &>/dev/null; then
        info "Starting tmux server..."
        tmux start-server || die "Failed to start tmux server"
    fi
    debug "tmux server is running"
}

# Aktuelle Claude-Session ermitteln
get_current_session() {
    local session_name
    local current_path
    local claude_pid
    
    # tmux Session-Info sammeln
    if session_name=$(tmux display-message -p -F "#{session_name}" 2>/dev/null); then
        current_path=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || echo "unknown")
        
        # Prüfe ob es eine Claude-Session ist
        if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
            local project_name="${session_name#${TMUX_SESSION_PREFIX}-}"
            
            echo -e "${CYAN}📊 AKTUELLE SESSION:${NC}"
            echo "   Session: $session_name"
            echo "   Projekt: $project_name"
            echo "   Pfad: $current_path"
            
            # Claude-Prozess-Info
            claude_pid=$(pgrep -f "^claude$" 2>/dev/null || echo "")
            if [[ -n "$claude_pid" ]]; then
                local memory_mb
                memory_mb=$(ps -p "$claude_pid" -o rss= 2>/dev/null | awk '{print int($1/1024)}' || echo "?")
                echo "   Claude PID: $claude_pid (${memory_mb}MB)"
            else
                echo "   Claude: nicht aktiv"
            fi
            
            return 0
        else
            echo -e "${YELLOW}⚠️  Session '$session_name' ist keine Claude-Session${NC}"
            return 1
        fi
    else
        echo -e "${RED}❌ Keine aktive tmux-Session gefunden${NC}"
        return 1
    fi
}

# Liste alle Claude-Sessions
list_claude_sessions() {
    local sessions=()
    local session_info
    
    echo -e "${BLUE}📋 CLAUDE SESSIONS:${NC}"
    
    if ! session_info=$(tmux list-sessions 2>/dev/null); then
        echo "   Keine aktiven Sessions"
        return 1
    fi
    
    local found_sessions=0
    while IFS= read -r line; do
        local session_name
        session_name=$(echo "$line" | cut -d: -f1)
        
        if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
            local project_name="${session_name#${TMUX_SESSION_PREFIX}-}"
            local status="unknown"
            
            # Session-Status ermitteln
            if echo "$line" | grep -q "attached"; then
                status="🟢 aktiv"
            else
                status="🟡 detached"
            fi
            
            echo "   $status $session_name -> ${PROJECT_DESCRIPTIONS[$project_name]:-'Unknown Project'}"
            sessions+=("$session_name")
            ((found_sessions++))
        fi
    done <<< "$session_info"
    
    if [[ $found_sessions -eq 0 ]]; then
        echo "   Keine Claude-Sessions gefunden"
        return 1
    fi
    
    return 0
}

# =============================================================================
# SESSION STATE MANAGEMENT
# =============================================================================

# Session-Zustand speichern
save_session_state() {
    local project="$1"
    local session_name
    session_name=$(generate_session_name "$project")
    
    mkdir -p "$STATE_DIR"
    local state_file="$STATE_DIR/${project}.state"
    
    # Sammle Session-Informationen
    local window_layout=""
    local pane_info=""
    local current_path=""
    
    if tmux has-session -t "$session_name" 2>/dev/null; then
        window_layout=$(tmux list-windows -t "$session_name" -F "#{window_layout}" 2>/dev/null | head -1 || echo "")
        pane_info=$(tmux list-panes -t "$session_name" -F "#{pane_id}:#{pane_width}x#{pane_height}" 2>/dev/null || echo "")
        current_path=$(tmux display-message -t "$session_name" -p -F "#{pane_current_path}" 2>/dev/null || echo "")
    fi
    
    # State-File schreiben
    cat > "$state_file" << EOF
# tmux Session State for Project: $project
# Saved: $(date)
PROJECT="$project"
SESSION_NAME="$session_name"
WORKING_DIR="${PROJECTS[$project]}"
CURRENT_PATH="$current_path"
LAST_SAVE=$(date +%s)
WINDOW_LAYOUT="$window_layout"
PANE_INFO="$pane_info"
CLAUDE_PID=$(pgrep -f "^claude$" 2>/dev/null || echo "")
EOF

    debug "Session state saved to: $state_file"
    info "Session-State für $project gespeichert"
}

# Session-State laden
load_session_state() {
    local project="$1"
    local state_file="$STATE_DIR/${project}.state"
    
    if [[ -f "$state_file" ]]; then
        source "$state_file"
        
        local time_str="unknown"
        if [[ -n "${LAST_SAVE:-}" ]]; then
            time_str=$(date -d "@$LAST_SAVE" '+%d.%m.%Y %H:%M' 2>/dev/null || echo "invalid")
        fi
        
        echo -e "${CYAN}📁 SESSION STATE GEFUNDEN:${NC}"
        echo "   Projekt: $PROJECT"
        echo "   Letzte Session: $time_str"
        echo "   Working Dir: $WORKING_DIR"
        [[ -n "${CLAUDE_PID:-}" ]] && echo "   Letzte Claude PID: $CLAUDE_PID"
        
        return 0
    else
        debug "No session state found for $project"
        return 1
    fi
}

# =============================================================================
# SESSION STARTUP & CREATION
# =============================================================================

# Optimales Pane-Layout berechnen
calculate_pane_layout() {
    local total_width
    total_width=$(tmux display-message -p '#{window_width}' 2>/dev/null || echo "120")
    
    local left_width=$(( total_width * LEFT_PANE_WIDTH / 100 ))
    local right_width=$(( total_width - left_width ))
    
    # Mindestbreiten einhalten
    [[ $right_width -lt 12 ]] && right_width=12
    [[ $left_width -lt 40 ]] && left_width=40
    
    echo "$right_width"
}

# Neue Session mit optimiertem Layout erstellen
create_new_session() {
    local project="$1"
    local working_dir="${PROJECTS[$project]}"
    local session_name
    session_name=$(generate_session_name "$project")
    
    debug "Creating new session: $session_name in $working_dir"
    
    # Verzeichnis-Validierung
    [[ ! -d "$working_dir" ]] && die "Working directory does not exist: $working_dir"
    
    # Session bereits vorhanden?
    if tmux has-session -t "$session_name" 2>/dev/null; then
        warn "Session $session_name bereits vorhanden, wird überschrieben"
        tmux kill-session -t "$session_name" 2>/dev/null || true
        sleep 1
    fi
    
    # Neue Session erstellen mit zwei Panes
    info "Erstelle tmux-Session: $session_name"
    
    # Hauptsession mit erstem Pane (links, für Claude)
    tmux new-session -d -s "$session_name" -n "$DEFAULT_WINDOW_NAME" -c "$working_dir" \
        'echo "Initializing Claude session..."; bash -i'
    
    # Zweites Pane hinzufügen (rechts, für Befehle)
    tmux split-window -h -t "$session_name:$DEFAULT_WINDOW_NAME" -c "$working_dir" \
        'echo "Terminal ready"; bash -i'
    
    # Pane-Größen optimieren
    local right_pane_width
    right_pane_width=$(calculate_pane_layout)
    
    # Resize versuchen (verschiedene tmux-Versionen)
    tmux resize-pane -t "$session_name:$DEFAULT_WINDOW_NAME.1" -x "$right_pane_width" 2>/dev/null || \
    tmux resize-pane -t "$session_name:$DEFAULT_WINDOW_NAME.1" -l "$right_pane_width" 2>/dev/null || \
    debug "Could not resize pane (tmux version compatibility)"
    
    debug "Session layout configured: Left=${LEFT_PANE_WIDTH}%, Right=${RIGHT_PANE_WIDTH}%"
    
    return 0
}

# Claude im linken Pane starten
start_claude_in_session() {
    local project="$1"
    local session_name
    session_name=$(generate_session_name "$project")
    
    debug "Starting Claude in session: $session_name"
    
    # Prüfe ob Session existiert
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        die "Session $session_name not found"
    fi
    
    # Claude im linken Pane (Pane 0) starten
    if command -v claude >/dev/null 2>&1; then
        info "Starte Claude Code CLI in linkem Pane..."
        
        # Fokus auf linkes Pane setzen
        tmux select-window -t "$session_name:$DEFAULT_WINDOW_NAME"
        tmux select-pane -t "$session_name:$DEFAULT_WINDOW_NAME.0"
        
        # Claude starten mit Resume und Skip-Permissions
        tmux send-keys -t "$session_name:$DEFAULT_WINDOW_NAME.0" \
            'claude -resume --dangerously-skip-permissions' C-m
        
        # Kurz warten für Claude-Startup
        sleep 2
        
        # Verify Claude is running
        local claude_pid
        claude_pid=$(pgrep -f "^claude$" 2>/dev/null || echo "")
        if [[ -n "$claude_pid" ]]; then
            info "Claude erfolgreich gestartet (PID: $claude_pid)"
        else
            warn "Claude-Prozess nicht erkannt (möglicherweise noch am starten)"
        fi
    else
        die "Claude command not found in PATH"
    fi
}

# =============================================================================
# SESSION SHUTDOWN & CLEANUP
# =============================================================================

# Sichere Session-Beendigung
terminate_session_safely() {
    local project="$1"
    local session_name
    session_name=$(generate_session_name "$project")
    
    info "Beende Session sicher: $session_name"
    
    # Session vorhanden?
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        debug "Session $session_name not found, nothing to terminate"
        return 0
    fi
    
    # State VORHER speichern
    save_session_state "$project"
    
    # Todo-System spezielle Behandlung
    if [[ "$project" == "todo" ]]; then
        check_todo_completion_state "$session_name"
    fi
    
    # Claude-Prozess graceful beenden
    terminate_claude_process
    
    # Session beenden
    debug "Killing tmux session: $session_name"
    tmux kill-session -t "$session_name" 2>/dev/null || {
        warn "Could not kill session $session_name"
        return 1
    }
    
    info "Session $session_name erfolgreich beendet"
    return 0
}

# Todo-spezifische Completion-Prüfung
check_todo_completion_state() {
    local session_name="$1"
    
    debug "Checking Todo completion state"
    
    # Prüfe TASK_COMPLETED Flag
    if [[ ! -f "/tmp/TASK_COMPLETED" ]]; then
        warn "TASK_COMPLETED flag missing!"
        echo -e "${YELLOW}⚠️  Aktuelle Todo-Session scheint unvollständig zu sein.${NC}"
        echo -e "${YELLOW}   Fehlende TASK_COMPLETED-Datei kann zu Hook-Violations führen.${NC}"
        
        read -p "Session trotzdem beenden? (y/N): " -n 1 -r
        echo
        
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            die "Session termination cancelled by user"
        fi
        
        warn "User chose to continue despite missing TASK_COMPLETED"
    else
        debug "TASK_COMPLETED flag present"
    fi
}

# Claude-Prozess beenden
terminate_claude_process() {
    local claude_pids
    claude_pids=$(pgrep -f "^claude$" 2>/dev/null || echo "")
    
    if [[ -z "$claude_pids" ]]; then
        debug "No Claude processes found"
        return 0
    fi
    
    info "Beende Claude-Prozesse: $claude_pids"
    
    # Graceful shutdown versuchen
    for pid in $claude_pids; do
        debug "Sending TERM signal to Claude PID: $pid"
        kill -TERM "$pid" 2>/dev/null || continue
    done
    
    # Wait for graceful shutdown
    sleep 3
    
    # Force kill falls nötig
    for pid in $claude_pids; do
        if kill -0 "$pid" 2>/dev/null; then
            warn "Force killing Claude PID: $pid"
            kill -KILL "$pid" 2>/dev/null || true
        fi
    done
    
    # Verification
    sleep 1
    local remaining
    remaining=$(pgrep -f "^claude$" 2>/dev/null || echo "")
    if [[ -n "$remaining" ]]; then
        warn "Some Claude processes could not be terminated: $remaining"
        return 1
    fi
    
    debug "All Claude processes terminated successfully"
    return 0
}

# Zombie-Session Detection und Cleanup
cleanup_zombie_sessions() {
    info "Scanning for zombie sessions..."
    
    local cleaned=0
    
    # Alle tmux-Sessions durchgehen
    while IFS= read -r session_line; do
        local session_name
        session_name=$(echo "$session_line" | cut -d: -f1)
        
        # Nur Claude-Sessions prüfen
        if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
            # Prüfe ob Session responsive ist
            if ! tmux list-panes -t "$session_name" &>/dev/null; then
                warn "Removing zombie session: $session_name"
                tmux kill-session -t "$session_name" 2>/dev/null || true
                ((cleaned++))
            fi
        fi
    done < <(tmux list-sessions 2>/dev/null || echo "")
    
    info "Cleaned $cleaned zombie sessions"
    
    # Orphaned Prozesse cleanup
    cleanup_orphaned_processes
}

# Orphaned/Zombie Prozesse bereinigen
cleanup_orphaned_processes() {
    debug "Cleaning up orphaned processes"
    
    # Shell-snapshot Prozesse (häufige Claude-Zombies)
    local snapshot_pids
    snapshot_pids=$(pgrep -f "shell-snapshots.*python3" 2>/dev/null || echo "")
    if [[ -n "$snapshot_pids" ]]; then
        warn "Killing orphaned snapshot processes: $snapshot_pids"
        for pid in $snapshot_pids; do
            kill -TERM "$pid" 2>/dev/null || kill -KILL "$pid" 2>/dev/null || true
        done
    fi
    
    # Hängende tmux send-keys Prozesse
    local tmux_pids
    tmux_pids=$(pgrep -f "tmux send-keys" 2>/dev/null || echo "")
    if [[ -n "$tmux_pids" ]]; then
        warn "Killing hanging tmux commands: $tmux_pids"
        for pid in $tmux_pids; do
            kill -TERM "$pid" 2>/dev/null || true
        done
    fi
    
    debug "Orphaned process cleanup completed"
}

# =============================================================================
# INTER-SESSION COMMUNICATION
# =============================================================================

# Befehl an bestimmte Session senden
send_command_to_session() {
    local project="$1"
    local command="$2"
    local pane="${3:-0}"  # Default: linkes Pane
    
    local session_name
    session_name=$(generate_session_name "$project")
    
    debug "Sending command to $session_name pane $pane: $command"
    
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        die "Session $session_name not found"
    fi
    
    # Command senden
    tmux send-keys -t "$session_name:$DEFAULT_WINDOW_NAME.$pane" "$command" C-m
    
    info "Command sent to $project session"
}

# Session-Status abfragen
query_session_status() {
    local project="$1"
    local session_name
    session_name=$(generate_session_name "$project")
    
    echo -e "${CYAN}📊 STATUS FÜR: $project${NC}"
    
    if tmux has-session -t "$session_name" 2>/dev/null; then
        # Session existiert
        local window_count
        local pane_count
        local attached_clients
        
        window_count=$(tmux list-windows -t "$session_name" 2>/dev/null | wc -l)
        pane_count=$(tmux list-panes -t "$session_name" 2>/dev/null | wc -l)
        attached_clients=$(tmux list-clients -t "$session_name" 2>/dev/null | wc -l)
        
        echo "   Session: ✅ Active"
        echo "   Windows: $window_count"
        echo "   Panes: $pane_count"
        echo "   Clients: $attached_clients"
        
        # Claude-Prozess prüfen
        local claude_pid
        claude_pid=$(pgrep -f "^claude$" 2>/dev/null || echo "")
        if [[ -n "$claude_pid" ]]; then
            echo "   Claude: ✅ Running (PID: $claude_pid)"
        else
            echo "   Claude: ❌ Not running"
        fi
    else
        echo "   Session: ❌ Not active"
    fi
}

# =============================================================================
# HAUPTFUNKTIONEN & WORKFLOW
# =============================================================================

# Kompletten Projekt-Switch durchführen
switch_project() {
    local target_project="$1"
    
    # Validierung
    if [[ -z "$target_project" ]]; then
        die "Target project not specified"
    fi
    
    if [[ ! -v PROJECTS["$target_project"] ]]; then
        die "Unknown project: $target_project. Available: ${!PROJECTS[*]}"
    fi
    
    info "Starte Projekt-Wechsel zu: $target_project"
    
    # Lock für atomaren Wechsel
    acquire_lock 60
    
    # Cleanup bei Fehlern
    trap 'cleanup_failed_switch; release_lock' ERR
    
    # 1. Aktuelle Session ermitteln und beenden
    local current_project=""
    if current_project=$(detect_current_project); then
        info "Beende aktuelle Session: $current_project"
        terminate_session_safely "$current_project"
    fi
    
    # 2. Zombie-Cleanup
    cleanup_zombie_sessions
    
    # 3. Kurze Pause für Cleanup
    sleep 2
    
    # 4. Neue Session erstellen
    create_new_session "$target_project"
    
    # 5. Claude in neuer Session starten
    start_claude_in_session "$target_project"
    
    # 6. Session-State speichern
    save_session_state "$target_project"
    
    # 7. Zur Session wechseln
    local target_session
    target_session=$(generate_session_name "$target_project")
    
    info "Wechsle zu Session: $target_session"
    
    # Cleanup trap entfernen
    trap - ERR
    release_lock
    
    # Session anhängen (exec für kompletten Ersatz der aktuellen Shell)
    exec tmux attach -t "$target_session"
}

# Aktuelles Projekt erkennen
detect_current_project() {
    local current_path
    current_path=$(tmux display-message -p -F "#{pane_current_path}" 2>/dev/null || pwd)
    
    for project in "${!PROJECTS[@]}"; do
        if [[ "$current_path" == "${PROJECTS[$project]}"* ]]; then
            echo "$project"
            return 0
        fi
    done
    
    # Auch Session-Name prüfen
    local session_name
    session_name=$(tmux display-message -p -F "#{session_name}" 2>/dev/null || echo "")
    if [[ "$session_name" == "${TMUX_SESSION_PREFIX}-"* ]]; then
        echo "${session_name#${TMUX_SESSION_PREFIX}-}"
        return 0
    fi
    
    return 1
}

# Failed Switch Cleanup
cleanup_failed_switch() {
    warn "Switch failed, performing cleanup..."
    cleanup_zombie_sessions
    cleanup_orphaned_processes
}

# =============================================================================
# DASHBOARD & MONITORING
# =============================================================================

# Comprehensive Session Dashboard
show_session_dashboard() {
    echo -e "${PURPLE}════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}         TMUX SESSION MANAGER DASHBOARD${NC}"
    echo -e "${PURPLE}════════════════════════════════════════════════════════${NC}"
    echo
    
    # System-Info
    echo -e "${YELLOW}🖥️  SYSTEM STATUS:${NC}"
    
    # tmux-Server Status
    if tmux info &>/dev/null; then
        local session_count
        session_count=$(tmux list-sessions 2>/dev/null | wc -l)
        echo "   tmux Server: ✅ Active ($session_count total sessions)"
    else
        echo "   tmux Server: ❌ Not running"
        return 1
    fi
    
    # Aktuelle Session
    echo
    if get_current_session; then
        echo
    fi
    
    # Alle Claude-Sessions
    echo
    list_claude_sessions
    echo
    
    # Projekt-Status
    echo -e "${YELLOW}📁 PROJEKT STATUS:${NC}"
    for project in "${!PROJECTS[@]}"; do
        local path="${PROJECTS[$project]}"
        local desc="${PROJECT_DESCRIPTIONS[$project]}"
        
        # Directory-Check
        local dir_status="❌ missing"
        if [[ -d "$path" ]]; then
            dir_status="✅ exists"
        fi
        
        # Session-Check
        local session_name
        session_name=$(generate_session_name "$project")
        local session_status="⚫ offline"
        if tmux has-session -t "$session_name" 2>/dev/null; then
            session_status="🟢 active"
        fi
        
        # CLAUDE.md Check
        local claude_md_status="❌ missing"
        if [[ -f "$path/CLAUDE.md" ]]; then
            local word_count
            word_count=$(wc -w < "$path/CLAUDE.md" 2>/dev/null || echo "0")
            claude_md_status="✅ present (${word_count}w)"
        fi
        
        echo "   $session_status $project - $desc"
        echo "        📁 Directory: $dir_status"
        echo "        📋 CLAUDE.md: $claude_md_status"
        echo "        🔗 Path: $path"
        echo
    done
    
    # Resource-Usage
    echo -e "${YELLOW}📊 RESOURCE USAGE:${NC}"
    local mem_usage
    mem_usage=$(free -m | awk '/^Mem:/{printf "%.1f%%", $3/$2 * 100}' 2>/dev/null || echo "unknown")
    echo "   RAM: $mem_usage used"
    
    local disk_usage
    disk_usage=$(df -h /home 2>/dev/null | awk 'NR==2{print $5}' || echo "unknown")
    echo "   Disk (/home): $disk_usage used"
    
    # Logs
    echo
    echo -e "${YELLOW}📝 RECENT LOGS:${NC}"
    if [[ -f "$LOG_FILE" ]]; then
        echo "   Log file: $LOG_FILE"
        echo "   Recent entries:"
        tail -5 "$LOG_FILE" | sed 's/^/     /' 2>/dev/null || echo "     No recent entries"
    else
        echo "   No log file found"
    fi
}

# System Health Check
system_health_check() {
    echo -e "${BLUE}🏥 SYSTEM HEALTH CHECK${NC}"
    echo "======================"
    
    local issues=0
    local warnings=0
    
    # 1. tmux verfügbar?
    if command -v tmux >/dev/null 2>&1; then
        echo "✅ tmux binary: available"
    else
        echo "❌ tmux binary: NOT FOUND"
        ((issues++))
    fi
    
    # 2. tmux-Server
    if tmux info &>/dev/null; then
        echo "✅ tmux server: running"
    else
        echo "❌ tmux server: not running"
        ((issues++))
    fi
    
    # 3. Claude verfügbar?
    if command -v claude >/dev/null 2>&1; then
        echo "✅ claude binary: available"
    else
        echo "❌ claude binary: NOT FOUND"
        ((issues++))
    fi
    
    # 4. Projekt-Verzeichnisse
    for project in "${!PROJECTS[@]}"; do
        if [[ -d "${PROJECTS[$project]}" ]]; then
            echo "✅ project $project: directory exists"
        else
            echo "❌ project $project: DIRECTORY MISSING"
            ((issues++))
        fi
    done
    
    # 5. State-Directory
    if [[ -d "$STATE_DIR" ]]; then
        local state_count
        state_count=$(find "$STATE_DIR" -name "*.state" 2>/dev/null | wc -l)
        echo "✅ state directory: exists ($state_count states)"
    else
        echo "⚠️  state directory: missing (will be created)"
        ((warnings++))
    fi
    
    # 6. Write-Permissions
    if touch "/tmp/tmux-session-test" 2>/dev/null; then
        rm -f "/tmp/tmux-session-test"
        echo "✅ temp directory: writable"
    else
        echo "❌ temp directory: NOT WRITABLE"
        ((issues++))
    fi
    
    # 7. Lock-File Status
    if [[ -f "$LOCK_FILE" ]]; then
        local lock_pid
        lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || echo "invalid")
        if kill -0 "$lock_pid" 2>/dev/null; then
            echo "⚠️  lock file: active (PID: $lock_pid)"
            ((warnings++))
        else
            echo "🧹 lock file: stale (cleaning)"
            rm -f "$LOCK_FILE"
        fi
    else
        echo "✅ lock file: clean"
    fi
    
    # Summary
    echo
    echo "SUMMARY:"
    if [[ $issues -eq 0 && $warnings -eq 0 ]]; then
        echo -e "${GREEN}🎉 SYSTEM STATUS: OPTIMAL${NC}"
    elif [[ $issues -eq 0 ]]; then
        echo -e "${YELLOW}⚠️  SYSTEM STATUS: $warnings WARNING(S)${NC}"
    else
        echo -e "${RED}🚨 SYSTEM STATUS: $issues ERROR(S), $warnings WARNING(S)${NC}"
    fi
    
    return $issues
}

# =============================================================================
# HAUPTPROGRAMM & CLI
# =============================================================================

# Help-Funktion
show_help() {
    echo -e "${BLUE}🔄 TMUX SESSION MANAGER v3.0${NC}"
    echo "Robuste Session-Management-Lösung für Claude-Projekt-Wechsel"
    echo
    echo -e "${YELLOW}USAGE:${NC}"
    echo "  $SCRIPT_NAME <command> [options]"
    echo
    echo -e "${YELLOW}COMMANDS:${NC}"
    echo "  switch <project>    - Switch to project (with full session replacement)"
    echo "  create <project>    - Create new session for project"
    echo "  kill <project>      - Terminate session safely"
    echo "  attach <project>    - Attach to existing session"
    echo "  send <proj> <cmd>   - Send command to session"
    echo "  list                - List all Claude sessions"
    echo "  status              - Show current session status"
    echo "  status <project>    - Show specific project status"
    echo "  dashboard           - Show comprehensive dashboard"
    echo "  health              - System health check"
    echo "  cleanup             - Clean up zombie sessions and processes"
    echo "  state <project>     - Show session state"
    echo "  help                - Show this help"
    echo
    echo -e "${YELLOW}AVAILABLE PROJECTS:${NC}"
    for project in "${!PROJECTS[@]}"; do
        echo "  $project - ${PROJECT_DESCRIPTIONS[$project]}"
    done
    echo
    echo -e "${YELLOW}EXAMPLES:${NC}"
    echo "  $SCRIPT_NAME switch todo"
    echo "  $SCRIPT_NAME dashboard"
    echo "  $SCRIPT_NAME send todo './todo'"
    echo "  $SCRIPT_NAME health"
}

# Hauptfunktion
main() {
    # Setup
    ensure_tmux_server
    mkdir -p "$STATE_DIR"
    
    # Command verarbeiten
    case "${1:-help}" in
        "switch")
            [[ -z "${2:-}" ]] && die "Project name required for switch"
            switch_project "$2"
            ;;
        "create")
            [[ -z "${2:-}" ]] && die "Project name required for create"
            create_new_session "$2"
            start_claude_in_session "$2"
            save_session_state "$2"
            info "Session created successfully. Attach with: tmux attach -t $(generate_session_name "$2")"
            ;;
        "kill"|"terminate")
            [[ -z "${2:-}" ]] && die "Project name required for kill"
            terminate_session_safely "$2"
            ;;
        "attach")
            [[ -z "${2:-}" ]] && die "Project name required for attach"
            local session_name
            session_name=$(generate_session_name "$2")
            exec tmux attach -t "$session_name"
            ;;
        "send")
            [[ -z "${2:-}" ]] && die "Project name required for send"
            [[ -z "${3:-}" ]] && die "Command required for send"
            send_command_to_session "$2" "$3" "${4:-0}"
            ;;
        "list"|"ls")
            list_claude_sessions
            ;;
        "status")
            if [[ -n "${2:-}" ]]; then
                query_session_status "$2"
            else
                get_current_session
            fi
            ;;
        "dashboard"|"dash")
            show_session_dashboard
            ;;
        "health"|"check")
            system_health_check
            ;;
        "cleanup")
            cleanup_zombie_sessions
            ;;
        "state")
            [[ -z "${2:-}" ]] && die "Project name required for state"
            load_session_state "$2"
            ;;
        "help"|"-h"|"--help"|*)
            show_help
            ;;
    esac
}

# Script entry point
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi