#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="/tmp/webhook_monitoring.pid"

echo "Stopping Webhook System Monitoring..."

if [ -f "$PID_FILE" ]; then
    PIDS=$(cat "$PID_FILE")
    
    for PID in $PIDS; do
        if kill -0 $PID 2>/dev/null; then
            echo "Stopping process $PID..."
            kill -TERM $PID
            
            # Wait up to 10 seconds for graceful shutdown
            for i in {1..10}; do
                if ! kill -0 $PID 2>/dev/null; then
                    break
                fi
                sleep 1
            done
            
            # Force kill if still running
            if kill -0 $PID 2>/dev/null; then
                echo "Force killing process $PID..."
                kill -9 $PID
            fi
        fi
    done
    
    rm -f "$PID_FILE"
    echo "All monitoring processes stopped"
else
    echo "No PID file found - killing by pattern"
    
    # Kill by process patterns
    pkill -f "watch-local-trigger-optimized.sh" 2>/dev/null || true
    pkill -f "webhook-monitor.py" 2>/dev/null || true
    pkill -f "log-manager.py.*start-daemon" 2>/dev/null || true
    
    echo "Pattern-based kill completed"
fi
