#!/bin/bash

# SESSION RECOVERY SYSTEM - Emergency Recovery für tmux Claude Sessions
# Version: 1.0
# Datum: 2025-08-24

set -euo pipefail

readonly SCRIPT_NAME="$(basename "$0")"
readonly LOG_FILE="/tmp/session-recovery.log"
readonly STATE_DIR="/home/rodemkay/.claude/session-states"
readonly BACKUP_DIR="/home/rodemkay/.claude/backups"
readonly EMERGENCY_LOCK="/tmp/.session-emergency.lock"

# Farben
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m'

# tmux Session-Konfiguration
readonly TMUX_SESSION_PREFIX="claude"

# =============================================================================
# LOGGING & ERROR HANDLING
# =============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"
}

emergency_log() {
    log "EMERGENCY: $*"
    echo -e "${RED}🚨 EMERGENCY: $*${NC}" >&2
}

info() {
    log "INFO: $*"
    echo -e "${GREEN}ℹ️  $*${NC}"
}

warn() {
    log "WARN: $*"
    echo -e "${YELLOW}⚠️  $*${NC}"
}

# =============================================================================
# CRASH DETECTION
# =============================================================================

# Erkenne crashed/zombie Sessions
detect_crashed_sessions() {
    echo -e "${BLUE}🔍 CRASH DETECTION${NC}"
    echo "=================="
    
    local crashed_sessions=()
    local zombie_processes=()
    local issues_found=0
    
    # 1. tmux-Sessions prüfen
    if tmux list-sessions &>/dev/null; then
        while IFS= read -r session_line; do
            local session_name
            session_name=$(echo "$session_line" | cut -d: -f1)
            
            if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
                # Prüfe Session-Responsiveness
                if ! tmux list-panes -t "$session_name" &>/dev/null; then
                    warn "Detected unresponsive session: $session_name"
                    crashed_sessions+=("$session_name")
                    ((issues_found++))
                fi
                
                # Prüfe Session-Inhalt
                local pane_count
                pane_count=$(tmux list-panes -t "$session_name" 2>/dev/null | wc -l || echo "0")
                if [[ "$pane_count" -eq 0 ]]; then
                    warn "Detected empty session: $session_name"
                    crashed_sessions+=("$session_name")
                    ((issues_found++))
                fi
            fi
        done < <(tmux list-sessions 2>/dev/null || echo "")
    else
        emergency_log "tmux server not responding!"
        ((issues_found++))
    fi
    
    # 2. Zombie Claude-Prozesse
    local claude_pids
    claude_pids=$(pgrep -f "claude" 2>/dev/null || echo "")
    for pid in $claude_pids; do
        # Prüfe Prozess-Status
        local proc_stat
        proc_stat=$(ps -p "$pid" -o stat= 2>/dev/null | tr -d ' ' || echo "")
        
        if [[ "$proc_stat" =~ Z ]]; then
            warn "Detected zombie Claude process: $pid"
            zombie_processes+=("$pid")
            ((issues_found++))
        elif [[ "$proc_stat" =~ D ]]; then
            warn "Detected uninterruptible Claude process: $pid"
            zombie_processes+=("$pid")
            ((issues_found++))
        fi
    done
    
    # 3. Orphaned snapshot-Prozesse
    local snapshot_pids
    snapshot_pids=$(pgrep -f "shell-snapshots" 2>/dev/null || echo "")
    for pid in $snapshot_pids; do
        # Prüfe Parent-Prozess
        local ppid
        ppid=$(ps -p "$pid" -o ppid= 2>/dev/null | tr -d ' ' || echo "1")
        
        if [[ "$ppid" == "1" ]]; then
            warn "Detected orphaned snapshot process: $pid"
            zombie_processes+=("$pid")
            ((issues_found++))
        fi
    done
    
    # Report
    echo
    if [[ $issues_found -eq 0 ]]; then
        info "No crashed sessions or zombie processes detected"
        return 0
    else
        emergency_log "$issues_found issues detected:"
        [[ ${#crashed_sessions[@]} -gt 0 ]] && echo "  Crashed sessions: ${crashed_sessions[*]}"
        [[ ${#zombie_processes[@]} -gt 0 ]] && echo "  Zombie processes: ${zombie_processes[*]}"
        return 1
    fi
}

# =============================================================================
# SESSION STATE BACKUP & RESTORE
# =============================================================================

# Erstelle vollständige Session-Backups
create_session_backup() {
    local project="${1:-all}"
    
    echo -e "${BLUE}💾 SESSION BACKUP${NC}"
    echo "================"
    
    mkdir -p "$BACKUP_DIR"
    local backup_timestamp
    backup_timestamp=$(date '+%Y%m%d_%H%M%S')
    
    if [[ "$project" == "all" ]]; then
        # Backup aller Sessions
        local backup_file="$BACKUP_DIR/all_sessions_${backup_timestamp}.tar.gz"
        
        info "Creating comprehensive session backup..."
        
        # State-Files sammeln
        local temp_dir
        temp_dir=$(mktemp -d)
        
        if [[ -d "$STATE_DIR" ]]; then
            cp -r "$STATE_DIR"/* "$temp_dir/" 2>/dev/null || true
        fi
        
        # tmux Session-Info sammeln
        if tmux list-sessions &>/dev/null; then
            tmux list-sessions > "$temp_dir/tmux_sessions.txt" 2>/dev/null || true
            
            # Für jede Claude-Session Details sammeln
            while IFS= read -r session_line; do
                local session_name
                session_name=$(echo "$session_line" | cut -d: -f1)
                
                if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
                    {
                        echo "=== SESSION: $session_name ==="
                        tmux list-windows -t "$session_name" 2>/dev/null || echo "No windows"
                        echo
                        tmux list-panes -t "$session_name" -F "#{pane_id} #{pane_current_path} #{pane_current_command}" 2>/dev/null || echo "No panes"
                        echo
                    } >> "$temp_dir/session_${session_name}.info"
                fi
            done < <(tmux list-sessions 2>/dev/null || echo "")
        fi
        
        # Prozess-Info sammeln
        {
            echo "=== CLAUDE PROCESSES ==="
            pgrep -af "claude" || echo "No Claude processes"
            echo
            echo "=== SYSTEM INFO ==="
            date
            whoami
            pwd
            echo "tmux server: $(tmux info 2>/dev/null | head -1 || echo 'not running')"
        } > "$temp_dir/system_info.txt"
        
        # Backup erstellen
        tar -czf "$backup_file" -C "$temp_dir" . 2>/dev/null
        rm -rf "$temp_dir"
        
        if [[ -f "$backup_file" ]]; then
            info "Backup created: $backup_file"
            info "Backup size: $(du -h "$backup_file" | cut -f1)"
        else
            emergency_log "Failed to create backup!"
            return 1
        fi
    else
        # Backup für spezifisches Projekt
        local session_name="${TMUX_SESSION_PREFIX}-${project}"
        local backup_file="$BACKUP_DIR/${project}_session_${backup_timestamp}.tar.gz"
        
        info "Creating backup for project: $project"
        
        local temp_dir
        temp_dir=$(mktemp -d)
        
        # State-File kopieren
        if [[ -f "$STATE_DIR/${project}.state" ]]; then
            cp "$STATE_DIR/${project}.state" "$temp_dir/"
        fi
        
        # Session-Info sammeln
        if tmux has-session -t "$session_name" 2>/dev/null; then
            {
                echo "=== SESSION INFO ==="
                tmux list-windows -t "$session_name" 2>/dev/null || echo "No windows"
                echo
                tmux list-panes -t "$session_name" -F "#{pane_id} #{pane_current_path} #{pane_current_command}" 2>/dev/null || echo "No panes"
                echo
                echo "=== PANE CONTENTS (last 20 lines) ==="
                tmux capture-pane -t "$session_name:0.0" -p 2>/dev/null | tail -20 || echo "Could not capture"
            } > "$temp_dir/${project}_session.info"
        fi
        
        tar -czf "$backup_file" -C "$temp_dir" . 2>/dev/null
        rm -rf "$temp_dir"
        
        info "Project backup created: $backup_file"
    fi
}

# Session aus Backup wiederherstellen
restore_from_backup() {
    local backup_file="$1"
    
    if [[ ! -f "$backup_file" ]]; then
        emergency_log "Backup file not found: $backup_file"
        return 1
    fi
    
    echo -e "${BLUE}🔄 SESSION RESTORE${NC}"
    echo "================="
    
    info "Restoring from: $(basename "$backup_file")"
    
    # Backup extrahieren
    local restore_dir
    restore_dir=$(mktemp -d)
    
    if ! tar -xzf "$backup_file" -C "$restore_dir" 2>/dev/null; then
        emergency_log "Failed to extract backup"
        rm -rf "$restore_dir"
        return 1
    fi
    
    # State-Files wiederherstellen
    if [[ -d "$restore_dir" ]] && [[ -n "$(ls -A "$restore_dir"/*.state 2>/dev/null || echo "")" ]]; then
        mkdir -p "$STATE_DIR"
        cp "$restore_dir"/*.state "$STATE_DIR/" 2>/dev/null || true
        info "Session states restored"
    fi
    
    # Verfügbare Projekt-Infos anzeigen
    echo
    info "Available project information in backup:"
    for info_file in "$restore_dir"/*.info; do
        if [[ -f "$info_file" ]]; then
            local project_name
            project_name=$(basename "$info_file" .info | sed 's/session_//' | sed 's/^claude-//')
            echo "  - $project_name"
        fi
    done
    
    rm -rf "$restore_dir"
    info "Restore completed"
}

# =============================================================================
# EMERGENCY RECOVERY
# =============================================================================

# Vollständige System-Recovery
emergency_recovery() {
    echo -e "${RED}🚨 EMERGENCY RECOVERY INITIATED${NC}"
    echo "================================="
    
    # Lock für Emergency-Recovery
    if [[ -f "$EMERGENCY_LOCK" ]]; then
        local lock_pid
        lock_pid=$(cat "$EMERGENCY_LOCK" 2>/dev/null || echo "")
        if kill -0 "$lock_pid" 2>/dev/null; then
            emergency_log "Another emergency recovery in progress (PID: $lock_pid)"
            return 1
        else
            warn "Removing stale emergency lock"
            rm -f "$EMERGENCY_LOCK"
        fi
    fi
    
    echo $$ > "$EMERGENCY_LOCK"
    trap 'rm -f "$EMERGENCY_LOCK"' EXIT
    
    # 1. Backup vor Recovery
    info "Creating emergency backup..."
    create_session_backup "all"
    
    # 2. Alle Claude-Sessions beenden
    info "Terminating all Claude sessions..."
    while IFS= read -r session_line; do
        local session_name
        session_name=$(echo "$session_line" | cut -d: -f1)
        
        if [[ "$session_name" == "${TMUX_SESSION_PREFIX}"* ]]; then
            warn "Killing session: $session_name"
            tmux kill-session -t "$session_name" 2>/dev/null || true
        fi
    done < <(tmux list-sessions 2>/dev/null || echo "")
    
    # 3. Alle Claude-Prozesse beenden
    info "Terminating all Claude processes..."
    local all_claude_pids
    all_claude_pids=$(pgrep -f "claude" 2>/dev/null || echo "")
    
    if [[ -n "$all_claude_pids" ]]; then
        for pid in $all_claude_pids; do
            warn "Terminating Claude PID: $pid"
            kill -TERM "$pid" 2>/dev/null || true
        done
        
        # Wait and force kill if needed
        sleep 5
        all_claude_pids=$(pgrep -f "claude" 2>/dev/null || echo "")
        if [[ -n "$all_claude_pids" ]]; then
            for pid in $all_claude_pids; do
                warn "Force killing Claude PID: $pid"
                kill -KILL "$pid" 2>/dev/null || true
            done
        fi
    fi
    
    # 4. Orphaned Prozesse cleanup
    info "Cleaning up orphaned processes..."
    
    # Snapshot-Prozesse
    local snapshot_pids
    snapshot_pids=$(pgrep -f "shell-snapshots" 2>/dev/null || echo "")
    if [[ -n "$snapshot_pids" ]]; then
        for pid in $snapshot_pids; do
            kill -KILL "$pid" 2>/dev/null || true
        done
    fi
    
    # tmux-Befehle
    local tmux_pids
    tmux_pids=$(pgrep -f "tmux send-keys" 2>/dev/null || echo "")
    if [[ -n "$tmux_pids" ]]; then
        for pid in $tmux_pids; do
            kill -TERM "$pid" 2>/dev/null || true
        done
    fi
    
    # 5. Lock-Files bereinigen
    info "Cleaning up lock files..."
    rm -f /tmp/.tmux-session-manager.lock 2>/dev/null || true
    rm -f /tmp/.claude-switch-lock 2>/dev/null || true
    
    # 6. tmux-Server neu starten
    info "Restarting tmux server..."
    tmux kill-server 2>/dev/null || true
    sleep 2
    tmux start-server
    
    # 7. System-Validierung
    info "Validating system state..."
    local remaining_claude
    remaining_claude=$(pgrep -f "claude" 2>/dev/null || echo "")
    
    if [[ -n "$remaining_claude" ]]; then
        warn "Some Claude processes still running: $remaining_claude"
    else
        info "All Claude processes terminated successfully"
    fi
    
    if tmux info &>/dev/null; then
        info "tmux server restarted successfully"
    else
        emergency_log "tmux server failed to restart!"
        return 1
    fi
    
    echo
    echo -e "${GREEN}✅ EMERGENCY RECOVERY COMPLETED${NC}"
    echo "================================"
    info "System is ready for new sessions"
    info "Use 'tmux-session-manager.sh create <project>' to start fresh"
}

# Sanity-Check nach Recovery
validate_recovery() {
    echo -e "${BLUE}✅ RECOVERY VALIDATION${NC}"
    echo "==================="
    
    local issues=0
    
    # 1. tmux-Server
    if tmux info &>/dev/null; then
        info "tmux server: OK"
    else
        emergency_log "tmux server: FAILED"
        ((issues++))
    fi
    
    # 2. Claude-Prozesse (sollten keine da sein)
    local claude_count
    claude_count=$(pgrep -f "^claude$" 2>/dev/null | wc -l || echo "0")
    
    if [[ "$claude_count" -eq 0 ]]; then
        info "Claude processes: clean (none running)"
    else
        warn "Claude processes: $claude_count still running"
        ((issues++))
    fi
    
    # 3. Session-Clean-State
    local claude_sessions
    claude_sessions=$(tmux list-sessions 2>/dev/null | grep -c "^${TMUX_SESSION_PREFIX}" || echo "0")
    
    if [[ "$claude_sessions" -eq 0 ]]; then
        info "Sessions: clean (no Claude sessions)"
    else
        warn "Sessions: $claude_sessions Claude sessions still exist"
        ((issues++))
    fi
    
    # 4. Orphaned-Prozesse
    local orphaned
    orphaned=$(pgrep -f "shell-snapshots" 2>/dev/null | wc -l || echo "0")
    
    if [[ "$orphaned" -eq 0 ]]; then
        info "Orphaned processes: clean"
    else
        warn "Orphaned processes: $orphaned still running"
        ((issues++))
    fi
    
    # Summary
    echo
    if [[ $issues -eq 0 ]]; then
        echo -e "${GREEN}🎉 SYSTEM VALIDATION: PASSED${NC}"
        return 0
    else
        echo -e "${RED}❌ SYSTEM VALIDATION: $issues ISSUES REMAINING${NC}"
        return 1
    fi
}

# =============================================================================
# BACKUP MANAGEMENT
# =============================================================================

# Liste verfügbare Backups
list_backups() {
    echo -e "${BLUE}📋 AVAILABLE BACKUPS${NC}"
    echo "==================="
    
    if [[ ! -d "$BACKUP_DIR" ]]; then
        info "No backup directory found"
        return 1
    fi
    
    local backup_files
    backup_files=$(find "$BACKUP_DIR" -name "*.tar.gz" -type f 2>/dev/null | sort -r || echo "")
    
    if [[ -z "$backup_files" ]]; then
        info "No backup files found"
        return 1
    fi
    
    echo "Recent backups:"
    while IFS= read -r backup_file; do
        if [[ -f "$backup_file" ]]; then
            local filename
            filename=$(basename "$backup_file")
            local size
            size=$(du -h "$backup_file" 2>/dev/null | cut -f1 || echo "?")
            local date_created
            date_created=$(stat -c %y "$backup_file" 2>/dev/null | cut -d' ' -f1 || echo "unknown")
            
            echo "  📦 $filename ($size, $date_created)"
        fi
    done <<< "$backup_files"
}

# Backup-Cleanup (alte Backups löschen)
cleanup_old_backups() {
    local keep_days="${1:-7}"  # Default: 7 Tage behalten
    
    echo -e "${BLUE}🧹 BACKUP CLEANUP${NC}"
    echo "================="
    
    if [[ ! -d "$BACKUP_DIR" ]]; then
        info "No backup directory to clean"
        return 0
    fi
    
    info "Removing backups older than $keep_days days..."
    
    local deleted=0
    while IFS= read -r backup_file; do
        if [[ -f "$backup_file" ]]; then
            # Datei-Alter prüfen
            local file_age
            file_age=$(find "$backup_file" -mtime +$keep_days 2>/dev/null || echo "")
            
            if [[ -n "$file_age" ]]; then
                local filename
                filename=$(basename "$backup_file")
                warn "Deleting old backup: $filename"
                rm -f "$backup_file"
                ((deleted++))
            fi
        fi
    done < <(find "$BACKUP_DIR" -name "*.tar.gz" -type f 2>/dev/null || echo "")
    
    info "Deleted $deleted old backup files"
}

# =============================================================================
# MAIN PROGRAM
# =============================================================================

show_help() {
    echo -e "${BLUE}🔄 SESSION RECOVERY SYSTEM v1.0${NC}"
    echo "Emergency Recovery für tmux Claude Sessions"
    echo
    echo -e "${YELLOW}USAGE:${NC}"
    echo "  $SCRIPT_NAME <command> [options]"
    echo
    echo -e "${YELLOW}COMMANDS:${NC}"
    echo "  detect              - Detect crashed sessions and zombie processes"
    echo "  backup [project]    - Create session backup (default: all projects)"
    echo "  restore <file>      - Restore session from backup"
    echo "  emergency           - Full emergency recovery (nuclear option)"
    echo "  validate            - Validate system state after recovery"
    echo "  list-backups        - List available backup files"
    echo "  cleanup-backups [days] - Clean up old backups (default: 7 days)"
    echo "  help                - Show this help"
    echo
    echo -e "${YELLOW}EXAMPLES:${NC}"
    echo "  $SCRIPT_NAME detect"
    echo "  $SCRIPT_NAME backup todo"
    echo "  $SCRIPT_NAME emergency"
    echo "  $SCRIPT_NAME restore /path/to/backup.tar.gz"
}

main() {
    # Setup
    mkdir -p "$BACKUP_DIR" "$STATE_DIR"
    
    case "${1:-help}" in
        "detect"|"check")
            detect_crashed_sessions
            ;;
        "backup")
            create_session_backup "${2:-all}"
            ;;
        "restore")
            [[ -z "${2:-}" ]] && { echo "Backup file required"; exit 1; }
            restore_from_backup "$2"
            ;;
        "emergency"|"nuke")
            echo -e "${RED}⚠️  EMERGENCY RECOVERY WILL TERMINATE ALL CLAUDE SESSIONS!${NC}"
            read -p "Are you sure? (type 'YES' to confirm): " confirm
            if [[ "$confirm" == "YES" ]]; then
                emergency_recovery
                echo
                validate_recovery
            else
                info "Emergency recovery cancelled"
            fi
            ;;
        "validate"|"check-recovery")
            validate_recovery
            ;;
        "list-backups"|"backups")
            list_backups
            ;;
        "cleanup-backups"|"cleanup")
            cleanup_old_backups "${2:-7}"
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