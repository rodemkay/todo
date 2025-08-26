#!/bin/bash

# =============================================================================
# PROJECT DETECTOR - Claude Project Configuration System  
# =============================================================================
# 
# Erkennt automatisch das benötigte Projekt basierend auf:
# - Aktueller Arbeitsverzeichnis
# - Todo-Scope Mappings  
# - Pfad-Pattern Matching
# - Validiert Projekt-Konfigurationen
# - Warnt bei fehlenden Dependencies
#
# Version: 1.0.0
# Autor: Claude AI Assistant
# Datum: 2024-01-24
# =============================================================================

set -euo pipefail

# Konfigurationsdatei laden
CONFIG_FILE="/home/rodemkay/www/react/plugin-todo/config/projects.json"
LOG_FILE="/tmp/claude-project-detector.log"
CURRENT_DIR=$(pwd)

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# LOGGING FUNKTIONEN
# =============================================================================

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] $*" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}$(date '+%Y-%m-%d %H:%M:%S') [ERROR] $*${NC}" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}$(date '+%Y-%m-%d %H:%M:%S') [WARNING] $*${NC}" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] $*${NC}" | tee -a "$LOG_FILE"
}

# =============================================================================
# VALIDIERUNG & HILFSFUNKTIONEN  
# =============================================================================

validate_config() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        log_error "Projekt-Konfigurationsdatei nicht gefunden: $CONFIG_FILE"
        return 1
    fi
    
    if ! jq empty "$CONFIG_FILE" 2>/dev/null; then
        log_error "Ungültiges JSON Format in Konfigurationsdatei"
        return 1
    fi
    
    log_success "Projekt-Konfiguration erfolgreich validiert"
    return 0
}

get_project_list() {
    jq -r '.projects | keys[]' "$CONFIG_FILE" 2>/dev/null || echo ""
}

get_project_info() {
    local project_name="$1"
    jq -r --arg project "$project_name" '.projects[$project]' "$CONFIG_FILE" 2>/dev/null
}

get_project_status() {
    local project_name="$1"
    jq -r --arg project "$project_name" '.projects[$project].status // "unknown"' "$CONFIG_FILE" 2>/dev/null
}

# =============================================================================
# PROJEKT-ERKENNUNG BASIEREND AUF PFADEN
# =============================================================================

detect_project_by_path() {
    local check_path="${1:-$CURRENT_DIR}"
    local detected_project=""
    
    log "Projekt-Erkennung für Pfad: $check_path"
    
    # Pattern-Matching aus Konfiguration
    local patterns
    patterns=$(jq -r '.path_patterns | to_entries[] | "\(.key)|\(.value)"' "$CONFIG_FILE" 2>/dev/null)
    
    while IFS='|' read -r pattern project; do
        # Konvertiere glob pattern zu regex (vereinfacht)
        local regex_pattern
        regex_pattern=$(echo "$pattern" | sed 's/\*\*/.*/' | sed 's/\*/[^\/]*/')
        
        if [[ "$check_path" =~ $regex_pattern ]]; then
            log "Pattern Match gefunden: $pattern -> $project"
            detected_project="$project"
            break
        fi
    done <<< "$patterns"
    
    # Fallback: Direkte Verzeichnis-Erkennung
    if [[ -z "$detected_project" ]]; then
        case "$check_path" in
            */plugin-todo*)
                detected_project="plugin-todo"
                ;;
            */plugin-article*)
                detected_project="article-builder"
                ;;
            */react*|*/forexsignale*)
                detected_project="forexsignale-magazine"
                ;;
        esac
    fi
    
    echo "$detected_project"
}

# =============================================================================
# PROJEKT-ERKENNUNG BASIEREND AUF TODO-SCOPE
# =============================================================================

detect_project_by_scope() {
    local scope="$1"
    
    if [[ -z "$scope" ]]; then
        return 1
    fi
    
    log "Projekt-Erkennung für Scope: $scope"
    
    # Scope-Mappings aus Konfiguration laden
    local projects
    projects=$(jq -r --arg scope "$scope" '.scope_mappings[$scope][]?' "$CONFIG_FILE" 2>/dev/null)
    
    if [[ -n "$projects" ]]; then
        # Erstes aktives Projekt zurückgeben
        while read -r project; do
            local status
            status=$(get_project_status "$project")
            if [[ "$status" == "active" ]]; then
                log "Scope-Match gefunden: $scope -> $project"
                echo "$project"
                return 0
            fi
        done <<< "$projects"
    fi
    
    return 1
}

# =============================================================================
# ARBEITSVERZEICHNIS VALIDATION
# =============================================================================

validate_working_directory() {
    local project_name="$1"
    local working_dir
    working_dir=$(jq -r --arg project "$project_name" '.projects[$project].directories.working_directory' "$CONFIG_FILE" 2>/dev/null)
    
    if [[ -z "$working_dir" || "$working_dir" == "null" ]]; then
        log_warning "Kein Arbeitsverzeichnis definiert für Projekt: $project_name"
        return 1
    fi
    
    if [[ ! -d "$working_dir" ]]; then
        log_error "Arbeitsverzeichnis nicht gefunden: $working_dir"
        return 1
    fi
    
    log_success "Arbeitsverzeichnis validiert: $working_dir"
    return 0
}

