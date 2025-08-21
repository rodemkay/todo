# 🔧 TROUBLESHOOTING PLAYBOOK
**Comprehensive Problem Resolution Guide for Webhook System**

## 📋 OVERVIEW

This playbook provides step-by-step troubleshooting procedures for common issues in the WordPress ↔ Claude CLI Webhook System. Each section includes symptoms, diagnosis steps, solutions, and prevention measures.

## 🚨 EMERGENCY RESPONSE MATRIX

| Severity | Response Time | Escalation | Examples |
|----------|---------------|------------|----------|
| **P0 - Critical** | < 5 minutes | Immediate | Complete system down, security breach |
| **P1 - High** | < 30 minutes | Within 1 hour | Service failures, data corruption |
| **P2 - Medium** | < 2 hours | Next business day | Performance issues, partial failures |
| **P3 - Low** | < 24 hours | Weekly review | Minor bugs, documentation updates |

## 🔍 DIAGNOSTIC TOOLS & COMMANDS

### System Health Check Tools

```bash
# Quick system overview
./service-scripts/health-check.sh

# Service status check
./service-scripts/webhook-services.sh status

# Resource monitoring
htop
iostat 1 5
netstat -tulpn | grep webhook

# Log analysis
sudo journalctl -f -u webhook-socket-server
tail -f /var/log/webhook-system/monitor.log
```

### Network Diagnostic Tools

```bash
# Connectivity tests
ping 159.69.157.54
ping 100.67.210.46
telnet 100.89.207.122 8899
nc -zv 100.89.207.122 8899

# DNS resolution
nslookup forexsignale.trade
dig forexsignale.trade

# Tailscale diagnostics
tailscale status
tailscale ping 100.67.210.46
```

### Database Diagnostic Tools

```bash
# Database connectivity
ssh rodemkay@159.69.157.54 "mysqladmin ping"
ssh rodemkay@159.69.157.54 "mysql -u ForexSignale -p -e 'SHOW PROCESSLIST;'"

# WordPress database check
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db check"
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db repair"
```

## 🚨 P0 - CRITICAL ISSUES

### ISSUE: Complete System Failure

**Symptoms:**
- All services down
- No response from web server
- Cannot SSH to servers
- Complete loss of functionality

**Immediate Response (< 5 minutes):**

```bash
# Step 1: Verify server accessibility
ping 159.69.157.54  # Hetzner server
ping 100.89.207.122  # Ryzen server

# Step 2: Try SSH access
ssh rodemkay@159.69.157.54
ssh rodemkay@100.89.207.122

# Step 3: If servers accessible, check critical services
ssh rodemkay@159.69.157.54 "sudo systemctl status mysql nginx"
systemctl status webhook-socket-server webhook-monitor

# Step 4: If servers not accessible, check Tailscale
sudo systemctl restart tailscaled
tailscale status
```

**Recovery Procedures:**

```bash
# If Ryzen server accessible:
cd /home/rodemkay/www/react/todo/monitoring
./recovery-scripts/disaster-recovery.sh latest

# If Hetzner server accessible but services down:
ssh rodemkay@159.69.157.54 "sudo systemctl start mysql nginx"
./service-scripts/webhook-services.sh start

# Verify recovery:
./service-scripts/health-check.sh
curl https://forexsignale.trade/staging/
```

**Escalation Triggers:**
- Servers completely unreachable for > 10 minutes
- Recovery procedures fail
- Evidence of security compromise

---

### ISSUE: Security Breach Detected

**Symptoms:**
- Unusual login attempts
- Modified system files
- Suspicious network traffic
- Malware detection alerts

**Immediate Response (< 5 minutes):**

