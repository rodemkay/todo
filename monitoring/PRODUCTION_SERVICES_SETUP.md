# 🚀 PRODUCTION SERVICES SETUP
**Complete systemd Service Configuration for Production Deployment**

## 📋 OVERVIEW

This document provides complete production-ready service configurations using systemd for automatic startup, health monitoring, and service management. All services are configured with proper dependencies, restart policies, and security restrictions.

## 🏗️ SERVICE ARCHITECTURE

```
Production Services Hierarchy:
├── webhook-socket-server.service (Primary Communication)
├── webhook-monitor.service (Health Monitoring)  
├── webhook-queue-manager.service (Task Processing)
├── webhook-backup.service (Automated Backups)
├── webhook-log-manager.service (Log Processing)
└── webhook-security-monitor.service (Security Monitoring)
```

## 🔧 SYSTEMD SERVICE CONFIGURATIONS

### 1. Socket Server Service

```ini
# /etc/systemd/system/webhook-socket-server.service
[Unit]
Description=Webhook Socket Server for Claude CLI Communication
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/FINAL_SYSTEM_ARCHITECTURE.md
After=network-online.target
Wants=network-online.target
Requires=tailscaled.service

[Service]
Type=simple
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring
ExecStart=/usr/bin/python3 -u /home/rodemkay/www/react/plugin-todo/monitoring/socket_server.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=on-failure
RestartSec=5
StartLimitInterval=60s
StartLimitBurst=3

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/www/react/plugin-todo/monitoring /tmp
PrivateTmp=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true

# Environment
Environment=PYTHONPATH=/home/rodemkay/www/react/plugin-todo/monitoring
Environment=SOCKET_PORT=8899
Environment=SOCKET_HOST=100.89.207.122
EnvironmentFile=-/home/rodemkay/.env

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-socket

# Process limits
LimitNOFILE=4096
LimitNPROC=1024

[Install]
WantedBy=multi-user.target
```

### 2. Health Monitor Service

```ini
# /etc/systemd/system/webhook-monitor.service
[Unit]
Description=Webhook System Health Monitor
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/FINAL_SYSTEM_ARCHITECTURE.md
After=network-online.target webhook-socket-server.service
Wants=network-online.target
Requires=webhook-socket-server.service

[Service]
Type=simple
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring
ExecStart=/usr/bin/python3 -u /home/rodemkay/www/react/plugin-todo/monitoring/webhook-monitor.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=always
RestartSec=10
StartLimitInterval=300s
StartLimitBurst=5

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/www/react/plugin-todo/monitoring /home/rodemkay/backups /tmp /var/log
PrivateTmp=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true

# Environment
Environment=PYTHONPATH=/home/rodemkay/www/react/plugin-todo/monitoring
Environment=MONITOR_INTERVAL=30
Environment=HEALTH_CHECK_PORT=8901
EnvironmentFile=-/home/rodemkay/.env

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-monitor

# Process limits
LimitNOFILE=2048
LimitNPROC=512

[Install]
WantedBy=multi-user.target
```

### 3. Queue Manager Service

```ini
# /etc/systemd/system/webhook-queue-manager.service
[Unit]
Description=Webhook Task Queue Manager
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/FINAL_SYSTEM_ARCHITECTURE.md
After=network-online.target webhook-socket-server.service
Wants=network-online.target
Requires=webhook-socket-server.service

[Service]
Type=simple
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring
ExecStart=/usr/bin/python3 -u /home/rodemkay/www/react/plugin-todo/monitoring/queue-manager.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=on-failure
RestartSec=15
StartLimitInterval=300s
StartLimitBurst=3

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/www/react/plugin-todo/monitoring /tmp
PrivateTmp=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true

# Environment
Environment=PYTHONPATH=/home/rodemkay/www/react/plugin-todo/monitoring
Environment=QUEUE_MAX_SIZE=1000
Environment=WORKER_THREADS=4
EnvironmentFile=-/home/rodemkay/.env

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-queue

# Process limits
LimitNOFILE=2048
LimitNPROC=1024

[Install]
WantedBy=multi-user.target
```

### 4. Backup Service

