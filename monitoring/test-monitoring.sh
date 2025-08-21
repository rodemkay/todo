#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "========================================="
echo "WEBHOOK MONITORING SYSTEM TEST"
echo "========================================="

# Test 1: Basic monitor functionality
echo "Test 1: Monitor functionality..."
if python3 "$SCRIPT_DIR/webhook-monitor.py" --dashboard > /tmp/monitor_test.json; then
    echo "✅ Monitor script working"
else
    echo "❌ Monitor script failed"
fi

# Test 2: Log manager
echo ""
echo "Test 2: Log manager functionality..."
if python3 "$SCRIPT_DIR/log-manager.py" --status > /tmp/logmgr_test.json; then
    echo "✅ Log manager working"
else
    echo "❌ Log manager failed"
fi

# Test 3: Queue manager
echo ""
echo "Test 3: Queue manager functionality..."
if python3 "$SCRIPT_DIR/queue-manager.py" --status > /tmp/queue_test.json; then
    echo "✅ Queue manager working"
else
    echo "❌ Queue manager failed"
fi

# Test 4: Load tester (quick test)
echo ""
echo "Test 4: Load tester (quick test)..."
if python3 "$SCRIPT_DIR/load-test.py" --test-type custom --duration 10 --users 2 --requests 5 > /tmp/loadtest_results.txt; then
    echo "✅ Load tester working"
    echo "   Results saved to /tmp/loadtest_results.txt"
else
    echo "❌ Load tester failed"
fi

echo ""
echo "Test completed. Check /tmp/*_test.json for detailed results."
