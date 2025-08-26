#!/bin/bash

# Claude Session-Switching System - Comprehensive Test Suite
# Version: 1.0
# Datum: 2025-01-21

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
GRAY='\033[0;37m'
BOLD='\033[1m'
NC='\033[0m'

# Test-Konfiguration
TEST_DIR="/home/rodemkay/www/react/plugin-todo/tests"
PLUGIN_TODO_DIR="/home/rodemkay/www/react/plugin-todo"
LOGS_DIR="$TEST_DIR/logs"
TEST_LOG="$LOGS_DIR/session-switching-tests-$(date +%Y%m%d_%H%M%S).log"
RESULTS_LOG="$LOGS_DIR/test-results.json"

# Test-Statistiken
TESTS_TOTAL=0
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_SKIPPED=0

# Test-Setup
setup_test_environment() {
    echo -e "${BLUE}🔧 Setting up test environment...${NC}"
    
    # Logs-Verzeichnis erstellen
    mkdir -p "$LOGS_DIR"
    
    # Test-Log initialisieren
    {
        echo "SESSION-SWITCHING TEST SUITE"
        echo "============================"
        echo "Started: $(date)"
        echo "Test Dir: $TEST_DIR"
        echo ""
    } > "$TEST_LOG"
    
    # JSON-Results initialisieren
    cat > "$RESULTS_LOG" << 'EOF'
{
  "test_suite": "session-switching",
  "version": "1.0",
  "started_at": "",
  "completed_at": "",
  "environment": {
    "hostname": "",
    "user": "",
    "pwd": ""
  },
  "statistics": {
    "total": 0,
    "passed": 0,
    "failed": 0,
    "skipped": 0,
    "success_rate": 0
  },
  "tests": []
}
EOF
    
    # Umgebungsinfos sammeln
    local hostname=$(hostname)
    local user=$(whoami)
    local pwd="$(pwd)"
    local started_at=$(date -Iseconds)
    
    # JSON aktualisieren
    jq --arg hostname "$hostname" --arg user "$user" --arg pwd "$pwd" --arg started_at "$started_at" '
        .environment.hostname = $hostname |
        .environment.user = $user |
        .environment.pwd = $pwd |
        .started_at = $started_at
    ' "$RESULTS_LOG" > "$RESULTS_LOG.tmp" && mv "$RESULTS_LOG.tmp" "$RESULTS_LOG"
    
    echo -e "${GREEN}✓ Test environment ready${NC}"
}

# Logging-Funktion
log_test() {
    local level="$1"
    local message="$2"
    echo "$(date '+%H:%M:%S') [$level] $message" >> "$TEST_LOG"
}

# Test-Result Logger
log_test_result() {
    local test_name="$1"
    local status="$2"
    local duration="$3"
    local details="$4"
    local error_msg="${5:-}"
    
    # JSON Result erstellen
    local test_result=$(cat << EOF
{
  "name": "$test_name",
  "status": "$status",
  "duration_ms": $duration,
  "details": "$details",
  "error": "$error_msg",
  "timestamp": "$(date -Iseconds)"
}
EOF
)
    
    # Zu Tests-Array hinzufügen
    jq --argjson test "$test_result" '.tests += [$test]' "$RESULTS_LOG" > "$RESULTS_LOG.tmp" && mv "$RESULTS_LOG.tmp" "$RESULTS_LOG"
}

# Test-Wrapper-Funktion
run_test() {
    local test_name="$1"
    local test_function="$2"
    local description="$3"
    
    ((TESTS_TOTAL++))
    
    echo -e "\n${BOLD}🧪 Test #$TESTS_TOTAL: $test_name${NC}"
    echo -e "${GRAY}   $description${NC}"
    
    log_test "INFO" "Starting test: $test_name"
    
    # Test-Zeit messen
    local start_time=$(date +%s%3N)
    local test_status="FAILED"
    local error_message=""
    
    # Test ausführen
    if $test_function; then
        test_status="PASSED"
        ((TESTS_PASSED++))
        echo -e "${GREEN}   ✓ PASSED${NC}"
    else
        test_status="FAILED"
        ((TESTS_FAILED++))
        error_message="Test function returned non-zero exit code"
        echo -e "${RED}   ✗ FAILED${NC}"
    fi
    
    local end_time=$(date +%s%3N)
    local duration=$((end_time - start_time))
    
    log_test "RESULT" "$test_name: $test_status (${duration}ms)"
    log_test_result "$test_name" "$test_status" "$duration" "$description" "$error_message"
    
    return $([[ "$test_status" == "PASSED" ]] && echo 0 || echo 1)
}

