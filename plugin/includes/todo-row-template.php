                    <div class="wsj-table-row wsj-todo-row wsj-fade-in" data-todo-id="<?php echo $todo->id; ?>">
                        <!-- Spalte 1: Checkbox, ID, Bereich -->
                        <div class="wsj-table-col wsj-col-select">
                            <div class="wsj-cell wsj-cell-top">
                                <input type="checkbox" class="wsj-checkbox todo-checkbox" name="todo_ids[]" value="<?php echo $todo->id; ?>" form="bulk-action-form" />
                            </div>
                            <div class="wsj-cell wsj-cell-mid">
                                <span class="wsj-todo-id">#<?php echo $todo->id; ?></span>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <span class="wsj-scope-badge wsj-scope-<?php echo esc_attr($todo->scope); ?>">
                                    <?php echo esc_html(ucfirst($todo->scope)); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Spalte 2: Titel, Beschreibung -->
                        <div class="wsj-table-col wsj-col-title">
                            <div class="wsj-cell wsj-cell-top">
                                <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $todo->id); ?>" class="wsj-todo-title-link">
                                    <?php echo esc_html($todo->title); ?>
                                </a>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <?php if (!empty($todo->description)): ?>
                                    <p class="wsj-todo-description">
                                        <?php 
                                        $desc = strip_tags($todo->description);
                                        $words = explode(' ', $desc);
                                        if (count($words) > 20) {
                                            echo esc_html(implode(' ', array_slice($words, 0, 20)) . '...');
                                        } else {
                                            echo esc_html($desc);
                                        }
                                        ?>
                                    </p>
                                <?php else: ?>
                                    <span class="wsj-empty-text">Keine Beschreibung</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Spalte 3: Status, Priorität -->
                        <div class="wsj-table-col wsj-col-status">
                            <div class="wsj-cell wsj-cell-top">
                                <form method="post" class="wsj-inline-form">
                                    <?php wp_nonce_field('update_status_' . $todo->id); ?>
                                    <input type="hidden" name="todo_id" value="<?php echo $todo->id; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="new_status" class="wsj-status-select wsj-status-<?php echo esc_attr($todo->status); ?>" onchange="this.form.submit()">
                                        <option value="offen" <?php selected($todo->status, 'offen'); ?>>Offen</option>
                                        <option value="in_progress" <?php selected($todo->status, 'in_progress'); ?>>In Bearbeitung</option>
                                        <option value="completed" <?php selected($todo->status, 'completed'); ?>>Abgeschlossen</option>
                                        <option value="blocked" <?php selected($todo->status, 'blocked'); ?>>Blockiert</option>
                                        <option value="cancelled" <?php selected($todo->status, 'cancelled'); ?>>Abgebrochen</option>
                                    </select>
                                </form>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <span class="wsj-priority-badge wsj-priority-<?php echo esc_attr($todo->priority); ?>">
                                    <?php 
                                    $priority_labels = ['low' => 'Niedrig', 'medium' => 'Normal', 'high' => 'Hoch', 'critical' => 'Kritisch'];
                                    echo esc_html($priority_labels[$todo->priority] ?? ucfirst($todo->priority));
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Spalte 4: Claude, Anhänge -->
                        <div class="wsj-table-col wsj-col-claude">
                            <div class="wsj-cell wsj-cell-top">
                                <form method="post" class="wsj-inline-form">
                                    <?php wp_nonce_field('toggle_bearbeiten_' . $todo->id); ?>
                                    <input type="hidden" name="todo_id" value="<?php echo $todo->id; ?>">
                                    <input type="hidden" name="toggle_bearbeiten" value="1">
                                    <button type="submit" class="wsj-claude-toggle <?php echo $todo->bearbeiten ? 'wsj-claude-active' : 'wsj-claude-inactive'; ?>" 
                                            title="<?php echo $todo->bearbeiten ? 'Claude bearbeitet diese Aufgabe' : 'Claude überspringt diese Aufgabe'; ?>">
                                        <span class="wsj-claude-status"><?php echo $todo->bearbeiten ? 'EIN' : 'AUS'; ?></span>
                                    </button>
                                </form>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <?php if (isset($todo->attachment_count) && $todo->attachment_count > 0): ?>
                                    <span class="wsj-attachment-count">
                                        📎 <?php echo $todo->attachment_count; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="wsj-empty-text">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Spalte 5: Erstellt, Letzte Änderung -->
                        <div class="wsj-table-col wsj-col-dates">
                            <div class="wsj-cell wsj-cell-top">
                                <div class="wsj-date-info">
                                    <span class="wsj-date-user">
                                        <?php 
                                        $created_by = $todo->created_by ? get_userdata($todo->created_by) : null;
                                        echo esc_html($created_by ? $created_by->display_name : 'System');
                                        ?>
                                    </span>
                                    <span class="wsj-date-time">
                                        <?php echo mysql2date('d.m. H:i', $todo->created_at); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <?php if ($todo->completed_date): ?>
                                    <div class="wsj-date-info">
                                        <span class="wsj-change-indicator">
                                            <?php echo $todo->status === 'completed' ? '✓' : '⟳'; ?>
                                        </span>
                                        <span class="wsj-date-time">
                                            <?php echo mysql2date('d.m. H:i', $todo->completed_date); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="wsj-empty-text">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Spalte 6: Arbeitsverzeichnis, Aktionen -->
                        <div class="wsj-table-col wsj-col-actions">
                            <div class="wsj-cell wsj-cell-top">
                                <code class="wsj-directory-path" title="<?php echo esc_attr($todo->working_directory); ?>">
                                    <?php echo esc_html(basename($todo->working_directory)); ?>
                                </code>
                            </div>
                            <div class="wsj-cell wsj-cell-bottom">
                                <!-- Erste Reihe Buttons -->
                                <div class="wsj-action-row">
                                    <button class="wsj-action-btn wsj-btn-claude send-single-todo" data-todo-id="<?php echo $todo->id; ?>" title="An Claude senden">
                                        An Claude
                                    </button>
                                    <a href="<?php echo admin_url('admin.php?page=wp-project-todos-new&id=' . $todo->id); ?>" class="wsj-action-btn wsj-btn-edit" title="Bearbeiten">
                                        Edit
                                    </a>
                                    <button onclick="openContinueModal(<?php echo $todo->id; ?>)" class="wsj-action-btn wsj-btn-schedule" title="Wiedervorlage">
                                        Wiedervorlage
                                    </button>
                                </div>
                                <!-- Zweite Reihe Buttons -->
                                <div class="wsj-action-row">
                                    <?php if (!empty($todo->claude_output) || !empty($todo->claude_notes) || !empty($todo->bemerkungen)): ?>
                                    <button onclick="openReportModal(<?php echo $todo->id; ?>)" class="wsj-action-btn wsj-btn-report" title="HTML Report anzeigen">
                                        HTML
                                    </button>
                                    <?php else: ?>
                                    <button class="wsj-action-btn wsj-btn-report wsj-btn-disabled" disabled title="Kein Report verfügbar">
                                        HTML
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo admin_url('admin.php?page=wp-project-todos-claude&todo_id=' . $todo->id); ?>" class="wsj-action-btn wsj-btn-output" title="Claude Output anzeigen">
                                        Output
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-project-todos&action=delete&id=' . $todo->id), 'delete_todo_' . $todo->id); ?>" 
                                       class="wsj-action-btn wsj-btn-delete" title="Aufgabe löschen"
                                       onclick="return confirm('Aufgabe wirklich löschen?');">
                                        Löschen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>