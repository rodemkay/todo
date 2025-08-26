# 👨‍💼 WEBHOOK SYSTEM ADMIN HANDBOOK
**Complete Administration Guide for Production Environment**

## 📋 OVERVIEW

This handbook provides comprehensive guidance for administrators managing the WordPress ↔ Claude CLI Webhook System. It covers daily operations, troubleshooting, maintenance procedures, and emergency response protocols.

### 🎯 ADMIN RESPONSIBILITIES
- **System Monitoring:** Daily health checks and performance monitoring
- **Security Management:** Access control, updates, and incident response
- **Backup Management:** Verify backups and test recovery procedures
- **Performance Optimization:** Monitor and optimize system performance
- **Documentation:** Keep system documentation current and accurate

## 🚀 QUICK START FOR NEW ADMINS

### Initial System Access

```bash
# 1. Connect to Ryzen Server (Claude CLI host)
ssh rodemkay@100.89.207.122  # or Tailscale IP

# 2. Navigate to project directory
cd /home/rodemkay/www/react/plugin-todo/monitoring

# 3. Check overall system health
./service-scripts/health-check.sh

# 4. View service status
./service-scripts/webhook-services.sh status

# 5. Check recent logs
./service-scripts/webhook-services.sh logs
```

### First Day Checklist

- [ ] Verify SSH key access to both servers
- [ ] Test WordPress admin access
- [ ] Confirm all services are running
- [ ] Review recent backup status
- [ ] Check security monitoring alerts
- [ ] Familiarize with log locations
- [ ] Test emergency contacts/procedures

## 🖥️ SYSTEM ARCHITECTURE OVERVIEW

### Server Infrastructure

```
Production Environment:
┌──────────────────────────────────────────────────────────┐
│                    INFRASTRUCTURE                        │
├──────────────────────────────────────────────────────────┤
│ Hetzner Server (159.69.157.54 / 100.67.210.46)        │
│ ├─ WordPress (Staging: /var/www/forexsignale/staging/) │
│ ├─ MySQL Database (staging_forexsignale)               │
│ ├─ Nginx Web Server                                     │
│ └─ SSH Access (Key-based only)                         │
├──────────────────────────────────────────────────────────┤
│ Ryzen Server (100.89.207.122)                          │
│ ├─ Claude CLI (tmux session "claude")                  │
│ ├─ Socket Server (Port 8899)                           │
│ ├─ Health Monitor (Port 8901)                          │
│ ├─ Backup Storage (/home/rodemkay/backups/)            │
│ └─ Service Management Scripts                           │
└──────────────────────────────────────────────────────────┘

Network: Tailscale VPN (Private mesh network)
```

### Core Services

| Service | Purpose | Port | Status Monitoring |
|---------|---------|------|------------------|
| webhook-socket-server | Real-time communication | 8899 | systemctl status |
| webhook-monitor | Health monitoring | 8901 | HTTP endpoint |
| webhook-queue-manager | Task processing | Internal | Process monitoring |
| webhook-log-manager | Log management | Internal | Log file status |
| webhook-security-monitor | Security scanning | Internal | Alert system |
| webhook-backup (timer) | Automated backups | N/A | Timer status |

## 🛠️ DAILY OPERATIONS

### Morning Routine (5-10 minutes)

```bash
#!/bin/bash
# Daily morning health check routine

echo "🌅 Morning Health Check - $(date)"

# 1. Overall system status
echo "1️⃣ Checking system services..."
./service-scripts/webhook-services.sh status

# 2. Review overnight alerts
echo "2️⃣ Checking security alerts..."
tail -20 /home/rodemkay/backups/security-monitor.log

# 3. Backup verification
echo "3️⃣ Verifying recent backups..."
ls -la /home/rodemkay/backups/database/db_backup_$(date +%Y%m%d)*.sql.gz 2>/dev/null || echo "⚠️ No backup from today yet"

# 4. Disk space check
echo "4️⃣ Checking disk usage..."
df -h /home/rodemkay/backups | grep -v Filesystem

# 5. WordPress accessibility
echo "5️⃣ Testing WordPress access..."
if curl -s -f "https://forexsignale.trade/staging/" > /dev/null; then
    echo "✅ WordPress accessible"
else
    echo "❌ WordPress access failed"
fi

# 6. Claude CLI status
echo "6️⃣ Checking Claude CLI session..."
if ssh rodemkay@100.89.207.122 "tmux list-sessions | grep claude"; then
    echo "✅ Claude session active"
else
    echo "⚠️ Claude session not found"
fi

echo "✅ Morning health check completed"
```

