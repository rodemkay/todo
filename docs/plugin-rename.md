# 📝 PLUGIN RENAME DOCUMENTATION

## 📍 STATUS
**Datum:** 2025-08-20  
**Änderung:** Plugin umbenannt von "WP Project Todos" zu "TODO"

## 🔄 DURCHGEFÜHRTE ÄNDERUNGEN

### 1. Haupt-Plugin-Datei
- **Alt:** `wp-project-todos.php`
- **Neu:** `todo.php`
- **Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/wp-project-todos/`
- **Hinweis:** Beide Dateien existieren aktuell parallel

### 2. Plugin-Header geändert
```php
// ALT:
Plugin Name: WP Project To-Dos
Text Domain: wp-project-todos
Version: 1.0.0

// NEU:
Plugin Name: TODO
Text Domain: todo
Version: 2.0.0
```

### 3. WordPress Aktivierung
```bash
# Altes Plugin deaktivieren
wp plugin deactivate wp-project-todos

# Neues Plugin aktivieren  
wp plugin activate wp-project-todos/todo.php
```

## ⚠️ WICHTIGE HINWEISE

### Warum im alten Verzeichnis?
- Plugin liegt noch in `/wp-project-todos/` Ordner
- Nur die Haupt-Datei wurde umbenannt zu `todo.php`
- Vollständige Migration zu `/todo/` Ordner später

### Kompatibilität
- Datenbank-Tabellen bleiben gleich: `stage_project_todos`
- Alle Funktionen weiterhin verfügbar
- Menu-Slug bleibt: `wp-project-todos` (für URL-Kompatibilität)

## 🚀 NÄCHSTE SCHRITTE

1. **Test der neuen todo.php**
   ```bash
   wp plugin list | grep -E "todo|project"
   ```

2. **Alte wp-project-todos.php löschen** (nach erfolgreichem Test)

3. **Vollständige Migration** zu `/plugins/todo/` Ordner

## 📊 AUSWIRKUNGEN

### Keine Änderungen bei:
- Datenbank-Struktur
- Admin-URLs
- Bestehende Todos
- Hook-System

### Geändert:
- Plugin-Name in Admin-Liste
- Version auf 2.0.0
- Text-Domain zu "todo"

---

**Status:** Plugin umbenannt, Test ausstehend