#!/bin/bash

# Project Detector for Claude Session Switcher
# Detects current project context and lists available projects

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

# Basis-Pfade
PROJECTS_BASE="/home/rodemkay/www/react"

# Aktuelles Projekt erkennen
detect_current_project() {
    local current_dir="$PWD"
    local project_name=""
    
    # Direkte Projekt-Erkennung
    if [[ "$current_dir" == *"/plugin-todo"* ]]; then
        project_name="plugin-todo"
    elif [[ "$current_dir" == *"/plugin-article"* ]]; then
        project_name="plugin-article"
    elif [[ "$current_dir" == *"/forexsignale-magazine"* ]]; then
        project_name="forexsignale-magazine"
    elif [[ "$current_dir" == *"/mcp-client"* ]]; then
        project_name="mcp-client"
    elif [[ "$current_dir" == "$PROJECTS_BASE"/* ]]; then
        # Fallback: Extrahiere Projektname aus Pfad
        project_name=$(echo "$current_dir" | sed "s|$PROJECTS_BASE/||" | cut -d'/' -f1)
    fi
    
    if [[ -n "$project_name" && -d "$PROJECTS_BASE/$project_name" ]]; then
        echo "$project_name"
        return 0
    fi
    
    return 1
}

# Verfügbare Projekte auflisten
list_available_projects() {
    local projects=()
    
    # Definierte Plugin-Projekte
    for plugin in plugin-todo plugin-article; do
        if [[ -d "$PROJECTS_BASE/$plugin" ]]; then
            local description=""
            if [[ -f "$PROJECTS_BASE/$plugin/package.json" ]]; then
                description=$(grep '"description"' "$PROJECTS_BASE/$plugin/package.json" 2>/dev/null | cut -d'"' -f4 | head -c 40 || echo "")
            fi
            if [[ -n "$description" ]]; then
                echo -e "${GREEN}🔌 $plugin${NC} - ${GRAY}$description${NC}"
            else
                echo -e "${GREEN}🔌 $plugin${NC} - ${GRAY}WordPress Plugin${NC}"
            fi
        fi
    done
    
    # Theme-Projekte
    for theme in forexsignale-magazine; do
        if [[ -d "$PROJECTS_BASE/$theme" ]]; then
            echo -e "${BLUE}🎨 $theme${NC} - ${GRAY}WordPress Theme${NC}"
        fi
    done
    
    # Andere React-Projekte
    for dir in "$PROJECTS_BASE"/*/; do
        if [[ -d "$dir" ]]; then
            local name=$(basename "$dir")
            
            # Skip bereits behandelte Projekte
            if [[ "$name" == "plugin-"* || "$name" == "*magazine*" ]]; then
                continue
            fi
            
            # Nur wenn package.json oder relevante Dateien vorhanden
            if [[ -f "$dir/package.json" ]] || [[ -f "$dir/README.md" ]] || [[ -f "$dir/CLAUDE.md" ]]; then
                local type="Project"
                if [[ -f "$dir/package.json" ]]; then
                    type="React"
                elif [[ -f "$dir/CLAUDE.md" ]]; then
                    type="Claude"
                fi
                echo -e "${PURPLE}⚛️  $name${NC} - ${GRAY}$type Project${NC}"
            fi
        fi
    done
}

# Projekt-Informationen abrufen
get_project_info() {
    local project_name="$1"
    local project_dir="$PROJECTS_BASE/$project_name"
    
    if [[ ! -d "$project_dir" ]]; then
        return 1
    fi
    
    echo -e "${CYAN}📁 Project: $project_name${NC}"
    echo -e "${CYAN}📍 Path: $project_dir${NC}"
    
    # Git Info
    if [[ -d "$project_dir/.git" ]]; then
        local branch=$(cd "$project_dir" && git branch --show-current 2>/dev/null || echo "unknown")
        local status=$(cd "$project_dir" && git status --porcelain 2>/dev/null | wc -l)
        echo -e "${GREEN}🌳 Git Branch: $branch${NC}"
        echo -e "${GREEN}📝 Uncommitted: $status files${NC}"
    fi
    
    # Package.json Info
    if [[ -f "$project_dir/package.json" ]]; then
        local version=$(cd "$project_dir" && grep '"version"' package.json | cut -d'"' -f4 2>/dev/null || echo "unknown")
        echo -e "${BLUE}📦 Version: $version${NC}"
    fi
    
    # WordPress Plugin Info
    if [[ -f "$project_dir"/*.php ]] && grep -q "Plugin Name:" "$project_dir"/*.php 2>/dev/null; then
        local plugin_name=$(grep "Plugin Name:" "$project_dir"/*.php | head -1 | cut -d: -f2 | xargs)
        echo -e "${PURPLE}🔌 WordPress Plugin: $plugin_name${NC}"
    fi
    
    return 0
}

# Projekt-Pfad abrufen
get_project_path() {
    local project_name="$1"
    local project_dir="$PROJECTS_BASE/$project_name"
    
    if [[ -d "$project_dir" ]]; then
        echo "$project_dir"
        return 0
    fi
    
    return 1
}

# Projektvarianten suchen
find_project_variants() {
    local search_term="$1"
    local matches=()
    
    for dir in "$PROJECTS_BASE"/*/; do
        if [[ -d "$dir" ]]; then
            local name=$(basename "$dir")
            if [[ "$name" == *"$search_term"* ]]; then
                matches+=("$name")
            fi
        fi
    done
    
    if (( ${#matches[@]} > 0 )); then
        echo "Mögliche Matches für '$search_term':"
        for match in "${matches[@]}"; do
            echo -e "${GREEN}  • $match${NC}"
        done
        return 0
    fi
    
    return 1
}

# Hilfe anzeigen
show_help() {
    cat << 'EOF'
Project Detector - Hilfe

VERWENDUNG:
  ./project-detector.sh [BEFEHL] [OPTIONEN]

BEFEHLE:
  current               Erkennt aktuelles Projekt aus Arbeitsverzeichnis
  list                  Listet alle verfügbaren Projekte auf
  info <projekt>        Zeigt detaillierte Projekt-Informationen
  path <projekt>        Gibt Projekt-Pfad zurück
  find <begriff>        Sucht nach Projekten mit ähnlichem Namen
  help                  Zeigt diese Hilfe

BEISPIELE:
  ./project-detector.sh current
  ./project-detector.sh list
  ./project-detector.sh info plugin-todo
  ./project-detector.sh path plugin-article
  ./project-detector.sh find todo

RÜCKGABEWERTE:
  0    Erfolgreich
  1    Projekt nicht gefunden / Fehler

EOF
}

# Hauptfunktion
main() {
    local command="${1:-current}"
    
    case "$command" in
        "current")
            if detect_current_project; then
                return 0
            else
                echo -e "${YELLOW}Kein Projekt erkannt${NC}" >&2
                return 1
            fi
            ;;
        "list")
            echo -e "${BLUE}📋 Verfügbare Projekte:${NC}"
            echo
            list_available_projects
            ;;
        "info")
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Projekt-Name erforderlich${NC}" >&2
                echo "Usage: ./project-detector.sh info <projekt>"
                exit 1
            fi
            get_project_info "$2"
            ;;
        "path")
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Projekt-Name erforderlich${NC}" >&2
                exit 1
            fi
            get_project_path "$2"
            ;;
        "find")
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Suchbegriff erforderlich${NC}" >&2
                exit 1
            fi
            find_project_variants "$2"
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