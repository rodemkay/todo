#!/usr/bin/env python3
"""
Claude Output Collector
Sammelt und verarbeitet Claude's Outputs während der Session
"""

import json
import os
import re
import html
from datetime import datetime
from pathlib import Path
import subprocess

class OutputCollector:
    def __init__(self, todo_id):
        self.todo_id = todo_id
        self.session_start = datetime.now()
        self.outputs = {
            'files_created': [],
            'files_modified': [],
            'commands_executed': [],
            'errors_encountered': [],
            'key_actions': [],
            'raw_text': []
        }
        
        # Pfade für Session-Tracking
        self.session_dir = Path(f"/tmp/claude_session_{todo_id}")
        self.session_dir.mkdir(exist_ok=True)
        
        # Output-Dateien
        self.html_file = self.session_dir / "output.html"
        self.text_file = self.session_dir / "output.txt"
        self.summary_file = self.session_dir / "summary.txt"
        
    def collect_from_terminal(self):
        """Sammelt Output aus dem Terminal/tmux Session"""
        try:
            # Versuche tmux pane content zu lesen
            result = subprocess.run(
                ["tmux", "capture-pane", "-t", "claude:0.0", "-p"],
                capture_output=True, text=True
            )
            if result.returncode == 0:
                self.outputs['raw_text'].append(result.stdout)
                return result.stdout
        except:
            pass
        return ""
    
    def track_file_operation(self, operation, file_path):
        """Trackt Datei-Operationen"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        entry = {"time": timestamp, "path": file_path}
        
        if operation == "created":
            self.outputs['files_created'].append(entry)
        elif operation == "modified":
            self.outputs['files_modified'].append(entry)
            
    def track_command(self, command, output="", success=True):
        """Trackt ausgeführte Befehle"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.outputs['commands_executed'].append({
            "time": timestamp,
            "command": command,
            "success": success,
            "output": output[:500] if output else ""  # Erste 500 Zeichen
        })
        
    def track_error(self, error_msg):
        """Trackt aufgetretene Fehler"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.outputs['errors_encountered'].append({
            "time": timestamp,
            "error": error_msg
        })
        
    def add_key_action(self, action):
        """Fügt eine wichtige Aktion hinzu"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.outputs['key_actions'].append({
            "time": timestamp,
            "action": action
        })
    
    def generate_markdown_output(self):
        """Generiert Markdown-Zusammenfassung der Session für bessere Lesbarkeit"""
        markdown_content = f"""# 📋 Todo #{self.todo_id} - Session Report

## 📊 Session Information
- **Session Start:** {self.session_start.strftime("%Y-%m-%d %H:%M:%S")}
- **Session Duration:** {(datetime.now() - self.session_start).total_seconds():.0f} Sekunden

"""
        
        # Key Actions
        if self.outputs['key_actions']:
            markdown_content += "## 🎯 Hauptaktionen\n\n"
            for action in self.outputs['key_actions']:
                markdown_content += f"- `[{action['time']}]` {action['action']}\n"
            markdown_content += "\n"
        
        # Files Created
        if self.outputs['files_created']:
            markdown_content += f"## 📁 Erstellte Dateien ({len(self.outputs['files_created'])})\n\n"
            for file in self.outputs['files_created']:
                markdown_content += f"- `{file['path']}` [{file['time']}]\n"
            markdown_content += "\n"
        
        # Files Modified
        if self.outputs['files_modified']:
            markdown_content += f"## ✏️ Geänderte Dateien ({len(self.outputs['files_modified'])})\n\n"
            for file in self.outputs['files_modified']:
                markdown_content += f"- `{file['path']}` [{file['time']}]\n"
            markdown_content += "\n"
        
        # Commands Executed
        if self.outputs['commands_executed']:
            markdown_content += f"## 💻 Ausgeführte Befehle ({len(self.outputs['commands_executed'])})\n\n"
            markdown_content += "Die letzten 10 Befehle:\n\n"
            for cmd in self.outputs['commands_executed'][-10:]:  # Letzte 10 Befehle
                status = "✅" if cmd['success'] else "❌"
                markdown_content += f"{status} `{cmd['command']}` [{cmd['time']}]\n"
                if cmd.get('output'):
                    markdown_content += f"   ```\n   {cmd['output'][:200]}\n   ```\n"
            markdown_content += "\n"
        
        # Errors
        if self.outputs['errors_encountered']:
            markdown_content += f"## ⚠️ Aufgetretene Fehler ({len(self.outputs['errors_encountered'])})\n\n"
            for error in self.outputs['errors_encountered']:
                markdown_content += f"- `[{error['time']}]` {error['error']}\n"
            markdown_content += "\n"
        
        # Raw Output (falls vorhanden)
        if self.outputs.get('raw_text') and self.outputs['raw_text']:
            last_output = ''.join(self.outputs['raw_text'][-3:])  # Letzte 3 Captures
            if last_output:
                markdown_content += "## 📝 Terminal Output (Auszug)\n\n```\n"
                markdown_content += last_output[-3000:]  # Letzte 3000 Zeichen
                markdown_content += "\n```\n"
        
        # Speichern als .md Datei
        md_file = self.session_dir / "output.md"
        with open(md_file, 'w', encoding='utf-8') as f:
            f.write(markdown_content)
        
        # Update file references für Markdown
        self.html_file = md_file  # Überschreibe html_file mit md_file für Kompatibilität
        self.markdown_file = md_file  # Explizite Markdown-Referenz
            
        return markdown_content
    
    def generate_html_output(self):
        """Generiert HTML-Zusammenfassung der Session"""
        html_content = f"""<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Todo #{self.todo_id} - Session Report</title>
    <style>
        body {{ font-family: system-ui, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }}
        h1 {{ color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }}
        h2 {{ color: #34495e; margin-top: 30px; }}
        .section {{ background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }}
        .success {{ color: #28a745; }}
        .error {{ color: #dc3545; }}
        .file {{ background: #e9ecef; padding: 5px 10px; border-radius: 3px; margin: 5px 0; font-family: monospace; }}
        .command {{ background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 5px; margin: 10px 0; }}
        .timestamp {{ color: #6c757d; font-size: 0.9em; }}
        ul {{ list-style-type: none; padding-left: 0; }}
        li {{ margin: 10px 0; }}
    </style>
</head>
<body>
    <h1>📋 Todo #{self.todo_id} - Session Report</h1>
    <div class="section">
        <p><strong>Session Start:</strong> {self.session_start.strftime("%Y-%m-%d %H:%M:%S")}</p>
        <p><strong>Session Duration:</strong> {(datetime.now() - self.session_start).total_seconds():.0f} Sekunden</p>
    </div>
"""
        
        # Key Actions
        if self.outputs['key_actions']:
            html_content += """
    <h2>🎯 Hauptaktionen</h2>
    <div class="section">
        <ul>"""
            for action in self.outputs['key_actions']:
                html_content += f"""
            <li><span class="timestamp">[{action['time']}]</span> {html.escape(action['action'])}</li>"""
            html_content += """
        </ul>
    </div>"""
        
        # Files Created
        if self.outputs['files_created']:
            html_content += f"""
    <h2>📁 Erstellte Dateien ({len(self.outputs['files_created'])})</h2>
    <div class="section">
        <ul>"""
            for file in self.outputs['files_created']:
                html_content += f"""
            <li><span class="file">{html.escape(file['path'])}</span> <span class="timestamp">[{file['time']}]</span></li>"""
            html_content += """
        </ul>
    </div>"""
        
        # Files Modified
        if self.outputs['files_modified']:
            html_content += f"""
    <h2>✏️ Geänderte Dateien ({len(self.outputs['files_modified'])})</h2>
    <div class="section">
        <ul>"""
            for file in self.outputs['files_modified']:
                html_content += f"""
            <li><span class="file">{html.escape(file['path'])}</span> <span class="timestamp">[{file['time']}]</span></li>"""
            html_content += """
        </ul>
    </div>"""
        
        # Commands Executed
        if self.outputs['commands_executed']:
            html_content += f"""
    <h2>💻 Ausgeführte Befehle ({len(self.outputs['commands_executed'])})</h2>
    <div class="section">"""
            for cmd in self.outputs['commands_executed'][-10:]:  # Letzte 10 Befehle
                status = "✅" if cmd['success'] else "❌"
                html_content += f"""
        <div class="command">
            <span>{status}</span> <code>{html.escape(cmd['command'])}</code>
            <span class="timestamp">[{cmd['time']}]</span>
        </div>"""
            html_content += """
    </div>"""
        
        # Errors
        if self.outputs['errors_encountered']:
            html_content += f"""
    <h2>⚠️ Aufgetretene Fehler ({len(self.outputs['errors_encountered'])})</h2>
    <div class="section">
        <ul>"""
            for error in self.outputs['errors_encountered']:
                html_content += f"""
            <li class="error"><span class="timestamp">[{error['time']}]</span> {html.escape(error['error'])}</li>"""
            html_content += """
        </ul>
    </div>"""
        
        html_content += """
</body>
</html>"""
        
        # Speichern
        with open(self.html_file, 'w', encoding='utf-8') as f:
            f.write(html_content)
            
        return html_content
    
    def generate_text_output(self):
        """Generiert Plain-Text Zusammenfassung"""
        text_lines = [
            f"Todo #{self.todo_id} - Session Report",
            "=" * 50,
            f"Session Start: {self.session_start.strftime('%Y-%m-%d %H:%M:%S')}",
            f"Duration: {(datetime.now() - self.session_start).total_seconds():.0f} Sekunden",
            "",
        ]
        
        if self.outputs['key_actions']:
            text_lines.append("HAUPTAKTIONEN:")
            for action in self.outputs['key_actions']:
                text_lines.append(f"  [{action['time']}] {action['action']}")
            text_lines.append("")
        
        if self.outputs['files_created']:
            text_lines.append(f"ERSTELLTE DATEIEN ({len(self.outputs['files_created'])}):")
            for file in self.outputs['files_created']:
                text_lines.append(f"  - {file['path']} [{file['time']}]")
            text_lines.append("")
        
        if self.outputs['files_modified']:
            text_lines.append(f"GEÄNDERTE DATEIEN ({len(self.outputs['files_modified'])}):")
            for file in self.outputs['files_modified']:
                text_lines.append(f"  - {file['path']} [{file['time']}]")
            text_lines.append("")
        
        if self.outputs['commands_executed']:
            text_lines.append(f"BEFEHLE ({len(self.outputs['commands_executed'])}):")
            for cmd in self.outputs['commands_executed'][-5:]:
                status = "✓" if cmd['success'] else "✗"
                text_lines.append(f"  {status} {cmd['command']} [{cmd['time']}]")
            text_lines.append("")
        
        if self.outputs['errors_encountered']:
            text_lines.append(f"FEHLER ({len(self.outputs['errors_encountered'])}):")
            for error in self.outputs['errors_encountered']:
                text_lines.append(f"  [{error['time']}] {error['error']}")
            text_lines.append("")
        
        text_output = "\n".join(text_lines)
        
        # Speichern
        with open(self.text_file, 'w', encoding='utf-8') as f:
            f.write(text_output)
            
        return text_output
    
    def generate_summary(self):
        """Generiert kurze Zusammenfassung (max 150 Zeichen)"""
        # Zähle Aktionen
        files_created = len(self.outputs['files_created'])
        files_modified = len(self.outputs['files_modified'])
        commands = len(self.outputs['commands_executed'])
        errors = len(self.outputs['errors_encountered'])
        
        # Erstelle Summary
        parts = []
        if files_created > 0:
            parts.append(f"{files_created} Dateien erstellt")
        if files_modified > 0:
            parts.append(f"{files_modified} geändert")
        if commands > 0:
            parts.append(f"{commands} Befehle")
        if errors > 0:
            parts.append(f"{errors} Fehler")
            
        if parts:
            summary = "✅ " + ", ".join(parts)
        else:
            summary = "✅ Task abgeschlossen"
            
        # Kürzen auf 150 Zeichen
        if len(summary) > 150:
            summary = summary[:147] + "..."
            
        # Speichern
        with open(self.summary_file, 'w', encoding='utf-8') as f:
            f.write(summary)
            
        return summary
    
    def cleanup(self):
        """Räumt temporäre Dateien auf"""
        # Session-Verzeichnis behalten für Debugging
        # Kann später gelöscht werden nach erfolgreichem DB-Update
        pass