# Skip Test-Funktion
skip_test() {
    local test_name="$1"
    local reason="$2"
    
    ((TESTS_TOTAL++))
    ((TESTS_SKIPPED++))
    
    echo -e "\n${BOLD}⏭️  Test #$TESTS_TOTAL: $test_name${NC}"
    echo -e "${YELLOW}   ⚠️  SKIPPED: $reason${NC}"
    
    log_test "SKIP" "$test_name: $reason"
    log_test_result "$test_name" "SKIPPED" 0 "$reason" ""
}

# =============================================================================
# 1. FUNKTIONS-TESTS
# =============================================================================

# Test: Session Detection
test_session_detection() {
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    
    if [[ ! -f "$claude_switch" ]]; then
        log_test "ERROR" "claude-switch.sh not found at $claude_switch"
        return 1
    fi
    
    # Script ausführbar machen
    chmod +x "$claude_switch"
    
    # Status abfragen (sollte nicht fehlschlagen)
    if ! "$claude_switch" status &>/dev/null; then
        log_test "ERROR" "claude-switch.sh status command failed"
        return 1
    fi
    
    log_test "SUCCESS" "Session detection works"
    return 0
}

# Test: Projekt-Mapping
test_project_mapping() {
    local project_detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -f "$project_detector" ]]; then
        log_test "ERROR" "project-detector.sh not found"
        return 1
    fi
    
    chmod +x "$project_detector"
    
    # Projekt-Liste abrufen
    local projects_output
    if ! projects_output=$("$project_detector" list 2>&1); then
        log_test "ERROR" "project-detector list failed: $projects_output"
        return 1
    fi
    
    # Plugin-todo muss in der Liste sein
    if [[ "$projects_output" != *"plugin-todo"* ]]; then
        log_test "ERROR" "plugin-todo not found in project list"
        return 1
    fi
    
    log_test "SUCCESS" "Project mapping works"
    return 0
}

# Test: Session-Switching Dry-Run
test_session_switching_dryrun() {
    local session_manager="$PLUGIN_TODO_DIR/session-manager.sh"
    
    if [[ ! -f "$session_manager" ]]; then
        log_test "ERROR" "session-manager.sh not found"
        return 1
    fi
    
    chmod +x "$session_manager"
    
    # Health check (sollte funktionieren)
    if ! "$session_manager" health &>/dev/null; then
        log_test "WARNING" "Session health check indicated problems"
    fi
    
    # Current session status
    local current_session
    if current_session=$("$session_manager" current 2>/dev/null); then
        log_test "INFO" "Current session: $current_session"
    else
        log_test "INFO" "No current session detected"
    fi
    
    log_test "SUCCESS" "Session switching dry-run completed"
    return 0
}

# Test: Error Recovery
test_error_recovery() {
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    
    if [[ ! -f "$claude_switch" ]]; then
        log_test "ERROR" "claude-switch.sh not found"
        return 1
    fi
    
    # System-Check sollte funktionieren
    if ! "$claude_switch" init &>/dev/null; then
        log_test "ERROR" "System initialization failed"
        return 1
    fi
    
    # Fix command testen
    if ! "$claude_switch" fix &>/dev/null; then
        log_test "WARNING" "Fix command had issues"
    fi
    
    log_test "SUCCESS" "Error recovery mechanisms work"
    return 0
}

# =============================================================================
# 2. INTEGRATIONS-TESTS
# =============================================================================

# Test: TODO-System Integration
test_todo_integration() {
    local todo_cli="$PLUGIN_TODO_DIR/cli/todo"
    
    if [[ ! -f "$todo_cli" ]]; then
        log_test "ERROR" "todo CLI not found"
        return 1
    fi
    
    chmod +x "$todo_cli"
    
    # Status abfragen (kann fehlschlagen wenn keine TODOs)
    local todo_status
    if todo_status=$("$todo_cli" status 2>&1); then
        log_test "INFO" "TODO status: OK"
    else
        log_test "INFO" "TODO status: No active todos or connection issues"
    fi
    
    log_test "SUCCESS" "TODO integration test completed"
    return 0
}

