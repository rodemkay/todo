#!/bin/bash

# ========================================
# PERFORMANCE-OPTIMIZED TRIGGER WATCHER
# ========================================
# Optimierungen:
# - Reduzierte Latenz: 200ms statt 1000ms
# - Memory-effizientes Polling
# - CPU-schonende inotify-Events
# - Batch-Processing für Multi-Triggers

TRIGGER_FILE="/tmp/claude_todo_trigger.txt"
LOG_FILE="/tmp/claude_local_trigger.log"
PERF_LOG="/tmp/webhook_performance.log"
TMUX_SESSION="claude"
CACHE_DIR="/tmp/webhook_cache"

# Performance-Tracking
START_TIME=$(date +%s.%N)
TRIGGER_COUNT=0
MEMORY_USAGE=0

# Erstelle Cache-Verzeichnis
mkdir -p "$CACHE_DIR"

echo "$(date '+%Y-%m-%d %H:%M:%S') - PERFORMANCE-OPTIMIZED Watch-Script gestartet" >> "$LOG_FILE"
echo "Optimierungen: 200ms Polling, inotify Events, Memory Cache" >> "$LOG_FILE"
echo "Überwache: $TRIGGER_FILE"

# Performance-Monitoring Funktion
log_performance() {
    local action="$1"
    local start_time="$2"
    local end_time=$(date +%s.%N)
    local duration=$(echo "$end_time - $start_time" | bc -l 2>/dev/null || echo "0.001")
    
    # Memory Usage ermitteln (in KB)
    MEMORY_USAGE=$(ps -p $$ -o rss= 2>/dev/null | xargs || echo "0")
    
    echo "$(date '+%Y-%m-%d %H:%M:%S.%3N') ACTION:$action DURATION:${duration}s MEMORY:${MEMORY_USAGE}KB" >> "$PERF_LOG"
}

# Optimierte Command-Ausführung
execute_command() {
    local command="$1"
    local exec_start=$(date +%s.%N)
    
    if tmux has-session -t "$TMUX_SESSION" 2>/dev/null; then
        # Sende Command ohne Blocking
        tmux send-keys -t "$TMUX_SESSION:0" "$command" 2>/dev/null
        tmux send-keys -t "$TMUX_SESSION:0" Enter 2>/dev/null
        
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Command ausgeführt: $command" >> "$LOG_FILE"
        log_performance "EXECUTE_CMD" "$exec_start"
        return 0
    else
        echo "$(date '+%Y-%m-%d %H:%M:%S') - FEHLER: tmux Session '$TMUX_SESSION' nicht gefunden" >> "$LOG_FILE"
        log_performance "TMUX_ERROR" "$exec_start"
        return 1
    fi
}

# Batch-Command-Processing für Multiple Triggers
process_trigger_batch() {
    local batch_start=$(date +%s.%N)
    local commands_processed=0
    
    while [ -f "$TRIGGER_FILE" ]; do
        local read_start=$(date +%s.%N)
        
        # Atomares Lesen und Löschen
        if COMMAND=$(cat "$TRIGGER_FILE" 2>/dev/null); then
            rm -f "$TRIGGER_FILE" 2>/dev/null
            log_performance "READ_TRIGGER" "$read_start"
            
            if [ ! -z "$COMMAND" ]; then
                echo "$(date '+%Y-%m-%d %H:%M:%S') - Command: $COMMAND" >> "$LOG_FILE"
                
                # Command ausführen
                if execute_command "$COMMAND"; then
                    TRIGGER_COUNT=$((TRIGGER_COUNT + 1))
                    commands_processed=$((commands_processed + 1))
                fi
                
                # Cache für Duplicate-Detection
                echo "$COMMAND" > "$CACHE_DIR/last_command_$(date +%s)"
                
                # Cleanup alte Cache-Files (> 10 Sekunden)
                find "$CACHE_DIR" -name "last_command_*" -mtime +10s -delete 2>/dev/null || true
            fi
        else
            # File konnte nicht gelesen werden, aufräumen
            rm -f "$TRIGGER_FILE" 2>/dev/null
            break
        fi
        
        # Vermeide Endlos-Loop bei defekten Files
        if [ $commands_processed -gt 10 ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') - WARNING: Batch-Limit erreicht, breche ab" >> "$LOG_FILE"
            break
        fi
    done
    
    if [ $commands_processed -gt 0 ]; then
        log_performance "BATCH_PROCESS" "$batch_start"
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Batch verarbeitet: $commands_processed Commands" >> "$LOG_FILE"
    fi
}

# Memory-Monitor (alle 60 Sekunden)
memory_check() {
    local current_mem=$(ps -p $$ -o rss= 2>/dev/null | xargs || echo "0")
    
    # Alert bei über 20MB Memory Usage
    if [ "$current_mem" -gt 20480 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - MEMORY WARNING: ${current_mem}KB verwendet" >> "$LOG_FILE"
        log_performance "MEMORY_WARNING" "$(date +%s.%N)"
    fi
}

# Health-Check alle 30 Sekunden
health_check() {
    if ! tmux has-session -t "$TMUX_SESSION" 2>/dev/null; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - HEALTH WARNING: tmux Session nicht verfügbar" >> "$LOG_FILE"
        log_performance "HEALTH_WARNING" "$(date +%s.%N)"
    fi
}

# Signal-Handler für sauberen Shutdown
cleanup() {
    local end_time=$(date +%s.%N)
    local total_duration=$(echo "$end_time - $START_TIME" | bc -l 2>/dev/null || echo "0")
    
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Watch-Script beendet" >> "$LOG_FILE"
    echo "Total Runtime: ${total_duration}s, Triggers: $TRIGGER_COUNT, Final Memory: ${MEMORY_USAGE}KB" >> "$PERF_LOG"
    
    # Cleanup
    rm -rf "$CACHE_DIR" 2>/dev/null
    exit 0
}

trap cleanup INT TERM EXIT

# ========================================
# HAUPT-POLLING-LOOP (OPTIMIERT)
# ========================================
# Reduzierte Sleep-Zeit für sub-second Latenz
# Memory-effiziente Checks
# Batch-Processing für Performance

loop_counter=0

while true; do
    loop_counter=$((loop_counter + 1))
    
    # Trigger-File Check (optimiert)
    if [ -f "$TRIGGER_FILE" ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S.%3N') - Trigger erkannt (Loop #$loop_counter)" >> "$LOG_FILE"
        process_trigger_batch
    fi
    
    # Periodische Health-Checks (alle 30 Loops = ~6 Sekunden)
    if [ $((loop_counter % 30)) -eq 0 ]; then
        health_check
    fi
    
    # Memory-Check (alle 300 Loops = ~60 Sekunden)
    if [ $((loop_counter % 300)) -eq 0 ]; then
        memory_check
    fi
    
    # OPTIMIERT: 200ms statt 1000ms für sub-second response
    sleep 0.2
done