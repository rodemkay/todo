# Agent Output Management System

## Übersicht
Dieses Verzeichnis enthält die gespeicherten Analysen und Outputs von Subagents für das Todo-System.

## Struktur
```
agent-outputs/
├── todo-{ID}/                  # Ein Ordner pro Todo
│   ├── {AGENT_NAME}_{TIMESTAMP}.md   # Agent-Output Dateien
│   └── ...
└── README.md                    # Diese Datei
```

## Verwendung
Wenn das "Agent Output Management" für ein Todo aktiviert ist:
1. Subagents speichern ihre vollständigen Analysen als .md Dateien
2. Dateien werden automatisch im Ordner `todo-{ID}/` organisiert
3. Bei Löschung des Todos werden auch die Outputs gelöscht
4. Outputs können über die Web-Oberfläche eingesehen werden

## Automatische Bereinigung
- Outputs werden automatisch gelöscht, wenn das zugehörige Todo gelöscht wird
- Vor der Löschung wird ein Backup im Archive-Ordner erstellt

## Dateiformat
Alle Agent-Outputs folgen diesem Format:
- Dateiname: `{AGENT_NAME}_{YYYYMMDD_HHMMSS}.md`
- Inhalt: Strukturiertes Markdown mit vollständiger Analyse

## Größenbeschränkung
- Max. 10MB pro Datei
- Nur .md Dateien erlaubt

---
*Generiert vom Agent Output Management System V3.0*