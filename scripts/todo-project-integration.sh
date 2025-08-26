#!/bin/bash

# =============================================================================
# TODO-PROJECT INTEGRATION - Claude Project Configuration System
# =============================================================================
#
# Integration zwischen Todo-System und Project Configuration:
# - Auto-Projekt-Wechsel bei Todo-Start
# - Working Directory Validation
# - Scope-basierte Projekt-Erkennung  
# - Environment Setup für Todos
#
# Diese Datei wird vom ./todo Script aufgerufen
#
# Version: 1.0.0
# Autor: Claude AI Assistant
# Datum: 2024-01-24
# =============================================================================

set -euo pipefail

# Pfade
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_MANAGER="$SCRIPT_DIR/project-manager.sh"
PROJECT_DETECTOR="$SCRIPT_DIR/project-detector.sh"
LOG_FILE="/tmp/claude-todo-project-integration.log"

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# =============================================================================
# LOGGING
# =============================================================================

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [TODO-PROJECT] $*" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}$(date '+%Y-%m-%d %H:%M:%S') [TODO-PROJECT-ERROR] $*${NC}" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}$(date '+%Y-%m-%d %H:%M:%S') [TODO-PROJECT-SUCCESS] $*${NC}" | tee -a "$LOG_FILE"
}

# =============================================================================
# TODO-BASIERTE PROJEKT-ERKENNUNG
# =============================================================================

detect_project_for_todo() {
    local todo_id="${1:-}"
    local todo_scope="${2:-}"
    local todo_arbeitsverzeichnis="${3:-}"
    
    log "Todo-Projekt-Erkennung: ID=$todo_id, Scope=$todo_scope, WorkDir=$todo_arbeitsverzeichnis"
    
    local detected_project=""
    
    # 1. Priorität: Explizites Arbeitsverzeichnis
    if [[ -n "$todo_arbeitsverzeichnis" && "$todo_arbeitsverzeichnis" != "null" ]]; then
        log "Versuche Erkennung via Working Directory: $todo_arbeitsverzeichnis"
        detected_project=$("$PROJECT_DETECTOR" path "$todo_arbeitsverzeichnis" 2>/dev/null || echo "")
        if [[ -n "$detected_project" ]]; then
            log_success "Projekt erkannt via Working Directory: $detected_project"
            echo "$detected_project"
            return 0
        fi
    fi
    
    # 2. Priorität: Todo Scope
    if [[ -n "$todo_scope" && "$todo_scope" != "null" ]]; then
        log "Versuche Erkennung via Scope: $todo_scope"
        detected_project=$("$PROJECT_DETECTOR" scope "$todo_scope" 2>/dev/null || echo "")
        if [[ -n "$detected_project" ]]; then
            log_success "Projekt erkannt via Scope: $detected_project"
            echo "$detected_project"
            return 0
        fi
    fi
    
    # 3. Fallback: Auto-detect
    log "Fallback zu Auto-detect"
    detected_project=$("$PROJECT_DETECTOR" auto-detect 2>/dev/null || echo "")
    if [[ -n "$detected_project" ]]; then
        log_success "Projekt erkannt via Auto-detect: $detected_project"
        echo "$detected_project"
        return 0
    fi
    
    log_error "Kein Projekt für Todo erkannt"
    return 1
}

# =============================================================================
# WORKING DIRECTORY MANAGEMENT
# =============================================================================

