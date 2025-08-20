#!/usr/bin/env python3
"""
Claude Code Hook: Stop Consistency Validator
Validiert Hook-System-Konsistenz bei Session-Ende und blockiert bei Violations
"""
import json
import sys
import os
from datetime import datetime
from pathlib import Path

# Konfiguration
HOOK_LOG_PATH = Path("/home/rodemkay/.claude/hooks/hook_consistency.log")
AUDIT_LOG_PATH = Path("/home/rodemkay/.claude/hooks/tool_audit.log")
CONTEXT_FILE = Path("/home/rodemkay/.claude/hooks/task_context.json")
CURRENT_TODO_FILE = Path("/tmp/CURRENT_TODO_ID")

def log_event(level: str, message: str, data: dict = None):
    """Log hook events mit Timestamp"""
    timestamp = datetime.now().isoformat()
    log_entry = f"[{timestamp}] [{level}] {message}"
    if data:
        log_entry += f" | Data: {json.dumps(data)}"
    
    with open(HOOK_LOG_PATH, "a") as f:
        f.write(log_entry + "\n")

def load_context():
    """Lade aktuellen Task-Context"""
    if CONTEXT_FILE.exists():
        with open(CONTEXT_FILE, 'r') as f:
            return json.load(f)
    return {"active_todos": [], "last_task_completed": None, "hook_violations": 0}

def get_current_todo_id():
    """Hole aktuelle Todo-ID"""
    if CURRENT_TODO_FILE.exists():
        with open(CURRENT_TODO_FILE, 'r') as f:
            return f.read().strip()
    return None

def analyze_session_audit_log_with_acknowledgments(context: dict):
    """Analysiere Session Audit-Log mit Berücksichtigung von acknowledgments"""
    if not AUDIT_LOG_PATH.exists():
        return []
    
    issues = []
    todo_write_completions = []
    task_completed_triggers = []
    acknowledged_violations = set(context.get("historical_violations_acknowledged", []))
    
    # Lese letzte 100 Audit-Einträge
    with open(AUDIT_LOG_PATH, 'r') as f:
        lines = f.readlines()[-100:]
    
    for line in lines:
        try:
            entry = json.loads(line.strip())
            tool_name = entry.get("tool_name", "")
            
            # Tracke TodoWrite completions
            if tool_name == "TodoWrite":
                tool_input = entry.get("tool_input", {})
                todos = tool_input.get("todos", [])
                for todo in todos:
                    if todo.get("status") == "completed":
                        todo_write_completions.append({
                            "timestamp": entry.get("timestamp"),
                            "todo_id": todo.get("id")
                        })
            
            # Tracke TASK_COMPLETED Triggers  
            elif tool_name == "Bash":
                command = entry.get("tool_input", {}).get("command", "")
                if "TASK_COMPLETED" in command:
                    task_completed_triggers.append({
                        "timestamp": entry.get("timestamp"),
                        "command": command
                    })
        
        except (json.JSONDecodeError, KeyError):
            continue
    
    # Analyse: TodoWrite completions ohne TASK_COMPLETED (mit Acknowledgment-Filter)
    for completion in todo_write_completions:
        todo_id = completion["todo_id"]
        
        # Überspringe bereits acknowledged violations
        if str(todo_id) in map(str, acknowledged_violations):
            continue
            
        # Suche nach TASK_COMPLETED in zeitnaher Umgebung (5min window)
        completion_time = datetime.fromisoformat(completion["timestamp"])
        
        matching_trigger = None
        for trigger in task_completed_triggers:
            trigger_time = datetime.fromisoformat(trigger["timestamp"])
            time_diff = abs((completion_time - trigger_time).total_seconds())
            
            if time_diff <= 300:  # 5 Minuten
                matching_trigger = trigger
                break
        
        if not matching_trigger:
            issues.append(f"VIOLATION: TodoWrite completion of {completion['todo_id']} without TASK_COMPLETED trigger")
    
    return issues

# Legacy function for backward compatibility  
def analyze_session_audit_log():
    """Legacy function - delegates to new implementation"""
    context = load_context()
    return analyze_session_audit_log_with_acknowledgments(context)

