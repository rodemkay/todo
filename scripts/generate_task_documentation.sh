#!/bin/bash
# V3.0: Automatische Dokumentations-Generierung nach Task-Abschluss

TODO_ID=$1
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DOC_DIR="/home/rodemkay/www/react/plugin-todo/documentation/completed-tasks"
DOC_FILE="$DOC_DIR/task_${TODO_ID}_${TIMESTAMP}.md"

# Erstelle Dokumentations-Verzeichnis falls nicht vorhanden
mkdir -p "$DOC_DIR"

# Hole Todo-Details aus der Datenbank
TODO_DATA=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"
SELECT id, title, description, status, priority, scope, 
       working_directory, agent_count, created_at, completed_date,
       claude_notes, claude_summary, plan_html
FROM stage_project_todos 
WHERE id=$TODO_ID
\" --format=json" 2>/dev/null)

if [ -z "$TODO_DATA" ] || [ "$TODO_DATA" = "[]" ]; then
    echo "❌ Todo #$TODO_ID nicht gefunden"
    exit 1
fi

# Parse JSON und erstelle Dokumentation
echo "# Task #$TODO_ID - Abschlussdokumentation" > "$DOC_FILE"
echo "" >> "$DOC_FILE"
echo "**Generiert am:** $(date '+%Y-%m-%d %H:%M:%S')" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"

# Extrahiere Felder aus JSON
TITLE=$(echo "$TODO_DATA" | jq -r '.[0].title')
DESCRIPTION=$(echo "$TODO_DATA" | jq -r '.[0].description')
STATUS=$(echo "$TODO_DATA" | jq -r '.[0].status')
PRIORITY=$(echo "$TODO_DATA" | jq -r '.[0].priority')
SCOPE=$(echo "$TODO_DATA" | jq -r '.[0].scope')
CREATED=$(echo "$TODO_DATA" | jq -r '.[0].created_at')
COMPLETED=$(echo "$TODO_DATA" | jq -r '.[0].completed_date')
SUMMARY=$(echo "$TODO_DATA" | jq -r '.[0].claude_summary')

echo "## 📋 Task-Details" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"
echo "- **Titel:** $TITLE" >> "$DOC_FILE"
echo "- **Status:** $STATUS" >> "$DOC_FILE"
echo "- **Priorität:** $PRIORITY" >> "$DOC_FILE"
echo "- **Scope:** $SCOPE" >> "$DOC_FILE"
echo "- **Erstellt:** $CREATED" >> "$DOC_FILE"
echo "- **Abgeschlossen:** $COMPLETED" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"

echo "## 📝 Beschreibung" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"
echo "$DESCRIPTION" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"

if [ "$SUMMARY" != "null" ] && [ ! -z "$SUMMARY" ]; then
    echo "## 🎯 Claude's Zusammenfassung" >> "$DOC_FILE"
    echo "" >> "$DOC_FILE"
    echo "$SUMMARY" >> "$DOC_FILE"
    echo "" >> "$DOC_FILE"
fi

# Prüfe auf Agent Outputs
AGENT_OUTPUT_DIR="/home/rodemkay/www/react/plugin-todo/agent-outputs/todo-$TODO_ID"
if [ -d "$AGENT_OUTPUT_DIR" ]; then
    OUTPUT_COUNT=$(ls -1 "$AGENT_OUTPUT_DIR"/*.md 2>/dev/null | wc -l)
    if [ "$OUTPUT_COUNT" -gt 0 ]; then
        echo "## 📁 Agent Outputs ($OUTPUT_COUNT Dateien)" >> "$DOC_FILE"
        echo "" >> "$DOC_FILE"
        for file in "$AGENT_OUTPUT_DIR"/*.md; do
            filename=$(basename "$file")
            echo "- $filename" >> "$DOC_FILE"
        done
        echo "" >> "$DOC_FILE"
    fi
fi

# Geänderte Dateien (aus Git falls verfügbar)
echo "## 🔧 Geänderte Dateien" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"
cd /home/rodemkay/www/react/plugin-todo
CHANGED_FILES=$(git diff --name-only HEAD~1 2>/dev/null || echo "Git-Historie nicht verfügbar")
echo "\`\`\`" >> "$DOC_FILE"
echo "$CHANGED_FILES" >> "$DOC_FILE"
echo "\`\`\`" >> "$DOC_FILE"
echo "" >> "$DOC_FILE"

echo "---" >> "$DOC_FILE"
echo "*Automatisch generiert vom V3.0 Documentation System*" >> "$DOC_FILE"

echo "✅ Dokumentation erstellt: $DOC_FILE"

# Optional: Füge Link zur Datenbank hinzu
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \"
UPDATE stage_project_todos 
SET report_url='file://$DOC_FILE' 
WHERE id=$TODO_ID
\""