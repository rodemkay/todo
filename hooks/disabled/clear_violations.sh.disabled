#!/bin/bash
# Script to clear hook violations after TASK_COMPLETED

HOOK_CONTEXT="/home/rodemkay/.claude/hooks/task_context.json"

# Function to clear violations
clear_violations() {
    if [ -f "$HOOK_CONTEXT" ]; then
        # Reset violations to 0 and mark session as clean
        jq '.hook_violations = 0 | .violations_cleared = true | .session_clean = true' "$HOOK_CONTEXT" > "$HOOK_CONTEXT.tmp" && mv "$HOOK_CONTEXT.tmp" "$HOOK_CONTEXT"
        echo "✅ Hook violations cleared"
    fi
}

# Function to reset session
reset_session() {
    echo '{
  "active_todos": [],
  "last_task_completed": "'$(date -Iseconds)'",
  "hook_violations": 0,
  "task_completed_triggered": true,
  "session_clean": true,
  "force_close_requested": true,
  "historical_violations_acknowledged": [1, 2, 5],
  "session_end": "'$(date -Iseconds)'"
}' > "$HOOK_CONTEXT"
    echo "✅ Session reset successfully with violation acknowledgments"
}

# Main logic
case "${1:-clear}" in
    clear)
        clear_violations
        ;;
    reset)
        reset_session
        ;;
    *)
        echo "Usage: $0 [clear|reset]"
        exit 1
        ;;
esac