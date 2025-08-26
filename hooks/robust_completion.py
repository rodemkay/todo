#!/usr/bin/env python3
"""
Robust Completion Mechanism - Multi-Layer System
Zuverlässige TASK_COMPLETED Verarbeitung mit Fallbacks
"""

import json
import os
import time
import html
from datetime import datetime
from pathlib import Path
import subprocess
import logging
import traceback

# Logging Setup
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/home/rodemkay/www/react/plugin-todo/hooks/logs/completion.log'),
        logging.StreamHandler()
    ]
)

class RobustCompletion:
    def __init__(self, config):
        self.config = config
        self.retry_count = 0
        self.max_retries = config.get('behavior', {}).get('max_retries', 3)
        self.completion_timestamp = datetime.now()
        
    def execute_completion(self, todo_id):
        """Hauptfunktion - Robuste Completion mit mehreren Fallback-Ebenen"""
        logging.info(f"🚀 Starting robust completion for Todo #{todo_id}")
        
        # Layer 1: Output Collection mit Fallbacks
        outputs = self._collect_outputs_multi_layer(todo_id)
        
        # Layer 2: HTML Generation (auch bei Collector-Versagen)
        html_output = self._generate_html_with_fallback(todo_id, outputs)
        
        # Layer 3: Database Update mit Retry-Logic
        success = self._update_database_with_retry(todo_id, outputs, html_output)
        
        # Layer 4: Cleanup & Verification
        self._cleanup_and_verify(todo_id, success)
        
        return success
    
    def _collect_outputs_multi_layer(self, todo_id):
        """Layer 1: Output Collection mit 3 Fallback-Methoden"""
        outputs = {
            'html': '',
            'text': '',
            'summary': '',
            'method_used': 'none',
            'collection_errors': []
        }
        
        # Method 1: Output Collector (Primary)
        try:
            from output_collector import collect_outputs_for_todo
            collected = collect_outputs_for_todo(todo_id)
            outputs.update(collected)
            outputs['method_used'] = 'output_collector'
            logging.info(f"✅ Output Collector successful: {len(outputs['html'])} chars HTML")
            return outputs
        except Exception as e:
            outputs['collection_errors'].append(f"output_collector: {str(e)}")
            logging.warning(f"⚠️ Output Collector failed: {e}")
        
        # Method 2: Session Directory Fallback
        try:
            session_dir = Path(f"/tmp/claude_session_{todo_id}")
            if session_dir.exists():
                # Prüfe zuerst nach Markdown, dann HTML als Fallback
                md_file = session_dir / "output.md"
                html_file = session_dir / "output.html"
                text_file = session_dir / "output.txt"
                summary_file = session_dir / "summary.txt"
                
                if md_file.exists():
                    outputs['html'] = md_file.read_text()  # Nutze Markdown für HTML-Feld
                    outputs['markdown'] = md_file.read_text()
                elif html_file.exists():
                    outputs['html'] = html_file.read_text()
                if text_file.exists():
                    outputs['text'] = text_file.read_text()
                if summary_file.exists():
                    outputs['summary'] = summary_file.read_text()
                    
                outputs['method_used'] = 'session_directory'
                logging.info(f"✅ Session Directory fallback successful")
                return outputs
        except Exception as e:
            outputs['collection_errors'].append(f"session_directory: {str(e)}")
            logging.warning(f"⚠️ Session Directory fallback failed: {e}")
        
        # Method 3: tmux Capture Fallback
        try:
            result = subprocess.run(
                ["tmux", "capture-pane", "-t", "claude:0.0", "-p"],
                capture_output=True, text=True, timeout=10
            )
            if result.returncode == 0 and result.stdout:
                outputs['text'] = result.stdout[-5000:]  # Last 5000 chars
                outputs['summary'] = "Auto-generated from tmux capture"
                outputs['method_used'] = 'tmux_capture'
                logging.info(f"✅ tmux capture fallback successful")
                return outputs
        except Exception as e:
            outputs['collection_errors'].append(f"tmux_capture: {str(e)}")
            logging.warning(f"⚠️ tmux capture fallback failed: {e}")
        
        # Method 4: Emergency Fallback - Basic Todo Data
        outputs['method_used'] = 'emergency_fallback'
        logging.warning(f"⚠️ All collection methods failed, using emergency fallback")
        return outputs
    
    def _generate_html_with_fallback(self, todo_id, outputs):
        """Layer 2: Markdown Generation für bessere Lesbarkeit und Weiterverarbeitung"""
        
        # Falls bereits Markdown vorhanden
        if outputs.get('markdown') and len(outputs['markdown']) > 100:
            return outputs['markdown']
        
        # Fallback Markdown generieren
        try:
            todo_data = self._get_todo_data(todo_id)
            completion_time = self.completion_timestamp.strftime("%Y-%m-%d %H:%M:%S")
            
            # Markdown-Format für bessere Lesbarkeit in Claude Code
            fallback_markdown = f"""# 📋 Todo #{todo_id} - Completion Report

## ✅ Task Information
- **Title:** {todo_data.get('title', 'Unknown Task')}
- **Description:** {todo_data.get('description', 'No description')[:300]}
- **Completed At:** {completion_time}
- **Output Collection Method:** {outputs.get('method_used', 'unknown')}

## 🔧 Execution Summary
Task wurde erfolgreich abgeschlossen durch Claude Code CLI.

{self._generate_collection_status_markdown(outputs)}

{self._generate_text_output_section_markdown(outputs)}

## 📊 System Information
- **Environment:** Ryzen Server (tmux: claude)
- **Working Directory:** {todo_data.get('working_directory', '/home/rodemkay/www/react/plugin-todo/')}
- **Scope:** {todo_data.get('scope', 'todo-plugin')}
"""
            
            logging.info(f"✅ Generated fallback Markdown: {len(fallback_markdown)} chars")
            return fallback_markdown
            
        except Exception as e:
            logging.error(f"❌ Failed to generate fallback HTML: {e}")
            return f"<html><body><h1>Todo #{todo_id} completed at {completion_time}</h1><p>Basic completion report - HTML generation failed.</p></body></html>"
    
    def _generate_collection_status_markdown(self, outputs):
        """Generiert Markdown-Sektion mit Collection-Status"""
        if outputs.get('collection_errors'):
            errors_md = "\n### Collection Warnings:\n"
            for error in outputs['collection_errors']:
                errors_md += f"- ❌ {error}\n"
            return errors_md
        else:
            return "✅ Output collection successful\n"
    
    def _generate_collection_status_html(self, outputs):
        """Legacy HTML-Version für Kompatibilität"""
        return self._generate_collection_status_markdown(outputs)
    
    def _generate_text_output_section_markdown(self, outputs):
        """Generiert Text-Output-Sektion in Markdown"""
        if outputs.get('text') and len(outputs['text']) > 10:
            return f"""
## 📝 Session Output
```
{outputs['text'][-2000:]}
```
"""
        return ""
    
    def _generate_text_output_section(self, outputs):
        """Legacy HTML-Version für Kompatibilität"""
        if outputs.get('text') and len(outputs['text']) > 10:
            return f"""
    <div class="section">
        <h2>📝 Session Output</h2>
        <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">
{html.escape(outputs['text'][-2000:])}
        </pre>
    </div>"""
        return ""
    
    def _update_database_with_retry(self, todo_id, outputs, html_output):
        """Layer 3: Database Update mit Retry-Logic"""
        
        for attempt in range(self.max_retries):
            try:
                success = self._execute_database_update(todo_id, outputs, html_output)
                if success:
                    logging.info(f"✅ Database update successful on attempt {attempt + 1}")
                    return True
                else:
                    logging.warning(f"⚠️ Database update failed on attempt {attempt + 1}")
                    
            except Exception as e:
                logging.error(f"❌ Database update exception on attempt {attempt + 1}: {e}")
                
            # Exponential backoff
            if attempt < self.max_retries - 1:
                sleep_time = 2 ** attempt
                logging.info(f"⏳ Retrying in {sleep_time} seconds...")
                time.sleep(sleep_time)
        
        logging.error(f"❌ All {self.max_retries} database update attempts failed")
        return False
    
    def _execute_database_update(self, todo_id, outputs, html_output):
        """Führt Database Update aus"""
        # MySQL-Escaping
        html_escaped = html_output.replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
        text_escaped = outputs.get('text', '').replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
        summary_escaped = outputs.get('summary', '').replace("\\", "\\\\").replace("'", "\\'").replace('"', '\\"')
        
        # SQL Query
        query = f"""UPDATE {self.config['database']['table_prefix']}project_todos 
SET status='completed',
    completed_at=NOW(),
    claude_html_output='{html_escaped}',
    claude_text_output='{text_escaped}',
    claude_summary='{summary_escaped}',
    updated_at=NOW()
WHERE id={todo_id}"""
        
        # SSH Command ausführen
        query_escaped = query.replace("'", "'\\''")
        cmd = f"wp db query '{query_escaped}'"
        
        result = subprocess.run([
            "ssh", f"rodemkay@{self.config['database']['host']}", 
            f"cd {self.config['database']['remote_path']} && {cmd}"
        ], capture_output=True, text=True, timeout=30)
        
        return result.returncode == 0
    
    def _cleanup_and_verify(self, todo_id, success):
        """Layer 4: Cleanup & Verification"""
        try:
            # Lösche current todo file
            current_todo_path = Path(self.config["paths"]["current_todo"])
            if current_todo_path.exists():
                current_todo_path.unlink()
                logging.info("✅ Current todo file cleaned up")
            
            # Lösche TASK_COMPLETED marker
            task_completed_path = Path(self.config["paths"]["task_completed"])
            if task_completed_path.exists():
                task_completed_path.unlink()
                logging.info("✅ TASK_COMPLETED marker cleaned up")
            
            # Archiviere Session-Daten bei Erfolg
            if success:
                self._archive_session_data(todo_id)
            
            # Status-Verifikation
            if success:
                self._verify_completion_status(todo_id)
                
        except Exception as e:
            logging.error(f"❌ Cleanup failed: {e}")
    
    def _archive_session_data(self, todo_id):
        """Archiviert Session-Daten"""
        try:
            source_dir = Path(f"/tmp/claude_session_{todo_id}")
            if source_dir.exists():
                archive_dir = Path(self.config["paths"]["archive"]) / f"todo_{todo_id}_{int(time.time())}"
                archive_dir.mkdir(parents=True, exist_ok=True)
                
                for file in source_dir.iterdir():
                    if file.is_file():
                        (archive_dir / file.name).write_bytes(file.read_bytes())
                
                logging.info(f"✅ Session data archived to {archive_dir}")
        except Exception as e:
            logging.warning(f"⚠️ Archiving failed: {e}")
    
    def _verify_completion_status(self, todo_id):
        """Verifiziert dass Completion in DB angekommen ist"""
        try:
            query = f"SELECT status, completed_at FROM {self.config['database']['table_prefix']}project_todos WHERE id={todo_id}"
            cmd = f"wp db query '{query}'"
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=15)
            
            if result.returncode == 0 and "completed" in result.stdout:
                logging.info(f"✅ Completion verified in database for Todo #{todo_id}")
            else:
                logging.error(f"❌ Completion verification failed for Todo #{todo_id}")
                
        except Exception as e:
            logging.warning(f"⚠️ Completion verification failed: {e}")
    
    def _get_todo_data(self, todo_id):
        """Holt Todo-Daten für Fallback-HTML"""
        try:
            query = f"SELECT title, description, working_directory, scope FROM {self.config['database']['table_prefix']}project_todos WHERE id={todo_id}"
            cmd = f"wp db query '{query}'"
            
            result = subprocess.run([
                "ssh", f"rodemkay@{self.config['database']['host']}", 
                f"cd {self.config['database']['remote_path']} && {cmd}"
            ], capture_output=True, text=True, timeout=15)
            
            if result.returncode == 0 and result.stdout:
                lines = result.stdout.strip().split('\n')
                if len(lines) > 1:
                    parts = lines[1].split('\t')
                    return {
                        'title': parts[0] if len(parts) > 0 else 'Unknown',
                        'description': parts[1] if len(parts) > 1 else '',
                        'working_directory': parts[2] if len(parts) > 2 else '/home/rodemkay/www/react/plugin-todo/',
                        'scope': parts[3] if len(parts) > 3 else 'todo-plugin'
                    }
        except Exception as e:
            logging.warning(f"⚠️ Could not fetch todo data: {e}")
        
        return {
            'title': f'Todo #{todo_id}',
            'description': 'Details could not be retrieved',
            'working_directory': '/home/rodemkay/www/react/plugin-todo/',
            'scope': 'todo-plugin'
        }


def robust_complete_todo(todo_id, config):
    """Public API - Robuste Todo-Completion"""
    completion = RobustCompletion(config)
    return completion.execute_completion(todo_id)


if __name__ == "__main__":
    # Test-Modus
    import sys
    if len(sys.argv) > 1:
        todo_id = sys.argv[1]
        with open('/home/rodemkay/www/react/plugin-todo/hooks/config.json') as f:
            config = json.load(f)
        
        success = robust_complete_todo(todo_id, config)
        print(f"Completion result: {'SUCCESS' if success else 'FAILED'}")