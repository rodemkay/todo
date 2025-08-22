#!/bin/bash

# Claude Code Project Switcher
# Ermöglicht schnellen Wechsel zwischen Projekten

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Funktion zum Anzeigen von Projekten
show_projects() {
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}         Claude Code Project Switcher${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "${YELLOW}Verfügbare Projekte:${NC}"
    echo ""
    echo "1) 📋 Todo Plugin        - /home/rodemkay/www/react/todo"
    echo "2) 💱 ForexSignale       - /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging"
    echo "3) 🎯 Breakout Brain     - /home/rodemkay/www/react/breakout-brain"
    echo "4) 🖥️  Development       - /home/rodemkay/www/react/development"
    echo "5) 📁 Custom Path        - Eigenen Pfad eingeben"
    echo "0) ❌ Abbrechen"
    echo ""
}

# Funktion zum Wechseln des Projekts
switch_project() {
    local project_path="$1"
    
    if [ ! -d "$project_path" ]; then
        echo -e "${RED}❌ Fehler: Verzeichnis $project_path existiert nicht!${NC}"
        return 1
    fi
    
    echo -e "${GREEN}✅ Wechsle zu: $project_path${NC}"
    
    # Prüfe ob Claude Code läuft
    if pgrep -f "claude" > /dev/null; then
        echo -e "${YELLOW}⚠️  Claude Code läuft noch. Sende Exit-Signal...${NC}"
        
        # Sende Exit an die tmux Session
        tmux send-keys -t claude "exit" Enter 2>/dev/null || {
            echo -e "${RED}Konnte Exit nicht senden. Bitte manuell beenden.${NC}"
        }
        
        # Warte kurz
        sleep 2
    fi
    
    # Wechsle Verzeichnis
    cd "$project_path" || return 1
    
    echo -e "${BLUE}📂 Aktuelles Verzeichnis: $(pwd)${NC}"
    
    # Starte Claude Code neu
    echo -e "${GREEN}🚀 Starte Claude Code...${NC}"
    echo ""
    echo -e "${YELLOW}Führe aus: claude --resume --dangerously-skip-permissions${NC}"
    
    # Option 1: Direkt starten
    claude --resume --dangerously-skip-permissions
    
    # Option 2: In tmux Session (falls gewünscht)
    # tmux new-session -d -s claude-$$ "cd $project_path && claude --resume --dangerously-skip-permissions"
    # tmux attach-session -t claude-$$
}

# Hauptprogramm
main() {
    show_projects
    
    echo -n "Wähle ein Projekt (0-5): "
    read -r choice
    
    case $choice in
        1)
            switch_project "/home/rodemkay/www/react/todo"
            ;;
        2)
            switch_project "/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging"
            ;;
        3)
            switch_project "/home/rodemkay/www/react/breakout-brain"
            ;;
        4)
            switch_project "/home/rodemkay/www/react/development"
            ;;
        5)
            echo -n "Gib den vollständigen Pfad ein: "
            read -r custom_path
            switch_project "$custom_path"
            ;;
        0)
            echo -e "${YELLOW}Abgebrochen.${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}Ungültige Auswahl!${NC}"
            exit 1
            ;;
    esac
}

# Script ausführen
main