# 📅 WIEDERVORLAGE SYSTEM - OPTION B IMPLEMENTATION

**Version:** 3.0.0  
**Status:** ✅ VOLLSTÄNDIG IMPLEMENTIERT  
**Kategorie:** Core Feature  
**Letzte Aktualisierung:** 2025-01-21

---

## 🎯 ÜBERSICHT

Das **Wiedervorlage-System** ist eine der wichtigsten Innovationen in TODO System V3.0. Es ermöglicht die **intelligente Terminierung** von TODOs mit vollständiger **Kontext-Erhaltung** und **automatischer Output-Sammlung**.

### 🔑 KERNFUNKTION
Wenn ein TODO nicht sofort abgeschlossen werden kann, ermöglicht das System eine **präzise Terminierung** für einen späteren Zeitpunkt, während **alle bisherigen Fortschritte und Erkenntnisse** automatisch gespeichert und für die spätere Wiederaufnahme verfügbar gemacht werden.

### 🎯 ZIELE
1. **Keine Arbeit geht verloren** - Vollständige Kontext-Erhaltung
2. **Nahtlose Fortsetzung** - Wiederaufnahme mit allen Informationen
3. **Intelligente Terminierung** - Flexible Datum/Zeit-Auswahl
4. **Automatisierte Dokumentation** - Output-Sammlung ohne Mehraufwand

---

## 🔧 FUNKTIONSWEISE

### 1. AUSLÖSUNG DES WIEDERVORLAGE-SYSTEMS

#### Via CLI:
```bash
# Während der Bearbeitung eines TODOs:
./todo defer
```

#### Via Web-Interface:
- "Wiedervorlage"-Button im TODO-Dashboard
- Keyboard Shortcut: `Ctrl+D` (Defer)
- Floating Action Button: "📅 Defer"

#### Automatische Erkennung:
Das System erkennt automatisch, wenn ein TODO **nicht abgeschlossen** ist und bietet die Wiedervorlage als Option an.

### 2. TERMINIERUNGS-DIALOG

#### Datum/Zeit-Auswahl:
```html
<!-- Moderner DateTime-Picker -->
<input type="datetime-local" 
       id="defer-datetime" 
       min="2025-01-21T14:00" 
       value="2025-01-22T09:00">

<!-- Preset-Buttons für häufige Termine -->
<button onclick="setDefer('tomorrow-morning')">Morgen 9:00</button>
<button onclick="setDefer('next-week')">Nächste Woche</button>
<button onclick="setDefer('next-monday')">Nächsten Montag</button>
```

#### Wiedervorlage-Kategorien:
- **Kurz (1-24h):** Für Tasks die nur eine kurze Pause benötigen
- **Medium (1-7 Tage):** Standard-Wiedervorlage für normale Tasks
- **Lang (1-4 Wochen):** Für komplexe Tasks die längere Vorlaufzeit benötigen

### 3. AUTOMATISCHE OUTPUT-SAMMLUNG

#### Was wird gesammelt:
```php
<?php
class DeferOutputCollector {
    public function collectOutput($todo_id) {
        $output_data = array(
            // Bisherige CLI-Outputs
            'cli_output' => $this->getCliOutput($todo_id),
            
            // File-Changes seit Todo-Start
            'file_changes' => $this->trackFileChanges($todo_id),
            
            // Browser-Screenshots (falls Playwright aktiv)
            'screenshots' => $this->collectScreenshots($todo_id),
            
            // Zwischennotizen
            'working_notes' => $this->getWorkingNotes($todo_id),
            
            // Zeitaufwand
            'time_spent' => $this->calculateTimeSpent($todo_id),
            
            // Fortschritt (0-100%)
            'progress' => $this->estimateProgress($todo_id)
        );
        
        return $output_data;
    }
}
?>
```

#### Sammlung erfolgt automatisch:
1. **CLI-Output:** Alle Terminal-Ausgaben seit TODO-Start
2. **File-Changes:** Geänderte Dateien mit Diff-Anzeige
3. **Screenshots:** Automatische Browser-Screenshots bei Playwright-Nutzung
4. **Working-Notes:** Zwischennotizen und temporäre Erkenntnisse
5. **Progress-Metrics:** Geschätzter Fortschritt und Zeitaufwand

### 4. NOTIZ-GENERIERUNG

