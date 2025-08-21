# 📁 MOUNT USAGE GUIDE - Read/Edit Tools

**Erstellt:** 2025-08-21  
**Status:** ✅ AKTIV - Kritisch für alle Datei-Operationen  
**Zweck:** Korrekte Verwendung von Read/Edit Tools über SSHFS-Mounts

---

## 🚨 KRITISCHE REGEL

**Read/Edit Tools funktionieren NUR über lokale Pfade!**  
SSH-Pfade werden NICHT unterstützt und führen zu Fehlern.

---

## ✅ KORREKTE VERWENDUNG

### **Verfügbare Mounts:**
```bash
/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/  # WordPress Staging
/home/rodemkay/www/react/mounts/hetzner/forexsignale/         # WordPress Live (READ-ONLY)
```

### **Read Tool - RICHTIG:**
```javascript
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/todo.php")
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/admin/new-todo.php")
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-config.php")
```

### **Edit Tool - RICHTIG:**
```javascript
Edit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/admin/new-todo.php",
     "old_string_here",
     "new_string_here")
```

### **MultiEdit Tool - RICHTIG:**
```javascript
MultiEdit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/todo.php", [
    { old_string: "old1", new_string: "new1" },
    { old_string: "old2", new_string: "new2" }
])
```

---

## ❌ FALSCHE VERWENDUNG

### **Diese Pfade funktionieren NICHT:**
```javascript
// ❌ FALSCH - SSH-Pfad:
Read("/var/www/forexsignale/staging/wp-content/plugins/todo/todo.php")

// ❌ FALSCH - SSH mit Host:
Read("rodemkay@159.69.157.54:/var/www/forexsignale/staging/...")

// ❌ FALSCH - Relativer Pfad:
Read("wp-content/plugins/todo/todo.php")

// ❌ FALSCH - Ohne Mount:
Edit("/var/www/forexsignale/staging/...", old, new)
```

### **Typische Fehlermeldungen:**
```
Error: <tool_use_error>File does not exist.</tool_use_error>
Error: Permission denied
Error: No such file or directory
```

---

## 🔧 MOUNT STATUS PRÜFEN

### **Mount verfügbar?**
```bash
ls -la /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/
```

### **Mount neu einrichten (falls nötig):**
```bash
# Prüfe ob gemountet:
mountpoint /home/rodemkay/www/react/mounts/hetzner/

# Falls nicht gemountet:
sudo sshfs rodemkay@159.69.157.54:/var/www/ \
    /home/rodemkay/www/react/mounts/hetzner/ \
    -o allow_other,reconnect,ServerAliveInterval=15
```

---

## 📋 PFAD-MAPPING REFERENZ

| SSH-Pfad (Server) | Mount-Pfad (Lokal) | Tool-Usage |
|-------------------|---------------------|------------|
| `/var/www/forexsignale/staging/` | `/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/` | ✅ |
| `/var/www/forexsignale/` | `/home/rodemkay/www/react/mounts/hetzner/forexsignale/` | ✅ |
| `rodemkay@159.69.157.54:/var/www/` | - | ❌ |

---

## 🎯 PRAKTISCHE BEISPIELE

### **WordPress Plugin bearbeiten:**
```javascript
// Plugin-Datei lesen:
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/todo.php")

// Plugin-Datei bearbeiten:
Edit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/admin/new-todo.php",
     "<?php if ($todo): ?>",
     "<?php // Immer anzeigen: ?>")
```

### **Theme-Dateien bearbeiten:**
```javascript
// Theme-Funktionen lesen:
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/themes/fxmag/functions.php")

// Style.css bearbeiten:
Edit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/themes/fxmag/style.css",
     "/* old css */",
     "/* new css */")
```

### **Konfigurationsdateien:**
```javascript
// wp-config.php lesen:
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-config.php")

// .htaccess bearbeiten:
Edit("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/.htaccess",
     "old_rule",
     "new_rule")
```

---

## 🐛 TROUBLESHOOTING

### **Problem: "File does not exist"**
**Lösung:** Prüfe Mount-Status und verwende korrekten Mount-Pfad

### **Problem: "Permission denied"**
**Lösung:** 
```bash
# Mount-Rechte prüfen:
ls -la /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/

# Falls nötig, neu mounten mit allow_other
```

### **Problem: Tools sind langsam**
**Ursache:** SSHFS kann bei großen Dateien langsam sein  
**Lösung:** Normal - SSHFS ist für Remote-Zugriff optimiert

---

## 💡 BEST PRACTICES

### **1. Immer Mount-Status prüfen vor Tool-Usage**
```bash
ls /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/ | head -5
```

### **2. Vollständige Pfade verwenden**
```javascript
// ✅ RICHTIG - Absoluter Mount-Pfad:
Read("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/plugins/todo/todo.php")
```

### **3. Bei Fehlern: SSH-Fallback verwenden**
```bash
# Falls Read/Edit nicht funktioniert, SSH nutzen:
ssh rodemkay@159.69.157.54 "cat /var/www/forexsignale/staging/wp-content/plugins/todo/todo.php"
```

### **4. Backup vor größeren Edits**
```bash
ssh rodemkay@159.69.157.54 "cp /var/www/file.php /var/www/file.php.backup-$(date +%Y%m%d-%H%M%S)"
```

---

## ✅ FAZIT

**IMMER ÜBER MOUNTS ARBEITEN!**  
Die SSHFS-Mounts sind bereits konfiguriert und funktionieren zuverlässig.  
Read/Edit Tools sind darauf angewiesen und funktionieren nur so.

**Quick-Referenz-Pfad:**  
`/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/` + `dein/datei/pfad`

---

**Letzte Verifikation:** 2025-08-21 ✅ - Alle Pfade getestet und funktionsfähig