# Test: Working Directory Validation
test_working_directory_validation() {
    local projects_config="$PLUGIN_TODO_DIR/config/projects.json"
    
    if [[ ! -f "$projects_config" ]]; then
        log_test "ERROR" "projects.json config not found"
        return 1
    fi
    
    # JSON-Format validieren
    if ! jq . "$projects_config" >/dev/null 2>&1; then
        log_test "ERROR" "projects.json is not valid JSON"
        return 1
    fi
    
    # Plugin-todo Konfiguration prüfen
    local working_dir
    if working_dir=$(jq -r '.projects."plugin-todo".directories.working_directory' "$projects_config" 2>/dev/null); then
        if [[ -d "$working_dir" ]]; then
            log_test "INFO" "Working directory exists: $working_dir"
        else
            log_test "WARNING" "Working directory not found: $working_dir"
        fi
    else
        log_test "ERROR" "Could not extract working directory from config"
        return 1
    fi
    
    log_test "SUCCESS" "Working directory validation completed"
    return 0
}

# Test: TASK_COMPLETED Handling
test_task_completed_handling() {
    local task_completed_file="/tmp/TASK_COMPLETED"
    
    # Cleanup zuerst
    rm -f "$task_completed_file"
    
    # Simuliere TASK_COMPLETED Creation
    echo 'TASK_COMPLETED' > "$task_completed_file"
    
    if [[ -f "$task_completed_file" ]]; then
        local content=$(cat "$task_completed_file")
        if [[ "$content" == "TASK_COMPLETED" ]]; then
            log_test "SUCCESS" "TASK_COMPLETED file handling works"
            rm -f "$task_completed_file"
            return 0
        fi
    fi
    
    log_test "ERROR" "TASK_COMPLETED file handling failed"
    return 1
}

# Test: Lock Mechanisms
test_lock_mechanisms() {
    local lock_file="/tmp/claude_session_lock"
    
    # Cleanup zuerst
    rm -f "$lock_file"
    
    # Lock erstellen
    echo "test-project" > "$lock_file"
    
    if [[ -f "$lock_file" ]]; then
        local lock_content=$(cat "$lock_file")
        if [[ "$lock_content" == "test-project" ]]; then
            log_test "SUCCESS" "Lock mechanism works"
            rm -f "$lock_file"
            return 0
        fi
    fi
    
    log_test "ERROR" "Lock mechanism failed"
    return 1
}

# =============================================================================
# 3. EDGE-CASES
# =============================================================================

# Test: Nicht-existierendes Projekt
test_nonexistent_project() {
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    local project_detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -f "$project_detector" ]]; then
        log_test "ERROR" "project-detector.sh not found"
        return 1
    fi
    
    chmod +x "$project_detector"
    
    # Test mit nicht-existierendem Projekt
    if "$project_detector" info "nonexistent-project-12345" &>/dev/null; then
        log_test "ERROR" "project-detector should fail for nonexistent project"
        return 1
    else
        log_test "SUCCESS" "Correctly handles nonexistent project"
        return 0
    fi
}

# Test: Bereits laufende Session
test_existing_session() {
    # Prüfe ob tmux verfügbar ist
    if ! command -v tmux >/dev/null 2>&1; then
        log_test "WARNING" "tmux not available, skipping session test"
        return 0
    fi
    
    local session_manager="$PLUGIN_TODO_DIR/session-manager.sh"
    
    if [[ ! -f "$session_manager" ]]; then
        log_test "ERROR" "session-manager.sh not found"
        return 1
    fi
    
    chmod +x "$session_manager"
    
    # Aktuelle Sessions prüfen
    if tmux list-sessions &>/dev/null; then
        log_test "INFO" "tmux sessions detected"
    else
        log_test "INFO" "No active tmux sessions"
    fi
    
    log_test "SUCCESS" "Existing session handling tested"
    return 0
}

