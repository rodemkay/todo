#!/bin/bash

# Direct TODO Loader - Lädt TODOs direkt ohne tmux
# Läuft parallel zum normalen Claude-Betrieb

LOG_FILE="/home/rodemkay/www/react/plugin-todo/hooks/logs/direct-loader.log"
CHECK_INTERVAL=30
MANAGER="/home/rodemkay/www/react/plugin-todo/hooks/todo_manager.py"

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "Direct TODO Loader gestartet"

while true; do
    # Prüfe ob Claude beschäftigt ist
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        log_message "Claude arbeitet an TODO #$current_id - überspringe Check"
    else
        # Prüfe auf neue TODOs
        NEW_TODOS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1' --skip-column-names" 2>/dev/null | tr -d '\r\n' | grep -o '[0-9]*' | head -1)
        
        if [ -n "$NEW_TODOS" ] && [ "$NEW_TODOS" -gt "0" ] 2>/dev/null; then
            log_message "Gefunden: $NEW_TODOS neue Todos mit bearbeiten=1"
            
            # Hole die ID des ersten TODOs
            TODO_ID=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            
            if [ -n "$TODO_ID" ]; then
                log_message "Lade TODO #$TODO_ID direkt..."
                
                # Führe todo_manager.py direkt aus
                cd /home/rodemkay/www/react/plugin-todo
                python3 "$MANAGER" load-id "$TODO_ID" 2>&1 | tee -a "$LOG_FILE"
                
                log_message "TODO #$TODO_ID geladen"
            fi
        else
            log_message "Keine neuen Todos gefunden (Count: $NEW_TODOS)"
        fi
    fi
    
    # Warte bis zum nächsten Check
    sleep $CHECK_INTERVAL
done