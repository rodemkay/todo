# 🎯 AUTOMATISCHES SESSION-MANAGEMENT FÜR TODOS

## 📋 Das Problem (gelöst!)
1. **Störender Health-Check:** `echo tmux-health-test` wurde ständig gesendet ✅ BEHOBEN
2. **Manuelles Session-Switching:** Man musste selbst die richtige Session finden
3. **Projekt-Kontext verloren:** TODOs aus anderen Projekten liefen in falscher Umgebung

## ✅ Die Lösung: Automatisches Session-Management

### Was passiert jetzt automatisch?

Wenn ein TODO geladen wird (egal ob durch Button, Auto-Continue oder manuell):

```
TODO wird geladen
    ↓
System liest Projekt aus TODO-Daten
    ↓
System prüft: Sind wir in der richtigen Session?
    ↓
NEIN → System wechselt automatisch zur Projekt-Session
       (oder erstellt neue, falls nicht vorhanden)
    ↓
JA → TODO wird direkt ausgeführt
```

## 🔄 PRAKTISCHES BEISPIEL

### Szenario: Du arbeitest am TODO-Plugin, ein ForexSignale-TODO kommt rein

```bash
# Du arbeitest gerade:
# Session: plugin-todo
# Verzeichnis: /home/rodemkay/www/react/plugin-todo/

# Ein TODO aus ForexSignale wird geladen (z.B. durch Remote-Button):
./todo -id 250  # (TODO #250 gehört zu ForexSignale)

# Was passiert automatisch:
🔄 SESSION-SWITCHING für TODO #250
📁 Projekt: ForexSignale
🎯 Ziel-Session: forexsignale
📍 Aktuelle Session: plugin-todo
🔄 Wechsle zu bestehender Session: forexsignale
✅ Fenster fokussiert: forexsignale

# Du bist jetzt automatisch im ForexSignale-Fenster!
# Das TODO wird dort ausgeführt
```

### Nach Abschluss:

```bash
# TODO #250 abgeschlossen, nächstes TODO wird geladen
# TODO #251 gehört wieder zu plugin-todo

# Automatisch:
🔄 SESSION-SWITCHING für TODO #251
📁 Projekt: plugin-todo
🎯 Ziel-Session: plugin-todo
📍 Aktuelle Session: forexsignale
🔄 Wechsle zu bestehender Session: plugin-todo
✅ Fenster fokussiert: plugin-todo

# Du bist automatisch zurück im TODO-Plugin Fenster!
```

## 🎨 WIE ERKENNT DAS SYSTEM DAS PROJEKT?

Das System prüft in dieser Reihenfolge:

1. **`scope`** Feld im TODO (z.B. "ForexSignale", "Todo-Plugin")
2. **`project_name`** Feld im TODO
3. **`working_directory`** Feld (extrahiert Projekt aus Pfad)
4. **Fallback:** plugin-todo (Standard)

## 📂 PROJEKT-MAPPINGS

```python
# Projekt-Namen → Session-Namen
PROJECT_SESSION_MAP = {
    'todo-plugin': 'plugin-todo',
    'Todo-Plugin': 'plugin-todo',
    'ForexSignale': 'forexsignale',
    'Article-Builder': 'article-builder',
    'Trading-Bot': 'trading-bot',
    # ...
}

# Session-Namen → Verzeichnisse
PROJECT_DIR_MAP = {
    'plugin-todo': '/home/rodemkay/www/react/plugin-todo',
    'forexsignale': '/home/rodemkay/www/react/forexsignale-magazine',
    'article-builder': '/home/rodemkay/www/react/plugin-article',
    # ...
}
```

## 🚀 NEUE SESSION WIRD AUTOMATISCH ERSTELLT

Wenn ein TODO für ein neues Projekt kommt:

```bash
# TODO für neues Projekt "mein-ai-tool"
./todo -id 500

# System:
🔄 SESSION-SWITCHING für TODO #500
📁 Projekt: mein-ai-tool
🎯 Ziel-Session: mein-ai-tool
📍 Aktuelle Session: plugin-todo
⚠️ Session mein-ai-tool existiert nicht
🚀 Erstelle neue Session: mein-ai-tool in /home/rodemkay/www/react/mein-ai-tool

# Neues Kitty-Fenster öffnet sich automatisch!
# - Titel: "mein-ai-tool"
# - Claude startet im linken Pane (90%)
# - Monitor im rechten Pane (10%)
# - Working directory ist gesetzt
```

## 🎯 VORTEILE

1. **Kein manuelles Switching mehr** - System erkennt automatisch das richtige Projekt
2. **Kontext bleibt erhalten** - Jedes Projekt läuft in seiner eigenen Umgebung
3. **Parallele Arbeit** - Mehrere Projekte können gleichzeitig laufen
4. **Automatische Session-Erstellung** - Neue Projekte bekommen automatisch Sessions
5. **Fokus-Management** - Das richtige Fenster wird automatisch fokussiert

## 🔧 KONFIGURATION ERWEITERN

### Neues Projekt hinzufügen

In `/home/rodemkay/www/react/plugin-todo/hooks/session_switcher.py`:

```python
# Projekt-Mapping erweitern
PROJECT_SESSION_MAP = {
    # ...
    'mein-neues-projekt': 'mein-projekt-session',
}

PROJECT_DIR_MAP = {
    # ...
    'mein-projekt-session': '/home/rodemkay/www/react/mein-neues-projekt',
}
```

## 🐛 TROUBLESHOOTING

### Session wird nicht gewechselt?

```bash
# Prüfe ob Session existiert
tmux ls

# Prüfe ob Fokus-Tools installiert sind
which wmctrl    # oder
which xdotool

# Manuell zur Session wechseln (Fallback)
tmux attach -t session-name
```

### Falsches Projekt erkannt?

```bash
# Prüfe TODO-Daten
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query 'SELECT id, scope, project_name, working_directory FROM stage_project_todos WHERE id = TODO_ID'"
```

### Session startet nicht?

```bash
# Manuell erstellen
./tmux-controller-v2.sh create projekt-name

# Oder direkt mit Kitty
kitty --session ~/.config/kitty/sessions/projekt-name.conf
```

## 📊 STATUS-ÜBERSICHT

| Feature | Status | Beschreibung |
|---------|--------|--------------|
| Health-Check entfernt | ✅ | Kein `echo tmux-health-test` mehr |
| Auto-Session-Switch | ✅ | Automatisch zur richtigen Session |
| Session-Erstellung | ✅ | Neue Sessions werden automatisch erstellt |
| Fokus-Management | ✅ | Fenster wird automatisch fokussiert |
| Multi-Projekt | ✅ | Beliebig viele parallele Sessions |
| Projekt-Erkennung | ✅ | Aus TODO-Daten extrahiert |

## 🎬 ZUSAMMENFASSUNG

**Vorher:**
- Manuell zur richtigen Session wechseln
- Falscher Kontext bei Projekt-TODOs
- Störender Health-Check

**Jetzt:**
- TODO wird geladen → Richtiges Fenster öffnet sich automatisch
- Jedes Projekt in eigener Umgebung
- Kein manuelles Eingreifen nötig
- Kein störender Health-Check mehr!

---

**Das System ist AKTIV und funktioniert ab sofort automatisch!**