def perform_consistency_validation(context: dict) -> tuple[bool, list, str]:
    """Führe umfassende Konsistenz-Validierung durch mit historischen Acknowledgments"""
    violations = []
    warnings = []
    
    current_todo = get_current_todo_id()
    
    # 1. Prüfe auf aktive Todos ohne proper completion
    if context["active_todos"] and current_todo:
        violations.append(f"CRITICAL: Active todo #{current_todo} not properly completed via TASK_COMPLETED")
    
    # 2. Prüfe Hook-Violations Counter
    total_violations = context.get("hook_violations", 0)
    if total_violations > 0:
        warnings.append(f"WARNING: {total_violations} hook violations detected in this session")
    
    # 3. Audit-Log Analyse mit Acknowledgment-Filter
    audit_issues = analyze_session_audit_log_with_acknowledgments(context)
    violations.extend(audit_issues)
    
    # 4. Prüfe auf kritische Dateien
    if CURRENT_TODO_FILE.exists() and not context.get("task_completed_triggered", False):
        with open(CURRENT_TODO_FILE, 'r') as f:
            todo_id = f.read().strip()
            if todo_id:
                violations.append(f"CRITICAL: Todo #{todo_id} remains in CURRENT_TODO_ID without TASK_COMPLETED")
    
    # Generiere Report
    all_issues = violations + warnings
    is_consistent = len(violations) == 0
    
    report = f"""
🔍 SESSION CONSISTENCY VALIDATION REPORT
{'='*50}
Session End Time: {datetime.now().isoformat()}
Total Issues: {len(all_issues)}
Critical Violations: {len(violations)}
Warnings: {len(warnings)}

Hook System Stats:
- Total Hook Violations: {total_violations}
- Active Todos: {context.get('active_todos', [])}
- Current Todo ID: {current_todo}
- Last TASK_COMPLETED: {context.get('last_task_completed', 'Never')}

"""
    
    if violations:
        report += "\n🚨 CRITICAL VIOLATIONS:\n"
        for v in violations:
            report += f"  - {v}\n"
    
    if warnings:
        report += "\n⚠️  WARNINGS:\n"
        for w in warnings:
            report += f"  - {w}\n"
    
    if is_consistent:
        report += "\n✅ SESSION CONSISTENT: All hook system rules followed correctly!"
    else:
        report += f"\n❌ SESSION INCONSISTENT: {len(violations)} critical violations detected!"
    
    return is_consistent, all_issues, report

def main():
    try:
        # Input von stdin lesen
        input_data = json.loads(sys.stdin.read())
        session_id = input_data.get("session_id", "unknown")
        
        # Context laden
        context = load_context()
        
        # Konsistenz-Validierung
        is_consistent, issues, report = perform_consistency_validation(context)
        
        # Logging
        log_event("VALIDATION", f"Session consistency check", {
            "session_id": session_id,
            "consistent": is_consistent,
            "issues_count": len(issues),
            "issues": issues[:5]  # Nur erste 5 für Log
        })
        
        # Bei Inkonsistenzen: Prüfe auf Force-Close Option
        if not is_consistent:
            # Prüfe auf Force-Close Flag in Context
            if context.get("force_close_requested", False) or context.get("session_clean", False):
                log_event("FORCE_CLOSE", "Session forced to close despite violations", {
                    "violations": len([i for i in issues if "CRITICAL" in i]),
                    "acknowledged": len(context.get("historical_violations_acknowledged", []))
                })
                print(f"⚠️ FORCED SESSION CLOSE: Violations acknowledged and bypassed")
                print(report)
            else:
                # Biete Force-Close Option an
                force_close_hint = "\n\n🔧 FORCE CLOSE OPTIONS:\n" + \
                                 "1. Set 'force_close_requested': true in task_context.json\n" + \
                                 "2. Use: /home/rodemkay/.claude/hooks/clear_violations.sh reset\n" + \
                                 "3. Acknowledge violations manually in context file"
                
                output = {
                    "decision": "block",
                    "reason": f"🚨 SESSION CONSISTENCY VIOLATIONS DETECTED:\n\n{report}{force_close_hint}"
                }
                print(json.dumps(output))
                log_event("BLOCK", f"Session stop blocked due to consistency violations", {
                    "violations": len([i for i in issues if "CRITICAL" in i])
                })
                sys.exit(0)
        
        # Session ist konsistent
        print(f"✅ Hook System Consistency Check: PASSED")
        print(report)
        
        # Context zurücksetzen für nächste Session
        context = {
            "active_todos": [],
            "last_task_completed": datetime.now().isoformat(),
            "hook_violations": 0,
            "task_completed_triggered": False
        }
        
        with open(CONTEXT_FILE, 'w') as f:
            json.dump(context, f, indent=2)
        
        log_event("SUCCESS", "Session consistency validation passed", {
            "session_id": session_id
        })
        sys.exit(0)
        
    except json.JSONDecodeError as e:
        log_event("ERROR", f"Invalid JSON input: {e}")
        sys.exit(1)
    except Exception as e:
        log_event("ERROR", f"Unexpected validation error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()