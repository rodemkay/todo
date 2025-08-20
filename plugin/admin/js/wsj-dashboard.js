/**
 * WP Project Todos - WSJ Dashboard JavaScript
 * Handles all interactive features of the WSJ-style dashboard
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeWSJDashboard();
    });

    function initializeWSJDashboard() {
        initializeSelectAll();
        initializeBulkActions();
        initializeSendTodoFunctionality();
        initializeModalSupport();
        initializeTooltips();
    }

    // Select All Functionality
    function initializeSelectAll() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.todo-checkbox');
        const selectedCount = document.getElementById('selected-count');

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.todo-checkbox:checked').length;
            if (selectedCount) {
                selectedCount.textContent = checked > 0 ? `${checked} ausgewählt` : '';
                
                // Update select-all state
                if (selectAll) {
                    const allChecked = checked === checkboxes.length;
                    const someChecked = checked > 0;
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                }
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Initial count
        updateSelectedCount();
    }

    // Bulk Actions
    function initializeBulkActions() {
        const form = document.getElementById('bulk-action-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.todo-checkbox:checked').length;
                const action = document.getElementById('bulk_action').value;
                
                if (selectedCount === 0) {
                    e.preventDefault();
                    alert('Bitte wählen Sie mindestens eine Aufgabe aus.');
                    return false;
                }
                
                if (action === 'delete') {
                    if (!confirm(`${selectedCount} Aufgaben wirklich löschen?`)) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }
    }

    // Send Single Todo Functionality
    function initializeSendTodoFunctionality() {
        document.querySelectorAll('.send-single-todo').forEach(button => {
            button.addEventListener('click', function() {
                const todoId = this.dataset.todoId;
                const originalText = this.textContent;
                
                sendTodoToClaudeWithFeedback(this, todoId, originalText);
            });
        });
    }

    function sendTodoToClaudeWithFeedback(button, todoId, originalText) {
        // Update button state
        button.disabled = true;
        button.textContent = 'Sende...';
        button.classList.add('wsj-loading');
        
        // Make AJAX request
        fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'send_specific_todo_to_claude',
                todo_id: todoId,
                nonce: window.wpProjectTodosNonce || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.textContent = '✓ Gesendet';
                button.classList.add('wsj-btn-success');
                showNotification('Todo erfolgreich an Claude gesendet!', 'success');
            } else {
                button.textContent = '✗ Fehler';
                button.classList.add('wsj-btn-error');
                showNotification('Fehler beim Senden: ' + (data.data?.message || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.textContent = '✗ Fehler';
            button.classList.add('wsj-btn-error');
            showNotification('Netzwerk-Fehler beim Senden an Claude', 'error');
        })
        .finally(() => {
            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
                button.classList.remove('wsj-loading', 'wsj-btn-success', 'wsj-btn-error');
            }, 3000);
        });
    }

    // ./todo Loop Start Functionality
    window.startTodoLoop = function() {
        const button = document.getElementById('start-todo-loop');
        const status = document.getElementById('loop-status');
        
        if (!button) return;
        
        const originalContent = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<span class="wsj-btn-icon">⏳</span><span>Startet...</span>';
        
        if (status) {
            status.innerHTML = 'Initialisierung...';
        }
        
        fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'start_todo_loop',
                nonce: window.wpProjectTodosNonce || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.innerHTML = '<span class="wsj-btn-icon">✅</span><span>Gestartet</span>';
                if (status) status.innerHTML = 'Loop läuft...';
                showNotification('Todo-Loop erfolgreich gestartet!', 'success');
            } else {
                button.innerHTML = '<span class="wsj-btn-icon">❌</span><span>Fehler</span>';
                if (status) status.innerHTML = 'Fehler beim Starten';
                showNotification('Fehler beim Starten: ' + (data.data?.message || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = '<span class="wsj-btn-icon">❌</span><span>Fehler</span>';
            if (status) status.innerHTML = 'Netzwerk-Fehler';
            showNotification('Netzwerk-Fehler beim Starten des Loops', 'error');
        })
        .finally(() => {
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
                if (status) status.innerHTML = '';
            }, 5000);
        });
    };

    // Modal Support for Continue and Report
    function initializeModalSupport() {
        // Continue Modal
        window.openContinueModal = function(todoId) {
            if (confirm('Wiedervorlage für Todo #' + todoId + ' erstellen?')) {
                // Redirect to new todo page with parent_id
                window.location.href = `admin.php?page=wp-project-todos-new&parent_id=${todoId}`;
            }
        };

        // Report Modal
        window.openReportModal = function(todoId) {
            window.open(`admin.php?page=wp-project-todos-claude&todo_id=${todoId}`, '_blank');
        };
    }

    // Tooltip Support
    function initializeTooltips() {
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = createTooltip(this.getAttribute('title'));
                document.body.appendChild(tooltip);
                positionTooltip(tooltip, this);
            });

            element.addEventListener('mouseleave', function() {
                const tooltip = document.querySelector('.wsj-tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
            });
        });
    }

    function createTooltip(text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'wsj-tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 10000;
            pointer-events: none;
            white-space: nowrap;
            max-width: 250px;
            word-wrap: break-word;
            white-space: normal;
        `;
        return tooltip;
    }

    function positionTooltip(tooltip, element) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top = rect.top - tooltipRect.height - 8;
        let left = rect.left + (rect.width - tooltipRect.width) / 2;
        
        // Adjust if tooltip goes outside viewport
        if (top < 0) {
            top = rect.bottom + 8;
        }
        if (left < 0) {
            left = 8;
        }
        if (left + tooltipRect.width > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - 8;
        }
        
        tooltip.style.top = (top + window.scrollY) + 'px';
        tooltip.style.left = (left + window.scrollX) + 'px';
    }

    // Notification System
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `wsj-notification wsj-notification-${type}`;
        notification.innerHTML = `
            <div class="wsj-notification-content">
                <span class="wsj-notification-icon">${getNotificationIcon(type)}</span>
                <span class="wsj-notification-message">${message}</span>
                <button class="wsj-notification-close">&times;</button>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 32px;
            right: 20px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 12px 16px;
            max-width: 350px;
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            border-left: 4px solid ${getNotificationColor(type)};
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Close button
        notification.querySelector('.wsj-notification-close').addEventListener('click', () => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    function getNotificationIcon(type) {
        switch (type) {
            case 'success': return '✅';
            case 'error': return '❌';
            case 'warning': return '⚠️';
            default: return 'ℹ️';
        }
    }

    function getNotificationColor(type) {
        switch (type) {
            case 'success': return '#10b981';
            case 'error': return '#ef4444';
            case 'warning': return '#f59e0b';
            default: return '#3b82f6';
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + A to select all todos
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.shiftKey) {
            const selectAll = document.getElementById('select-all');
            if (selectAll && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                selectAll.click();
            }
        }
        
        // Escape to deselect all
        if (e.key === 'Escape') {
            const selectAll = document.getElementById('select-all');
            if (selectAll && selectAll.checked) {
                selectAll.click();
            }
        }
    });

    // Accessibility improvements
    document.querySelectorAll('.wsj-action-btn').forEach(button => {
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Performance monitoring
    if (window.performance && window.performance.mark) {
        window.performance.mark('wsj-dashboard-initialized');
        console.log('WSJ Dashboard initialized successfully');
    }
})();