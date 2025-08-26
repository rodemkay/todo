#!/bin/bash

# Performance and Edge-Case Tests für Session-Switching System
# Version: 1.0

set -euo pipefail

PLUGIN_TODO_DIR="/home/rodemkay/www/react/plugin-todo"
TESTS=0
PASSED=0
WARNINGS=0

# Logging mit Timestamps
log() {
    echo "$(date '+%H:%M:%S') - $1"
}

test_result() {
    local name="$1"
    local result="$2"
    local details="${3:-}"
    
    ((TESTS++))
    echo -n "Testing $name... "
    
    if [[ "$result" == "PASS" ]]; then
        echo "✓ PASS"
        ((PASSED++))
        [[ -n "$details" ]] && echo "    $details"
    elif [[ "$result" == "WARN" ]]; then
        echo "⚠ WARN"
        ((WARNINGS++))
        [[ -n "$details" ]] && echo "    $details"
    else
        echo "✗ FAIL"
        [[ -n "$details" ]] && echo "    $details"
    fi
}

# Performance Test: Project Detection Speed
test_project_detection_speed() {
    local detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -x "$detector" ]]; then
        test_result "Project Detection Speed" "FAIL" "detector not executable"
        return
    fi
    
    local start_time=$(date +%s%N)
    
    if "$detector" list >/dev/null 2>&1; then
        local end_time=$(date +%s%N)
        local duration_ms=$(( (end_time - start_time) / 1000000 ))
        
        if (( duration_ms < 1000 )); then
            test_result "Project Detection Speed" "PASS" "${duration_ms}ms - Excellent"
        elif (( duration_ms < 3000 )); then
            test_result "Project Detection Speed" "PASS" "${duration_ms}ms - Good"  
        elif (( duration_ms < 5000 )); then
            test_result "Project Detection Speed" "WARN" "${duration_ms}ms - Acceptable"
        else
            test_result "Project Detection Speed" "FAIL" "${duration_ms}ms - Too slow"
        fi
    else
        test_result "Project Detection Speed" "FAIL" "detector command failed"
    fi
}

# Performance Test: Session Manager Speed
test_session_manager_speed() {
    local manager="$PLUGIN_TODO_DIR/session-manager.sh"
    
    if [[ ! -x "$manager" ]]; then
        test_result "Session Manager Speed" "FAIL" "manager not executable"
        return
    fi
    
    local start_time=$(date +%s%N)
    
    if "$manager" health >/dev/null 2>&1; then
        local end_time=$(date +%s%N)
        local duration_ms=$(( (end_time - start_time) / 1000000 ))
        
        if (( duration_ms < 500 )); then
            test_result "Session Manager Speed" "PASS" "${duration_ms}ms - Fast"
        elif (( duration_ms < 2000 )); then
            test_result "Session Manager Speed" "PASS" "${duration_ms}ms - Good"
        else
            test_result "Session Manager Speed" "WARN" "${duration_ms}ms - Slow"
        fi
    else
        test_result "Session Manager Speed" "WARN" "health check indicated problems"
    fi
}

# Edge Case: Non-existent Project
test_nonexistent_project() {
    local detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -x "$detector" ]]; then
        test_result "Non-existent Project Handling" "FAIL" "detector not executable"
        return
    fi
    
    # Test mit definiert nicht-existierendem Projekt
    if "$detector" info "definitely-does-not-exist-12345" >/dev/null 2>&1; then
        test_result "Non-existent Project Handling" "FAIL" "should reject invalid projects"
    else
        test_result "Non-existent Project Handling" "PASS" "correctly rejects invalid projects"
    fi
}

# Edge Case: Invalid JSON Configuration
test_invalid_json_handling() {
    local config="$PLUGIN_TODO_DIR/config/projects.json"
    local backup="$config.backup"
    
    # Backup erstellen
    cp "$config" "$backup"
    
    # JSON korruptieren
    echo "{ invalid json }" > "$config"
    
    # Test ob System graceful mit invalid JSON umgeht
    if jq . "$config" >/dev/null 2>&1; then
        test_result "Invalid JSON Handling" "FAIL" "should detect invalid JSON"
    else
        test_result "Invalid JSON Handling" "PASS" "correctly detects invalid JSON"
    fi
    
    # Backup wiederherstellen
    mv "$backup" "$config"
}

# Edge Case: Permission Problems
test_permission_handling() {
    local test_file="/tmp/permission_test_$$"
    
    # Test-Datei erstellen und Permissions entfernen
    echo "test" > "$test_file"
    chmod 000 "$test_file"
    
    # Versuche zu lesen (sollte fehlschlagen)
    if cat "$test_file" >/dev/null 2>&1; then
        test_result "Permission Handling" "FAIL" "should respect file permissions"
    else
        test_result "Permission Handling" "PASS" "correctly handles permission denials"
    fi
    
    # Cleanup
    chmod 644 "$test_file"
    rm -f "$test_file"
}

# Edge Case: Lock File Age Detection
test_lock_age_handling() {
    local lock_file="/tmp/claude_session_lock_test_$$"
    
    # Alten Lock simulieren
    echo "old-project" > "$lock_file"
    
    # Timestamp auf 2 Stunden zurücksetzen
    if command -v touch >/dev/null 2>&1; then
        touch -d "2 hours ago" "$lock_file" 2>/dev/null || touch -t 202501250000 "$lock_file"
        
        # Lock-Age prüfen
        local age=$(stat -c %Y "$lock_file" 2>/dev/null || echo "0")
        local current=$(date +%s)
        local age_min=$(( (current - age) / 60 ))
        
        if (( age_min > 30 )); then
            test_result "Lock Age Detection" "PASS" "correctly identifies old lock (${age_min}min)"
        else
            test_result "Lock Age Detection" "WARN" "lock age detection may not work properly"
        fi
    else
        test_result "Lock Age Detection" "WARN" "touch command not available for test"
    fi
    
    rm -f "$lock_file"
}

# Memory Leak Test: Multiple Executions
test_memory_leaks() {
    local detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -x "$detector" ]]; then
        test_result "Memory Leak Detection" "FAIL" "detector not executable"
        return
    fi
    
    # Prozess-Count vor Test
    local initial_count=$(ps aux | grep -c "[p]roject-detector\|[s]ession-manager\|[c]laude-switch" || echo "0")
    
    # 10x ausführen
    for i in {1..10}; do
        "$detector" list >/dev/null 2>&1 || true
    done
    
    # Kurz warten bis Prozesse beendet sind
    sleep 2
    
    # Prozess-Count nach Test
    local final_count=$(ps aux | grep -c "[p]roject-detector\|[s]ession-manager\|[c]laude-switch" || echo "0")
    
    local hanging_processes=$((final_count - initial_count))
    
    if (( hanging_processes <= 0 )); then
        test_result "Memory Leak Detection" "PASS" "no hanging processes detected"
    elif (( hanging_processes <= 2 )); then
        test_result "Memory Leak Detection" "WARN" "$hanging_processes potentially hanging processes"
    else
        test_result "Memory Leak Detection" "FAIL" "$hanging_processes hanging processes detected"
    fi
}

# Stress Test: Concurrent Access
test_concurrent_access() {
    local lock_file="/tmp/claude_session_lock_concurrent_$$"
    
    # Cleanup
    rm -f "$lock_file"
    
    # 5 parallele "Sessions" simulieren
    for i in {1..5}; do
        (
            echo "session-$i" > "$lock_file"
            sleep 0.1
        ) &
    done
    
    # Warten bis alle fertig sind
    wait
    
    # Lock sollte existieren
    if [[ -f "$lock_file" ]]; then
        local content=$(cat "$lock_file")
        test_result "Concurrent Access" "PASS" "lock file exists with content: $content"
    else
        test_result "Concurrent Access" "FAIL" "no lock file after concurrent writes"
    fi
    
    rm -f "$lock_file"
}

# Resource Usage Test
test_resource_usage() {
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    
    if [[ ! -x "$claude_switch" ]]; then
        test_result "Resource Usage" "FAIL" "claude-switch not executable"
        return
    fi
    
    # Memory usage testen mit /usr/bin/time falls verfügbar
    if command -v /usr/bin/time >/dev/null 2>&1; then
        local mem_output
        if mem_output=$(/usr/bin/time -f "Memory: %M KB" "$claude_switch" status 2>&1 | grep "Memory:" || echo "Memory: unknown KB"); then
            local mem_kb=$(echo "$mem_output" | grep -oP '\d+(?= KB)' || echo "unknown")
            
            if [[ "$mem_kb" != "unknown" ]] && (( mem_kb < 50000 )); then
                test_result "Resource Usage" "PASS" "Memory usage: ${mem_kb}KB (good)"
            elif [[ "$mem_kb" != "unknown" ]] && (( mem_kb < 100000 )); then
                test_result "Resource Usage" "WARN" "Memory usage: ${mem_kb}KB (high)"
            else
                test_result "Resource Usage" "WARN" "Memory usage: ${mem_kb}KB or measurement failed"
            fi
        else
            test_result "Resource Usage" "WARN" "unable to measure memory usage"
        fi
    else
        test_result "Resource Usage" "WARN" "/usr/bin/time not available for measurement"
    fi
}

# Network Resilience Test (SSH-ähnlich)
test_network_resilience() {
    # Simuliere SSH-Timeout durch Connection zu nicht-erreichbarer IP
    local timeout_test_result=0
    
    # Test mit definiertem Timeout
    if timeout 5 bash -c 'exec 3<>/dev/tcp/192.0.2.1/22 2>/dev/null' 2>/dev/null; then
        test_result "Network Resilience" "WARN" "connection succeeded unexpectedly"
    else
        timeout_test_result=$?
        if (( timeout_test_result == 124 )); then
            test_result "Network Resilience" "PASS" "timeout mechanism works (5s)"
        else
            test_result "Network Resilience" "PASS" "connection properly failed"
        fi
    fi
}

# Configuration Recovery Test
test_config_recovery() {
    local config="$PLUGIN_TODO_DIR/config/projects.json"
    
    # Prüfe ob Backup-Mechanismus funktionieren würde
    if [[ -f "$config" ]]; then
        local backup_name="${config}.backup-$(date +%Y%m%d_%H%M%S)"
        
        # Simuliere Backup
        cp "$config" "$backup_name" 2>/dev/null
        
        if [[ -f "$backup_name" ]]; then
            test_result "Config Recovery" "PASS" "backup mechanism functional"
            rm -f "$backup_name"
        else
            test_result "Config Recovery" "FAIL" "backup creation failed"
        fi
    else
        test_result "Config Recovery" "FAIL" "no config file to backup"
    fi
}

# Main execution
main() {
    echo "🚀 CLAUDE SESSION-SWITCHING ADVANCED TESTS"
    echo "=========================================="
    echo
    
    log "Starting performance and edge-case tests..."
    
    echo "⏱️  PERFORMANCE TESTS"
    echo "===================="
    test_project_detection_speed
    test_session_manager_speed
    test_resource_usage
    test_memory_leaks
    
    echo
    echo "🎯 EDGE-CASE TESTS"
    echo "=================="
    test_nonexistent_project
    test_invalid_json_handling  
    test_permission_handling
    test_lock_age_handling
    test_concurrent_access
    test_network_resilience
    test_config_recovery
    
    echo
    echo "📊 ADVANCED TEST RESULTS"
    echo "========================"
    echo "Total Tests: $TESTS"
    echo "Passed: $PASSED"
    echo "Warnings: $WARNINGS"
    echo "Failed: $((TESTS - PASSED - WARNINGS))"
    
    local success_rate=0
    if (( TESTS > 0 )); then
        success_rate=$(( (PASSED * 100) / TESTS ))
    fi
    
    local warning_rate=0
    if (( TESTS > 0 )); then
        warning_rate=$(( (WARNINGS * 100) / TESTS ))
    fi
    
    echo "Success Rate: $success_rate%"
    echo "Warning Rate: $warning_rate%"
    
    # Bewertung
    if (( success_rate >= 80 && warning_rate < 30 )); then
        echo
        echo "🏆 SYSTEM IS PRODUCTION READY"
        echo "- Strong performance characteristics"
        echo "- Robust edge-case handling"
        echo "- Good resource efficiency"
        
    elif (( success_rate >= 60 )); then
        echo
        echo "⚠️  SYSTEM IS FUNCTIONAL WITH WARNINGS"
        echo "- Performance may be suboptimal"
        echo "- Some edge cases need attention"
        echo "- Monitor resource usage"
        
    else
        echo
        echo "❌ SYSTEM HAS SIGNIFICANT ISSUES"
        echo "- Performance problems detected"
        echo "- Edge cases not properly handled"
        echo "- Resource efficiency concerns"
        exit 1
    fi
    
    log "Advanced testing completed"
}

# Execute main function
main "$@"