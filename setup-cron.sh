#!/bin/bash

# Setup Cron Job für Todo-Manager
# Optional - nur wenn gewünscht

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONTROL_SCRIPT="$SCRIPT_DIR/todo-manager-control.sh"

echo "Todo-Manager Cron Setup"
echo "======================="
echo ""
echo "Möchten Sie den Todo-Manager als Cron-Job einrichten?"
echo "Der Manager wird dann automatisch gestartet, falls er nicht läuft."
echo ""
echo "Optionen:"
echo "1) Alle 5 Minuten prüfen"
echo "2) Alle 10 Minuten prüfen"
echo "3) Alle 30 Minuten prüfen"
echo "4) Stündlich prüfen"
echo "5) Cron-Job entfernen"
echo "0) Abbrechen"
echo ""
read -p "Wählen Sie eine Option (0-5): " choice

# Remove existing cron job first
(crontab -l 2>/dev/null | grep -v "todo-manager-control.sh check") | crontab -

case $choice in
    1)
        # Every 5 minutes
        (crontab -l 2>/dev/null; echo "*/5 * * * * $CONTROL_SCRIPT check") | crontab -
        echo "✅ Cron-Job eingerichtet: Alle 5 Minuten"
        ;;
    2)
        # Every 10 minutes
        (crontab -l 2>/dev/null; echo "*/10 * * * * $CONTROL_SCRIPT check") | crontab -
        echo "✅ Cron-Job eingerichtet: Alle 10 Minuten"
        ;;
    3)
        # Every 30 minutes
        (crontab -l 2>/dev/null; echo "*/30 * * * * $CONTROL_SCRIPT check") | crontab -
        echo "✅ Cron-Job eingerichtet: Alle 30 Minuten"
        ;;
    4)
        # Every hour
        (crontab -l 2>/dev/null; echo "0 * * * * $CONTROL_SCRIPT check") | crontab -
        echo "✅ Cron-Job eingerichtet: Stündlich"
        ;;
    5)
        echo "✅ Cron-Job entfernt"
        ;;
    0)
        echo "Abgebrochen"
        exit 0
        ;;
    *)
        echo "Ungültige Option"
        exit 1
        ;;
esac

echo ""
echo "Aktuelle Cron-Jobs:"
crontab -l 2>/dev/null | grep todo-manager || echo "Keine Todo-Manager Cron-Jobs gefunden"