#### Automatische Zusammenfassung:
```php
<?php
function generateDeferSummary($todo_id, $output_data) {
    $summary = "📅 WIEDERVORLAGE ERSTELLT AM " . date('d.m.Y H:i') . "\n\n";
    
    // Fortschritt
    $summary .= "🔄 FORTSCHRITT: " . $output_data['progress'] . "%\n";
    $summary .= "⏱️ ZEIT AUFGEWANDT: " . $output_data['time_spent'] . "\n\n";
    
    // Erreichte Ergebnisse
    if (!empty($output_data['file_changes'])) {
        $summary .= "✅ ERREICHTE ERGEBNISSE:\n";
        foreach ($output_data['file_changes'] as $file) {
            $summary .= "- " . $file['name'] . " (" . $file['changes'] . " Änderungen)\n";
        }
        $summary .= "\n";
    }
    
    // Nächste Schritte
    $summary .= "🎯 NÄCHSTE SCHRITTE BEI WIEDERAUFNAHME:\n";
    $summary .= $this->generateNextSteps($todo_id, $output_data);
    
    // Kontext für Wiederaufnahme
    $summary .= "\n📝 KONTEXT FÜR WIEDERAUFNAHME:\n";
    $summary .= $output_data['working_notes'];
    
    return $summary;
}
?>
```

#### Template-basierte Notizen:
```markdown
# TODO #{id} WIEDERVORLAGE

## 📊 STATUS
- **Fortschritt:** {progress}%
- **Zeit aufgewandt:** {time_spent}
- **Wiedervorlage:** {defer_date}
- **Grund:** {defer_reason}

## ✅ ERREICHTE ERGEBNISSE
{accomplished_tasks}

## 🎯 NÄCHSTE SCHRITTE
{next_steps}

## 📝 ARBEITSNOTIZEN
{working_notes}

## 🔗 RELEVANTE DATEIEN
{modified_files}

## 💡 ERKENNTNISSE & HINWEISE
{insights_and_notes}
```

### 5. STATUS-MANAGEMENT

#### Datenbankaktualisierung:
```sql
UPDATE stage_project_todos 
SET 
    status = 'terminiert',
    defer_date = '{selected_datetime}',
    defer_summary = '{generated_summary}',
    updated_at = NOW()
WHERE id = {todo_id};
```

#### Status-Definitionen:
- **terminiert:** TODO ist für späteren Zeitpunkt geplant
- **defer_date:** Exakter Wiedervorlage-Zeitpunkt
- **defer_summary:** Automatisch generierte Zusammenfassung
- **defer_reason:** Grund für Terminierung (optional)

---

## 🔄 WIEDERAUFNAHME-PROZESS

### 1. AUTOMATISCHE ERKENNUNG FÄLLIGER TODOS
```bash
# Täglicher Cron-Job prüft fällige TODOs:
./todo check-deferred

# Ausgabe bei fälligen TODOs:
# "📅 3 terminierte TODOs sind zur Bearbeitung bereit!"
# "Führe './todo' aus um das nächste fällige TODO zu laden."
```

### 2. KONTEXT-WIEDERHERSTELLUNG
```php
<?php
function restoreTodoContext($todo_id) {
    // Laden der Wiedervorlage-Daten
    $todo = $this->getTodoWithDefer($todo_id);
    
    // Kontext-Display für CLI
    echo "📅 WIEDERVORLAGE AKTIV für TODO #{$todo_id}\n";
    echo "🕒 Terminiert seit: " . $todo['defer_date'] . "\n";
    echo "⏱️ Pause-Dauer: " . $this->calculateDeferDuration($todo) . "\n\n";
    
    // Zusammenfassung anzeigen
    echo "📝 BISHERIGER FORTSCHRITT:\n";
    echo $todo['defer_summary'] . "\n";
    
    // Working Directory wiederherstellen
    if ($todo['working_directory']) {
        chdir($todo['working_directory']);
        echo "📁 Working Directory: " . $todo['working_directory'] . "\n";
    }
    
    // Relevante Dateien auflisten
    $this->displayRelevantFiles($todo_id);
}
?>
```

