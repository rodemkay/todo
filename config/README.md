# 🎯 Project Configuration System - Quick Start

## Übersicht

Dieses System verwaltet automatisch verschiedene Claude-Projekte und deren spezifische Konfigurationen.

## 📁 Dateien

- **`projects.json`**: Zentrale Konfiguration aller Projekte
- **`../scripts/project-detector.sh`**: Projekt-Erkennung
- **`../scripts/project-manager.sh`**: Projekt-Management  
- **`../scripts/todo-project-integration.sh`**: Todo-Integration

## 🚀 Schnellstart

### Projekte auflisten
```bash
cd /home/rodemkay/www/react/plugin-todo
./scripts/project-detector.sh list
```

### Projekt automatisch erkennen
```bash
# Basierend auf aktuellem Pfad
./scripts/project-detector.sh auto-detect

# Basierend auf Todo-Scope
./scripts/project-detector.sh scope "Plugin Development"
```

### Zu Projekt wechseln
```bash
./scripts/project-manager.sh switch plugin-todo
```

### Todo-Integration testen
```bash
./scripts/todo-project-integration.sh detect 123 "Backend"
```

## 🔧 Konfiguration

### Neues Projekt hinzufügen

1. **In `projects.json` erweitern**:
```json
{
  "projects": {
    "mein-projekt": {
      "name": "Mein Projekt", 
      "type": "custom",
      "directories": {
        "working_directory": "/path/to/project"
      },
      "tmux": {
        "session_name": "claude",
        "window_name": "mein-projekt"
      }
    }
  }
}
```

2. **Scope-Mapping hinzufügen**:
```json
{
  "scope_mappings": {
    "Mein Bereich": ["mein-projekt"]
  }
}
```

3. **Pfad-Pattern definieren**:
```json
{
  "path_patterns": {
    "/path/to/project/**": "mein-projekt"
  }
}
```

## 📊 Aktuelle Projekte

1. **plugin-todo** - Todo Management System
2. **forexsignale-magazine** - ForexSignale Website
3. **article-builder** - Article Builder Plugin

## 🔍 Scope-Mappings

- **Website** → forexsignale-magazine
- **Plugin Development** → plugin-todo, article-builder  
- **Backend** → plugin-todo
- **Frontend** → forexsignale-magazine, article-builder
- **WordPress** → alle Projekte

## 📝 Logs

Alle Logs werden nach `/tmp/` geschrieben:
- `claude-project-config.log`
- `claude-project-manager.log`
- `claude-todo-project-integration.log`

## ⚙️ Integration mit ./todo

Das System ist vollständig in das Todo-System integriert:

1. **Bei `./todo` Start**: Automatische Projekt-Erkennung und Environment Setup
2. **Während Todo**: Projekt-spezifische Tools und Pfade verfügbar
3. **Bei `./todo complete`**: Cleanup, Projekt bleibt aktiv

## 🆘 Troubleshooting

### "Kein Projekt erkannt"
1. Prüfe `projects.json` Syntax: `jq empty config/projects.json`
2. Validiere Pfad-Pattern für aktuelles Verzeichnis
3. Fallback auf manuelles Projekt: `./scripts/project-manager.sh switch <projekt>`

### "Working Directory nicht gefunden"
1. Prüfe ob Pfad in `projects.json` existiert
2. Erstelle fehlende Verzeichnisse
3. Validiere Mount-Points: `./scripts/project-manager.sh mounts`

### "tmux Session Fehler"  
1. Prüfe ob tmux läuft: `tmux list-sessions`
2. Session 'claude' muss existieren
3. Ignoriere tmux-Fehler - System funktioniert auch ohne

Vollständige Dokumentation: `../docs/PROJECT_CONFIGURATION_SYSTEM.md`