setup_working_directory_for_todo() {
    local project_name="$1"
    local todo_arbeitsverzeichnis="${2:-}"
    
    # Projekt Working Directory aus Config laden
    local project_working_dir
    project_working_dir=$(jq -r --arg project "$project_name" '.projects[$project].directories.working_directory' \
        "/home/rodemkay/www/react/plugin-todo/config/projects.json" 2>/dev/null)
    
    local target_dir=""
    
    # Priorisierung der Working Directories
    if [[ -n "$todo_arbeitsverzeichnis" && "$todo_arbeitsverzeichnis" != "null" && -d "$todo_arbeitsverzeichnis" ]]; then
        target_dir="$todo_arbeitsverzeichnis"
        log "Verwende Todo-spezifisches Working Directory: $target_dir"
    elif [[ -n "$project_working_dir" && "$project_working_dir" != "null" && -d "$project_working_dir" ]]; then
        target_dir="$project_working_dir"
        log "Verwende Projekt Working Directory: $target_dir"
    else
        log_error "Kein gültiges Working Directory gefunden"
        return 1
    fi
    
    # Directory wechseln
    if cd "$target_dir" 2>/dev/null; then
        log_success "Working Directory gesetzt: $target_dir"
        export CLAUDE_TODO_WORKING_DIR="$target_dir"
        return 0
    else
        log_error "Kann nicht zu Working Directory wechseln: $target_dir"
        return 1
    fi
}

# =============================================================================
# TODO ENVIRONMENT SETUP
# =============================================================================

setup_todo_environment() {
    local todo_id="$1"
    local project_name="$2"
    local todo_scope="${3:-}"
    local todo_arbeitsverzeichnis="${4:-}"
    
    log "Todo Environment Setup: Todo=$todo_id, Projekt=$project_name"
    
    # Todo-spezifische Environment Variables
    export CLAUDE_CURRENT_TODO_ID="$todo_id"
    export CLAUDE_CURRENT_PROJECT="$project_name"
    
    if [[ -n "$todo_scope" && "$todo_scope" != "null" ]]; then
        export CLAUDE_CURRENT_TODO_SCOPE="$todo_scope"
    fi
    
    if [[ -n "$todo_arbeitsverzeichnis" && "$todo_arbeitsverzeichnis" != "null" ]]; then
        export CLAUDE_TODO_WORKING_DIR="$todo_arbeitsverzeichnis"
    fi
    
    # Projekt-spezifische Environment Variables laden
    if [[ -x "$PROJECT_MANAGER" ]]; then
        # Nur Environment laden, kein vollständiger Projekt-Switch
        log "Projekt Environment wird geladen..."
        # Das Project Manager Script hat eine env Funktion, aber wir brauchen nur die Variablen
        # Daher laden wir sie direkt aus der Config
        local project_env_vars
        project_env_vars=$(jq -r --arg project "$project_name" '.projects[$project].environment_variables // {} | to_entries[] | "\(.key)=\(.value)"' \
            "/home/rodemkay/www/react/plugin-todo/config/projects.json" 2>/dev/null)
        
        if [[ -n "$project_env_vars" ]]; then
            while IFS='=' read -r key value; do
                export "$key"="$value"
                log "ENV: $key=$value"
            done <<< "$project_env_vars"
        fi
    fi
    
    log_success "Todo Environment Setup abgeschlossen"
}

# =============================================================================
# HAUPTFUNKTIONEN FÜR TODO-INTEGRATION
# =============================================================================

# Wird von ./todo beim Start aufgerufen
prepare_todo_environment() {
    local todo_id="$1"
    local todo_scope="${2:-}"
    local todo_arbeitsverzeichnis="${3:-}"
    
    log "=== TODO ENVIRONMENT PREPARATION ==="
    log "Todo ID: $todo_id"
    log "Todo Scope: ${todo_scope:-"nicht gesetzt"}"  
    log "Todo Working Dir: ${todo_arbeitsverzeichnis:-"nicht gesetzt"}"
    
    # Projekt erkennen
    local detected_project
    detected_project=$(detect_project_for_todo "$todo_id" "$todo_scope" "$todo_arbeitsverzeichnis")
    
    if [[ -z "$detected_project" ]]; then
        log_error "Kein Projekt erkannt - verwende Fallback"
        detected_project="plugin-todo"  # Default fallback
    fi
    
    log "Erkanntes Projekt: $detected_project"
    
    # Environment Setup
    setup_todo_environment "$todo_id" "$detected_project" "$todo_scope" "$todo_arbeitsverzeichnis"
    
    # Working Directory Setup
    setup_working_directory_for_todo "$detected_project" "$todo_arbeitsverzeichnis"
    
    log_success "Todo Environment Preparation abgeschlossen"
    
    # Projekt-Name für weiteren Gebrauch ausgeben
    echo "$detected_project"
}

