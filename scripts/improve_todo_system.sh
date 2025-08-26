#!/bin/bash
# Verbesserungen für das Todo-System

# 1. Quick Mode - Kompakte Ausgabe
todo_quick() {
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT id, title, priority FROM stage_project_todos \
        WHERE status=\"offen\" AND bearbeiten=1 \
        ORDER BY FIELD(priority, \"hoch\", \"mittel\", \"niedrig\"), id \
        LIMIT 1' --format=json" | \
    jq -r '.[0] | "📋 #\(.id): \(.title) [\(.priority)]"'
}

# 2. Batch Mode - Mehrere Tasks
todo_batch() {
    local count=${1:-5}
    echo "📦 Loading $count tasks..."
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT id, title, priority, scope FROM stage_project_todos \
        WHERE status=\"offen\" AND bearbeiten=1 \
        ORDER BY FIELD(priority, \"hoch\", \"mittel\", \"niedrig\"), id \
        LIMIT $count'"
}

# 3. Stats - Session Statistiken
todo_stats() {
    echo "📊 TODO SYSTEM STATS"
    echo "─────────────────────"
    
    # Counts
    local completed=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"completed\"' --skip-column-names")
    local in_progress=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"in_progress\"' --skip-column-names")
    local pending=$(ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT COUNT(*) FROM stage_project_todos WHERE status=\"offen\"' --skip-column-names")
    
    echo "✅ Completed: $completed"
    echo "🔄 In Progress: $in_progress"
    echo "⏳ Pending: $pending"
    
    # Recent activity
    echo ""
    echo "📅 Recent Completions:"
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT id, title, completed_at FROM stage_project_todos \
        WHERE status=\"completed\" AND completed_at IS NOT NULL \
        ORDER BY completed_at DESC LIMIT 5'"
}

# 4. Smart Cleanup
todo_cleanup_smart() {
    echo "🧹 Smart Cleanup..."
    
    # Reset old in_progress (>2 hours)
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'UPDATE stage_project_todos \
        SET status=\"offen\", execution_started_at=NULL \
        WHERE status=\"in_progress\" \
        AND TIMESTAMPDIFF(HOUR, execution_started_at, NOW()) > 2'"
    
    # Reset tasks without start time
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'UPDATE stage_project_todos \
        SET status=\"offen\" \
        WHERE status=\"in_progress\" \
        AND execution_started_at IS NULL'"
    
    echo "✅ Cleanup complete"
}

# 5. Priority Queue
todo_priority() {
    echo "🎯 PRIORITY QUEUE"
    echo "─────────────────────"
    
    echo "🔴 HIGH Priority:"
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT id, title FROM stage_project_todos \
        WHERE status=\"offen\" AND priority=\"hoch\" AND bearbeiten=1 \
        LIMIT 3'"
    
    echo ""
    echo "🟡 MEDIUM Priority:"
    ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
        wp db query 'SELECT id, title FROM stage_project_todos \
        WHERE status=\"offen\" AND priority=\"mittel\" AND bearbeiten=1 \
        LIMIT 3'"
}

# Main menu
case "${1:-help}" in
    quick)
        todo_quick
        ;;
    batch)
        todo_batch ${2:-5}
        ;;
    stats)
        todo_stats
        ;;
    cleanup)
        todo_cleanup_smart
        ;;
    priority)
        todo_priority
        ;;
    *)
        echo "📋 TODO System Improvements"
        echo "Usage: $0 {quick|batch|stats|cleanup|priority}"
        echo ""
        echo "  quick    - Kompakte Ausgabe der nächsten Task"
        echo "  batch N  - Lade N Tasks auf einmal"
        echo "  stats    - Zeige Session-Statistiken"
        echo "  cleanup  - Smart Cleanup stuck tasks"
        echo "  priority - Zeige Priority Queue"
        ;;
esac