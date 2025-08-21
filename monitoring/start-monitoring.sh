#!/bin/bash

# Webhook System Monitoring Startup Script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="/tmp/webhook_monitoring.pid"

echo "Starting Webhook System Monitoring..."

# Check if already running
if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
    echo "Monitoring already running (PID: $(cat "$PID_FILE"))"
    exit 1
fi

# Start optimized watch script
nohup "$SCRIPT_DIR/../watch-local-trigger-optimized.sh" > /tmp/webhook_watch.out 2>&1 &
WATCH_PID=$!

# Start monitoring daemon
nohup "$SCRIPT_DIR/webhook-monitor.py" --config "$SCRIPT_DIR/monitoring-config.json" > /tmp/webhook_monitor.out 2>&1 &
MONITOR_PID=$!

# Start log manager
nohup "$SCRIPT_DIR/log-manager.py" --start-daemon --config-file "$SCRIPT_DIR/monitoring-config.json" > /tmp/webhook_logmgr.out 2>&1 &
LOGMGR_PID=$!

# Save PIDs
echo "$WATCH_PID $MONITOR_PID $LOGMGR_PID" > "$PID_FILE"

echo "Monitoring started:"
echo "  Watch Script PID: $WATCH_PID"
echo "  Monitor PID: $MONITOR_PID"
echo "  Log Manager PID: $LOGMGR_PID"

# Wait a moment and check if processes are still running
sleep 2

if kill -0 $WATCH_PID 2>/dev/null; then
    echo "✅ Watch script running"
else
    echo "❌ Watch script failed to start"
fi

if kill -0 $MONITOR_PID 2>/dev/null; then
    echo "✅ Monitor daemon running"
else
    echo "❌ Monitor daemon failed to start"
fi

if kill -0 $LOGMGR_PID 2>/dev/null; then
    echo "✅ Log manager running"
else
    echo "❌ Log manager failed to start"
fi

echo ""
echo "Dashboard available at: file://$SCRIPT_DIR/dashboard.html"
echo "Logs in: /tmp/"
echo ""
echo "Use 'stop-monitoring.sh' to stop all services"
