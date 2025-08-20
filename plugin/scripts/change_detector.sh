#!/bin/bash
# Änderungs-Detektor für wichtige Dateien

WORK_DIR="/home/rodemkay/www/react"
IMPORTANT_FILES=(
    "$WORK_DIR/.env"
    "$WORK_DIR/CLAUDE.md"
    "$WORK_DIR/wp-project-todos/wp-project-todos.php"
    "$WORK_DIR/wp-project-todos/includes/class-admin.php"
)

echo "🔍 Prüfe auf Änderungen in wichtigen Dateien..."
echo "================================================"

for file in "${IMPORTANT_FILES[@]}"; do
    if [ -f "$file" ]; then
        # Prüfe ob Datei heute geändert wurde
        if [ $(find "$file" -mtime -1 | wc -l) -gt 0 ]; then
            echo "✅ GEÄNDERT: $file"
            echo "   Letzte Änderung: $(stat -c %y "$file" | cut -d' ' -f1,2)"
            echo "   Größe: $(stat -c %s "$file") bytes"
            
            # Spezielle Behandlung für .env
            if [[ "$file" == *".env" ]]; then
                echo "   ⚠️  WICHTIG: .env wurde geändert - prüfe ob neue Credentials dokumentiert sind!"
            fi
            
            # Spezielle Behandlung für CLAUDE.md
            if [[ "$file" == *"CLAUDE.md" ]]; then
                echo "   📝 Projekt-Dokumentation wurde aktualisiert"
            fi
        fi
    fi
done

# Prüfe auf neue Dateien
echo ""
echo "📁 Neue Dateien (letzte 24h):"
find "$WORK_DIR" -type f -mtime -1 -name "*.php" -o -name "*.js" -o -name "*.css" 2>/dev/null | grep -v node_modules | grep -v .git | head -10

echo ""
echo "💡 Empfehlung: Führe 'Session-Dokumentation erstellen' aus, um alle Änderungen zu dokumentieren."
