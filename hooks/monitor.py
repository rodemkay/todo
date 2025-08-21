#!/usr/bin/env python3
"""
Hook System Monitor
Überwacht den Zustand des Hook-Systems und meldet Probleme
"""

import json
import os
import subprocess
from datetime import datetime, timedelta
from pathlib import Path
import sys

# Konfiguration laden
CONFIG_PATH = Path(__file__).parent / "config.json"
with open(CONFIG_PATH) as f:
    CONFIG = json.load(f)

class HookSystemMonitor:
    def __init__(self):
        self.status = {
            'healthy': True,
            'checks': [],
            'warnings': [],
            'errors': []
        }
        
    def check_todo_consistency(self):
        """Prüft ob Todo-IDs konsistent sind"""
        current_todo_file = Path(CONFIG["paths"]["current_todo"])
        
        if current_todo_file.exists():
            with open(current_todo_file) as f:
                todo_id = f.read().strip()
                
            # Prüfe ob Todo in DB existiert
            cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query 'SELECT id, status FROM {CONFIG['database']['table_prefix']}project_todos WHERE id={todo_id}'\""
            
            try:
                result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
                if result.returncode == 0 and todo_id in result.stdout:
                    self.status['checks'].append(f"✅ Todo #{todo_id} exists in database")
                else:
                    self.status['errors'].append(f"❌ Todo #{todo_id} not found in database!")
                    self.status['healthy'] = False
            except:
                self.status['warnings'].append(f"⚠️ Could not verify todo #{todo_id}")
        else:
            self.status['checks'].append("✅ No active todo")
            
    def check_stale_locks(self):
        """Prüft auf veraltete Lock-Dateien"""
        lock_files = [
            Path("/tmp/TASK_COMPLETED"),
            Path("/tmp/SPECIFIC_TODO_MODE")
        ]
        
        for lock_file in lock_files:
            if lock_file.exists():
                # Prüfe Alter der Datei
                age = datetime.now() - datetime.fromtimestamp(lock_file.stat().st_mtime)
                if age > timedelta(hours=1):
                    self.status['warnings'].append(f"⚠️ Stale lock file: {lock_file} ({age.total_seconds():.0f}s old)")
                    # Auto-cleanup bei sehr alten Dateien
                    if age > timedelta(hours=24):
                        lock_file.unlink()
                        self.status['checks'].append(f"🧹 Cleaned up old lock: {lock_file}")
                        
    def check_log_size(self):
        """Prüft Log-Datei-Größen"""
        log_dir = Path(CONFIG["paths"]["logs"])
        if log_dir.exists():
            for log_file in log_dir.glob("*.log"):
                size_mb = log_file.stat().st_size / (1024 * 1024)
                if size_mb > CONFIG["logging"]["max_size_mb"]:
                    self.status['warnings'].append(f"⚠️ Large log file: {log_file.name} ({size_mb:.1f}MB)")
                    # Rotate log
                    if CONFIG["logging"].get("rotate_daily", False):
                        backup_name = log_file.with_suffix(f".{datetime.now().strftime('%Y%m%d')}.log")
                        log_file.rename(backup_name)
                        self.status['checks'].append(f"📁 Rotated log to {backup_name.name}")
                        
    def check_db_connection(self):
        """Prüft Datenbankverbindung"""
        cmd = f"ssh {CONFIG['database']['user']}@{CONFIG['database']['host']} \"cd {CONFIG['database']['remote_path']} && wp db query 'SELECT 1'\""
        
        try:
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                self.status['checks'].append("✅ Database connection OK")
            else:
                self.status['errors'].append("❌ Database connection failed!")
                self.status['healthy'] = False
        except subprocess.TimeoutExpired:
            self.status['errors'].append("❌ Database connection timeout!")
            self.status['healthy'] = False
            
    def check_session_cleanup(self):
        """Prüft und bereinigt alte Session-Verzeichnisse"""
        sessions_path = Path(CONFIG["paths"].get("sessions", "/tmp/claude_sessions"))
        if sessions_path.exists():
            for session_dir in sessions_path.glob("claude_session_*"):
                if session_dir.is_dir():
                    age = datetime.now() - datetime.fromtimestamp(session_dir.stat().st_mtime)
                    if age > timedelta(days=7):
                        # Alte Session-Daten löschen
                        import shutil
                        shutil.rmtree(session_dir)
                        self.status['checks'].append(f"🧹 Cleaned old session: {session_dir.name}")
                        
    def run_health_check(self):
        """Führt alle Health-Checks aus"""
        print("🔍 Hook System Health Check")
        print("=" * 50)
        
        # Alle Checks ausführen
        self.check_db_connection()
        self.check_todo_consistency()
        self.check_stale_locks()
        self.check_log_size()
        self.check_session_cleanup()
        
        # Status ausgeben
        print("\n📊 Status Report:")
        
        if self.status['checks']:
            print("\n✅ Successful Checks:")
            for check in self.status['checks']:
                print(f"  {check}")
                
        if self.status['warnings']:
            print("\n⚠️ Warnings:")
            for warning in self.status['warnings']:
                print(f"  {warning}")
                
        if self.status['errors']:
            print("\n❌ Errors:")
            for error in self.status['errors']:
                print(f"  {error}")
                
        # Gesamtstatus
        print("\n" + "=" * 50)
        if self.status['healthy']:
            print("✅ System Status: HEALTHY")
        else:
            print("❌ System Status: UNHEALTHY - Manual intervention required")
            
        return self.status['healthy']
    
    def fix_common_issues(self):
        """Versucht häufige Probleme automatisch zu beheben"""
        print("\n🔧 Attempting auto-fixes...")
        
        # Stale locks entfernen
        for lock_file in ["/tmp/TASK_COMPLETED", "/tmp/SPECIFIC_TODO_MODE"]:
            if Path(lock_file).exists():
                Path(lock_file).unlink()
                print(f"  ✅ Removed stale lock: {lock_file}")
                
        # Inkonsistente Todo-ID zurücksetzen
        current_todo_file = Path(CONFIG["paths"]["current_todo"])
        if current_todo_file.exists():
            current_todo_file.unlink()
            print("  ✅ Reset current todo tracking")
            
        print("  🔄 Auto-fixes completed")

def main():
    monitor = HookSystemMonitor()
    
    if len(sys.argv) > 1:
        if sys.argv[1] == "fix":
            monitor.fix_common_issues()
        elif sys.argv[1] == "check":
            healthy = monitor.run_health_check()
            sys.exit(0 if healthy else 1)
    else:
        # Standard: Health Check
        healthy = monitor.run_health_check()
        
        if not healthy:
            print("\nRun 'python3 monitor.py fix' to attempt auto-fixes")
            
        sys.exit(0 if healthy else 1)

if __name__ == "__main__":
    main()