### Evening Routine (5 minutes)

```bash
#!/bin/bash
# Daily evening wrap-up routine

echo "🌇 Evening System Review - $(date)"

# 1. Performance summary
echo "1️⃣ Daily performance summary..."
echo "Service restarts today: $(journalctl --since "00:00:00" | grep -c "systemctl.*restart.*webhook")"
echo "Failed logins today: $(ssh rodemkay@159.69.157.54 "sudo grep '$(date +%b\ %d)' /var/log/auth.log | grep -c 'authentication failure'")"

# 2. Backup status
echo "2️⃣ Today's backup status..."
if find /home/rodemkay/backups -name "*$(date +%Y%m%d)*" -type f | head -5; then
    echo "✅ Backups found for today"
else
    echo "⚠️ No backups found for today"
fi

# 3. Resource usage summary
echo "3️⃣ Resource usage summary..."
echo "Average CPU: $(uptime | awk '{print $10}' | sed 's/,//')"
echo "Memory used: $(free -h | awk 'NR==2{print $3}')"
echo "Disk used: $(df -h /home/rodemkay | awk 'NR==2{print $5}')"

# 4. Plan for tomorrow
echo "4️⃣ Upcoming maintenance..."
systemctl list-timers webhook-* --no-pager | grep -v PASSED

echo "✅ Evening review completed"
```

## 🔧 COMMON ADMINISTRATIVE TASKS

### Service Management

```bash
# Start all services
./service-scripts/webhook-services.sh start

# Stop all services (maintenance mode)
./service-scripts/webhook-services.sh stop

# Restart services (after configuration changes)
./service-scripts/webhook-services.sh restart

# Check detailed status
./service-scripts/webhook-services.sh status

# View recent logs
./service-scripts/webhook-services.sh logs

# Enable auto-start (production mode)
./service-scripts/webhook-services.sh enable

# Disable auto-start (maintenance mode)
./service-scripts/webhook-services.sh disable
```

### Backup Management

```bash
# Run immediate backup
sudo systemctl start webhook-backup.service

# Check backup status
sudo systemctl status webhook-backup.service

# List recent backups
ls -la /home/rodemkay/backups/database/ | tail -10

# Verify backup integrity
./backup-scripts/verify-backups.sh

# Restore from backup (emergency)
./recovery-scripts/restore-database.sh latest
```

### Log Management

```bash
# View real-time logs
sudo journalctl -f -u webhook-socket-server

# Search logs for errors
sudo journalctl -u webhook-monitor --since "1 hour ago" | grep ERROR

# Rotate logs manually
sudo logrotate /etc/logrotate.d/webhook-system

# Clear old logs (emergency disk space)
find /var/log/webhook-system -name "*.log" -mtime +7 -delete
```

### Security Management

```bash
# Run security scan
./security-scripts/security-monitor.sh

# Check failed logins
ssh rodemkay@159.69.157.54 "sudo grep 'authentication failure' /var/log/auth.log | tail -10"

# Review firewall status
ssh rodemkay@159.69.157.54 "sudo ufw status numbered"

# Update system packages
ssh rodemkay@159.69.157.54 "sudo apt update && sudo apt list --upgradable"
```

## 🚨 TROUBLESHOOTING GUIDE

### Service Won't Start

**Symptom:** Service fails to start or immediately stops

**Diagnosis Steps:**
```bash
# 1. Check service status
systemctl status webhook-socket-server

# 2. View detailed logs
sudo journalctl -u webhook-socket-server -n 50

# 3. Check configuration
python3 -c "import sys; sys.path.append('/home/rodemkay/www/react/plugin-todo/monitoring'); import socket_server"

# 4. Verify permissions
ls -la /home/rodemkay/www/react/plugin-todo/monitoring/socket_server.py
```

**Common Solutions:**
- Check Python module imports
- Verify file permissions (should be 755 for scripts)
- Check environment variables in .env file
- Ensure network ports are available

### High CPU Usage

**Symptom:** System running slowly, high load average

**Diagnosis Steps:**
```bash
# 1. Identify CPU-intensive processes
top -p $(pgrep -d, -f webhook)

# 2. Check service-specific CPU usage
systemctl show webhook-socket-server --property=CPUUsageNSec

# 3. Review recent activity
sudo journalctl -u webhook-queue-manager --since "1 hour ago" | grep -i "processing"
```

**Common Solutions:**
- Restart resource-intensive services
- Check for infinite loops in queue processing
- Review queue size and processing rate
- Temporarily reduce queue worker threads

