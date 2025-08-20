#!/usr/bin/env python3
"""
Database Protection Hook - Selektive Blockierung von Status-Änderungen

Dieses Script erlaubt normale Datenbankoperationen, blockiert aber nur
direkte Status-Änderungen die das Hook-System umgehen würden.
"""

import sys
import json
import os
from datetime import datetime

# Konfiguration
ALLOWED_DB_OPERATIONS = [
    'claude_notes',      # Claude kann Notizen schreiben
    'claude_output',     # Claude kann Output speichern
    'bemerkungen',       # Bemerkungen sind erlaubt
    'description',       # Beschreibung ändern ist ok
    'title',            # Titel ändern ist ok
    'priority',         # Priorität ändern ist ok
    'working_directory', # Arbeitsverzeichnis ändern ist ok
    'updated_at',       # Zeitstempel update ist ok
    'attachments',      # Anhänge sind ok
]

PROTECTED_STATUS_CHANGES = {
    'in_progress': ['completed'],  # in_progress → completed muss über TASK_COMPLETED
    'offen': ['completed'],        # offen → completed muss über TASK_COMPLETED
}

def is_status_change_allowed(current_status, new_status, via_hook=False):
    """
    Prüft ob eine Status-Änderung erlaubt ist
    """
    # Wenn über Hook-System, immer erlaubt
    if via_hook:
        return True
    
    # Prüfe ob diese Status-Änderung geschützt ist
    if current_status in PROTECTED_STATUS_CHANGES:
        protected_transitions = PROTECTED_STATUS_CHANGES[current_status]
        if new_status in protected_transitions:
            return False  # Diese Änderung muss über Hook-System
    
    # Alle anderen Status-Änderungen sind erlaubt
    return True

def check_db_operation(operation_type, table, data, context=None):
    """
    Prüft eine Datenbankoperation und entscheidet ob sie erlaubt ist
    
    Returns: (allowed, reason)
    """
    # Nur project_todos Tabelle überwachen
    if 'project_todos' not in table:
        return (True, "Andere Tabelle - nicht überwacht")
    
    # INSERT ist immer erlaubt (neue Todos erstellen)
    if operation_type == 'INSERT':
        return (True, "INSERT ist immer erlaubt")
    
    # DELETE ist immer erlaubt
    if operation_type == 'DELETE':
        return (True, "DELETE ist immer erlaubt")
    
    # Bei UPDATE genauer prüfen
    if operation_type == 'UPDATE':
        # Prüfe ob Status geändert wird
        if 'status' in data:
            new_status = data['status']
            
            # Hole aktuellen Status aus Context wenn vorhanden
            current_status = context.get('current_status') if context else None
            
            # Wenn wir den aktuellen Status kennen, prüfe ob Änderung erlaubt
            if current_status:
                if not is_status_change_allowed(current_status, new_status):
                    return (False, f"Status-Änderung {current_status} → {new_status} muss über TASK_COMPLETED Hook")
            
            # Wenn Status auf 'completed' gesetzt wird, immer über Hook
            if new_status == 'completed' and not context.get('via_hook'):
                return (False, "Status 'completed' muss über TASK_COMPLETED gesetzt werden")
        
        # Alle anderen Updates sind erlaubt
        return (True, "UPDATE ohne geschützte Status-Änderung")
    
    # Default: Erlauben
    return (True, "Standard - erlaubt")

def log_db_protection(message, level="INFO"):
    """
    Loggt Protection-Events
    """
    log_file = "/tmp/db_protection.log"
    timestamp = datetime.now().isoformat()
    
    with open(log_file, "a") as f:
        f.write(f"[{timestamp}] [{level}] {message}\n")

def main():
    """
    Hauptfunktion - wird von Hook-System aufgerufen
    """
    # Lese Input vom Hook-System
    if len(sys.argv) < 2:
        print("Usage: db_protection.py <operation_json>")
        sys.exit(1)
    
    try:
        operation = json.loads(sys.argv[1])
        
        # Extrahiere Operation-Details
        op_type = operation.get('type', 'UNKNOWN')
        table = operation.get('table', '')
        data = operation.get('data', {})
        context = operation.get('context', {})
        
        # Prüfe Operation
        allowed, reason = check_db_operation(op_type, table, data, context)
        
        # Logge Entscheidung
        log_db_protection(f"{op_type} on {table}: {'ALLOWED' if allowed else 'BLOCKED'} - {reason}")
        
        # Gebe Entscheidung zurück
        result = {
            'allowed': allowed,
            'reason': reason,
            'timestamp': datetime.now().isoformat()
        }
        
        print(json.dumps(result))
        sys.exit(0 if allowed else 1)
        
    except Exception as e:
        log_db_protection(f"ERROR: {str(e)}", "ERROR")
        # Im Fehlerfall erlauben wir die Operation
        print(json.dumps({'allowed': True, 'reason': f'Error in protection: {str(e)}'}))
        sys.exit(0)

if __name__ == "__main__":
    main()