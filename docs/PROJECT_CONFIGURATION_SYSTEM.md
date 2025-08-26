# 🎯 PROJECT CONFIGURATION SYSTEM - Dokumentation

## Übersicht

Das Project Configuration System ist eine zentrale Lösung für die Verwaltung verschiedener Claude-Projekte. Es ermöglicht automatische Projekt-Erkennung, Environment-Setup und nahtlose Integration mit dem Todo-System.

## 📁 Komponenten

### Kern-Dateien
```
config/
├── projects.json                    # Zentrale Projekt-Konfiguration
scripts/
├── project-detector.sh             # Projekt-Erkennung & Validation
├── project-manager.sh              # Projekt-Management & Switching  
└── todo-project-integration.sh     # Todo-System Integration
```

### Konfigurationsdatei: `projects.json`

Zentrale JSON-Konfiguration mit allen Projekt-Definitionen:

```json
{
  "projects": {
    "plugin-todo": {
      "name": "Todo Management System",
      "type": "wordpress-plugin",
      "directories": {
        "working_directory": "/home/rodemkay/www/react/plugin-todo",
        "mount_staging": "/home/rodemkay/www/react/mounts/hetzner/.../todo"
      },
      "tmux": {
        "session_name": "claude",
        "window_name": "todo-main"
      }
    }
  },
  "scope_mappings": {
    "Plugin Development": ["plugin-todo", "article-builder"],
    "Website": ["forexsignale-magazine"]
  }
}
```

## 🔧 Funktionalitäten

### 1. Automatische Projekt-Erkennung

Das System erkennt Projekte basierend auf:
- **Aktueller Pfad**: Pattern-Matching gegen definierte Pfad-Regeln
- **Todo-Scope**: Mapping von Todo-Bereichen zu Projekten  
- **Arbeitsverzeichnis**: Direkter Match mit Projekt-Working-Directories

```bash
# Auto-detect basierend auf aktuellem Pfad
./project-detector.sh auto-detect

# Erkennung via Todo-Scope
./project-detector.sh scope "Plugin Development"

# Erkennung via spezifischem Pfad
./project-detector.sh path "/home/rodemkay/www/react/plugin-article"
```

### 2. Projekt-Management

Vollständiges Management von Projekt-Umgebungen:

```bash
# Zu Projekt wechseln (komplettes Environment Setup)
./project-manager.sh switch plugin-todo

# Todo-basierter Projekt-Wechsel
./project-manager.sh switch-todo 123 "Backend"

# Aktuelles Projekt anzeigen
./project-manager.sh current

# Working Directory validieren
./project-manager.sh validate

# Mount-Status prüfen
./project-manager.sh mounts
```

### 3. Todo-Integration

Nahtlose Integration mit dem Todo-System:

```bash
# Environment für Todo vorbereiten (automatisch)
./todo-project-integration.sh prepare 123 "Plugin Development" "/custom/workdir"

# Projekt für Todo erkennen
./todo-project-integration.sh detect 123 "Backend"

# Todo-Environment Status
./todo-project-integration.sh status

# Environment nach Todo bereinigen
./todo-project-integration.sh cleanup 123
```

## 📋 Projekt-Definitionen

### Aktuelle Projekte

1. **plugin-todo** (Priorität: 1)
   - Todo Management System
   - WordPress Plugin
   - Working Dir: `/home/rodemkay/www/react/plugin-todo`
   - tmux: Session `claude`, Window `todo-main`

2. **forexsignale-magazine** (Priorität: 2)  
   - ForexSignale Magazine Website
   - WordPress Website im WSJ-Stil
   - Working Dir: `/home/rodemkay/www/react`
   - tmux: Session `claude`, Window `forexsignale`

3. **article-builder** (Priorität: 3)
   - Article Builder Plugin
   - WordPress Plugin mit KI-Integration
   - Working Dir: `/home/rodemkay/www/react/plugin-article`
   - Build System: webpack + npm

## 🔀 Scope-Mappings

Todo-Bereiche werden automatisch zu Projekten gemappt:

```json
{
  "Website": ["forexsignale-magazine"],
  "Plugin Development": ["plugin-todo", "article-builder"],
  "WordPress": ["forexsignale-magazine", "plugin-todo", "article-builder"],
  "Frontend": ["forexsignale-magazine", "article-builder"],
  "Backend": ["plugin-todo"],
  "Full-Stack": ["forexsignale-magazine", "plugin-todo", "article-builder"]
}
```

## 🌐 Environment Management

### Automatisches Setup

Bei Projekt-Wechsel werden automatisch gesetzt:
- **Working Directory**: Wechsel zum Projekt-Verzeichnis
- **Environment Variables**: Projekt-spezifische Variablen
- **tmux Session**: Window/Pane-Management
- **Mount Points**: Validation von SSHFS-Mounts
- **MCP Server**: Requirements-Check

### Standard Environment Variables

```bash
# Global
CLAUDE_CURRENT_PROJECT=plugin-todo
CLAUDE_PROJECT_CONFIG=/path/to/projects.json
CLAUDE_PROJECT_LOG=/tmp/claude-project-manager.log

# Todo-spezifisch  
CLAUDE_CURRENT_TODO_ID=123
CLAUDE_CURRENT_TODO_SCOPE="Plugin Development"
CLAUDE_TODO_WORKING_DIR=/custom/workdir

# Projekt-spezifisch
PROJECT_NAME=plugin-todo
PLUGIN_PATH=/var/www/forexsignale/staging/wp-content/plugins/todo
DB_NAME=staging_forexsignale
```

