#!/bin/bash

# Kitty Session Manager for Claude Projects
# Usage: ./kitty-session-manager.sh [action] [project/params]

ACTION=${1:-help}
PROJECT=${2:-todo}
KITTY_SOCKET="unix:/tmp/kitty-$USER"

# Project directories
declare -A PROJECTS=(
    ["todo"]="/home/rodemkay/www/react/todo"
    ["forex"]="/home/rodemkay/www/react/forexsignale"
    ["trading"]="/home/rodemkay/trading"
    ["staging"]="/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging"
)

case $ACTION in
    list)
        echo "📂 Available Claude Projects:"
        for proj in "${!PROJECTS[@]}"; do
            echo "  - $proj: ${PROJECTS[$proj]}"
        done
        # Show active Kitty windows
        echo -e "\n📺 Active Kitty Windows:"
        kitty @ --to $KITTY_SOCKET ls | grep -E "title:|id:" | paste - - | column -t
        ;;
    
    create)
        if [[ -z "${PROJECTS[$PROJECT]}" ]]; then
            echo "❌ Unknown project: $PROJECT"
            exit 1
        fi
        echo "🚀 Creating new Claude session for $PROJECT..."
        kitty @ --to $KITTY_SOCKET new-window \
            --new-tab \
            --tab-title "claude-$PROJECT" \
            --cwd "${PROJECTS[$PROJECT]}"
        sleep 1
        kitty @ --to $KITTY_SOCKET send-text \
            --match title:"claude-$PROJECT" \
            "cd ${PROJECTS[$PROJECT]} && claude\n"
        echo "✅ Session 'claude-$PROJECT' created"
        ;;
    
    switch)
        echo "🔄 Switching to claude-$PROJECT..."
        kitty @ --to $KITTY_SOCKET focus-tab --match title:"claude-$PROJECT"
        if [ $? -eq 0 ]; then
            echo "✅ Switched to $PROJECT"
        else
            echo "❌ Session 'claude-$PROJECT' not found. Create it first with: $0 create $PROJECT"
        fi
        ;;
    
    resize)
        # Format: left:right (e.g., 10:90)
        IFS=':' read -r LEFT RIGHT <<< "$PROJECT"
        if [[ -z "$LEFT" ]] || [[ -z "$RIGHT" ]]; then
            echo "❌ Usage: $0 resize LEFT:RIGHT (e.g., $0 resize 10:90)"
            exit 1
        fi
        
        echo "📐 Resizing layout to $LEFT% left, $RIGHT% right..."
        
        # Calculate increment from 50/50 baseline
        DIFF=$((50 - LEFT))
        
        # Resize windows
        kitty @ --to $KITTY_SOCKET resize-window \
            --match num:0 \
            --axis horizontal \
            --increment "-$DIFF"
        
        echo "✅ Layout resized"
        ;;
    
    reset-layout)
        echo "🔄 Resetting to 50/50 layout..."
        kitty @ --to $KITTY_SOCKET goto-layout tall
        echo "✅ Layout reset"
        ;;
    
    send)
        # Send command to specific project session
        COMMAND="${@:3}"
        echo "📤 Sending to claude-$PROJECT: $COMMAND"
        kitty @ --to $KITTY_SOCKET send-text \
            --match title:"claude-$PROJECT" \
            "$COMMAND\n"
        ;;
    
    current)
        # Show current active window
        echo "📍 Current active window:"
        kitty @ --to $KITTY_SOCKET ls | grep -A5 "is_focused.*true"
        ;;
    
    help|*)
        cat << EOF
🎯 Kitty Session Manager for Claude Projects

Usage: $0 [action] [params]

Actions:
  list                - List all projects and active sessions
  create [project]    - Create new Claude session for project
  switch [project]    - Switch to existing project session
  resize [L:R]        - Resize layout (e.g., 10:90 for 10% left, 90% right)
  reset-layout        - Reset to 50/50 layout
  send [proj] [cmd]   - Send command to specific project session
  current             - Show current active window
  help                - Show this help

Projects:
  todo     - Todo Plugin Project
  forex    - ForexSignale Magazine
  trading  - Trading Bot Project
  staging  - Staging WordPress

Examples:
  $0 create forex           # Create new session for forex project
  $0 switch todo            # Switch to todo project session
  $0 resize 10:90           # Make left pane 10%, right 90%
  $0 send todo "./todo"     # Send command to todo session

EOF
        ;;
esac