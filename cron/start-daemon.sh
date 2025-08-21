#!/bin/bash
# TODO Cron-Daemon Starter Script

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DAEMON_SCRIPT="$SCRIPT_DIR/cron_daemon.py"
PID_FILE="$SCRIPT_DIR/cron_daemon.pid"
LOG_DIR="$SCRIPT_DIR/logs"

# Erstelle Log-Verzeichnis falls nicht vorhanden
mkdir -p "$LOG_DIR"

# Prüfe ob Daemon bereits läuft
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "Cron-Daemon läuft bereits (PID: $PID)"
        exit 1
    else
        echo "Stale PID-Datei gefunden, lösche..."
        rm -f "$PID_FILE"
    fi
fi

# Installiere Python-Abhängigkeiten falls notwendig
echo "Prüfe Python-Abhängigkeiten..."
python3 -c "import mysql.connector, croniter, requests, daemon" 2>/dev/null || {
    echo "Installiere fehlende Python-Pakete..."
    pip3 install --user mysql-connector-python croniter requests python-daemon
}

# Starte Daemon
echo "Starte TODO Cron-Daemon..."
echo "Daemon-Script: $DAEMON_SCRIPT"
echo "PID-Datei: $PID_FILE"
echo "Log-Verzeichnis: $LOG_DIR"

# Mache Script ausführbar
chmod +x "$DAEMON_SCRIPT"

# Starte im Hintergrund
nohup python3 "$DAEMON_SCRIPT" > "$LOG_DIR/daemon.out" 2>&1 &
DAEMON_PID=$!

# Speichere PID
echo "$DAEMON_PID" > "$PID_FILE"

# Warte kurz und prüfe ob Daemon läuft
sleep 2
if kill -0 "$DAEMON_PID" 2>/dev/null; then
    echo "✅ Cron-Daemon erfolgreich gestartet (PID: $DAEMON_PID)"
    echo "📄 Logs: $LOG_DIR/"
    echo "🛑 Stoppen mit: $SCRIPT_DIR/stop-daemon.sh"
    echo "📊 Status mit: $SCRIPT_DIR/status-daemon.sh"
else
    echo "❌ Fehler beim Starten des Daemons"
    echo "📄 Prüfe Logs in $LOG_DIR/daemon.out"
    rm -f "$PID_FILE"
    exit 1
fi