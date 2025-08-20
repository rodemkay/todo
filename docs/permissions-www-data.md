# 🔐 PERMISSIONS & WWW-DATA USER

## 📍 WICHTIG
WordPress läuft unter dem User `www-data` auf dem Hetzner Server.
Alle Plugin-Dateien müssen korrekte Permissions haben!

## 👤 USER-ÜBERSICHT

### Hetzner Server
- **WordPress User:** www-data
- **Group:** www-data  
- **SSH User:** rodemkay
- **Sudo benötigt für:** Änderungen an www-data Dateien

### Typische Permission-Probleme
1. **rsync/scp schlägt fehl** → Dateien gehören www-data
2. **Plugin nicht ladbar** → Falsche Permissions
3. **Uploads funktionieren nicht** → wp-content/uploads braucht www-data

## 🔧 KORREKTE PERMISSIONS SETZEN

### Nach Plugin-Deployment
```bash
# Via SSH auf Hetzner
sudo chown -R www-data:www-data /var/www/forexsignale/staging/wp-content/plugins/todo
sudo chmod -R 755 /var/www/forexsignale/staging/wp-content/plugins/todo
sudo chmod -R 775 /var/www/forexsignale/staging/wp-content/uploads
```

### Für Entwicklung (temporär)
```bash
# Temporär für Deployment ändern
sudo chown -R rodemkay:www-data /var/www/forexsignale/staging/wp-content/plugins/todo
# Nach Änderungen zurücksetzen
sudo chown -R www-data:www-data /var/www/forexsignale/staging/wp-content/plugins/todo
```

## 📂 WICHTIGE VERZEICHNISSE

| Verzeichnis | Owner | Permissions | Zweck |
|------------|-------|-------------|--------|
| `/var/www/forexsignale/staging/` | www-data:www-data | 755 | WordPress Root |
| `.../wp-content/plugins/todo/` | www-data:www-data | 755 | Plugin-Dateien |
| `.../wp-content/uploads/` | www-data:www-data | 775 | Upload-Verzeichnis |
| `.../wp-content/cache/` | www-data:www-data | 775 | Cache-Dateien |

## 🐛 TROUBLESHOOTING

### Problem: Permission Denied bei rsync
```bash
# Lösung 1: Temporär Permissions ändern
ssh rodemkay@100.67.210.46
sudo chown -R rodemkay:www-data /path/to/plugin
# Deploy
rsync -avz ...
# Zurücksetzen
sudo chown -R www-data:www-data /path/to/plugin

# Lösung 2: Über Mount mit sudo
sudo rsync -avz /local/path/ /mount/path/
```

### Problem: Plugin wird nicht geladen
```bash
# Permissions prüfen
ls -la /var/www/forexsignale/staging/wp-content/plugins/
# Korrigieren
sudo chown -R www-data:www-data todo/
sudo chmod -R 755 todo/
```

### Problem: Datei-Uploads funktionieren nicht
```bash
# Uploads-Verzeichnis prüfen
ls -la /var/www/forexsignale/staging/wp-content/uploads/
# Korrigieren
sudo chown -R www-data:www-data uploads/
sudo chmod -R 775 uploads/
```

## 🚀 DEPLOYMENT WORKFLOW

1. **Entwicklung** auf Ryzen (als rodemkay)
2. **Deploy** via rsync/scp
3. **Permissions setzen** auf Hetzner:
   ```bash
   sudo chown -R www-data:www-data /var/www/forexsignale/staging/wp-content/plugins/todo
   ```
4. **Verify** im WordPress Admin

## 📝 NOTIZEN

- WordPress Core: www-data muss lesen können
- Plugins: www-data muss lesen und ausführen können
- Uploads: www-data muss schreiben können
- Cache: www-data muss schreiben und löschen können

---

**Wichtig:** Nach jedem Deployment Permissions prüfen und korrigieren!