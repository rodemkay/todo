# 🌐 TODO PROJECT - ENVIRONMENT DOCUMENTATION

## 📍 INFRASTRUKTUR-ÜBERSICHT

### Server-Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                     RYZEN SERVER                             │
│                   (Development & CLI)                         │
│                                                              │
│  - IP: 100.89.207.122 (Tailscale)                          │
│  - Claude Code CLI (tmux session "claude")                  │
│  - Webhook Server (Port 8089)                              │
│  - Mount Points zu Hetzner                                 │
│  - Working Dir: /home/rodemkay/www/react/todo/            │
└────────────┬────────────────────────────────────────────────┘
             │
             │ SSH/SSHFS Mount
             │ Tailscale Network
             │
┌────────────▼────────────────────────────────────────────────┐
│                     HETZNER SERVER                           │
│                  (Production/Staging)                        │
│                                                              │
│  - Public IP: 159.69.157.54                                │
│  - Tailscale IP: 100.67.210.46                             │
│  - WordPress Installation                                   │
│  - MySQL Database                                          │
│  - Path: /var/www/forexsignale/staging/                   │
└─────────────────────────────────────────────────────────────┘
```

## 🔑 ZUGANGSDATEN

### Hetzner Server
- **SSH:** `ssh rodemkay@159.69.157.54` oder `ssh rodemkay@100.67.210.46`
- **SSH Password:** Siehe .env (HETZNER_SSH_PASS)
- **Sudo Password:** Siehe .env (HETZNER_SUDO_PASS)

### WordPress Admin
- **URL:** https://forexsignale.trade/staging/wp-admin
- **User 1:** ForexSignale / .Foret333doka?
- **User 2:** wsj-admin / wsj2024secure

### Datenbank
- **Host:** localhost (auf Hetzner)
- **Database:** staging_forexsignale
- **User:** ForexSignale
- **Password:** @C3e!S5t#Q7p*V8g
- **Prefix:** stage_
- **phpMyAdmin:** https://forexsignale.trade/staging/phpmyadmin

## 📂 WICHTIGE PFADE

### Auf Ryzen Server
```
/home/rodemkay/www/react/todo/          # Hauptprojekt
├── plugin/                              # Plugin Source Code
├── hooks/                               # Hook System
├── cli/                                 # CLI Tools
└── docs/                                # Dokumentation

/home/rodemkay/www/react/mounts/        # Mount Points
├── hetzner/
│   └── forexsignale/                   # Live Mount (READ-ONLY!)
│       └── staging/                    # Staging Mount (READ/WRITE)
```

### Auf Hetzner Server
```
/var/www/forexsignale/
├── staging/                             # Staging WordPress
│   └── wp-content/
│       └── plugins/
│           └── todo/                    # Neuer Plugin-Pfad!
└── live/                                # Production (nicht anfassen!)
```

## 🌐 NETZWERK-KONFIGURATION

### Tailscale VPN
- **Network:** 100.64.0.0/10
- **Ryzen:** 100.89.207.122
- **Hetzner:** 100.67.210.46

### ⚠️ WICHTIGE REGEL
**NIEMALS `localhost` in Code verwenden!**
- Falsch: `http://localhost:8089/webhook`
- Richtig: `http://100.89.207.122:8089/webhook`

### Webhook System
```javascript
// Webhook Configuration
const WEBHOOK_CONFIG = {
    host: '100.89.207.122',    // Ryzen Tailscale IP
    port: 8089,
    endpoint: '/webhook',
    secret: 'secure_webhook_key_2024'
};
```

## 🚀 DEPLOYMENT WORKFLOW

### 1. Development (Ryzen)
```bash
# Arbeiten im lokalen Verzeichnis
cd /home/rodemkay/www/react/todo
# Code bearbeiten
vim plugin/includes/class-admin.php
```

### 2. Testing
```bash
# Playwright Tests lokal
npm test
```

### 3. Deploy zu Staging
```bash
# Automatisches Deployment
./scripts/deploy.sh staging

# Oder manuell via rsync
rsync -avz plugin/ rodemkay@100.67.210.46:/var/www/forexsignale/staging/wp-content/plugins/todo/
```

### 4. Verification
```bash
# SSH zu Hetzner
ssh rodemkay@100.67.210.46

# Plugin Status prüfen
cd /var/www/forexsignale/staging
wp plugin list | grep todo
```

## 🔄 MOUNT POINTS VERWALTUNG

### Mount aktivieren (falls nicht aktiv)
```bash
# SSHFS Mount für Staging
sshfs rodemkay@100.67.210.46:/var/www/forexsignale/staging \
      /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging

# Mount prüfen
df -h | grep hetzner
```

### Mount deaktivieren
```bash
fusermount -u /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging
```

## 📧 EMAIL KONFIGURATION

### Email Accounts
- **info@forexsignale.trade** - Hauptadresse
- **claude@forexsignale.trade** - Claude-spezifisch

### SMTP (Brevo)
- **Server:** smtp-relay.brevo.com:587
- **User:** 94e086001@smtp-brevo.com
- **Key:** Siehe .env (SMTP_KEY)

## 🔐 SICHERHEIT

### Wichtige Sicherheitsregeln
1. **.env niemals committen!** (ist in .gitignore)
2. **Passwörter nur in .env speichern**
3. **Live-System niemals direkt bearbeiten**
4. **Backups vor größeren Änderungen**
5. **SSH-Keys verwenden statt Passwörter** (wenn möglich)

### Backup-Strategie
```bash
# Backup erstellen
ssh rodemkay@100.67.210.46 "cd /var/www/forexsignale/staging && \
    wp db export backup-$(date +%Y%m%d-%H%M%S).sql"

# Plugin Backup
ssh rodemkay@100.67.210.46 "tar -czf todo-backup-$(date +%Y%m%d).tar.gz \
    /var/www/forexsignale/staging/wp-content/plugins/todo"
```

## 🐛 TROUBLESHOOTING

### Problem: Mount nicht erreichbar
```bash
# Mount-Status prüfen
mount | grep hetzner

# Neu mounten
fusermount -u /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging
sshfs rodemkay@100.67.210.46:/var/www/forexsignale/staging \
      /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging
```

### Problem: Webhook empfängt keine Befehle
```bash
# Webhook-Server Status
ps aux | grep webhook
netstat -tlnp | grep 8089

# Webhook neu starten
cd /home/rodemkay/www/react/todo
python3 webhook-server.py &
```

### Problem: Plugin nicht sichtbar in WordPress
```bash
# Via SSH prüfen
ssh rodemkay@100.67.210.46
cd /var/www/forexsignale/staging
wp plugin list
wp plugin activate todo
```

## 📊 MONITORING

### Logs prüfen
```bash
# WordPress Debug Log
ssh rodemkay@100.67.210.46 "tail -f /var/www/forexsignale/staging/wp-content/debug.log"

# System Logs
tail -f /tmp/audit.log
tail -f /tmp/claude_trigger.log
```

### Performance
```bash
# Server-Auslastung Hetzner
ssh rodemkay@100.67.210.46 "htop"

# Datenbank-Performance
ssh rodemkay@100.67.210.46 "cd /var/www/forexsignale/staging && \
    wp db query 'SHOW PROCESSLIST'"
```

---

**Letzte Aktualisierung:** 2025-08-20  
**Version:** 1.0.0