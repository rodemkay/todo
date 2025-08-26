# 📋 TODO - WordPress Task Management Plugin

Ein fortschrittliches Task-Management-System für WordPress mit Claude Code CLI Integration.

## 🚀 Features

- **WordPress Integration**: Nahtlose Integration in WordPress Admin Dashboard
- **Claude Code CLI Support**: Direkte Verbindung zu Claude Code über Webhooks
- **Remote Control**: Tasks können remote über Buttons im WordPress Admin ausgelöst werden
- **Hook System**: Automatisierte Workflows mit Hook-Integration
- **WSJ-Style Dashboard**: Professionelles Design im Wall Street Journal Stil

## 📂 Projekt-Struktur

```
todo/
├── cli/               # Command Line Interface Tools
├── docs/              # Dokumentation und Screenshots
├── hooks/             # Hook System für Automatisierung
├── plugin/            # WordPress Plugin Code
├── scripts/           # Utility Scripts
└── tests/            # Playwright Tests
```

## 🛠️ Installation

### 1. Plugin zu WordPress hinzufügen

```bash
# Plugin zum WordPress Staging kopieren
rsync -avz plugin/ rodemkay@159.69.157.54:/var/www/forexsignale/staging/wp-content/plugins/todo/
```

### 2. Plugin aktivieren

Im WordPress Admin unter Plugins → TODO aktivieren.

### 3. CLI Tool einrichten

```bash
# CLI Tool ausführbar machen
chmod +x cli/todo

# Symlink erstellen (optional)
ln -s /home/rodemkay/www/react/plugin-todo/cli/todo ~/bin/todo
```

## 🖥️ Verwendung

### WordPress Admin

- **Dashboard**: `Einstellungen → TODO Dashboard`
- **Neue Aufgabe**: Button "Neue Aufgabe" im Dashboard
- **Remote Control**: "An Claude" Button für direkte Ausführung

### CLI Commands

```bash
# Nächstes Todo laden
./todo

# Spezifisches Todo laden
./todo -id 67

# Todo abschließen
./todo complete

# Alle Todos anzeigen
./todo list

# Status anzeigen
./todo status
```

## 🔧 Konfiguration

### Webhook Server

Der Webhook Server läuft auf Port 8089 und empfängt Befehle vom WordPress Plugin:

```bash
# Webhook Server Status prüfen
ps aux | grep webhook
```

### Hook System

Das Hook System ermöglicht automatisierte Workflows:

- **Pre-Task Hook**: Vor Task-Ausführung
- **Post-Task Hook**: Nach Task-Abschluss
- **Status Changed Hook**: Bei Status-Änderungen

## 🧪 Testing

```bash
# Playwright Tests ausführen
npm test

# Spezifischen Test ausführen
npx playwright test tests/dashboard.spec.js
```

## 📝 Dokumentation

Weitere Dokumentation finden Sie im `docs/` Verzeichnis:

- `IMPLEMENTATION_PLAN.md` - Detaillierter Implementierungsplan
- `infrastructure.md` - Infrastruktur-Übersicht
- `screenshots/` - UI Referenzbilder

## 🤝 Contributing

1. Fork das Repository
2. Erstelle einen Feature Branch
3. Committe deine Änderungen
4. Push zum Branch
5. Erstelle einen Pull Request

## 📄 Lizenz

Proprietär - Alle Rechte vorbehalten

## 👤 Autor

**Maik von ForexSignale.trade**

## 🆘 Support

Bei Fragen oder Problemen:
- GitHub Issues erstellen
- Email an support@forexsignale.trade

---

**Version:** 1.0.0  
**Letzte Aktualisierung:** 2025-08-20
