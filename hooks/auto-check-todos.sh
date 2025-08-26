#!/bin/bash

# Auto-Check Script für neue Todos
# Prüft alle 30 Sekunden auf neue Todos mit bearbeiten=1
# Läuft PARALLEL zum Specific-Mode!

LOG_FILE="/home/rodemkay/www/react/plugin-todo/hooks/logs/auto-check.log"
CHECK_INTERVAL=30
TMUX_SESSION="claude"
TMUX_PANE="0.0"  # Linkes Pane

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "Auto-Check Script gestartet"

while true; do
    # Prüfe ob Claude beschäftigt ist
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        # Claude arbeitet bereits an einem Todo
        log_message "Claude ist beschäftigt - überspringe Check"
    else
        # Prüfe ob neue Todos vorhanden sind (auch in_progress falls Claude abstürzte)
        NEW_TODOS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE (status=\"offen\" OR status=\"in_progress\") AND bearbeiten=1' --skip-column-names" 2>/dev/null | tr -d '\r\n' | grep -o '[0-9]*' | head -1)
        
        # Debug-Ausgabe
        log_message "SQL-Ergebnis: '$NEW_TODOS'"
        
        if [ -n "$NEW_TODOS" ] && [ "$NEW_TODOS" -gt "0" ] 2>/dev/null; then
            log_message "Gefunden: $NEW_TODOS neue Todos mit bearbeiten=1"
            
            # Sende ./todo Befehl an Claude Session
            tmux send-keys -t "${TMUX_SESSION}:${TMUX_PANE}" "./todo" C-m
            log_message "Befehl './todo' an Claude gesendet"
            
            # Warte kurz bis Todo geladen ist
            sleep 5
        else
            log_message "Keine neuen Todos gefunden (Count: $NEW_TODOS)"
        fi
    fi
    
    # Warte 30 Sekunden bis zum nächsten Check
    sleep $CHECK_INTERVAL
done