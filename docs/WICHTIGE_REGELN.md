# 🚨 WICHTIGE REGELN FÜR TODO-SYSTEM

## AUTOMATISCHES LADEN VON TODOS

### ✅ KORREKTE REGEL (Stand: 25.08.2025)
**NUR** Todos mit folgenden Kriterien werden automatisch geladen:
- `status = 'offen'` (NICHT in_progress!)
- `bearbeiten = 1`

### ❌ FALSCH
- KEINE in_progress Todos automatisch laden
- Auch wenn bearbeiten=1 gesetzt ist

### 📝 BEGRÜNDUNG
In_progress bedeutet, dass ein Todo bereits in Bearbeitung ist. Diese sollen NICHT automatisch neu geladen werden, da sie bereits bearbeitet werden.

### 🔧 IMPLEMENTIERUNG
Die Query im Monitor-Script (`intelligent_todo_monitor_fixed.sh`) lautet:
```sql
SELECT id, title, priority 
FROM ${DB_PREFIX}project_todos 
WHERE status = 'offen' AND bearbeiten = 1 
ORDER BY priority, created_at ASC 
LIMIT 1
```

### 📌 MERKE
- `offen` + `bearbeiten=1` = Automatisch laden ✅
- `in_progress` + `bearbeiten=1` = NICHT automatisch laden ❌
- Diese Regel ist FINAL und darf nicht geändert werden!

---
*Letzte Aktualisierung: 25.08.2025 04:55 Uhr*