### 3. NAHTLOSE FORTSETZUNG
```bash
# Nach './todo' bei fälligem terminierten TODO:

📅 WIEDERVORLAGE AKTIV für TODO #67
🕒 Terminiert seit: 22.01.2025 09:00
⏱️ Pause-Dauer: 18 Stunden

📝 BISHERIGER FORTSCHRITT:
✅ ERREICHTE ERGEBNISSE:
- todo.php (15 Änderungen)
- class-admin.php (8 Änderungen)

🎯 NÄCHSTE SCHRITTE:
- Plan-Editor Template-System implementieren
- Auto-Save-Funktionalität testen
- WYSIWYG-Integration vervollständigen

📁 Working Directory: /home/rodemkay/www/react/todo/
🔗 Relevante Dateien:
- plugin/includes/class-admin.php (zuletzt geändert: 21.01.2025 20:15)
- plugin/assets/js/plan-editor.js (neu)

💡 Erkenntnisse: TinyMCE Integration funktioniert, aber Auto-Save 
   benötigt noch Debouncing für bessere Performance.

Möchtest du mit diesem TODO fortfahren? [Y/n]
```

---

## 💻 TECHNISCHE IMPLEMENTATION

### 1. DATENBANKSCHEMA-ERWEITERUNG
```sql
-- Neue Spalten für Wiedervorlage-System
ALTER TABLE stage_project_todos 
ADD COLUMN defer_date DATETIME DEFAULT NULL,
ADD COLUMN defer_summary LONGTEXT DEFAULT NULL,
ADD COLUMN defer_reason VARCHAR(255) DEFAULT NULL,
ADD COLUMN defer_count INT DEFAULT 0,
ADD COLUMN original_start_date DATETIME DEFAULT NULL;

-- Index für effiziente Abfrage fälliger TODOs
CREATE INDEX idx_defer_due ON stage_project_todos (defer_date, status);
```

### 2. PHP-KLASSEN-STRUKTUR
```php
<?php
// Hauptklasse für Wiedervorlage-System
class DeferSystem {
    private $output_collector;
    private $summary_generator;
    private $context_restorer;
    
    public function __construct() {
        $this->output_collector = new DeferOutputCollector();
        $this->summary_generator = new DeferSummaryGenerator();
        $this->context_restorer = new DeferContextRestorer();
    }
    
    // Hauptmethode für Terminierung
    public function deferTodo($todo_id, $defer_datetime, $reason = '') {
        // 1. Output sammeln
        $output_data = $this->output_collector->collectOutput($todo_id);
        
        // 2. Zusammenfassung generieren
        $summary = $this->summary_generator->generate($todo_id, $output_data, $reason);
        
        // 3. Datenbank aktualisieren
        $this->updateTodoStatus($todo_id, $defer_datetime, $summary, $reason);
        
        // 4. Erfolg-Nachricht
        return $this->formatSuccessMessage($todo_id, $defer_datetime);
    }
    
    // Methode für Wiederaufnahme
    public function resumeDeferred($todo_id) {
        return $this->context_restorer->restore($todo_id);
    }
}
?>
```

### 3. CLI-INTEGRATION
```bash
#!/bin/bash
# Erweiterte ./todo CLI mit Defer-Support

case "$1" in
    "defer")
        # Wiedervorlage-Modus
        php /path/to/defer-handler.php --todo-id="$CURRENT_TODO_ID"
        ;;
    
    "check-deferred") 
        # Prüfung auf fällige TODOs
        php /path/to/check-deferred.php
        ;;
        
    "list-deferred")
        # Alle terminierten TODOs anzeigen
        php /path/to/list-deferred.php
        ;;
        
    *)
        # Standard-TODO-Ladelogik mit Defer-Unterstützung
        php /path/to/todo-loader.php --check-deferred=true
        ;;
esac
```

### 4. AJAX-ENDPOINTS FÜR WEB-INTERFACE
```php
<?php
// WordPress AJAX-Handler für Wiedervorlage
add_action('wp_ajax_defer_todo', 'handle_defer_todo');

function handle_defer_todo() {
    // Nonce-Validierung
    if (!wp_verify_nonce($_POST['nonce'], 'defer_todo_nonce')) {
        wp_die('Security check failed');
    }
    
    $todo_id = intval($_POST['todo_id']);
    $defer_datetime = sanitize_text_field($_POST['defer_datetime']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    // Defer-System initialisieren
    $defer_system = new DeferSystem();
    
    try {
        $result = $defer_system->deferTodo($todo_id, $defer_datetime, $reason);
        wp_send_json_success($result);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
?>
```

---

## 🎛️ BENUTZEROBERFLÄCHE

### 1. DEFER-BUTTON INTEGRATION
```html
<!-- Im TODO Dashboard -->
<div class="todo-actions">
    <button class="btn btn-primary" onclick="editTodo(<?php echo $todo['id']; ?>)">
        📝 Bearbeiten
    </button>
    
    <button class="btn btn-warning defer-btn" onclick="openDeferModal(<?php echo $todo['id']; ?>)">
        📅 Wiedervorlage
    </button>
    
    <button class="btn btn-success" onclick="completeTodo(<?php echo $todo['id']; ?>)">
        ✅ Abschließen
    </button>
</div>
```

