#!/bin/bash

# Session Status Dashboard for Claude Todo System
# Comprehensive overview of session state, projects, and health

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
GRAY='\033[0;37m'
NC='\033[0m'

# Pfade
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECTS_DIR="/home/rodemkay/www/react"

# Dashboard Header
show_header() {
    clear
    echo -e "${PURPLE}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${PURPLE}║                    CLAUDE SESSION DASHBOARD                  ║${NC}"
    echo -e "${PURPLE}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo -e "${GRAY}Last updated: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo
}

# System Overview
show_system_overview() {
    echo -e "${BLUE}📊 SYSTEM OVERVIEW${NC}"
    echo -e "${BLUE}==================${NC}"
    
    # Hostname und User
    echo -e "${GREEN}🖥️  Hostname: ${CYAN}$(hostname)${NC}"
    echo -e "${GREEN}👤 User: ${CYAN}$(whoami)${NC}"
    
    # Aktuelles Verzeichnis
    echo -e "${GREEN}📁 Working Dir: ${CYAN}$(pwd)${NC}"
    
    # System Load
    local load=$(uptime | awk -F'load average:' '{ print $2 }' | cut -d, -f1 | xargs)
    echo -e "${GREEN}⚡ System Load: ${CYAN}$load${NC}"
    
    # Memory Usage
    local mem_usage=$(free -h | awk 'NR==2{printf "%.1f%%", $3/$2*100}')
    echo -e "${GREEN}💾 Memory Usage: ${CYAN}$mem_usage${NC}"
    
    echo
}

# tmux Session Status
show_tmux_status() {
    echo -e "${BLUE}📺 TMUX SESSIONS${NC}"
    echo -e "${BLUE}===============${NC}"
    
    if ! command -v tmux >/dev/null 2>&1; then
        echo -e "${RED}❌ tmux not installed${NC}"
        echo
        return 1
    fi
    
    if ! tmux list-sessions >/dev/null 2>&1; then
        echo -e "${YELLOW}📺 No active tmux sessions${NC}"
        echo
        return 0
    fi
    
    # Aktive Sessions
    echo -e "${GREEN}Active Sessions:${NC}"
    while IFS= read -r line; do
        local session_name=$(echo "$line" | cut -d: -f1)
        local session_info=$(echo "$line" | cut -d: -f2-)
        
        if [[ "$session_name" == "claude" ]]; then
            echo -e "${GREEN}  🟢 $session_name${GRAY} - $session_info${NC}"
        else
            echo -e "${YELLOW}  🟡 $session_name${GRAY} - $session_info${NC}"
        fi
    done < <(tmux list-sessions 2>/dev/null)
    
    echo
}

# Project Detection
show_project_status() {
    echo -e "${BLUE}🎯 PROJECT STATUS${NC}"
    echo -e "${BLUE}=================${NC}"
    
    # Aktuelles Projekt erkennen
    local current_project="unknown"
    local project_type="unknown"
    
    if [[ "$PWD" == *"/plugin-todo"* ]]; then
        current_project="plugin-todo"
        project_type="WordPress Plugin"
    elif [[ "$PWD" == *"/plugin-article"* ]]; then
        current_project="plugin-article"
        project_type="WordPress Plugin"
    elif [[ "$PWD" == *"/forexsignale-magazine"* ]]; then
        current_project="forexsignale-magazine"
        project_type="WordPress Theme"
    elif [[ "$PWD" == *"/react/"* ]]; then
        current_project=$(basename "$PWD")
        project_type="React Project"
    fi
    
    echo -e "${GREEN}📍 Current Project: ${CYAN}$current_project${NC}"
    echo -e "${GREEN}🏷️  Project Type: ${CYAN}$project_type${NC}"
    
    # Git Status falls verfügbar
    if [[ -d ".git" ]]; then
        local branch=$(git branch --show-current 2>/dev/null || echo "unknown")
        local status=$(git status --porcelain 2>/dev/null | wc -l)
        echo -e "${GREEN}🌳 Git Branch: ${CYAN}$branch${NC}"
        echo -e "${GREEN}📝 Uncommitted: ${CYAN}$status files${NC}"
    fi
    
    echo
}

