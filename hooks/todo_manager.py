#!/usr/bin/env python3
"""
Neues zuverlässiges Todo-Management System
Ersetzt das problematische offizielle Hook-System
"""

import json
import os
import sys
import subprocess
import time
from datetime import datetime
from pathlib import Path
from project_filter import get_active_project, add_project_filter, get_project_info

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
    """Hole nächstes Todo mit ALLEN Feldern (V3.0 Feature)"""
    # V3.0: Erweiterte Datenladung - ALLE 30+ Felder laden
    fields = [
        'id', 'title', 'description', 'status', 'bearbeiten', 'mode', 'plan_approved',
        'version', 'priority', 'scope', 'working_directory', 'development_area',
        'agent_count', 'subagent_instructions', 'execution_mode', 'playwright_check', 'mcp_servers',
        'assigned_to', 'due_date', 'completed_date', 'is_recurring', 'recurring_type',
        'claude_notes', 'claude_prompt', 'bemerkungen', 'continuation_notes',
        'plan_html', 'report_url', 'related_files', 'dependencies', 'parent_todo_id',
        'created_at', 'updated_at', 'save_agent_outputs', 'prompt_output'
    ]
    
    # Original query ohne Projekt-Filter
    base_query = f"SELECT {', '.join(fields)} FROM {CONFIG['database']['table_prefix']}project_todos WHERE status='offen' AND bearbeiten=1 ORDER BY priority DESC, id ASC LIMIT 1"
    
    # Projekt-Filter hinzufügen falls aktives Projekt existiert
    query = add_project_filter(base_query)
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    if code == 0 and output:
        lines = output.strip().split('\n')
        # Prüfe ob wir Header + mindestens eine Datenzeile haben
        if len(lines) > 1 and lines[0].startswith('id'):
            parts = lines[1].split('\t')
            if len(parts) >= 3:
                # V3.0: Erweiterte Datenladung - Alle Felder zurückgeben
                todo_data = {
                    'id': parts[0],
                    'title': parts[1],
                    'description': parts[2] if len(parts) > 2 else '',
                    'status': parts[3] if len(parts) > 3 else 'offen',
                    'bearbeiten': parts[4] if len(parts) > 4 else '1',
                    'mode': parts[5] if len(parts) > 5 else 'execute',
                    'plan_approved': parts[6] if len(parts) > 6 else '0',
                    # Erweiterte Felder (V3.0)
                    'version': parts[7] if len(parts) > 7 else '1.00',
                    'priority': parts[8] if len(parts) > 8 else 'mittel',
                    'scope': parts[9] if len(parts) > 9 else 'todo-plugin',
                    'working_directory': parts[10] if len(parts) > 10 else '/home/rodemkay/www/react/plugin-todo/',
                    'development_area': parts[11] if len(parts) > 11 else 'fullstack',
                    'agent_count': parts[12] if len(parts) > 12 else '0',
                    'subagent_instructions': parts[13] if len(parts) > 13 else '',
                    'execution_mode': parts[14] if len(parts) > 14 else 'default',
                    'playwright_check': parts[15] if len(parts) > 15 else '0',
                    'mcp_servers': parts[16] if len(parts) > 16 else '',
                    'assigned_to': parts[17] if len(parts) > 17 else 'claude',
                    'due_date': parts[18] if len(parts) > 18 else None,
                    'completed_date': parts[19] if len(parts) > 19 else None,
                    'is_recurring': parts[20] if len(parts) > 20 else '0',
                    'recurring_type': parts[21] if len(parts) > 21 else None,
                    'claude_notes': parts[22] if len(parts) > 22 else '',
                    'claude_prompt': parts[23] if len(parts) > 23 else '',
                    'bemerkungen': parts[24] if len(parts) > 24 else '',
                    'continuation_notes': parts[25] if len(parts) > 25 else '',
                    'plan_html': parts[26] if len(parts) > 26 else '',
                    'report_url': parts[27] if len(parts) > 27 else '',
                    'related_files': parts[28] if len(parts) > 28 else '',
                    'dependencies': parts[29] if len(parts) > 29 else '',
                    'parent_todo_id': parts[30] if len(parts) > 30 else None,
                    'created_at': parts[31] if len(parts) > 31 else '',
                    'updated_at': parts[32] if len(parts) > 32 else '',
                    'save_agent_outputs': parts[33] if len(parts) > 33 else '0',
                    'prompt_output': parts[34] if len(parts) > 34 else ''
                }
                
                # Log erweiterte Datenladung
                log("INFO", f"V3.0: Loaded {len(todo_data)} fields for next todo")
                
                return todo_data
    return None

