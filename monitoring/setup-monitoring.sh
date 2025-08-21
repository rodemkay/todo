#!/bin/bash

# ========================================
# WEBHOOK SYSTEM MONITORING SETUP
# ========================================
# Automatisches Setup für das komplette Monitoring-System

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TODO_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="/tmp"
ARCHIVE_DIR="/tmp/webhook_logs_archive"
SYSTEMD_DIR="/etc/systemd/system"

echo "========================================="
echo "WEBHOOK SYSTEM MONITORING SETUP"
echo "========================================="
echo "Script Directory: $SCRIPT_DIR"
echo "Todo Directory: $TODO_DIR"
echo ""

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# ========================================
# 1. SYSTEM REQUIREMENTS CHECK
# ========================================
log_info "Checking system requirements..."

# Check Python 3
if command -v python3 >/dev/null 2>&1; then
    PYTHON_VERSION=$(python3 --version | cut -d' ' -f2)
    log_success "Python 3 found: $PYTHON_VERSION"
else
    log_error "Python 3 not found. Please install Python 3.7+"
    exit 1
fi

# Check required Python modules
REQUIRED_MODULES="sqlite3 threading psutil"
for module in $REQUIRED_MODULES; do
    if python3 -c "import $module" 2>/dev/null; then
        log_success "Python module '$module' available"
    else
        log_warning "Installing Python module: $module"
        pip3 install "$module" 2>/dev/null || log_error "Failed to install $module"
    fi
done

# Check tmux
if command -v tmux >/dev/null 2>&1; then
    log_success "tmux found"
else
    log_warning "tmux not found - required for webhook execution"
fi

# Check disk space
AVAILABLE_SPACE=$(df /tmp | tail -1 | awk '{print $4}')
AVAILABLE_GB=$((AVAILABLE_SPACE / 1024 / 1024))
if [ $AVAILABLE_GB -lt 2 ]; then
    log_warning "Low disk space in /tmp: ${AVAILABLE_GB}GB (recommend 2GB+)"
else
    log_success "Sufficient disk space: ${AVAILABLE_GB}GB available"
fi

# ========================================
# 2. DIRECTORY SETUP
# ========================================
log_info "Setting up directories..."

# Create required directories
mkdir -p "$ARCHIVE_DIR" || log_error "Failed to create archive directory"
mkdir -p "$LOG_DIR" || log_error "Failed to create log directory"

log_success "Directories created:"
log_success "  Archive: $ARCHIVE_DIR"
log_success "  Logs: $LOG_DIR"

# Set permissions
chmod 755 "$ARCHIVE_DIR"
chmod 755 "$SCRIPT_DIR"

# Make all Python scripts executable
find "$SCRIPT_DIR" -name "*.py" -exec chmod +x {} \;
log_success "Made Python scripts executable"

# ========================================
# 3. CONFIGURATION SETUP
# ========================================
log_info "Creating configuration files..."

# Create monitoring config
cat > "$SCRIPT_DIR/monitoring-config.json" << EOF
{
    "monitoring": {
        "enabled": true,
        "interval_seconds": 5,
        "alert_on_error": true,
        "memory_threshold_mb": 50,
        "cpu_threshold_percent": 80.0,
        "response_time_threshold_ms": 1000.0
    },
    "queue": {
        "max_queue_size": 1000,
        "max_concurrent_workers": 3,
        "rate_limit_per_second": 10,
        "batch_size": 5,
        "processing_timeout": 30.0
    },
    "logging": {
        "max_file_size_mb": 50,
        "max_age_days": 30,
        "max_total_size_gb": 5.0,
        "compression_enabled": true,
        "archive_directory": "$ARCHIVE_DIR",
        "cleanup_interval_hours": 6,
        "log_levels_to_keep": ["ERROR", "WARNING", "CRITICAL"]
    }
}
EOF

log_success "Configuration created: $SCRIPT_DIR/monitoring-config.json"

# Create systemd service for monitoring
if [ -w "$SYSTEMD_DIR" ] || [ "$EUID" -eq 0 ]; then
    log_info "Creating systemd service..."
    
    cat > "$SYSTEMD_DIR/webhook-monitor.service" << EOF
[Unit]
Description=Webhook System Monitor
After=network.target
Wants=network-online.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$SCRIPT_DIR
ExecStart=$SCRIPT_DIR/webhook-monitor.py --config $SCRIPT_DIR/monitoring-config.json
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    log_success "Systemd service created: webhook-monitor.service"
    
    # Create log manager service
    cat > "$SYSTEMD_DIR/webhook-log-manager.service" << EOF
[Unit]
Description=Webhook Log Manager
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$SCRIPT_DIR
ExecStart=$SCRIPT_DIR/log-manager.py --start-daemon --config-file $SCRIPT_DIR/monitoring-config.json
Restart=always
RestartSec=30
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    log_success "Systemd service created: webhook-log-manager.service"
    
    # Reload systemd
    if command -v systemctl >/dev/null 2>&1; then
        systemctl daemon-reload
        log_success "Systemd services reloaded"
    fi
else
    log_warning "No systemd access - services must be started manually"
fi

# ========================================
# 4. PERFORMANCE OPTIMIZATION SETUP
# ========================================
log_info "Setting up performance optimizations..."