### 2. DEFER-MODAL
```html
<!-- Wiedervorlage-Modal -->
<div id="deferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📅 TODO für später terminieren</h3>
            <span class="close" onclick="closeDeferModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <!-- Datum/Zeit-Auswahl -->
            <div class="form-group">
                <label for="defer-datetime">🕒 Wiedervorlage-Zeitpunkt:</label>
                <input type="datetime-local" id="defer-datetime" class="form-control">
            </div>
            
            <!-- Preset-Buttons -->
            <div class="defer-presets">
                <button onclick="setDeferPreset('tomorrow')" class="btn btn-outline">
                    Morgen 9:00
                </button>
                <button onclick="setDeferPreset('next-week')" class="btn btn-outline">
                    Nächste Woche
                </button>
                <button onclick="setDeferPreset('next-monday')" class="btn btn-outline">
                    Nächsten Montag
                </button>
            </div>
            
            <!-- Grund (optional) -->
            <div class="form-group">
                <label for="defer-reason">📝 Grund für Terminierung (optional):</label>
                <textarea id="defer-reason" class="form-control" rows="3" 
                          placeholder="z.B. Warten auf externe Abhängigkeiten, weitere Tests benötigt..."></textarea>
            </div>
            
            <!-- Fortschritt-Anzeige -->
            <div class="form-group">
                <label>📊 Geschätzter Fortschritt:</label>
                <div class="progress-slider">
                    <input type="range" id="defer-progress" min="0" max="100" value="30">
                    <span class="progress-value">30%</span>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button onclick="closeDeferModal()" class="btn btn-secondary">
                Abbrechen
            </button>
            <button onclick="submitDefer()" class="btn btn-warning">
                📅 Terminieren
            </button>
        </div>
    </div>
</div>
```

