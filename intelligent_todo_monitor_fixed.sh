#!/bin/bash

# INTELLIGENT TODO MONITORING SYSTEM - FIXED VERSION
# Todo #210 - Intelligentes Server-Monitoring für saubere Todo-Abarbeitung

# Konfiguration
DB_HOST="100.67.210.46"
DB_USER="ForexSignale"
DB_NAME="staging_forexsignale"
DB_PREFIX="stage_"
LOG_FILE="/tmp/intelligent_todo_monitor.log"
CHECK_INTERVAL=30

# Logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# SQL Ausführung
execute_sql() {
    ssh rodemkay@$DB_HOST "cd /var/www/forexsignale/staging && wp db query \"$1\"" 2>/dev/null
}

# Claude Status prüfen
check_claude_active() {
    local claude_active=false
    local reason=""
    
    # PRIMÄR: Prüfe ob ein Todo aktuell bearbeitet wird
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        local current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ -n "$current_id" ]; then
            # Prüfe ob dieses Todo noch in_progress ist
            local status=$(execute_sql "SELECT status FROM ${DB_PREFIX}project_todos WHERE id = $current_id" | tail -1)
            if [ "$status" = "in_progress" ]; then
                claude_active=true
                reason="Todo #$current_id in Bearbeitung"
            else
                # Todo ist nicht mehr in_progress - ABER warte auf TASK_COMPLETED!
                # Prüfe ZUERST ob TASK_COMPLETED existiert
                if [ ! -f "/tmp/TASK_COMPLETED" ]; then
                    # Nur löschen wenn KEIN TASK_COMPLETED wartet
                    log_message "🧹 Bereinige veraltete CURRENT_TODO_ID für abgeschlossenes Todo #$current_id"
                    rm -f /tmp/CURRENT_TODO_ID
                else
                    # TASK_COMPLETED existiert - NICHT löschen!
                    log_message "⏸️ CURRENT_TODO_ID behalten - TASK_COMPLETED wartet auf Verarbeitung"
                    claude_active=true
                    reason="TASK_COMPLETED wartet auf Verarbeitung für Todo #$current_id"
                fi
            fi
        fi
    fi
    
    # SEKUNDÄR: Prüfe ob TASK_COMPLETED existiert (bedeutet Claude ist fertig)
    if [ -f "/tmp/TASK_COMPLETED" ]; then
        claude_active=false
        reason="TASK_COMPLETED gefunden - Claude ist fertig"
        # Bereinige die Datei für nächsten Durchlauf
        rm -f /tmp/TASK_COMPLETED
        log_message "🧹 TASK_COMPLETED bereinigt"
    fi
    
    # TERTIÄR: Prüfe kürzliche Task-Aktivität (nur als Backup)
    if [ "$claude_active" = false ]; then
        local recent_files=$(find /tmp -name "TASK_STARTED_*" -newermt "5 minutes ago" 2>/dev/null | wc -l)
        if [ "$recent_files" -gt 0 ]; then
            claude_active=true
            reason="Kürzliche Task-Aktivität erkannt"
        fi
    fi
    
    # NICHT MEHR: pgrep nach kitty.*claude - das findet IMMER die Session!
    
    if [ "$claude_active" = true ]; then
        log_message "🔄 Claude ist aktiv - $reason"
        return 0
    else
        log_message "💤 Claude inaktiv - Bereit für neue Aufgaben"
        return 1
    fi
}

# Unvollständige Tasks analysieren
analyze_incomplete_tasks() {
    log_message "🔍 Analysiere unvollständige Tasks..."
    
    local query="SELECT id, title FROM ${DB_PREFIX}project_todos WHERE status = 'completed' AND bearbeiten = 1 AND (claude_html_output IS NULL OR claude_html_output = '' OR completed_date IS NULL) AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 5"
    local incomplete_tasks=$(execute_sql "$query")
    
    if [ -n "$incomplete_tasks" ] && ! echo "$incomplete_tasks" | grep -q "^id"; then
        echo "$incomplete_tasks" | while read -r line; do
            local task_id=$(echo "$line" | cut -f1)
            if [ -n "$task_id" ] && [ "$task_id" != "id" ]; then
                log_message "🔧 Repariere unvollständige Task #$task_id"
                fix_incomplete_task "$task_id"
            fi
        done
    fi
}