### Database Connection Issues

**Symptom:** WordPress errors, backup failures, database timeouts

**Diagnosis Steps:**
```bash
# 1. Test database connectivity
ssh rodemkay@159.69.157.54 "mysqladmin ping"

# 2. Check MySQL service status
ssh rodemkay@159.69.157.54 "sudo systemctl status mysql"

# 3. Review MySQL error logs
ssh rodemkay@159.69.157.54 "sudo tail -20 /var/log/mysql/error.log"

# 4. Test WordPress database connection
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db check"
```

**Common Solutions:**
- Restart MySQL service
- Check database credentials in .env
- Verify WordPress wp-config.php settings
- Check disk space on database server

### Network Connectivity Problems

**Symptom:** Services can't communicate, timeouts, connection refused

**Diagnosis Steps:**
```bash
# 1. Test basic connectivity
ping 159.69.157.54
ping 100.67.210.46

# 2. Check Tailscale status
tailscale status

# 3. Test specific ports
nc -zv 100.89.207.122 8899
nc -zv 159.69.157.54 22

# 4. Check firewall rules
ssh rodemkay@159.69.157.54 "sudo ufw status"
```

**Common Solutions:**
- Restart Tailscale service
- Check firewall configuration
- Verify SSH key authentication
- Restart networking services

### Backup Failures

**Symptom:** Missing backups, backup errors, corrupted backup files

**Diagnosis Steps:**
```bash
# 1. Check backup service status
systemctl status webhook-backup.service

# 2. Review backup logs
sudo journalctl -u webhook-backup.service -n 20

# 3. Test manual backup
./backup-scripts/backup-database.sh

# 4. Check disk space
df -h /home/rodemkay/backups
```

**Common Solutions:**
- Free up disk space
- Check database connectivity
- Verify backup script permissions
- Test SSH connectivity to database server

## 🚨 EMERGENCY PROCEDURES

### Complete System Failure

**If all services are down:**

1. **Assess Scope:**
   ```bash
   # Check if servers are accessible
   ping 159.69.157.54
   ping 100.89.207.122
   
   # Try SSH access
   ssh rodemkay@159.69.157.54
   ssh rodemkay@100.89.207.122
   ```

2. **Emergency Recovery:**
   ```bash
   # Run disaster recovery
   cd /home/rodemkay/www/react/plugin-todo/monitoring
   ./recovery-scripts/disaster-recovery.sh latest
   ```

3. **Service Restart:**
   ```bash
   # Restart all services
   ./service-scripts/webhook-services.sh restart
   
   # Verify recovery
   ./service-scripts/health-check.sh
   ```

### Security Incident Response

**If security breach suspected:**

1. **Immediate Actions:**
   ```bash
   # Stop all external services
   ./service-scripts/webhook-services.sh stop
   
   # Change all passwords
   ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp user update admin --user_pass=NEW_SECURE_PASSWORD"
   
   # Review access logs
   ssh rodemkay@159.69.157.54 "sudo grep 'authentication failure' /var/log/auth.log | tail -50"
   ```

2. **Investigation:**
   ```bash
   # Run security scan
   ./security-scripts/security-monitor.sh
   
   # Check for malware
   ssh rodemkay@159.69.157.54 "sudo clamscan -r /var/www/forexsignale/staging"
   
   # Review file changes
   ssh rodemkay@159.69.157.54 "sudo find /var/www/forexsignale/staging -name '*.php' -mtime -1"
   ```

3. **Recovery:**
   ```bash
   # Restore from clean backup
   ./recovery-scripts/restore-database.sh [CLEAN_BACKUP_TIMESTAMP]
   ./recovery-scripts/restore-plugin.sh [CLEAN_BACKUP_TIMESTAMP]
   
   # Update security measures
   ./security-scripts/enhance-security-headers.sh
   ```

### Data Loss Recovery

**If data has been lost or corrupted:**

1. **Stop Services:**
   ```bash
   ./service-scripts/webhook-services.sh stop
   ```

2. **Assess Damage:**
   ```bash
   # Check what data is affected
   ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db check"
   
   # Verify backup integrity
   ./backup-scripts/verify-backups.sh
   ```

3. **Recovery Process:**
   ```bash
   # Restore from most recent good backup
   ./recovery-scripts/disaster-recovery.sh [BACKUP_TIMESTAMP]
   
   # Verify data integrity
   ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db repair"
   ```

## 📊 MONITORING & ALERTING

### Key Metrics to Monitor

