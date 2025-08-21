#!/bin/bash
# TODO Cron-Daemon Stopper Script

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="$SCRIPT_DIR/cron_daemon.pid"

echo "Stoppe TODO Cron-Daemon..."

# Prüfe ob PID-Datei existiert
if [ ! -f "$PID_FILE" ]; then
    echo "❌ PID-Datei nicht gefunden: $PID_FILE"
    echo "Daemon läuft möglicherweise nicht oder wurde bereits gestoppt."
    exit 1
fi

# Lese PID
PID=$(cat "$PID_FILE")

# Prüfe ob Prozess läuft
if ! kill -0 "$PID" 2>/dev/null; then
    echo "❌ Prozess mit PID $PID läuft nicht mehr"
    rm -f "$PID_FILE"
    exit 1
fi

echo "Sende SIGTERM an Prozess $PID..."
kill -TERM "$PID"

# Warte auf graceful shutdown (max 10 Sekunden)
for i in {1..10}; do
    if ! kill -0 "$PID" 2>/dev/null; then
        echo "✅ Daemon erfolgreich gestoppt"
        rm -f "$PID_FILE"
        exit 0
    fi
    echo "Warte auf Daemon-Shutdown... ($i/10)"
    sleep 1
done

# Falls graceful shutdown nicht funktioniert, erzwinge es
echo "⚠️  Graceful shutdown fehlgeschlagen, erzwinge Beendigung..."
kill -KILL "$PID" 2>/dev/null

# Prüfe erneut
if ! kill -0 "$PID" 2>/dev/null; then
    echo "✅ Daemon zwangsweise beendet"
    rm -f "$PID_FILE"
    exit 0
else
    echo "❌ Konnte Daemon nicht beenden"
    exit 1
fi