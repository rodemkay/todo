# 🎮 Claude Session Control System - Vollständige Implementierung

## Übersicht
Komplettes Remote-Control-System für Claude Code Sessions mit Live-Status, Mode-Switching und Terminal-Kontrolle.

## 1. Session Control Commands

### Basis-Befehle via Kitty
```bash
# Befehle an Claude Session senden
kitty @ send-text --match title:claude "claude --auto-accept\n"
kitty @ send-text --match title:claude "claude --plan-mode\n"
kitty @ send-text --match title:claude "exit\n"

# Session neustarten
kitty @ send-text --match title:claude "\x03"  # Ctrl+C
sleep 1
kitty @ send-text --match title:claude "claude\n"

# Verzeichnis wechseln
kitty @ send-text --match title:claude "cd /new/project && claude\n"
```

### Mode-Switching Befehle
```bash
# Auto-Accept Mode
echo "claude --auto-accept" | ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude --stdin'

# Plan Mode
echo "claude --plan-mode" | ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude --stdin'

# Bypass Permissions
echo "claude --bypass-permissions 'Bash(ssh:*),Read(/etc/*),Write(/var/*)'" | ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude --stdin'
```

## 2. Status Monitoring

### Live Terminal Output Capture
```bash
#!/bin/bash
# capture-claude-status.sh

# Capture letzte 50 Zeilen vom Terminal
kitty @ get-text --match title:claude --extent last_non_empty_output > /tmp/claude_terminal_snapshot.txt

# Parse Claude Mode
if grep -q "auto-accept mode" /tmp/claude_terminal_snapshot.txt; then
    echo "MODE: Auto-Accept"
elif grep -q "plan mode" /tmp/claude_terminal_snapshot.txt; then
    echo "MODE: Plan"
else
    echo "MODE: Standard"
fi

# Check if Claude is active
if pgrep -f "claude" > /dev/null; then
    echo "STATUS: Active"
else
    echo "STATUS: Inactive"
fi
```

## 3. WordPress Admin Page Implementation

### Admin Page Code
```php
// In todo/admin/claude-control.php
<?php
namespace Todo\Admin;

class Claude_Control {
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>🎮 Claude Session Control Center</h1>
            
            <!-- Status Display -->
            <div class="claude-status-panel">
                <h2>Current Status</h2>
                <div id="claude-status-display">
                    <div class="status-item">
                        <strong>Session:</strong> <span id="session-status">Checking...</span>
                    </div>
                    <div class="status-item">
                        <strong>Mode:</strong> <span id="mode-status">Checking...</span>
                    </div>
                    <div class="status-item">
                        <strong>Working Directory:</strong> <span id="cwd-status">Checking...</span>
                    </div>
                    <div class="status-item">
                        <strong>Active Task:</strong> <span id="task-status">Checking...</span>
                    </div>
                </div>
            </div>
            
            <!-- Mode Controls -->
            <div class="claude-mode-controls">
                <h2>Session Modes</h2>
                <button class="button button-primary" onclick="setClaudeMode('auto-accept')">
                    🚀 Auto-Accept Mode
                </button>
                <button class="button" onclick="setClaudeMode('plan')">
                    📋 Plan Mode
                </button>
                <button class="button" onclick="setClaudeMode('standard')">
                    🎯 Standard Mode
                </button>
            </div>
            
            <!-- Session Controls -->
            <div class="claude-session-controls">
                <h2>Session Management</h2>
                <button class="button" onclick="restartClaude()">🔄 Restart Session</button>
                <button class="button" onclick="stopClaude()">⏹️ Stop Session</button>
                <button class="button button-primary" onclick="startClaude()">▶️ Start Session</button>
            </div>
            
            <!-- Direct Command Input -->
            <div class="claude-command-input">
                <h2>Send Command to Terminal</h2>
                <input type="text" id="terminal-command" placeholder="Enter command..." style="width: 500px;">
                <button class="button" onclick="sendCommand()">Send</button>
                <div id="command-history"></div>
            </div>
            
            <!-- Terminal Preview -->
            <div class="claude-terminal-preview">
                <h2>Terminal Output (Last 20 lines)</h2>
                <pre id="terminal-output">Loading...</pre>
                <button class="button" onclick="refreshTerminal()">🔄 Refresh</button>
            </div>
        </div>
        
        <script>
        function setClaudeMode(mode) {
            jQuery.post(ajaxurl, {
                action: 'claude_set_mode',
                mode: mode
            }, function(response) {
                if (response.success) {
                    alert('Mode changed to: ' + mode);
                    updateStatus();
                }
            });
        }
        
        function sendCommand() {
            const command = jQuery('#terminal-command').val();
            jQuery.post(ajaxurl, {
                action: 'claude_send_command',
                command: command
            }, function(response) {
                if (response.success) {
                    jQuery('#command-history').prepend('<div>' + command + '</div>');
                    jQuery('#terminal-command').val('');
                    setTimeout(refreshTerminal, 1000);
                }
            });
        }
        
        function refreshTerminal() {
            jQuery.post(ajaxurl, {
                action: 'claude_get_terminal'
            }, function(response) {
                if (response.success) {
                    jQuery('#terminal-output').text(response.data.output);
                }
            });
        }
        
        function updateStatus() {
            jQuery.post(ajaxurl, {
                action: 'claude_get_status'
            }, function(response) {
                if (response.success) {
                    jQuery('#session-status').text(response.data.session);
                    jQuery('#mode-status').text(response.data.mode);
                    jQuery('#cwd-status').text(response.data.cwd);
                    jQuery('#task-status').text(response.data.task);
                }
            });
        }
        
        // Auto-update every 5 seconds
        setInterval(updateStatus, 5000);
        setInterval(refreshTerminal, 10000);
        updateStatus();
        refreshTerminal();
        </script>
        <?php
    }
    
    public function ajax_set_mode() {
        $mode = $_POST['mode'];
        $command = '';
        
        switch($mode) {
            case 'auto-accept':
                $command = 'claude --auto-accept';
                break;
            case 'plan':
                $command = 'claude --plan-mode';
                break;
            case 'standard':
                $command = 'claude';
                break;
        }
        
        $result = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude \"\\x03\"'");
        sleep(1);
        $result = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude \"$command\\n\"'");
        
        wp_send_json_success(['command' => $command]);
    }
    
    public function ajax_send_command() {
        $command = sanitize_text_field($_POST['command']);
        $result = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ send-text --match title:claude \"$command\\n\"'");
        wp_send_json_success(['sent' => $command]);
    }
    
    public function ajax_get_terminal() {
        $output = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ get-text --match title:claude --extent screen | tail -20'");
        wp_send_json_success(['output' => $output]);
    }
    
    public function ajax_get_status() {
        // Get session status
        $is_active = shell_exec("ssh rodemkay@100.89.207.122 'pgrep -f claude'");
        $session = $is_active ? 'Active' : 'Inactive';
        
        // Get current directory
        $cwd = shell_exec("ssh rodemkay@100.89.207.122 'pwd'");
        
        // Get mode from terminal
        $terminal = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ get-text --match title:claude --extent screen | tail -50'");
        $mode = 'Standard';
        if (strpos($terminal, 'auto-accept') !== false) {
            $mode = 'Auto-Accept';
        } elseif (strpos($terminal, 'plan-mode') !== false) {
            $mode = 'Plan';
        }
        
        // Get active task
        $task = 'None';
        if (file_exists('/tmp/CURRENT_TODO_ID')) {
            $task_id = trim(file_get_contents('/tmp/CURRENT_TODO_ID'));
            $task = "Todo #$task_id";
        }
        
        wp_send_json_success([
            'session' => $session,
            'mode' => $mode,
            'cwd' => trim($cwd),
            'task' => $task
        ]);
    }
}
```

