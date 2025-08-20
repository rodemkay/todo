#!/bin/bash
# Auto-Dokumentations-Script für Claude Sessions

WORK_DIR="/home/rodemkay/www/react"
CLAUDE_MD="$WORK_DIR/CLAUDE.md"
DATE=$(date +"%Y-%m-%d %H:%M")

# Session-Dokumentation Template
cat >> "$CLAUDE_MD" << TEMPLATE

## 📅 CHANGELOG - $DATE

### 🔄 Session: Auto-Dokumentation

#### 🎯 BEARBEITETE AUFGABEN:
$(mysql -h 159.69.157.54 -u ForexSignale -p'.Foret333doka?' staging_forexsignale -B -N -e "
SELECT CONCAT('- #', id, ': ', title, ' (', status, ')')
FROM stage_project_todos 
WHERE DATE(COALESCE(completed_at, started_at, updated_at)) = CURDATE()
ORDER BY id DESC LIMIT 10;
")

#### 📁 GEÄNDERTE DATEIEN:
$(find $WORK_DIR -type f -mtime -1 -name "*.php" -o -name "*.js" -o -name "*.css" 2>/dev/null | head -20)

#### 🔧 WICHTIGE ÄNDERUNGEN:
- [Hier manuelle Notizen einfügen]

#### 💡 LESSONS LEARNED:
- [Hier Erkenntnisse einfügen]

---
TEMPLATE

echo "Dokumentation wurde zu $CLAUDE_MD hinzugefügt"