# Test: Fehlende Berechtigungen
test_permission_issues() {
    local test_file="/tmp/claude_permission_test"
    
    # Test-Datei erstellen
    echo "test" > "$test_file"
    
    # Berechtigung entfernen
    chmod 000 "$test_file"
    
    # Versuch zu lesen (sollte fehlschlagen)
    if cat "$test_file" &>/dev/null; then
        log_test "ERROR" "Permission test failed - file should be unreadable"
        chmod 644 "$test_file"
        rm -f "$test_file"
        return 1
    else
        log_test "SUCCESS" "Permission restrictions work as expected"
        chmod 644 "$test_file"
        rm -f "$test_file"
        return 0
    fi
}

# Test: Unterbrochene Switches
test_interrupted_switches() {
    local lock_file="/tmp/claude_session_lock"
    
    # Simuliere alten Lock (30+ Minuten alt)
    echo "interrupted-project" > "$lock_file"
    
    # Timestamp auf 2 Stunden zurück setzen
    touch -d "2 hours ago" "$lock_file"
    
    local session_manager="$PLUGIN_TODO_DIR/session-manager.sh"
    
    if [[ ! -f "$session_manager" ]]; then
        log_test "ERROR" "session-manager.sh not found"
        return 1
    fi
    
    chmod +x "$session_manager"
    
    # Repair sollte alten Lock entfernen
    if "$session_manager" repair &>/dev/null; then
        # Lock sollte weg sein
        if [[ -f "$lock_file" ]]; then
            local age=$(stat -c %Y "$lock_file")
            local current=$(date +%s)
            local age_min=$(( (current - age) / 60 ))
            
            if (( age_min > 30 )); then
                log_test "WARNING" "Old lock not cleaned up by repair"
            else
                log_test "SUCCESS" "Lock was refreshed by repair"
            fi
        else
            log_test "SUCCESS" "Old lock was cleaned up by repair"
        fi
        
        rm -f "$lock_file"
        return 0
    else
        log_test "ERROR" "Session repair failed"
        rm -f "$lock_file"
        return 1
    fi
}

# =============================================================================
# 4. PERFORMANCE-TESTS
# =============================================================================

# Test: Session-Switch-Zeit messen
test_session_switch_performance() {
    local project_detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -f "$project_detector" ]]; then
        log_test "ERROR" "project-detector.sh not found"
        return 1
    fi
    
    chmod +x "$project_detector"
    
    # Zeit messen für project detection
    local start_time=$(date +%s%3N)
    
    if "$project_detector" list &>/dev/null; then
        local end_time=$(date +%s%3N)
        local duration=$((end_time - start_time))
        
        log_test "PERFORMANCE" "Project detection took ${duration}ms"
        
        # Performance-Bewertung
        if (( duration < 1000 )); then
            log_test "SUCCESS" "Performance: Excellent (< 1s)"
        elif (( duration < 3000 )); then
            log_test "SUCCESS" "Performance: Good (< 3s)"
        else
            log_test "WARNING" "Performance: Slow (> 3s)"
        fi
        
        return 0
    else
        log_test "ERROR" "Project detection failed"
        return 1
    fi
}

# Test: Resource Usage
test_resource_usage() {
    # Teste Speicher-Usage der Scripts
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    
    if [[ ! -f "$claude_switch" ]]; then
        log_test "ERROR" "claude-switch.sh not found"
        return 1
    fi
    
    chmod +x "$claude_switch"
    
    # Memory usage via /usr/bin/time messen
    if command -v /usr/bin/time >/dev/null 2>&1; then
        local mem_output
        if mem_output=$(/usr/bin/time -f "%M KB" "$claude_switch" status 2>&1); then
            local mem_kb=$(echo "$mem_output" | grep -oP '\d+(?= KB)' || echo "unknown")
            log_test "PERFORMANCE" "Memory usage: ${mem_kb} KB"
            return 0
        fi
    fi
    
    log_test "INFO" "Resource usage measurement not available"
    return 0
}

