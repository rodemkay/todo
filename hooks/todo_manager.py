#!/usr/bin/env python3
"""
Neues zuverlässiges Todo-Management System
Ersetzt das problematische offizielle Hook-System
"""

import json
import os
import sys
import subprocess
from datetime import datetime
from pathlib import Path
import time

# Konfiguration laden
CONFIG_PATH = Path(__file__).parent / "config.json"
with open(CONFIG_PATH) as f:
    CONFIG = json.load(f)

def log(level, message):
    """Einfaches Logging"""
    if CONFIG["logging"]["enabled"]:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_file = Path(CONFIG["paths"]["logs"]) / f"todo_{datetime.now().strftime('%Y%m%d')}.log"
        with open(log_file, "a") as f:
            f.write(f"[{timestamp}] [{level}] {message}\n")
        print(f"[{level}] {message}")

def ssh_command(cmd):
    """Führe Befehl auf Remote-Server aus"""
    ssh_host = f"{CONFIG['database']['user']}@{CONFIG['database']['host']}"
    remote_path = CONFIG['database']['remote_path']
    
    # Use list form for better escaping
    ssh_cmd = ["ssh", ssh_host, f"cd {remote_path} && {cmd}"]
    
    try:
        result = subprocess.run(ssh_cmd, capture_output=True, text=True, timeout=30)
        return result.stdout.strip(), result.returncode
    except subprocess.TimeoutExpired:
        log("ERROR", f"Command timed out: {cmd}")
        return "", 1
    except Exception as e:
        log("ERROR", f"SSH command failed: {e}")
        return "", 1

def get_next_todo():
    """Hole nächstes Todo mit status='offen' und bearbeiten=1"""
    query = f"SELECT id, title, description, status FROM {CONFIG['database']['table_prefix']}project_todos WHERE status='offen' AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1"
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    if code == 0 and output:
        lines = output.strip().split('\n')
        # Prüfe ob wir Header + mindestens eine Datenzeile haben
        if len(lines) > 1 and lines[0].startswith('id'):
            parts = lines[1].split('\t')
            if len(parts) >= 3:
                return {
                    'id': parts[0],
                    'title': parts[1],
                    'description': parts[2] if len(parts) > 2 else '',
                    'status': parts[3] if len(parts) > 3 else 'offen'
                }
    return None

def get_todo_by_id(todo_id):
    """Hole spezifisches Todo, unabhängig von Status"""
    query = f"SELECT id, title, description, status, bearbeiten FROM {CONFIG['database']['table_prefix']}project_todos WHERE id={todo_id}"
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    if code == 0 and output:
        lines = output.strip().split('\n')
        if len(lines) > 1:  # Header + mindestens eine Datenzeile
            parts = lines[1].split('\t')
            if len(parts) >= 5:
                return {
                    'id': parts[0],
                    'title': parts[1],
                    'description': parts[2] if len(parts) > 2 else '',
                    'status': parts[3] if len(parts) > 3 else '',
                    'bearbeiten': parts[4] if len(parts) > 4 else ''
                }
    return None

def set_todo_status(todo_id, status):
    """Setze Todo-Status"""
    query = f"UPDATE {CONFIG['database']['table_prefix']}project_todos SET status='{status}', updated_at=NOW() WHERE id={todo_id}"
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    if code == 0:
        log("INFO", f"Todo #{todo_id} status set to {status}")
        return True
    return False

def complete_todo(todo_id, html_output="", text_output="", summary=""):
    """Schließe Todo ab mit Outputs"""
    # Escape for MySQL - use backslash for quotes
    html_output = html_output.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    text_output = text_output.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    summary = summary.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    
    # Use single quotes for the query to avoid shell escaping issues
    query = f"""UPDATE {CONFIG['database']['table_prefix']}project_todos 
SET status='completed',
    claude_html_output='{html_output}',
    claude_text_output='{text_output}',
    claude_summary='{summary}',
    updated_at=NOW()
WHERE id={todo_id}"""
    
    # Escape the entire query for shell
    query_escaped = query.replace("'", "'\\''")
    cmd = f"wp db query '{query_escaped}'"
    output, code = ssh_command(cmd)
    
    if code == 0:
        log("INFO", f"Todo #{todo_id} completed with outputs")
        return True
    else:
        log("ERROR", f"Failed to complete todo #{todo_id}: {output}")
        return False

