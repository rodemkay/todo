# 🚀 SESSION MANAGEMENT - PRAKTISCHE ANLEITUNG

## 📝 Wie funktioniert es bei einem NEUEN PROJEKT?

### Szenario: Du startest ein neues Projekt "mein-trading-bot"

## Option 1: AUTOMATISCH (Empfohlen)

```bash
# 1. Projekt-Ordner erstellen
cd /home/rodemkay/www/react
mkdir mein-trading-bot
cd mein-trading-bot

# 2. Einfach ./todo aufrufen
./todo

# Was passiert automatisch:
# ✅ System erkennt: "Wir sind in mein-trading-bot"
# ✅ System prüft: "Gibt es eine mein-trading-bot Session?" → NEIN
# ✅ System erstellt: Neue Kitty-Session "mein-trading-bot"
# ✅ System startet: Claude im linken Pane (90%)
# ✅ System öffnet: Monitor im rechten Pane (10%)
# ✅ Du arbeitest: In der neuen Session
```

## Option 2: MANUELL (Mehr Kontrolle)

```bash
# 1. Session explizit erstellen
./tmux-controller-v2.sh create mein-trading-bot

# 2. Session wird geöffnet:
# - Neues Kitty-Fenster erscheint
# - Titel: "mein-trading-bot"
# - Claude startet automatisch links
# - Rechts ist Monitor-Pane

# 3. In der neuen Session arbeiten
cd /home/rodemkay/www/react/mein-trading-bot
./todo
```

## 🎯 PRAKTISCHES BEISPIEL: Multi-Projekt-Workflow

### Vormittag: Trading-Bot entwickeln
```bash
cd ~/www/react/trading-bot
./todo -id 100  # Lädt Todo #100

# System:
# → Erkennt: Projekt "trading-bot"
# → Prüft: Session "trading-bot" existiert? 
# → JA: Bleibt in Session
# → NEIN: Erstellt neue Session
```

### Mittag: Quick-Fix im TODO-Plugin
```bash
cd ~/www/react/plugin-todo
./todo -id 365  # Lädt Todo #365

# System:
# → Erkennt: Projekt "plugin-todo"
# → Prüft: Session "plugin-todo" existiert?
# → JA: WECHSELT zu plugin-todo Session (anderes Kitty-Fenster)
# → NEIN: Erstellt neue Session
```

### Nachmittag: Zurück zum Trading-Bot
```bash
cd ~/www/react/trading-bot
./todo  # Weiter arbeiten

# System:
# → Erkennt: Projekt "trading-bot"
# → Session existiert bereits
# → WECHSELT zurück zu trading-bot Session
# → Deine Arbeit ist noch da!
```

## 📂 WAS PASSIERT IM HINTERGRUND?

### 1. Bei Session-Erstellung:

```
~/.config/kitty/sessions/
├── plugin-todo.conf        # Kitty-Config für TODO-Plugin
├── trading-bot.conf        # Kitty-Config für Trading-Bot
└── mein-neues-projekt.conf # Automatisch erstellt!
```

Inhalt einer `.conf` Datei:
```bash
# Session für mein-trading-bot
cd /home/rodemkay/www/react/mein-trading-bot

# Neues Tab mit tmux Session
launch --type=tab --tab-title="mein-trading-bot" sh -c "tmux new-session -s mein-trading-bot"

# Layout mit zwei Panes (90/10 Split)
layout splits
launch --location=hsplit --title="Claude" sh -c "cd /home/rodemkay/www/react/mein-trading-bot && claude"
launch --location=vsplit --title="Monitor" sh -c "cd /home/rodemkay/www/react/mein-trading-bot && exec bash"
```

### 2. Sessions parallel:

```bash
# Alle aktiven Sessions anzeigen
tmux ls

# Output:
plugin-todo: 2 windows (created Sat Jan 25 10:00:00 2025)
trading-bot: 2 windows (created Sat Jan 25 11:00:00 2025)
mein-trading-bot: 2 windows (created Sat Jan 25 14:00:00 2025)
```

## 🎨 VISUELLE DARSTELLUNG

