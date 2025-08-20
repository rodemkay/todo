# Claude Summary Workflow

## 🎯 Zweck
Stellt sicher, dass Claude IMMER eine Zusammenfassung schreibt, bevor ein Task abgeschlossen wird.

## 📝 Workflow für Claude

### 1. Task bearbeiten
```bash
./todo  # Task laden
# Arbeit am Task...
```

### 2. VOR Task-Abschluss - Zusammenfassung speichern
```bash
CURRENT_TODO_ID=123 ./save_summary.sh "Kurze Zusammenfassung" "Detaillierte technische Ausgabe"
```

### 3. Task abschließen
```bash
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
```

## 🔧 Komponenten

### save_summary.sh
- Helper-Script zum einfachen Speichern von Zusammenfassungen
- Parameter 1: Zusammenfassung (Pflicht)
- Parameter 2: Detaillierte Ausgabe (Optional)
- Escaped automatisch SQL-Sonderzeichen

### pre_task_completed.sh (Hook)
- Wird VOR Task-Abschluss ausgeführt
- Prüft ob claude_notes oder claude_output existiert
- Fügt Standard-Zusammenfassung hinzu wenn leer
- Verhindert, dass Tasks ohne Dokumentation abgeschlossen werden

### HTML-Ausgabe
- Zeigt Zusammenfassung in zwei Sektionen:
  - Oben: Ursprüngliche Anforderung
  - Unten: Claude's Zusammenfassung
- Wiedervorlage-Button für Fortsetzungen

## 📊 Datenbank-Felder

- **claude_notes**: Kurze Zusammenfassung (was wurde gemacht)
- **claude_output**: Technische Details (wie wurde es gemacht)
- **bemerkungen**: Manuelle Notizen vom User

## ✅ Best Practices

1. **IMMER** Zusammenfassung schreiben vor TASK_COMPLETED
2. **Strukturierte** Zusammenfassungen mit Aufzählungen
3. **Technische Details** in claude_output dokumentieren
4. **Geänderte Dateien** auflisten
5. **Nächste Schritte** erwähnen wenn relevant

## 🚨 Wichtig

- Ohne Zusammenfassung kann die Wiedervorlage-Funktion nicht richtig funktionieren
- Claude sieht bei Wiedervorlage nur, was in der DB gespeichert wurde
- Vollständige Dokumentation ermöglicht bessere Fortsetzungen

## 📌 Beispiel

```bash
# Task laden
./todo

# Arbeit erledigen...

# Zusammenfassung speichern
CURRENT_TODO_ID=112 ./save_summary.sh \
"Feature X implementiert:
- Component Y erstellt
- API-Endpoint hinzugefügt  
- Tests geschrieben" \
"TECHNISCHE DETAILS:
Datei: /path/to/file.php
- Funktion abc() hinzugefügt (Zeilen 10-50)
- Klasse XYZ erweitert"

# Task abschließen
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
```