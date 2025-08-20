#!/bin/bash
# Pre-Task-Completed Hook
# Dieses Script wird VOR dem Abschluss eines Tasks ausgeführt
# Es stellt sicher, dass Claude eine Zusammenfassung geschrieben hat

CURRENT_TODO_ID=${CURRENT_TODO_ID:-""}

if [ -z "$CURRENT_TODO_ID" ]; then
    echo "⚠️  Keine CURRENT_TODO_ID gesetzt"
    exit 0
fi

# Prüfe ob claude_notes oder claude_output existiert
NOTES=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"SELECT claude_notes FROM stage_project_todos WHERE id = $CURRENT_TODO_ID\" --skip-column-names")
OUTPUT=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"SELECT claude_output FROM stage_project_todos WHERE id = $CURRENT_TODO_ID\" --skip-column-names")

if [ -z "$NOTES" ] && [ -z "$OUTPUT" ]; then
    echo "❌ WARNUNG: Keine Claude-Zusammenfassung für Task #$CURRENT_TODO_ID gefunden!"
    echo "Bitte füge eine Zusammenfassung hinzu bevor du den Task abschließt."
    
    # Erstelle automatische Zusammenfassung wenn keine vorhanden
    DEFAULT_SUMMARY="Task #$CURRENT_TODO_ID wurde bearbeitet. (Automatisch generiert - keine manuelle Zusammenfassung erstellt)"
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"UPDATE stage_project_todos SET claude_notes = '$DEFAULT_SUMMARY' WHERE id = $CURRENT_TODO_ID\""
    echo "✅ Standard-Zusammenfassung wurde hinzugefügt"
fi

echo "✅ Pre-Task-Completed Hook erfolgreich"