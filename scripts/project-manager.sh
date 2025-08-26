#!/bin/bash

# =============================================================================
# PROJECT MANAGER - Claude Project Configuration System
# =============================================================================
#
# Zentraler Manager für Project-spezifische Operationen:
# - Projekt-Wechsel mit Environment-Setup
# - Working Directory Management
# - tmux Session Control
# - Environment Variables Setup
# - MCP Server Management
# - Integration mit Todo-System
#
# Version: 1.0.0
# Autor: Claude AI Assistant  
# Datum: 2024-01-24
# =============================================================================

set -euo pipefail

# Pfade und Konfiguration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DETECTOR="$SCRIPT_DIR/project-detector.sh"
CONFIG_FILE="/home/rodemkay/www/react/plugin-todo/config/projects.json"
STATE_FILE="/tmp/claude-current-project.state"
LOG_FILE="/tmp/claude-project-manager.log"

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# =============================================================================
# LOGGING & UTILITIES
# =============================================================================

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] $*" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}$(date '+%Y-%m-%d %H:%M:%S') [ERROR] $*${NC}" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] $*${NC}" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}$(date '+%Y-%m-%d %H:%M:%S') [WARNING] $*${NC}" | tee -a "$LOG_FILE"
}

# Projekt-Info aus Konfiguration laden
get_project_config() {
    local project_name="$1"
    jq -r --arg project "$project_name" '.projects[$project]' "$CONFIG_FILE" 2>/dev/null
}

# Aktuelles Projekt speichern/laden
save_current_project() {
    local project_name="$1"
    echo "$project_name" > "$STATE_FILE"
    log "Aktuelles Projekt gespeichert: $project_name"
}

get_current_project() {
    if [[ -f "$STATE_FILE" ]]; then
        cat "$STATE_FILE"
    else
        echo ""
    fi
}

# =============================================================================
# PROJECT SWITCHING
# =============================================================================

switch_to_project() {
    local project_name="$1"
    local project_config
    project_config=$(get_project_config "$project_name")
    
    if [[ -z "$project_config" || "$project_config" == "null" ]]; then
        log_error "Projekt nicht gefunden: $project_name"
        return 1
    fi
    
    log "Wechsel zu Projekt: $project_name"
    
    # Working Directory wechseln
    local working_dir
    working_dir=$(echo "$project_config" | jq -r '.directories.working_directory')
    
    if [[ -n "$working_dir" && "$working_dir" != "null" ]]; then
        if [[ -d "$working_dir" ]]; then
            cd "$working_dir" || {
                log_error "Kann nicht zu Working Directory wechseln: $working_dir"
                return 1
            }
            log_success "Working Directory: $working_dir"
        else
            log_warning "Working Directory nicht gefunden: $working_dir"
        fi
    fi
    
    # Environment Variables setzen
    setup_project_environment "$project_name" "$project_config"
    
    # tmux Session Setup
    setup_tmux_session "$project_name" "$project_config"
    
    # Startup Commands ausführen
    run_startup_commands "$project_config"
    
    # Aktuelles Projekt speichern
    save_current_project "$project_name"
    
    # Status anzeigen  
    show_project_status "$project_name"
    
    log_success "Projekt-Wechsel abgeschlossen: $project_name"
    return 0
}

# =============================================================================
# ENVIRONMENT SETUP
# =============================================================================

setup_project_environment() {
    local project_name="$1"
    local project_config="$2"
    
    log "Environment Setup für $project_name"
    
    # Environment Variables aus Konfiguration laden
    local env_vars
    env_vars=$(echo "$project_config" | jq -r '.environment_variables // {} | to_entries[] | "\(.key)=\(.value)"' 2>/dev/null)
    
    if [[ -n "$env_vars" ]]; then
        while IFS='=' read -r key value; do
            export "$key"="$value"
            log "ENV: $key=$value"
        done <<< "$env_vars"
    fi
    
    # Global Environment Variables
    export CLAUDE_CURRENT_PROJECT="$project_name"
    export CLAUDE_PROJECT_CONFIG="$CONFIG_FILE"
    export CLAUDE_PROJECT_LOG="$LOG_FILE"
    
    log_success "Environment Setup abgeschlossen"
}

# =============================================================================
# TMUX SESSION MANAGEMENT
# =============================================================================

setup_tmux_session() {
    local project_name="$1"
    local project_config="$2"
    
    # tmux Konfiguration laden
    local session_name
    session_name=$(echo "$project_config" | jq -r '.tmux.session_name // "claude"')
    local window_name
    window_name=$(echo "$project_config" | jq -r '.tmux.window_name // "main"')
    local pane_target
    pane_target=$(echo "$project_config" | jq -r '.tmux.pane_target // "main"')
    
    log "tmux Setup: Session=$session_name, Window=$window_name, Pane=$pane_target"
    
    # Prüfe ob tmux Session existiert
    if ! tmux has-session -t "$session_name" 2>/dev/null; then
        log_warning "tmux Session '$session_name' existiert nicht - wird übersprungen"
        return 0
    fi
    
    # Window erstellen oder fokussieren
    if ! tmux list-windows -t "$session_name" -F "#{window_name}" | grep -q "^$window_name$"; then
        tmux new-window -t "$session_name" -n "$window_name" 2>/dev/null || true
        log "tmux Window erstellt: $window_name"
    else
        log "tmux Window bereits vorhanden: $window_name"
    fi
    
    # Working Directory setzen (falls tmux läuft)
    local working_dir
    working_dir=$(echo "$project_config" | jq -r '.directories.working_directory')
    if [[ -n "$working_dir" && "$working_dir" != "null" ]]; then
        tmux send-keys -t "$session_name:$window_name" "cd '$working_dir'" C-m 2>/dev/null || true
    fi
    
    log_success "tmux Setup abgeschlossen"
}