def get_todo_by_id(todo_id):
    """Hole spezifisches Todo mit ALLEN Feldern (V3.0 Feature)"""
    # V3.0: Erweiterte Datenladung - ALLE 30+ Felder laden
    fields = [
        'id', 'title', 'description', 'status', 'bearbeiten', 'mode', 'plan_approved',
        'version', 'priority', 'scope', 'working_directory', 'development_area',
        'agent_count', 'subagent_instructions', 'execution_mode', 'playwright_check', 'mcp_servers',
        'assigned_to', 'due_date', 'completed_date', 'is_recurring', 'recurring_type',
        'claude_notes', 'claude_prompt', 'bemerkungen', 'continuation_notes',
        'plan_html', 'report_url', 'related_files', 'dependencies', 'parent_todo_id',
        'created_at', 'updated_at', 'save_agent_outputs', 'prompt_output'
    ]
    
    query = f"SELECT {', '.join(fields)} FROM {CONFIG['database']['table_prefix']}project_todos WHERE id={todo_id}"
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    # Debug-Output
    if code != 0:
        print(f"Debug: SSH command failed with code {code}")
        return None
        
    if not output:
        print("Debug: No output from query")
        return None
        
    if output.startswith('Success:'):
        print(f"Debug: Got success message instead of data: {output[:50]}")
        return None
    
    # Verarbeite alle Zeilen als einen Block wegen möglicher Newlines in description
    all_text = output.strip()
    
    # Finde die Position des Headers und überspringe ihn
    if '\n' in all_text:
        header_end = all_text.index('\n')
        data_text = all_text[header_end+1:]
        
        # Versuche Tab-getrennte Felder zu parsen
        # Bei multiline descriptions müssen wir vorsichtig sein
        parts = data_text.split('\t')
        
        if len(parts) >= 5:  # Mindestens die wichtigsten Felder
            # Bereinige description von Newlines
            desc = parts[2] if len(parts) > 2 else ''
            desc = desc.replace('\\n', ' ').replace('\n', ' ').replace('\\r', ' ')
            
            # V3.0: Erweiterte Datenladung - Alle Felder zurückgeben
            todo_data = {
                'id': parts[0],
                'title': parts[1],
                'description': desc,
                'status': parts[3] if len(parts) > 3 else '',
                'bearbeiten': parts[4] if len(parts) > 4 else '',
                'mode': parts[5] if len(parts) > 5 else 'execute',
                'plan_approved': parts[6] if len(parts) > 6 else '0',
                # Erweiterte Felder (V3.0)
                'version': parts[7] if len(parts) > 7 else '1.00',
                'priority': parts[8] if len(parts) > 8 else 'mittel',
                'scope': parts[9] if len(parts) > 9 else 'todo-plugin',
                'working_directory': parts[10] if len(parts) > 10 else '/home/rodemkay/www/react/plugin-todo/',
                'development_area': parts[11] if len(parts) > 11 else 'fullstack',
                'agent_count': parts[12] if len(parts) > 12 else '0',
                'subagent_instructions': parts[13] if len(parts) > 13 else '',
                'execution_mode': parts[14] if len(parts) > 14 else 'default',
                'playwright_check': parts[15] if len(parts) > 15 else '0',
                'mcp_servers': parts[16] if len(parts) > 16 else '',
                'assigned_to': parts[17] if len(parts) > 17 else 'claude',
                'due_date': parts[18] if len(parts) > 18 else None,
                'completed_date': parts[19] if len(parts) > 19 else None,
                'is_recurring': parts[20] if len(parts) > 20 else '0',
                'recurring_type': parts[21] if len(parts) > 21 else None,
                'claude_notes': parts[22] if len(parts) > 22 else '',
                'claude_prompt': parts[23] if len(parts) > 23 else '',
                'bemerkungen': parts[24] if len(parts) > 24 else '',
                'continuation_notes': parts[25] if len(parts) > 25 else '',
                'plan_html': parts[26] if len(parts) > 26 else '',
                'report_url': parts[27] if len(parts) > 27 else '',
                'related_files': parts[28] if len(parts) > 28 else '',
                'dependencies': parts[29] if len(parts) > 29 else '',
                'parent_todo_id': parts[30] if len(parts) > 30 else None,
                'created_at': parts[31] if len(parts) > 31 else '',
                'updated_at': parts[32] if len(parts) > 32 else '',
                'save_agent_outputs': parts[33] if len(parts) > 33 else '0',
                'prompt_output': parts[34] if len(parts) > 34 else ''
            }
            
            # Log erweiterte Datenladung
            log("INFO", f"V3.0: Loaded {len(todo_data)} fields for todo #{todo_id}")
            
            return todo_data
    
    print(f"Debug: Could not parse output. First 100 chars: {output[:100]}")
    return None

