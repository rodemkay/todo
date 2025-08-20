# 📋 TODO - Task Management System

Ein WordPress Plugin für professionelles Task-Management mit Claude CLI Integration.

## 🚀 Features

- **WSJ-Style Dashboard** - Modernes, übersichtliches Design
- **Claude Integration** - Direkte Task-Bearbeitung durch Claude
- **Multi-Agent System** - Parallele Bearbeitung mit bis zu 30 Agents
- **MCP Server Support** - Integration verschiedener MCP Server
- **CRON Tasks** - Wiederkehrende Aufgaben automatisiert
- **Hook System** - Automatisierung via CLI

## 📂 Struktur

```
todo/
├── docs/           # Dokumentation
├── plugin/         # WordPress Plugin Code
├── hooks/          # Hook System
├── cli/            # CLI Tools
├── tests/          # Playwright Tests
└── scripts/        # Utility Scripts
```

## 🔧 Installation

### 1. Repository klonen
```bash
git clone https://github.com/rodemkay/todo.git
cd todo
```

### 2. Plugin installieren
```bash
# Sync zum WordPress Server
./scripts/deploy.sh staging
```

### 3. Plugin aktivieren
Im WordPress Admin unter Plugins → TODO aktivieren

## 💻 CLI Verwendung

```bash
# Nächstes Todo laden
./todo

# Spezifisches Todo laden
./todo -id 67

# Todo abschließen
./todo complete

# Alle Todos anzeigen
./todo list
```

## 🧪 Testing

```bash
# Playwright Tests ausführen
npm test

# Einzelnen Test ausführen
npm test dashboard
```

## 📝 Dokumentation

- [Implementationsplan](docs/IMPLEMENTATION_PLAN.md)
- [Architektur](docs/ARCHITECTURE.md)
- [API Referenz](docs/API_REFERENCE.md)
- [Screenshots](docs/screenshots/)

## 🚀 Deployment

### Staging
```bash
./scripts/deploy.sh staging
```

### Production
```bash
./scripts/deploy.sh production
```

## 📊 Status

- ✅ Verzeichnisstruktur
- ⏳ Claude Toggle Implementation
- ⏳ Working Directory Dropdown
- ⏳ Save ohne Redirect
- ⏳ CRON Integration
- ⏳ Hook System Stabilisierung

## 📞 Support

Bei Fragen oder Problemen: [Issues](https://github.com/rodemkay/todo/issues)

## 📜 Lizenz

Proprietary - Alle Rechte vorbehalten

---

**Version:** 1.0.0  
**Letzte Aktualisierung:** 2025-08-20