# Integration mit todo-manager.py
def collect_outputs_for_todo(todo_id):
    """Hauptfunktion zum Sammeln aller Outputs für ein Todo"""
    collector = OutputCollector(todo_id)
    
    # Sammle Terminal-Output
    terminal_content = collector.collect_from_terminal()
    
    # Parse Terminal-Content für File-Operationen und Commands
    # (Vereinfachte Version - kann erweitert werden)
    for line in terminal_content.split('\n'):
        if 'File created successfully' in line:
            # Extract file path
            match = re.search(r'at: (.+)', line)
            if match:
                collector.track_file_operation('created', match.group(1))
        elif 'has been updated' in line:
            # Extract file path
            match = re.search(r'file (.+) has been updated', line)
            if match:
                collector.track_file_operation('modified', match.group(1))
        elif line.startswith('$') or line.startswith('#'):
            # Command line
            collector.track_command(line[1:].strip())
    
    # Generiere Outputs - JETZT MARKDOWN STATT HTML!
    markdown = collector.generate_markdown_output()  # NEU: Markdown statt HTML
    text = collector.generate_text_output()
    summary = collector.generate_summary()
    
    return {
        'markdown': markdown,  # Geändert von 'html' zu 'markdown'
        'html': markdown,      # Behalte 'html' key für Kompatibilität, nutze aber Markdown-Content
        'text': text,
        'summary': summary,
        'session_dir': str(collector.session_dir)
    }

if __name__ == "__main__":
    # Test-Modus
    import sys
    if len(sys.argv) > 1:
        todo_id = sys.argv[1]
        results = collect_outputs_for_todo(todo_id)
        print(f"Markdown: {len(results.get('markdown', ''))} chars")
        print(f"HTML (contains Markdown): {len(results['html'])} chars")
        print(f"Text: {len(results['text'])} chars")
        print(f"Summary: {results['summary']}")
        print(f"Session Dir: {results['session_dir']}")