def set_todo_status(todo_id, status):
    """Setze Todo-Status mit Zeitstempel"""
    # Base query
    updates = [f"status='{status}'", "updated_at=NOW()"]
    
    # Add execution_started_at for in_progress
    if status == 'in_progress':
        updates.append("execution_started_at=IFNULL(execution_started_at, NOW())")
    
    # Add completed_at for completed
    if status == 'completed':
        updates.append("completed_at=NOW()")
    
    query = f"UPDATE {CONFIG['database']['table_prefix']}project_todos SET {', '.join(updates)} WHERE id={todo_id}"
    
    cmd = f'wp db query "{query}"'
    output, code = ssh_command(cmd)
    
    if code == 0:
        log("INFO", f"Todo #{todo_id} status set to {status}")
        return True
    return False

def complete_todo(todo_id, html_output="", text_output="", summary=""):
    """Schließe Todo ab mit Outputs"""
    # Release Claude lock when completing
    lock_file = Path("/tmp/claude_processing.lock")
    if lock_file.exists():
        lock_file.unlink()
    
    # Escape for MySQL - use backslash for quotes
    html_output = html_output.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    text_output = text_output.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    summary = summary.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
    
    # Use single quotes for the query to avoid shell escaping issues
    query = f"""UPDATE {CONFIG['database']['table_prefix']}project_todos 
SET status='completed',
    completed_at=NOW(),
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
    # Import planning mode handler
    try:
        import sys
        sys.path.append('/home/rodemkay/www/react/plugin-todo/hooks')
        from planning_mode import get_mode_instruction, generate_plan_html
    except ImportError:
        get_mode_instruction = None
        generate_plan_html = None
    
    # Import session switcher für automatisches Projekt-Session-Management
    # DEAKTIVIERT - funktioniert nicht richtig
    auto_switch_for_todo = None
    # try:
    #     from session_switcher import auto_switch_for_todo
    # except ImportError:
    #     auto_switch_for_todo = None
    #     log("WARNING", "Session switcher not available")
    
    # Prüfe ob bereits ein Todo aktiv ist
    if Path(CONFIG["paths"]["current_todo"]).exists():
        with open(CONFIG["paths"]["current_todo"]) as f:
            active_id = f.read().strip()
        log("WARNING", f"Todo #{active_id} is still active. Cannot load new todo.")
        print(f"● Previous query still processing. Please try again.")
        # WICHTIG: Status NICHT ändern wenn bereits ein Todo aktiv ist!
        # Das neue Todo bleibt auf 'offen' und wird beim nächsten Mal geladen
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
            # AUTOMATISCHES SESSION-SWITCHING basierend auf TODO-Projekt
            # DEAKTIVIERT - funktioniert nicht richtig
            # if auto_switch_for_todo:
            #     try:
            #         auto_switch_for_todo(todo)
            #     except Exception as e:
            #         log("ERROR", f"Session switching failed: {e}")
            #         print(f"⚠️ Session-Switching fehlgeschlagen: {e}")
            
            print(f"\n📋 Loading Todo #{todo['id']}: {todo['title']}")
            print(f"Description: {todo['description'][:200]}...")
            print(f"Current Status: {todo['status']}")
            
            # IMMER Agent-Output-Ordner erstellen für Uploads und Dokumentation (VOR prompt_output check!)
            agent_output_dir = Path(f"/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs/todo-{todo.get('id')}")
            
            # AUTOMATISCH ORDNER ERSTELLEN (für JEDES TODO!)
            try:
                agent_output_dir.mkdir(parents=True, exist_ok=True)
                print(f"\n✅ Agent-Output-Ordner automatisch erstellt/verifiziert:")
                print(f"   📁 {agent_output_dir}/")
                print(f"   ℹ️ Dieser Ordner wird für Uploads, Dokumentation und Zusammenfassungen verwendet")
            except Exception as e:
                print(f"⚠️ Konnte Agent-Output-Ordner nicht erstellen: {e}")
                log("WARNING", f"Failed to create agent-output directory: {e}")
            
            # NEUE PROMPT_OUTPUT LOGIK - Primary Output anzeigen falls vorhanden
            if todo.get('prompt_output') and len(todo.get('prompt_output', '')) > 10:
                print(f"\n📋 PROMPT OUTPUT (aus Datenbank):")
                print("=" * 50)
                print(todo.get('prompt_output'))
                print("=" * 50)
                print("✅ Prompt Output aus Datenbank geladen - keine weitere Verarbeitung!")
                
                # ID speichern mit Validierung (VOR Status-Änderung!)
                with open(CONFIG["paths"]["current_todo"], 'w') as f:
                    f.write(str(todo['id']))
                
                # Verifiziere gespeicherte ID
                with open(CONFIG["paths"]["current_todo"]) as f:
                    saved_id = f.read().strip()
                if saved_id != str(todo['id']):
                    log("ERROR", f"ID mismatch: saved {saved_id} vs expected {todo['id']}")
                    Path(CONFIG["paths"]["current_todo"]).unlink()
                    return None
                
                # Specific mode marker setzen
                Path(CONFIG["paths"]["specific_mode"]).touch()
                
                # Kein Status-Update nötig, da prompt_output bereits existiert
                log("INFO", f"Loaded specific todo #{todo['id']} with existing prompt_output")
                return todo
            
            # FALLBACK: Alte Logik wenn kein prompt_output vorhanden
            print(f"\n📝 Keine prompt_output gefunden, verwende Standard-Logik...")
            
            # V3.0: Erweiterte Feldanzeige
            print(f"\n🎯 V3.0 Extended Fields:")
            print(f"  Priority: {todo.get('priority', 'mittel')}")
            print(f"  Scope: {todo.get('scope', 'todo-plugin')}")
            print(f"  Development Area: {todo.get('development_area', 'fullstack')}")
            print(f"  Working Directory: {todo.get('working_directory', '/home/rodemkay/www/react/plugin-todo/')}")
            if todo.get('due_date'):
                print(f"  Due Date: {todo.get('due_date')}")
            if todo.get('claude_notes'):
                print(f"  Claude Notes: {todo.get('claude_notes')[:100]}...")
            if todo.get('mcp_servers'):
                print(f"  MCP Servers: {todo.get('mcp_servers')}")
            
            # V3.0: Automatische Ordner-Erstellung für TODO Media Management
            try:
                # Erstelle vollständige Ordnerstruktur über WordPress Plugin
                php_code = f"""php -r "