```bash
# Step 1: Isolate the system
./service-scripts/webhook-services.sh stop
ssh rodemkay@159.69.157.54 "sudo systemctl stop nginx"

# Step 2: Preserve evidence
sudo journalctl -u webhook-security-monitor --since "1 hour ago" > /tmp/security-incident-$(date +%Y%m%d_%H%M%S).log
ssh rodemkay@159.69.157.54 "sudo cp /var/log/auth.log /tmp/auth-incident-$(date +%Y%m%d_%H%M%S).log"

# Step 3: Run security scan
./security-scripts/security-monitor.sh > /tmp/security-scan-$(date +%Y%m%d_%H%M%S).log

# Step 4: Change critical passwords
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp user update 1 --user_pass='$(openssl rand -base64 32)'"
```

**Investigation Procedures:**

```bash
# Check for compromised files
ssh rodemkay@159.69.157.54 "sudo find /var/www/forexsignale/staging -name '*.php' -mtime -1 -ls"

# Review access logs
ssh rodemkay@159.69.157.54 "sudo grep -E '(POST|eval|base64|exec)' /var/log/nginx/access.log | tail -20"

# Check for malicious processes
ssh rodemkay@159.69.157.54 "ps aux | grep -E '(nc|ncat|wget|curl)' | grep -v grep"

# File integrity check
ssh rodemkay@159.69.157.54 "sudo debsums -c"
```

**Recovery Steps:**

```bash
# Restore from clean backup
./recovery-scripts/restore-database.sh [CLEAN_BACKUP_TIMESTAMP]
./recovery-scripts/restore-plugin.sh [CLEAN_BACKUP_TIMESTAMP]

# Harden security
./security-scripts/enhance-security-headers.sh
ssh rodemkay@159.69.157.54 "sudo ufw --force reset && sudo ufw enable"

# Update all software
ssh rodemkay@159.69.157.54 "sudo apt update && sudo apt upgrade -y"
```

## 🔴 P1 - HIGH PRIORITY ISSUES

### ISSUE: Trigger File Communication Failure (KRITISCH - 2025-08-21 BEHOBEN)

**Symptoms:**
- WordPress Remote Control zeigt "Claude Offline"
- Befehle von WordPress erreichen Claude CLI nicht
- Trigger-Datei wird nicht erstellt oder nicht gefunden
- Watch-Script erkennt keine Trigger-Dateien

**Root Cause Analysis (BEHOBEN):**
- **WordPress AJAX Handler schrieb in falschen Pfad:** `/tmp/claude_trigger.txt`
- **Mount kann /tmp/ nicht zugreifen:** Nur `/var/www/forexsignale/staging/` gemountet
- **Result:** 100% Kommunikationsfehler zwischen WordPress und Claude CLI

**Diagnosis Steps:**

```bash
# Step 1: Check WordPress trigger file path (KRITISCHER TEST)
ssh rodemkay@159.69.157.54 "ls -la /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt"

# Step 2: Check mount accessibility from Ryzen
ls -la /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/claude_trigger.txt

# Step 3: Test AJAX endpoint path generation
curl -X POST "https://forexsignale.trade/staging/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=send_command_to_claude&command=./todo status&_wpnonce=[NONCE]" \
  -v | grep -i "trigger"

# Step 4: Monitor watch script activity
tail -f /tmp/claude_trigger.log

# Step 5: Check database column mapping
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id, title, bearbeiten FROM stage_project_todos WHERE id = 106' --format=table"
```

**Resolution Steps (IMPLEMENTIERT):**

```bash
# Solution 1: AJAX Handler Path Korrektur (BEREITS IMPLEMENTIERT)
# WordPress Plugin class-remote-control.php Zeilen 45-60:
# KORREKT: wp_upload_dir()['basedir'] . '/claude_trigger.txt'
# FALSCH: '/tmp/claude_trigger.txt'

# Solution 2: Database Column Names Korrektur (BEREITS IMPLEMENTIERT)
# Verify correct column usage in AJAX handlers
# 'claude_modus' statt 'bearbeiten', etc.

# Solution 3: Test fixed system
echo "./todo status" > /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt
sleep 5
# Should execute in Claude CLI immediately

# Solution 4: Verify hook system repair
# consistency_validator.py line 74 corrected
# TASK_COMPLETED recognition now 100% functional
```