```
┌─────────────────────────────────────────────────────┐
│                  KITTY TERMINAL 1                    │
│              Session: plugin-todo                    │
├─────────────────────────────────┬───────────────────┤
│                                 │                   │
│        CLAUDE (90%)             │  MONITOR (10%)    │
│                                 │                   │
│  > ./todo -id 365               │  [Logs]           │
│  Arbeite an TODO-Plugin...      │                   │
│                                 │                   │
└─────────────────────────────────┴───────────────────┘

┌─────────────────────────────────────────────────────┐
│                  KITTY TERMINAL 2                    │
│              Session: trading-bot                    │
├─────────────────────────────────┬───────────────────┤
│                                 │                   │
│        CLAUDE (90%)             │  MONITOR (10%)    │
│                                 │                   │
│  > Entwickle Trading-Strategie  │  [Performance]    │
│                                 │                   │
└─────────────────────────────────┴───────────────────┘

┌─────────────────────────────────────────────────────┐
│                  KITTY TERMINAL 3                    │
│           Session: mein-neues-projekt                │
├─────────────────────────────────┬───────────────────┤
│                                 │                   │
│        CLAUDE (90%)             │  MONITOR (10%)    │
│                                 │                   │
│  > Starte neues Projekt...      │  [Status]         │
│                                 │                   │
└─────────────────────────────────┴───────────────────┘
```

## 🔧 PROJEKT-KONFIGURATION ANPASSEN

### Projekt mit speziellen Einstellungen hinzufügen:

In `tmux-controller-v2.sh` editieren:
```bash
declare -A PROJECT_CONFIGS=(
    ["plugin-todo"]="plugin-todo:90:10"
    ["trading-bot"]="trading-bot:85:15"
    ["mein-neues-projekt"]="mein-neues-projekt:80:20"  # 80% Claude, 20% Monitor
)
```

### Session-Defaults für Projekt:

```bash
# Erstelle custom Kitty-Config
cat > ~/.config/kitty/sessions/mein-projekt-custom.conf <<EOF
cd /home/rodemkay/www/react/mein-projekt

# Tab 1: Development
launch --type=tab --tab-title="Dev" sh -c "tmux new-session -s mein-projekt"
launch --location=hsplit sh -c "claude"
launch --location=vsplit sh -c "npm run dev"

# Tab 2: Testing
launch --type=tab --tab-title="Test" sh -c "npm test --watch"

# Tab 3: Logs
launch --type=tab --tab-title="Logs" sh -c "tail -f logs/*.log"
EOF
```

## 🎯 QUICK COMMANDS

```bash
# Session für aktuelles Verzeichnis erstellen
./tmux-controller-v2.sh create $(basename $PWD)

# Alle Sessions anzeigen
./tmux-controller-v2.sh list

# Zu Session wechseln
./tmux-controller-v2.sh switch projekt-name

# Session beenden
./tmux-controller-v2.sh kill projekt-name

# Health-Check (ohne störenden echo!)
./tmux-controller-v2.sh health projekt-name
```

## 💡 TIPPS

1. **Sessions bleiben bestehen** - Du kannst jederzeit zurückwechseln
2. **Keine Datenverlust** - Jede Session behält ihren Zustand
3. **Parallel arbeiten** - Mehrere Projekte gleichzeitig möglich
4. **Auto-Recovery** - Bei Absturz einfach Session neu verbinden

## ⚠️ WICHTIG

- **KEIN `echo tmux-health-test` mehr!** Das wurde komplett entfernt
- Sessions werden NICHT automatisch beendet
- Jedes Projekt = Eigene Session = Eigenes Kitty-Fenster
- Du kannst beliebig zwischen Sessions wechseln

## 🔍 DEBUGGING

```bash
# Wenn Session nicht startet
./tmux-controller-v2.sh check mein-projekt
# Output: Session mein-projekt existiert nicht

# Manuell erstellen
./tmux-controller-v2.sh create mein-projekt

# Wenn Kitty nicht öffnet
kitty --debug-gl  # Debug-Modus

# Session direkt verbinden
tmux attach -t mein-projekt
```

---

**Das ist die PRAKTISCHE Anwendung - einfach ins Projekt-Verzeichnis wechseln und `./todo` aufrufen!**