```ini
# /etc/systemd/system/webhook-backup.service
[Unit]
Description=Webhook System Automated Backup
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/BACKUP_RECOVERY_PROCEDURES.md
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring/backup-scripts
ExecStart=/home/rodemkay/www/react/plugin-todo/monitoring/backup-scripts/master-backup.sh
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-backup

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/backups /home/rodemkay/www/react/plugin-todo /tmp
PrivateTmp=true

# Environment
EnvironmentFile=-/home/rodemkay/.env

[Install]
WantedBy=multi-user.target
```

### 5. Backup Timer

```ini
# /etc/systemd/system/webhook-backup.timer
[Unit]
Description=Run Webhook Backup Daily
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/BACKUP_RECOVERY_PROCEDURES.md
Requires=webhook-backup.service

[Timer]
OnCalendar=daily
AccuracySec=1m
Persistent=true
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
```

### 6. Log Manager Service

```ini
# /etc/systemd/system/webhook-log-manager.service
[Unit]
Description=Webhook System Log Manager
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/FINAL_SYSTEM_ARCHITECTURE.md
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring
ExecStart=/usr/bin/python3 -u /home/rodemkay/www/react/plugin-todo/monitoring/log-manager.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=on-failure
RestartSec=30
StartLimitInterval=600s
StartLimitBurst=3

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/www/react/plugin-todo/monitoring /var/log /home/rodemkay/backups /tmp
PrivateTmp=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true

# Environment
Environment=PYTHONPATH=/home/rodemkay/www/react/plugin-todo/monitoring
Environment=LOG_RETENTION_DAYS=30
Environment=LOG_ROTATION_SIZE=100M
EnvironmentFile=-/home/rodemkay/.env

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-logs

# Process limits
LimitNOFILE=1024
LimitNPROC=256

[Install]
WantedBy=multi-user.target
```

### 7. Security Monitor Service

```ini
# /etc/systemd/system/webhook-security-monitor.service
[Unit]
Description=Webhook System Security Monitor
Documentation=file:///home/rodemkay/www/react/plugin-todo/monitoring/SECURITY_AUDIT_REPORT.md
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=rodemkay
Group=rodemkay
WorkingDirectory=/home/rodemkay/www/react/plugin-todo/monitoring/security-scripts
ExecStart=/home/rodemkay/www/react/plugin-todo/monitoring/security-scripts/security-monitor.sh
Restart=always
RestartSec=300
StartLimitInterval=1800s
StartLimitBurst=3

# Security restrictions
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/home/rodemkay/backups /tmp
PrivateTmp=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true

# Environment
EnvironmentFile=-/home/rodemkay/.env
Environment=SECURITY_CHECK_INTERVAL=300

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=webhook-security

[Install]
WantedBy=multi-user.target
```

## 🔧 SERVICE MANAGEMENT SCRIPTS

### Master Service Control Script

