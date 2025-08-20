# Database Protection Hook System

## 🎯 Zweck
Das DB Protection System erlaubt normale Datenbankoperationen, blockiert aber nur kritische Status-Änderungen die das Hook-System umgehen würden.

## ✅ ERLAUBTE Operationen

### Immer erlaubt:
- **INSERT** - Neue Todos erstellen
- **DELETE** - Todos löschen  
- **UPDATE** von:
  - `claude_notes` - Claude kann Notizen schreiben
  - `claude_output` - Claude kann Output speichern
  - `bemerkungen` - Bemerkungen hinzufügen
  - `description` - Beschreibung ändern
  - `title` - Titel ändern
  - `priority` - Priorität ändern
  - `working_directory` - Arbeitsverzeichnis ändern
  - `updated_at` - Zeitstempel updates
  - Alle anderen Felder außer geschützte Status-Übergänge

### Status-Änderungen:
- ✅ `offen` → `in_progress` (erlaubt)
- ✅ `in_progress` → `blocked` (erlaubt)
- ✅ `blocked` → `in_progress` (erlaubt)
- ✅ `completed` → `offen` (Wiederöffnen erlaubt)
- ❌ `in_progress` → `completed` (NUR über TASK_COMPLETED)
- ❌ `offen` → `completed` (NUR über TASK_COMPLETED)

## 🔒 BLOCKIERTE Operationen

Nur diese spezifischen Status-Änderungen werden blockiert:
1. **Direkte Completion ohne Hook:**
   - `status = 'completed'` ohne TASK_COMPLETED Trigger
   
2. **Geschützte Status-Übergänge:**
   - `in_progress` → `completed` (muss über Hook)
   - `offen` → `completed` (muss über Hook)

## 📝 Beispiele

### Erlaubt - Claude Notes Update:
```python
wpdb.update('wp_project_todos', {
    'claude_notes': 'Analyse abgeschlossen...',
    'updated_at': current_time()
}, {'id': 123})
# ✅ ERLAUBT - nur Notes Update
```

### Erlaubt - Status zu in_progress:
```python
wpdb.update('wp_project_todos', {
    'status': 'in_progress',
    'updated_at': current_time()
}, {'id': 123})
# ✅ ERLAUBT - nicht-geschützter Übergang
```

### Blockiert - Direkte Completion:
```python
wpdb.update('wp_project_todos', {
    'status': 'completed'
}, {'id': 123})
# ❌ BLOCKIERT - muss über TASK_COMPLETED
```

### Erlaubt - Completion über Hook:
```bash
echo 'TASK_COMPLETED' > /tmp/TASK_COMPLETED
# ✅ Triggert Hook → Status wird auf completed gesetzt
```

## 🔧 Integration

Das System arbeitet mit dem bestehenden Hook-System zusammen:
1. **consistency_validator.py** - Überwacht TodoWrite operations
2. **db_protection.py** - Filtert erlaubte/blockierte DB-Operationen
3. **status_changed.sh** - Führt erlaubte Status-Änderungen aus

## 📊 Logging

Alle Entscheidungen werden geloggt in:
- `/tmp/db_protection.log` - Protection decisions
- `/tmp/hook_audit.log` - Allgemeines Hook-Audit

## 💡 Vorteile

1. **Flexibilität:** Claude kann normal arbeiten und Datenbank updaten
2. **Sicherheit:** Kritische Status-Änderungen nur über Hook-System
3. **Transparenz:** Alle Blockierungen werden klar begründet
4. **Minimal-invasiv:** Nur wirklich kritische Operationen werden blockiert

## 🚀 Aktivierung

```bash
# Aktiviere DB Protection
chmod +x /home/rodemkay/www/react/wp-project-todos/hooks/db_protection.py

# Teste mit Beispiel
python3 /home/rodemkay/www/react/wp-project-todos/hooks/db_protection.py \
  '{"type":"UPDATE","table":"wp_project_todos","data":{"claude_notes":"Test"}}'
```