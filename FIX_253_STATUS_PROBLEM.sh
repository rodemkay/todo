#!/bin/bash

# Fix für Task #253: Status nur setzen wenn Claude wirklich verfügbar ist

cat << 'EOF'
PROBLEM:
========
Der Status wird auf "in_progress" gesetzt BEVOR geprüft wird, ob Claude verfügbar ist.
Wenn Claude busy ist ("Previous query still processing"), bleibt der Task stuck.

LÖSUNG:
=======
1. Erst prüfen ob Claude verfügbar ist
2. Nur wenn Claude frei ist, Status auf "in_progress" setzen
3. Lock-File verwenden um Race Conditions zu vermeiden

IMPLEMENTIERUNG:
================
EOF

# Backup erstellen
cp /home/rodemkay/www/react/plugin-todo/cli/todo /home/rodemkay/www/react/plugin-todo/cli/todo.backup-253

# Neue Funktion für Claude-Verfügbarkeitsprüfung
cat << 'NEWCODE' > /tmp/claude_check.sh
# Function to check if Claude is available
check_claude_available() {
    # Check for lock file
    local lock_file="/tmp/claude_processing.lock"
    
    if [ -f "$lock_file" ]; then
        # Check if lock is stale (older than 5 minutes)
        local lock_age=$(( $(date +%s) - $(stat -c %Y "$lock_file" 2>/dev/null || echo 0) ))
        if [ $lock_age -gt 300 ]; then
            echo "Stale lock detected, removing..." >&2
            rm -f "$lock_file"
            return 0
        else
            echo "● Previous query still processing. Please try again." >&2
            return 1
        fi
    fi
    
    # Create lock file
    touch "$lock_file"
    return 0
}

# Function to release Claude lock
release_claude_lock() {
    rm -f "/tmp/claude_processing.lock"
}
NEWCODE

echo "
NEUE TODO FUNKTION:
==================="

cat << 'IMPROVED' > /tmp/improved_todo.sh
# Function to get next todo (IMPROVED)
get_next_todo() {
    # First, just fetch the todo WITHOUT changing status
    local result=$(remote_wp db query "SELECT * FROM ${DB_PREFIX}project_todos WHERE status='offen' AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1" --format=json 2>/dev/null)
    
    # Check if result is empty
    if [ -z "$result" ] || [ "$result" = "[]" ]; then
        echo -e "${YELLOW}No open todos found with bearbeiten=1.${NC}"
        return 1
    fi
    
    local todo_id=$(echo "$result" | jq -r '.[0].id')
    
    # Check if Claude is available BEFORE changing status
    if ! check_claude_available; then
        echo -e "${RED}Claude is busy. Todo #$todo_id remains in 'offen' status.${NC}"
        return 1
    fi
    
    # NOW we can safely update the status
    remote_wp db query "UPDATE ${DB_PREFIX}project_todos SET status='in_progress', execution_started_at=NOW() WHERE id=$todo_id AND status='offen'"
    
    # Store current todo ID
    echo "$todo_id" > /tmp/CURRENT_TODO_ID
    
    # Display the todo
    display_todo_v3 "$result"
    
    echo -e "[INFO] Todo #$todo_id status set to in_progress"
    echo -e "${GREEN}✅ Todo successfully loaded and status changed to: in_progress${NC}"
}

# Function to get todo by ID (IMPROVED)
get_todo_by_id() {
    local todo_id=$1
    
    # First, just fetch the todo WITHOUT changing status
    local result=$(remote_wp db query "SELECT * FROM ${DB_PREFIX}project_todos WHERE id=$todo_id" --format=json 2>/dev/null)
    
    # Check if result is empty
    if [ -z "$result" ] || [ "$result" = "[]" ]; then
        echo -e "${RED}Todo #$todo_id not found.${NC}"
        return 1
    fi
    
    local current_status=$(echo "$result" | jq -r '.[0].status')
    
    # Only update if status is 'offen' AND Claude is available
    if [ "$current_status" = "offen" ]; then
        if ! check_claude_available; then
            echo -e "${RED}Claude is busy. Todo #$todo_id remains in 'offen' status.${NC}"
            return 1
        fi
        
        # NOW we can safely update the status
        remote_wp db query "UPDATE ${DB_PREFIX}project_todos SET status='in_progress', execution_started_at=NOW() WHERE id=$todo_id AND status='offen'"
    fi
    
    # Store current todo ID
    echo "$todo_id" > /tmp/CURRENT_TODO_ID
    
    # Display the todo
    display_todo_v3 "$result"
    
    if [ "$current_status" = "offen" ]; then
        echo -e "[INFO] Todo #$todo_id status set to in_progress"
    fi
    echo -e "${GREEN}✅ Todo successfully loaded${NC}"
}
IMPROVED

echo "
HOOK INTEGRATION:
================="

cat << 'HOOKCODE' > /tmp/hook_integration.sh
# Add to TASK_COMPLETED handler
# When task is completed, release the lock
trap 'release_claude_lock' EXIT

# In the completion function
complete_current_todo() {
    # ... existing code ...
    
    # Release lock when done
    release_claude_lock
    
    # ... rest of code ...
}
HOOKCODE

echo "
ZUSAMMENFASSUNG:
================"

cat << 'SUMMARY'
Die Lösung behebt das Problem durch:

1. **Zweistufiger Prozess:**
   - Erst Todo laden (ohne Status-Änderung)
   - Claude-Verfügbarkeit prüfen
   - NUR wenn Claude frei ist: Status ändern

2. **Lock-File Mechanismus:**
   - /tmp/claude_processing.lock verhindert parallele Aufrufe
   - Automatische Bereinigung nach 5 Minuten (stale locks)
   - Wird bei TASK_COMPLETED freigegeben

3. **Vorteile:**
   - Keine stuck Tasks mehr
   - Klare Fehlermeldung wenn Claude busy
   - Task bleibt in "offen" wenn nicht verarbeitet
   - Kann später erneut aufgerufen werden

4. **Backward Compatible:**
   - Bestehende Funktionalität bleibt erhalten
   - Nur Timing der Status-Änderung wird verbessert
SUMMARY
EOF