| Metric | Normal Range | Warning Threshold | Critical Threshold | Action Required |
|--------|--------------|-------------------|-------------------|-----------------|
| CPU Usage | <30% | >50% | >80% | Investigate high CPU processes |
| Memory Usage | <60% | >75% | >90% | Restart services, check for leaks |
| Disk Usage | <70% | >80% | >90% | Clean logs, expand storage |
| Response Time | <200ms | >500ms | >1000ms | Check network, restart services |
| Error Rate | <1% | >2% | >5% | Review logs, identify root cause |
| Backup Age | <24h | >36h | >48h | Check backup service, run manual |

### Alert Escalation

**Level 1 - Informational:**
- Service restarts
- High resource usage warnings
- Backup completion notifications

**Level 2 - Warning:**
- Service failures with successful auto-recovery
- Disk space warnings
- Performance degradation

**Level 3 - Critical:**
- Multiple service failures
- Security incidents
- Data corruption
- Complete system unavailability

### Monitoring Dashboard Access

- **URL:** http://100.89.207.122:8901
- **Authentication:** SSH tunnel recommended
- **Updates:** Real-time (30-second intervals)

## 📚 REFERENCE DOCUMENTATION

### Important File Locations

```bash
# Configuration Files
/home/rodemkay/.env                    # Environment variables
/home/rodemkay/www/react/plugin-todo/CLAUDE.md   # Project instructions

# Service Configurations
/etc/systemd/system/webhook-*.service  # Service definitions
/etc/logrotate.d/webhook-system        # Log rotation config

# Application Files
/home/rodemkay/www/react/plugin-todo/monitoring/   # Main application
/var/www/forexsignale/staging/wp-content/plugins/todo/  # WordPress plugin

# Backup Locations
/home/rodemkay/backups/database/       # Database backups
/home/rodemkay/backups/plugin/         # Plugin backups
/home/rodemkay/backups/system-config/  # System configuration backups

# Log Files
/var/log/webhook-system/               # Application logs
/home/rodemkay/backups/security-monitor.log  # Security logs
/home/rodemkay/backups/health-check.log      # Health check logs
```

### Command Quick Reference

```bash
# Service Management
systemctl {start|stop|restart|status} webhook-{service-name}
./service-scripts/webhook-services.sh {start|stop|restart|status|logs}

# Health Checks
./service-scripts/health-check.sh
curl http://100.89.207.122:8901/health

# Backup & Recovery
sudo systemctl start webhook-backup.service
./recovery-scripts/disaster-recovery.sh latest

# Security
./security-scripts/security-monitor.sh
ssh rodemkay@159.69.157.54 "sudo grep 'authentication failure' /var/log/auth.log"

# Performance
top -p $(pgrep -d, -f webhook)
df -h /home/rodemkay/backups
free -h
```

### Contact Information

**System Administrator:** rodemkay  
**Emergency Escalation:** [Configure as needed]  
**Documentation Location:** `/home/rodemkay/www/react/plugin-todo/monitoring/`  
**Support Repository:** `github.com/rodemkay/todo-webhook-system`

## 🎓 TRAINING & CERTIFICATION

### New Admin Onboarding Checklist

- [ ] **Week 1:** System overview and architecture understanding
- [ ] **Week 2:** Daily operations and routine procedures
- [ ] **Week 3:** Troubleshooting and problem resolution
- [ ] **Week 4:** Emergency procedures and disaster recovery
- [ ] **Month 2:** Advanced monitoring and optimization
- [ ] **Month 3:** Security management and compliance

### Recommended Reading

1. **System Architecture:** `FINAL_SYSTEM_ARCHITECTURE.md`
2. **Backup Procedures:** `BACKUP_RECOVERY_PROCEDURES.md`
3. **Security Audit:** `SECURITY_AUDIT_REPORT.md`
4. **Production Setup:** `PRODUCTION_SERVICES_SETUP.md`

### Skills Assessment

Admins should be comfortable with:
- Linux system administration
- systemd service management
- Network troubleshooting
- Database administration (MySQL)
- WordPress administration
- Security best practices
- Backup and recovery procedures

---

## ✅ CONCLUSION

This handbook provides comprehensive guidance for managing the Webhook System in production. Regular review and updates of this documentation ensure effective system administration and minimal downtime.

**Remember:** When in doubt, check the logs first, then consult this handbook. Always test changes in a non-production environment when possible.

---

**Last Updated:** 2025-08-21  
**Version:** 1.0.0  
**Next Review:** 2025-09-21