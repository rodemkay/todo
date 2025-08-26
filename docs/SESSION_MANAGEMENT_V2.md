# 🎯 SESSION MANAGEMENT V2 - Dokumentation

## 📋 Übersicht

Das neue Session-Management-System löst folgende Probleme:
1. **Kein störender `echo tmux-health-test` mehr** ✅
2. **Automatisches Session-Switching** nach Projekt
3. **Multi-Terminal Management** mit Kitty
4. **Intelligente Session-Erstellung** bei Bedarf

## 🔧 Was wurde geändert?

### 1. Health-Check entfernt
**Problem:** Der alte Health-Check sendete ständig `echo tmux-health-test` in deine aktive Session
**Lösung:** Health-Check prüft jetzt nur noch ob Session existiert und Panes hat, OHNE Befehle zu senden

### 2. Neuer tmux-controller-v2.sh
- Keine störenden Tests mehr
- Intelligentes Session-Management
- Automatische Kitty-Integration
- Projekt-basierte Konfiguration

### 3. Session-Konfigurationen

```bash
# Projekt-Konfigurationen
PROJECT_CONFIGS=(
    ["plugin-todo"]="plugin-todo:90:10"      # 90% Claude, 10% Monitor
    ["forexsignale"]="forexsignale:85:15"    # 85% Claude, 15% Monitor
    ["article-builder"]="article-builder:80:20"
    ["trading-bot"]="trading-bot:75:25"
)
```

## 🚀 Verwendung

### Manuelles Session-Management

```bash
# Session prüfen (ohne störenden Test!)
./tmux-controller-v2.sh check claude

# Session-Health (ohne echo-Test!)
./tmux-controller-v2.sh health claude

# Neue Session erstellen
./tmux-controller-v2.sh create plugin-todo

# Zu Projekt-Session wechseln
./tmux-controller-v2.sh switch forexsignale

# Alle Sessions anzeigen
./tmux-controller-v2.sh list

# Session beenden
./tmux-controller-v2.sh kill old-session
```

### Automatisches Session-Switching

Wenn du `./todo` aufrufst, wird automatisch:
1. Das aktuelle Projekt erkannt
2. Geprüft ob die richtige Session läuft
3. Bei Bedarf zur richtigen Session gewechselt
4. Oder eine neue Session erstellt

## 🎨 Session-Layout

Jede Projekt-Session wird mit Kitty erstellt:
- **Linkes Pane (90%):** Claude Code CLI
- **Rechtes Pane (10%):** Monitoring/Logs
- **Automatischer Pfad:** Projekt-Verzeichnis
- **Persistente Konfiguration:** In `~/.config/kitty/sessions/`

## 📝 Workflow

### Szenario 1: Wechsel zwischen Projekten
```bash
# Aktuell in plugin-todo
cd /home/rodemkay/www/react/plugin-todo
./todo  # Bleibt in plugin-todo Session

# Wechsel zu forexsignale
cd /home/rodemkay/www/react/forexsignale-magazine
./todo  # Wechselt automatisch zu forexsignale Session
```

### Szenario 2: Neue Session benötigt
```bash
cd /home/rodemkay/www/react/new-project
./todo  # Erstellt automatisch neue Session für new-project
```

## 🛠️ Konfiguration

### Projekt hinzufügen

In `tmux-controller-v2.sh`:
```bash
PROJECT_CONFIGS=(
    ["plugin-todo"]="plugin-todo:90:10"
    ["neues-projekt"]="neues-projekt:85:15"  # Neu hinzugefügt
)
```

### Session-Defaults anpassen

In `~/.config/kitty/sessions/`:
- Jedes Projekt bekommt eigene `.conf` Datei
- Layout und Startbefehle anpassbar
- Persistente Einstellungen

## ⚠️ Wichtige Hinweise

1. **Kein `echo tmux-health-test` mehr!** Der störende Test wurde komplett entfernt
2. **Sessions bleiben offen** - Mehrere Terminals gleichzeitig sind gewollt
3. **Fokus-Wechsel** erfolgt automatisch mit `wmctrl` oder `xdotool`
4. **Claude startet automatisch** im linken Pane jeder neuen Session

## 🔍 Troubleshooting

### Session reagiert nicht
```bash
# Health-Check (ohne störenden Test)
./tmux-controller-v2.sh health claude

# Bei Problemen: Session neu starten
./tmux-controller-v2.sh kill claude
./tmux-controller-v2.sh create claude plugin-todo
```

### Falsches Projekt erkannt
```bash
# Projekt manuell setzen
./claude-switch.sh switch plugin-todo
```

### Kitty startet nicht
```bash
# Prüfe Kitty-Konfiguration
ls -la ~/.config/kitty/sessions/

# Manuelle Session-Erstellung
kitty --session ~/.config/kitty/sessions/plugin-todo.conf
```

## 📊 Status

✅ **Gelöst:** Störender `echo tmux-health-test` entfernt
✅ **Implementiert:** Neuer tmux-controller-v2.sh ohne Health-Test  
✅ **Funktioniert:** Session-Check prüft nur Existenz, nicht Responsiveness
🔄 **In Arbeit:** Automatisches Session-Switching bei Projektwechsel
📋 **Geplant:** GUI für Session-Management

---

**Version:** 2.0
**Datum:** 2025-01-25
**Status:** AKTIV - Störender Health-Check entfernt!