# =============================================================================
# DEPENDENCY VALIDATION
# =============================================================================

validate_dependencies() {
    local project_name="$1"
    local project_info
    project_info=$(get_project_info "$project_name")
    
    if [[ -z "$project_info" || "$project_info" == "null" ]]; then
        log_error "Projekt-Information nicht gefunden: $project_name"
        return 1
    fi
    
    log "Validiere Dependencies für Projekt: $project_name"
    
    # CLAUDE.md Check
    local claude_md
    claude_md=$(echo "$project_info" | jq -r '.config_files.claude_md // empty')
    if [[ -n "$claude_md" && ! -f "$claude_md" ]]; then
        log_warning "CLAUDE.md nicht gefunden: $claude_md"
    fi
    
    # package.json Check (falls vorhanden)
    local package_json
    package_json=$(echo "$project_info" | jq -r '.config_files.package_json // empty')
    if [[ -n "$package_json" && ! -f "$package_json" ]]; then
        log_warning "package.json nicht gefunden: $package_json"
    fi
    
    # Mount Points Check
    local mount_staging
    mount_staging=$(echo "$project_info" | jq -r '.directories.mount_staging // empty')
    if [[ -n "$mount_staging" && ! -d "$mount_staging" ]]; then
        log_warning "Staging Mount Point nicht verfügbar: $mount_staging"
    fi
    
    # MCP Server Check (required)
    local required_mcp
    required_mcp=$(echo "$project_info" | jq -r '.mcp_servers.required[]?' 2>/dev/null)
    if [[ -n "$required_mcp" ]]; then
        log "Erforderliche MCP Server: $(echo "$required_mcp" | tr '\n' ' ')"
    fi
    
    return 0
}

# =============================================================================
# PROJEKT-INFORMATIONEN AUSGEBEN
# =============================================================================

show_project_info() {
    local project_name="$1"
    local project_info
    project_info=$(get_project_info "$project_name")
    
    if [[ -z "$project_info" || "$project_info" == "null" ]]; then
        log_error "Projekt nicht gefunden: $project_name"
        return 1
    fi
    
    echo -e "\n${BLUE}=== PROJEKT INFORMATION: $project_name ===${NC}"
    
    # Basis-Informationen
    echo "Name: $(echo "$project_info" | jq -r '.name')"
    echo "Beschreibung: $(echo "$project_info" | jq -r '.description')"
    echo "Type: $(echo "$project_info" | jq -r '.type')"
    echo "Status: $(echo "$project_info" | jq -r '.status')"
    echo "Version: $(echo "$project_info" | jq -r '.version')"
    echo "Priorität: $(echo "$project_info" | jq -r '.priority')"
    
    # Verzeichnisse
    echo -e "\n${BLUE}Verzeichnisse:${NC}"
    echo "Working Directory: $(echo "$project_info" | jq -r '.directories.working_directory')"
    echo "Mount Staging: $(echo "$project_info" | jq -r '.directories.mount_staging // "N/A"')"
    echo "Mount Live: $(echo "$project_info" | jq -r '.directories.mount_live // "N/A"')"
    
    # Konfigurationsdateien
    echo -e "\n${BLUE}Konfiguration:${NC}"
    echo "CLAUDE.md: $(echo "$project_info" | jq -r '.config_files.claude_md // "N/A"')"
    echo "package.json: $(echo "$project_info" | jq -r '.config_files.package_json // "N/A"')"
    
    # tmux Konfiguration
    echo -e "\n${BLUE}tmux Session:${NC}"
    echo "Session: $(echo "$project_info" | jq -r '.tmux.session_name')"
    echo "Window: $(echo "$project_info" | jq -r '.tmux.window_name')"
    echo "Pane: $(echo "$project_info" | jq -r '.tmux.pane_target')"
    
    # MCP Server Requirements
    echo -e "\n${BLUE}MCP Server:${NC}"
    local required_mcp
    required_mcp=$(echo "$project_info" | jq -r '.mcp_servers.required[]?' 2>/dev/null | tr '\n' ' ')
    echo "Required: $required_mcp"
    local optional_mcp
    optional_mcp=$(echo "$project_info" | jq -r '.mcp_servers.optional[]?' 2>/dev/null | tr '\n' ' ')
    echo "Optional: $optional_mcp"
    
    echo ""
}

# =============================================================================
# LISTE ALLER PROJEKTE
# =============================================================================

list_all_projects() {
    echo -e "\n${BLUE}=== VERFÜGBARE PROJEKTE ===${NC}"
    
    local projects
    projects=$(get_project_list)
    
    if [[ -z "$projects" ]]; then
        log_error "Keine Projekte in Konfiguration gefunden"
        return 1
    fi
    
    while read -r project; do
        local status
        status=$(get_project_status "$project")
        local name
        name=$(jq -r --arg project "$project" '.projects[$project].name' "$CONFIG_FILE")
        local priority
        priority=$(jq -r --arg project "$project" '.projects[$project].priority' "$CONFIG_FILE")
        
        local status_color
        case "$status" in
            "active") status_color="$GREEN" ;;
            "inactive") status_color="$YELLOW" ;;
            *) status_color="$RED" ;;
        esac
        
        printf "%-20s ${status_color}%-10s${NC} (P%s) %s\n" "$project" "[$status]" "$priority" "$name"
    done <<< "$projects"
    
    echo ""
}