**Post-Repair Verification:**

```bash
# Test complete workflow
curl -X POST "https://forexsignale.trade/staging/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=send_command_to_claude&command=./todo status"

# Verify file creation in correct location
ls -la /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/

# Check Claude CLI execution
tail -5 /tmp/claude_trigger.log
```

**Prevention:**
- ✅ WordPress uploads directory used (permanent mount access)
- ✅ AJAX handlers use correct path functions
- ✅ Database column names verified and mapped correctly
- ✅ Hook system TASK_COMPLETED recognition repaired

---

### ISSUE: Database Connection Failures

**Symptoms:**
- WordPress showing "Error establishing database connection"
- Backup failures
- Plugin not loading todo items
- MySQL connection timeouts

**Diagnosis Steps:**

```bash
# Step 1: Test basic connectivity
ssh rodemkay@159.69.157.54 "mysqladmin ping"

# Step 2: Check MySQL service status
ssh rodemkay@159.69.157.54 "sudo systemctl status mysql"

# Step 3: Review MySQL error logs
ssh rodemkay@159.69.157.54 "sudo tail -20 /var/log/mysql/error.log"

# Step 4: Test credentials
DB_PASS=$(grep DB_PASS /home/rodemkay/.env | cut -d'=' -f2)
ssh rodemkay@159.69.157.54 "mysql -u ForexSignale -p$DB_PASS -e 'SELECT 1;'"

# Step 5: Check database file permissions
ssh rodemkay@159.69.157.54 "sudo ls -la /var/lib/mysql/staging_forexsignale/"
```

**Resolution Steps:**

```bash
# Solution 1: Restart MySQL service
ssh rodemkay@159.69.157.54 "sudo systemctl restart mysql"
sleep 5
ssh rodemkay@159.69.157.54 "mysqladmin ping"

# Solution 2: Repair database if corrupted
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db repair"

# Solution 3: Check disk space
ssh rodemkay@159.69.157.54 "df -h /"

# Solution 4: Reset MySQL permissions
ssh rodemkay@159.69.157.54 "sudo mysql -e \"FLUSH PRIVILEGES;\""
```

**Prevention:**
- Monitor disk space (automated)
- Regular database integrity checks
- Connection pool monitoring
- Automated MySQL health checks

---

### ISSUE: Socket Server Communication Failures

**Symptoms:**
- WordPress admin shows "Claude Offline"
- Commands from WordPress not reaching Claude CLI
- Socket connection timeouts
- Service appears running but not responding

**Diagnosis Steps:**

```bash
# Step 1: Check service status
systemctl status webhook-socket-server

# Step 2: Test port connectivity
nc -zv 100.89.207.122 8899

# Step 3: Check process details
ps aux | grep socket_server.py

# Step 4: Review service logs
sudo journalctl -u webhook-socket-server -n 50

# Step 5: Test Python imports
python3 -c "import sys; sys.path.append('/home/rodemkay/www/react/todo/monitoring'); import socket_server"
```

**Resolution Steps:**

```bash
# Solution 1: Restart socket server
sudo systemctl restart webhook-socket-server
sleep 5
systemctl status webhook-socket-server

# Solution 2: Check and fix file permissions
chmod 755 /home/rodemkay/www/react/todo/monitoring/socket_server.py
chown rodemkay:rodemkay /home/rodemkay/www/react/todo/monitoring/socket_server.py

# Solution 3: Kill any hanging processes
pkill -f socket_server.py
sudo systemctl start webhook-socket-server

# Solution 4: Check Python dependencies
pip3 install --user --upgrade asyncio websockets

# Solution 5: Verify environment variables
grep -E "SOCKET_|PYTHON" /home/rodemkay/.env
```

