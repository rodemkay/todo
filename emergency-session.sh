#!/bin/bash

# Emergency Session Management for Claude
# Provides panic buttons and recovery options

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# Pfade
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOGS_DIR="$SCRIPT_DIR/logs"
EMERGENCY_LOG="$LOGS_DIR/emergency.log"

# Logging
log_emergency() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') EMERGENCY: $*" >> "$EMERGENCY_LOG"
}

# Emergency Status Dashboard
show_emergency_status() {
    echo -e "${RED}🚨 EMERGENCY SESSION STATUS DASHBOARD 🚨${NC}"
    echo -e "${RED}================================================${NC}"
    echo
    
    # tmux Sessions
    echo -e "${YELLOW}📺 Active tmux Sessions:${NC}"
    if tmux list-sessions 2>/dev/null; then
        echo
    else
        echo -e "${RED}   No tmux sessions found${NC}"
        echo
    fi
    
    # Stuck Processes
    echo -e "${YELLOW}🔒 Potentially Stuck Processes:${NC}"
    ps aux | grep -E "(claude|todo|python3.*todo)" | grep -v grep | while read line; do
        echo -e "${RED}   $line${NC}"
    done
    echo
    
    # Lock Files
    echo -e "${YELLOW}🔐 Active Locks:${NC}"
    for lock in /tmp/claude_session_lock /tmp/TASK_COMPLETED /tmp/todo_*.lock; do
        if [[ -f "$lock" ]]; then
            local age=$(stat -c %Y "$lock")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            echo -e "${RED}   $lock (${age_min}m alt)${NC}"
        fi
    done
    echo
    
    # Working Directory
    echo -e "${YELLOW}📁 Current Directory:${NC}"
    echo -e "   ${CYAN}$(pwd)${NC}"
    echo
    
    # Last Emergency Action
    if [[ -f "$EMERGENCY_LOG" ]]; then
        echo -e "${YELLOW}📋 Last Emergency Actions:${NC}"
        tail -5 "$EMERGENCY_LOG" 2>/dev/null | while read line; do
            echo -e "${BLUE}   $line${NC}"
        done
    fi
    echo
}

# Nuclear Option - Kill Everything
nuclear_reset() {
    echo -e "${RED}☢️  NUCLEAR RESET INITIATED${NC}"
    echo -e "${RED}This will DESTROY all tmux sessions and Claude processes!${NC}"
    echo -e "${YELLOW}Are you ABSOLUTELY sure? Type 'NUCLEAR' to confirm:${NC}"
    read -r confirmation
    
    if [[ "$confirmation" != "NUCLEAR" ]]; then
        echo -e "${GREEN}Nuclear reset aborted${NC}"
        return 0
    fi
    
    log_emergency "NUCLEAR RESET initiated"
    
    echo -e "${RED}💥 Killing all tmux sessions...${NC}"
    tmux kill-server 2>/dev/null || echo -e "${YELLOW}No tmux server to kill${NC}"
    
    echo -e "${RED}💥 Killing Claude processes...${NC}"
    pkill -f "claude" 2>/dev/null || echo -e "${YELLOW}No claude processes found${NC}"
    
    echo -e "${RED}💥 Killing todo processes...${NC}"
    pkill -f "todo.*python" 2>/dev/null || echo -e "${YELLOW}No todo processes found${NC}"
    
    echo -e "${RED}💥 Removing all locks...${NC}"
    rm -f /tmp/claude_session_lock
    rm -f /tmp/TASK_COMPLETED
    rm -f /tmp/todo_*.lock
    rm -f /tmp/claude_*
    
    echo -e "${RED}💥 Clearing temporary files...${NC}"
    find /tmp -name "*claude*" -type f -delete 2>/dev/null || true
    find /tmp -name "*todo*" -type f -delete 2>/dev/null || true
    
    log_emergency "NUCLEAR RESET completed"
    echo -e "${GREEN}☢️  Nuclear reset completed${NC}"
    echo -e "${BLUE}System is clean. You can now restart Claude.${NC}"
}

# Soft Reset - Gentler approach
soft_reset() {
    echo -e "${YELLOW}🔄 SOFT RESET initiated${NC}"
    log_emergency "SOFT RESET initiated"
    
    echo -e "${BLUE}🔍 Checking for stuck processes...${NC}"
    
    # Alte Locks entfernen (>30min)
    for lock in /tmp/claude_session_lock /tmp/TASK_COMPLETED; do
        if [[ -f "$lock" ]]; then
            local age=$(stat -c %Y "$lock")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            if (( age_min > 30 )); then
                echo -e "${YELLOW}🗑️  Removing old lock: $lock (${age_min}m)${NC}"
                rm -f "$lock"
                log_emergency "Removed old lock: $lock"
            fi
        fi
    done
    
    # TASK_COMPLETED setzen falls nötig
    if [[ ! -f "/tmp/TASK_COMPLETED" ]]; then
        echo -e "${GREEN}✅ Setting TASK_COMPLETED${NC}"
        echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
        log_emergency "Set TASK_COMPLETED"
    fi
    
    # Hung tmux sessions sanft beenden
    echo -e "${BLUE}🔄 Checking tmux sessions...${NC}"
    if tmux list-sessions 2>/dev/null | grep -q "claude"; then
        echo -e "${YELLOW}⚠️  Found claude session, checking if responsive...${NC}"
        # Teste ob Session reagiert
        if ! timeout 5 tmux send-keys -t claude "echo responsive" Enter 2>/dev/null; then
            echo -e "${RED}🚫 Claude session unresponsive, terminating...${NC}"
            tmux kill-session -t claude 2>/dev/null || true
            log_emergency "Killed unresponsive claude session"
        else
            echo -e "${GREEN}✅ Claude session responsive${NC}"
        fi
    fi
    
    log_emergency "SOFT RESET completed"
    echo -e "${GREEN}🔄 Soft reset completed${NC}"
}

