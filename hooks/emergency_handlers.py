#!/usr/bin/env python3
"""
Emergency Handlers - Timeout & Recovery Mechanisms
Behandelt kritische Situationen und Notfall-Recoveries
"""

import json
import time
import signal
import os
from datetime import datetime
from pathlib import Path
import subprocess
import logging
import threading

# Logging Setup
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/home/rodemkay/www/react/plugin-todo/hooks/logs/emergency.log'),
        logging.StreamHandler()
    ]
)

class EmergencyHandler:
    def __init__(self, config_path='/home/rodemkay/www/react/plugin-todo/hooks/config.json'):
        with open(config_path) as f:
            self.config = json.load(f)
        
        self.emergency_timeout = 1800  # 30 minutes
        self.completion_timeout = 300   # 5 minutes
        self.active_timers = {}
        
    def setup_completion_timeout(self, todo_id, timeout_seconds=None):
        """Setzt Completion-Timeout für Todo"""
        if timeout_seconds is None:
            timeout_seconds = self.completion_timeout
            
        # Starte Timer-Thread
        timer = threading.Timer(timeout_seconds, self._handle_completion_timeout, args=[todo_id])
        timer.daemon = True
        timer.start()
        
        self.active_timers[todo_id] = timer
        logging.info(f"⏰ Completion timeout set for Todo #{todo_id}: {timeout_seconds}s")
        
        return timer
    
    def cancel_completion_timeout(self, todo_id):
        """Cancelt Completion-Timeout"""
        if todo_id in self.active_timers:
            self.active_timers[todo_id].cancel()
            del self.active_timers[todo_id]
            logging.info(f"✅ Completion timeout cancelled for Todo #{todo_id}")
    
    def _handle_completion_timeout(self, todo_id):
        """Behandelt Completion-Timeout"""
        logging.warning(f"⚠️ Completion timeout reached for Todo #{todo_id}")
        
        try:
            # Prüfe ob Todo noch aktiv ist
            current_todo_path = Path(self.config["paths"]["current_todo"])
            if not current_todo_path.exists():
                logging.info(f"Todo #{todo_id} already completed normally")
                return
            
            active_id = current_todo_path.read_text().strip()
            if active_id != str(todo_id):
                logging.info(f"Different todo active ({active_id}), timeout not relevant")
                return
            
            # Emergency Completion
            logging.warning(f"🚨 Starting emergency completion for Todo #{todo_id}")
            self._emergency_complete_todo(todo_id)
            
        except Exception as e:
            logging.error(f"❌ Emergency completion failed: {e}")
            # Last resort: Reset todo status
            self._emergency_reset_todo(todo_id)
    
    def _emergency_complete_todo(self, todo_id):
        """Notfall-Completion eines Todos"""
        try:
            # Sammle was möglich ist
            emergency_html = self._generate_emergency_html(todo_id)
            emergency_text = "Emergency completion due to timeout"
            emergency_summary = "Task completed by emergency handler after timeout"
            
            # Update Database
            success = self._emergency_database_update(todo_id, emergency_html, emergency_text, emergency_summary)
            
            if success:
                # Cleanup
                self._emergency_cleanup(todo_id)
                logging.info(f"✅ Emergency completion successful for Todo #{todo_id}")
            else:
                logging.error(f"❌ Emergency database update failed for Todo #{todo_id}")
                self._emergency_reset_todo(todo_id)
                
        except Exception as e:
            logging.error(f"❌ Emergency completion failed: {e}")
            self._emergency_reset_todo(todo_id)
    
    def _generate_emergency_html(self, todo_id):
        """Generiert Emergency-HTML für Timeout-Situation"""
        completion_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        return f"""<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Emergency Completion - Todo #{todo_id}</title>
    <style>
        body {{ font-family: system-ui, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }}
        h1 {{ color: #dc3545; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }}
        .emergency {{ background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }}
        .warning {{ color: #856404; }}
        .info {{ background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; }}
    </style>
</head>
<body>
    <h1>🚨 Emergency Completion Report - Todo #{todo_id}</h1>
    
    <div class="emergency">
        <h2 class="warning">⚠️ Completion Timeout Reached</h2>
        <p><strong>Completion Time:</strong> {completion_time}</p>
        <p><strong>Reason:</strong> Task exceeded maximum completion timeout ({self.completion_timeout/60} minutes)</p>
        <p><strong>Action:</strong> Automatic emergency completion triggered</p>
    </div>
    
    <div class="info">
        <h2>📋 Task Information</h2>
        <p><strong>Todo ID:</strong> #{todo_id}</p>
        <p><strong>Emergency Handler:</strong> Activated</p>
        <p><strong>Session Status:</strong> Force-completed due to timeout</p>
        <p><strong>Data Collection:</strong> Limited (emergency mode)</p>
    </div>
    
    <div class="emergency">
        <h2>🔧 Recovery Actions Taken</h2>
        <ul>
            <li>✅ Todo status set to 'completed'</li>
            <li>✅ Completion timestamp recorded</li>
            <li>✅ Session files cleaned up</li>
            <li>✅ Emergency report generated</li>
            <li>⚠️ Limited output collection due to timeout</li>
        </ul>
    </div>
    
    <div class="info">
        <h2>📊 Recommendations</h2>
        <ul>
            <li>Review task complexity and consider breaking into smaller parts</li>
            <li>Check for infinite loops or blocking operations</li>
            <li>Verify system resources and performance</li>
            <li>Consider increasing timeout for complex tasks</li>
        </ul>
    </div>
</body>
</html>"""
    
    def _emergency_database_update(self, todo_id, html_output, text_output, summary):
        """Emergency Database Update mit reduzierten Anforderungen"""
        try:
            # Simplified escaping für Emergency-Situation
            html_escaped = html_output.replace("'", "''")
            text_escaped = text_output.replace("'", "''")
            summary_escaped = summary.replace("'", "''")
            
            query = f"""UPDATE {self.config['database']['table_prefix']}project_todos 
SET status='completed',
    completed_at=NOW(),
    claude_html_output='{html_escaped}',
    claude_text_output='{text_escaped}',
    claude_summary='{summary_escaped}',
    updated_at=NOW()
WHERE id={todo_id}"""
            
            cmd = f"wp db query \"{query}\""
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=60)
            
            if result.returncode == 0:
                logging.info(f"✅ Emergency database update successful for Todo #{todo_id}")
                return True
            else:
                logging.error(f"❌ Emergency database update failed: {result.stderr}")
                return False
                
        except Exception as e:
            logging.error(f"❌ Emergency database update exception: {e}")
            return False
    
    def _emergency_cleanup(self, todo_id):
        """Emergency Cleanup"""
        try:
            # Remove current todo file
            current_todo_path = Path(self.config["paths"]["current_todo"])
            if current_todo_path.exists():
                current_todo_path.unlink()
                logging.info("✅ Current todo file removed")
            
            # Remove completion marker
            task_completed_path = Path(self.config["paths"]["task_completed"])
            if task_completed_path.exists():
                task_completed_path.unlink()
                logging.info("✅ Task completed marker removed")
            
            # Archive session if exists
            session_dir = Path(f"/tmp/claude_session_{todo_id}")
            if session_dir.exists():
                archive_dir = Path(self.config["paths"]["archive"]) / f"emergency_todo_{todo_id}_{int(time.time())}"
                archive_dir.mkdir(parents=True, exist_ok=True)
                
                for file in session_dir.iterdir():
                    if file.is_file():
                        try:
                            (archive_dir / file.name).write_bytes(file.read_bytes())
                        except:
                            pass  # Ignore individual file errors in emergency
                
                # Remove original session dir
                import shutil
                shutil.rmtree(session_dir, ignore_errors=True)
                logging.info(f"✅ Session archived to {archive_dir}")
            
        except Exception as e:
            logging.warning(f"⚠️ Emergency cleanup partial failure: {e}")
    
    def _emergency_reset_todo(self, todo_id):
        """Last Resort: Reset Todo to 'offen' status"""
        try:
            query = f"UPDATE {self.config['database']['table_prefix']}project_todos SET status='offen', updated_at=NOW() WHERE id={todo_id}"
            cmd = f"wp db query \"{query}\""
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                # Emergency cleanup
                self._emergency_cleanup(todo_id)
                logging.warning(f"⚠️ Todo #{todo_id} reset to 'offen' status (last resort)")
            else:
                logging.error(f"❌ Emergency reset failed for Todo #{todo_id}")
                
        except Exception as e:
            logging.error(f"❌ Emergency reset exception: {e}")
    
    def setup_session_watchdog(self, todo_id):
        """Setzt Session-Watchdog für erweiterte Überwachung"""
        watchdog_timer = threading.Timer(self.emergency_timeout, self._handle_session_emergency, args=[todo_id])
        watchdog_timer.daemon = True
        watchdog_timer.start()
        
        logging.info(f"🐕 Session watchdog activated for Todo #{todo_id}: {self.emergency_timeout/60} minutes")
        return watchdog_timer
    
    def _handle_session_emergency(self, todo_id):
        """Behandelt Session-Notfall (längere Timeouts)"""
        logging.error(f"🚨 Session emergency triggered for Todo #{todo_id}")
        
        try:
            # Prüfe System-Status
            self._log_system_status()
            
            # Force-terminate wenn möglich
            self._force_terminate_session(todo_id)
            
            # Emergency completion
            self._emergency_complete_todo(todo_id)
            
        except Exception as e:
            logging.error(f"❌ Session emergency handling failed: {e}")
    
    def _log_system_status(self):
        """Loggt System-Status für Debugging"""
        try:
            # CPU & Memory
            result = subprocess.run(["top", "-bn1"], capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                lines = result.stdout.split('\n')[:10]  # First 10 lines
                logging.info(f"System Status: {' | '.join(lines)}")
            
            # Disk Space
            result = subprocess.run(["df", "-h", "/tmp"], capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                logging.info(f"Disk Space: {result.stdout.strip()}")
                
        except Exception as e:
            logging.warning(f"⚠️ System status logging failed: {e}")
    
    def _force_terminate_session(self, todo_id):
        """Force-terminiert Session wenn möglich"""
        try:
            # Prüfe tmux sessions
            result = subprocess.run(["tmux", "list-sessions"], capture_output=True, text=True, timeout=10)
            if result.returncode == 0 and "claude" in result.stdout:
                logging.warning(f"🔄 tmux session 'claude' still active during emergency")
                
                # Könnte hier tmux session killen, aber das ist sehr drastisch
                # logging.warning("💀 Killing tmux session (emergency)")
                # subprocess.run(["tmux", "kill-session", "-t", "claude"], timeout=10)
                
        except Exception as e:
            logging.warning(f"⚠️ Force terminate attempt failed: {e}")

# Global Emergency Handler Instance
_emergency_handler = None

def get_emergency_handler():
    """Singleton Pattern für Emergency Handler"""
    global _emergency_handler
    if _emergency_handler is None:
        _emergency_handler = EmergencyHandler()
    return _emergency_handler

def setup_completion_timeout(todo_id, timeout_seconds=None):
    """Public API - Setup Completion Timeout"""
    handler = get_emergency_handler()
    return handler.setup_completion_timeout(todo_id, timeout_seconds)

def cancel_completion_timeout(todo_id):
    """Public API - Cancel Completion Timeout"""
    handler = get_emergency_handler()
    return handler.cancel_completion_timeout(todo_id)

def emergency_complete(todo_id):
    """Public API - Force Emergency Completion"""
    handler = get_emergency_handler()
    return handler._emergency_complete_todo(todo_id)

if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 2 and sys.argv[1] == "emergency-complete":
        todo_id = sys.argv[2]
        handler = EmergencyHandler()
        handler._emergency_complete_todo(todo_id)
        print(f"Emergency completion triggered for Todo #{todo_id}")
    else:
        print("Usage: emergency_handlers.py emergency-complete <todo_id>")
        print("This script provides emergency handlers for the todo system.")