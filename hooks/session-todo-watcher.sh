#!/bin/bash

# Session TODO Watcher - Läuft IN der Claude Session
# Überwacht und führt TODOs sequentiell aus

LOG_FILE="/home/rodemkay/www/react/plugin-todo/hooks/logs/session-watcher.log"
CHECK_INTERVAL=30

# Farben für bessere Sichtbarkeit
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
    echo -e "${BLUE}[WATCHER]${NC} $1"
}

complete_current_todo() {
    local todo_id=$1
    log_message "Schließe TODO #$todo_id ab..."
    
    # Führe completion aus
    echo -e "${GREEN}[WATCHER] Führe TODO-Abschluss aus...${NC}"
    ./todo complete
    
    # Warte kurz auf Completion
    sleep 2
    
    # Prüfe ob erfolgreich
    if [ ! -f "/tmp/CURRENT_TODO_ID" ]; then
        log_message "TODO #$todo_id erfolgreich abgeschlossen"
        echo -e "${GREEN}✅ TODO #$todo_id abgeschlossen${NC}"
        return 0
    else
        log_message "TODO #$todo_id Abschluss fehlgeschlagen"
        echo -e "${RED}❌ TODO #$todo_id konnte nicht abgeschlossen werden${NC}"
        return 1
    fi
}

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}🤖 SESSION TODO WATCHER GESTARTET${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
log_message "Session TODO Watcher gestartet"

while true; do
    # Prüfe ob TASK_COMPLETED gesetzt wurde
    if [ -f "/tmp/TASK_COMPLETED" ]; then
        current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ -n "$current_id" ]; then
            echo -e "${YELLOW}[WATCHER] TASK_COMPLETED erkannt für TODO #$current_id${NC}"
            complete_current_todo "$current_id"
            rm -f /tmp/TASK_COMPLETED
        fi
    fi
    
    # Prüfe ob Claude beschäftigt ist
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        echo -e "${BLUE}[WATCHER] Claude arbeitet an TODO #$current_id${NC}"
        log_message "Claude arbeitet an TODO #$current_id"
    else
        # Suche nächstes TODO
        echo -e "${BLUE}[WATCHER] Suche nach neuen TODOs...${NC}"
        
        NEW_TODOS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1' --skip-column-names" 2>/dev/null | tr -d '\r\n' | grep -o '[0-9]*' | head -1)
        
        if [ -n "$NEW_TODOS" ] && [ "$NEW_TODOS" -gt "0" ] 2>/dev/null; then
            # Hole Details des nächsten TODOs
            TODO_INFO=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id, title FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1' --skip-column-names" 2>/dev/null)
            
            TODO_ID=$(echo "$TODO_INFO" | cut -f1)
            TODO_TITLE=$(echo "$TODO_INFO" | cut -f2)
            
            echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            echo -e "${GREEN}🎯 NEUES TODO GEFUNDEN!${NC}"
            echo -e "${GREEN}   ID: #$TODO_ID${NC}"
            echo -e "${GREEN}   Titel: $TODO_TITLE${NC}"
            echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            
            log_message "Starte TODO #$TODO_ID: $TODO_TITLE"
            
            # Führe TODO aus
            echo -e "${YELLOW}[WATCHER] Führe './todo -id $TODO_ID' aus...${NC}"
            ./todo -id "$TODO_ID"
            
            # Warte kurz
            sleep 5
        else
            echo -e "${BLUE}[WATCHER] Keine offenen TODOs gefunden (Count: $NEW_TODOS)${NC}"
            log_message "Keine offenen TODOs gefunden"
        fi
    fi
    
    # Warte bis zum nächsten Check
    echo -e "${BLUE}[WATCHER] Warte $CHECK_INTERVAL Sekunden...${NC}"
    sleep $CHECK_INTERVAL
done