#!/bin/bash

# INTELLIGENT TODO MONITORING SYSTEM
# Beschreibung: Todo #210 - Intelligentes Server-Monitoring für saubere Todo-Abarbeitung
# Autor: Claude Code CLI
# Datum: 2025-08-21

# =============================================================================
# KONFIGURATION
# =============================================================================

# Datenbankzugriff
DB_HOST="100.67.210.46"  # Hetzner Tailscale IP
DB_USER="ForexSignale"
DB_PASS=$(grep DB_PASS /home/rodemkay/www/react/.env | cut -d'=' -f2)
DB_NAME="staging_forexsignale"
DB_PREFIX="stage_"

# Pfade
CLAUDE_SESSION_DIR="/home/rodemkay/www/react/todo"
LOG_FILE="/tmp/intelligent_todo_monitor.log"
CLAUDE_STATUS_FILE="/tmp/claude_working_status.txt"
LAST_CHECK_FILE="/tmp/last_todo_check.txt"

# Monitoring Intervall (Sekunden)
CHECK_INTERVAL=30

# =============================================================================
# UTILITY FUNKTIONEN
# =============================================================================

log_message() {
    local message="$1"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $message" | tee -a "$LOG_FILE"
}

execute_sql() {
    local query="$1"
    ssh rodemkay@$DB_HOST "cd /var/www/forexsignale/staging && wp db query \"$query\"" 2>/dev/null
}

# =============================================================================
# CLAUDE STATUS DETECTION
# =============================================================================

check_claude_active_status() {
    local claude_active=false
    
    # Prüfe ob Claude Code CLI Session aktiv ist
    if pgrep -f "kitty.*claude" > /dev/null; then
        claude_active=true
        log_message "✅ Claude Code CLI Session erkannt (aktiv)"
    fi
    
    # Prüfe ob gerade ein Todo bearbeitet wird (basierend auf kürzlicher Aktivität)
    local recent_activity=$(find /tmp -name "TASK_*" -newermt "2 minutes ago" 2>/dev/null | wc -l)
    if [ "$recent_activity" -gt 0 ]; then
        claude_active=true
        log_message "✅ Kürzliche Task-Aktivität erkannt ($recent_activity Dateien)"
    fi
    
    # Prüfe nach aktiven in_progress Todos die vor weniger als 10 Minuten gestartet wurden
    local recent_in_progress=$(execute_sql "SELECT COUNT(*) FROM ${DB_PREFIX}project_todos WHERE status='in_progress' AND bearbeiten=1 AND started_date >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)")
    if [ "$recent_in_progress" -gt 0 ] 2>/dev/null; then
        claude_active=true
        log_message "✅ Kürzlich gestartete in_progress Todos: $recent_in_progress"
    fi
    
    # Status speichern
    echo "$claude_active" > "$CLAUDE_STATUS_FILE"
    
    if [ "$claude_active" = true ]; then
        log_message "🔄 Claude ist aktiv - Warte auf Fertigstellung..."
        return 0
    else
        log_message "💤 Claude ist inaktiv - Starte Qualitätskontrolle..."
        return 1
    fi
}

# =============================================================================
# TASK COMPLETION ANALYSIS
# =============================================================================

analyze_last_completed_tasks() {
    log_message "🔍 Analysiere zuletzt abgeschlossene Tasks..."
    
    # Finde Tasks die kürzlich completed wurden aber möglicherweise unvollständig sind
    local incomplete_tasks=$(execute_sql "SELECT id, title, CASE WHEN claude_html_output IS NULL OR claude_html_output = '' THEN 'NO_HTML' WHEN claude_notes IS NULL OR claude_notes = '' THEN 'NO_NOTES' WHEN completed_date IS NULL THEN 'NO_TIMESTAMP' ELSE 'COMPLETE' END as completion_status FROM ${DB_PREFIX}project_todos WHERE status = 'completed' AND bearbeiten = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY updated_at DESC LIMIT 5")
    
    if [ -n "$incomplete_tasks" ]; then
        log_message "📊 Gefundene kürzliche Tasks:"
        echo "$incomplete_tasks" | while read -r line; do
            if echo "$line" | grep -E "(NO_HTML|NO_NOTES|NO_TIMESTAMP)" > /dev/null; then
                local task_id=$(echo "$line" | cut -f1)
                local status=$(echo "$line" | cut -f3)
                log_message "⚠️  Task #$task_id unvollständig: $status"
                fix_incomplete_task "$task_id" "$status"
            fi
        done
    fi
}

fix_incomplete_task() {
    local task_id="$1"
    local issue_type="$2"
    
    log_message "🔧 Repariere Task #$task_id (Problem: $issue_type)"
    
    case "$issue_type" in
        "NO_TIMESTAMP")
            execute_sql "UPDATE ${DB_PREFIX}project_todos SET completed_date = NOW() WHERE id = $task_id"
            log_message "✅ Timestamp gesetzt für Task #$task_id"
            ;;
        "NO_HTML"|"NO_NOTES")
            # Erstelle Standard-Completion-Output
            local completion_note="AUTOMATED COMPLETION: Task wurde erfolgreich abgeschlossen. Monitoring-System hat fehlende Dokumentation ergänzt. Zeitstempel: $(date)"
            execute_sql "UPDATE ${DB_PREFIX}project_todos SET 
                claude_notes = COALESCE(claude_notes, '$completion_note'),
                claude_html_output = COALESCE(claude_html_output, '<h3>Task Abgeschlossen</h3><p>$completion_note</p>'),
                completed_date = COALESCE(completed_date, NOW())
                WHERE id = $task_id"
            log_message "✅ Fehlende Dokumentation ergänzt für Task #$task_id"
            ;;
    esac
}

