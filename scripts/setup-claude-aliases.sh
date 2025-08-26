#!/bin/bash

# Setup Script für Claude Code Projekt-Wechsel Aliases

echo "🔧 Claude Code Alias Setup"
echo "=========================="
echo ""
echo "Füge diese Zeilen zu deiner ~/.bashrc oder ~/.zshrc hinzu:"
echo ""
echo "# Claude Code Project Management"
echo "alias claude-switch='/home/rodemkay/www/react/plugin-todo/scripts/claude-switch-project.sh'"
echo "alias claude-tmux='/home/rodemkay/www/react/plugin-todo/scripts/claude-tmux-manager.sh'"
echo ""
echo "# Schnell-Wechsel zu Projekten"
echo "alias claude-todo='cd /home/rodemkay/www/react/plugin-todo && claude --resume --dangerously-skip-permissions'"
echo "alias claude-forex='cd /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging && claude --resume --dangerously-skip-permissions'"
echo "alias claude-brain='cd /home/rodemkay/www/react/breakout-brain && claude --resume --dangerously-skip-permissions'"
echo ""
echo "# TMUX Session Management"
echo "alias tm='tmux'"
echo "alias tls='tmux list-sessions'"
echo "alias ta='tmux attach -t'"
echo "alias tks='tmux kill-session -t'"
echo "alias tka='tmux kill-server'"
echo ""
echo "# Claude in TMUX starten"
echo "function claude-in-tmux() {"
echo "    local session_name=\"claude-\$(basename \$(pwd))\""
echo "    tmux new-session -d -s \"\$session_name\" \"claude --resume --dangerously-skip-permissions\""
echo "    tmux attach -t \"\$session_name\""
echo "}"
echo ""
echo "# Liste alle Claude Sessions"
echo "function claude-list() {"
echo "    echo '📋 Aktive Claude Sessions:'"
echo "    tmux list-sessions 2>/dev/null | grep -i claude || echo 'Keine aktiven Sessions'"
echo "}"
echo ""
echo "=========================="
echo ""
echo "Nach dem Hinzufügen, lade die Konfiguration neu mit:"
echo "  source ~/.bashrc"
echo "oder"
echo "  source ~/.zshrc"
echo ""
echo "Möchtest du die Aliases automatisch zu deiner Shell-Konfiguration hinzufügen? (y/n)"
read -r answer

if [ "$answer" = "y" ] || [ "$answer" = "Y" ]; then
    # Erkenne Shell
    if [ -n "$ZSH_VERSION" ]; then
        CONFIG_FILE="$HOME/.zshrc"
    else
        CONFIG_FILE="$HOME/.bashrc"
    fi
    
    # Backup erstellen
    cp "$CONFIG_FILE" "$CONFIG_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Aliases hinzufügen
    cat >> "$CONFIG_FILE" << 'EOF'

# Claude Code Project Management (added by setup-claude-aliases.sh)
alias claude-switch='/home/rodemkay/www/react/plugin-todo/scripts/claude-switch-project.sh'
alias claude-tmux='/home/rodemkay/www/react/plugin-todo/scripts/claude-tmux-manager.sh'

# Schnell-Wechsel zu Projekten
alias claude-todo='cd /home/rodemkay/www/react/plugin-todo && claude --resume --dangerously-skip-permissions'
alias claude-forex='cd /home/rodemkay/www/react/mounts/hetzner/forexsignale/staging && claude --resume --dangerously-skip-permissions'
alias claude-brain='cd /home/rodemkay/www/react/breakout-brain && claude --resume --dangerously-skip-permissions'

# TMUX Session Management
alias tm='tmux'
alias tls='tmux list-sessions'
alias ta='tmux attach -t'
alias tks='tmux kill-session -t'
alias tka='tmux kill-server'

# Claude in TMUX starten
function claude-in-tmux() {
    local session_name="claude-$(basename $(pwd))"
    tmux new-session -d -s "$session_name" "claude --resume --dangerously-skip-permissions"
    tmux attach -t "$session_name"
}

# Liste alle Claude Sessions
function claude-list() {
    echo '📋 Aktive Claude Sessions:'
    tmux list-sessions 2>/dev/null | grep -i claude || echo 'Keine aktiven Sessions'
}
EOF
    
    echo "✅ Aliases wurden zu $CONFIG_FILE hinzugefügt!"
    echo "📝 Backup erstellt: $CONFIG_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    echo ""
    echo "Führe jetzt aus: source $CONFIG_FILE"
fi