# Available Projects
show_available_projects() {
    echo -e "${BLUE}📋 AVAILABLE PROJECTS${NC}"
    echo -e "${BLUE}=====================${NC}"
    
    local projects=()
    
    # Plugin-Projekte
    for plugin_dir in "$PROJECTS_DIR"/plugin-*; do
        if [[ -d "$plugin_dir" ]]; then
            local name=$(basename "$plugin_dir")
            if [[ -f "$plugin_dir/package.json" ]]; then
                projects+=("🔌 $name (Plugin)")
            else
                projects+=("🔌 $name")
            fi
        fi
    done
    
    # Theme-Projekte  
    for theme_dir in "$PROJECTS_DIR"/*magazine*; do
        if [[ -d "$theme_dir" ]]; then
            local name=$(basename "$theme_dir")
            projects+=("🎨 $name (Theme)")
        fi
    done
    
    # Andere React-Projekte
    for react_dir in "$PROJECTS_DIR"/*; do
        if [[ -d "$react_dir" && -f "$react_dir/package.json" ]]; then
            local name=$(basename "$react_dir")
            if [[ ! "$name" == *"plugin"* && ! "$name" == *"magazine"* ]]; then
                projects+=("⚛️  $name (React)")
            fi
        fi
    done
    
    # Projekte anzeigen
    for project in "${projects[@]}"; do
        echo -e "${GREEN}  $project${NC}"
    done
    
    echo -e "${GRAY}  Total: ${#projects[@]} projects${NC}"
    echo
}

# Lock Status
show_lock_status() {
    echo -e "${BLUE}🔒 LOCK STATUS${NC}"
    echo -e "${BLUE}==============${NC}"
    
    local locks_found=0
    
    # Session Lock
    if [[ -f "/tmp/claude_session_lock" ]]; then
        local age=$(stat -c %Y /tmp/claude_session_lock)
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        echo -e "${YELLOW}🔐 Session Lock: ${age_min}m alt${NC}"
        locks_found=1
    fi
    
    # Task Completed
    if [[ -f "/tmp/TASK_COMPLETED" ]]; then
        local age=$(stat -c %Y /tmp/TASK_COMPLETED)
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        echo -e "${GREEN}✅ TASK_COMPLETED: ${age_min}m alt${NC}"
        locks_found=1
    fi
    
    # Andere Locks
    for lock in /tmp/todo_*.lock /tmp/claude_*.lock; do
        if [[ -f "$lock" ]]; then
            local age=$(stat -c %Y "$lock")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            echo -e "${RED}🔒 $(basename "$lock"): ${age_min}m alt${NC}"
            locks_found=1
        fi
    done
    
    if [[ $locks_found -eq 0 ]]; then
        echo -e "${GREEN}🔓 No active locks${NC}"
    fi
    
    echo
}

# Process Status
show_process_status() {
    echo -e "${BLUE}⚙️  PROCESS STATUS${NC}"
    echo -e "${BLUE}=================${NC}"
    
    # Claude Processes
    local claude_procs=$(ps aux | grep -E "claude" | grep -v grep | wc -l)
    echo -e "${GREEN}🤖 Claude Processes: ${CYAN}$claude_procs${NC}"
    
    # Python Todo Processes
    local todo_procs=$(ps aux | grep -E "python.*todo" | grep -v grep | wc -l)
    echo -e "${GREEN}🐍 Todo Python Processes: ${CYAN}$todo_procs${NC}"
    
    # Webhook Processes
    local webhook_procs=$(ps aux | grep -E "watch.*trigger|webhook" | grep -v grep | wc -l)
    echo -e "${GREEN}🪝 Webhook Processes: ${CYAN}$webhook_procs${NC}"
    
    # Auflistung bei Bedarf
    if [[ "$claude_procs" -gt 5 || "$todo_procs" -gt 3 ]]; then
        echo -e "${YELLOW}⚠️  High process count detected${NC}"
    fi
    
    echo
}

# Health Assessment
show_health_status() {
    echo -e "${BLUE}🏥 HEALTH ASSESSMENT${NC}"
    echo -e "${BLUE}===================${NC}"
    
    local health_score=100
    local issues=()
    local warnings=()
    
    # tmux Check
    if ! command -v tmux >/dev/null 2>&1; then
        issues+=("tmux not available")
        health_score=$((health_score - 30))
    fi
    
    # Lock Age Check
    if [[ -f "/tmp/claude_session_lock" ]]; then
        local age=$(stat -c %Y /tmp/claude_session_lock)
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        if (( age_min > 60 )); then
            issues+=("Old session lock (${age_min}m)")
            health_score=$((health_score - 20))
        elif (( age_min > 30 )); then
            warnings+=("Session lock aging (${age_min}m)")
            health_score=$((health_score - 5))
        fi
    fi
    
    # Process Count Check
    local total_procs=$(ps aux | grep -E "(claude|todo.*python|webhook)" | grep -v grep | wc -l)
    if (( total_procs > 10 )); then
        issues+=("Too many processes ($total_procs)")
        health_score=$((health_score - 15))
    elif (( total_procs > 5 )); then
        warnings+=("High process count ($total_procs)")
        health_score=$((health_score - 5))
    fi
    
    # Required Scripts Check
    for script in claude-switch.sh emergency-session.sh session-manager.sh; do
        if [[ ! -f "$SCRIPT_DIR/$script" ]]; then
            issues+=("Missing: $script")
            health_score=$((health_score - 10))
        fi
    done
    
    # Health Score Display
    echo -e "${GREEN}Overall Health Score:${NC}"
    if (( health_score >= 95 )); then
        echo -e "${GREEN}    🟢 ${health_score}/100 - EXCELLENT${NC}"
    elif (( health_score >= 80 )); then
        echo -e "${YELLOW}    🟡 ${health_score}/100 - GOOD${NC}"
    elif (( health_score >= 60 )); then
        echo -e "${YELLOW}    🟠 ${health_score}/100 - FAIR${NC}"
    else
        echo -e "${RED}    🔴 ${health_score}/100 - CRITICAL${NC}"
    fi
    
    # Issues
    if (( ${#issues[@]} > 0 )); then
        echo -e "${RED}❌ Critical Issues:${NC}"
        for issue in "${issues[@]}"; do
            echo -e "${RED}   • $issue${NC}"
        done
    fi
    
    # Warnings
    if (( ${#warnings[@]} > 0 )); then
        echo -e "${YELLOW}⚠️  Warnings:${NC}"
        for warning in "${warnings[@]}"; do
            echo -e "${YELLOW}   • $warning${NC}"
        done
    fi
    
    echo
}

# Quick Actions
show_quick_actions() {
    echo -e "${BLUE}⚡ QUICK ACTIONS${NC}"
    echo -e "${BLUE}===============${NC}"
    echo -e "${GREEN}Available Commands:${NC}"
    echo -e "${CYAN}  ./claude-switch.sh switch <project>${NC} - Switch project"
    echo -e "${CYAN}  ./claude-switch.sh status${NC}          - Full status"
    echo -e "${CYAN}  ./emergency-session.sh soft${NC}        - Soft reset"
    echo -e "${CYAN}  ./emergency-session.sh health${NC}      - Health check"
    echo -e "${CYAN}  ./todo${NC}                             - Load next todo"
    echo -e "${CYAN}  ./todo session${NC}                     - Session status"
    echo
}

# Live Mode (refresh every 5 seconds)
live_mode() {
    echo -e "${BLUE}🔴 LIVE DASHBOARD MODE${NC}"
    echo -e "${BLUE}Press Ctrl+C to exit${NC}"
    echo
    
    while true; do
        show_header
        show_system_overview
        show_tmux_status
        show_project_status
        show_lock_status
        show_health_status
        
        echo -e "${GRAY}Refreshing in 5 seconds...${NC}"
        sleep 5
    done
}

# Hauptfunktion
main() {
    local mode="${1:-full}"
    
    case "$mode" in
        "full"|"")
            show_header
            show_system_overview
            show_tmux_status
            show_project_status
            show_available_projects
            show_lock_status
            show_process_status
            show_health_status
            show_quick_actions
            ;;
        "live")
            live_mode
            ;;
        "health")
            show_header
            show_health_status
            ;;
        "projects")
            show_header
            show_project_status
            show_available_projects
            ;;
        "tmux")
            show_header
            show_tmux_status
            ;;
        *)
            echo "Usage: $0 [full|live|health|projects|tmux]"
            exit 1
            ;;
    esac
}

# Trap für Ctrl+C in live mode
trap 'echo -e "\n${GREEN}Live mode beendet${NC}"; exit 0' INT

# Script ausführen
main "$@"