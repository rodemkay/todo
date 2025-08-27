#!/bin/bash

# Robuster Session TODO Watcher - Mit Activity-Check
# Überwacht TODOs und prüft, ob Claude wirklich arbeitet

LOG_FILE="/home/rodemkay/www/react/plugin-todo/hooks/logs/session-watcher.log"
CHECK_INTERVAL=30
ACTIVITY_TIMEOUT=300  # 5 Minuten ohne Aktivität = Problem

# Farben für bessere Sichtbarkeit
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
    echo -e "${BLUE}[WATCHER]${NC} $1"
}

check_claude_activity() {
    # Prüft ob Claude wirklich aktiv ist
    local todo_id=$1
    
    # Prüfe wann die CURRENT_TODO_ID Datei zuletzt geändert wurde
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        local file_age=$(( $(date +%s) - $(stat -c %Y /tmp/CURRENT_TODO_ID) ))
        
        if [ $file_age -gt $ACTIVITY_TIMEOUT ]; then
            echo -e "${RED}⚠️  Claude hängt! TODO #$todo_id seit ${file_age} Sekunden inaktiv${NC}"
            log_message "Claude inactive for $file_age seconds on TODO #$todo_id"
            return 1  # Nicht aktiv
        else
            # Prüfe ob im Log kürzlich Aktivität war
            local recent_activity=$(tail -20 /home/rodemkay/www/react/plugin-todo/hooks/logs/todo_*.log 2>/dev/null | grep -c "$(date '+%Y-%m-%d %H')")
            if [ "$recent_activity" -gt "0" ]; then
                echo -e "${GREEN}✓ Claude ist aktiv (${recent_activity} Log-Einträge in letzter Stunde)${NC}"
                return 0  # Aktiv
            else
                echo -e "${YELLOW}⚠ Claude zeigt keine Log-Aktivität${NC}"
                return 1  # Wahrscheinlich nicht aktiv
            fi
        fi
    fi
    
    return 2  # Keine CURRENT_TODO_ID Datei
}

handle_stuck_todo() {
    local todo_id=$1
    
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}🚨 TODO #$todo_id HÄNGT!${NC}"
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # Option 1: Status auf 'blocked' setzen
    echo -e "${YELLOW}Setze TODO #$todo_id auf 'blocked'...${NC}"
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"UPDATE stage_project_todos SET status='blocked', bemerkungen=CONCAT(bemerkungen, '\nAutomatisch blockiert: Claude inactive $(date)') WHERE id=$todo_id\"" 2>/dev/null
    
    # Lösche die Lock-Dateien
    rm -f /tmp/CURRENT_TODO_ID
    rm -f /tmp/TODO_SENT_TO_CLAUDE
    
    echo -e "${GREEN}✓ TODO #$todo_id auf 'blocked' gesetzt und Locks gelöscht${NC}"
    log_message "Set TODO #$todo_id to blocked due to inactivity"
}

complete_current_todo() {
    local todo_id=$1
    log_message "Schließe TODO #$todo_id ab..."
    
    echo -e "${GREEN}[WATCHER] Führe TODO-Abschluss aus...${NC}"
    ./todo complete
    
    sleep 2
    
    if [ ! -f "/tmp/CURRENT_TODO_ID" ]; then
        log_message "TODO #$todo_id erfolgreich abgeschlossen"
        echo -e "${GREEN}✅ TODO #$todo_id abgeschlossen${NC}"
        # Lösche auch andere Marker
        rm -f /tmp/TASK_COMPLETED
        rm -f /tmp/TODO_SENT_TO_CLAUDE
        return 0
    else
        log_message "TODO #$todo_id Abschluss fehlgeschlagen"
        echo -e "${RED}❌ TODO #$todo_id konnte nicht abgeschlossen werden${NC}"
        return 1
    fi
}

