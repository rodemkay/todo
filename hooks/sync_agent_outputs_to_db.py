#!/usr/bin/env python3
"""
Synchronisiert Agent-Outputs aus Dateien in die Datenbank
Löst das Problem, dass Agent-Outputs nur als Dateien existieren
"""

import os
import subprocess
from pathlib import Path
import logging

logging.basicConfig(level=logging.INFO)

def sync_todo_outputs(todo_id):
    """Synchronisiert Agent-Outputs eines Todos in die Datenbank"""
    
    output_dir = Path(f"/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs/todo-{todo_id}")
    
    if not output_dir.exists():
        logging.warning(f"Kein Output-Verzeichnis für Todo #{todo_id}")
        return False
    
    # Sammle alle .md Dateien
    md_files = sorted(output_dir.glob("*.md"))
    
    if not md_files:
        logging.warning(f"Keine .md Dateien in {output_dir}")
        return False
    
    # Kombiniere alle Outputs
    full_output = f"# 📁 Agent-Outputs für Todo #{todo_id}\n\n"
    short_summary_parts = []
    
    for md_file in md_files:
        content = md_file.read_text(encoding='utf-8')
        filename = md_file.name
        
        # Füge Dateiname als Header hinzu
        full_output += f"## 📄 {filename}\n\n"
        full_output += content
        full_output += "\n\n---\n\n"
        
        # Extrahiere erste Zeilen für kurze Zusammenfassung
        lines = content.split('\n')
        for line in lines[:5]:
            if line.strip() and not line.startswith('#'):
                short_summary_parts.append(line.strip())
                break
    
    # Erstelle kurze Zusammenfassung
    short_summary = f"Todo #{todo_id}: {len(md_files)} Agent-Outputs erstellt. "
    short_summary += " | ".join(short_summary_parts[:3])
    if len(short_summary) > 500:
        short_summary = short_summary[:497] + "..."
    
    # Escape für SQL
    full_output_escaped = full_output.replace("'", "\\'").replace('"', '\\"')
    short_summary_escaped = short_summary.replace("'", "\\'").replace('"', '\\"')
    
    # Update Datenbank
    update_query = f"""
    UPDATE stage_project_todos 
    SET claude_html_output = '{full_output_escaped}',
        claude_notes = '{short_summary_escaped}'
    WHERE id = {todo_id}
    """
    
    # Führe Query aus
    cmd = f'ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && wp db query \\"{update_query}\\""'
    
    try:
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
        if result.returncode == 0:
            logging.info(f"✅ Todo #{todo_id} Outputs in DB synchronisiert")
            logging.info(f"   - {len(md_files)} Dateien kombiniert")
            logging.info(f"   - {len(full_output)} Zeichen gespeichert")
            return True
        else:
            logging.error(f"❌ Fehler beim Update: {result.stderr}")
            return False
    except Exception as e:
        logging.error(f"❌ Exception: {e}")
        return False

if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 1:
        todo_id = sys.argv[1]
        sync_todo_outputs(todo_id)
    else:
        # Synchronisiere alle Todos mit Output-Verzeichnissen
        output_base = Path("/home/rodemkay/www/react/mounts/hetzner/forexsignale/staging/wp-content/uploads/agent-outputs")
        for todo_dir in output_base.glob("todo-*"):
            if todo_dir.is_dir():
                todo_id = todo_dir.name.replace("todo-", "")
                logging.info(f"Synchronisiere Todo #{todo_id}...")
                sync_todo_outputs(todo_id)