require_once('/var/www/forexsignale/staging/wp-config.php');
require_once('/var/www/forexsignale/staging/wp-content/plugins/todo/includes/class-media-manager.php');
\\$media_manager = new Todo_Media_Manager();
\\$success = \\$media_manager->create_todo_folders({todo.get('id')});
if (\\$success) {{
    echo 'SUCCESS: TODO media folders created for #{todo.get('id')}';
}} else {{
    echo 'ERROR: Failed to create media folders for #{todo.get('id')}';
}}
\""""
                
                media_creation_result, code = ssh_command(php_code)
                
                if code == 0 and 'SUCCESS' in media_creation_result:
                    print(f"\n📁 TODO MEDIA FOLDER CREATED:")
                    print(f"✅ Ordner: /wp-content/uploads/agent-outputs/todo-{todo.get('id')}/")
                    print(f"🔒 Security: .htaccess protection aktiviert")
                    print(f"📝 README.txt mit TODO-Informationen erstellt")
                else:
                    print(f"⚠️ Media folder creation result: {media_creation_result}")
                    log("WARNING", f"Media folder creation issue for todo #{todo.get('id')}: {media_creation_result}")
                    
            except Exception as e:
                print(f"❌ FEHLER: Konnte Media-Ordner nicht erstellen: {e}")
                log("ERROR", f"Failed to create media folders for todo #{todo.get('id')}: {e}")
            
            # HINWEIS: Agent-Output-Ordner bereits oben erstellt - keine Duplikation nötig!
            
            # Agent Output Management System V3.0 - SIMPLIFIED SINGLE FOLDER
            if todo.get('save_agent_outputs') == '1':
                print(f"\n🗄️ AGENT OUTPUT MANAGEMENT AKTIVIERT:")
                print(f"📁 Ordner: {agent_output_dir}/")
                print(f"ℹ️ WICHTIGE ANWEISUNGEN FÜR SUBAGENTS:")
                print(f"   1. Verwende Write Tool mit klaren Dateinamen:")
                print(f"      - Write('{agent_output_dir}/AGENTNAME_YYYYMMDD_HHMMSS.md', content)")
                print(f"      - Write('{agent_output_dir}/requirements.md', specs)")
                print(f"      - Write('{agent_output_dir}/analysis_results.json', data)")
                print(f"   2. Verwende NIEMALS TodoWrite in Subagents!")
                print(f"   3. Maximale Dateigröße: 10MB pro Datei")
                print(f"   ⚠️ Alle Dateien werden im gleichen Ordner gespeichert für einfache Sortierung!")
            
            # Zeige Mode-spezifische Anweisungen
            if get_mode_instruction:
                print(get_mode_instruction(todo))
            
            # Generiere und zeige Orchestrator-Prompt
            final_prompt = generate_orchestrator_prompt(todo)
            if final_prompt.strip():
                print(f"\n📝 ORCHESTRATOR PROMPT:")
                print("=" * 50)
                print(final_prompt)
                print("=" * 50)
            
            # ID speichern mit Validierung (VOR Status-Änderung!)
            with open(CONFIG["paths"]["current_todo"], 'w') as f:
                f.write(str(todo['id']))
            
            # Verifiziere gespeicherte ID
            with open(CONFIG["paths"]["current_todo"]) as f:
                saved_id = f.read().strip()
            if saved_id != str(todo['id']):
                log("ERROR", f"ID mismatch: saved {saved_id} vs expected {todo['id']}")
                # Bei Fehler: Abbrechen ohne Status zu ändern
                Path(CONFIG["paths"]["current_todo"]).unlink()
                return None
            
            # Prüfe ob Claude verfügbar ist BEVOR Status geändert wird
            lock_file = Path("/tmp/claude_processing.lock")
            if lock_file.exists():
                # Check if lock is stale (older than 5 minutes)
                lock_age = time.time() - lock_file.stat().st_mtime
                if lock_age > 300:
                    print("Stale lock detected, removing...")
                    lock_file.unlink()
                else:
                    print("● Previous query still processing. Todo remains in 'offen' status.")
                    return None
            
            # Claude ist verfügbar, jetzt Lock erstellen und Status ändern
            lock_file.touch()
            set_todo_status(todo['id'], 'in_progress')
            print("✅ Todo successfully loaded and status changed to: in_progress")
            
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
            # AUTOMATISCHES SESSION-SWITCHING basierend auf TODO-Projekt
            # DEAKTIVIERT - funktioniert nicht richtig
            # if auto_switch_for_todo:
            #     try:
            #         auto_switch_for_todo(todo)
            #     except Exception as e:
            #         log("ERROR", f"Session switching failed: {e}")
            #         print(f"⚠️ Session-Switching fehlgeschlagen: {e}")
            
            print(f"\n📋 Loading Todo #{todo['id']}: {todo['title']}")
            print(f"Description: {todo['description'][:200]}...")
            print(f"Current Status: {todo.get('status', 'offen')}")
            
            # NEUE PROMPT_OUTPUT LOGIK - Primary Output anzeigen falls vorhanden
            if todo.get('prompt_output') and len(todo.get('prompt_output', '')) > 10:
                print(f"\n📋 PROMPT OUTPUT (aus Datenbank):")
                print("=" * 50)
                print(todo.get('prompt_output'))
                print("=" * 50)
                print("✅ Prompt Output aus Datenbank geladen - keine weitere Verarbeitung!")
                
                # ID speichern (VOR Status-Änderung!)
                with open(CONFIG["paths"]["current_todo"], 'w') as f:
                    f.write(str(todo['id']))
                
                # Verifiziere gespeicherte ID
                with open(CONFIG["paths"]["current_todo"]) as f:
                    saved_id = f.read().strip()
                if saved_id != str(todo['id']):
                    log("ERROR", f"ID mismatch: saved {saved_id} vs expected {todo['id']}")
                    Path(CONFIG["paths"]["current_todo"]).unlink()
                    return None
                
                # Specific mode marker löschen (da nächstes Todo)
                if Path(CONFIG["paths"]["specific_mode"]).exists():
                    Path(CONFIG["paths"]["specific_mode"]).unlink()
                
                # Kein Status-Update nötig, da prompt_output bereits existiert
                log("INFO", f"Loaded next todo #{todo['id']} with existing prompt_output")
                return todo
            
            # FALLBACK: Alte Logik wenn kein prompt_output vorhanden
            print(f"\n📝 Keine prompt_output gefunden, verwende Standard-Logik...")
            
            # V3.0: Erweiterte Feldanzeige
            print(f"\n🎯 V3.0 Extended Fields:")
            print(f"  Priority: {todo.get('priority', 'mittel')}")
            print(f"  Scope: {todo.get('scope', 'todo-plugin')}")
            print(f"  Development Area: {todo.get('development_area', 'fullstack')}")
            print(f"  Working Directory: {todo.get('working_directory', '/home/rodemkay/www/react/plugin-todo/')}")
            if todo.get('due_date'):
                print(f"  Due Date: {todo.get('due_date')}")
            if todo.get('claude_notes'):
                print(f"  Claude Notes: {todo.get('claude_notes')[:100]}...")
            if todo.get('mcp_servers'):
                print(f"  MCP Servers: {todo.get('mcp_servers')}")
            
            # V3.0: Automatische Ordner-Erstellung für TODO Media Management
            try:
                # Erstelle vollständige Ordnerstruktur über WordPress Plugin
                php_code = f"""php -r "
