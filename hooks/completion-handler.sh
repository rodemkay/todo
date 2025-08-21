#!/bin/bash

# TASK_COMPLETED Handler für das neue System
# Wird aufgerufen wenn TASK_COMPLETED geschrieben wird

TASK_FILE="/tmp/TASK_COMPLETED"
MANAGER="/home/rodemkay/www/react/todo/hooks/todo-manager.py"
LOG_FILE="/home/rodemkay/www/react/todo/hooks/logs/completion.log"

# Logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Hauptloop - überwacht TASK_COMPLETED
while true; do
    if [ -f "$TASK_FILE" ]; then
        log_message "TASK_COMPLETED detected"
        
        # Handle completion
        python3 "$MANAGER" complete
        
        # Aufräumen
        rm -f "$TASK_FILE"
        
        log_message "Completion handled"
    fi
    
    # Kurz warten
    sleep 2
done