```bash
#!/bin/bash
# /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/webhook-services.sh

# Webhook Services Management Script
SERVICES=(
    "webhook-socket-server"
    "webhook-monitor"
    "webhook-queue-manager"
    "webhook-log-manager"
    "webhook-security-monitor"
)

TIMER_SERVICES=(
    "webhook-backup"
)

usage() {
    echo "Usage: $0 {start|stop|restart|status|enable|disable|logs}"
    echo ""
    echo "Commands:"
    echo "  start     - Start all webhook services"
    echo "  stop      - Stop all webhook services"
    echo "  restart   - Restart all webhook services"
    echo "  status    - Show status of all services"
    echo "  enable    - Enable all services for auto-start"
    echo "  disable   - Disable auto-start for all services"
    echo "  logs      - Show logs for all services"
    exit 1
}

start_services() {
    echo "🚀 Starting webhook services..."
    
    for service in "${SERVICES[@]}"; do
        echo "Starting $service..."
        sudo systemctl start "$service"
        if systemctl is-active --quiet "$service"; then
            echo "✅ $service started successfully"
        else
            echo "❌ $service failed to start"
            systemctl status "$service" --no-pager -l
        fi
    done
    
    # Start timer services
    for timer in "${TIMER_SERVICES[@]}"; do
        echo "Starting ${timer}.timer..."
        sudo systemctl start "${timer}.timer"
        if systemctl is-active --quiet "${timer}.timer"; then
            echo "✅ ${timer}.timer started successfully"
        else
            echo "❌ ${timer}.timer failed to start"
        fi
    done
    
    echo "✅ All services started"
}

stop_services() {
    echo "🛑 Stopping webhook services..."
    
    # Stop timer services first
    for timer in "${TIMER_SERVICES[@]}"; do
        echo "Stopping ${timer}.timer..."
        sudo systemctl stop "${timer}.timer"
        echo "✅ ${timer}.timer stopped"
    done
    
    # Stop services in reverse order
    for ((i=${#SERVICES[@]}-1; i>=0; i--)); do
        service="${SERVICES[i]}"
        echo "Stopping $service..."
        sudo systemctl stop "$service"
        echo "✅ $service stopped"
    done
    
    echo "✅ All services stopped"
}

restart_services() {
    echo "🔄 Restarting webhook services..."
    stop_services
    sleep 3
    start_services
}

status_services() {
    echo "📊 Webhook Services Status:"
    echo "================================"
    
    for service in "${SERVICES[@]}"; do
        echo ""
        echo "🔍 $service:"
        if systemctl is-active --quiet "$service"; then
            echo "  Status: ✅ Active"
        else
            echo "  Status: ❌ Inactive"
        fi
        
        if systemctl is-enabled --quiet "$service"; then
            echo "  Auto-start: ✅ Enabled"
        else
            echo "  Auto-start: ❌ Disabled"
        fi
        
        # Show key metrics
        if systemctl is-active --quiet "$service"; then
            UPTIME=$(systemctl show "$service" --property=ActiveEnterTimestamp --value | xargs -I {} date -d {} +'%Y-%m-%d %H:%M:%S')
            echo "  Started: $UPTIME"
            
            MEMORY=$(systemctl show "$service" --property=MemoryCurrent --value)
            if [[ "$MEMORY" != "[not set]" && "$MEMORY" -gt 0 ]]; then
                MEMORY_MB=$((MEMORY / 1024 / 1024))
                echo "  Memory: ${MEMORY_MB}MB"
            fi
        fi
    done
    
    echo ""
    echo "🕐 Timer Services:"
    for timer in "${TIMER_SERVICES[@]}"; do
        echo ""
        echo "🔍 ${timer}.timer:"
        if systemctl is-active --quiet "${timer}.timer"; then
            echo "  Status: ✅ Active"
            NEXT_RUN=$(systemctl list-timers "${timer}.timer" --no-pager | grep "${timer}.timer" | awk '{print $1, $2}')
            echo "  Next run: $NEXT_RUN"
        else
            echo "  Status: ❌ Inactive"
        fi
    done
}

enable_services() {
    echo "🔄 Enabling webhook services for auto-start..."
    
    for service in "${SERVICES[@]}"; do
        echo "Enabling $service..."
        sudo systemctl enable "$service"
        echo "✅ $service enabled"
    done
    
    for timer in "${TIMER_SERVICES[@]}"; do
        echo "Enabling ${timer}.timer..."
        sudo systemctl enable "${timer}.timer"
        echo "✅ ${timer}.timer enabled"
    done
    
    echo "✅ All services enabled for auto-start"
}

disable_services() {
    echo "🔄 Disabling webhook services auto-start..."
    
    for service in "${SERVICES[@]}"; do
        echo "Disabling $service..."
        sudo systemctl disable "$service"
        echo "✅ $service disabled"
    done
    
    for timer in "${TIMER_SERVICES[@]}"; do
        echo "Disabling ${timer}.timer..."
        sudo systemctl disable "${timer}.timer"
        echo "✅ ${timer}.timer disabled"
    done
    
    echo "✅ All services disabled from auto-start"
}

show_logs() {
    echo "📄 Webhook Services Logs:"
    echo "========================="
    
    for service in "${SERVICES[@]}"; do
        echo ""
        echo "🔍 Recent logs for $service:"
        echo "----------------------------"
        sudo journalctl -u "$service" --no-pager -n 10 --since "1 hour ago"
    done
    
    echo ""
    echo "🔍 System resource usage:"
    echo "-------------------------"
    echo "Memory usage:"
    free -h
    echo ""
    echo "Disk usage:"
    df -h /home/rodemkay/backups /home/rodemkay/www/react/plugin-todo
}

# Main script logic
case "$1" in
    start)
        start_services
        ;;
    stop)
        stop_services
        ;;
    restart)
        restart_services
        ;;
    status)
        status_services
        ;;
    enable)
        enable_services
        ;;
    disable)
        disable_services
        ;;
    logs)
        show_logs
        ;;
    *)
        usage
        ;;
esac
```