# Stop old watch script if running
pkill -f "watch-local-trigger.sh" 2>/dev/null || true
log_info "Stopped old watch scripts"

# Create optimized startup script
cat > "$SCRIPT_DIR/start-monitoring.sh" << 'EOF'
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
EOF

chmod +x "$SCRIPT_DIR/start-monitoring.sh"

# Create stop script
cat > "$SCRIPT_DIR/stop-monitoring.sh" << 'EOF'
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
EOF

chmod +x "$SCRIPT_DIR/stop-monitoring.sh"

log_success "Management scripts created:"
log_success "  Start: $SCRIPT_DIR/start-monitoring.sh"
log_success "  Stop: $SCRIPT_DIR/stop-monitoring.sh"

# ========================================
# 5. TESTING SETUP
# ========================================
log_info "Creating test scripts..."

# Create quick test script
cat > "$SCRIPT_DIR/test-monitoring.sh" << 'EOF'
#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "========================================="
echo "WEBHOOK MONITORING SYSTEM TEST"
echo "========================================="

# Test 1: Basic monitor functionality
echo "Test 1: Monitor functionality..."
if python3 "$SCRIPT_DIR/webhook-monitor.py" --dashboard > /tmp/monitor_test.json; then
    echo "✅ Monitor script working"
else
    echo "❌ Monitor script failed"
fi

# Test 2: Log manager
echo ""
echo "Test 2: Log manager functionality..."
if python3 "$SCRIPT_DIR/log-manager.py" --status > /tmp/logmgr_test.json; then
    echo "✅ Log manager working"
else
    echo "❌ Log manager failed"
fi

# Test 3: Queue manager
echo ""
echo "Test 3: Queue manager functionality..."
if python3 "$SCRIPT_DIR/queue-manager.py" --status > /tmp/queue_test.json; then
    echo "✅ Queue manager working"
else
    echo "❌ Queue manager failed"
fi

# Test 4: Load tester (quick test)
echo ""
echo "Test 4: Load tester (quick test)..."
if python3 "$SCRIPT_DIR/load-test.py" --test-type custom --duration 10 --users 2 --requests 5 > /tmp/loadtest_results.txt; then
    echo "✅ Load tester working"
    echo "   Results saved to /tmp/loadtest_results.txt"
else
    echo "❌ Load tester failed"
fi

echo ""
echo "Test completed. Check /tmp/*_test.json for detailed results."
EOF

chmod +x "$SCRIPT_DIR/test-monitoring.sh"

log_success "Test script created: $SCRIPT_DIR/test-monitoring.sh"

# ========================================
# 6. DASHBOARD SETUP
# ========================================
log_info "Setting up dashboard..."

# Dashboard is already created, just make sure it's accessible
if [ -f "$SCRIPT_DIR/dashboard.html" ]; then
    log_success "Dashboard available at: file://$SCRIPT_DIR/dashboard.html"
    
    # For production, you might want to serve it via web server
    log_info "To serve dashboard via HTTP, run:"
    log_info "  cd $SCRIPT_DIR && python3 -m http.server 8080"
else
    log_warning "Dashboard file not found at $SCRIPT_DIR/dashboard.html"
fi

# ========================================
# 7. VERIFICATION & SUMMARY
# ========================================
log_info "Running verification tests..."

# Test configuration file
if python3 -c "import json; json.load(open('$SCRIPT_DIR/monitoring-config.json'))" 2>/dev/null; then
    log_success "Configuration file is valid JSON"
else
    log_error "Configuration file has JSON syntax errors"
fi

# Test Python scripts
SCRIPT_COUNT=0
ERROR_COUNT=0

for script in "$SCRIPT_DIR"/*.py; do
    if [ -f "$script" ]; then
        SCRIPT_COUNT=$((SCRIPT_COUNT + 1))
        
        if python3 -m py_compile "$script" 2>/dev/null; then
            log_success "$(basename "$script") - syntax OK"
        else
            log_error "$(basename "$script") - syntax error"
            ERROR_COUNT=$((ERROR_COUNT + 1))
        fi
    fi
done

# Final summary
echo ""
echo "========================================="
echo "SETUP SUMMARY"
echo "========================================="
echo "✅ System requirements checked"
echo "✅ Directories created and configured"
echo "✅ Configuration files created"
echo "✅ Management scripts created"
echo "✅ Test scripts created"
echo "✅ $SCRIPT_COUNT Python scripts verified ($ERROR_COUNT errors)"
echo ""

if [ $ERROR_COUNT -eq 0 ]; then
    log_success "Setup completed successfully!"
    echo ""
    echo "NEXT STEPS:"
    echo "1. Start monitoring: $SCRIPT_DIR/start-monitoring.sh"
    echo "2. Run tests: $SCRIPT_DIR/test-monitoring.sh"
    echo "3. Open dashboard: file://$SCRIPT_DIR/dashboard.html"
    echo "4. Check logs in: /tmp/"
    echo ""
    echo "For systemd services (if available):"
    echo "  sudo systemctl enable webhook-monitor.service"
    echo "  sudo systemctl enable webhook-log-manager.service"
    echo "  sudo systemctl start webhook-monitor.service"
    echo "  sudo systemctl start webhook-log-manager.service"
else
    log_error "Setup completed with $ERROR_COUNT errors - check Python syntax"
    exit 1
fi

log_success "Webhook System Monitoring is ready to use!"