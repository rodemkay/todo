#!/usr/bin/env python3
"""
Completion Monitor - Auto-Recovery & Health Checks
Überwacht und repariert hängende Completion-Prozesse
"""

import json
import time
import os
from datetime import datetime, timedelta
from pathlib import Path
import subprocess
import logging

# Logging Setup
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/home/rodemkay/www/react/plugin-todo/hooks/logs/monitor.log'),
        logging.StreamHandler()
    ]
)

class CompletionMonitor:
    def __init__(self, config_path='/home/rodemkay/www/react/plugin-todo/hooks/config.json'):
        with open(config_path) as f:
            self.config = json.load(f)
        
        self.max_session_duration = 3600  # 1 hour
        self.stale_timeout = 1800  # 30 minutes
        
    def run_health_check(self):
        """Führt umfassenden Health Check durch"""
        logging.info("🔍 Starting completion system health check...")
        
        issues = []
        
        # Check 1: Hängende Current Todo Files
        issues.extend(self._check_hanging_sessions())
        
        # Check 2: Verwaiste Session-Verzeichnisse
        issues.extend(self._check_orphaned_sessions())
        
        # Check 3: Stale TASK_COMPLETED Marker
        issues.extend(self._check_stale_completion_markers())
        
        # Check 4: Database Consistency
        issues.extend(self._check_database_consistency())
        
        # Check 5: Log File Sizes
        issues.extend(self._check_log_sizes())
        
        # Auto-Recovery wenn Issues gefunden
        if issues:
            logging.warning(f"⚠️ Found {len(issues)} issues, starting auto-recovery...")
            self._run_auto_recovery(issues)
        else:
            logging.info("✅ All completion system checks passed")
        
        return len(issues) == 0
    
    def _check_hanging_sessions(self):
        """Prüft auf hängende Sessions"""
        issues = []
        current_todo_path = Path(self.config["paths"]["current_todo"])
        
        if current_todo_path.exists():
            # Prüfe Alter der Datei
            file_age = time.time() - current_todo_path.stat().st_mtime
            
            if file_age > self.max_session_duration:
                todo_id = current_todo_path.read_text().strip()
                issues.append({
                    'type': 'hanging_session',
                    'todo_id': todo_id,
                    'age_minutes': int(file_age / 60),
                    'path': str(current_todo_path)
                })
                logging.warning(f"⚠️ Hanging session detected: Todo #{todo_id} running for {int(file_age / 60)} minutes")
        
        return issues
    
    def _check_orphaned_sessions(self):
        """Prüft auf verwaiste Session-Verzeichnisse"""
        issues = []
        sessions_path = Path("/tmp")
        
        for session_dir in sessions_path.glob("claude_session_*"):
            if session_dir.is_dir():
                # Prüfe Alter des Verzeichnisses
                dir_age = time.time() - session_dir.stat().st_mtime
                
                if dir_age > self.stale_timeout:
                    todo_id = session_dir.name.replace("claude_session_", "")
                    
                    # Prüfe ob Todo in DB als completed markiert ist
                    is_completed = self._check_todo_completed(todo_id)
                    
                    if is_completed:
                        issues.append({
                            'type': 'orphaned_session',
                            'todo_id': todo_id,
                            'age_minutes': int(dir_age / 60),
                            'path': str(session_dir)
                        })
                        logging.warning(f"⚠️ Orphaned session directory: {session_dir}")
        
        return issues
    
    def _check_stale_completion_markers(self):
        """Prüft auf alte TASK_COMPLETED Marker"""
        issues = []
        marker_path = Path(self.config["paths"]["task_completed"])
        
        if marker_path.exists():
            file_age = time.time() - marker_path.stat().st_mtime
            
            if file_age > 300:  # 5 minutes
                issues.append({
                    'type': 'stale_completion_marker',
                    'age_minutes': int(file_age / 60),
                    'path': str(marker_path)
                })
                logging.warning(f"⚠️ Stale TASK_COMPLETED marker: {int(file_age / 60)} minutes old")
        
        return issues
    
    def _check_database_consistency(self):
        """Prüft Database-Konsistenz"""
        issues = []
        
        try:
            # Prüfe auf Todos mit status='in_progress' aber ohne aktuelle Session
            query = f"SELECT id, title, updated_at FROM {self.config['database']['table_prefix']}project_todos WHERE status='in_progress' AND bearbeiten=1"
            cmd = f"wp db query '{query}'"
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0 and result.stdout:
                lines = result.stdout.strip().split('\n')
                current_todo_path = Path(self.config["paths"]["current_todo"])
                
                for line in lines[1:]:  # Skip header
                    if line.strip():
                        parts = line.split('\t')
                        if len(parts) >= 3:
                            todo_id = parts[0]
                            
                            # Prüfe ob aktuell aktiv
                            is_active = (current_todo_path.exists() and 
                                       current_todo_path.read_text().strip() == todo_id)
                            
                            if not is_active:
                                # Prüfe Alter der letzten Aktualisierung
                                try:
                                    updated_at = datetime.strptime(parts[2], '%Y-%m-%d %H:%M:%S')
                                    age = datetime.now() - updated_at
                                    
                                    if age > timedelta(minutes=30):
                                        issues.append({
                                            'type': 'stale_in_progress',
                                            'todo_id': todo_id,
                                            'title': parts[1],
                                            'age_minutes': int(age.total_seconds() / 60)
                                        })
                                        logging.warning(f"⚠️ Stale in_progress todo: #{todo_id}")
                                except ValueError:
                                    pass  # Ignore date parsing errors
        
        except Exception as e:
            logging.error(f"❌ Database consistency check failed: {e}")
            issues.append({
                'type': 'database_check_failed',
                'error': str(e)
            })
        
        return issues
    
    def _check_log_sizes(self):
        """Prüft Log-Datei-Größen"""
        issues = []
        logs_path = Path(self.config["paths"]["logs"])
        max_size_mb = self.config.get("logging", {}).get("max_size_mb", 10)
        
        if logs_path.exists():
            for log_file in logs_path.glob("*.log"):
                size_mb = log_file.stat().st_size / (1024 * 1024)
                
                if size_mb > max_size_mb:
                    issues.append({
                        'type': 'oversized_log',
                        'file': str(log_file),
                        'size_mb': round(size_mb, 2)
                    })
                    logging.warning(f"⚠️ Oversized log file: {log_file} ({round(size_mb, 2)} MB)")
        
        return issues
    
    def _run_auto_recovery(self, issues):
        """Führt automatische Reparaturen durch"""
        recovered = 0
        
        for issue in issues:
            try:
                if issue['type'] == 'hanging_session':
                    if self._recover_hanging_session(issue):
                        recovered += 1
                
                elif issue['type'] == 'orphaned_session':
                    if self._recover_orphaned_session(issue):
                        recovered += 1
                
                elif issue['type'] == 'stale_completion_marker':
                    if self._recover_stale_marker(issue):
                        recovered += 1
                
                elif issue['type'] == 'stale_in_progress':
                    if self._recover_stale_todo(issue):
                        recovered += 1
                
                elif issue['type'] == 'oversized_log':
                    if self._recover_oversized_log(issue):
                        recovered += 1
                        
            except Exception as e:
                logging.error(f"❌ Recovery failed for {issue['type']}: {e}")
        
        logging.info(f"✅ Auto-recovery completed: {recovered}/{len(issues)} issues resolved")
        return recovered
    
    def _recover_hanging_session(self, issue):
        """Repariert hängende Session"""
        try:
            # Force-complete das hängende Todo
            todo_id = issue['todo_id']
            
            # Erstelle Emergency-Completion
            from robust_completion import robust_complete_todo
            success = robust_complete_todo(todo_id, self.config)
            
            if success:
                logging.info(f"✅ Force-completed hanging Todo #{todo_id}")
                return True
            else:
                # Fallback: Reset status to 'offen'
                self._reset_todo_status(todo_id, 'offen')
                Path(issue['path']).unlink()
                logging.info(f"✅ Reset hanging Todo #{todo_id} to 'offen'")
                return True
                
        except Exception as e:
            logging.error(f"❌ Failed to recover hanging session {issue['todo_id']}: {e}")
            return False
    
    def _recover_orphaned_session(self, issue):
        """Repariert verwaiste Session-Verzeichnisse"""
        try:
            # Archiviere oder lösche verwaiste Sessions
            session_path = Path(issue['path'])
            archive_path = Path(self.config["paths"]["archive"]) / f"orphaned_{session_path.name}_{int(time.time())}"
            
            if session_path.exists():
                archive_path.parent.mkdir(parents=True, exist_ok=True)
                session_path.rename(archive_path)
                logging.info(f"✅ Archived orphaned session to {archive_path}")
                return True
                
        except Exception as e:
            logging.error(f"❌ Failed to recover orphaned session: {e}")
            return False
    
    def _recover_stale_marker(self, issue):
        """Entfernt alte Completion-Marker"""
        try:
            Path(issue['path']).unlink()
            logging.info(f"✅ Removed stale completion marker")
            return True
        except Exception as e:
            logging.error(f"❌ Failed to remove stale marker: {e}")
            return False
    
    def _recover_stale_todo(self, issue):
        """Repariert Todos mit stale in_progress Status"""
        try:
            # Reset status to 'offen'
            success = self._reset_todo_status(issue['todo_id'], 'offen')
            if success:
                logging.info(f"✅ Reset stale Todo #{issue['todo_id']} to 'offen'")
            return success
        except Exception as e:
            logging.error(f"❌ Failed to reset stale todo: {e}")
            return False
    
    def _recover_oversized_log(self, issue):
        """Rotiert übergroße Log-Dateien"""
        try:
            log_path = Path(issue['file'])
            backup_path = Path(f"{log_path}.backup.{int(time.time())}")
            
            # Backup und neue Log-Datei erstellen
            log_path.rename(backup_path)
            log_path.touch()
            
            logging.info(f"✅ Rotated oversized log: {log_path}")
            return True
        except Exception as e:
            logging.error(f"❌ Failed to rotate log: {e}")
            return False
    
    def _reset_todo_status(self, todo_id, status):
        """Setzt Todo-Status zurück"""
        try:
            query = f"UPDATE {self.config['database']['table_prefix']}project_todos SET status='{status}', updated_at=NOW() WHERE id={todo_id}"
            cmd = f"wp db query '{query}'"
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=30)
            
            return result.returncode == 0
        except Exception:
            return False
    
    def _check_todo_completed(self, todo_id):
        """Prüft ob Todo als completed markiert ist"""
        try:
            query = f"SELECT status FROM {self.config['database']['table_prefix']}project_todos WHERE id={todo_id}"
            cmd = f"wp db query '{query}'"
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=15)
            
            return "completed" in result.stdout
        except Exception:
            return False

def run_monitor():
    """Public API - Führt Monitor aus"""
    monitor = CompletionMonitor()
    return monitor.run_health_check()

if __name__ == "__main__":
    # CLI Interface
    import sys
    
    monitor = CompletionMonitor()
    
    if len(sys.argv) > 1 and sys.argv[1] == "--daemon":
        # Daemon-Modus: Läuft kontinuierlich
        logging.info("🔄 Starting completion monitor in daemon mode")
        
        while True:
            try:
                monitor.run_health_check()
                time.sleep(300)  # Check alle 5 Minuten
            except KeyboardInterrupt:
                logging.info("👋 Monitor daemon stopped")
                break
            except Exception as e:
                logging.error(f"❌ Monitor daemon error: {e}")
                time.sleep(60)  # Wait 1 minute on error
    else:
        # Single-Run Modus
        healthy = monitor.run_health_check()
        exit(0 if healthy else 1)