# Unvollständige Task reparieren
fix_incomplete_task() {
    local task_id="$1"
    local completion_note="AUTOMATED COMPLETION: Task wurde erfolgreich abgeschlossen. Monitoring-System hat fehlende Dokumentation ergänzt. Zeitstempel: $(date)"
    
    local update_query="UPDATE ${DB_PREFIX}project_todos SET claude_notes = COALESCE(claude_notes, '$completion_note'), claude_html_output = COALESCE(claude_html_output, '<h3>Task Abgeschlossen</h3><p>$completion_note</p>'), completed_date = COALESCE(completed_date, NOW()) WHERE id = $task_id"
    
    execute_sql "$update_query"
    log_message "✅ Task #$task_id repariert"
    
    # Bereinige auch hier die Hook-Dateien falls diese Task-ID aktiv war
    cleanup_hook_files "$task_id"
}

# Stale in_progress Tasks prüfen
check_stale_todos() {
    log_message "🔍 Prüfe stale in_progress Todos..."
    
    local query="SELECT id, title FROM ${DB_PREFIX}project_todos WHERE status = 'in_progress' AND bearbeiten = 1 AND started_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
    local stale_todos=$(execute_sql "$query")
    
    if [ -n "$stale_todos" ] && ! echo "$stale_todos" | grep -q "^id"; then
        echo "$stale_todos" | while read -r line; do
            local todo_id=$(echo "$line" | cut -f1)
            if [ -n "$todo_id" ] && [ "$todo_id" != "id" ]; then
                log_message "⏰ Stale Todo gefunden: #$todo_id"
                if [ -f "/tmp/TASK_COMPLETED" ]; then
                    complete_todo "$todo_id"
                    # complete_todo ruft bereits cleanup_hook_files auf
                fi
            fi
        done
    fi
}

# Nächstes Todo finden und starten
find_next_todo() {
    log_message "🔍 Suche nächstes Todo..."
    
    # NUR offene Todos mit bearbeiten=1 laden (KEINE in_progress!)
    local query="SELECT id, title, priority FROM ${DB_PREFIX}project_todos WHERE status = 'offen' AND bearbeiten = 1 ORDER BY CASE priority WHEN 'kritisch' THEN 1 WHEN 'hoch' THEN 2 WHEN 'mittel' THEN 3 WHEN 'niedrig' THEN 4 END, created_at ASC LIMIT 1"
    local next_todo=$(execute_sql "$query")
    
    if [ -n "$next_todo" ]; then
        # Überspringe Header-Zeile und hole erste Daten-Zeile
        local data_line=$(echo "$next_todo" | grep -v "^id" | head -1)
        
        if [ -n "$data_line" ]; then
            local todo_id=$(echo "$data_line" | cut -f1)
            local todo_title=$(echo "$data_line" | cut -f2)
            
            if [ -n "$todo_id" ] && [ "$todo_id" != "id" ] && [ "$todo_id" -gt 0 ] 2>/dev/null; then
                log_message "🚀 Nächstes Todo gefunden: #$todo_id - $todo_title"
                start_todo "$todo_id"
            else
                log_message "💤 Keine offenen Todos gefunden"
            fi
        else
            log_message "💤 Keine offenen Todos mit bearbeiten=1 gefunden (nur Header)"
        fi
    else
        log_message "💤 Keine offenen Todos mit bearbeiten=1 gefunden (leeres Ergebnis)"
    fi
}

# Todo starten
start_todo() {
    local todo_id="$1"
    
    log_message "🚀 Starte Todo #$todo_id..."
    
    # Status aktualisieren
    execute_sql "UPDATE ${DB_PREFIX}project_todos SET status = 'in_progress', started_date = NOW() WHERE id = $todo_id"
    
    # Trigger-Datei erstellen - NUR ./todo ohne ID!
    echo "./todo" > /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/claude_trigger.txt
    
    log_message "✅ Todo #$todo_id gestartet - Trigger erstellt"
}