### Installation Script

```bash
#!/bin/bash
# /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/install-services.sh

# Webhook Services Installation Script
echo "🔧 Installing Webhook System Services..."

# Check if running as root or with sudo
if [[ $EUID -eq 0 ]]; then
    echo "❌ Please run this script as the rodemkay user, not as root"
    echo "   The script will use sudo when needed"
    exit 1
fi

# Create necessary directories
echo "📁 Creating service directories..."
mkdir -p /home/rodemkay/www/react/plugin-todo/monitoring/{service-scripts,security-scripts,backup-scripts,recovery-scripts}
mkdir -p /home/rodemkay/backups/{database,plugin,system-config,monitoring,hourly}
mkdir -p /home/rodemkay/backups/file-hashes

# Set proper permissions
echo "🔒 Setting permissions..."
chmod 755 /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/*
chmod 755 /home/rodemkay/www/react/plugin-todo/monitoring/security-scripts/*
chmod 755 /home/rodemkay/www/react/plugin-todo/monitoring/backup-scripts/*
chmod 755 /home/rodemkay/www/react/plugin-todo/monitoring/recovery-scripts/*
chmod 700 /home/rodemkay/backups

# Copy service files to systemd directory
echo "📋 Installing systemd service files..."
SERVICE_FILES=(
    "webhook-socket-server.service"
    "webhook-monitor.service"
    "webhook-queue-manager.service"
    "webhook-backup.service"
    "webhook-backup.timer"
    "webhook-log-manager.service"
    "webhook-security-monitor.service"
)

SERVICE_DIR="/home/rodemkay/www/react/plugin-todo/monitoring/service-configs"
mkdir -p "$SERVICE_DIR"

for service_file in "${SERVICE_FILES[@]}"; do
    if [[ -f "$SERVICE_DIR/$service_file" ]]; then
        echo "Installing $service_file..."
        sudo cp "$SERVICE_DIR/$service_file" "/etc/systemd/system/"
        sudo chmod 644 "/etc/systemd/system/$service_file"
        echo "✅ $service_file installed"
    else
        echo "⚠️ Warning: $service_file not found in $SERVICE_DIR"
    fi
done

# Reload systemd daemon
echo "🔄 Reloading systemd daemon..."
sudo systemctl daemon-reload

# Verify installations
echo "🔍 Verifying service installations..."
SERVICES=(
    "webhook-socket-server"
    "webhook-monitor"
    "webhook-queue-manager"
    "webhook-backup"
    "webhook-log-manager" 
    "webhook-security-monitor"
)

ALL_SERVICES_OK=true
for service in "${SERVICES[@]}"; do
    if systemctl list-unit-files | grep -q "$service.service"; then
        echo "✅ $service.service installed successfully"
    else
        echo "❌ $service.service installation failed"
        ALL_SERVICES_OK=false
    fi
done

# Check timer
if systemctl list-unit-files | grep -q "webhook-backup.timer"; then
    echo "✅ webhook-backup.timer installed successfully"
else
    echo "❌ webhook-backup.timer installation failed"
    ALL_SERVICES_OK=false
fi

# Install Python dependencies
echo "🐍 Installing Python dependencies..."
pip3 install --user asyncio websockets psutil requests

# Create log directories
echo "📄 Creating log directories..."
sudo mkdir -p /var/log/webhook-system
sudo chown rodemkay:rodemkay /var/log/webhook-system
sudo chmod 755 /var/log/webhook-system

# Setup log rotation
echo "🔄 Setting up log rotation..."
sudo tee /etc/logrotate.d/webhook-system << 'EOF'
/var/log/webhook-system/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 rodemkay rodemkay
    postrotate
        systemctl reload webhook-log-manager
    endscript
}
EOF

# Final status check
if [[ "$ALL_SERVICES_OK" == "true" ]]; then
    echo ""
    echo "✅ Installation completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Enable services: ./webhook-services.sh enable"
    echo "2. Start services: ./webhook-services.sh start"
    echo "3. Check status: ./webhook-services.sh status"
    echo ""
    echo "Management commands:"
    echo "- ./webhook-services.sh {start|stop|restart|status|enable|disable|logs}"
    echo "- sudo systemctl status webhook-socket-server"
    echo "- sudo journalctl -u webhook-monitor -f"
else
    echo ""
    echo "❌ Installation completed with errors"
    echo "Please check the error messages above and retry installation"
    exit 1
fi
```

