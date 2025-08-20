# Remote Control Architektur für WordPress ↔ Claude CLI

## 🏗️ **Systemarchitektur**

### **Multi-Layer Communication System**

```
WordPress Dashboard (Hetzner)           Claude CLI (Ryzen)
159.69.157.54                          100.89.207.122
┌─────────────────────────────┐       ┌─────────────────────────────┐
│  WP Admin Dashboard         │       │  tmux session "claude"     │
│  ┌─────────────────────────┐│       │  ┌─────────────────────────┐│
│  │ Remote Control Panel    ││       │  │  Claude CLI active      ││
│  │ - Command Buttons       ││       │  │  ./todo system          ││
│  │ - Live Terminal         ││       │  │  Live output capture    ││
│  │ - Status Indicators     ││       │  │                         ││
│  └─────────────────────────┘│       │  └─────────────────────────┘│
└─────────────────────────────┘       └─────────────────────────────┘
              │                                         ▲
              │                                         │
              ▼                                         │
┌─────────────────────────────────────────────────────────────────┐
│                   COMMUNICATION LAYERS                         │
│                                                                 │
│  Layer 1: Socket Bridge (Port 8899) - LIVE BIDIRECTIONAL      │
│  Layer 2: SSH2 Extension - DIRECT TMUX CONTROL                │
│  Layer 3: Trigger Files - ROBUST FALLBACK                     │
│  Layer 4: SSH exec() - EMERGENCY FALLBACK                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 🔧 **Komponenten-Übersicht**

### **1. Socket Bridge System (Primär)**

**Zweck:** Echtzeit-bidirektionale Kommunikation
**Port:** 8899 auf RyzenServer  
**Protokoll:** TCP Socket + JSON Messages

**Vorteile:**
- ✅ Live-Terminal-Output im WordPress Dashboard
- ✅ Instant-Befehls-Übertragung
- ✅ Status-Monitoring in Echtzeit
- ✅ Bidirektionale Kommunikation
- ✅ Session-Persistenz

**Implementation:**
- `class-socket-bridge.php` - WordPress Socket Client
- `socket_server.py` - Python Socket Server auf RyzenServer
- Systemd Service für automatischen Start
- JSON-basierte Message-Protokoll

### **2. SSH2 Extension Handler (Sekundär)**

**Zweck:** Direkte SSH-Verbindung für Terminal-Control
**Methode:** SSH2 PHP Extension + tmux send-keys

**Vorteile:**
- ✅ Direkte tmux session Kontrolle
- ✅ Terminal-Output capture
- ✅ SSH Key oder Password Authentication
- ✅ Verbindung-Wiederverwendung

**Implementation:**
- `class-ssh2-handler.php` - SSH2 Wrapper
- tmux send-keys für Befehls-Injection
- tmux capture-pane für Output-Retrieval

### **3. Trigger File System (Fallback)**

**Zweck:** Mount-basierte Kommunikation (bestehend)
**Methode:** Watch-Script überwacht Trigger-Dateien

**Vorteile:**
- ✅ Funktioniert bereits
- ✅ Keine Netzwerk-Abhängigkeiten
- ✅ Robust gegen Verbindungsabbrüche

**Implementation:**
- `claude_trigger.txt` in WordPress uploads
- `watch-hetzner-trigger.sh` auf RyzenServer

## 📡 **Kommunikationsfluss**

### **Befehl senden (WordPress → Claude):**

```
1. USER klickt Button im WP Dashboard
   ↓
2. AJAX Request an wp-admin/admin-ajax.php
   ↓
3. Remote_Control::ajax_send_command()
   ↓
4. PRIORITÄT 0: Socket Bridge versuchen
   ├─ SUCCESS → Instant tmux send-keys
   └─ FAIL → Weiter zu Priorität 1
   ↓
5. PRIORITÄT 1: SSH2 Extension versuchen  
   ├─ SUCCESS → Direkter tmux send-keys
   └─ FAIL → Weiter zu Priorität 2
   ↓
6. PRIORITÄT 2: Trigger File schreiben
   ├─ SUCCESS → Watch-Script erkennt Datei
   └─ FAIL → SSH exec() Fallback
```

### **Live Output abrufen (Claude → WordPress):**

```
1. JavaScript Timer alle 2 Sekunden
   ↓
2. AJAX Request: get_terminal_output
   ↓
3. PRIORITÄT 0: Socket Bridge
   ├─ get_live_output() via TCP
   └─ JSON Response mit Terminal-Content
   ↓
4. PRIORITÄT 1: SSH2 Extension
   ├─ tmux capture-pane via SSH
   └─ Terminal-Output als String
   ↓
5. Terminal-Fenster im Dashboard aktualisieren
   ├─ Neue Zeilen hinzufügen
   └─ Auto-Scroll zu unten
```

## 🛠️ **Installation & Setup**

### **1. Socket Server Deployment**

```bash
# Setup Script ausführen
cd /home/rodemkay/www/react/scripts
./setup-socket-server.sh

# Auf RyzenServer deployen
./deploy-socket-server.sh