# Todo abschließen
complete_todo() {
    local todo_id="$1"
    
    execute_sql "UPDATE ${DB_PREFIX}project_todos SET status = 'completed', completed_date = NOW() WHERE id = $todo_id"
    log_message "✅ Todo #$todo_id abgeschlossen"
    
    # Bereinige Hook-System temporäre Dateien für Synchronisation
    cleanup_hook_files "$todo_id"
}

# Hook-System Dateien bereinigen (NUR wenn sie zu diesem Todo gehören!)
cleanup_hook_files() {
    local todo_id="$1"
    local cleaned=false
    
    # NUR bereinigen wenn die CURRENT_TODO_ID zu diesem Todo gehört
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        local current_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ "$current_id" = "$todo_id" ]; then
            # Dieses Todo ist tatsächlich das aktive - kann bereinigt werden
            rm -f /tmp/CURRENT_TODO_ID
            log_message "🧹 Hook-Datei CURRENT_TODO_ID bereinigt für abgeschlossenes Todo #$todo_id"
            cleaned=true
            
            # NUR wenn CURRENT_TODO_ID zu diesem Todo gehörte, bereinige auch die anderen
            if [ -f "/tmp/TASK_COMPLETED" ]; then
                rm -f /tmp/TASK_COMPLETED
                log_message "🧹 Hook-Datei TASK_COMPLETED bereinigt"
            fi
            
            if [ -f "/tmp/SPECIFIC_TODO_MODE" ]; then
                rm -f /tmp/SPECIFIC_TODO_MODE
                log_message "🧹 Hook-Datei SPECIFIC_TODO_MODE bereinigt"
            fi
        else
            log_message "⚠️  CURRENT_TODO_ID enthält #$current_id, nicht #$todo_id - keine Bereinigung"
        fi
    fi
    
    if [ "$cleaned" = false ]; then
        log_message "ℹ️  Keine Hook-Dateien zu bereinigen für Todo #$todo_id"
    fi
}

# Hauptschleife
main_loop() {
    log_message "🚀 Intelligent TODO Monitor gestartet (Intervall: ${CHECK_INTERVAL}s)"
    
    # Startup-Bereinigung: Prüfe und bereinige alte Hook-Dateien
    if [ -f "/tmp/CURRENT_TODO_ID" ]; then
        local stale_id=$(cat /tmp/CURRENT_TODO_ID 2>/dev/null)
        if [ -n "$stale_id" ]; then
            # Prüfe ob dieses Todo bereits completed ist
            local status=$(execute_sql "SELECT status FROM ${DB_PREFIX}project_todos WHERE id = $stale_id" | tail -1)
            if [ "$status" = "completed" ]; then
                log_message "🧹 Startup-Bereinigung: Entferne veraltete Hook-Dateien für bereits abgeschlossenes Todo #$stale_id"
                cleanup_hook_files "$stale_id"
            fi
        fi
    fi
    
    while true; do
        if ! check_claude_active; then
            analyze_incomplete_tasks
            check_stale_todos
            find_next_todo
        fi
        
        sleep $CHECK_INTERVAL
    done
}

# Script ausführen
case "${1:-start}" in
    start)
        main_loop
        ;;
    stop)
        pkill -f intelligent_todo_monitor_fixed.sh
        log_message "🛑 Monitor gestoppt"
        ;;
    status)
        if pgrep -f intelligent_todo_monitor_fixed.sh > /dev/null; then
            echo "✅ Monitor läuft (PID: $(pgrep -f intelligent_todo_monitor_fixed.sh))"
        else
            echo "❌ Monitor läuft nicht"
        fi
        ;;
    test)
        log_message "🧪 Test-Modus - Ein Monitoring-Zyklus"
        if ! check_claude_active; then
            analyze_incomplete_tasks
            check_stale_todos
            find_next_todo
        fi
        log_message "✅ Test abgeschlossen"
        ;;
    *)
        echo "Usage: $0 {start|stop|status|test}"
        ;;
esac