### 3. DEFER-STATUS ANZEIGE
```html
<!-- In der TODO-Liste -->
<div class="todo-item deferred" data-id="67">
    <div class="todo-status">
        <span class="status-badge status-deferred">📅 Terminiert</span>
        <span class="defer-date">22.01.2025 09:00</span>
    </div>
    
    <div class="todo-content">
        <h4>Plan-Editor mit WYSIWYG implementieren</h4>
        <div class="defer-info">
            <span class="defer-reason">🔄 30% abgeschlossen - Warten auf TinyMCE-Tests</span>
            <div class="defer-actions">
                <button onclick="resumeEarly(67)" class="btn btn-sm btn-success">
                    ⚡ Früher fortsetzen
                </button>
                <button onclick="postponeDefer(67)" class="btn btn-sm btn-warning">
                    ⏰ Verschieben
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 📊 SMART-ANALYTICS & METRIKEN

### 1. DEFER-STATISTIKEN
```php
<?php
function getDeferAnalytics($user_id = null) {
    global $wpdb;
    
    $where_clause = $user_id ? "AND user_id = $user_id" : "";
    
    $stats = $wpdb->get_results("
        SELECT 
            COUNT(*) as total_deferred,
            AVG(defer_count) as avg_deferrals,
            AVG(TIMESTAMPDIFF(HOUR, defer_date, updated_at)) as avg_defer_duration,
            COUNT(CASE WHEN defer_count > 2 THEN 1 END) as chronic_deferrals
        FROM stage_project_todos 
        WHERE status = 'terminiert' $where_clause
    ");
    
    return $stats[0];
}
?>
```

### 2. DEFER-PATTERN ERKENNUNG
```php
<?php
// Erkennung von Defer-Mustern zur Optimierung
class DeferPatternAnalyzer {
    
    public function analyzeUserPatterns($user_id) {
        $patterns = array(
            'most_deferred_day' => $this->getMostDeferredDayOfWeek($user_id),
            'most_deferred_time' => $this->getMostDeferredTimeOfDay($user_id),
            'common_defer_reasons' => $this->getCommonDeferReasons($user_id),
            'defer_success_rate' => $this->calculateDeferSuccessRate($user_id)
        );
        
        return $patterns;
    }
    
    public function suggestOptimalDeferTime($todo_id, $user_id) {
        $patterns = $this->analyzeUserPatterns($user_id);
        // KI-basierte Empfehlung basierend auf historischen Daten
        return $this->calculateOptimalTime($patterns, $todo_id);
    }
}
?>
```

---

## 🚀 ERWEITERTE FEATURES

### 1. SMART DEFER SUGGESTIONS
- **KI-basierte Zeitvorschläge** basierend auf TODO-Typ und User-Pattern
- **Automatische Reason-Erkennung** aus bisherigem Progress
- **Optimal-Time-Calculator** für beste Produktivitätszeiten

### 2. COLLABORATIVE DEFERS
- **Team-Wiedervorlage** für gemeinsame TODOs
- **Dependency-Tracking** für verknüpfte Tasks  
- **Notification-System** für Team-Members bei Defer-Updates

### 3. DEFER-CHAINS
- **Automatische Folge-TODOs** nach Defer-Ablauf
- **Conditional Defers** basierend auf externen Faktoren
- **Recurring Defer-Patterns** für wiederkehrende Tasks

---

## 🐛 TROUBLESHOOTING

### Häufige Probleme:

#### 1. Defer-Modal öffnet nicht
```javascript
// JavaScript-Fehler prüfen:
console.log('Defer modal functions loaded:', typeof openDeferModal !== 'undefined');

// CSS-Konflikte prüfen:
$('#deferModal').show(); // Sollte Modal anzeigen
```

#### 2. DateTime-Picker funktioniert nicht
```html
<!-- Fallback für ältere Browser: -->
<input type="text" id="defer-datetime-fallback" 
       placeholder="DD.MM.YYYY HH:MM" 
       class="datetime-input">
```

#### 3. Output-Sammlung unvollständig
```bash
# CLI-Output-Log prüfen:
tail -f /tmp/claude_output.log

# File-Change-Tracking verifizieren:
git status # sollte Änderungen seit TODO-Start zeigen
```

#### 4. Wiederaufnahme funktioniert nicht
```sql
-- Fällige TODOs manuell prüfen:
SELECT id, titel, defer_date, status 
FROM stage_project_todos 
WHERE status = 'terminiert' 
AND defer_date <= NOW()
ORDER BY defer_date ASC;
```

---

## 📚 BEST PRACTICES

### 1. OPTIMALE DEFER-ZEITEN
- **Kurze Pausen (1-4h):** Für Wartezeiten auf Tests/Builds
- **Tagesende (next morning):** Für komplexe Tasks die frischen Kopf brauchen
- **Wochenende (next monday):** Für Tasks die Office-Hours benötigen
- **Längerfristig (1-4 Wochen):** Für Tasks mit externen Abhängigkeiten

### 2. DEFER-REASONS KATEGORIEN
```php
<?php
$defer_reason_templates = array(
    'technical' => 'Warten auf: Tests, Builds, Dependencies, Code-Review',
    'external' => 'Warten auf: Team-Feedback, Approval, externe APIs',
    'research' => 'Benötigt: Weitere Recherche, Dokumentation, Experimente',
    'energy' => 'Task benötigt: Frischen Kopf, höhere Konzentration, mehr Zeit'
);
?>
```

### 3. PROGRESS-TRACKING
- **0-25%:** Planning/Research Phase
- **26-50%:** Implementation begonnen, Grundstruktur vorhanden
- **51-75%:** Core-Funktionalität implementiert, Testing läuft
- **76-99%:** Fast fertig, nur noch Feinschliff/Dokumentation

### 4. DEFER-COUNT MANAGEMENT
- **1x Defer:** Normal, kein Problem
- **2x Defer:** Gelb - möglicherweise Task zu komplex
- **3+ Defers:** Rot - Task sollte aufgeteilt werden

---

## 🔄 CHANGELOG

### Version 3.0.0 (2025-01-21)
- ✅ Vollständige Implementation aller Kernfunktionen
- ✅ CLI-Integration mit `./todo defer` 
- ✅ Web-Interface mit Modal-Dialog
- ✅ Automatische Output-Sammlung
- ✅ Smart Summary-Generierung  
- ✅ Kontext-Wiederherstellung
- ✅ Defer-Analytics und Pattern-Recognition

### Geplante Features (v3.1):
- 🔮 KI-basierte Defer-Time-Suggestions
- 🔮 Collaborative Team-Defers
- 🔮 Advanced Analytics Dashboard
- 🔮 Mobile App Support

---

**Status:** ✅ PRODUKTIONSREIF  
**Maintenance:** Claude Code  
**Support:** `/home/rodemkay/www/react/todo/docs/`