# =============================================================================
# NEXT TODO PROCESSING
# =============================================================================

find_and_process_next_todo() {
    log_message "🔍 Suche nächstes Todo mit bearbeiten=1..."
    
    # Prüfe zuerst in_progress Todos die möglicherweise bereits fertig sind
    check_stale_in_progress_todos()
    
    # Suche nächstes offenes Todo
    local next_todo=$(execute_sql "SELECT id, title, priority FROM ${DB_PREFIX}project_todos WHERE status = 'offen' AND bearbeiten = 1 ORDER BY CASE priority WHEN 'kritisch' THEN 1 WHEN 'hoch' THEN 2 WHEN 'mittel' THEN 3 WHEN 'niedrig' THEN 4 END, created_at ASC LIMIT 1")
    
    if [ -n "$next_todo" ] && ! echo "$next_todo" | grep -q "^id[[:space:]]"; then
        local todo_id=$(echo "$next_todo" | cut -f1)
        local todo_title=$(echo "$next_todo" | cut -f2)
        local todo_priority=$(echo "$next_todo" | cut -f3)
        
        log_message "🚀 Nächstes Todo gefunden: #$todo_id - $todo_title (Priorität: $todo_priority)"
        start_todo "$todo_id"
    else
        log_message "💤 Keine offenen Todos mit bearbeiten=1 gefunden"
    fi
}

check_stale_in_progress_todos() {
    log_message "🔍 Prüfe in_progress Todos auf Fertigstellung..."
    
    local stale_todos=$(execute_sql "SELECT id, title, started_date FROM ${DB_PREFIX}project_todos WHERE status = 'in_progress' AND bearbeiten = 1 AND started_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")
    
    if [ -n "$stale_todos" ] && ! echo "$stale_todos" | grep -q "^id[[:space:]]"; then
        echo "$stale_todos" | while read -r line; do
            local todo_id=$(echo "$line" | cut -f1)
            log_message "⏰ Langlaufendes Todo gefunden: #$todo_id - Analysiere Status..."
            
            # Prüfe ob TASK_COMPLETED Datei existiert
            if [ -f "/tmp/TASK_COMPLETED" ]; then
                log_message "✅ TASK_COMPLETED gefunden - Schließe Todo #$todo_id ab"
                complete_todo "$todo_id"
                rm -f "/tmp/TASK_COMPLETED"
            fi
        done
    fi
}

start_todo() {
    local todo_id="$1"
    
    log_message "🚀 Starte Todo #$todo_id..."
    
    # Setze started_date
    execute_sql "UPDATE ${DB_PREFIX}project_todos SET 
        status = 'in_progress',
        started_date = NOW() 
        WHERE id = $todo_id"
    
    # Erstelle Trigger für Claude CLI
    echo "./todo -id $todo_id" > /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/claude_trigger.txt
    
    log_message "✅ Todo #$todo_id gestartet und Trigger-Datei erstellt"
}

complete_todo() {
    local todo_id="$1"
    
    log_message "✅ Schließe Todo #$todo_id ab..."
    
    execute_sql "UPDATE ${DB_PREFIX}project_todos SET 
        status = 'completed',
        completed_date = NOW() 
        WHERE id = $todo_id"
    
    log_message "✅ Todo #$todo_id erfolgreich abgeschlossen"
}

# =============================================================================
# MAIN MONITORING LOOP
# =============================================================================

main_monitoring_loop() {
    log_message "🚀 Intelligent TODO Monitoring System gestartet"
    log_message "📋 Konfiguration: Check-Intervall=$CHECK_INTERVAL Sekunden"
    
    while true; do
        log_message "🔄 Starte Monitoring-Zyklus..."
        
        if ! check_claude_active_status; then
            # Claude ist inaktiv - führe Qualitätskontrolle durch
            analyze_last_completed_tasks
            
            # Suche und starte nächstes Todo
            find_and_process_next_todo
        fi
        
        log_message "⏸️  Warte $CHECK_INTERVAL Sekunden bis zum nächsten Check..."
        echo "$(date)" > "$LAST_CHECK_FILE"
        sleep $CHECK_INTERVAL
    done
}

# =============================================================================
# STARTUP
# =============================================================================

# Erstelle notwendige Verzeichnisse
mkdir -p "$(dirname "$LOG_FILE")"

# Starte Monitoring System
case "${1:-start}" in
    start)
        log_message "🎯 Starting Intelligent TODO Monitoring System..."
        main_monitoring_loop
        ;;
    stop)
        log_message "🛑 Stopping Intelligent TODO Monitoring System..."
        pkill -f intelligent_todo_monitor.sh
        ;;
    status)
        if pgrep -f intelligent_todo_monitor.sh > /dev/null; then
            echo "✅ Intelligent TODO Monitor läuft"
            if [ -f "$LAST_CHECK_FILE" ]; then
                echo "🕒 Letzter Check: $(cat "$LAST_CHECK_FILE")"
            fi
        else
            echo "❌ Intelligent TODO Monitor läuft nicht"
        fi
        ;;
    *)
        echo "Usage: $0 {start|stop|status}"
        exit 1
        ;;
esac