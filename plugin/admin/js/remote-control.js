/**
 * Enhanced Remote Control JavaScript
 * Bidirektionale WordPress ↔ Claude CLI Kommunikation
 */

(function($) {
    'use strict';
    
    // State Management
    const state = {
        terminalOpen: false,
        connected: false,
        lastCommand: '',
        outputBuffer: [],
        statusInterval: null,
        outputInterval: null
    };
    
    // Initialize when document ready
    $(document).ready(function() {
        initializeRemoteControl();
        startStatusMonitoring();
        bindEventHandlers();
        checkInitialStatus();
    });
    
    /**
     * Initialize Remote Control System
     */
    function initializeRemoteControl() {
        console.log('Initializing Remote Control...');
        
        // Set up periodic output refresh if terminal is open
        state.outputInterval = setInterval(function() {
            if (state.terminalOpen && state.connected) {
                refreshTerminalOutput();
            }
        }, 2000); // Every 2 seconds
    }
    
    /**
     * Start Status Monitoring
     */
    function startStatusMonitoring() {
        state.statusInterval = setInterval(checkClaudeStatus, 10000); // Every 10 seconds
    }
    
    /**
     * Bind Event Handlers
     */
    function bindEventHandlers() {
        // Command buttons
        $('[data-command]').on('click', function() {
            const command = $(this).data('command');
            sendCommand(command, $(this));
        });
        
        // Terminal controls
        $('#toggle-terminal').on('click', toggleTerminal);
        $('#close-terminal').on('click', closeTerminal);
        $('#terminal-send').on('click', sendTerminalCommand);
        
        // Enter key in terminal
        $('#terminal-command').on('keypress', function(e) {
            if (e.which === 13) {
                sendTerminalCommand();
            }
        });
        
        // Status check button
        $('#check-status-btn').on('click', function() {
            checkClaudeStatus();
            showNotification('Status wird geprüft...', 'info');
        });
        
        // Watch script controls
        $('#start-watch-script').on('click', startWatchScript);
        $('#stop-watch-script').on('click', stopWatchScript);
    }
    
    /**
     * Check Initial Status
     */
    function checkInitialStatus() {
        setTimeout(checkClaudeStatus, 1000);
    }
    
    /**
     * Send Command to Claude
     */
    function sendCommand(command, button) {
        const originalText = button.text();
        const originalBg = button.css('background-color');
        
        // Visual feedback
        button.text('⏳ Sende...').css('background-color', '#ff9800');
        
        const data = {
            action: 'send_command_remote',
            command: command,
            nonce: wpProjectTodosRemote.nonce
        };
        
        $.ajax({
            url: wpProjectTodosRemote.ajaxurl,
            type: 'POST',
            data: data,
            timeout: 15000,
            
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    addToTerminalOutput(`$ ${command}`, 'command');
                    addToTerminalOutput(response.data.info || 'Befehl gesendet', 'response');
                    
                    // Update button to success state
                    button.text('✅ Gesendet').css('background-color', '#4caf50');
                    
                    // Auto-refresh output after command
                    setTimeout(refreshTerminalOutput, 1000);
                    
                } else {
                    showNotification('Fehler: ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    addToTerminalOutput(`Fehler bei: ${command}`, 'error');
                    button.text('❌ Fehler').css('background-color', '#f44336');
                }
            },
            
            error: function(xhr, status, error) {
                const errorMsg = `Ajax Error: ${status} - ${error}`;
                showNotification(errorMsg, 'error');
                addToTerminalOutput(errorMsg, 'error');
                button.text('❌ Fehler').css('background-color', '#f44336');
            },
            
            complete: function() {
                // Reset button after 3 seconds
                setTimeout(function() {
                    button.text(originalText).css('background-color', originalBg);
                }, 3000);
            }
        });
        
        state.lastCommand = command;
    }
    
    /**
     * Check Claude Status
     */
    function checkClaudeStatus() {
        $.ajax({
            url: wpProjectTodosRemote.ajaxurl,
            type: 'POST',
            data: {
                action: 'check_claude_status',
                nonce: wpProjectTodosRemote.nonce
            },
            timeout: 10000,
            
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    const isWorking = response.data.is_working;
                    
                    updateStatusIndicator(status, isWorking);
                    state.connected = (status === 'running');
                    
                    // Update last output if available
                    if (response.data.last_output) {
                        updateLastOutput(response.data.last_output);
                    }
                } else {
                    updateStatusIndicator('error', false);
                    state.connected = false;
                }
            },
            
            error: function() {
                updateStatusIndicator('offline', false);
                state.connected = false;
            }
        });
    }
    
    /**
     * Update Status Indicator
     */
    function updateStatusIndicator(status, isWorking) {
        const indicator = $('.status-indicator');
        const statusText = $('.status-text');
        
        // Remove all status classes
        indicator.removeClass('active idle offline');
        
        switch(status) {
            case 'running':
                if (isWorking) {
                    indicator.addClass('active');
                    statusText.text('Aktiv');
                } else {
                    indicator.addClass('idle');
                    statusText.text('Bereit');
                }
                break;
                
            case 'offline':
                indicator.addClass('offline');
                statusText.text('Offline');
                break;
                
            default:
                indicator.addClass('offline');
                statusText.text('Unbekannt');
        }
    }
    
    /**
     * Toggle Terminal Window
     */
    function toggleTerminal() {
        const terminal = $('#claude-terminal');
        const button = $('#toggle-terminal');
        
        if (state.terminalOpen) {
            closeTerminal();
        } else {
            openTerminal();
        }
    }
    
    /**
     * Open Terminal
     */
    function openTerminal() {
        const terminal = $('#claude-terminal');
        const button = $('#toggle-terminal');
        
        terminal.slideDown(300);
        button.text('💻 Terminal schließen');
        state.terminalOpen = true;
        
        // Focus command input
        setTimeout(function() {
            $('#terminal-command').focus();
        }, 350);
        
        // Scroll to terminal
        $('html, body').animate({
            scrollTop: terminal.offset().top - 100
        }, 500);
        
        // Load initial output
        refreshTerminalOutput();
    }
    
    /**
     * Close Terminal
     */
    function closeTerminal() {
        const terminal = $('#claude-terminal');
        const button = $('#toggle-terminal');
        
        terminal.slideUp(300);
        button.text('💻 Terminal');
        state.terminalOpen = false;
    }
    
    /**
     * Send Terminal Command
     */
    function sendTerminalCommand() {
        const input = $('#terminal-command');
        const command = input.val().trim();
        
        if (!command) return;
        
        // Add to terminal output immediately
        addToTerminalOutput(`$ ${command}`, 'command');
        input.val('');
        
        // Send command
        if (command === 'clear') {
            $('#terminal-output').text('Claude Terminal v1.0\n=====================================\nTerminal bereinigt.\n');
        } else {
            sendCommand(command, $('<button>').text('Terminal'));
        }
    }
    
    /**
     * Add Line to Terminal Output
     */
    function addToTerminalOutput(text, type = 'normal') {
        const output = $('#terminal-output');
        const timestamp = new Date().toLocaleTimeString();
        
        let prefix = '';
        let style = '';
        
        switch(type) {
            case 'command':
                prefix = '';
                style = 'color: #4caf50; font-weight: bold;';
                break;
            case 'response':
                prefix = '→ ';
                style = 'color: #2196f3;';
                break;
            case 'error':
                prefix = '❌ ';
                style = 'color: #f44336;';
                break;
            case 'info':
                prefix = 'ℹ️ ';
                style = 'color: #ff9800;';
                break;
        }
        
        const line = `[${timestamp}] ${prefix}${text}\n`;
        output.append($('<span>').attr('style', style).text(line));
        
        // Auto-scroll to bottom
        output.scrollTop(output[0].scrollHeight);
        
        // Keep buffer manageable (last 100 lines)
        const lines = output.text().split('\n');
        if (lines.length > 100) {
            const newText = lines.slice(-100).join('\n');
            output.text(newText);
        }
    }
    
    /**
     * Refresh Terminal Output
     */
    function refreshTerminalOutput() {
        if (!state.terminalOpen) return;
        
        $.ajax({
            url: wpProjectTodosRemote.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_terminal_output',
                nonce: wpProjectTodosRemote.nonce
            },
            timeout: 5000,
            
            success: function(response) {
                if (response.success && response.data.output) {
                    updateTerminalWithFreshOutput(response.data.output);
                }
            },
            
            error: function() {
                // Silent fail for output refresh
            }
        });
    }
    
    /**
     * Update Terminal with Fresh Output
     */
    function updateTerminalWithFreshOutput(output) {
        // Only update if output is different
        const currentOutput = $('#terminal-output').text();
        if (output.trim() !== currentOutput.trim()) {
            const lines = output.split('\n');
            const recent = lines.slice(-20).join('\n'); // Show last 20 lines
            
            if (recent.trim()) {
                addToTerminalOutput('--- Fresh Output ---', 'info');
                addToTerminalOutput(recent, 'normal');
            }
        }
    }
    
    /**
     * Update Last Output Display
     */
    function updateLastOutput(output) {
        // Could be used to show last command result in status area
        if (output && output.length > 0) {
            console.log('Last Claude output:', output);
        }
    }
    
    /**
     * Start Watch Script
     */
    function startWatchScript() {
        const button = $('#start-watch-script');
        const originalText = button.text();
        
        button.text('🚀 Starte...').prop('disabled', true);
        
        $.ajax({
            url: wpProjectTodosRemote.ajaxurl,
            type: 'POST',
            data: {
                action: 'start_watch_script',
                nonce: wpProjectTodosRemote.nonce
            },
            
            success: function(response) {
                if (response.success) {
                    showNotification('Watch-Script gestartet!', 'success');
                    button.text('✅ Gestartet').css('background-color', '#4caf50');
                } else {
                    showNotification('Fehler beim Starten des Watch-Scripts', 'error');
                    button.text('❌ Fehler').css('background-color', '#f44336');
                }
            },
            
            error: function() {
                showNotification('Ajax-Fehler beim Starten', 'error');
                button.text('❌ Fehler').css('background-color', '#f44336');
            },
            
            complete: function() {
                setTimeout(function() {
                    button.text(originalText).prop('disabled', false).css('background-color', '');
                }, 3000);
            }
        });
    }
    
    /**
     * Stop Watch Script
     */
    function stopWatchScript() {
        const button = $('#stop-watch-script');
        const originalText = button.text();
        
        button.text('⏹️ Stoppe...').prop('disabled', true);
        
        $.ajax({
            url: wpProjectTodosRemote.ajaxurl,
            type: 'POST',
            data: {
                action: 'stop_watch_script',
                nonce: wpProjectTodosRemote.nonce
            },
            
            success: function(response) {
                if (response.success) {
                    showNotification('Watch-Script gestoppt!', 'success');
                    button.text('✅ Gestoppt').css('background-color', '#4caf50');
                } else {
                    showNotification('Fehler beim Stoppen des Watch-Scripts', 'error');
                    button.text('❌ Fehler').css('background-color', '#f44336');
                }
            },
            
            error: function() {
                showNotification('Ajax-Fehler beim Stoppen', 'error');
                button.text('❌ Fehler').css('background-color', '#f44336');
            },
            
            complete: function() {
                setTimeout(function() {
                    button.text(originalText).prop('disabled', false).css('background-color', '');
                }, 3000);
            }
        });
    }
    
    /**
     * Show Notification
     */
    function showNotification(message, type = 'info') {
        const statusDiv = $('#claude-status');
        const messageP = statusDiv.find('p');
        
        // Set message and type
        messageP.text(message);
        statusDiv.removeClass('notice-success notice-error notice-warning notice-info');
        
        switch(type) {
            case 'success':
                statusDiv.addClass('notice-success');
                break;
            case 'error':
                statusDiv.addClass('notice-error');
                break;
            case 'warning':
                statusDiv.addClass('notice-warning');
                break;
            default:
                statusDiv.addClass('notice-info');
        }
        
        // Show notification
        statusDiv.slideDown(200);
        
        // Auto-hide after 5 seconds for success/info
        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                statusDiv.slideUp(200);
            }, 5000);
        }
    }
    
    /**
     * Cleanup on page unload
     */
    $(window).on('beforeunload', function() {
        if (state.statusInterval) {
            clearInterval(state.statusInterval);
        }
        if (state.outputInterval) {
            clearInterval(state.outputInterval);
        }
    });
    
})(jQuery);