# TODO Defaults Tabelle - Umbenennung

## ✅ Erfolgreich umbenannt

**Alt:** `stage_todo_defaults`  
**Neu:** `stage_project_todo_defaults`

## 📊 Anpassungen

### 1. Datenbank
```sql
RENAME TABLE stage_todo_defaults TO stage_project_todo_defaults
```
- ✅ Alle 14 Datensätze erhalten
- ✅ Struktur unverändert

### 2. PHP-Klasse TodoDefaultsManager
Alle Referenzen angepasst:
- `{$wpdb->prefix}todo_defaults` → `{$wpdb->prefix}project_todo_defaults`
- Konstante: `TABLE_NAME = 'stage_project_todo_defaults'`

### 3. Konsistenz im System
Jetzt alle TODO-Tabellen einheitlich:
```
stage_project_todos                 # Haupt-Tabelle
stage_project_todo_attachments      # Anhänge
stage_project_todo_comments         # Kommentare
stage_project_todo_continuations    # Fortsetzungen
stage_project_todo_cron_reports     # Cron-Reports
stage_project_todo_defaults         # ✅ NEU: Standardwerte
stage_project_todo_history          # Historie
stage_project_todo_reports          # Reports
stage_project_todo_versions         # Versionen
```

## 📍 Datenbank-Info

**Datenbank:** `staging_forexsignale`  
**Prefix:** `stage_`  
**Vollständiger Tabellenname:** `stage_project_todo_defaults`

## ✅ Verifizierung

MCP-Server Defaults sind weiterhin korrekt:
```json
["context7","filesystem","puppeteer"]
```

---
**Umbenennung abgeschlossen:** 26.08.2025