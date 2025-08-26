# new-todo-v2.php - Formular zu Datenbank Mapping

## Datei-Informationen
- **Pfad:** `/var/www/forexsignale/staging/wp-content/plugins/todo/admin/new-todo-v2.php`
- **Zweck:** Formular für neue Todo-Erstellung und -Bearbeitung
- **Letzte Änderung:** 21.08.2025

## Formular-Felder zu Datenbank-Spalten Mapping

### Hauptfelder
| Formular-Feld (name=) | Datenbank-Spalte | Typ | Standard-Wert |
|----------------------|------------------|-----|---------------|
| title | title | VARCHAR(255) | - |
| description | description | TEXT | - |
| bemerkungen | bemerkungen | TEXT | - |
| claude_prompt | claude_prompt | TEXT | - |

### Status & Priorität
| Formular-Feld | Datenbank-Spalte | Werte | Standard |
|--------------|------------------|-------|----------|
| status | status | offen, in_progress, completed, blocked, cancelled, cron | offen |
| priority | priority | niedrig, mittel, hoch, kritisch | mittel |

### Projekt & Arbeitsumgebung  
| Formular-Feld | Datenbank-Spalte | Beschreibung | Standard |
|--------------|------------------|--------------|----------|
| project (dropdown) | default_project | Projekt-Name | To-Do Plugin |
| working_directory | working_directory | Arbeitsverzeichnis-Pfad | /home/rodemkay/www/react/plugin-todo/ |
| dev_area | development_area | Frontend, Backend, Full-Stack | Backend |

### Claude & Agent Konfiguration
| Formular-Feld | Datenbank-Spalte | Typ | Beschreibung |
|--------------|------------------|-----|--------------|
| bearbeiten (checkbox) | bearbeiten | TINYINT(1) | Von Claude bearbeiten lassen (0/1) |
| num_agents / agent_count | agent_count | INT | Anzahl Agents (0-30) |
| execution_mode | execution_mode | ENUM | default, parallel, hierarchical |

### MCP Server (Checkboxen)
| Formular-Feld | Datenbank-Spalte | Gespeichert als |
|--------------|------------------|-----------------|
| mcp_context7 | mcp_servers | JSON Array mit aktiven Servern |
| mcp_playwright | mcp_servers | " |
| mcp_filesystem | mcp_servers | " |
| mcp_github | mcp_servers | " |
| mcp_puppeteer | mcp_servers | " |
| mcp_docker | mcp_servers | " |
| mcp_youtube | mcp_servers | " |
| mcp_database | mcp_servers | " |
| mcp_shadcn | mcp_servers | " |

### Cron-Job Einstellungen
| Formular-Feld | Datenbank-Spalte | Typ | Beschreibung |
|--------------|------------------|-----|--------------|
| is_recurring | is_recurring | TINYINT(1) | Als Cron-Job einrichten |
| cron_schedule | cron_schedule | VARCHAR(100) | Cron-Schedule (z.B. "every_15_minutes") |

## Bekannte Probleme & Fixes (Stand: 21.08.2025)

### 1. ✅ BEHOBEN: bearbeiten-Checkbox Bug
- **Problem:** Checkbox-Wert wurde immer als 1 gespeichert
- **Fix:** 
  - Element-ID von `claude-auto` auf `bearbeiten_checkbox` geändert
  - JavaScript-Sync zwischen Checkbox und Hidden Field implementiert
  - Default-Wert für neue Aufgaben korrigiert

### 2. ✅ BEHOBEN: Fehlende Projekte
- **Problem:** MT5 und N8N fehlten im Dropdown
- **Fix:** WordPress Option `todo_saved_projects` aktualisiert

### 3. ⚠️ INKONSISTENZ: development_area Werte
- **Problem:** Inkonsistente Werte in DB: "fullstack" vs "Full-Stack" 
- **Teilfix:** Radio-Button prüft jetzt beide Varianten
- **TODO:** Datenbank-Migration für konsistente Werte

## JavaScript-Dateien
- **todo-form-ajax.js:** Verarbeitet AJAX-Submit
  - Zeile 30: `bearbeiten: $('#bearbeiten_checkbox').is(':checked') ? 1 : 0`
  - Sammelt MCP-Server als einzelne POST-Parameter

## PHP-Verarbeitung (todo.php)
- **Zeile 318:** `'bearbeiten' => intval($_POST['bearbeiten'] ?? 0)`
- **Zeile 316:** `"development_area" => sanitize_text_field($_POST["dev_area"] ?? "Backend")`
- **MCP-Server:** Werden als JSON-String in `mcp_servers` gespeichert

## Verfügbare Projekte (Stand: 21.08.2025)
1. To-Do Plugin
2. ForexSignale  
3. Homepage
4. Article Builder
5. Staging
6. MT5 Trading
7. N8N Automation

## Hinweise zur Wartung
- Bei neuen Feldern: Sowohl `new-todo-v2.php` als auch `todo-form-ajax.js` anpassen
- Datenbank-Spalten prüfen mit: `wp db query 'DESCRIBE stage_project_todos'`
- JavaScript-Debugging: Browser-Konsole prüft AJAX-Requests