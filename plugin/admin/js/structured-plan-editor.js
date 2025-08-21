/**
 * JavaScript für strukturierten Plan-Editor
 * Verwaltet die Benutzerinteraktion und AJAX-Kommunikation
 */

jQuery(document).ready(function($) {
    
    // Plan-Editor öffnen
    $(document).on('click', '#open-structured-editor', function() {
        const todoId = $(this).data('todo-id');
        const container = $('#structured-editor-container');
        
        // Loading-Anzeige
        container.html('<div class="loading-editor">📋 Editor wird geladen...</div>').show();
        
        // Plan nur anzeigen ausblenden
        $('#plan-display-area').hide();
        
        // AJAX-Request für Editor-HTML
        $.post(ajaxurl, {
            action: 'load_structured_plan_editor',
            nonce: wpProjectTodos.nonce,
            todo_id: todoId
        }, function(response) {
            if (response.success) {
                container.html(response.data.html);
                initializeStructuredEditor();
            } else {
                container.html('<div class="error">❌ Fehler beim Laden: ' + response.data.message + '</div>');
            }
        }).fail(function() {
            container.html('<div class="error">❌ Netzwerkfehler beim Laden des Editors</div>');
        });
    });
    
    // Plan nur anzeigen
    $(document).on('click', '#view-plan-only', function() {
        const planArea = $('#plan-display-area');
        const editorContainer = $('#structured-editor-container');
        
        if (planArea.is(':visible')) {
            planArea.hide();
            $(this).text('👀 Plan anzeigen');
        } else {
            editorContainer.hide();
            planArea.show();
            $(this).text('🔐 Plan ausblenden');
        }
    });
    
    /**
     * Initialisiert den strukturierten Editor nach dem Laden
     */
    function initializeStructuredEditor() {
        // Editor-Modus wechseln
        $('#switch-to-simple').click(function() {
            $('#simple-editor').show();
            $('#html-editor').hide();
            $(this).addClass('active').removeClass('button-secondary').addClass('button-primary');
            $('#switch-to-html').removeClass('active').removeClass('button-primary').addClass('button-secondary');
        });
        
        $('#switch-to-html').click(function() {
            $('#html-editor').show();
            $('#simple-editor').hide();
            $(this).addClass('active').removeClass('button-secondary').addClass('button-primary');
            $('#switch-to-simple').removeClass('active').removeClass('button-primary').addClass('button-secondary');
        });
        
        // Dynamische Listen verwalten
        $('.add-item').click(function() {
            const target = $(this).data('target');
            const container = $(`[data-list="${target}"]`);
            const isOrdered = container.hasClass('ordered-list');
            
            let newItem = '<div class="list-item">';
            
            if (isOrdered) {
                const nextNumber = container.find('.list-item').length + 1;
                newItem += `<span class="step-number">${nextNumber}</span>`;
            }
            
            newItem += `<input type="text" name="${target}[]" value="" placeholder="${getPlaceholder(target)}">`;
            
            if (isOrdered) {
                newItem += `<div class="item-actions">
                    <button type="button" class="move-up" title="Nach oben">⬆️</button>
                    <button type="button" class="move-down" title="Nach unten">⬇️</button>
                    <button type="button" class="remove-item">❌</button>
                </div>`;
            } else {
                newItem += '<button type="button" class="remove-item">❌</button>';
            }
            
            newItem += '</div>';
            
            container.append(newItem);
            
            if (isOrdered) {
                updateStepNumbers(container);
            }
            
            // Live-Vorschau aktualisieren
            updatePreview();
        });
        
        // Item entfernen
        $(document).on('click', '.remove-item', function() {
            const container = $(this).closest('.dynamic-list');
            const isOrdered = container.hasClass('ordered-list');
            
            $(this).closest('.list-item').remove();
            
            if (isOrdered) {
                updateStepNumbers(container);
            }
            
            // Live-Vorschau aktualisieren
            updatePreview();
        });
        
        // Items verschieben (nur bei geordneten Listen)
        $(document).on('click', '.move-up', function() {
            const item = $(this).closest('.list-item');
            const prev = item.prev('.list-item');
            if (prev.length) {
                item.insertBefore(prev);
                updateStepNumbers(item.closest('.dynamic-list'));
                updatePreview();
            }
        });
        
        $(document).on('click', '.move-down', function() {
            const item = $(this).closest('.list-item');
            const next = item.next('.list-item');
            if (next.length) {
                item.insertAfter(next);
                updateStepNumbers(item.closest('.dynamic-list'));
                updatePreview();
            }
        });
        
        // Live-Vorschau aktualisieren
        $('#structured-plan-form input, #structured-plan-form textarea').on('input', 
            debounce(updatePreview, 500)
        );
        
        // Plan speichern
        $('#save-plan').click(function() {
            const saveButton = $(this);
            const originalText = saveButton.text();
            
            saveButton.text('💾 Speichert...').prop('disabled', true);
            
            const isSimpleMode = $('#simple-editor').is(':visible');
            let planData;
            
            if (isSimpleMode) {
                planData = {
                    mode: 'structured',
                    structure: {
                        title: $('#plan-title').val(),
                        goals: getListValues('goals'),
                        requirements: getListValues('requirements'),
                        steps: getListValues('steps'),
                        risks: getListValues('risks'),
                        notes: $('#plan-notes').val(),
                        timeline: $('#plan-timeline').val(),
                        user_feedback: $('#user-feedback').val()
                    }
                };
            } else {
                planData = {
                    mode: 'html',
                    html: $('#html-content').val()
                };
            }
            
            $.post(ajaxurl, {
                action: 'save_structured_plan',
                nonce: wpProjectTodos.nonce,
                todo_id: saveButton.closest('#structured-plan-editor').data('todo-id'),
                plan_data: planData
            }, function(response) {
                saveButton.text(originalText).prop('disabled', false);
                
                if (response.success) {
                    showNotification('✅ Plan erfolgreich gespeichert!', 'success');
                    
                    // Plan-Viewer aktualisieren
                    setTimeout(() => {
                        location.reload(); // Vollständige Aktualisierung
                    }, 1500);
                } else {
                    showNotification('❌ Fehler beim Speichern: ' + response.data.message, 'error');
                }
            }).fail(function() {
                saveButton.text(originalText).prop('disabled', false);
                showNotification('❌ Netzwerkfehler beim Speichern', 'error');
            });
        });
        
        // Vollbild-Vorschau
        $('#preview-plan').click(function() {
            const previewContent = $('#plan-preview').html();
            
            if (!previewContent) {
                alert('Bitte warten Sie, bis die Vorschau geladen ist.');
                return;
            }
            
            const previewWindow = window.open('', '_blank', 'width=1200,height=800');
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html lang="de">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Plan-Vorschau</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
                        h1, h2, h3 { color: #333; }
                        .print-btn { position: fixed; top: 20px; right: 20px; background: #2271b1; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
                    </style>
                </head>
                <body>
                    <button class="print-btn" onclick="window.print()">🖨️ Drucken</button>
                    ${previewContent}
                </body>
                </html>
            `);
            previewWindow.document.close();
        });
        
        // Plan zurücksetzen
        $('#reset-plan').click(function() {
            if (confirm('⚠️ Möchten Sie wirklich alle Änderungen verwerfen?')) {
                location.reload();
            }
        });
        
        // Plan exportieren
        $('#export-plan').click(function() {
            const structure = {
                title: $('#plan-title').val(),
                goals: getListValues('goals'),
                requirements: getListValues('requirements'),
                steps: getListValues('steps'),
                risks: getListValues('risks'),
                notes: $('#plan-notes').val(),
                timeline: $('#plan-timeline').val(),
                user_feedback: $('#user-feedback').val(),
                exported_at: new Date().toISOString()
            };
            
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(structure, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", `plan_${structure.title || 'unnamed'}_${new Date().toISOString().split('T')[0]}.json`);
            downloadAnchor.click();
        });
        
        // Initiale Vorschau laden
        updatePreview();
    }
    
    /**
     * Live-Vorschau aktualisieren
     */
    function updatePreview() {
        if (!$('#structured-plan-form').length) return;
        
        const structure = {
            title: $('#plan-title').val(),
            goals: getListValues('goals'),
            requirements: getListValues('requirements'),
            steps: getListValues('steps'),
            risks: getListValues('risks'),
            notes: $('#plan-notes').val(),
            timeline: $('#plan-timeline').val(),
            user_feedback: $('#user-feedback').val()
        };
        
        // Nur aktualisieren wenn mindestens ein Feld ausgefüllt ist
        if (Object.values(structure).some(val => 
            Array.isArray(val) ? val.length > 0 : val && val.trim() !== '')) {
            
            $.post(ajaxurl, {
                action: 'generate_plan_preview',
                nonce: wpProjectTodos.nonce,
                structure: structure
            }, function(response) {
                if (response.success) {
                    $('#plan-preview').html(response.data.html);
                }
            });
        }
    }
    
    /**
     * Hilfsfunktionen
     */
    function getListValues(listName) {
        return $(`[name="${listName}[]"]`).map(function() {
            return $(this).val();
        }).get().filter(val => val && val.trim() !== '');
    }
    
    function updateStepNumbers(container) {
        container.find('.step-number').each(function(index) {
            $(this).text(index + 1);
        });
    }
    
    function getPlaceholder(target) {
        const placeholders = {
            'goals': 'Ziel eingeben...',
            'requirements': 'Anforderung eingeben...',
            'steps': 'Implementierungsschritt eingeben...',
            'risks': 'Potenzielle Probleme eingeben...'
        };
        return placeholders[target] || 'Wert eingeben...';
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function showNotification(message, type = 'info') {
        // WordPress-ähnliche Notification erstellen
        const notification = $(`
            <div class="notice notice-${type === 'success' ? 'success' : 'error'} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Nachricht verwerfen</span>
                </button>
            </div>
        `);
        
        // Am Anfang der Seite einfügen
        $('.wrap').prepend(notification);
        
        // Auto-remove nach 5 Sekunden
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').click(function() {
            notification.fadeOut(() => notification.remove());
        });
    }
    
    // AJAX-Handler für Editor-Loading hinzufügen
    $(document).ajaxStart(function() {
        $('body').addClass('loading');
    }).ajaxStop(function() {
        $('body').removeClass('loading');
    });
});

// Global CSS für Loading-State
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .loading-editor {
                text-align: center;
                padding: 40px;
                font-size: 1.2em;
                color: #666;
                background: #f9f9f9;
                border-radius: 5px;
                animation: pulse 1.5s infinite;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            .error {
                background: #fff5f5;
                border: 2px solid #dc3545;
                color: #dc3545;
                padding: 20px;
                border-radius: 5px;
                text-align: center;
            }
            
            .plan-actions-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #2271b1;
            }
            
            .plan-actions-header h3 {
                margin: 0;
                color: #2271b1;
            }
            
            .plan-action-buttons {
                display: flex;
                gap: 10px;
            }
            
            body.loading::after {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 3px;
                background: linear-gradient(90deg, #2271b1 0%, #72aee6 50%, #2271b1 100%);
                background-size: 200% 100%;
                animation: loading-bar 1.5s infinite;
                z-index: 9999;
            }
            
            @keyframes loading-bar {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
        `)
        .appendTo('head');
});