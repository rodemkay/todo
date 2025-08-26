#!/bin/bash

# Claude Project Session Switcher - Master Control
# Version: 1.0
# Autor: Claude Code Integration

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Pfade und Konfiguration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECTS_DIR="/home/rodemkay/www/react"
LOGS_DIR="$SCRIPT_DIR/logs"
SESSION_LOG="$LOGS_DIR/session-switch.log"
TMUX_CONTROLLER="$SCRIPT_DIR/tmux-controller.sh"
SESSION_MANAGER="$SCRIPT_DIR/session-manager.sh"
PROJECT_DETECTOR="$SCRIPT_DIR/project-detector.sh"

# Logging-Funktion
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $*" >> "$SESSION_LOG"
}

# Error Handler
error_exit() {
    echo -e "${RED}FEHLER: $1${NC}" >&2
    log "ERROR: $1"
    exit 1
}

# Erfolgs-Ausgabe
success() {
    echo -e "${GREEN}✓ $1${NC}"
    log "SUCCESS: $1"
}

# Warning-Ausgabe
warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
    log "WARNING: $1"
}

# Info-Ausgabe
info() {
    echo -e "${BLUE}ℹ $1${NC}"
    log "INFO: $1"
}

# Logs-Verzeichnis erstellen
mkdir -p "$LOGS_DIR"

# Initialisierung prüfen
init_check() {
    info "Führe System-Check durch..."
    
    # Erforderliche Scripts prüfen
    local required_scripts=("$TMUX_CONTROLLER" "$SESSION_MANAGER" "$PROJECT_DETECTOR")
    for script in "${required_scripts[@]}"; do
        if [[ ! -f "$script" ]]; then
            error_exit "Erforderliches Script nicht gefunden: $script"
        fi
        if [[ ! -x "$script" ]]; then
            chmod +x "$script"
            info "Ausführberechtigung für $script gesetzt"
        fi
    done
    
    # tmux prüfen
    if ! command -v tmux >/dev/null 2>&1; then
        error_exit "tmux ist nicht installiert"
    fi
    
    success "System-Check erfolgreich"
}

# Aktuellen Status anzeigen
show_status() {
    echo -e "${PURPLE}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${PURPLE}║                    CLAUDE SESSION STATUS                   ║${NC}"
    echo -e "${PURPLE}╚════════════════════════════════════════════════════════════╝${NC}"
    echo
    
    # Aktuelle Session
    local current_session
    if current_session=$("$TMUX_CONTROLLER" current 2>/dev/null); then
        echo -e "${GREEN}📊 Aktuelle Session: ${CYAN}$current_session${NC}"
    else
        echo -e "${RED}📊 Keine aktive tmux Session gefunden${NC}"
    fi
    
    # Aktuelles Verzeichnis
    echo -e "${BLUE}📁 Arbeitsverzeichnis: ${CYAN}$(pwd)${NC}"
    
    # Projekt-Erkennung
    local project_info
    if project_info=$("$PROJECT_DETECTOR" current 2>/dev/null); then
        echo -e "${GREEN}🎯 Erkanntes Projekt: ${CYAN}$project_info${NC}"
    else
        echo -e "${YELLOW}🎯 Kein Projekt erkannt${NC}"
    fi
    
    # Verfügbare Projekte
    echo
    echo -e "${PURPLE}📋 Verfügbare Projekte:${NC}"
    if [[ -f "$PROJECT_DETECTOR" ]]; then
        "$PROJECT_DETECTOR" list || warning "Projekte konnten nicht aufgelistet werden"
    fi
    
    echo
    # Session Health
    local health_status
    if health_status=$("$TMUX_CONTROLLER" health 2>/dev/null); then
        echo -e "${GREEN}💚 Session Health: OK${NC}"
    else
        echo -e "${RED}💔 Session Health: PROBLEME ERKANNT${NC}"
        echo -e "${YELLOW}   Führe './claude-switch.sh fix' aus${NC}"
    fi
}

# Session wechseln
switch_session() {
    local target_project="$1"
    
    info "Wechsle zu Projekt: $target_project"
    
    # TASK_COMPLETED sicherstellen
    if [[ -f "/tmp/TASK_COMPLETED" ]]; then
        info "TASK_COMPLETED bereits gesetzt"
    else
        warning "Setze TASK_COMPLETED vor Session-Wechsel"
        echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
    fi
    
    # Bestätigung bei aktiver Session
    local current_session
    if current_session=$("$TMUX_CONTROLLER" current 2>/dev/null); then
        echo -e "${YELLOW}⚠ Aktive Session erkannt: $current_session${NC}"
        echo -e "${YELLOW}   Möchten Sie wirklich wechseln? [j/N]${NC}"
        read -r confirmation
        if [[ ! "$confirmation" =~ ^[JjYy]$ ]]; then
            info "Session-Wechsel abgebrochen"
            return 0
        fi
    fi
    
    # Session-Wechsel durchführen
    if "$SESSION_MANAGER" switch "$target_project"; then
        success "Session erfolgreich gewechselt zu: $target_project"
        
        # Status nach Wechsel anzeigen
        echo
        show_status
    else
        error_exit "Session-Wechsel fehlgeschlagen"
    fi
}