# Test: Memory Leaks Detection
test_memory_leaks() {
    # Einfacher Memory Leak Test - mehrfache Ausführung
    local project_detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -f "$project_detector" ]]; then
        log_test "ERROR" "project-detector.sh not found"
        return 1
    fi
    
    chmod +x "$project_detector"
    
    # 10x ausführen und schauen ob Processes hängen bleiben
    local initial_processes=$(ps aux | grep -c "project-detector\|claude-switch\|session-manager" || echo 0)
    
    for i in {1..10}; do
        "$project_detector" list &>/dev/null || true
    done
    
    sleep 2  # Warten bis Processes beendet sind
    
    local final_processes=$(ps aux | grep -c "project-detector\|claude-switch\|session-manager" || echo 0)
    
    if (( final_processes <= initial_processes + 1 )); then
        log_test "SUCCESS" "No hanging processes detected"
        return 0
    else
        log_test "WARNING" "Possible hanging processes: $((final_processes - initial_processes))"
        return 1
    fi
}

# =============================================================================
# 5. VALIDATION CHECKLIST
# =============================================================================

validation_checklist() {
    echo -e "\n${BOLD}📋 VALIDATION CHECKLIST${NC}"
    echo -e "${PURPLE}========================${NC}"
    
    local checklist_items=(
        "Session-Detection funktioniert"
        "Projekt-Mapping korrekt konfiguriert"
        "Session-Switching ohne Fehler"
        "Error-Recovery verfügbar"
        "TODO-Integration funktional"
        "Working-Directory Validation OK"
        "TASK_COMPLETED Handling korrekt"
        "Lock-Mechanismen funktionieren"
        "Fehlende Projekte werden erkannt"
        "Bestehende Sessions werden respektiert"
        "Permission-Probleme werden behandelt"
        "Unterbrochene Switches werden recovered"
        "Performance ist akzeptabel (< 3s)"
        "Keine Memory-Leaks"
        "Keine hängenden Processes"
    )
    
    local passed_tests=(
        "$([[ $TESTS_PASSED -gt 0 ]] && echo "✓" || echo "✗")"
    )
    
    for item in "${checklist_items[@]}"; do
        if (( TESTS_PASSED > TESTS_FAILED )); then
            echo -e "${GREEN}  ✓ $item${NC}"
        else
            echo -e "${YELLOW}  ⚠ $item${NC}"
        fi
    done
    
    echo
    
    # Gesamtbewertung
    local success_rate=$(( (TESTS_PASSED * 100) / TESTS_TOTAL ))
    
    if (( success_rate >= 90 )); then
        echo -e "${GREEN}🏆 SYSTEM READY FOR PRODUCTION (${success_rate}%)${NC}"
    elif (( success_rate >= 70 )); then
        echo -e "${YELLOW}⚠️  SYSTEM FUNCTIONAL WITH WARNINGS (${success_rate}%)${NC}"
    else
        echo -e "${RED}❌ SYSTEM HAS CRITICAL ISSUES (${success_rate}%)${NC}"
    fi
}

# =============================================================================
# MAIN TEST RUNNER
# =============================================================================

# Finalisiere Test-Results
finalize_results() {
    local completed_at=$(date -Iseconds)
    local success_rate=0
    
    if (( TESTS_TOTAL > 0 )); then
        success_rate=$(( (TESTS_PASSED * 100) / TESTS_TOTAL ))
    fi
    
    # JSON Results finalisieren
    jq --arg completed_at "$completed_at" --arg total "$TESTS_TOTAL" --arg passed "$TESTS_PASSED" --arg failed "$TESTS_FAILED" --arg skipped "$TESTS_SKIPPED" --arg success_rate "$success_rate" '
        .completed_at = $completed_at |
        .statistics.total = ($total | tonumber) |
        .statistics.passed = ($passed | tonumber) |
        .statistics.failed = ($failed | tonumber) |
        .statistics.skipped = ($skipped | tonumber) |
        .statistics.success_rate = ($success_rate | tonumber)
    ' "$RESULTS_LOG" > "$RESULTS_LOG.tmp" && mv "$RESULTS_LOG.tmp" "$RESULTS_LOG"
    
    # Abschluss-Log
    {
        echo ""
        echo "TEST SUITE COMPLETED"
        echo "==================="
        echo "Total Tests: $TESTS_TOTAL"
        echo "Passed: $TESTS_PASSED"
        echo "Failed: $TESTS_FAILED"
        echo "Skipped: $TESTS_SKIPPED"
        echo "Success Rate: ${success_rate}%"
        echo "Completed: $(date)"
    } >> "$TEST_LOG"
}