## 4. Quick Command Scripts

### mode-switcher.sh
```bash
#!/bin/bash
# Quick mode switcher for Claude

MODE=$1
KITTY_SOCKET="unix:/tmp/kitty-$USER"

case $MODE in
    auto)
        echo "Switching to Auto-Accept mode..."
        kitty @ --to $KITTY_SOCKET send-text --match title:claude $'\x03'
        sleep 1
        kitty @ --to $KITTY_SOCKET send-text --match title:claude "claude --auto-accept\n"
        ;;
    plan)
        echo "Switching to Plan mode..."
        kitty @ --to $KITTY_SOCKET send-text --match title:claude $'\x03'
        sleep 1
        kitty @ --to $KITTY_SOCKET send-text --match title:claude "claude --plan-mode\n"
        ;;
    bypass)
        PERMISSIONS=$2
        echo "Activating bypass permissions: $PERMISSIONS"
        kitty @ --to $KITTY_SOCKET send-text --match title:claude $'\x03'
        sleep 1
        kitty @ --to $KITTY_SOCKET send-text --match title:claude "claude --bypass-permissions '$PERMISSIONS'\n"
        ;;
    *)
        echo "Usage: $0 {auto|plan|bypass [permissions]}"
        ;;
esac
```

## 5. Integration in Dashboard

### Add Menu Item
```php
// In todo.php
add_action('admin_menu', function() {
    add_submenu_page(
        'todo',
        'Claude Control',
        '🎮 Claude Control',
        'manage_options',
        'claude-control',
        [new Claude_Control(), 'render_page']
    );
});
```

## 6. Advanced Features

### Terminal Screenshot via ANSI to HTML
```bash
# capture-terminal-html.sh
kitty @ get-text --match title:claude --extent screen --ansi | \
    aha --black --title "Claude Terminal" > /tmp/claude_terminal.html
```

### Session Recording
```bash
# Start recording
script -f /tmp/claude_session.log

# Replay recording
scriptreplay /tmp/claude_session.log
```

### Multi-Session Dashboard
```php
// Show all active Claude sessions
$sessions = shell_exec("ssh rodemkay@100.89.207.122 'kitty @ ls | grep claude'");
// Parse and display each session with controls
```

## 7. Security Considerations

- Sanitize all commands before sending
- Whitelist allowed commands
- Log all remote commands
- Implement rate limiting
- Add nonce verification

## 8. Implementation Steps

1. **Create Admin Page** ✅
2. **Add AJAX Handlers** ✅
3. **Test Kitty Commands** ⏳
4. **Add to Menu** ⏳
5. **Style UI** ⏳
6. **Add Security** ⏳

---

**Status:** Ready for Implementation
**Priority:** High
**Estimated Time:** 3-4 hours