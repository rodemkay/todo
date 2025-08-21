-- Migration für strukturierte Plan-Daten
-- Neue Spalte für JSON-Struktur der Pläne

USE staging_forexsignale;

-- Neue Spalte für strukturierte Plan-Daten hinzufügen
ALTER TABLE stage_project_todos 
ADD COLUMN plan_structure LONGTEXT NULL 
COMMENT 'JSON-strukturierte Plan-Daten für benutzerfreundliche Bearbeitung'
AFTER plan_html;

-- Index für bessere Performance bei Plan-Queries
CREATE INDEX idx_planning_mode ON stage_project_todos(is_planning_mode, plan_created_at);

-- Status anzeigen
DESCRIBE stage_project_todos;