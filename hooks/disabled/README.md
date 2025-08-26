# Deaktivierte Hook-Dateien

Diese Dateien wurden am 24.08.2025 deaktiviert, da sie Probleme in anderen Claude Code Projekten verursacht haben.

## Deaktivierte Dateien:
- `consistency_validator.py.disabled` - Hook der Sessions blockiert hat
- `clear_violations.sh.disabled` - Script zum Zurücksetzen von Violations
- `task_context.json` - Kontext-Datei die Violations getrackt hat

## Problem:
Die Hooks haben in anderen Projekten Fehlermeldungen zu "validity consistor" verursacht und Sessions blockiert.

## Reaktivierung:
Falls diese Hooks wieder benötigt werden, können sie zurück nach `/hooks/` verschoben und umbenannt werden (`.disabled` entfernen).

## Deaktiviert von: Claude am 24.08.2025