# Emergency Commands
panic_button() {
    warning "🚨 PANIC BUTTON aktiviert!"
    
    echo -e "${RED}Dies wird ALLE tmux Sessions beenden!${NC}"
    echo -e "${YELLOW}Sind Sie sicher? [j/N]${NC}"
    read -r confirmation
    if [[ ! "$confirmation" =~ ^[JjYy]$ ]]; then
        info "Panic Button abgebrochen"
        return 0
    fi
    
    # Alle Sessions beenden
    if "$TMUX_CONTROLLER" kill-all; then
        success "Alle Sessions beendet"
    else
        error_exit "Fehler beim Beenden der Sessions"
    fi
    
    # Cleanup
    rm -f /tmp/TASK_COMPLETED
    rm -f /tmp/claude_session_lock
    
    success "System zurückgesetzt"
}

# Safe Mode
safe_mode() {
    info "🛡️ Starte Safe Mode..."
    
    # Session-Lock entfernen
    rm -f /tmp/claude_session_lock
    
    # Stuck tasks bereinigen
    rm -f /tmp/TASK_COMPLETED
    
    # Minimale Session starten
    if "$SESSION_MANAGER" create "safe-mode" "/tmp"; then
        success "Safe Mode Session erstellt"
        "$TMUX_CONTROLLER" attach "safe-mode"
    else
        error_exit "Safe Mode konnte nicht gestartet werden"
    fi
}

# System reparieren
fix_system() {
    info "🔧 Führe System-Reparatur durch..."
    
    # Session Health Check
    "$TMUX_CONTROLLER" health || {
        warning "Session-Probleme erkannt, führe Reparatur durch..."
        "$TMUX_CONTROLLER" repair
    }
    
    # Stuck Locks entfernen
    if [[ -f "/tmp/claude_session_lock" ]]; then
        local lock_age
        lock_age=$(stat -c %Y /tmp/claude_session_lock)
        local current_time
        current_time=$(date +%s)
        local age_minutes=$(( (current_time - lock_age) / 60 ))
        
        if (( age_minutes > 30 )); then
            warning "Altes Session-Lock gefunden (${age_minutes}m), entferne es"
            rm -f /tmp/claude_session_lock
        fi
    fi
    
    success "System-Reparatur abgeschlossen"
}

# Projekt-Liste anzeigen
list_projects() {
    echo -e "${PURPLE}📋 Verfügbare Projekte:${NC}"
    echo
    "$PROJECT_DETECTOR" list
}

# Hilfe anzeigen
show_help() {
    cat << 'EOF'
Claude Project Session Switcher - Hilfe

VERWENDUNG:
  ./claude-switch.sh [BEFEHL] [OPTIONEN]

BEFEHLE:
  status                Zeigt aktuellen Status und verfügbare Projekte
  switch <projekt>      Wechselt zu dem angegebenen Projekt
  list                  Zeigt alle verfügbaren Projekte
  
  Emergency Commands:
  panic                 🚨 Beendet ALLE Sessions (Notfall)
  safe                  🛡️ Startet Safe Mode Session
  fix                   🔧 Repariert System-Probleme
  
  System:
  init                  Führt System-Initialisierung durch
  logs                  Zeigt Session-Logs
  help                  Zeigt diese Hilfe

BEISPIELE:
  ./claude-switch.sh status
  ./claude-switch.sh switch plugin-todo
  ./claude-switch.sh switch plugin-article
  ./claude-switch.sh panic
  ./claude-switch.sh safe

LOGS:
  Session-Logs: ./logs/session-switch.log

EOF
}

# Logs anzeigen
show_logs() {
    if [[ -f "$SESSION_LOG" ]]; then
        echo -e "${BLUE}📄 Session-Logs (letzte 20 Zeilen):${NC}"
        echo
        tail -20 "$SESSION_LOG"
    else
        info "Keine Session-Logs vorhanden"
    fi
}

# Hauptfunktion
main() {
    local command="${1:-status}"
    
    case "$command" in
        "status")
            show_status
            ;;
        "switch")
            if [[ -z "${2:-}" ]]; then
                error_exit "Projekt-Name erforderlich. Verwendung: ./claude-switch.sh switch <projekt>"
            fi
            init_check
            switch_session "$2"
            ;;
        "list")
            list_projects
            ;;
        "panic")
            init_check
            panic_button
            ;;
        "safe")
            init_check
            safe_mode
            ;;
        "fix")
            init_check
            fix_system
            ;;
        "init")
            init_check
            success "Initialisierung erfolgreich"
            ;;
        "logs")
            show_logs
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