/**
 * WP Project To-Dos Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Quick edit functionality
    $('.todo-quick-edit').on('click', function(e) {
        e.preventDefault();
        
        var $this = $(this);
        var todoId = $this.data('id');
        var field = $this.data('field');
        var currentValue = $this.data('value');
        
        var newValue = prompt('Neuer Wert:', currentValue);
        
        if (newValue !== null && newValue !== currentValue) {
            $.ajax({
                url: wpProjectTodos.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_project_todos_quick_edit',
                    nonce: wpProjectTodos.nonce,
                    id: todoId,
                    field: field,
                    value: newValue
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + response.data);
                    }
                }
            });
        }
    });
    
    // Bulk actions
    $('#doaction').on('click', function(e) {
        var action = $('#bulk-action-selector-top').val();
        
        if (action === '-1') {
            return;
        }
        
        e.preventDefault();
        
        var ids = [];
        $('input[name="todo[]"]:checked').each(function() {
            ids.push($(this).val());
        });
        
        if (ids.length === 0) {
            alert('Bitte wähle mindestens eine Aufgabe aus.');
            return;
        }
        
        if (confirm('Möchtest du diese Aktion wirklich durchführen?')) {
            $.ajax({
                url: wpProjectTodos.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_project_todos_bulk_action',
                    nonce: wpProjectTodos.nonce,
                    bulk_action: action,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + response.data);
                    }
                }
            });
        }
    });
    
    // Auto-refresh Claude output
    if ($('.claude-output-viewer').length > 0) {
        var todoId = $('.claude-output-viewer').data('todo-id');
        
        if (todoId) {
            setInterval(function() {
                $.get(wpProjectTodos.apiUrl + 'todo/' + todoId, {
                    _wpnonce: wpProjectTodos.nonce
                }, function(response) {
                    if (response.success && response.data.claude_output) {
                        updateClaudeOutput(response.data.claude_output);
                    }
                });
            }, 5000); // Refresh every 5 seconds
        }
    }
    
    function updateClaudeOutput(outputJson) {
        var output = JSON.parse(outputJson);
        var $viewer = $('.claude-output-viewer');
        
        $viewer.empty();
        
        output.forEach(function(entry) {
            var $entry = $('<div class="claude-output-entry claude-output-' + entry.type + '">');
            $entry.append('<span class="claude-output-timestamp">[' + entry.timestamp + ']</span> ');
            $entry.append('<span class="claude-output-message">' + entry.message + '</span>');
            $viewer.append($entry);
        });
        
        // Scroll to bottom
        $viewer.scrollTop($viewer[0].scrollHeight);
    }
});