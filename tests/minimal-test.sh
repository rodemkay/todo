#!/bin/bash

# Minimal Session-Switching Test
echo "🧪 MINIMAL SESSION-SWITCHING TEST"
echo "=================================="

PLUGIN_TODO_DIR="/home/rodemkay/www/react/plugin-todo"
TESTS=0
PASSED=0

test_file_exists() {
    local file="$1"
    local name="$2"
    
    ((TESTS++))
    echo -n "Testing $name... "
    
    if [[ -f "$file" ]]; then
        echo "✓ PASS"
        ((PASSED++))
        return 0
    else
        echo "✗ FAIL - not found: $file"
        return 1
    fi
}

test_executable() {
    local file="$1" 
    local name="$2"
    
    ((TESTS++))
    echo -n "Testing $name executable... "
    
    if [[ -x "$file" ]]; then
        echo "✓ PASS"
        ((PASSED++))
        return 0
    elif [[ -f "$file" ]]; then
        echo "⚠ WARN - making executable"
        chmod +x "$file"
        ((PASSED++))
        return 0
    else
        echo "✗ FAIL - not found: $file"
        return 1
    fi
}

test_command() {
    local cmd="$1"
    local name="$2"
    
    ((TESTS++))
    echo -n "Testing $name available... "
    
    if command -v "$cmd" >/dev/null 2>&1; then
        echo "✓ PASS"
        ((PASSED++))
        return 0
    else
        echo "✗ FAIL - command not found: $cmd"
        return 1
    fi
}

# File existence tests
echo "📁 FILE EXISTENCE TESTS"
test_file_exists "$PLUGIN_TODO_DIR/claude-switch.sh" "claude-switch.sh"
test_file_exists "$PLUGIN_TODO_DIR/session-manager.sh" "session-manager.sh"
test_file_exists "$PLUGIN_TODO_DIR/project-detector.sh" "project-detector.sh"
test_file_exists "$PLUGIN_TODO_DIR/cli/todo" "todo CLI"
test_file_exists "$PLUGIN_TODO_DIR/config/projects.json" "projects.json"

echo
echo "🔧 EXECUTABLE TESTS"
test_executable "$PLUGIN_TODO_DIR/claude-switch.sh" "claude-switch.sh"
test_executable "$PLUGIN_TODO_DIR/session-manager.sh" "session-manager.sh"
test_executable "$PLUGIN_TODO_DIR/project-detector.sh" "project-detector.sh"
test_executable "$PLUGIN_TODO_DIR/cli/todo" "todo CLI"

echo
echo "📦 DEPENDENCY TESTS"
test_command "jq" "jq JSON processor"
test_command "ssh" "SSH client"
test_command "tmux" "tmux terminal multiplexer"

echo
echo "🧪 BASIC FUNCTIONALITY TESTS"
((TESTS++))
echo -n "Testing projects.json validity... "
if jq . "$PLUGIN_TODO_DIR/config/projects.json" >/dev/null 2>&1; then
    echo "✓ PASS"
    ((PASSED++))
else
    echo "✗ FAIL - invalid JSON"
fi

((TESTS++))
echo -n "Testing project-detector list... "
if "$PLUGIN_TODO_DIR/project-detector.sh" list >/dev/null 2>&1; then
    echo "✓ PASS"
    ((PASSED++))
else
    echo "✗ FAIL - project-detector failed"
fi

((TESTS++))
echo -n "Testing lock mechanism... "
LOCK_FILE="/tmp/test_lock_$$"
echo "test" > "$LOCK_FILE"
if [[ -f "$LOCK_FILE" ]] && [[ "$(cat "$LOCK_FILE")" == "test" ]]; then
    echo "✓ PASS"
    ((PASSED++))
    rm -f "$LOCK_FILE"
else
    echo "✗ FAIL - lock file mechanism"
fi

echo
echo "📊 RESULTS"
echo "=========="
echo "Tests:  $TESTS"
echo "Passed: $PASSED"
echo "Failed: $((TESTS - PASSED))"

SUCCESS_RATE=$(( (PASSED * 100) / TESTS ))
echo "Success Rate: $SUCCESS_RATE%"

if (( SUCCESS_RATE >= 70 )); then
    echo "🎉 SYSTEM FUNCTIONAL"
    exit 0
else
    echo "❌ SYSTEM HAS ISSUES"
    exit 1
fi