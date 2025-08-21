#!/bin/bash

# Local Trigger Watch Script
# Überwacht /tmp/claude_todo_trigger.txt und führt Commands in der claude tmux Session aus

TRIGGER_FILE="/tmp/claude_todo_trigger.txt"
LOG_FILE="/tmp/claude_local_trigger.log"
TMUX_SESSION="claude"

echo "$(date '+%Y-%m-%d %H:%M:%S') - Local Watch-Script gestartet" >> "$LOG_FILE"
echo "Überwache: $TRIGGER_FILE"

while true; do
    if [ -f "$TRIGGER_FILE" ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Trigger-Datei gefunden" >> "$LOG_FILE"
        
        # Lese Command
        COMMAND=$(cat "$TRIGGER_FILE")
        
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Command: $COMMAND" >> "$LOG_FILE"
        
        # Führe Command in tmux Session aus
        if tmux has-session -t "$TMUX_SESSION" 2>/dev/null; then
            # Sende Command an die Session (geht automatisch ins linke Pane)
            tmux send-keys -t "$TMUX_SESSION:0" "$COMMAND"
            tmux send-keys -t "$TMUX_SESSION:0" Enter
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Command ausgeführt in tmux: $COMMAND" >> "$LOG_FILE"
        else
            echo "$(date '+%Y-%m-%d %H:%M:%S') - tmux Session '$TMUX_SESSION' nicht gefunden" >> "$LOG_FILE"
        fi
        
        # Lösche Trigger-Datei
        rm -f "$TRIGGER_FILE"
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Trigger-Datei gelöscht" >> "$LOG_FILE"
    fi
    
    # Warte 1 Sekunde
    sleep 1
done