#!/usr/bin/env python3
"""
Automatisches Session-Switching basierend auf TODO-Projekt
Integriert mit todo_manager.py
"""

import subprocess
import json
from pathlib import Path
import os
import sys

# Projekt-zu-Session Mapping
PROJECT_SESSION_MAP = {
    'todo-plugin': 'plugin-todo',
    'Todo-Plugin': 'plugin-todo',
    'plugin-todo': 'plugin-todo',
    'ForexSignale': 'forexsignale',
    'forexsignale-magazine': 'forexsignale',
    'Article-Builder': 'article-builder',
    'article-builder': 'article-builder',
    'Trading-Bot': 'trading-bot',
    'trading-bot': 'trading-bot',
    'WordPress': 'wordpress-dev',
    'wp-development': 'wordpress-dev'
}

# Projekt-zu-Verzeichnis Mapping
PROJECT_DIR_MAP = {
    'plugin-todo': '/home/rodemkay/www/react/plugin-todo',
    'forexsignale': '/home/rodemkay/www/react/forexsignale-magazine',
    'article-builder': '/home/rodemkay/www/react/plugin-article',
    'trading-bot': '/home/rodemkay/www/react/trading-bot',
    'wordpress-dev': '/home/rodemkay/www/react/wordpress'
}

def get_current_tmux_session():
    """Hole aktuelle tmux Session"""
    try:
        result = subprocess.run(
            ['tmux', 'display-message', '-p', '#S'],
            capture_output=True, text=True, check=False
        )
        if result.returncode == 0:
            return result.stdout.strip()
    except:
        pass
    return None

def check_session_exists(session_name):
    """Prüfe ob tmux Session existiert"""
    try:
        result = subprocess.run(
            ['tmux', 'has-session', '-t', session_name],
            capture_output=True, check=False
        )
        return result.returncode == 0
    except:
        return False

def create_session_with_kitty(session_name, project_dir):
    """Erstelle neue Session mit Kitty"""
    print(f"🚀 Erstelle neue Session: {session_name} in {project_dir}")
    
    # Kitty Session-Config erstellen
    kitty_config_dir = Path.home() / '.config' / 'kitty' / 'sessions'
    kitty_config_dir.mkdir(parents=True, exist_ok=True)
    
    config_file = kitty_config_dir / f"{session_name}.conf"
    
    config_content = f"""# Session für {session_name}
cd {project_dir}

# Neues Tab mit tmux Session
launch --type=tab --tab-title="{session_name}" sh -c "tmux new-session -s {session_name} -c {project_dir}"

# Layout mit zwei Panes (90/10 Split)
layout splits
launch --location=hsplit --title="Claude" sh -c "cd {project_dir} && echo '1' | claude --resume --dangerously-skip-permissions"
launch --location=vsplit --title="Monitor" sh -c "cd {project_dir} && exec bash"
resize_window shorter 10
"""
    
    config_file.write_text(config_content)
    
    # Kitty mit Session starten
    subprocess.Popen(['kitty', '--session', str(config_file)])
    
    # Warte bis Session bereit ist
    import time
    max_wait = 10
    waited = 0
    while not check_session_exists(session_name) and waited < max_wait:
        time.sleep(1)
        waited += 1
    
    if check_session_exists(session_name):
        print(f"✅ Session {session_name} erfolgreich erstellt")
        return True
    else:
        print(f"❌ Session {session_name} konnte nicht erstellt werden")
        return False

def focus_kitty_window(session_name):
    """Versuche Kitty-Fenster mit Session zu fokussieren"""
    # Versuche mit wmctrl
    try:
        subprocess.run(['wmctrl', '-a', session_name], check=False, capture_output=True)
        return True
    except:
        pass
    
    # Versuche mit xdotool
    try:
        subprocess.run(['xdotool', 'search', '--name', session_name, 'windowactivate'], 
                      check=False, capture_output=True)
        return True
    except:
        pass
    
    return False

def switch_to_project_session(project_name, todo_id=None):
    """
    Wechsle zur Session basierend auf Projekt
    Returns: True wenn erfolgreich, False sonst
    """
    # Normalisiere Projekt-Namen
    session_name = PROJECT_SESSION_MAP.get(project_name)
    if not session_name:
        # Fallback: Verwende Projekt-Namen direkt
        session_name = project_name.lower().replace(' ', '-')
    
    project_dir = PROJECT_DIR_MAP.get(session_name, f"/home/rodemkay/www/react/{session_name}")
    
    current_session = get_current_tmux_session()
    
    print(f"\n🔄 SESSION-SWITCHING für TODO #{todo_id if todo_id else 'N/A'}")
    print(f"📁 Projekt: {project_name}")
    print(f"🎯 Ziel-Session: {session_name}")
    print(f"📍 Aktuelle Session: {current_session}")
    
    # Prüfe ob wir bereits in der richtigen Session sind
    if current_session == session_name:
        print(f"✅ Bereits in korrekter Session: {session_name}")
        return True
    
    # Prüfe ob Ziel-Session existiert
    if check_session_exists(session_name):
        print(f"🔄 Wechsle zu bestehender Session: {session_name}")
        
        # Fokussiere Kitty-Fenster
        if focus_kitty_window(session_name):
            print(f"✅ Fenster fokussiert: {session_name}")
        else:
            print(f"⚠️ Konnte Fenster nicht automatisch fokussieren")
            print(f"💡 Bitte manuell zu Kitty-Fenster '{session_name}' wechseln")
        
        return True
    else:
        print(f"⚠️ Session {session_name} existiert nicht")
        
        # Erstelle neue Session
        if create_session_with_kitty(session_name, project_dir):
            # Warte kurz und fokussiere
            import time
            time.sleep(2)
            focus_kitty_window(session_name)
            return True
        
        return False

def get_project_from_todo(todo_data):
    """
    Extrahiere Projekt aus TODO-Daten
    Priorität: scope > project_name > working_directory
    """
    # 1. Versuche scope
    if todo_data.get('scope'):
        return todo_data['scope']
    
    # 2. Versuche project_name
    if todo_data.get('project_name'):
        return todo_data['project_name']
    
    # 3. Versuche aus working_directory zu extrahieren
    if todo_data.get('working_directory'):
        work_dir = todo_data['working_directory']
        # Extrahiere Projekt-Namen aus Pfad
        if 'plugin-todo' in work_dir:
            return 'plugin-todo'
        elif 'forexsignale' in work_dir:
            return 'forexsignale'
        elif 'article' in work_dir:
            return 'article-builder'
        elif 'trading' in work_dir:
            return 'trading-bot'
    
    # 4. Fallback
    return 'plugin-todo'

def auto_switch_for_todo(todo_data):
    """
    Hauptfunktion: Automatisches Session-Switching für TODO
    """
    if not todo_data:
        return False
    
    project = get_project_from_todo(todo_data)
    todo_id = todo_data.get('id', 'unknown')
    
    return switch_to_project_session(project, todo_id)

# Test-Funktion
if __name__ == "__main__":
    if len(sys.argv) > 1:
        # Manueller Test mit Projekt-Namen
        project = sys.argv[1]
        switch_to_project_session(project)
    else:
        # Test mit Beispiel-TODO
        test_todo = {
            'id': '123',
            'scope': 'forexsignale',
            'working_directory': '/home/rodemkay/www/react/forexsignale-magazine'
        }
        auto_switch_for_todo(test_todo)