## 🔍 Pfad-Pattern Matching

Automatische Erkennung via Pfad-Patterns:

```json
{
  "path_patterns": {
    "/home/rodemkay/www/react/plugin-todo/**": "plugin-todo",
    "/home/rodemkay/www/react/plugin-article/**": "article-builder", 
    "/home/rodemkay/www/react/[!plugin-]*/**": "forexsignale-magazine",
    "/var/www/forexsignale/staging/wp-content/plugins/todo/**": "plugin-todo"
  }
}
```

## 🚀 Integration in Todo-Workflow

### Automatische Integration

Das Todo-System (`./todo`) ruft automatisch die Projekt-Integration auf:

1. **Bei Todo-Start**: `prepare_todo_environment()`
   - Projekt-Erkennung basierend auf Scope/Working-Dir
   - Environment Variables Setup
   - Working Directory Wechsel
   
2. **Während Todo-Bearbeitung**: 
   - Environment bleibt aktiv
   - Alle projekt-spezifischen Tools verfügbar
   
3. **Bei Todo-Complete**: `cleanup_todo_environment()`
   - Todo-spezifische Variablen löschen
   - Projekt bleibt für weitere Todos aktiv

### Todo-spezifische Erkennung

```bash
# Beispiel Todo-Daten:
{
  "id": 123,
  "scope": "Plugin Development", 
  "arbeitsverzeichnis": "/home/rodemkay/www/react/plugin-todo"
}

# Automatische Erkennung:
# 1. Arbeitsverzeichnis → plugin-todo
# 2. Scope → plugin-todo oder article-builder (erstes aktives)  
# 3. Fallback → auto-detect via Pfad
```

## 📊 Validation & Health Checks

### Projekt-Validation

- **Working Directory**: Existenz prüfen
- **CLAUDE.md**: Konfigurationsdatei vorhanden
- **Mount Points**: SSHFS-Mounts verfügbar
- **MCP Server**: Required/Optional Server verfügbar
- **Build System**: package.json, webpack.config.js (falls applicable)

### System Health

```bash
# Vollständige Projekt-Liste
./project-detector.sh list

# Projekt-spezifische Validation
./project-detector.sh validate plugin-todo

# System-Status
./project-detector.sh status

# Mount-Status
./project-manager.sh mounts
```

## 🔧 Erweiterung & Kustomisierung

### Neues Projekt hinzufügen

1. **projects.json erweitern**:
```json
{
  "projects": {
    "neues-projekt": {
      "name": "Neues Projekt",
      "type": "custom",
      "priority": 4,
      "status": "active",
      "directories": {
        "working_directory": "/path/to/project"
      }
    }
  }
}
```

2. **Scope-Mapping hinzufügen**:
```json
{
  "scope_mappings": {
    "Neuer Bereich": ["neues-projekt"]
  }
}
```

3. **Pfad-Pattern definieren**:
```json
{
  "path_patterns": {
    "/path/to/project/**": "neues-projekt"
  }
}
```

### Custom MCP Server Requirements

```json
{
  "mcp_servers": {
    "required": ["filesystem", "custom-server"],
    "optional": ["additional-server"]  
  }
}
```

### Build System Integration

```json
{
  "build_system": {
    "type": "npm",
    "commands": {
      "build": "npm run build",
      "dev": "npm run dev",
      "test": "npm test"
    }
  }
}
```

## 📝 Logs & Debugging

### Log-Dateien

```
/tmp/claude-project-config.log         # Project Detector
/tmp/claude-project-manager.log        # Project Manager  
/tmp/claude-todo-project-integration.log # Todo Integration
```

### Debug-Modi

```bash
# Verbose Logging aktivieren
export CLAUDE_PROJECT_DEBUG=true

# Log-Level setzen  
export CLAUDE_PROJECT_LOG_LEVEL=DEBUG
```

## ⚠️ Bekannte Limitationen

1. **tmux Dependency**: tmux Session muss bereits existieren
2. **Mount Dependencies**: SSHFS-Mounts müssen manuell eingerichtet werden
3. **Permission Issues**: Scripts benötigen Exec-Permissions
4. **JSON Parsing**: jq muss installiert sein

## 🔮 Roadmap

### Geplante Features

- **GUI-Interface**: Web-basiertes Project Management
- **Remote Project Support**: SSH-basierte Projekt-Verwaltung  
- **Template System**: Projekt-Templates für neue Projekte
- **Integration APIs**: REST API für externe Integration
- **Auto-Configuration**: Automatische Projekt-Erkennung via Git/Package-Dateien

### Verbesserungen

- **Performance**: Caching für häufige Operationen
- **Error Handling**: Robustere Fehlerbehandlung
- **Documentation**: Automatische Dokumentationsgenerierung
- **Testing**: Unit Tests für alle Komponenten

---

**Letzte Aktualisierung**: 2024-01-24  
**Version**: 1.0.0  
**Autor**: Claude AI Assistant