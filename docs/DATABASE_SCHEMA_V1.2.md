# Todo Plugin Database Schema v1.2.0

## Agent Documentation System Fields

### Multi-Agent System Configuration

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `agent_count` | int(11) | 0 | Anzahl Subagenten (0=Standard/1 Agent, 1=Orchestrator+1 Subagent, 5=Orchestrator+5 Subagenten) |
| `agent_settings` | text | NULL | JSON-Konfiguration für Agent-Einstellungen |
| `subagent_instructions` | text | NULL | Benutzerdefinierte Anweisungen für Subagenten |
| `save_agent_outputs` | tinyint(1) | 0 | Subagenten sollen ihre Schritte dokumentieren (1=ja, 0=nein) |
| `execution_mode` | enum | 'default' | Ausführungsmodus: parallel, hierarchical, default |

### Agent Count Examples

- `agent_count = 0` → 1 Agent total (Standard-Modus)
- `agent_count = 1` → 2 Agents total (1 Orchestrator + 1 Subagent)  
- `agent_count = 3` → 4 Agents total (1 Orchestrator + 3 Subagents)
- `agent_count = 5` → 6 Agents total (1 Orchestrator + 5 Subagents)

### Subagent Instructions Templates

Standard-Templates verfügbar via WordPress Option `todo_default_subagent_templates`:

1. **testing** - Browser-Tests und umfangreiche Validierung
2. **frontend_backend** - Spezialisierte UI/UX und Server-Logic Agents
3. **documentation** - Ausführliche Schritt-für-Schritt Dokumentation  
4. **performance** - Optimierungen und Bottleneck-Analyse
5. **security** - Sicherheitsprüfungen und Best Practices
6. **database** - DB-Operationen und Schema-Optimierungen

### Integration in Workflows

**Multi-Agent Workflow:**
1. Task wird mit `agent_count > 0` erstellt
2. Orchestrator liest `subagent_instructions`
3. Subagents werden mit spezifischen Briefings gestartet
4. Bei `save_agent_outputs = 1` → Dokumentation in `/agent-outputs/todo-{id}/`
5. Orchestrator sammelt alle Ergebnisse und erstellt Gesamtbericht

**Dokumentations-Struktur:**
```
/agent-outputs/
├── todo-300/
│   ├── orchestrator-main.md
│   ├── subagent-1-database.md
│   ├── subagent-2-frontend.md
│   └── subagent-3-testing.md
```

## Migration History

- **v1.0.0** - Initial schema
- **v1.1.0** - Added recurring tasks and CRON support
- **v1.2.0** - Added Agent Documentation System fields

## Schema Update Commands

```sql
-- Add subagent_instructions field
ALTER TABLE stage_project_todos 
ADD COLUMN subagent_instructions TEXT DEFAULT NULL 
COMMENT 'Benutzerdefinierte Anweisungen für Subagenten' 
AFTER agent_settings;

-- Update field comments
ALTER TABLE stage_project_todos 
MODIFY COLUMN agent_count int(11) DEFAULT 0 
COMMENT 'Anzahl Subagenten (0=Standard/1 Agent, 1=Orchestrator+1 Subagent, 5=Orchestrator+5 Subagenten)';

ALTER TABLE stage_project_todos 
MODIFY COLUMN save_agent_outputs tinyint(1) DEFAULT 0 
COMMENT 'Subagenten sollen ihre Schritte dokumentieren (1=ja, 0=nein)';
```