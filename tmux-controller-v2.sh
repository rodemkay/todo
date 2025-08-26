#!/bin/bash
# TMUX Controller V2 - Ohne störende Health-Checks
# Verwaltet tmux Sessions für verschiedene Projekte

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Konfiguration
KITTY_CONFIG_DIR="/home/rodemkay/.config/kitty"
CLAUDE_BIN="/home/rodemkay/.local/bin/claude"
PROJECTS_BASE="/home/rodemkay/www/react"

# Projekt-Konfigurationen
declare -A PROJECT_CONFIGS=(
    ["plugin-todo"]="plugin-todo:90:10"
    ["forexsignale"]="forexsignale-magazine:85:15"
    ["article-builder"]="article-builder:80:20"
    ["trading-bot"]="trading-bot:75:25"
)

# Funktion: Session existiert prüfen (OHNE störenden Test)
check_session_exists() {
    local session_name=$1
    if tmux has-session -t "$session_name" 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

# Funktion: Session erstellen mit Kitty
create_session_with_kitty() {
    local session_name=$1
    local project_path=$2
    local left_pane_size=${3:-90}
    local right_pane_size=${4:-10}
    
    echo -e "${BLUE}🚀 Erstelle neue Session: $session_name${NC}"
    
    # Kitty Session-Konfiguration erstellen
    cat > "$KITTY_CONFIG_DIR/sessions/${session_name}.conf" <<EOF
# Session für $session_name
cd $project_path

# Neues Tab mit tmux Session
launch --type=tab --tab-title="$session_name" sh -c "tmux new-session -s $session_name -c $project_path"

# Layout mit zwei Panes (90/10 Split)
layout splits
launch --location=hsplit --title="Claude" sh -c "cd $project_path && exec bash"
launch --location=vsplit --title="Monitor" sh -c "cd $project_path && exec bash"
resize_window shorter 10
EOF

    # Kitty mit Session starten
    kitty --session "$KITTY_CONFIG_DIR/sessions/${session_name}.conf" &
    
    # Warte bis Session bereit ist
    local max_wait=10
    local waited=0
    while ! check_session_exists "$session_name" && [ $waited -lt $max_wait ]; do
        sleep 1
        ((waited++))
    done
    
    if check_session_exists "$session_name"; then
        success "Session $session_name erfolgreich erstellt"
        
        # Claude in linkem Pane starten mit resume und dangerously-skip-permissions
        # Zuerst ins Projektverzeichnis wechseln, dann Claude starten
        sleep 2
        tmux send-keys -t "${session_name}:0.0" "cd $project_path" Enter
        sleep 1
        # Echo '1' um erste Session zu wählen, dann Claude mit Flags starten
        tmux send-keys -t "${session_name}:0.0" "echo '1' | claude --resume --dangerously-skip-permissions" Enter
        
        return 0
    else
        error "Session $session_name konnte nicht erstellt werden"
        return 1
    fi
}

# Funktion: Zu Session wechseln
switch_to_session() {
    local session_name=$1
    
    if check_session_exists "$session_name"; then
        echo -e "${GREEN}✓ Wechsle zu Session: $session_name${NC}"
        
        # Fokussiere Kitty Fenster mit dieser Session
        # Nutze wmctrl falls installiert
        if command -v wmctrl &> /dev/null; then
            wmctrl -a "$session_name" 2>/dev/null || true
        fi
        
        # Alternativ: xdotool falls installiert
        if command -v xdotool &> /dev/null; then
            xdotool search --name "$session_name" windowactivate 2>/dev/null || true
        fi
        
        return 0
    else
        return 1
    fi
}

# Funktion: Session Health Status (OHNE störenden Test)
get_session_health() {
    local session_name=${1:-claude}
    
    if ! check_session_exists "$session_name"; then
        echo "NO_SESSION"
        return 1
    fi
    
    # Prüfe nur ob Session existiert und Prozesse hat
    local pane_count=$(tmux list-panes -t "$session_name" 2>/dev/null | wc -l)
    if [ "$pane_count" -gt 0 ]; then
        echo "HEALTHY"
        return 0
    else
        echo "UNHEALTHY"
        return 1
    fi
}

# Funktion: Smart Session Switch
smart_session_switch() {
    local target_project=$1
    local current_session=$(tmux display-message -p '#S' 2>/dev/null || echo "none")
    
    # Bestimme Session-Namen basierend auf Projekt
    local target_session="claude"  # Default
    if [ -n "${PROJECT_CONFIGS[$target_project]:-}" ]; then
        target_session=$(echo "${PROJECT_CONFIGS[$target_project]}" | cut -d: -f1)
    fi
    
    echo -e "${CYAN}📍 Projekt: $target_project${NC}"
    echo -e "${CYAN}📍 Ziel-Session: $target_session${NC}"
    
    # Prüfe ob Session existiert
    if check_session_exists "$target_session"; then
        if [ "$current_session" != "$target_session" ]; then
            echo -e "${YELLOW}⚠ Session-Wechsel erforderlich${NC}"
            switch_to_session "$target_session"
        else
            echo -e "${GREEN}✓ Bereits in korrekter Session${NC}"
        fi
    else
        echo -e "${YELLOW}⚠ Session existiert nicht, erstelle neue...${NC}"
        
        # Bestimme Projekt-Pfad
        local project_path="$PROJECTS_BASE/$target_project"
        if [ ! -d "$project_path" ]; then
            project_path="$PROJECTS_BASE"
        fi
        
        # Erstelle neue Session
        create_session_with_kitty "$target_session" "$project_path"
    fi
}

# Funktion: Liste alle Sessions
list_sessions() {
    echo -e "${BLUE}📋 Aktive tmux Sessions:${NC}"
    echo "------------------------"
    
    if tmux list-sessions 2>/dev/null; then
        echo ""
        echo -e "${CYAN}Session Details:${NC}"
        for session in $(tmux list-sessions -F '#S' 2>/dev/null); do
            local health=$(get_session_health "$session")
            local status_icon="🟢"
            [ "$health" != "HEALTHY" ] && status_icon="🔴"
            
            echo -e "$status_icon $session - Status: $health"
            
            # Zeige Panes in Session
            tmux list-panes -t "$session" -F '  └─ Pane #{pane_index}: #{pane_current_command}' 2>/dev/null || true
        done
    else
        echo "Keine aktiven Sessions"
    fi
}

# Funktion: Kill Session
kill_session() {
    local session_name=$1
    
    if check_session_exists "$session_name"; then
        tmux kill-session -t "$session_name"
        success "Session $session_name beendet"
    else
        warning "Session $session_name existiert nicht"
    fi
}

# Erfolgs-Meldung
success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Fehler-Meldung
error() {
    echo -e "${RED}✗ $1${NC}"
}

# Warning-Meldung
warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Main - Kommando-Verarbeitung
main() {
    local command=${1:-help}
    
    case "$command" in
        check)
            local session=${2:-claude}
            if check_session_exists "$session"; then
                success "Session $session existiert"
            else
                error "Session $session existiert nicht"
            fi
            ;;
            
        health)
            local session=${2:-claude}
            local health=$(get_session_health "$session")
            echo "Session $session: $health"
            ;;
            
        create)
            local session=${2:-claude}
            local project=${3:-plugin-todo}
            local project_path="$PROJECTS_BASE/$project"
            create_session_with_kitty "$session" "$project_path"
            ;;
            
        switch)
            local project=${2:-plugin-todo}
            smart_session_switch "$project"
            ;;
            
        list)
            list_sessions
            ;;
            
        kill)
            local session=${2:-}
            if [ -z "$session" ]; then
                error "Session-Name erforderlich"
                exit 1
            fi
            kill_session "$session"
            ;;
            
        help|*)
            cat <<EOF
${BLUE}TMUX Controller V2 - Session Management${NC}
${CYAN}==========================================${NC}

Verwendung: $(basename $0) <command> [options]

Commands:
  check [session]     - Prüft ob Session existiert
  health [session]    - Zeigt Session-Gesundheitsstatus
  create [session] [project] - Erstellt neue Session mit Kitty
  switch [project]    - Wechselt intelligent zur Projekt-Session
  list               - Listet alle aktiven Sessions
  kill [session]     - Beendet eine Session
  help               - Zeigt diese Hilfe

Beispiele:
  $(basename $0) check claude
  $(basename $0) create claude plugin-todo
  $(basename $0) switch forexsignale
  $(basename $0) list
  $(basename $0) kill old-session

Konfigurierte Projekte:
EOF
            for project in "${!PROJECT_CONFIGS[@]}"; do
                echo "  - $project"
            done
            ;;
    esac
}

# Führe Main aus
main "$@"