**Alternative Communication Test:**

```bash
# Test SSH2 fallback
ssh rodemkay@100.89.207.122 "tmux list-sessions | grep claude"

# Test trigger file system
echo "./todo status" > /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt
sleep 5
rm /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt
```

---

### ISSUE: High Memory Usage / System Slowdown

**Symptoms:**
- System response time > 5 seconds
- High load average (> 4.0)
- Memory usage > 90%
- Services crashing with OOM errors

**Diagnosis Steps:**

```bash
# Step 1: Check overall system resources
free -h
top -b -n1 | head -20

# Step 2: Identify memory-intensive processes
ps aux --sort=-%mem | head -10

# Step 3: Check service-specific memory usage
systemctl show webhook-socket-server --property=MemoryCurrent
systemctl show webhook-monitor --property=MemoryCurrent
systemctl show webhook-queue-manager --property=MemoryCurrent

# Step 4: Review swap usage
swapon --show
vmstat 1 5

# Step 5: Check for memory leaks
sudo journalctl -k | grep -i "out of memory\|oom\|killed"
```

**Resolution Steps:**

```bash
# Solution 1: Restart memory-intensive services
./service-scripts/webhook-services.sh restart

# Solution 2: Clear system caches
sudo sync
echo 3 | sudo tee /proc/sys/vm/drop_caches

# Solution 3: Kill non-essential processes
pkill -f "python.*backup"  # If backup process hanging
pkill -f "python.*load-test"  # If load test running

# Solution 4: Increase swap if needed (temporary)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Solution 5: Restart problem services with limited memory
sudo systemctl edit webhook-queue-manager
# Add: [Service]
#      MemoryLimit=512M
sudo systemctl daemon-reload
sudo systemctl restart webhook-queue-manager
```

**Investigation for Root Cause:**

```bash
# Check for Python memory leaks
python3 -c "
import psutil
for p in psutil.process_iter(['pid', 'name', 'memory_percent']):
    if 'webhook' in p.info['name']:
        print(f'{p.info[\"name\"]}: {p.info[\"memory_percent\"]:.1f}%')
"

# Monitor queue sizes
ls -la /tmp/webhook_queue_* 2>/dev/null | wc -l

# Check log file sizes
du -sh /var/log/webhook-system/* | sort -hr
```

## 🟡 P2 - MEDIUM PRIORITY ISSUES

### ISSUE: Backup Failures

**Symptoms:**
- Backup service failing
- No recent backups found
- Backup files corrupted
- Insufficient disk space errors

**Diagnosis Steps:**

```bash
# Step 1: Check backup service status
systemctl status webhook-backup.service

# Step 2: Review backup logs
sudo journalctl -u webhook-backup.service -n 20

# Step 3: Check disk space
df -h /home/rodemkay/backups

# Step 4: Test backup script manually
./backup-scripts/backup-database.sh

# Step 5: Verify backup integrity
./backup-scripts/verify-backups.sh
```

**Resolution Steps:**

```bash
# Solution 1: Free up disk space
find /home/rodemkay/backups -name "*.sql.gz" -mtime +30 -delete
find /home/rodemkay/backups -name "*.tar.gz" -mtime +30 -delete

# Solution 2: Fix permissions
chmod 755 /home/rodemkay/www/react/todo/monitoring/backup-scripts/*.sh
chown -R rodemkay:rodemkay /home/rodemkay/backups

# Solution 3: Test database connectivity
ssh rodemkay@159.69.157.54 "mysqladmin ping"

# Solution 4: Run manual backup
sudo systemctl start webhook-backup.service
sudo journalctl -u webhook-backup.service -f

# Solution 5: Reset backup timer
sudo systemctl restart webhook-backup.timer
systemctl list-timers webhook-backup.timer
```

---

### ISSUE: WordPress Plugin Errors

