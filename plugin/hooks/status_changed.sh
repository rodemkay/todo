#!/bin/bash

# STATUS_CHANGED Hook - wird bei jeder Status-Änderung ausgeführt
# Parameter: $1 = TODO_ID, $2 = NEW_STATUS, $3 = OLD_STATUS

TODO_ID="$1"
NEW_STATUS="$2"
OLD_STATUS="$3"
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")
DATE_SLUG=$(date "+%Y%m%d-%H%M%S")

if [ -z "$TODO_ID" ] || [ -z "$NEW_STATUS" ]; then
    echo "❌ Parameter fehlen: TODO_ID=$TODO_ID, NEW_STATUS=$NEW_STATUS"
    exit 1
fi

echo "🔄 Status-Änderung Hook für Todo #$TODO_ID: '$OLD_STATUS' → '$NEW_STATUS'"

# Get todo details
TODO_DETAILS=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp eval '
    global \$wpdb;
    \$table = \$wpdb->prefix . \"project_todos\";
    \$todo = \$wpdb->get_row(\"SELECT * FROM \$table WHERE id = $TODO_ID\");
    if(\$todo) {
        echo json_encode([
            \"id\" => \$todo->id,
            \"title\" => \$todo->title,
            \"description\" => \$todo->description,
            \"scope\" => \$todo->scope,
            \"working_directory\" => \$todo->working_directory
        ]);
    }
'" 2>/dev/null)

# Generate status-specific messages and actions
case "$NEW_STATUS" in
    "completed")
        echo "✅ Todo wird als ABGESCHLOSSEN markiert..."
        STATUS_MESSAGE="Todo wurde erfolgreich abgeschlossen"
        STATUS_ICON="✅"
        STATUS_COLOR="#4caf50"
        # Create detailed HTML report for completed tasks
        /home/rodemkay/www/react/wp-project-todos/hooks/generate_completion_report.sh "$TODO_ID" "$TIMESTAMP" "$DATE_SLUG"
        ;;
    "in_progress")
        echo "🔄 Todo wird als IN BEARBEITUNG markiert..."
        STATUS_MESSAGE="Todo wurde zur Bearbeitung aufgenommen"
        STATUS_ICON="🔄"
        STATUS_COLOR="#2196F3"
        ;;
    "cancelled")
        echo "❌ Todo wird als ABGEBROCHEN markiert..."
        STATUS_MESSAGE="Todo wurde abgebrochen"
        STATUS_ICON="❌"
        STATUS_COLOR="#f44336"
        ;;
    "blocked")
        echo "⏸️ Todo wird als BLOCKIERT markiert..."
        STATUS_MESSAGE="Todo ist blockiert und kann nicht fortgesetzt werden"
        STATUS_ICON="⏸️"
        STATUS_COLOR="#ff9800"
        ;;
    "pending")
        echo "📋 Todo wird als AUSSTEHEND markiert..."
        STATUS_MESSAGE="Todo ist bereit zur Bearbeitung"
        STATUS_ICON="📋"
        STATUS_COLOR="#9e9e9e"
        ;;
    *)
        echo "📝 Status geändert zu: $NEW_STATUS"
        STATUS_MESSAGE="Status wurde geändert zu: $NEW_STATUS"
        STATUS_ICON="📝"
        STATUS_COLOR="#607d8b"
        ;;
esac

# Create status log entry
STATUS_LOG="Status-Änderung Todo #${TODO_ID} am ${TIMESTAMP}

VORHERIGER STATUS: ${OLD_STATUS}
NEUER STATUS: ${NEW_STATUS}

${STATUS_MESSAGE}

ARBEITSVERZEICHNIS: $(pwd)
ZEITSTEMPEL: ${TIMESTAMP}"