# Status prüfen
ssh rodemkay@100.89.207.122 "sudo systemctl status claude-socket-server"
```

### **2. SSH2 Extension Installation**

```bash
# Auf Hetzner Server
sudo apt update
sudo apt install php-ssh2
sudo systemctl restart apache2

# Prüfen ob verfügbar
php -m | grep ssh2
```

### **3. WordPress Plugin Update**

```bash
# Plugin-Dateien sind bereits implementiert:
# - class-socket-bridge.php
# - class-ssh2-handler.php  
# - enhanced remote-control.js
# - updated class-remote-control.php

# Plugin reaktivieren falls nötig
wp plugin deactivate wp-project-todos
wp plugin activate wp-project-todos
```

## 🔒 **Sicherheitsfeatures**

### **Authentication**
- SSH Key Authentication (bevorzugt)
- Password Authentication (Fallback)
- WordPress Nonce Verification
- Restricted Socket Ports

### **Network Security**
- Tailscale VPN zwischen Servern
- Socket Server nur auf privaten IPs
- SSH Timeout-Konfiguration
- Connection Limits

### **Input Validation**
- Command Sanitization
- JSON Message Validation
- tmux session Verification
- Error Handling mit Fallbacks

## 📊 **Status-Monitoring**

### **Live Status Indicators**

**Dashboard zeigt:**
- 🟢 Aktiv - Socket + SSH verfügbar
- 🟡 Bereit - Nur SSH oder Trigger verfügbar  
- 🔴 Offline - Keine Verbindung

**Monitoring alle 10 Sekunden:**
- Socket Server Ping-Test
- SSH tmux session Check
- Claude CLI Aktivitäts-Status

## 🚀 **Erweiterte Features**

### **Live Terminal Interface**
- Vollständiges Terminal-Fenster im Dashboard
- Befehls-Historie mit Timestamps
- Auto-Refresh alle 2 Sekunden
- Syntax-Highlighting für verschiedene Output-Typen
- Keyboard-Shortcuts (Enter für Send, Clear-Command)

### **Command Buttons**
- `./todo` - Einzelnes Todo laden
- `./todo all` - Alle Todos abarbeiten
- `./todo complete` - Aktuelles Todo abschließen
- `./todo count` - Todo-Anzahl anzeigen
- Custom Commands via Terminal-Input

### **Watch Script Management**
- Start/Stop Watch-Script via Dashboard
- PID-Tracking von Background-Prozessen
- Log-File Monitoring

## 🔧 **Troubleshooting**

### **Socket Server Probleme**
```bash
# Status prüfen
ssh rodemkay@100.89.207.122 "sudo systemctl status claude-socket-server"

# Logs anzeigen
ssh rodemkay@100.89.207.122 "sudo journalctl -u claude-socket-server -f"

# Port prüfen
ssh rodemkay@100.89.207.122 "netstat -tlnp | grep 8899"

# Neustart
ssh rodemkay@100.89.207.122 "sudo systemctl restart claude-socket-server"
```

### **SSH2 Extension Probleme**
```bash
# Extension prüfen
php -m | grep ssh2

# Alternative Installation
sudo apt install libssh2-1-dev
sudo pecl install ssh2
echo "extension=ssh2.so" | sudo tee /etc/php/8.1/apache2/conf.d/20-ssh2.ini
```

### **Connection Tests**
```bash
# Socket Test
./test-socket-connection.py 100.89.207.122 8899

# SSH Test  
ssh rodemkay@100.89.207.122 "tmux list-sessions | grep claude"

# Trigger File Test
ls -la /var/www/forexsignale/staging/wp-content/uploads/claude_trigger.txt
```

## 📈 **Performance & Skalierung**

### **Optimierungen**
- Connection Pooling für SSH2
- JSON Message Compression
- Terminal Output Buffering (100 Zeilen)
- AJAX Request Debouncing
- Automatic Cleanup alter Verbindungen

### **Resource Usage**
- Socket Server: ~5MB RAM
- SSH2 Connections: ~1MB pro Session  
- JavaScript Timer: Minimal CPU
- Terminal Buffer: ~50KB pro Session

## 🔮 **Zukunftserweiterungen**

### **Geplante Features**
- WebSocket-Upgrade für noch niedrigere Latenz
- Multi-Server Management (mehrere Claude-Instanzen)
- Command-Historie Persistierung in Database
- Mobile-responsive Terminal-Interface
- Bulk-Command Execution
- Scheduled Commands (Cron-Integration)

---

## 📋 **Quick Reference**

### **Wichtige URLs**
- WordPress Dashboard: `https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos`
- Socket Server: `100.89.207.122:8899`
- SSH Connection: `rodemkay@100.89.207.122`

### **Wichtige Befehle**
```bash
# Socket Server Management
sudo systemctl {start|stop|restart|status} claude-socket-server

# SSH Key Setup
ssh-copy-id rodemkay@100.89.207.122

# Plugin Development
wp plugin {activate|deactivate} wp-project-todos

# Testing
./test-socket-connection.py
```

---

**Status:** ✅ Vollständig implementiert und bereit für Deployment
**Letzte Aktualisierung:** 19.08.2025