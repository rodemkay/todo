# 🚀 Multi-Session Claude Management - Lösungskonzepte

## Problem
- Eine Claude Session ist an ein Arbeitsverzeichnis gebunden (mit CLAUDE.md)
- Bei mehreren Projekten müsste man zwischen Verzeichnissen wechseln
- Aktuelle Kitty-Session "claude" ist auf ein Projekt fixiert

## Lösungsansätze

### 1. **Mehrere Kitty Sessions** (Empfohlen)
```bash
# Für jedes Projekt eine eigene Session
kitty @ new-session --name claude-todo --cwd /home/rodemkay/www/react/plugin-todo
kitty @ new-session --name claude-forex --cwd /home/rodemkay/www/react/forexsignale
kitty @ new-session --name claude-trading --cwd /home/rodemkay/trading

# Zwischen Sessions wechseln
kitty @ focus-tab --match title:claude-todo
kitty @ focus-tab --match title:claude-forex
```

### 2. **Tmux Sessions pro Projekt**
```bash
# Tmux Sessions erstellen
tmux new-session -d -s claude-todo -c /home/rodemkay/www/react/plugin-todo
tmux new-session -d -s claude-forex -c /home/rodemkay/www/react/forexsignale

# Session wechseln
tmux switch-client -t claude-todo
tmux switch-client -t claude-forex

# Claude in spezifischer Session starten
tmux send-keys -t claude-todo "claude" Enter
```

### 3. **Dynamic Working Directory** 
```bash
# Script: switch-claude-project.sh
#!/bin/bash
PROJECT=$1
case $PROJECT in
    todo)
        cd /home/rodemkay/www/react/plugin-todo
        ;;
    forex)
        cd /home/rodemkay/www/react/forexsignale
        ;;
esac
# Reload CLAUDE.md context
claude --reset-context
```

### 4. **Project-Specific Claude Wrapper**
```bash
#!/bin/bash
# claude-project.sh
PROJECT_NAME=$1
PROJECT_DIR="/home/rodemkay/www/react/$PROJECT_NAME"

# Start Claude with project-specific context
cd "$PROJECT_DIR"
export CLAUDE_PROJECT="$PROJECT_NAME"
claude --context "$PROJECT_DIR/CLAUDE.md"
```

## Kitty Layout Control

### Remote Layout Manipulation
```bash
# Resize panes in Kitty
kitty @ resize-window --match id:left --axis horizontal --increment -80
kitty @ resize-window --match id:right --axis horizontal --increment 80

# Change layout
kitty @ goto-layout tall  # Vertical split
kitty @ goto-layout fat   # Horizontal split
kitty @ goto-layout grid  # Grid layout

# Create custom layout
kitty @ launch --location=hsplit --cwd=current
kitty @ resize-window --self --axis=horizontal --increment=-40

# Send to specific window
kitty @ send-text --match title:claude "cd /new/project\n"
```

### Kitty Session Control Script
```bash
#!/bin/bash
# manage-claude-sessions.sh

ACTION=$1
PROJECT=$2

case $ACTION in
    create)
        kitty @ new-window --new-tab --tab-title "claude-$PROJECT" \
              --cwd "/home/rodemkay/www/react/$PROJECT"
        kitty @ send-text --match title:"claude-$PROJECT" \
              "claude\n"
        ;;
    
    switch)
        kitty @ focus-tab --match title:"claude-$PROJECT"
        ;;
    
    resize)
        # Make left pane 10%, right pane 90%
        kitty @ resize-window --match num:0 --axis horizontal --increment -40
        kitty @ resize-window --match num:1 --axis horizontal --increment 40
        ;;
    
    reset)
        # Reset to 50/50 split
        kitty @ goto-layout tall
        ;;
esac
```

## Implementierungs-Empfehlung

### Phase 1: Tmux Multi-Session
1. Erstelle tmux Sessions für jedes Projekt
2. Nutze Session-Namen als Projekt-Identifier
3. Implementiere Switch-Kommandos im todo CLI

### Phase 2: Kitty Integration
1. Nutze Kitty Remote Control für Layout-Management
2. Implementiere Project-Switcher mit Kitty Tabs
3. Automatische CLAUDE.md Erkennung

### Phase 3: Project Manager
```bash
# claude-pm (Claude Project Manager)
claude-pm list           # Liste alle Projekte
claude-pm switch todo    # Wechsle zu todo Projekt
claude-pm new forex      # Erstelle neues Projekt
claude-pm resize 10:90   # Resize Layout
```

## Konkrete Befehle für sofortige Nutzung

### 1. Neues Projekt-Fenster erstellen
```bash
kitty @ launch --type=tab --tab-title "claude-newproject" \
       --cwd /path/to/project
```

### 2. Layout ändern (10% links, 90% rechts)
```bash
# In der aktuellen Kitty Session
kitty @ resize-window --match num:0 --axis horizontal --increment -40
```

### 3. Zwischen Projekten wechseln
```bash
# Via tmux
tmux switch-client -t claude-todo
# Via Kitty
kitty @ focus-tab --match title:claude-todo
```

## Integration mit Todo-System

### Remote Control Extension
```php
// In Remote_Control class
public function switch_project($project_name) {
    $command = "kitty @ focus-tab --match title:claude-$project_name";
    shell_exec($command);
}

public function resize_layout($left_percent) {
    $right_percent = 100 - $left_percent;
    $increment = 50 - $left_percent; // From 50/50 baseline
    $command = "kitty @ resize-window --match num:0 --axis horizontal --increment -$increment";
    shell_exec($command);
}
```

### Dashboard UI Addition
```html
<!-- Project Switcher -->
<select id="claude-project-switcher">
    <option value="todo">TODO Plugin</option>
    <option value="forex">ForexSignale</option>
    <option value="trading">Trading Bot</option>
</select>

<!-- Layout Control -->
<input type="range" id="layout-slider" min="10" max="90" value="50">
<button onclick="applyLayout()">Apply Layout</button>
```

## Nächste Schritte

1. **Test Kitty Remote Control**
   ```bash
   kitty @ ls  # Liste alle Windows
   kitty @ resize-window --help  # Siehe Optionen
   ```

2. **Implementiere Project Switcher**
   - Erstelle manage-sessions.sh Script
   - Teste mit 2-3 Projekten

3. **Integriere in Todo Dashboard**
   - Add Project Dropdown
   - Add Layout Slider
   - AJAX calls zu Remote Control

## Vorteile dieser Lösung

✅ **Projekt-Isolation:** Jedes Projekt hat eigene Session
✅ **Kontext-Bewahrung:** CLAUDE.md automatisch geladen
✅ **Flexible Layouts:** Dynamische Fenster-Größen
✅ **Remote Control:** Alles via SSH steuerbar
✅ **Dashboard Integration:** UI Controls im WordPress

---

**Status:** Konzept bereit zur Implementierung
**Priorität:** Mittel
**Aufwand:** 2-3 Stunden