# Create mini HTML report for non-completed status changes
if [ "$NEW_STATUS" != "completed" ]; then
    REPORT_DIR="/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/todo-status-logs"
    mkdir -p "$REPORT_DIR"
    STATUS_REPORT="$REPORT_DIR/todo-${TODO_ID}-status-${NEW_STATUS}-${DATE_SLUG}.html"
    
    cat > "$STATUS_REPORT" << EOF
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo #${TODO_ID} - Status: ${NEW_STATUS}</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .header { 
            background: ${STATUS_COLOR}; 
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            text-align: center;
        }
        .content { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .status-icon { font-size: 32px; }
        .meta { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="status-icon">${STATUS_ICON}</div>
        <h1>Todo #${TODO_ID}</h1>
        <h2>${STATUS_MESSAGE}</h2>
        <p>${TIMESTAMP}</p>
    </div>
    
    <div class="content">
        <div class="meta">
            <strong>Titel:</strong> $(echo "$TODO_DETAILS" | jq -r '.title // "Nicht verfügbar"')<br>
            <strong>Status-Änderung:</strong> ${OLD_STATUS} → ${NEW_STATUS}<br>
            <strong>Zeitstempel:</strong> ${TIMESTAMP}<br>
            <strong>Bereich:</strong> $(echo "$TODO_DETAILS" | jq -r '.scope // "other"')
        </div>
        
        <h3>📝 Beschreibung</h3>
        <p>$(echo "$TODO_DETAILS" | jq -r '.description // "Keine Beschreibung verfügbar"')</p>
        
        <p><a href="https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos">← Zurück zum Dashboard</a></p>
    </div>
</body>
</html>
EOF

    STATUS_REPORT_URL="https://forexsignale.trade/staging/wp-content/uploads/todo-status-logs/todo-${TODO_ID}-status-${NEW_STATUS}-${DATE_SLUG}.html"
fi

# Update database with status log and timestamp
echo "📊 Aktualisiere Datenbank..."

# Determine which date field to update
DATE_FIELD=""
case "$NEW_STATUS" in
    "completed")
        DATE_FIELD="completed_date"
        ;;
    "in_progress")
        DATE_FIELD="updated_at"
        ;;
    *)
        DATE_FIELD="updated_at"
        ;;
esac

ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp eval '
    global \$wpdb;
    \$table = \$wpdb->prefix . \"project_todos\";
    
    // Get current claude_notes to append to
    \$current = \$wpdb->get_row(\"SELECT claude_notes, claude_output FROM \$table WHERE id = $TODO_ID\");
    \$existing_notes = \$current->claude_notes ?? \"\";
    \$existing_output = \$current->claude_output ?? \"\";
    
    \$new_note = \"\n--- Status Update ${TIMESTAMP} ---\n${STATUS_MESSAGE}\nVon: ${OLD_STATUS} → ${NEW_STATUS}\";
    \$updated_notes = \$existing_notes . \$new_note;
    
    \$new_output_line = \"[${TIMESTAMP}] Status: ${OLD_STATUS} → ${NEW_STATUS}\";
    \$updated_output = \$existing_output . \"\n\" . \$new_output_line;
    
    \$update_data = [
        \"status\" => \"$NEW_STATUS\",
        \"$DATE_FIELD\" => current_time(\"mysql\"),
        \"claude_notes\" => \$updated_notes,
        \"claude_output\" => \$updated_output
    ];
    
    if(\"$NEW_STATUS\" == \"completed\" && !empty(\"$STATUS_REPORT_URL\")) {
        \$update_data[\"report_url\"] = \"$STATUS_REPORT_URL\";
    }
    
    \$result = \$wpdb->update(\$table, \$update_data, [\"id\" => $TODO_ID]);
    
    if(\$result !== false) {
        echo \"✅ Todo #$TODO_ID Status aktualisiert: $NEW_STATUS\";
        if(!empty(\"$STATUS_REPORT_URL\")) {
            echo \"📄 Status-Report: $STATUS_REPORT_URL\";
        }
    } else {
        echo \"❌ Fehler beim Status-Update in Datenbank\";
    }
'" 2>/dev/null

echo ""
echo "${STATUS_ICON} Todo #$TODO_ID Status aktualisiert: '$OLD_STATUS' → '$NEW_STATUS'"
if [ ! -z "$STATUS_REPORT_URL" ]; then
    echo "📄 Status-Report: $STATUS_REPORT_URL"
fi
echo "📊 Dashboard: https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos"