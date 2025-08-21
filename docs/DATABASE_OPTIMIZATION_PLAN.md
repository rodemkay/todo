# 📊 TODO SYSTEM - DATABASE OPTIMIZATION PLAN

## 🔍 AKTUELLE ANALYSE

### Datenbankstatistik (Stand: 2025-08-21)
- **Gesamt Todos:** 35
- **Status-Verteilung:**
  - Offen: 4
  - In Progress: 2
  - Completed: 21
  - Blocked: 2
  - Cron: 6
- **Parent-Child Relationships:** 0 aktuelle Child-Todos
- **Recurring Todos:** 2

### Strukturelle Probleme
1. **Datentyp-Inkonsistenz:** Mixture aus ENUM und VARCHAR
2. **Fehlende Constraints:** Keine Foreign Keys für parent_todo_id
3. **Suboptimale Indizierung:** Fehlende Composite-Indizes für häufige Filter-Kombinationen
4. **Normalisierungsprobleme:** MCP Servers, Tags als TEXT statt separate Tabellen
5. **Fehlende Notifications-Infrastruktur**

## 🎯 OPTIMIERUNGSPLAN

### 1. WIEDERVORLAGE SYSTEM ENHANCEMENT

#### 1.1 Todo Relationship Hierarchie
```sql
-- Erweiterte Parent-Child Struktur
ALTER TABLE stage_project_todos 
ADD CONSTRAINT fk_parent_todo 
FOREIGN KEY (parent_todo_id) REFERENCES stage_project_todos(id) 
ON DELETE SET NULL;

-- Neue Spalten für Wiedervorlage
ALTER TABLE stage_project_todos
ADD COLUMN followup_date DATE,
ADD COLUMN followup_reason TEXT,
ADD COLUMN context_transfer TEXT,
ADD COLUMN inherited_context BOOLEAN DEFAULT FALSE,
ADD INDEX idx_followup_date (followup_date),
ADD INDEX idx_parent_status (parent_todo_id, status);
```

#### 1.2 Context Transfer Tabelle
```sql
CREATE TABLE stage_todo_context_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_todo_id INT NOT NULL,
    target_todo_id INT NOT NULL,
    transfer_type ENUM('full', 'partial', 'summary') DEFAULT 'partial',
    transferred_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    FOREIGN KEY (target_todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    INDEX idx_source_todo (source_todo_id),
    INDEX idx_target_todo (target_todo_id),
    INDEX idx_transfer_chain (source_todo_id, target_todo_id)
);
```

### 2. SMART FILTERS OPTIMIZATION

#### 2.1 Composite Indizes für häufige Filter-Kombinationen
```sql
-- Multi-Status Filter Performance
CREATE INDEX idx_status_priority_scope ON stage_project_todos(status, priority, scope);
CREATE INDEX idx_development_area_status ON stage_project_todos(development_area, status);
CREATE INDEX idx_bearbeiten_status_priority ON stage_project_todos(bearbeiten, status, priority);

-- Datum-basierte Filter
CREATE INDEX idx_created_status_priority ON stage_project_todos(created_at, status, priority);
CREATE INDEX idx_due_status ON stage_project_todos(due_date, status) WHERE due_date IS NOT NULL;
CREATE INDEX idx_deadline_range ON stage_project_todos(due_date, status, priority) WHERE due_date IS NOT NULL;

-- Claude-spezifische Filter
CREATE INDEX idx_claude_mode_status ON stage_project_todos(claude_mode, status, bearbeiten);
```

#### 2.2 Normalisierung für bessere Performance
```sql
-- MCP Servers Normalisierung
CREATE TABLE stage_todo_mcp_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    todo_id INT NOT NULL,
    server_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    INDEX idx_todo_server (todo_id, server_name),
    INDEX idx_server_active (server_name, is_active)
);

-- Tags Normalisierung
CREATE TABLE stage_todo_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    todo_id INT NOT NULL,
    tag_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    INDEX idx_todo_tag (todo_id, tag_name),
    INDEX idx_tag_search (tag_name),
    UNIQUE KEY unique_todo_tag (todo_id, tag_name)
);

-- Dependencies Normalisierung
CREATE TABLE stage_todo_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    todo_id INT NOT NULL,
    depends_on_todo_id INT NOT NULL,
    dependency_type ENUM('blocks', 'requires', 'follows') DEFAULT 'requires',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    INDEX idx_todo_dependencies (todo_id),
    INDEX idx_dependency_chain (depends_on_todo_id, todo_id),
    UNIQUE KEY unique_dependency (todo_id, depends_on_todo_id)
);
```

### 3. NOTIFICATIONS SYSTEM

#### 3.1 Notifications Infrastruktur
```sql
CREATE TABLE stage_todo_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    todo_id INT NOT NULL,
    notification_type ENUM('deadline_approaching', 'status_changed', 'assigned', 'completed', 'blocked', 'followup_due') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    FOREIGN KEY (todo_id) REFERENCES stage_project_todos(id) ON DELETE CASCADE,
    INDEX idx_unread_notifications (is_read, created_at),
    INDEX idx_todo_notifications (todo_id, is_read),
    INDEX idx_notification_type (notification_type, is_read),
    INDEX idx_priority_unread (priority, is_read, created_at)
);

CREATE TABLE stage_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 1,
    notification_type ENUM('deadline_approaching', 'status_changed', 'assigned', 'completed', 'blocked', 'followup_due') NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    delivery_method ENUM('dashboard', 'email', 'webhook') DEFAULT 'dashboard',
    advance_notice_hours INT DEFAULT 24,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_notification (user_id, notification_type)
);
```

### 4. PERFORMANCE OPTIMIERTE QUERIES

#### 4.1 Dashboard Filter Queries
```sql
-- Multi-Status Filter (optimiert)
SELECT t.*, 
       COUNT(d.id) as dependency_count,
       GROUP_CONCAT(tag.tag_name) as tag_list
FROM stage_project_todos t
LEFT JOIN stage_todo_dependencies d ON t.id = d.todo_id
LEFT JOIN stage_todo_tags tag ON t.id = tag.todo_id
WHERE t.status IN ('offen', 'in_progress') 
  AND t.bearbeiten = 1
  AND t.priority IN ('hoch', 'kritisch')
GROUP BY t.id
ORDER BY 
  FIELD(t.priority, 'kritisch', 'hoch', 'mittel', 'niedrig'),
  t.created_at DESC
LIMIT 20;

-- Project Hierarchy Query
WITH RECURSIVE todo_hierarchy AS (
    SELECT id, title, parent_todo_id, scope, 0 as level
    FROM stage_project_todos
    WHERE parent_todo_id IS NULL AND scope = 'todo-plugin'
    
    UNION ALL
    
    SELECT t.id, t.title, t.parent_todo_id, t.scope, h.level + 1
    FROM stage_project_todos t
    JOIN todo_hierarchy h ON t.parent_todo_id = h.id
)
SELECT * FROM todo_hierarchy ORDER BY level, id;

-- Development Area Performance Query
SELECT 
    development_area,
    status,
    COUNT(*) as count,
    AVG(estimated_hours) as avg_hours,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
FROM stage_project_todos
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY development_area, status
ORDER BY development_area, FIELD(status, 'offen', 'in_progress', 'completed', 'blocked');
```

#### 4.2 Wiedervorlage Smart Queries
```sql
-- Wiedervorlage mit Context
SELECT t.*, 
       p.title as parent_title,
       p.claude_summary as parent_summary,
       ct.transferred_data as inherited_context
FROM stage_project_todos t
LEFT JOIN stage_project_todos p ON t.parent_todo_id = p.id
LEFT JOIN stage_todo_context_transfers ct ON ct.target_todo_id = t.id
WHERE t.followup_date <= CURDATE()
  AND t.status = 'offen'
ORDER BY t.priority DESC, t.followup_date ASC;

-- Context Chain Query
SELECT t1.id, t1.title, t1.status,
       t2.id as parent_id, t2.title as parent_title,
       t3.id as grandparent_id, t3.title as grandparent_title
FROM stage_project_todos t1
LEFT JOIN stage_project_todos t2 ON t1.parent_todo_id = t2.id
LEFT JOIN stage_project_todos t3 ON t2.parent_todo_id = t3.id
WHERE t1.inherited_context = TRUE
ORDER BY t1.created_at DESC;
```

### 5. MONITORING & MAINTENANCE

#### 5.1 Performance Monitoring Views
```sql
CREATE VIEW v_todo_performance_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as todos_created,
    AVG(TIMESTAMPDIFF(HOUR, created_at, completed_date)) as avg_completion_hours,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_today,
    COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_today
FROM stage_project_todos
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

CREATE VIEW v_todo_workload_by_area AS
SELECT 
    development_area,
    COUNT(CASE WHEN status IN ('offen', 'in_progress') THEN 1 END) as active_todos,
    COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_todos,
    AVG(priority_numeric) as avg_priority
FROM stage_project_todos t
JOIN (
    SELECT 'niedrig' as priority, 1 as priority_numeric
    UNION SELECT 'mittel', 2
    UNION SELECT 'hoch', 3  
    UNION SELECT 'kritisch', 4
) p ON t.priority = p.priority
GROUP BY development_area
ORDER BY avg_priority DESC;
```

#### 5.2 Maintenance Scripts
```sql
-- Alte Notifications aufräumen (älter als 30 Tage)
DELETE FROM stage_todo_notifications 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
  AND is_read = TRUE;

-- Expired Notifications löschen
DELETE FROM stage_todo_notifications 
WHERE expires_at IS NOT NULL 
  AND expires_at < NOW();

-- Verwaiste Context Transfers bereinigen
DELETE ct FROM stage_todo_context_transfers ct
LEFT JOIN stage_project_todos t1 ON ct.source_todo_id = t1.id
LEFT JOIN stage_project_todos t2 ON ct.target_todo_id = t2.id
WHERE t1.id IS NULL OR t2.id IS NULL;
```

## 📋 IMPLEMENTIERUNGSSCHRITTE

### Phase 1: Struktur-Optimierung (Priorität HOCH)
1. ✅ Foreign Key Constraints hinzufügen
2. ✅ Composite Indizes erstellen
3. ✅ Wiedervorlage-Spalten hinzufügen
4. ✅ Performance Views erstellen

### Phase 2: Normalisierung (Priorität MITTEL)
1. ⏳ MCP Servers Tabelle erstellen und migrieren
2. ⏳ Tags Tabelle erstellen und migrieren  
3. ⏳ Dependencies Tabelle erstellen und migrieren
4. ⏳ Context Transfers implementieren

### Phase 3: Notifications (Priorität MITTEL)
1. ⏳ Notifications Infrastruktur aufbauen
2. ⏳ Preferences System implementieren
3. ⏳ Trigger für automatische Benachrichtigungen
4. ⏳ Dashboard Integration

### Phase 4: Advanced Features (Priorität NIEDRIG)
1. ⏳ Reporting Dashboard
2. ⏳ Analytics Views
3. ⏳ Automated Maintenance
4. ⏳ Performance Monitoring

## 🚀 ERWARTETE PERFORMANCE-VERBESSERUNGEN

- **Dashboard Load Time:** 60% Reduktion durch optimierte Indizes
- **Filter Operations:** 75% schneller durch Composite Indizes
- **Wiedervorlage Queries:** 80% Performance-Gewinn durch dedizierte Struktur
- **Hierarchical Queries:** 90% schneller durch Foreign Key Constraints
- **Notification Queries:** Neue Funktionalität mit < 50ms Response Time

## 🔍 MONITORING METRIKEN

- Query Response Times per Operation Type
- Index Hit Ratio (Target: > 95%)
- Slow Query Log Analysis
- Table Size Growth Tracking
- Notification Delivery Success Rate

---

**Letzte Aktualisierung:** 2025-08-21  
**Version:** 1.0.0  
**Autor:** Claude Code Data Analyst