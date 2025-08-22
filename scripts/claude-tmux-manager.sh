#!/bin/bash

# Claude Code TMUX Manager
# Verwaltet mehrere Claude Sessions in tmux

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Funktion: Zeige alle Claude tmux Sessions
show_sessions() {
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}         Claude Code TMUX Manager${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "${YELLOW}Aktive Claude Sessions:${NC}"
    
    # Liste alle tmux Sessions die "claude" enthalten
    tmux list-sessions 2>/dev/null | grep -i claude | while read -r line; do
        echo "  • $line"
    done || echo "  Keine aktiven Claude Sessions gefunden."
    
    echo ""
}

# Funktion: Neue Claude Session erstellen
create_session() {
    local session_name="$1"
    local project_path="$2"
    
    if [ -z "$session_name" ]; then
        echo -n "Session Name (z.B. claude-todo): "
        read -r session_name
    fi
    
    if [ -z "$project_path" ]; then
        echo -n "Projekt Pfad: "
        read -r project_path
    fi
    
    if [ ! -d "$project_path" ]; then
        echo -e "${RED}❌ Verzeichnis existiert nicht: $project_path${NC}"
        return 1
    fi
    
    echo -e "${GREEN}✅ Erstelle neue Session: $session_name${NC}"
    echo -e "${BLUE}📂 Projekt: $project_path${NC}"
    
    # Erstelle neue tmux Session
    tmux new-session -d -s "$session_name" -c "$project_path" \
        "echo 'Starting Claude Code in $project_path...'; claude --resume --dangerously-skip-permissions; bash"
    
    echo -e "${GREEN}✅ Session erstellt!${NC}"
    echo ""
    echo "Verbinde mit: tmux attach -t $session_name"
}

# Funktion: Zwischen Sessions wechseln
switch_session() {
    echo -e "${YELLOW}Verfügbare Sessions:${NC}"
    
    # Array für Sessions
    declare -a sessions
    local i=1
    
    while IFS= read -r session; do
        sessions[$i]=$(echo "$session" | cut -d: -f1)
        echo "$i) $session"
        ((i++))
    done < <(tmux list-sessions 2>/dev/null | grep -i claude)
    
    if [ ${#sessions[@]} -eq 0 ]; then
        echo -e "${RED}Keine Claude Sessions gefunden!${NC}"
        return 1
    fi
    
    echo -n "Wähle Session (1-$((i-1))): "
    read -r choice
    
    if [ -n "${sessions[$choice]}" ]; then
        echo -e "${GREEN}✅ Wechsle zu: ${sessions[$choice]}${NC}"
        tmux attach -t "${sessions[$choice]}"
    else
        echo -e "${RED}Ungültige Auswahl!${NC}"
    fi
}

# Funktion: Session beenden
kill_session() {
    echo -e "${YELLOW}Aktive Claude Sessions:${NC}"
    
    declare -a sessions
    local i=1
    
    while IFS= read -r session; do
        sessions[$i]=$(echo "$session" | cut -d: -f1)
        echo "$i) $session"
        ((i++))
    done < <(tmux list-sessions 2>/dev/null | grep -i claude)
    
    if [ ${#sessions[@]} -eq 0 ]; then
        echo -e "${RED}Keine Claude Sessions zum Beenden gefunden!${NC}"
        return 1
    fi
    
    echo "a) Alle Sessions beenden"
    echo -n "Wähle Session zum Beenden (1-$((i-1)) oder a): "
    read -r choice
    
    if [ "$choice" = "a" ]; then
        echo -e "${YELLOW}⚠️  Beende alle Claude Sessions...${NC}"
        for session in "${sessions[@]}"; do
            [ -n "$session" ] && tmux kill-session -t "$session" 2>/dev/null
        done
        echo -e "${GREEN}✅ Alle Sessions beendet!${NC}"
    elif [ -n "${sessions[$choice]}" ]; then
        echo -e "${YELLOW}Beende Session: ${sessions[$choice]}${NC}"
        tmux kill-session -t "${sessions[$choice]}"
        echo -e "${GREEN}✅ Session beendet!${NC}"
    else
        echo -e "${RED}Ungültige Auswahl!${NC}"
    fi
}

# Funktion: Schnellstart für häufige Projekte
quickstart() {
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}         Schnellstart Projekte${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo ""
    echo "1) 📋 Todo Plugin"
    echo "2) 💱 ForexSignale"
    echo "3) 🎯 Breakout Brain"
    echo "4) 🖥️  Development"
    echo "0) Zurück"
    echo ""
    echo -n "Wahl: "
    read -r choice
    
    case $choice in
        1)
            create_session "claude-todo" "/home/rodemkay/www/react/todo"
            ;;
        2)
            create_session "claude-forex" "/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging"
            ;;
        3)
            create_session "claude-brain" "/home/rodemkay/www/react/breakout-brain"
            ;;
        4)
            create_session "claude-dev" "/home/rodemkay/www/react/development"
            ;;
        0)
            return
            ;;
        *)
            echo -e "${RED}Ungültige Auswahl!${NC}"
            ;;
    esac
}