# Force Session Switch (bypassing locks)
force_switch() {
    local target_project="$1"
    
    echo -e "${RED}⚡ FORCE SWITCH to $target_project${NC}"
    log_emergency "FORCE SWITCH to $target_project initiated"
    
    # Locks entfernen
    echo -e "${YELLOW}🔓 Removing session locks...${NC}"
    rm -f /tmp/claude_session_lock
    
    # TASK_COMPLETED setzen
    echo -e "${GREEN}✅ Setting TASK_COMPLETED${NC}"
    echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
    
    # Session wechseln mit claude-switch.sh
    if [[ -f "$SCRIPT_DIR/claude-switch.sh" ]]; then
        echo -e "${BLUE}🔄 Executing session switch...${NC}"
        "$SCRIPT_DIR/claude-switch.sh" switch "$target_project"
        log_emergency "FORCE SWITCH to $target_project completed"
    else
        echo -e "${RED}❌ claude-switch.sh not found${NC}"
        log_emergency "FORCE SWITCH failed - claude-switch.sh not found"
    fi
}

# Session Health Diagnostic
health_diagnostic() {
    echo -e "${BLUE}🏥 SESSION HEALTH DIAGNOSTIC${NC}"
    echo -e "${BLUE}=============================${NC}"
    echo
    
    local health_score=100
    local issues=()
    
    # Check 1: tmux availability
    if ! command -v tmux >/dev/null 2>&1; then
        issues+=("tmux not installed")
        health_score=$((health_score - 50))
    else
        echo -e "${GREEN}✅ tmux available${NC}"
    fi
    
    # Check 2: Session locks
    for lock in /tmp/claude_session_lock /tmp/TASK_COMPLETED; do
        if [[ -f "$lock" ]]; then
            local age=$(stat -c %Y "$lock")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            if (( age_min > 60 )); then
                issues+=("Old lock: $lock (${age_min}m)")
                health_score=$((health_score - 10))
            else
                echo -e "${GREEN}✅ Lock OK: $lock${NC}"
            fi
        fi
    done
    
    # Check 3: Stuck processes
    local stuck_count=$(ps aux | grep -E "(claude|todo.*python)" | grep -v grep | wc -l)
    if (( stuck_count > 3 )); then
        issues+=("Too many processes: $stuck_count")
        health_score=$((health_score - 20))
    else
        echo -e "${GREEN}✅ Process count OK: $stuck_count${NC}"
    fi
    
    # Check 4: Available scripts
    for script in claude-switch.sh session-manager.sh project-detector.sh; do
        if [[ ! -f "$SCRIPT_DIR/$script" ]]; then
            issues+=("Missing script: $script")
            health_score=$((health_score - 5))
        else
            echo -e "${GREEN}✅ Script available: $script${NC}"
        fi
    done
    
    echo
    echo -e "${BLUE}Health Score: ${NC}"
    if (( health_score >= 90 )); then
        echo -e "${GREEN}${health_score}/100 - EXCELLENT${NC}"
    elif (( health_score >= 70 )); then
        echo -e "${YELLOW}${health_score}/100 - GOOD${NC}"
    elif (( health_score >= 50 )); then
        echo -e "${YELLOW}${health_score}/100 - FAIR${NC}"
    else
        echo -e "${RED}${health_score}/100 - CRITICAL${NC}"
    fi
    
    if (( ${#issues[@]} > 0 )); then
        echo
        echo -e "${RED}❌ Issues detected:${NC}"
        for issue in "${issues[@]}"; do
            echo -e "${RED}   • $issue${NC}"
        done
        echo
        echo -e "${BLUE}💡 Run './emergency-session.sh soft' to auto-fix${NC}"
    fi
}

# Hilfe anzeigen
show_help() {
    cat << 'EOF'
Emergency Session Management - Hilfe

VERWENDUNG:
  ./emergency-session.sh [BEFEHL]

BEFEHLE:
  status                Zeigt Emergency Status Dashboard
  soft                  Soft Reset (empfohlen) - entfernt alte Locks
  nuclear               ☢️  Nuclear Reset - ZERSTÖRT ALLES
  force <projekt>       Erzwingt Session-Switch (bypass locks)
  health                Führt Health-Diagnostic durch
  help                  Zeigt diese Hilfe

EMERGENCY HOTKEYS:
  Ctrl+C                Unterbricht aktuellen Prozess
  Ctrl+Z                Pausiert Prozess (dann 'kill %1')
  pkill claude          Beendet alle Claude-Prozesse

RECOVERY SEQUENCE (bei stuck sessions):
1. ./emergency-session.sh status
2. ./emergency-session.sh soft
3. Falls immer noch Probleme: ./emergency-session.sh nuclear
4. Neu starten: ./claude-switch.sh init

EOF
}

# Hauptfunktion
main() {
    mkdir -p "$LOGS_DIR"
    
    local command="${1:-status}"
    
    case "$command" in
        "status")
            show_emergency_status
            ;;
        "soft")
            soft_reset
            ;;
        "nuclear")
            nuclear_reset
            ;;
        "force")
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: Projekt-Name erforderlich${NC}"
                echo "Usage: ./emergency-session.sh force <projekt>"
                exit 1
            fi
            force_switch "$2"
            ;;
        "health")
            health_diagnostic
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            echo -e "${RED}Unknown command: $command${NC}" >&2
            show_help
            exit 1
            ;;
    esac
}

# Script ausführen
main "$@"