#!/bin/bash

# Auto-Check Script für neue Todos
# Prüft alle 30 Sekunden auf neue Todos mit bearbeiten=1
# Läuft PARALLEL zum Specific-Mode!

LOG_FILE="/home/rodemkay/www/react/plugin-todo/hooks/logs/auto-check.log"
CHECK_INTERVAL=30
# Hardcoded auf plugin-todo für dieses Projekt
TMUX_SESSION="plugin-todo"
TMUX_PANE="0.0"  # Linkes Pane

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_message "Auto-Check Script gestartet"

while true; do
    # Prüfe ob Claude beschäftigt ist oder bereits ein TODO gesendet wurde
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        # Claude arbeitet bereits an einem Todo
        log_message "Claude ist beschäftigt - überspringe Check"
    elif [ -f "/tmp/TODO_SENT_TO_CLAUDE" ]; then
        # TODO wurde bereits gesendet, warte auf Verarbeitung
        sent_id=$(cat /tmp/TODO_SENT_TO_CLAUDE 2>/dev/null)
        log_message "TODO #$sent_id bereits an Claude gesendet - warte auf Verarbeitung"
    else
        # KRITISCH: NUR status='offen' UND bearbeiten=1 prüfen!
        NEW_TODOS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1' --skip-column-names" 2>/dev/null | tr -d '\r\n' | grep -o '[0-9]*' | head -1)
        
        # Debug-Ausgabe
        log_message "SQL-Ergebnis: '$NEW_TODOS'"
        
        if [ -n "$NEW_TODOS" ] && [ "$NEW_TODOS" -gt "0" ] 2>/dev/null; then
            log_message "Gefunden: $NEW_TODOS neue Todos mit bearbeiten=1"
            
            # Hole die ID des ersten TODOs
            TODO_ID=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id FROM stage_project_todos WHERE status=\"offen\" AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1' --skip-column-names" 2>/dev/null | tr -d '\r\n')
            
            if [ -n "$TODO_ID" ]; then
                # Markiere TODO als gesendet
                echo "$TODO_ID" > /tmp/TODO_SENT_TO_CLAUDE
                
                # Sende ./todo Befehl an Claude Session - erst Text, dann Enter separat
                tmux send-keys -t "${TMUX_SESSION}:${TMUX_PANE}" -l "./todo"
                sleep 0.5
                tmux send-keys -t "${TMUX_SESSION}:${TMUX_PANE}" C-m
                log_message "Befehl './todo' an Claude gesendet für TODO #$TODO_ID (Text + C-m)"
                
                # Warte kurz bis Todo geladen ist
                sleep 5
            fi
        else
            log_message "Keine neuen Todos gefunden (Count: $NEW_TODOS)"
        fi
    fi
    
    # Warte 30 Sekunden bis zum nächsten Check
    sleep $CHECK_INTERVAL
done