# Hauptmenü
main_menu() {
    while true; do
        show_sessions
        
        echo -e "${YELLOW}Optionen:${NC}"
        echo "1) 🚀 Schnellstart (vordefinierte Projekte)"
        echo "2) ➕ Neue Claude Session erstellen"
        echo "3) 🔄 Zu Session wechseln"
        echo "4) 📋 Sessions anzeigen"
        echo "5) ❌ Session beenden"
        echo "6) 🔧 tmux Cheatsheet anzeigen"
        echo "0) 🚪 Beenden"
        echo ""
        echo -n "Wahl: "
        read -r choice
        
        case $choice in
            1)
                quickstart
                ;;
            2)
                create_session
                ;;
            3)
                switch_session
                ;;
            4)
                tmux list-sessions 2>/dev/null | grep -i claude || echo "Keine Claude Sessions aktiv."
                echo ""
                read -p "Enter zum Fortfahren..."
                ;;
            5)
                kill_session
                ;;
            6)
                echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
                echo -e "${GREEN}         TMUX Cheatsheet${NC}"
                echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
                echo ""
                echo "Prefix Key: Ctrl+b (drücke zuerst Ctrl+b, dann den Befehl)"
                echo ""
                echo "SESSIONS:"
                echo "  d         - Session verlassen (detach)"
                echo "  s         - Session Liste anzeigen"
                echo "  $         - Session umbenennen"
                echo ""
                echo "PANES:"
                echo "  %         - Vertikal teilen"
                echo "  \"         - Horizontal teilen"
                echo "  Pfeile    - Zwischen Panes wechseln"
                echo "  x         - Pane schließen"
                echo "  z         - Pane zoom toggle"
                echo "  {/}       - Pane verschieben"
                echo ""
                echo "WINDOWS:"
                echo "  c         - Neues Window"
                echo "  n/p       - Nächstes/Vorheriges Window"
                echo "  0-9       - Zu Window Nummer wechseln"
                echo "  ,         - Window umbenennen"
                echo ""
                echo "COPY MODE:"
                echo "  [         - Copy Mode starten"
                echo "  Space     - Selektion starten"
                echo "  Enter     - Kopieren und beenden"
                echo "  ]         - Einfügen"
                echo ""
                read -p "Enter zum Fortfahren..."
                ;;
            0)
                echo -e "${GREEN}Auf Wiedersehen!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}Ungültige Auswahl!${NC}"
                sleep 1
                ;;
        esac
    done
}

# Prüfe ob tmux installiert ist
if ! command -v tmux &> /dev/null; then
    echo -e "${RED}❌ tmux ist nicht installiert!${NC}"
    echo "Installiere mit: sudo apt install tmux"
    exit 1
fi

# Starte Hauptmenü
main_menu