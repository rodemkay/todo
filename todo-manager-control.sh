#!/bin/bash

# TODO-MANAGER CONTROL SCRIPT
# Steuert den intelligent_todo_monitor_fixed.sh Prozess

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/intelligent_todo_monitor_fixed.sh"
PID_FILE="/tmp/todo_manager.pid"
LOG_FILE="/tmp/todo_manager.log"
STATUS_FILE="/tmp/todo_manager_status.json"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Write status to JSON file
write_status() {
    local status=$1
    local pid=$2
    local message=$3
    cat > "$STATUS_FILE" <<EOF
{
    "status": "$status",
    "pid": $pid,
    "message": "$message",
    "timestamp": $(date +%s),
    "time": "$(date '+%Y-%m-%d %H:%M:%S')"
}
EOF
    chmod 666 "$STATUS_FILE" 2>/dev/null
}

# Get PID of running monitor
get_pid() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            echo "$pid"
            return 0
        else
            # PID file exists but process is dead
            rm -f "$PID_FILE"
        fi
    fi
    
    # Fallback: Find by process name
    local pid=$(pgrep -f "intelligent_todo_monitor_fixed.sh" | head -1)
    if [ -n "$pid" ]; then
        echo "$pid"
        return 0
    fi
    
    return 1
}

# Start the monitor
start_monitor() {
    local pid=$(get_pid)
    if [ -n "$pid" ]; then
        echo -e "${YELLOW}⚠️  Todo-Manager läuft bereits (PID: $pid)${NC}"
        write_status "running" "$pid" "Already running"
        return 1
    fi
    
    echo -e "${GREEN}▶️  Starte Todo-Manager...${NC}"
    
    # Start monitor in background
    nohup "$MONITOR_SCRIPT" start > "$LOG_FILE" 2>&1 &
    local new_pid=$!
    
    # Save PID
    echo "$new_pid" > "$PID_FILE"
    
    # Wait a moment to check if it started successfully
    sleep 2
    
    if ps -p "$new_pid" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Todo-Manager gestartet (PID: $new_pid)${NC}"
        write_status "running" "$new_pid" "Started successfully"
        return 0
    else
        echo -e "${RED}❌ Fehler beim Starten des Todo-Managers${NC}"
        rm -f "$PID_FILE"
        write_status "stopped" 0 "Failed to start"
        return 1
    fi
}

# Stop the monitor
stop_monitor() {
    local pid=$(get_pid)
    if [ -z "$pid" ]; then
        echo -e "${YELLOW}⚠️  Todo-Manager läuft nicht${NC}"
        write_status "stopped" 0 "Not running"
        return 1
    fi
    
    echo -e "${RED}⏸️  Stoppe Todo-Manager (PID: $pid)...${NC}"
    
    # Send SIGTERM for graceful shutdown
    kill -TERM "$pid" 2>/dev/null
    
    # Wait up to 10 seconds for process to end
    local count=0
    while [ $count -lt 10 ]; do
        if ! ps -p "$pid" > /dev/null 2>&1; then
            break
        fi
        sleep 1
        count=$((count + 1))
    done
    
    # Force kill if still running
    if ps -p "$pid" > /dev/null 2>&1; then
        echo "Force killing..."
        kill -9 "$pid" 2>/dev/null
        sleep 1
    fi
    
    # Clean up
    rm -f "$PID_FILE"
    rm -f "/tmp/CURRENT_TODO_ID"
    
    echo -e "${GREEN}✅ Todo-Manager gestoppt${NC}"
    write_status "stopped" 0 "Stopped"
    return 0
}

# Restart the monitor
restart_monitor() {
    echo -e "${YELLOW}🔄 Neustart Todo-Manager...${NC}"
    stop_monitor
    sleep 2
    start_monitor
}

# Get status
get_status() {
    local pid=$(get_pid)
    if [ -n "$pid" ]; then
        echo -e "${GREEN}● Todo-Manager läuft (PID: $pid)${NC}"
        
        # Check if currently processing a todo
        if [ -f "/tmp/CURRENT_TODO_ID" ]; then
            local todo_id=$(cat "/tmp/CURRENT_TODO_ID")
            echo "   Bearbeitet gerade: Todo #$todo_id"
        fi
        
        # Show last activity
        if [ -f "$LOG_FILE" ]; then
            echo "   Letzte Aktivität: $(tail -1 "$LOG_FILE" 2>/dev/null | cut -d']' -f1 | cut -d'[' -f2)"
        fi
        
        write_status "running" "$pid" "Running"
        return 0
    else
        echo -e "${RED}○ Todo-Manager gestoppt${NC}"
        write_status "stopped" 0 "Stopped"
        return 1
    fi
}

# Main command handling
case "${1:-status}" in
    start)
        start_monitor
        ;;
    stop)
        stop_monitor
        ;;
    restart)
        restart_monitor
        ;;
    status)
        get_status
        ;;
    check)
        # For cron: Start if not running
        if ! get_status > /dev/null 2>&1; then
            start_monitor
        fi
        ;;
    json)
        # Return status as JSON (for AJAX)
        if [ -f "$STATUS_FILE" ]; then
            cat "$STATUS_FILE"
        else
            echo '{"status":"unknown","pid":0,"message":"No status available"}'
        fi
        ;;
    help|--help|-h)
        echo "Todo-Manager Control Script"
        echo ""
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  start    - Start the todo manager"
        echo "  stop     - Stop the todo manager"
        echo "  restart  - Restart the todo manager"
        echo "  status   - Show current status"
        echo "  check    - Start if not running (for cron)"
        echo "  json     - Return status as JSON"
        echo "  help     - Show this help"
        ;;
    *)
        echo "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac