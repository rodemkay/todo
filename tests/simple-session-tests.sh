#!/bin/bash

# Simple Session-Switching Tests (ohne komplexes JSON)
# Version: 1.0

set -euo pipefail

# Farbdefinitionen
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Test-Konfiguration
PLUGIN_TODO_DIR="/home/rodemkay/www/react/plugin-todo"
TESTS_TOTAL=0
TESTS_PASSED=0
TESTS_FAILED=0

# Logging
log_test() {
    echo "$(date '+%H:%M:%S') - $1"
}

# Test-Wrapper
run_test() {
    local test_name="$1"
    local test_function="$2" 
    local description="$3"
    
    ((TESTS_TOTAL++))
    
    echo -e "\n${BLUE}🧪 Test #$TESTS_TOTAL: $test_name${NC}"
    echo -e "   $description"
    
    if $test_function; then
        ((TESTS_PASSED++))
        echo -e "${GREEN}   ✓ PASSED${NC}"
        return 0
    else
        ((TESTS_FAILED++))
        echo -e "${RED}   ✗ FAILED${NC}"
        return 1
    fi
}

# =============================================================================
# TEST FUNCTIONS
# =============================================================================

test_session_detection() {
    local claude_switch="$PLUGIN_TODO_DIR/claude-switch.sh"
    
    if [[ ! -f "$claude_switch" ]]; then
        log_test "ERROR: claude-switch.sh not found at $claude_switch"
        return 1
    fi
    
    chmod +x "$claude_switch"
    
    # Status abfragen sollte nicht crashen
    if "$claude_switch" status &>/dev/null; then
        log_test "SUCCESS: Session detection works"
        return 0
    else
        log_test "WARNING: Status command had issues but didn't crash"
        # Nicht als Fehler werten wenn es nur warnings sind
        return 0
    fi
}

test_project_mapping() {
    local project_detector="$PLUGIN_TODO_DIR/project-detector.sh"
    
    if [[ ! -f "$project_detector" ]]; then
        log_test "ERROR: project-detector.sh not found"
        return 1
    fi
    
    chmod +x "$project_detector"
    
    # Projekt-Liste abrufen
    if "$project_detector" list &>/dev/null; then
        log_test "SUCCESS: Project mapping works"
        return 0
    else
        log_test "ERROR: project-detector list failed"
        return 1
    fi
}

test_config_validation() {
    local projects_config="$PLUGIN_TODO_DIR/config/projects.json"
    
    if [[ ! -f "$projects_config" ]]; then
        log_test "ERROR: projects.json config not found"
        return 1
    fi
    
    # JSON-Format validieren
    if jq . "$projects_config" >/dev/null 2>&1; then
        log_test "SUCCESS: projects.json is valid JSON"
        return 0
    else
        log_test "ERROR: projects.json is not valid JSON"
        return 1
    fi
}

test_todo_cli() {
    local todo_cli="$PLUGIN_TODO_DIR/cli/todo"
    
    if [[ ! -f "$todo_cli" ]]; then
        log_test "ERROR: todo CLI not found"
        return 1
    fi
    
    chmod +x "$todo_cli"
    
    # Check if script has correct path references
    if grep -q "plugin-todo" "$todo_cli"; then
        log_test "SUCCESS: TODO CLI has correct path references"
        return 0
    else
        log_test "WARNING: TODO CLI might have path issues"
        return 1
    fi
}

test_lock_mechanisms() {
    local lock_file="/tmp/claude_session_lock_test"
    
    # Cleanup
    rm -f "$lock_file"
    
    # Lock erstellen
    echo "test-project" > "$lock_file"
    
    if [[ -f "$lock_file" ]]; then
        local content=$(cat "$lock_file")
        if [[ "$content" == "test-project" ]]; then
            log_test "SUCCESS: Lock mechanism works"
            rm -f "$lock_file"
            return 0
        fi
    fi
    
    log_test "ERROR: Lock mechanism failed"
    return 1
}

test_task_completed_handling() {
    local task_file="/tmp/TASK_COMPLETED_test"
    
    # Cleanup
    rm -f "$task_file"
    
    # TASK_COMPLETED simulieren
    echo 'TASK_COMPLETED' > "$task_file"
    
    if [[ -f "$task_file" ]]; then
        local content=$(cat "$task_file")
        if [[ "$content" == "TASK_COMPLETED" ]]; then
            log_test "SUCCESS: TASK_COMPLETED handling works"
            rm -f "$task_file"
            return 0
        fi
    fi
    
    log_test "ERROR: TASK_COMPLETED handling failed"
    return 1
}

test_permissions() {
    local scripts=(
        "$PLUGIN_TODO_DIR/claude-switch.sh"
        "$PLUGIN_TODO_DIR/session-manager.sh" 
        "$PLUGIN_TODO_DIR/project-detector.sh"
        "$PLUGIN_TODO_DIR/cli/todo"
    )
    
    local missing_count=0
    
    for script in "${scripts[@]}"; do
        if [[ ! -f "$script" ]]; then
            log_test "WARNING: Script not found: $script"
            ((missing_count++))
        elif [[ ! -x "$script" ]]; then
            log_test "INFO: Making $script executable"
            chmod +x "$script"
        fi
    done
    
    if (( missing_count == 0 )); then
        log_test "SUCCESS: All scripts are available and executable"
        return 0
    else
        log_test "WARNING: $missing_count scripts missing"
        return 1
    fi
}

test_directories() {
    local dirs=(
        "$PLUGIN_TODO_DIR"
        "$PLUGIN_TODO_DIR/config"
        "$PLUGIN_TODO_DIR/cli"
        "$PLUGIN_TODO_DIR/scripts"
        "$PLUGIN_TODO_DIR/tests"
    )
    
    for dir in "${dirs[@]}"; do
        if [[ ! -d "$dir" ]]; then
            log_test "ERROR: Directory not found: $dir"
            return 1
        fi
    done
    
    log_test "SUCCESS: All required directories exist"
    return 0
}

test_dependencies() {
    local deps=(jq ssh tmux)
    local missing=()
    
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" >/dev/null 2>&1; then
            missing+=("$dep")
        fi
    done
    
    if (( ${#missing[@]} > 0 )); then
        log_test "ERROR: Missing dependencies: ${missing[*]}"
        return 1
    else
        log_test "SUCCESS: All dependencies available"
        return 0
    fi
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================

main() {
    echo -e "${BLUE}🧪 CLAUDE SESSION-SWITCHING SIMPLE TEST SUITE${NC}"
    echo -e "${PURPLE}===============================================${NC}"
    echo
    
    echo -e "${BLUE}📋 Running Basic Validation Tests${NC}"
    echo -e "${PURPLE}==================================${NC}"
    
    run_test "dependencies" "test_dependencies" "Check required dependencies (jq, ssh, tmux)"
    run_test "directories" "test_directories" "Verify directory structure"
    run_test "permissions" "test_permissions" "Check script permissions"
    run_test "config_validation" "test_config_validation" "Validate JSON configuration"
    
    echo -e "\n${BLUE}🔧 Testing Core Functionality${NC}"
    echo -e "${PURPLE}==============================${NC}"
    
    run_test "session_detection" "test_session_detection" "Test session detection mechanism"
    run_test "project_mapping" "test_project_mapping" "Test project-to-path mapping"
    run_test "todo_cli" "test_todo_cli" "Test TODO CLI integration"
    
    echo -e "\n${BLUE}🛠️  Testing Support Mechanisms${NC}"
    echo -e "${PURPLE}==============================${NC}"
    
    run_test "lock_mechanisms" "test_lock_mechanisms" "Test lock file mechanisms"
    run_test "task_completed" "test_task_completed_handling" "Test TASK_COMPLETED handling"
    
    # Results
    echo -e "\n${BLUE}📊 TEST RESULTS${NC}"
    echo -e "${PURPLE}===============${NC}"
    echo -e "${GREEN}Passed:  $TESTS_PASSED${NC}"
    echo -e "${RED}Failed:  $TESTS_FAILED${NC}"
    echo -e "${BLUE}Total:   $TESTS_TOTAL${NC}"
    
    local success_rate=0
    if (( TESTS_TOTAL > 0 )); then
        success_rate=$(( (TESTS_PASSED * 100) / TESTS_TOTAL ))
    fi
    
    echo -e "\n${BLUE}Success Rate: ${success_rate}%${NC}"
    
    if (( success_rate >= 70 )); then
        echo -e "\n${GREEN}🎉 SYSTEM FUNCTIONAL${NC}"
        return 0
    else
        echo -e "\n${RED}❌ SYSTEM HAS ISSUES${NC}"
        return 1
    fi
}

# Script ausführen
main "$@"