# Wird von ./todo beim Complete aufgerufen
cleanup_todo_environment() {
    local todo_id="$1"
    
    log "=== TODO ENVIRONMENT CLEANUP ==="
    log "Todo ID: $todo_id"
    
    # Todo-spezifische Environment Variables löschen
    unset CLAUDE_CURRENT_TODO_ID
    unset CLAUDE_CURRENT_TODO_SCOPE  
    unset CLAUDE_TODO_WORKING_DIR
    
    # Projekt bleibt aktiv für weitere Todos
    
    log_success "Todo Environment Cleanup abgeschlossen"
}

# Zeige aktuelles Todo-Environment
show_todo_environment() {
    echo -e "\n${BLUE}=== TODO ENVIRONMENT STATUS ===${NC}"
    echo "Todo ID: ${CLAUDE_CURRENT_TODO_ID:-"nicht gesetzt"}"
    echo "Todo Scope: ${CLAUDE_CURRENT_TODO_SCOPE:-"nicht gesetzt"}"
    echo "Todo Working Dir: ${CLAUDE_TODO_WORKING_DIR:-"nicht gesetzt"}"
    echo "Aktueller Pfad: $(pwd)"
    echo "Projekt: ${CLAUDE_CURRENT_PROJECT:-"nicht gesetzt"}"
    echo ""
}

# =============================================================================
# COMMAND LINE INTERFACE
# =============================================================================

show_usage() {
    cat << EOF
USAGE: $(basename "$0") [COMMAND] [OPTIONS]

Todo-Project Integration Script

COMMANDS:
    prepare <todo_id> [scope] [workdir]    Environment für Todo vorbereiten
    cleanup <todo_id>                      Environment nach Todo bereinigen  
    detect <todo_id> [scope] [workdir]     Projekt für Todo erkennen
    status                                 Aktuelles Todo-Environment anzeigen
    help                                   Diese Hilfe anzeigen

WIRD AUTOMATISCH AUFGERUFEN VON:
    ./todo                                 # prepare wird automatisch aufgerufen
    ./todo complete                        # cleanup wird automatisch aufgerufen

EXAMPLES:
    $(basename "$0") prepare 123 "Plugin Development" "/path/to/workdir"
    $(basename "$0") detect 123 "Backend"
    $(basename "$0") status
    $(basename "$0") cleanup 123

EOF
}

# =============================================================================
# MAIN LOGIC  
# =============================================================================

main() {
    local command="${1:-status}"
    
    case "$command" in
        "prepare")
            local todo_id="${2:-}"
            local scope="${3:-}"
            local workdir="${4:-}"
            if [[ -z "$todo_id" ]]; then
                log_error "Todo-ID erforderlich für prepare"
                show_usage
                exit 1
            fi
            prepare_todo_environment "$todo_id" "$scope" "$workdir"
            ;;
        "cleanup")
            local todo_id="${2:-}"
            if [[ -z "$todo_id" ]]; then
                log_error "Todo-ID erforderlich für cleanup"
                show_usage  
                exit 1
            fi
            cleanup_todo_environment "$todo_id"
            ;;
        "detect")
            local todo_id="${2:-}"
            local scope="${3:-}"
            local workdir="${4:-}"
            if [[ -z "$todo_id" ]]; then
                log_error "Todo-ID erforderlich für detect"
                show_usage
                exit 1
            fi
            detect_project_for_todo "$todo_id" "$scope" "$workdir"
            ;;
        "status")
            show_todo_environment
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