# =============================================================================
# STARTUP COMMANDS
# =============================================================================

run_startup_commands() {
    local project_config="$1"
    
    local commands
    commands=$(echo "$project_config" | jq -r '.startup_commands[]?' 2>/dev/null)
    
    if [[ -n "$commands" ]]; then
        log "Startup Commands ausführen..."
        
        while read -r command; do
            if [[ -n "$command" ]]; then
                log "Executing: $command"
                eval "$command" || log_warning "Command failed: $command"
            fi
        done <<< "$commands"
        
        log_success "Startup Commands abgeschlossen"
    fi
}

# =============================================================================
# PROJECT STATUS & INFO
# =============================================================================

show_project_status() {
    local project_name="$1"
    local project_config
    project_config=$(get_project_config "$project_name")
    
    echo -e "\n${CYAN}=== AKTUELLES PROJEKT: $project_name ===${NC}"
    
    # Basis-Info
    echo "Name: $(echo "$project_config" | jq -r '.name')"
    echo "Type: $(echo "$project_config" | jq -r '.type')"
    echo "Status: $(echo "$project_config" | jq -r '.status')"
    echo "Version: $(echo "$project_config" | jq -r '.version')"
    
    # Verzeichnisse
    echo -e "\n${BLUE}Verzeichnisse:${NC}"
    echo "Working: $(pwd)"
    echo "Mount Staging: $(echo "$project_config" | jq -r '.directories.mount_staging // "N/A"')"
    
    # Environment
    echo -e "\n${BLUE}Environment:${NC}"
    echo "CLAUDE_CURRENT_PROJECT: ${CLAUDE_CURRENT_PROJECT:-"nicht gesetzt"}"
    
    # Verfügbare Tools
    echo -e "\n${BLUE}CLI Tools:${NC}"
    local cli_tools
    cli_tools=$(echo "$project_config" | jq -r '.cli_tools // {} | to_entries[] | "\(.key): \(.value)"' 2>/dev/null)
    if [[ -n "$cli_tools" ]]; then
        echo "$cli_tools"
    else
        echo "Keine CLI Tools definiert"
    fi
    
    echo ""
}

# =============================================================================
# TODO SYSTEM INTEGRATION
# =============================================================================

switch_for_todo() {
    local todo_id="$1"
    local scope="${2:-}"
    
    log "Todo-basierter Projekt-Wechsel: ID=$todo_id, Scope=$scope"
    
    # Auto-detect mit Scope
    local detected_project
    if [[ -n "$scope" ]]; then
        detected_project=$("$PROJECT_DETECTOR" scope "$scope")
    else
        detected_project=$("$PROJECT_DETECTOR" auto-detect)
    fi
    
    if [[ -z "$detected_project" ]]; then
        log_error "Kein Projekt für Todo erkannt: ID=$todo_id, Scope=$scope"
        return 1
    fi
    
    log "Erkanntes Projekt für Todo: $detected_project"
    
    # Projekt wechseln
    switch_to_project "$detected_project"
    
    # Todo-spezifische Environment Variables
    export CLAUDE_CURRENT_TODO="$todo_id"
    if [[ -n "$scope" ]]; then
        export CLAUDE_CURRENT_SCOPE="$scope"
    fi
    
    log_success "Todo-Projekt-Wechsel abgeschlossen: $detected_project (Todo: $todo_id)"
}

# =============================================================================
# WORKING DIRECTORY VALIDATION
# =============================================================================

validate_working_directory() {
    local project_name="${1:-$(get_current_project)}"
    
    if [[ -z "$project_name" ]]; then
        log_error "Kein aktuelles Projekt gesetzt"
        return 1
    fi
    
    local project_config
    project_config=$(get_project_config "$project_name")
    
    local expected_dir
    expected_dir=$(echo "$project_config" | jq -r '.directories.working_directory')
    local current_dir
    current_dir=$(pwd)
    
    if [[ "$current_dir" == "$expected_dir" ]]; then
        log_success "Working Directory korrekt: $current_dir"
        return 0
    else
        log_warning "Working Directory Mismatch!"
        log_warning "Erwartet: $expected_dir"
        log_warning "Aktuell:  $current_dir"
        
        # Auto-fix anbieten
        read -p "Zu korrektem Working Directory wechseln? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            cd "$expected_dir" || {
                log_error "Wechsel fehlgeschlagen: $expected_dir"
                return 1
            }
            log_success "Working Directory korrigiert: $expected_dir"
        fi
        
        return 0
    fi
}