require_once('/var/www/forexsignale/staging/wp-config.php');
require_once('/var/www/forexsignale/staging/wp-content/plugins/todo/includes/class-media-manager.php');
\\$media_manager = new Todo_Media_Manager();
\\$success = \\$media_manager->create_todo_folders({todo.get('id')});
if (\\$success) {{
    echo 'SUCCESS: TODO media folders created for #{todo.get('id')}';
}} else {{
    echo 'ERROR: Failed to create media folders for #{todo.get('id')}';
}}
\""""
                
                media_creation_result, code = ssh_command(php_code)
                
                if code == 0 and 'SUCCESS' in media_creation_result:
                    print(f"\n📁 TODO MEDIA FOLDER CREATED:")
                    print(f"✅ Ordner: /wp-content/uploads/agent-outputs/todo-{todo.get('id')}/")
                    print(f"🔒 Security: .htaccess protection aktiviert")
                    print(f"📝 README.txt mit TODO-Informationen erstellt")
                else:
                    print(f"⚠️ Media folder creation result: {media_creation_result}")
                    log("WARNING", f"Media folder creation issue for todo #{todo.get('id')}: {media_creation_result}")
                    
            except Exception as e:
                print(f"❌ FEHLER: Konnte Media-Ordner nicht erstellen: {e}")
                log("ERROR", f"Failed to create media folders for todo #{todo.get('id')}: {e}")
            
            # HINWEIS: Agent-Output-Ordner bereits oben erstellt - keine Duplikation nötig!
            
            # Agent Output Management System V3.0 - SIMPLIFIED SINGLE FOLDER
            if todo.get('save_agent_outputs') == '1':
                print(f"\n🗄️ AGENT OUTPUT MANAGEMENT AKTIVIERT:")
                print(f"📁 Ordner: {agent_output_dir}/")
                print(f"ℹ️ WICHTIGE ANWEISUNGEN FÜR SUBAGENTS:")
                print(f"   1. Verwende Write Tool mit klaren Dateinamen:")
                print(f"      - Write('{agent_output_dir}/AGENTNAME_YYYYMMDD_HHMMSS.md', content)")
                print(f"      - Write('{agent_output_dir}/requirements.md', specs)")
                print(f"      - Write('{agent_output_dir}/analysis_results.json', data)")
                print(f"   2. Verwende NIEMALS TodoWrite in Subagents!")
                print(f"   3. Maximale Dateigröße: 10MB pro Datei")
                print(f"   ⚠️ Alle Dateien werden im gleichen Ordner gespeichert für einfache Sortierung!")
            
            # Zeige Mode-spezifische Anweisungen
            if get_mode_instruction:
                print(get_mode_instruction(todo))
            
            # Generiere und zeige Orchestrator-Prompt
            final_prompt = generate_orchestrator_prompt(todo)
            if final_prompt.strip():
                print(f"\n📝 ORCHESTRATOR PROMPT:")
                print("=" * 50)
                print(final_prompt)
                print("=" * 50)
            
            # ID speichern (VOR Status-Änderung!)
            with open(CONFIG["paths"]["current_todo"], 'w') as f:
                f.write(str(todo['id']))
            
            # V3.0: Setup Robust Completion System
            if CONFIG.get('behavior', {}).get('enable_robust_completion', True):
                try:
                    from emergency_handlers import setup_completion_timeout
                    timeout_seconds = CONFIG.get('behavior', {}).get('completion_timeout', 300)
                    setup_completion_timeout(todo['id'], timeout_seconds)
                    print(f"⏰ Completion timeout set: {timeout_seconds}s")
                except ImportError:
                    log("WARNING", "Emergency handlers not available")
                except Exception as e:
                    log("WARNING", f"Could not setup completion timeout: {e}")
            
            # Verifiziere gespeicherte ID
            with open(CONFIG["paths"]["current_todo"]) as f:
                saved_id = f.read().strip()
            if saved_id != str(todo['id']):
                log("ERROR", f"ID mismatch: saved {saved_id} vs expected {todo['id']}")
                # Bei Fehler: Abbrechen ohne Status zu ändern
                Path(CONFIG["paths"]["current_todo"]).unlink()
                return None
            
            # NUR wenn erfolgreich übernommen und status noch 'offen' ist, auf in_progress setzen
            if todo.get('status') == 'offen':
                # Prüfe ob Claude verfügbar ist BEVOR Status geändert wird
                lock_file = Path("/tmp/claude_processing.lock")
                if lock_file.exists():
                    # Check if lock is stale (older than 5 minutes)
                    lock_age = time.time() - lock_file.stat().st_mtime
                    if lock_age > 300:
                        print("Stale lock detected, removing...")
                        lock_file.unlink()
                    else:
                        print("● Previous query still processing. Todo remains in 'offen' status.")
                        return None
                
                # Claude ist verfügbar, jetzt Lock erstellen und Status ändern
                lock_file.touch()
                set_todo_status(todo['id'], 'in_progress')
                print("✅ Todo successfully loaded and status changed to: in_progress")
            else:
                print(f"Status remains: {todo.get('status')}")
            
            # Specific mode marker löschen
            if Path(CONFIG["paths"]["specific_mode"]).exists():
                Path(CONFIG["paths"]["specific_mode"]).unlink()
            
            log("INFO", f"Loaded next todo #{todo['id']}")
            return todo
        else:
            print("✅ Keine weiteren Todos mit status='offen' und bearbeiten=1")
            return None

def generate_orchestrator_prompt(todo):
    """Generiere Orchestrator-Prompt mit Subagent-Anweisungen"""
    prompt = todo.get('claude_prompt', '')
    agent_count = int(todo.get('agent_count', 0))
    subagent_instructions = todo.get('subagent_instructions', '')
    
    # Wenn agent_count > 0, füge Orchestrator-Anweisungen hinzu
    if agent_count > 0:
        orchestrator_section = f"\n\n🤖 ORCHESTRATOR MODUS AKTIVIERT:\n"
        orchestrator_section += f"Orchestriere BIS ZU {agent_count} Subagenten für diese Aufgabe.\n"
        
        if subagent_instructions.strip():
            orchestrator_section += f"Subagent-Anweisungen: {subagent_instructions}\n"
        
        orchestrator_section += "Wenn die Subagenten Dokumentationen erstellen, verwerte diese Informationen und beziehe sie ins Projekt mit ein.\n"
        
        # Füge Orchestrator-Anweisungen am Anfang hinzu
        prompt = orchestrator_section + "\n" + prompt
    
    return prompt

def handle_completion():
    """Handle TASK_COMPLETED - V3.0 Robust Completion System"""
    if not Path(CONFIG["paths"]["current_todo"]).exists():
        log("WARNING", "No current todo to complete")
        print("❌ No current todo to complete")
        return
    
    with open(CONFIG["paths"]["current_todo"]) as f:
        todo_id = f.read().strip()
    
    log("INFO", f"🚀 Starting robust completion for Todo #{todo_id}")
    print(f"🚀 Starting robust completion for Todo #{todo_id}")
    
    # V3.0: Verwende das neue Robust Completion System
    try:
        from robust_completion import robust_complete_todo
        success = robust_complete_todo(todo_id, CONFIG)
        
        if success:
            log("INFO", f"✅ Todo #{todo_id} successfully completed using robust system")
            print(f"✅ Todo #{todo_id} successfully completed!")
            print(f"📊 All outputs collected and saved to database")
            print(f"🧹 Session cleaned up and archived")
            
            # Remove completion marker
            if Path(CONFIG["paths"]["task_completed"]).exists():
                Path(CONFIG["paths"]["task_completed"]).unlink()
            
            return True
        else:
            log("ERROR", f"❌ Robust completion failed for Todo #{todo_id}")
            print(f"❌ Robust completion failed for Todo #{todo_id}")
            print(f"🔄 Please check logs and try manual completion")
            return False
            
    except ImportError as e:
        log("ERROR", f"Robust completion system not available: {e}")
        print(f"⚠️ Falling back to legacy completion system")
        # Fallback to legacy system
        return handle_completion_legacy(todo_id)
    except Exception as e:
        log("ERROR", f"Robust completion system failed: {e}")
        print(f"❌ Robust completion system failed: {e}")
        print(f"🔄 Falling back to legacy completion system")
        # Fallback to legacy system
        return handle_completion_legacy(todo_id)

def handle_completion_legacy(todo_id):
    """Legacy completion system as fallback"""
    log("INFO", f"Using legacy completion for Todo #{todo_id}")
    
    # Sammle Claude-Outputs mit dem alten Output-Collector
    try:
        from output_collector import collect_outputs_for_todo
        outputs = collect_outputs_for_todo(todo_id)
        html_output = outputs['html']
        text_output = outputs['text']
        summary = outputs['summary']
        log("INFO", f"Collected outputs for todo #{todo_id}: HTML={len(html_output)} chars, Text={len(text_output)} chars")
    except Exception as e:
        log("ERROR", f"Failed to collect outputs: {e}")
        html_output = ""
        text_output = ""
        summary = ""
    
    # Wenn keine Outputs gesammelt wurden, erstelle Basis-Zusammenfassung
    if not html_output and not text_output:
        # Hole Todo-Details für Zusammenfassung
        todo_details = get_todo_by_id(todo_id)
        if todo_details:
            title = todo_details.get('title', 'Unbekannte Aufgabe')
            desc = todo_details.get('description', '')[:200]
            
            html_output = f"""