**Symptoms:**
- Plugin not loading in WordPress admin
- PHP errors in WordPress
- Todo list not displaying
- AJAX requests failing
- Claude Toggle buttons not working
- Save functions not responding

**Diagnosis Steps:**

```bash
# Step 1: Check WordPress error logs
ssh rodemkay@159.69.157.54 "tail -20 /var/www/forexsignale/staging/wp-content/debug.log"

# Step 2: Test plugin activation
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp plugin status todo"

# Step 3: Check file permissions
ssh rodemkay@159.69.157.54 "ls -la /var/www/forexsignale/staging/wp-content/plugins/todo/"

# Step 4: Test database connectivity from WordPress
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db check"

# Step 5: Check PHP version compatibility
ssh rodemkay@159.69.157.54 "php -v"
ssh rodemkay@159.69.157.54 "php -m | grep -i mysql"

# Step 6: NEUE DEBUG-SCHRITTE für AJAX-Handler (2025-08-21)
# Test AJAX endpoint
curl -X POST "https://forexsignale.trade/staging/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=send_command_to_claude&command=./todo status&_wpnonce=[NONCE]"

# Check trigger file path (KRITISCH)
ssh rodemkay@159.69.157.54 "ls -la /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt"

# Verify database column names
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'DESCRIBE stage_project_todos' --format=table"
```

**Resolution Steps:**

```bash
# Solution 1: Reactivate plugin
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp plugin deactivate todo"
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp plugin activate todo"

# Solution 2: Fix file permissions
ssh rodemkay@159.69.157.54 "sudo chown -R www-data:www-data /var/www/forexsignale/staging/wp-content/plugins/todo/"
ssh rodemkay@159.69.157.54 "sudo find /var/www/forexsignale/staging/wp-content/plugins/todo/ -type f -exec chmod 644 {} \;"
ssh rodemkay@159.69.157.54 "sudo find /var/www/forexsignale/staging/wp-content/plugins/todo/ -type d -exec chmod 755 {} \;"

# Solution 3: Restore plugin from backup
./recovery-scripts/restore-plugin.sh latest

# Solution 4: Clear WordPress cache
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp cache flush"

# Solution 5: Enable WordPress debugging
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp config set WP_DEBUG true --type=constant"
```

---

### ISSUE: Performance Degradation

**Symptoms:**
- Slow response times (> 2 seconds)
- High CPU usage consistently
- Timeouts in web requests
- Queue processing delays

**Diagnosis Steps:**

```bash
# Step 1: Check current performance metrics
curl -w "@/dev/stdin" -o /dev/null -s "https://forexsignale.trade/staging/" <<< 'time_total: %{time_total}s\n'

# Step 2: Monitor real-time resource usage
htop
iostat 1 5

# Step 3: Check queue sizes
ls -la /tmp/webhook_queue_* 2>/dev/null | wc -l
wc -l /home/rodemkay/www/react/todo/monitoring/*.log

# Step 4: Profile database performance
ssh rodemkay@159.69.157.54 "mysql -u ForexSignale -p -e 'SHOW PROCESSLIST;'"
ssh rodemkay@159.69.157.54 "mysql -u ForexSignale -p -e 'SHOW ENGINE INNODB STATUS;'"

# Step 5: Network latency check
ping -c 5 159.69.157.54
curl -w 'time_namelookup: %{time_namelookup}s\ntime_connect: %{time_connect}s\n' -o /dev/null -s https://forexsignale.trade/staging/
```

**Optimization Steps:**

```bash
# Solution 1: Restart all services to clear memory
./service-scripts/webhook-services.sh restart

# Solution 2: Optimize database
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db optimize"

# Solution 3: Clear log files
sudo logrotate -f /etc/logrotate.d/webhook-system

# Solution 4: Reduce queue worker threads temporarily
sudo systemctl edit webhook-queue-manager
# Add: [Service]
#      Environment=WORKER_THREADS=2
sudo systemctl daemon-reload
sudo systemctl restart webhook-queue-manager

# Solution 5: Enable Nginx caching
ssh rodemkay@159.69.157.54 "sudo nginx -t && sudo systemctl reload nginx"
```