# =============================================================================
# MOUNT MANAGEMENT
# =============================================================================

check_mounts() {
    local project_name="${1:-$(get_current_project)}"
    
    if [[ -z "$project_name" ]]; then
        log_error "Kein Projekt angegeben"
        return 1
    fi
    
    local project_config
    project_config=$(get_project_config "$project_name")
    
    echo -e "\n${BLUE}=== MOUNT STATUS: $project_name ===${NC}"
    
    # Mount Points prüfen
    local mount_staging
    mount_staging=$(echo "$project_config" | jq -r '.directories.mount_staging // empty')
    if [[ -n "$mount_staging" ]]; then
        if [[ -d "$mount_staging" ]] && mountpoint -q "$mount_staging" 2>/dev/null; then
            echo -e "${GREEN}✓${NC} Staging Mount: $mount_staging"
        else
            echo -e "${RED}✗${NC} Staging Mount: $mount_staging (nicht gemountet)"
        fi
    fi
    
    local mount_live
    mount_live=$(echo "$project_config" | jq -r '.directories.mount_live // empty')
    if [[ -n "$mount_live" ]]; then
        if [[ -d "$mount_live" ]] && mountpoint -q "$mount_live" 2>/dev/null; then
            echo -e "${GREEN}✓${NC} Live Mount: $mount_live"
        else
            echo -e "${RED}✗${NC} Live Mount: $mount_live (nicht gemountet)"
        fi
    fi
    
    echo ""
}

# =============================================================================
# COMMAND LINE INTERFACE
# =============================================================================

show_usage() {
    cat << EOF
USAGE: $(basename "$0") [COMMAND] [OPTIONS]

Claude Project Configuration System - Project Manager

COMMANDS:
    switch <project>             Zu Projekt wechseln
    switch-todo <id> [scope]     Projekt-Wechsel basierend auf Todo
    current                      Aktuelles Projekt anzeigen
    status [project]             Projekt-Status anzeigen
    validate [project]           Working Directory validieren
    mounts [project]             Mount-Status prüfen
    list                         Alle Projekte auflisten
    detect [scope] [path]        Projekt automatisch erkennen
    env [project]                Environment Variables anzeigen
    help                         Diese Hilfe anzeigen

EXAMPLES:
    $(basename "$0") switch plugin-todo           # Zu Todo-Plugin wechseln
    $(basename "$0") switch-todo 123 "Backend"   # Todo-basierter Wechsel
    $(basename "$0") current                     # Aktuelles Projekt
    $(basename "$0") validate                    # Working Directory prüfen
    $(basename "$0") mounts                      # Mount-Status prüfen

INTEGRATION:
    Dieses Script wird vom Todo-System verwendet für automatischen
    Projekt-Wechsel basierend auf Todo-Scope und Working-Directory.

EOF
}

# =============================================================================
# MAIN LOGIC
# =============================================================================

main() {
    local command="${1:-current}"
    
    case "$command" in
        "switch")
            local project="${2:-}"
            if [[ -z "$project" ]]; then
                log_error "Projekt-Name erforderlich"
                show_usage
                exit 1
            fi
            switch_to_project "$project"
            ;;
        "switch-todo")
            local todo_id="${2:-}"
            local scope="${3:-}"
            if [[ -z "$todo_id" ]]; then
                log_error "Todo-ID erforderlich"
                show_usage
                exit 1
            fi
            switch_for_todo "$todo_id" "$scope"
            ;;
        "current")
            local current
            current=$(get_current_project)
            if [[ -n "$current" ]]; then
                show_project_status "$current"
            else
                echo "Kein aktuelles Projekt gesetzt"
                "$PROJECT_DETECTOR" auto-detect
            fi
            ;;
        "status")
            local project="${2:-$(get_current_project)}"
            if [[ -n "$project" ]]; then
                show_project_status "$project"
            else
                log_error "Kein Projekt angegeben oder aktuell gesetzt"
                exit 1
            fi
            ;;
        "validate")
            validate_working_directory "${2:-}"
            ;;
        "mounts")
            check_mounts "${2:-}"
            ;;
        "list")
            "$PROJECT_DETECTOR" list
            ;;
        "detect")
            "$PROJECT_DETECTOR" auto-detect "${2:-}" "${3:-}"
            ;;
        "env")
            local project="${2:-$(get_current_project)}"
            if [[ -n "$project" ]]; then
                local project_config
                project_config=$(get_project_config "$project")
                echo -e "\n${BLUE}Environment Variables für $project:${NC}"
                echo "$project_config" | jq -r '.environment_variables // {} | to_entries[] | "\(.key)=\(.value)"'
                echo ""
            else
                log_error "Kein Projekt angegeben"
                exit 1
            fi
            ;;
        "help"|"-h"|"--help")
            show_usage
            ;;
        *)
            log_error "Unbekannter Befehl: $command"
            show_usage
            exit 1
            ;;
    esac
}

# Script ausführen
main "$@"