load_and_start_todo() {
    local todo_id=$1
    local todo_title=$2
    
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}🎯 NEUES TODO GEFUNDEN!${NC}"
    echo -e "${GREEN}   ID: #$todo_id${NC}"
    echo -e "${GREEN}   Titel: $todo_title${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    log_message "Starte TODO #$todo_id: $todo_title"
    
    # Lösche alte Marker
    rm -f /tmp/TODO_SENT_TO_CLAUDE
    rm -f /tmp/TASK_COMPLETED
    rm -f /tmp/CLAUDE_AUTO_EXECUTE
    
    # Führe TODO aus - NUR ./todo, nicht mit -id!
    echo -e "${YELLOW}[WATCHER] Führe './todo' aus (lädt automatisch nächstes TODO)...${NC}"
    ./todo
    
    # Prüfe ob erfolgreich geladen
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        local loaded_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ "$loaded_id" == "$todo_id" ]; then
            echo -e "${GREEN}✓ TODO #$todo_id erfolgreich geladen${NC}"
            
            # NEU: Prüfe ob AUTO-EXECUTE Datei existiert und sende den Inhalt an Claude
            if [ -f "/tmp/CLAUDE_AUTO_EXECUTE" ]; then
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                echo -e "${CYAN}🚀 AUTO-EXECUTE MODUS AKTIVIERT!${NC}"
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                
                # Lese den Prompt-Inhalt
                local prompt_content=$(cat /tmp/CLAUDE_AUTO_EXECUTE)
                
                # Sende den Prompt direkt an Claude (im linken pane der plugin-todo session)
                echo -e "${YELLOW}[WATCHER] Sende claude_prompt automatisch an Claude...${NC}"
                
                # Verwende tmux send-keys um den Prompt zu senden
                # WICHTIG: Wir sind bereits IN der Session, also einfach den Text ausgeben
                echo -e "\n${CYAN}📋 AUTO-EXECUTION VON TODO #$todo_id:${NC}"
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
                cat /tmp/CLAUDE_AUTO_EXECUTE
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
                echo -e "${CYAN}⚠️ WICHTIG: Wenn fertig, führe aus:${NC}"
                echo -e "${YELLOW}echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED${NC}"
                
                # Markiere dass der Prompt gesendet wurde
                touch /tmp/TODO_SENT_TO_CLAUDE
                log_message "Auto-executed claude_prompt for TODO #$todo_id"
            else
                # Fallback zu alter Logik
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                echo -e "${CYAN}🤖 CLAUDE: Bitte bearbeite TODO #$todo_id${NC}"
                echo -e "${CYAN}   Beschreibung wurde geladen.${NC}"
                echo -e "${CYAN}   Wenn fertig: echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED${NC}"
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            fi
            
            return 0
        fi
    fi
    
    echo -e "${RED}❌ TODO #$todo_id konnte nicht geladen werden${NC}"
    return 1
}

# HAUPTLOOP
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}🤖 ROBUSTER SESSION TODO WATCHER GESTARTET${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
log_message "Robust Session TODO Watcher gestartet"

# Initialer Check
last_check_time=0

while true; do
    current_time=$(date +%s)
    
    # TASK_COMPLETED Check (höchste Priorität)
    if [ -f "/tmp/TASK_COMPLETED" ]; then
        current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ -n "$current_id" ]; then
            echo -e "${YELLOW}[WATCHER] TASK_COMPLETED erkannt für TODO #$current_id${NC}"
            complete_current_todo "$current_id"
        else
            echo -e "${YELLOW}[WATCHER] TASK_COMPLETED ohne aktives TODO - lösche Marker${NC}"
            rm -f /tmp/TASK_COMPLETED
        fi
        continue
    fi
    
    # Prüfe ob Claude beschäftigt ist
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        
        # DEAKTIVIERT: Automatisches Blockieren von TODOs
        # Der User entscheidet selbst, wann ein TODO blockiert werden soll
        # TODOs können Stunden/Tage alt sein bevor sie bearbeitet werden
        echo -e "${BLUE}[WATCHER] TODO #$current_id ist aktiv${NC}"
        log_message "TODO #$current_id is active"
        
        # Alte problematische Logik auskommentiert:
        # if check_claude_activity "$current_id"; then
        #     echo -e "${BLUE}[WATCHER] Claude arbeitet aktiv an TODO #$current_id${NC}"
        #     log_message "Claude actively working on TODO #$current_id"
        # else
        #     # Claude ist inaktiv - handle stuck TODO
        #     handle_stuck_todo "$current_id"
        #     continue
        # fi
    else
        # Keine aktive Session - suche neue TODOs
        echo -e "${BLUE}[WATCHER] Suche nach neuen TODOs...${NC}"
        
        # Hole Anzahl offener TODOs
        NEW_TODOS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1' --skip-column-names" 2>/dev/null | tr -d '\r\n' | grep -o '[0-9]*' | head -1)
        
        if [ -n "$NEW_TODOS" ] && [ "$NEW_TODOS" -gt "0" ] 2>/dev/null; then
            # Hole Details des nächsten TODOs
            TODO_INFO=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id, title FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1' --skip-column-names" 2>/dev/null)
            
            TODO_ID=$(echo "$TODO_INFO" | cut -f1)
            TODO_TITLE=$(echo "$TODO_INFO" | cut -f2)
            
            if [ -n "$TODO_ID" ]; then
                load_and_start_todo "$TODO_ID" "$TODO_TITLE"
            fi
        else
            echo -e "${BLUE}[WATCHER] Keine offenen TODOs gefunden (Count: $NEW_TODOS)${NC}"
            log_message "No open todos found"
            
            # Zeige Statistik
            TOTAL=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            OPEN=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\"' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            COMPLETED=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"completed\"' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            BLOCKED=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"blocked\"' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            
            echo -e "${CYAN}📊 Statistik: Total: $TOTAL | Offen: $OPEN (davon 0 mit bearbeiten=1) | Completed: $COMPLETED | Blocked: $BLOCKED${NC}"
        fi
    fi
    
    # Warte bis zum nächsten Check
    echo -e "${BLUE}[WATCHER] Warte $CHECK_INTERVAL Sekunden...${NC}"
    sleep $CHECK_INTERVAL
done