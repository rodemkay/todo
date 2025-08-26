# 📂 Agent-Output Management System - ZENTRALISIERT AUF HETZNER

## 🎯 Wichtige Änderung (25.08.2025)
**ALLE Agent-Outputs werden nun ZENTRAL auf dem Hetzner/WordPress-Server gespeichert!**

## 📍 Neue Verzeichnisstruktur

### Primärer Speicherort (HETZNER-SERVER)
```
/var/www/forexsignale/staging/wp-content/uploads/agent-outputs/
└── todo-{ID}/
    ├── implementation_summary.md
    ├── technical_details.md
    ├── test_results.md
    └── ...
```

### Zugriff vom Ryzen-Server (über Mount)
```
/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs/
```

## 🔧 Für Agents und Subagents

### IMMER diesen Pfad verwenden:
```bash
# Vom Ryzen-Server (Claude Code CLI)
AGENT_OUTPUT_DIR="/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs/todo-${TODO_ID}"

# Erstelle Verzeichnis falls nicht vorhanden
mkdir -p "$AGENT_OUTPUT_DIR"

# Speichere Outputs
echo "# Zusammenfassung" > "$AGENT_OUTPUT_DIR/summary.md"
```

### In PHP/WordPress
```php
$upload_dir = wp_upload_dir();
$agent_output_dir = $upload_dir['basedir'] . '/agent-outputs/todo-' . $todo_id;
```

## ✅ Vorteile der Zentralisierung

1. **Einheitlicher Speicherort** - Keine Verwirrung mehr über mehrere Pfade
2. **WordPress-Integration** - Direkt über WordPress Admin abrufbar
3. **Backup-freundlich** - Wird mit WordPress-Backups gesichert
4. **Berechtigungen** - www-data hat automatisch Zugriff
5. **Mount-Zugriff** - Trotzdem vom Ryzen-Server über Mount erreichbar

## 🚨 WICHTIG: Alte Pfade NICHT mehr verwenden!

### ❌ VERALTET:
```bash
/home/rodemkay/www/react/plugin-todo/agent-outputs/  # NICHT MEHR VERWENDEN!
/home/rodemkay/www/react/agent-outputs/             # NICHT MEHR VERWENDEN!
```

### ✅ NEU:
```bash
# Immer über Mount auf Hetzner:
/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs/
```

## 📋 Für TODO-System Integration

### Bei neuen TODOs:
1. Agent-Output Verzeichnis wird automatisch erstellt
2. Pfad: `/wp-content/uploads/agent-outputs/todo-{ID}/`
3. Abrufbar über WordPress Admin Dashboard

### Bei TODO-Löschung:
- Verzeichnis wird automatisch mit gelöscht
- Implementiert in `class-todo-model.php`

## 🔄 Migration durchgeführt

Alle bestehenden Agent-Outputs wurden migriert:
- todo-245 ✅
- todo-316 ✅
- todo-355 ✅
- todo-356 ✅
- todo-361 ✅
- todo-362 ✅
- todo-363 ✅

## 📝 Hook-System Anpassung

Das Hook-System sollte ebenfalls den neuen Pfad verwenden:
```python
# In hooks/todo_manager.py
AGENT_OUTPUT_BASE = "/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs"
```

---
*Zentralisiert am: 2025-08-25*