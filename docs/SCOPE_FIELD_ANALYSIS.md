# 📊 SCOPE Feld Analyse - Vollständige Übersicht

## 🔍 Zusammenfassung
Die `scope` Spalte in der Datenbank wird **AKTIV VERWENDET** und speichert den **Projektnamen** für jedes Todo.

## 📋 Datenbank-Schema

```sql
stage_project_todos
├── scope           VARCHAR(255)  -- Speichert Projektnamen (z.B. "Todo-Plugin")
├── project_id      INT(11)       -- Verknüpfung zur projects Tabelle
└── project_name    VARCHAR(255)  -- Redundante Speicherung des Projektnamens
```

## 🗂️ Aktuelle Verwendung von SCOPE

### 1. **Gespeicherte Werte** (aus der Datenbank)
```
- Todo-Plugin  (Hauptprojekt)
- ForexSignale
- BreakoutBrain  
- Global
- Live Seite
- test
```

### 2. **Wo SCOPE gelesen wird**

#### A. **Dashboard-Filterung** (`class-todo-model.php:584`)
```php
// Gruppierung nach Projekten für Statistiken
SELECT scope, COUNT(*) as count 
FROM stage_project_todos 
GROUP BY scope
```
**Zweck:** Zeigt Projekt-Übersicht mit Anzahl der Todos pro Projekt

#### B. **Projekt-Filter im Dashboard** (`wsj-dashboard.php`)
```php
// Filterung nach ausgewähltem Projekt
SELECT * FROM $table WHERE scope = %s
```
**Zweck:** Filtert Todo-Liste nach ausgewähltem Projekt

#### C. **Template-Vorlagen** (`new-todo-wsj.php:36`)
```php
// Laden der Standard-Werte für neues Todo
SELECT scope, working_directory FROM $table WHERE id = %d
```
**Zweck:** Übernimmt Projekt vom Parent-Todo

### 3. **Wo SCOPE geschrieben wird**

#### Beim Erstellen neuer Todos:
```php
// todo.php:581
'scope' => sanitize_text_field($_POST['project'] ?? 'Todo-Plugin')
```

#### Beim Bearbeiten:
```php
// class-admin.php:856
'scope' => sanitize_key($_POST['project'] ?? 'Todo-Plugin')
```

## ⚠️ Das Problem: Redundanz

Es gibt **DREI Felder** für dasselbe:
1. `scope` - Historisch, speichert Projektnamen
2. `project_id` - Neue Struktur, Referenz zur projects Tabelle
3. `project_name` - Redundante Speicherung

## 🎯 Warum SCOPE wichtig ist

1. **Projekt-Filterung:** Dashboard zeigt Todos gefiltert nach Projekt
2. **Statistiken:** Zählung der Todos pro Projekt
3. **Vererbung:** Neue Todos übernehmen Projekt vom Parent
4. **Backwards-Compatibility:** Alte Todos nutzen noch scope

## 🔧 Aktuelle Lösung

Nach den Fixes wird `scope` korrekt befüllt:
- HTML-Formular sendet `name="project"`
- PHP mappt `$_POST['project']` → `scope` Spalte
- Funktioniert trotz Namensinkonsistenz

## 📈 Empfehlung

### Kurzfristig (erledigt ✅)
- Formular-Felder vereinheitlicht auf `project`
- PHP-Mapping funktioniert korrekt

### Langfristig (TODO)
1. **Migration planen:**
   - `scope` → `project` umbenennen in DB
   - Oder `project_id` als primäre Referenz nutzen
   - `scope` und `project_name` entfernen

2. **Vereinfachung:**
   ```sql
   ALTER TABLE stage_project_todos 
   CHANGE scope project VARCHAR(255);
   ```

3. **Normalisierung:**
   - Nur `project_id` behalten
   - Projektname aus `projects` Tabelle joinen

## ✅ Status
**SCOPE wird aktiv genutzt und korrekt befüllt.** Die Namensinkonsistenz zwischen Formular (`project`) und Datenbank (`scope`) ist behoben durch korrektes Mapping in PHP.