<div class='task-completion'>
    <h2>✅ Todo #{todo_id}: {title}</h2>
    <p><strong>Status:</strong> Erfolgreich abgeschlossen</p>
    <p><strong>Zeitpunkt:</strong> {datetime.now().strftime('%d.%m.%Y %H:%M')}</p>
    <p><strong>Beschreibung:</strong> {desc}</p>
    <div class='completion-note'>
        <p>Die Aufgabe wurde erfolgreich bearbeitet und abgeschlossen.</p>
    </div>
</div>
"""
            text_output = f"Todo #{todo_id}: {title} - Erfolgreich abgeschlossen am {datetime.now().strftime('%d.%m.%Y %H:%M')}"
            summary = f"✅ {title} - Abgeschlossen"
        else:
            html_output = f"<h2>Todo #{todo_id} abgeschlossen</h2><p>Erfolgreich bearbeitet am {datetime.now().strftime('%d.%m.%Y %H:%M')}.</p>"
            text_output = f"Todo #{todo_id} erfolgreich bearbeitet."
            summary = f"✅ Todo #{todo_id} - Abgeschlossen"
    
    # Todo abschließen
    if complete_todo(todo_id, html_output, text_output, summary):
        print(f"✅ Todo #{todo_id} completed")
        
        # V3.0: Automatische Dokumentations-Generierung
        try:
            doc_script = "/home/rodemkay/www/react/plugin-todo/scripts/generate_task_documentation.sh"
            if Path(doc_script).exists():
                result = subprocess.run([doc_script, str(todo_id)], capture_output=True, text=True)
                if result.returncode == 0:
                    print(f"📄 Dokumentation generiert für Todo #{todo_id}")
                    log("INFO", f"Documentation generated for todo #{todo_id}")
                else:
                    log("WARNING", f"Documentation generation failed: {result.stderr}")
        except Exception as e:
            log("ERROR", f"Failed to generate documentation: {e}")
        
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
    
    # Zeige aktives Projekt falls vorhanden
    project_info = get_project_info()
    if project_info:
        print(f"📁 Aktives Projekt: {project_info['name']} (ID: {project_info['id']})")
        print(f"   Filter aktiv seit {project_info['age_hours']} Stunden")
        print("")
    
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