## 🟢 P3 - LOW PRIORITY ISSUES

### ISSUE: Log File Size Issues

**Symptoms:**
- Large log files consuming disk space
- Log rotation not working
- Difficulty finding relevant log entries

**Resolution Steps:**

```bash
# Compress large log files
find /var/log/webhook-system -name "*.log" -size +100M -exec gzip {} \;

# Force log rotation
sudo logrotate -f /etc/logrotate.d/webhook-system

# Set up better log retention
sudo tee -a /etc/logrotate.d/webhook-system << 'EOF'
/home/rodemkay/backups/*.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
}
EOF
```

---

### ISSUE: Monitoring Dashboard Issues

**Symptoms:**
- Dashboard not accessible
- Stale data in dashboard
- Missing metrics

**Resolution Steps:**

```bash
# Restart monitoring service
sudo systemctl restart webhook-monitor

# Check dashboard port availability
nc -zv 100.89.207.122 8901

# Update dashboard data
curl http://100.89.207.122:8901/refresh
```

## 🔄 PREVENTION STRATEGIES

### Automated Monitoring

```bash
# Set up continuous monitoring
crontab -l | grep -q health-check || (crontab -l; echo "*/5 * * * * /home/rodemkay/www/react/todo/monitoring/service-scripts/health-check.sh") | crontab -

# Disk space monitoring
crontab -l | grep -q disk-space || (crontab -l; echo "0 */6 * * * df -h /home/rodemkay/backups | awk 'NR==2{print \$5}' | sed 's/%//' | awk '{if(\$1 > 80) print \"WARNING: Disk usage at \" \$1 \"%\"}'") | crontab -
```

### Performance Baselines

Establish normal operating parameters:
- CPU: < 30% average
- Memory: < 60% usage
- Response time: < 500ms
- Queue depth: < 10 items
- Error rate: < 0.1%

### Regular Maintenance Tasks

```bash
# Weekly cleanup script
#!/bin/bash
# Clean old logs
find /var/log/webhook-system -name "*.log" -mtime +7 -delete

# Verify backups
./backup-scripts/verify-backups.sh

# Update system packages
ssh rodemkay@159.69.157.54 "sudo apt update && sudo apt list --upgradable"

# Performance report
./service-scripts/performance-report.sh
```

## 📞 ESCALATION PROCEDURES

### When to Escalate

**Immediate Escalation (P0):**
- Complete system failure > 10 minutes
- Security breach confirmed
- Data corruption detected

**1-Hour Escalation (P1):**
- Service restoration attempts fail
- Performance degradation > 50%
- Backup failures > 24 hours

**Next-Day Escalation (P2):**
- Recurring issues
- Capacity planning concerns
- Feature enhancement requests

### Escalation Contacts

1. **Primary Escalation:** System Administrator
2. **Secondary Escalation:** Development Team
3. **Emergency Escalation:** Infrastructure Team

### Information to Include

- **Issue Description:** Clear symptoms and impact
- **Timeline:** When issue started and duration
- **Steps Taken:** What troubleshooting was attempted
- **Current Status:** System state and workarounds
- **Log Excerpts:** Relevant error messages
- **Impact Assessment:** Users/services affected

---

## ✅ CONCLUSION

This troubleshooting playbook provides structured approaches to common issues. Remember:

1. **Always check logs first**
2. **Document what you try**
3. **Take backups before major changes**
4. **Escalate early rather than late**
5. **Update this playbook with new issues/solutions**

The key to effective troubleshooting is systematic diagnosis and methodical problem-solving. When in doubt, start with the health check script and work from there.

---

**Last Updated:** 2025-08-21  
**Version:** 1.0.0  
**Maintained By:** System Administration Team