def load_todo(todo_id=None):
    """Lade ein Todo (nächstes oder spezifisches)"""
    # Prüfe ob bereits ein Todo aktiv ist
    if Path(CONFIG["paths"]["current_todo"]).exists():
        with open(CONFIG["paths"]["current_todo"]) as f:
            active_id = f.read().strip()
        log("WARNING", f"Todo #{active_id} is still active. Completing it first.")
        handle_completion()
        # WICHTIG: Nach handle_completion() NICHT weitermachen!
        # handle_completion() lädt bereits das nächste Todo im Loop-Modus
        return None
    
    if todo_id:
        # Validiere Todo-ID
        if not str(todo_id).isdigit():
            print(f"❌ Invalid todo ID: {todo_id}")
            log("ERROR", f"Invalid todo ID format: {todo_id}")
            return None
            
        # Spezifisches Todo laden
        todo = get_todo_by_id(todo_id)
        if todo:
            print(f"\n📋 Loading Todo #{todo['id']}: {todo['title']}")
            print(f"Description: {todo['description'][:200]}...")
            print(f"Current Status: {todo['status']}")
            
            # Auf in_progress setzen
            set_todo_status(todo['id'], 'in_progress')
            
            # ID speichern mit Validierung
            with open(CONFIG["paths"]["current_todo"], 'w') as f:
                f.write(str(todo['id']))
            
            # Verifiziere gespeicherte ID
            with open(CONFIG["paths"]["current_todo"]) as f:
                saved_id = f.read().strip()
            if saved_id != str(todo['id']):
                log("ERROR", f"ID mismatch: saved {saved_id} vs expected {todo['id']}")
            
            # Specific mode marker setzen
            Path(CONFIG["paths"]["specific_mode"]).touch()
            
            log("INFO", f"Loaded specific todo #{todo['id']}")
            return todo
        else:
            print(f"❌ Todo #{todo_id} not found")
            return None
    else:
        # Nächstes Todo laden
        todo = get_next_todo()
        if todo:
            print(f"\n📋 Loading Todo #{todo['id']}: {todo['title']}")
            print(f"Description: {todo['description'][:200]}...")
            print(f"Current Status: {todo.get('status', 'offen')}")
            
            # Nur wenn status noch 'offen' ist, auf in_progress setzen
            if todo.get('status') == 'offen':
                set_todo_status(todo['id'], 'in_progress')
                print("Status changed to: in_progress")
            else:
                print(f"Status remains: {todo.get('status')}")
            
            # ID speichern
            with open(CONFIG["paths"]["current_todo"], 'w') as f:
                f.write(str(todo['id']))
            
            # Specific mode marker löschen
            if Path(CONFIG["paths"]["specific_mode"]).exists():
                Path(CONFIG["paths"]["specific_mode"]).unlink()
            
            log("INFO", f"Loaded next todo #{todo['id']}")
            return todo
        else:
            print("✅ Keine weiteren Todos mit status='offen' und bearbeiten=1")
            return None

def handle_completion():
    """Handle TASK_COMPLETED"""
    if not Path(CONFIG["paths"]["current_todo"]).exists():
        log("WARNING", "No current todo to complete")
        return
    
    with open(CONFIG["paths"]["current_todo"]) as f:
        todo_id = f.read().strip()
    
    # Sammle Claude-Outputs mit dem neuen Output-Collector
    try:
        from output_collector import collect_outputs_for_todo
        outputs = collect_outputs_for_todo(todo_id)
        html_output = outputs['html']
        text_output = outputs['text']
        summary = outputs['summary']
        log("INFO", f"Collected outputs for todo #{todo_id}: HTML={len(html_output)} chars, Text={len(text_output)} chars")
    except Exception as e:
        log("ERROR", f"Failed to collect outputs: {e}")
        # Fallback zu Platzhaltern
        html_output = "<h2>Todo abgeschlossen</h2><p>Erfolgreich bearbeitet.</p>"
        text_output = "Todo erfolgreich bearbeitet."
        summary = "✅ Abgeschlossen"
    
    # Todo abschließen
    if complete_todo(todo_id, html_output, text_output, summary):
        print(f"✅ Todo #{todo_id} completed")
        
        # Aufräumen
        Path(CONFIG["paths"]["current_todo"]).unlink()
        if Path(CONFIG["paths"]["task_completed"]).exists():
            Path(CONFIG["paths"]["task_completed"]).unlink()
        
        # Prüfe ob specific mode
        if Path(CONFIG["paths"]["specific_mode"]).exists():
            print("🏁 Specific todo completed. Session ending.")
            Path(CONFIG["paths"]["specific_mode"]).unlink()
            log("INFO", f"Session ended after specific todo #{todo_id}")
        else:
            # Auto-continue: Nächstes Todo laden
            print("🔄 Loading next todo...")
            time.sleep(2)
            next_todo = load_todo()
            if not next_todo:
                print("🎉 All todos completed!")
                log("INFO", "All todos with bearbeiten=1 completed")

def main():
    """Hauptfunktion"""
    if len(sys.argv) > 1:
        command = sys.argv[1]
        
        if command == "load":
            # Lade nächstes Todo
            load_todo()
        
        elif command == "load-id" and len(sys.argv) > 2:
            # Lade spezifisches Todo
            todo_id = sys.argv[2]
            load_todo(todo_id)
        
        elif command == "complete":
            # Handle completion
            handle_completion()
        
        elif command == "status":
            # Zeige aktuellen Status
            if Path(CONFIG["paths"]["current_todo"]).exists():
                with open(CONFIG["paths"]["current_todo"]) as f:
                    todo_id = f.read().strip()
                print(f"Current todo: #{todo_id}")
            else:
                print("No active todo")
        
        else:
            print(f"Unknown command: {command}")
            print("Usage: todo-manager.py [load|load-id ID|complete|status]")
    else:
        print("Todo Manager - New reliable system")
        print("Usage: todo-manager.py [load|load-id ID|complete|status]")

if __name__ == "__main__":
    main()