### Health Check Script

```bash
#!/bin/bash
# /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/health-check.sh

# Comprehensive health check for all webhook services
HEALTH_LOG="/home/rodemkay/backups/health-check.log"
ALERT_WEBHOOK="http://100.89.207.122:8089/health-alert"

echo "===========================================" >> "$HEALTH_LOG"
echo "Health Check Started: $(date)" >> "$HEALTH_LOG"
echo "===========================================" >> "$HEALTH_LOG"

OVERALL_HEALTH="HEALTHY"
ISSUES_FOUND=()

# Check service status
SERVICES=(
    "webhook-socket-server"
    "webhook-monitor"
    "webhook-queue-manager"
    "webhook-log-manager"
    "webhook-security-monitor"
)

echo "🔍 Checking service status..." >> "$HEALTH_LOG"
for service in "${SERVICES[@]}"; do
    if systemctl is-active --quiet "$service"; then
        echo "✅ $service: Active" >> "$HEALTH_LOG"
    else
        echo "❌ $service: Inactive" >> "$HEALTH_LOG"
        OVERALL_HEALTH="UNHEALTHY"
        ISSUES_FOUND+=("$service inactive")
        
        # Try to restart the service
        echo "🔄 Attempting to restart $service..." >> "$HEALTH_LOG"
        if sudo systemctl restart "$service"; then
            sleep 5
            if systemctl is-active --quiet "$service"; then
                echo "✅ $service: Restarted successfully" >> "$HEALTH_LOG"
            else
                echo "❌ $service: Restart failed" >> "$HEALTH_LOG"
            fi
        fi
    fi
done

# Check timer services
echo "🕐 Checking timer services..." >> "$HEALTH_LOG"
if systemctl is-active --quiet "webhook-backup.timer"; then
    echo "✅ webhook-backup.timer: Active" >> "$HEALTH_LOG"
else
    echo "❌ webhook-backup.timer: Inactive" >> "$HEALTH_LOG"
    OVERALL_HEALTH="DEGRADED"
    ISSUES_FOUND+=("backup timer inactive")
fi

# Check network connectivity
echo "🌐 Checking network connectivity..." >> "$HEALTH_LOG"

# Check Hetzner connectivity
if ping -c 1 159.69.157.54 >/dev/null 2>&1; then
    echo "✅ Hetzner server: Reachable" >> "$HEALTH_LOG"
else
    echo "❌ Hetzner server: Unreachable" >> "$HEALTH_LOG"
    OVERALL_HEALTH="UNHEALTHY"
    ISSUES_FOUND+=("hetzner unreachable")
fi

# Check Tailscale connectivity
if ping -c 1 100.67.210.46 >/dev/null 2>&1; then
    echo "✅ Tailscale network: Connected" >> "$HEALTH_LOG"
else
    echo "❌ Tailscale network: Disconnected" >> "$HEALTH_LOG"
    OVERALL_HEALTH="UNHEALTHY"
    ISSUES_FOUND+=("tailscale disconnected")
fi

# Check socket server connectivity
if nc -z 100.89.207.122 8899 >/dev/null 2>&1; then
    echo "✅ Socket server: Listening on port 8899" >> "$HEALTH_LOG"
else
    echo "❌ Socket server: Not listening on port 8899" >> "$HEALTH_LOG"
    if [[ "$OVERALL_HEALTH" == "HEALTHY" ]]; then
        OVERALL_HEALTH="DEGRADED"
    fi
    ISSUES_FOUND+=("socket server not listening")
fi

# Check disk space
echo "💾 Checking disk space..." >> "$HEALTH_LOG"
DISK_USAGE=$(df /home/rodemkay/backups | awk 'NR==2 {print $5}' | sed 's/%//')
if [[ $DISK_USAGE -gt 90 ]]; then
    echo "❌ Disk usage critical: ${DISK_USAGE}%" >> "$HEALTH_LOG"
    OVERALL_HEALTH="UNHEALTHY"
    ISSUES_FOUND+=("disk space critical")
elif [[ $DISK_USAGE -gt 80 ]]; then
    echo "⚠️ Disk usage high: ${DISK_USAGE}%" >> "$HEALTH_LOG"
    if [[ "$OVERALL_HEALTH" == "HEALTHY" ]]; then
        OVERALL_HEALTH="DEGRADED"
    fi
    ISSUES_FOUND+=("disk space high")
else
    echo "✅ Disk usage normal: ${DISK_USAGE}%" >> "$HEALTH_LOG"
fi

# Check memory usage
echo "🧠 Checking memory usage..." >> "$HEALTH_LOG"
MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [[ $MEMORY_USAGE -gt 90 ]]; then
    echo "❌ Memory usage critical: ${MEMORY_USAGE}%" >> "$HEALTH_LOG"
    OVERALL_HEALTH="UNHEALTHY"
    ISSUES_FOUND+=("memory usage critical")
elif [[ $MEMORY_USAGE -gt 80 ]]; then
    echo "⚠️ Memory usage high: ${MEMORY_USAGE}%" >> "$HEALTH_LOG"
    if [[ "$OVERALL_HEALTH" == "HEALTHY" ]]; then
        OVERALL_HEALTH="DEGRADED"
    fi
    ISSUES_FOUND+=("memory usage high")
else
    echo "✅ Memory usage normal: ${MEMORY_USAGE}%" >> "$HEALTH_LOG"
fi

# Check backup integrity
echo "💾 Checking recent backups..." >> "$HEALTH_LOG"
LATEST_BACKUP=$(find /home/rodemkay/backups/database -name "db_backup_*.sql.gz" -mtime -1 | wc -l)
if [[ $LATEST_BACKUP -eq 0 ]]; then
    echo "❌ No recent database backup found" >> "$HEALTH_LOG"
    if [[ "$OVERALL_HEALTH" == "HEALTHY" ]]; then
        OVERALL_HEALTH="DEGRADED"
    fi
    ISSUES_FOUND+=("no recent backup")
else
    echo "✅ Recent backup found" >> "$HEALTH_LOG"
fi

# Check log file sizes
echo "📄 Checking log file sizes..." >> "$HEALTH_LOG"
LARGE_LOGS=$(find /var/log /home/rodemkay/www/react/plugin-todo/monitoring -name "*.log" -size +100M 2>/dev/null | wc -l)
if [[ $LARGE_LOGS -gt 0 ]]; then
    echo "⚠️ Found $LARGE_LOGS large log files (>100MB)" >> "$HEALTH_LOG"
    if [[ "$OVERALL_HEALTH" == "HEALTHY" ]]; then
        OVERALL_HEALTH="DEGRADED"
    fi
    ISSUES_FOUND+=("large log files")
else
    echo "✅ Log file sizes normal" >> "$HEALTH_LOG"
fi

# Generate health summary
echo "===========================================" >> "$HEALTH_LOG"
echo "Health Check Summary:" >> "$HEALTH_LOG"
echo "Overall Status: $OVERALL_HEALTH" >> "$HEALTH_LOG"
echo "Issues Found: ${#ISSUES_FOUND[@]}" >> "$HEALTH_LOG"

if [[ ${#ISSUES_FOUND[@]} -gt 0 ]]; then
    echo "Issue Details:" >> "$HEALTH_LOG"
    for issue in "${ISSUES_FOUND[@]}"; do
        echo "  - $issue" >> "$HEALTH_LOG"
    done
    
    # Send alert
    curl -s -X POST "$ALERT_WEBHOOK" \
        -H "Content-Type: application/json" \
        -d "{
            \"type\": \"health_check\",
            \"status\": \"$OVERALL_HEALTH\",
            \"issues\": $(printf '"%s",' "${ISSUES_FOUND[@]}" | sed 's/,$//' | sed 's/^/[/' | sed 's/$/]/'),
            \"timestamp\": \"$(date -Iseconds)\"
        }" 2>/dev/null || echo "Warning: Could not send health alert" >> "$HEALTH_LOG"
fi

echo "Health Check Completed: $(date)" >> "$HEALTH_LOG"
echo "===========================================" >> "$HEALTH_LOG"

# Exit with appropriate code
case "$OVERALL_HEALTH" in
    "HEALTHY")
        exit 0
        ;;
    "DEGRADED")
        exit 1
        ;;
    "UNHEALTHY")
        exit 2
        ;;
esac
```

