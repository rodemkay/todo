#!/bin/bash
# Cleanup Script für hängengebliebene Todos

SSH_HOST="rodemkay@159.69.157.54"
REMOTE_PATH="/var/www/forexsignale/staging"

echo "🔍 Analysiere hängengebliebene Todos..."

# Zeige alle in_progress Todos
echo "📋 Todos mit Status 'in_progress':"
ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
    SELECT id, title, status, 
           execution_started_at,
           TIMESTAMPDIFF(HOUR, execution_started_at, NOW()) as hours_stuck
    FROM stage_project_todos 
    WHERE status = 'in_progress' 
    ORDER BY id
\""

# Zähle stuck todos
COUNT=$(ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
    SELECT COUNT(*) 
    FROM stage_project_todos 
    WHERE status = 'in_progress'
\" --skip-column-names")

echo ""
echo "⚠️  Gefunden: $COUNT Todos mit Status 'in_progress'"
echo ""

# Optionen anbieten
echo "Was möchtest du tun?"
echo "1) Alle alten (>1 Stunde) auf 'offen' zurücksetzen"
echo "2) Alle ohne execution_started_at auf 'offen' zurücksetzen"
echo "3) Alle außer dem aktuellen auf 'offen' zurücksetzen"
echo "4) Spezifische IDs auf 'offen' zurücksetzen"
echo "5) Nichts tun (nur anzeigen)"

read -p "Wähle Option (1-5): " OPTION

case $OPTION in
    1)
        echo "🔄 Setze alte Todos (>1 Stunde) zurück..."
        ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
            UPDATE stage_project_todos 
            SET status = 'offen',
                execution_started_at = NULL
            WHERE status = 'in_progress' 
            AND execution_started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        \""
        echo "✅ Alte Todos zurückgesetzt"
        ;;
    2)
        echo "🔄 Setze Todos ohne execution_started_at zurück..."
        ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
            UPDATE stage_project_todos 
            SET status = 'offen'
            WHERE status = 'in_progress' 
            AND execution_started_at IS NULL
        \""
        echo "✅ Todos ohne Startzeit zurückgesetzt"
        ;;
    3)
        if [ -f /tmp/CURRENT_TODO_ID ]; then
            CURRENT_ID=$(cat /tmp/CURRENT_TODO_ID)
            echo "🔄 Setze alle außer #$CURRENT_ID zurück..."
            ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
                UPDATE stage_project_todos 
                SET status = 'offen',
                    execution_started_at = NULL
                WHERE status = 'in_progress' 
                AND id != $CURRENT_ID
            \""
            echo "✅ Alle anderen Todos zurückgesetzt"
        else
            echo "❌ Keine aktuelle Todo-ID gefunden"
        fi
        ;;
    4)
        read -p "Gib die IDs ein (komma-getrennt): " IDS
        echo "🔄 Setze IDs $IDS zurück..."
        ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
            UPDATE stage_project_todos 
            SET status = 'offen',
                execution_started_at = NULL
            WHERE id IN ($IDS)
        \""
        echo "✅ Spezifische Todos zurückgesetzt"
        ;;
    5)
        echo "ℹ️  Keine Änderungen vorgenommen"
        ;;
    *)
        echo "❌ Ungültige Option"
        ;;
esac

# Zeige finalen Status
echo ""
echo "📊 Finaler Status:"
ssh $SSH_HOST "cd $REMOTE_PATH && wp db query \"
    SELECT status, COUNT(*) as count 
    FROM stage_project_todos 
    WHERE status IN ('offen', 'in_progress', 'completed')
    GROUP BY status
\""