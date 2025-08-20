# WP Project Todos Plugin - Changelog

## Version 1.3.0 - 18.08.2025

### 🚀 Neue Features

#### 1. **Automatische Claude-Aktivierung**
- Checkbox "Von Claude bearbeiten" ist jetzt standardmäßig aktiviert
- Spart Zeit bei der Erstellung neuer Aufgaben

#### 2. **Intelligente Sortierung**
- Abgeschlossene Aufgaben werden automatisch nach unten sortiert
- Offene Aufgaben bleiben oben für bessere Übersicht

#### 3. **Direktes Bearbeiten**
- Titel ist jetzt direkt klickbar zum Bearbeiten
- Bearbeiten-Button wurde entfernt (redundant)

#### 4. **Status-Filter**
- Neue Filter-Buttons: Alle, Ausstehend, In Bearbeitung, Abgeschlossen, Blockiert
- Schnelle Filterung ohne Seiten-Reload

#### 5. **Massenaktionen**
- Checkboxen für jede Aufgabe
- Bulk-Actions: Löschen, Status ändern, Claude aktivieren/deaktivieren
- Alle auswählen/abwählen Funktion

#### 6. **Bereich-Buttons**
- Scope-Auswahl als klickbare Buttons statt Dropdown
- Bessere Usability auf Mobile und Desktop

#### 7. **Beschreibungs-Vorschau**
- Erste 20 Wörter der Beschreibung unter dem Titel
- Graue, kursive Darstellung für bessere Lesbarkeit

#### 8. **Wiederkehrende Aufgaben**
- Neue Datenbank-Struktur (is_recurring, recurring_type, last_executed)
- UI-Section mit Karten-Layout
- 4 vordefinierte Aufgaben:
  - Session-Dokumentation erstellen
  - Änderungs-Detektor
  - Plugin-Änderungen dokumentieren
  - .env Updates prüfen
- Ausführen erstellt Kopie als normale Aufgabe

### 🐛 Bug Fixes
- Output-Button zeigt jetzt korrekt claude_notes und bemerkungen an
- Remote Control Nonce-Probleme behoben

### 📁 Neue Dateien
- `scripts/documentation_template.sh` - Auto-Dokumentations-Script
- `scripts/change_detector.sh` - Änderungs-Detektor für wichtige Dateien

### 💾 Datenbank-Änderungen
```sql
ALTER TABLE wp_project_todos 
ADD COLUMN is_recurring TINYINT(1) DEFAULT 0,
ADD COLUMN recurring_type VARCHAR(50) DEFAULT NULL,
ADD COLUMN last_executed DATETIME DEFAULT NULL,
ADD INDEX idx_recurring (is_recurring, recurring_type);
```

### 📝 Geänderte Dateien
- `includes/class-admin.php` - Hauptänderungen für UI und Features
- `includes/class-todo-model.php` - Sortierungs-Logik
- `includes/class-remote-control.php` - Remote Control Verbesserungen
- `admin/js/remote-control.js` - JavaScript für Remote Control

### 🔧 Technische Details
- Filter nutzen GET-Parameter `filter_status`
- Bulk-Actions via POST mit `bulk_action` und `todo_ids[]`
- Scope-Buttons mit Radio-Inputs und JavaScript
- Wiederkehrende Aufgaben bleiben persistent

---

## Installation der neuen Features
1. Plugin ist bereits aktiv auf forexsignale.trade/staging
2. Datenbank-Änderungen wurden automatisch angewendet
3. Keine weiteren Schritte erforderlich

## Verwendung
- **Filter:** Klicke auf Status-Buttons zum Filtern
- **Massenaktionen:** Wähle Aufgaben aus und nutze Dropdown
- **Wiederkehrende:** Klicke "Ausführen" bei dauerhaften Aufgaben
- **Scope:** Klicke direkt auf gewünschten Bereich

---

Entwickelt von Claude mit ❤️ für effizientes Projekt-Management