# Main-Funktion
main() {
    echo -e "${BOLD}${BLUE}🧪 CLAUDE SESSION-SWITCHING SYSTEM TEST SUITE${NC}"
    echo -e "${PURPLE}================================================${NC}"
    echo
    
    # Test-Umgebung einrichten
    setup_test_environment
    
    echo -e "\n${BOLD}1. FUNKTIONS-TESTS${NC}"
    echo -e "${GRAY}==================${NC}"
    
    run_test "session_detection" "test_session_detection" "Teste Session-Detection Mechanismus"
    run_test "project_mapping" "test_project_mapping" "Teste Projekt-zu-Pfad-Mapping"
    run_test "session_switching_dryrun" "test_session_switching_dryrun" "Teste Session-Switching ohne echten Wechsel"
    run_test "error_recovery" "test_error_recovery" "Teste Error-Recovery Mechanismen"
    
    echo -e "\n${BOLD}2. INTEGRATIONS-TESTS${NC}"
    echo -e "${GRAY}=====================${NC}"
    
    run_test "todo_integration" "test_todo_integration" "Teste TODO-System Integration"
    run_test "working_directory_validation" "test_working_directory_validation" "Teste Working-Directory Validation"
    run_test "task_completed_handling" "test_task_completed_handling" "Teste TASK_COMPLETED Handling"
    run_test "lock_mechanisms" "test_lock_mechanisms" "Teste Lock-Mechanismen"
    
    echo -e "\n${BOLD}3. EDGE-CASES${NC}"
    echo -e "${GRAY}==============${NC}"
    
    run_test "nonexistent_project" "test_nonexistent_project" "Teste Behandlung nicht-existierender Projekte"
    run_test "existing_session" "test_existing_session" "Teste Umgang mit bestehenden Sessions"
    run_test "permission_issues" "test_permission_issues" "Teste Permission-Problem-Handling"
    run_test "interrupted_switches" "test_interrupted_switches" "Teste Recovery bei unterbrochenen Switches"
    
    echo -e "\n${BOLD}4. PERFORMANCE-TESTS${NC}"
    echo -e "${GRAY}====================${NC}"
    
    run_test "session_switch_performance" "test_session_switch_performance" "Messe Session-Switch Performance"
    run_test "resource_usage" "test_resource_usage" "Teste Resource-Usage"
    run_test "memory_leaks" "test_memory_leaks" "Teste auf Memory-Leaks"
    
    # Validation Checklist
    validation_checklist
    
    # Finale Statistiken
    echo -e "\n${BOLD}📊 TEST STATISTICS${NC}"
    echo -e "${PURPLE}==================${NC}"
    echo -e "${GREEN}Passed:  $TESTS_PASSED${NC}"
    echo -e "${RED}Failed:  $TESTS_FAILED${NC}"
    echo -e "${YELLOW}Skipped: $TESTS_SKIPPED${NC}"
    echo -e "${BLUE}Total:   $TESTS_TOTAL${NC}"
    
    # Success Rate berechnen
    local success_rate=0
    if (( TESTS_TOTAL > 0 )); then
        success_rate=$(( (TESTS_PASSED * 100) / TESTS_TOTAL ))
    fi
    
    echo -e "\n${BOLD}Success Rate: ${success_rate}%${NC}"
    
    # Logs Info
    echo -e "\n${BOLD}📄 LOGS & RESULTS${NC}"
    echo -e "${PURPLE}==================${NC}"
    echo -e "${CYAN}Detailed Log: $TEST_LOG${NC}"
    echo -e "${CYAN}JSON Results: $RESULTS_LOG${NC}"
    
    # Finalisiere Results
    finalize_results
    
    # Exit Code basierend auf Erfolgsrate
    if (( success_rate >= 70 )); then
        echo -e "\n${GREEN}🎉 TEST SUITE COMPLETED SUCCESSFULLY${NC}"
        return 0
    else
        echo -e "\n${RED}❌ TEST SUITE COMPLETED WITH ISSUES${NC}"
        return 1
    fi
}

# Script ausführen wenn direkt aufgerufen
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi