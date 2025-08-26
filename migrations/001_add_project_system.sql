-- Migration 001: Add Project System
-- Date: 2025-08-22
-- Description: Adds project management tables and fields for Todo plugin

-- 1. Add project fields to todos table
ALTER TABLE stage_project_todos 
ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL AFTER scope,
ADD COLUMN IF NOT EXISTS project_name VARCHAR(255) DEFAULT NULL AFTER project_id,
ADD INDEX IF NOT EXISTS idx_project (project_id),
ADD INDEX IF NOT EXISTS idx_project_status (project_id, status);

-- 2. Create projects table
CREATE TABLE IF NOT EXISTS stage_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    color VARCHAR(7) DEFAULT '#667eea',
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create user project sessions table
CREATE TABLE IF NOT EXISTS stage_user_project_sessions (
    user_id INT PRIMARY KEY,
    active_project_id INT,
    session_token VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_token (session_token),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Insert default projects
INSERT INTO stage_projects (name, slug, description, color, icon, sort_order) VALUES
('Todo-Plugin', 'todo-plugin', 'Todo Plugin Development', '#667eea', '🔧', 1),
('ForexSignale', 'forexsignale', 'ForexSignale Magazine', '#764ba2', '💱', 2),
('System', 'system', 'System & Infrastructure', '#10b981', '⚙️', 3),
('Documentation', 'documentation', 'Documentation & Guides', '#f59e0b', '📚', 4),
('Andere', 'andere', 'Sonstige Aufgaben', '#6b7280', '📋', 99)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- 5. Migrate existing todos to projects based on scope
UPDATE stage_project_todos t
SET project_id = (
    SELECT id FROM stage_projects p 
    WHERE p.slug = CASE 
        WHEN t.scope = 'todo-plugin' THEN 'todo-plugin'
        WHEN t.scope = 'forexsignale' THEN 'forexsignale'
        WHEN t.scope = 'system' THEN 'system'
        ELSE 'andere'
    END
    LIMIT 1
),
project_name = (
    SELECT name FROM stage_projects p 
    WHERE p.slug = CASE 
        WHEN t.scope = 'todo-plugin' THEN 'todo-plugin'
        WHEN t.scope = 'forexsignale' THEN 'forexsignale'
        WHEN t.scope = 'system' THEN 'system'
        ELSE 'andere'
    END
    LIMIT 1
)
WHERE project_id IS NULL;

-- 6. Set default project for todos without scope
UPDATE stage_project_todos 
SET project_id = (SELECT id FROM stage_projects WHERE slug = 'andere' LIMIT 1),
    project_name = (SELECT name FROM stage_projects WHERE slug = 'andere' LIMIT 1)
WHERE project_id IS NULL;