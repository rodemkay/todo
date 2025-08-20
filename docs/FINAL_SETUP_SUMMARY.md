# 🎯 TODO PROJECT - FINALE SETUP ZUSAMMENFASSUNG

## ✅ WAS WURDE ERLEDIGT

### 1. Projekt-Reorganisation
- **Neues Hauptverzeichnis:** `/home/rodemkay/www/react/todo/`
- **Projektname:** `todo` (nicht mehr wp-project-todos)
- **Plugin-Pfad NEU:** `/var/www/forexsignale/staging/wp-content/plugins/todo/`

### 2. Environment-Konfiguration
- **.env Datei** mit allen Passwörtern und IPs erstellt
- **Tailscale IPs** dokumentiert (niemals localhost verwenden!)
- **Mount Points** korrekt dokumentiert
- **SSH Zugänge** für beide Server

### 3. Dokumentation
- **CLAUDE.md** - Spezifische Instruktionen für Claude
- **ENVIRONMENT.md** - Komplette Infrastruktur-Dokumentation
- **IMPLEMENTATION_PLAN.md** - Detaillierter Implementationsplan
- **CURRENT_STATUS.md** - Aktueller Projekt-Status

### 4. Git Repository
- **Lokal initialisiert** mit 3 Commits
- **GitHub vorbereitet** (muss noch manuell erstellt werden)
- **.gitignore** schützt sensitive Daten

## 🚨 KRITISCHE INFORMATIONEN

### Claude Session
```bash
# Start-Script
/home/rodemkay/.local/bin/kitty_claude_fresh.sh

# Session
tmux session "claude" - LINKES PANE!

# Befehle kommen im LINKEN PANE an
# Das ist für ./todo KRITISCH!
```

### Server IPs (IMMER Tailscale verwenden!)
- **Ryzen:** 100.89.207.122
- **Hetzner:** 100.67.210.46
- **NIE localhost verwenden!**

### Passwörter (alle in .env)
- **Hetzner:** rodemkay / .Zynit333doka? (SSH & sudo)
- **Ryzen:** rodemkay / 110201 (user & sudo)
- **WordPress:** ForexSignale / .Foret333doka?
- **MySQL:** ForexSignale / @C3e!S5t#Q7p*V8g

## 🚀 NÄCHSTE SCHRITTE

### 1. Deploy zum Hetzner Server
```bash
cd /home/rodemkay/www/react/todo
./scripts/deploy.sh staging
```

### 2. Plugin auf Hetzner aktivieren
```bash
ssh rodemkay@100.67.210.46
cd /var/www/forexsignale/staging
wp plugin deactivate wp-project-todos
wp plugin activate todo
```

### 3. GitHub Repository
1. Manuell auf github.com/new erstellen
2. Name: `todo`
3. KEINE README/License hinzufügen
4. Dann pushen:
```bash
git push -u origin main
```

### 4. Dashboard UI Fixes implementieren
- Claude Toggle als Individual-Buttons (❌/✓ pro Zeile)
- Working Directory Dropdown
- Save ohne Redirect
- CRON Integration

## 📁 PROJEKT-STRUKTUR

```
/home/rodemkay/www/react/todo/
├── .env                 # Alle Passwörter & IPs
├── .gitignore           # Schützt sensitive Daten
├── CLAUDE.md            # Claude-Instruktionen
├── README.md            # Projekt-Übersicht
├── package.json         # NPM Scripts
├── todo -> cli/todo     # Symlink zum CLI Tool
├── docs/                # Dokumentation
│   ├── screenshots/     # UI-Referenzbilder
│   ├── ENVIRONMENT.md   # Infrastruktur-Doku
│   └── IMPLEMENTATION_PLAN.md
├── plugin/              # WordPress Plugin Code
├── hooks/               # Hook System
├── cli/                 # CLI Tools
├── tests/               # Playwright Tests
└── scripts/             # Deploy Scripts
```

## 🔄 WORKFLOW

### Development Workflow
1. **Entwickeln** auf Ryzen im todo/ Verzeichnis
2. **Testen** mit Playwright lokal
3. **Deployen** mit `./scripts/deploy.sh staging`
4. **Verifizieren** auf https://forexsignale.trade/staging/wp-admin

### ./todo Workflow
1. Button im WordPress klickt → 
2. Webhook Server empfängt → 
3. Befehl kommt im LINKEN PANE an →
4. Claude führt aus →
5. TASK_COMPLETED signal

## 📊 STATUS

### Erledigt ✅
- Verzeichnisstruktur
- Environment-Konfiguration
- Dokumentation
- Git Repository (lokal)
- Deploy Scripts
- CLI Tools

### Ausstehend ⏳
- Deploy zu Hetzner
- Plugin aktivieren
- GitHub Push
- UI Fixes implementieren
- Testing

## 📝 WICHTIGE HINWEISE

1. **IMMER Tailscale IPs verwenden** (nie localhost)
2. **Befehle kommen im LINKEN PANE an** (kritisch für ./todo)
3. **Nur im /staging/ arbeiten** auf Hetzner
4. **Plugin heißt jetzt `todo`** nicht wp-project-todos
5. **.env niemals committen** (ist in .gitignore)

---

**Projekt bereit für Deployment!**  
**Datum:** 2025-08-20  
**Zeit:** 17:45 Uhr