## 🚀 DEPLOYMENT PROCESS

### Step-by-Step Production Deployment

```bash
#!/bin/bash
# /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/deploy-production.sh

echo "🚀 Starting Production Deployment..."

# Step 1: Pre-deployment checks
echo "1️⃣ Running pre-deployment checks..."
if ! ./health-check.sh; then
    echo "❌ Health check failed - aborting deployment"
    exit 1
fi

# Step 2: Install services
echo "2️⃣ Installing systemd services..."
if ! ./install-services.sh; then
    echo "❌ Service installation failed"
    exit 1
fi

# Step 3: Enable services
echo "3️⃣ Enabling services for auto-start..."
./webhook-services.sh enable

# Step 4: Start services
echo "4️⃣ Starting all services..."
./webhook-services.sh start

# Step 5: Verify deployment
echo "5️⃣ Verifying deployment..."
sleep 10
./webhook-services.sh status

# Step 6: Run initial backup
echo "6️⃣ Running initial backup..."
sudo systemctl start webhook-backup.service

# Step 7: Setup monitoring
echo "7️⃣ Setting up monitoring..."
# Add health check to cron
(crontab -l 2>/dev/null; echo "*/5 * * * * /home/rodemkay/www/react/plugin-todo/monitoring/service-scripts/health-check.sh") | crontab -

echo "✅ Production deployment completed successfully!"
echo ""
echo "📊 Service Status:"
./webhook-services.sh status
echo ""
echo "📝 Next Steps:"
echo "1. Monitor logs: ./webhook-services.sh logs"
echo "2. Check health: ./health-check.sh"
echo "3. View dashboard: http://100.89.207.122:8901"
```