# =============================================================================
# HAUPTFUNKTIONEN
# =============================================================================

auto_detect() {
    local scope="${1:-}"
    local path="${2:-$CURRENT_DIR}"
    
    log "=== AUTO-DETECT GESTARTET ==="
    log "Aktueller Pfad: $path"
    log "Todo-Scope: ${scope:-"nicht angegeben"}"
    
    # Validierung
    if ! validate_config; then
        return 1
    fi
    
    local detected_project=""
    
    # 1. Versuche Erkennung über Todo-Scope
    if [[ -n "$scope" ]]; then
        detected_project=$(detect_project_by_scope "$scope")
    fi
    
    # 2. Fallback: Erkennung über Pfad
    if [[ -z "$detected_project" ]]; then
        detected_project=$(detect_project_by_path "$path")
    fi
    
    # 3. Default Projekt
    if [[ -z "$detected_project" ]]; then
        detected_project=$(jq -r '.global_config.default_project' "$CONFIG_FILE" 2>/dev/null)
        log "Fallback zu Default-Projekt: $detected_project"
    fi
    
    # Validierung des erkannten Projekts
    if [[ -n "$detected_project" ]] && [[ "$detected_project" != "null" ]]; then
        if validate_working_directory "$detected_project" && validate_dependencies "$detected_project"; then
            log_success "Projekt erfolgreich erkannt: $detected_project"
            echo "$detected_project"
            return 0
        else
            log_warning "Projekt erkannt aber Validation fehlgeschlagen: $detected_project"
        fi
    fi
    
    log_error "Kein gültiges Projekt erkannt"
    return 1
}

# =============================================================================
# COMMAND LINE INTERFACE
# =============================================================================

show_usage() {
    cat << EOF
USAGE: $(basename "$0") [OPTION] [ARGUMENTS]

Claude Project Configuration System - Project Detector

OPTIONS:
    auto-detect [scope] [path]    Automatische Projekt-Erkennung
    list                         Alle verfügbaren Projekte anzeigen  
    info <project>               Detaillierte Projekt-Informationen
    validate <project>           Projekt-Konfiguration validieren
    scope <scope>                Projekt basierend auf Todo-Scope finden
    path <path>                  Projekt basierend auf Pfad finden
    status                       System-Status anzeigen
    help                         Diese Hilfe anzeigen

EXAMPLES:
    $(basename "$0") auto-detect                     # Auto-detect aktuelles Verzeichnis
    $(basename "$0") auto-detect "Plugin Development" # Auto-detect mit Scope
    $(basename "$0") info plugin-todo               # Info für spezifisches Projekt
    $(basename "$0") validate article-builder      # Validiere Projekt-Config
    $(basename "$0") list                           # Alle Projekte anzeigen

LOGS:
    $LOG_FILE

CONFIG:
    $CONFIG_FILE

EOF
}

# =============================================================================
# MAIN LOGIC
# =============================================================================

main() {
    local command="${1:-auto-detect}"
    
    case "$command" in
        "auto-detect"|"detect")
            local scope="${2:-}"
            local path="${3:-$CURRENT_DIR}"
            auto_detect "$scope" "$path"
            ;;
        "list"|"ls")
            validate_config && list_all_projects
            ;;
        "info")
            local project="${2:-}"
            if [[ -z "$project" ]]; then
                log_error "Projekt-Name erforderlich für 'info' Befehl"
                show_usage
                exit 1
            fi
            validate_config && show_project_info "$project"
            ;;
        "validate")
            local project="${2:-}"
            if [[ -z "$project" ]]; then
                log_error "Projekt-Name erforderlich für 'validate' Befehl"
                show_usage
                exit 1
            fi
            validate_config && validate_dependencies "$project"
            ;;
        "scope")
            local scope="${2:-}"
            if [[ -z "$scope" ]]; then
                log_error "Scope erforderlich für 'scope' Befehl"
                show_usage
                exit 1
            fi
            validate_config && detect_project_by_scope "$scope"
            ;;
        "path")
            local path="${2:-}"
            if [[ -z "$path" ]]; then
                log_error "Pfad erforderlich für 'path' Befehl"
                show_usage
                exit 1
            fi
            validate_config && detect_project_by_path "$path"
            ;;
        "status")
            echo -e "${BLUE}=== SYSTEM STATUS ===${NC}"
            echo "Config File: $CONFIG_FILE"
            echo "Log File: $LOG_FILE"
            echo "Current Directory: $CURRENT_DIR"
            echo "Config Valid: $(validate_config && echo "✓" || echo "✗")"
            echo "Projects Count: $(get_project_list | wc -l)"
            echo ""
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