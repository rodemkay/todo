<?php
/**
 * Strukturierter Plan-Editor Template
 * Zeigt Pläne in benutzerfreundlicher Form ohne HTML-Exposition
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plan-Parser laden
use WP_Project_Todos\Plan_Parser;

$parser = new Plan_Parser();
$structure = $parser->parse_html_to_structure($todo->plan_html ?? '');
?>

<div id="structured-plan-editor" class="plan-editor-container">
    <div class="plan-editor-header">
        <h2>📋 Plan bearbeiten: <?php echo esc_html($todo->title); ?></h2>
        <div class="editor-mode-switch">
            <button type="button" id="switch-to-simple" class="button button-primary active">
                📝 Einfacher Editor
            </button>
            <button type="button" id="switch-to-html" class="button button-secondary">
                💻 HTML-Editor
            </button>
        </div>
    </div>

    <!-- EINFACHER STRUKTURIERTER EDITOR -->
    <div id="simple-editor" class="editor-mode">
        <form id="structured-plan-form">
            <!-- Plan-Titel -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">🏷️</span>
                    Plan-Titel
                </label>
                <input type="text" 
                       id="plan-title" 
                       class="large-text" 
                       value="<?php echo esc_attr($structure['title']); ?>" 
                       placeholder="Implementierungsplan für...">
            </div>

            <!-- Ziele & Objectives -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">🎯</span>
                    Ziele & Objectives
                    <small>Was soll erreicht werden?</small>
                </label>
                <div class="dynamic-list" data-list="goals">
                    <?php if (!empty($structure['goals'])): ?>
                        <?php foreach ($structure['goals'] as $index => $goal): ?>
                            <div class="list-item">
                                <input type="text" 
                                       name="goals[]" 
                                       value="<?php echo esc_attr($goal); ?>" 
                                       placeholder="Ziel eingeben...">
                                <button type="button" class="remove-item">❌</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-item">
                            <input type="text" 
                                   name="goals[]" 
                                   value="" 
                                   placeholder="Erstes Ziel eingeben...">
                            <button type="button" class="remove-item">❌</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-item button button-small" data-target="goals">
                    ➕ Weiteres Ziel hinzufügen
                </button>
            </div>

            <!-- Anforderungen -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">📌</span>
                    Anforderungen
                    <small>Was ist erforderlich?</small>
                </label>
                <div class="dynamic-list" data-list="requirements">
                    <?php if (!empty($structure['requirements'])): ?>
                        <?php foreach ($structure['requirements'] as $req): ?>
                            <div class="list-item">
                                <input type="text" 
                                       name="requirements[]" 
                                       value="<?php echo esc_attr($req); ?>" 
                                       placeholder="Anforderung eingeben...">
                                <button type="button" class="remove-item">❌</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-item">
                            <input type="text" 
                                   name="requirements[]" 
                                   value="" 
                                   placeholder="Erste Anforderung eingeben...">
                            <button type="button" class="remove-item">❌</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-item button button-small" data-target="requirements">
                    ➕ Weitere Anforderung hinzufügen
                </button>
            </div>

            <!-- Implementierungsschritte -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">🔨</span>
                    Implementierungsschritte
                    <small>Wie wird es umgesetzt? (Reihenfolge wichtig)</small>
                </label>
                <div class="dynamic-list ordered-list" data-list="steps">
                    <?php if (!empty($structure['steps'])): ?>
                        <?php foreach ($structure['steps'] as $index => $step): ?>
                            <div class="list-item">
                                <span class="step-number"><?php echo $index + 1; ?></span>
                                <input type="text" 
                                       name="steps[]" 
                                       value="<?php echo esc_attr($step); ?>" 
                                       placeholder="Implementierungsschritt eingeben...">
                                <div class="item-actions">
                                    <button type="button" class="move-up" title="Nach oben">⬆️</button>
                                    <button type="button" class="move-down" title="Nach unten">⬇️</button>
                                    <button type="button" class="remove-item">❌</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-item">
                            <span class="step-number">1</span>
                            <input type="text" 
                                   name="steps[]" 
                                   value="" 
                                   placeholder="Ersten Schritt eingeben...">
                            <div class="item-actions">
                                <button type="button" class="move-up" title="Nach oben">⬆️</button>
                                <button type="button" class="move-down" title="Nach unten">⬇️</button>
                                <button type="button" class="remove-item">❌</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-item button button-small" data-target="steps">
                    ➕ Weiteren Schritt hinzufügen
                </button>
            </div>

            <!-- Potenzielle Risiken -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">⚠️</span>
                    Potenzielle Risiken
                    <small>Was könnte schief gehen?</small>
                </label>
                <div class="dynamic-list" data-list="risks">
                    <?php if (!empty($structure['risks'])): ?>
                        <?php foreach ($structure['risks'] as $risk): ?>
                            <div class="list-item">
                                <input type="text" 
                                       name="risks[]" 
                                       value="<?php echo esc_attr($risk); ?>" 
                                       placeholder="Potenzielle Probleme eingeben...">
                                <button type="button" class="remove-item">❌</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-item button button-small" data-target="risks">
                    ➕ Weiteres Risiko hinzufügen
                </button>
            </div>

            <!-- Wichtige Notizen -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">📝</span>
                    Wichtige Notizen
                    <small>Zusätzliche Überlegungen und Anmerkungen</small>
                </label>
                <textarea id="plan-notes" 
                          rows="4" 
                          class="large-text" 
                          placeholder="Weitere wichtige Punkte, Überlegungen oder technische Details..."><?php 
                    echo esc_textarea(implode("\n\n", $structure['notes'])); 
                ?></textarea>
            </div>

            <!-- Zeitplan -->
            <div class="form-section">
                <label class="section-label">
                    <span class="label-icon">⏱️</span>
                    Zeitschätzung / Timeline
                    <small>Geschätzte Dauer und Zeitplan</small>
                </label>
                <textarea id="plan-timeline" 
                          rows="2" 
                          class="large-text" 
                          placeholder="z.B. 'Geschätzte Dauer: 2-3 Stunden' oder detaillierter Zeitplan..."><?php 
                    echo esc_textarea($structure['timeline']); 
                ?></textarea>
            </div>

            <!-- BENUTZER-FEEDBACK SECTION -->
            <div class="form-section feedback-section">
                <label class="section-label feedback-label">
                    <span class="label-icon">💬</span>
                    Ihr Feedback & Kommentare
                    <small>Ihre Anmerkungen, Änderungswünsche oder zusätzliche Anforderungen</small>
                </label>
                <textarea id="user-feedback" 
                          rows="4" 
                          class="large-text feedback-textarea" 
                          placeholder="Hier können Sie Ihre Kommentare zum Plan eingeben:&#10;&#10;• Änderungswünsche&#10;• Zusätzliche Anforderungen&#10;• Fragen oder Bedenken&#10;• Prioritäten oder Präferenzen&#10;&#10;Diese Kommentare werden in den finalen Plan integriert."><?php 
                    echo esc_textarea($structure['user_feedback']); 
                ?></textarea>
            </div>
        </form>

        <!-- Plan-Vorschau -->
        <div class="plan-preview-section">
            <h3>👀 Vorschau des generierten Plans</h3>
            <div id="plan-preview" class="plan-preview">
                <!-- Wird via JavaScript gefüllt -->
            </div>
        </div>
    </div>

    <!-- HTML-EDITOR (für Experten) -->
    <div id="html-editor" class="editor-mode" style="display: none;">
        <div class="html-editor-warning">
            <p><strong>⚠️ Experten-Modus:</strong> Hier können Sie den HTML-Code direkt bearbeiten. 
            Seien Sie vorsichtig, da ungültiges HTML die Anzeige beschädigen kann.</p>
        </div>
        <textarea id="html-content" 
                  rows="20" 
                  class="large-text code-editor"><?php echo esc_textarea($todo->plan_html ?? ''); ?></textarea>
    </div>

    <!-- Aktions-Buttons -->
    <div class="editor-actions">
        <div class="action-group primary-actions">
            <button type="button" id="save-plan" class="button button-primary">
                💾 Plan speichern
            </button>
            <button type="button" id="preview-plan" class="button button-secondary">
                👀 Vollbild-Vorschau
            </button>
        </div>
        
        <div class="action-group secondary-actions">
            <button type="button" id="reset-plan" class="button button-tertiary">
                🔄 Zurücksetzen
            </button>
            <button type="button" id="export-plan" class="button button-tertiary">
                📤 Plan exportieren
            </button>
        </div>
    </div>
</div>

<!-- CSS für Strukturierten Editor -->
<style>
.plan-editor-container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.plan-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 3px solid #2271b1;
}

.plan-editor-header h2 {
    margin: 0;
    color: #2271b1;
}

.editor-mode-switch {
    display: flex;
    gap: 5px;
}

.editor-mode-switch .button.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #2271b1;
}

.feedback-section {
    border-left-color: #28a745;
    background: #f0fff0;
}

.section-label {
    display: block;
    font-weight: 600;
    font-size: 1.1em;
    margin-bottom: 15px;
    color: #333;
}

.section-label .label-icon {
    font-size: 1.2em;
    margin-right: 8px;
}

.section-label small {
    display: block;
    font-weight: normal;
    color: #666;
    font-size: 0.9em;
    margin-top: 5px;
}

.feedback-label {
    color: #28a745;
}

.feedback-textarea {
    border: 2px solid #28a745;
    border-radius: 5px;
    background: white;
}

.dynamic-list {
    margin-bottom: 15px;
}

.list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    background: white;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.list-item input {
    flex: 1;
    border: 1px solid #ccc;
    padding: 8px 12px;
    border-radius: 4px;
}

.list-item input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.ordered-list .step-number {
    background: #2271b1;
    color: white;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9em;
    flex-shrink: 0;
}

.item-actions {
    display: flex;
    gap: 5px;
}

.item-actions button {
    background: none;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 5px 8px;
    cursor: pointer;
    font-size: 0.9em;
    transition: all 0.2s;
}

.item-actions button:hover {
    background: #f0f0f0;
}

.remove-item {
    background: none;
    border: 1px solid #dc3545;
    color: #dc3545;
    border-radius: 3px;
    padding: 5px 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-item:hover {
    background: #dc3545;
    color: white;
}

.add-item {
    margin-top: 10px;
}

.plan-preview-section {
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #6c757d;
}

.plan-preview {
    background: white;
    padding: 20px;
    border-radius: 5px;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
}

.html-editor-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.code-editor {
    font-family: 'Courier New', Monaco, monospace;
    font-size: 13px;
    background: #1e1e1e;
    color: #d4d4d4;
    border-radius: 5px;
    padding: 15px;
}

.editor-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.action-group {
    display: flex;
    gap: 10px;
}

.button-tertiary {
    background: #6c757d;
    color: white;
    border: none;
}

.button-tertiary:hover {
    background: #545b62;
}

@media (max-width: 768px) {
    .plan-editor-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .list-item {
        flex-direction: column;
        align-items: stretch;
    }
    
    .item-actions {
        justify-content: center;
    }
    
    .editor-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .action-group {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- JavaScript für Interaktivität -->
<script>
jQuery(document).ready(function($) {
    
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
    });
    
    // Item entfernen
    $(document).on('click', '.remove-item', function() {
        const container = $(this).closest('.dynamic-list');
        const isOrdered = container.hasClass('ordered-list');
        
        $(this).closest('.list-item').remove();
        
        if (isOrdered) {
            updateStepNumbers(container);
        }
    });
    
    // Items verschieben (nur bei geordneten Listen)
    $(document).on('click', '.move-up', function() {
        const item = $(this).closest('.list-item');
        const prev = item.prev('.list-item');
        if (prev.length) {
            item.insertBefore(prev);
            updateStepNumbers(item.closest('.dynamic-list'));
        }
    });
    
    $(document).on('click', '.move-down', function() {
        const item = $(this).closest('.list-item');
        const next = item.next('.list-item');
        if (next.length) {
            item.insertAfter(next);
            updateStepNumbers(item.closest('.dynamic-list'));
        }
    });
    
    // Live-Vorschau aktualisieren
    function updatePreview() {
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
        
        // AJAX-Call zur Preview-Generierung
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
    
    // Event-Listener für Live-Updates
    $('#structured-plan-form input, #structured-plan-form textarea').on('input', 
        debounce(updatePreview, 500)
    );
    
    // Plan speichern
    $('#save-plan').click(function() {
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
            todo_id: <?php echo $todo->id; ?>,
            plan_data: planData
        }, function(response) {
            if (response.success) {
                alert('✅ Plan erfolgreich gespeichert!');
                // Optional: Seite neu laden oder Bestätigung anzeigen
            } else {
                alert('❌ Fehler beim Speichern: ' + response.data.message);
            }
        });
    });
    
    // Hilfsfunktionen
    function getListValues(listName) {
        return $(`[name="${listName}[]"]`).map(function() {
            return $(this).val();
        }).get().filter(val => val.trim() !== '');
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
    
    // Initiale Vorschau laden
    updatePreview();
});
</script>