---

## 📊 SERVICE MONITORING

### Service Status Dashboard

The services can be monitored via:
- **systemctl status webhook-***
- **journalctl -u webhook-*** 
- **Health check script**
- **Web dashboard (port 8901)**

### Performance Metrics

Each service reports key metrics:
- **CPU Usage**
- **Memory Usage** 
- **Network I/O**
- **Task Processing Rate**
- **Error Rate**

## 🎯 CONCLUSION

Mit diesen Production Service Configurations ist das Webhook-System vollständig automatisiert und production-ready. Alle Services haben:

- ✅ Automatic startup/restart
- ✅ Health monitoring
- ✅ Security restrictions
- ✅ Resource limits
- ✅ Proper logging
- ✅ Dependency management

Das System ist bereit für den 24/7 Production-Betrieb mit minimalen manuellen Eingriffen.

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"id": "webhook-system-finalization", "content": "Finalisierung des Webhook-Systems mit robuster Architektur und vollständiger Dokumentation", "status": "completed"}, {"id": "architecture-documentation", "content": "Komplette System-Architektur dokumentieren mit Component-Diagrammen und Datenfluss", "status": "completed"}, {"id": "backup-recovery-system", "content": "Backup-Strategien und Recovery-Procedures implementieren", "status": "completed"}, {"id": "security-audit-implementation", "content": "Security-Audit durchführen und Sicherheitslücken schließen", "status": "completed"}, {"id": "production-readiness", "content": "Production-ready Setup mit Service-Scripts und Auto-Start", "status": "completed"}, {"id": "knowledge-transfer-documentation", "content": "Vollständige Admin-Dokumentation und Training-Material erstellen", "status": "in_progress"}]