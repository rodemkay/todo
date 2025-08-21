#!/usr/bin/env python3
"""
Planning Mode Handler for Todo System
Handles plan-only, execute, and hybrid modes
"""

import json
import sys
from pathlib import Path
from datetime import datetime

def generate_plan_html(todo):
    """Generiere HTML-Plan für ein Todo"""
    
    mode = todo.get('mode', 'execute')
    title = todo.get('title', 'Untitled Task')
    description = todo.get('description', '')
    priority = todo.get('priority', 'mittel')
    scope = todo.get('scope', '')
    
    # Erstelle formatiertes HTML
    html = f"""<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Implementierungsplan: {title}</title>
    <style>
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }}
        .container {{
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }}
        .header {{
            border-bottom: 3px solid #2271b1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }}
        h1 {{
            color: #2271b1;
            margin: 0 0 10px 0;
        }}
        .meta {{
            display: flex;
            gap: 20px;
            color: #6c757d;
            font-size: 14px;
        }}
        .section {{
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }}
        .section h2 {{
            color: #135e96;
            margin-top: 0;
        }}
        .mode-badge {{
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-left: 10px;
        }}
        .mode-plan {{ background: #ffc107; color: #000; }}
        .mode-execute {{ background: #28a745; color: white; }}
        .mode-hybrid {{ background: linear-gradient(90deg, #ffc107 0%, #28a745 100%); color: white; }}
        .requirements {{
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
        }}
        .approach {{
            background: #f3e5f5;
            border-left: 4px solid #9c27b0;
            padding: 15px;
            margin: 20px 0;
        }}
        .steps {{
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }}
        .risks {{
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
        }}
        .action-buttons {{
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
        }}
        .btn {{
            padding: 10px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }}
        .btn-approve {{
            background: #28a745;
            color: white;
        }}
        .btn-modify {{
            background: #ffc107;
            color: #000;
        }}
        .btn:hover {{
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }}
        .timestamp {{
            color: #6c757d;
            font-size: 12px;
            font-style: italic;
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Implementierungsplan: {title}
                <span class="mode-badge mode-{mode}">{mode.upper()}</span>
            </h1>
            <div class="meta">
                <span>📅 Erstellt: {datetime.now().strftime('%d.%m.%Y %H:%M')}</span>
                <span>🎯 Priorität: {priority}</span>
                <span>📁 Projekt: {scope}</span>
            </div>
        </div>
        
        <div class="section">
            <h2>📝 Aufgabenbeschreibung</h2>
            <p>{description}</p>
        </div>
        
        <div class="requirements">
            <h2>📌 Anforderungsanalyse</h2>
            <p><em>Dieser Abschnitt wird von Claude ausgefüllt...</em></p>
            <ul>
                <li>Requirement 1</li>
                <li>Requirement 2</li>
                <li>Requirement 3</li>
            </ul>
        </div>
        
        <div class="approach">
            <h2>🎯 Lösungsansatz</h2>
            <p><em>Claude wird hier den optimalen Lösungsweg beschreiben...</em></p>
        </div>
        
        <div class="steps">
            <h2>🔨 Implementierungsschritte</h2>
            <ol>
                <li>Schritt 1: Vorbereitung</li>
                <li>Schritt 2: Implementation</li>
                <li>Schritt 3: Testing</li>
                <li>Schritt 4: Dokumentation</li>
            </ol>
        </div>
        
        <div class="risks">
            <h2>⚠️ Potenzielle Risiken & Herausforderungen</h2>
            <p><em>Claude wird mögliche Probleme identifizieren...</em></p>
        </div>
        
        <div class="section">
            <h2>⏱️ Zeitschätzung</h2>
            <p><em>Geschätzte Bearbeitungszeit wird von Claude ermittelt...</em></p>
        </div>
"""
    
    # Footer basierend auf Mode
    if mode == 'plan':
        html += """
        <div class="action-buttons">
            <p><strong>Dieser Plan wartet auf Ihre Freigabe zur Ausführung.</strong></p>
            <button class="btn btn-approve" onclick="approvePlan()">✅ Plan freigeben & ausführen</button>
            <button class="btn btn-modify" onclick="modifyPlan()">📝 Plan anpassen</button>
        </div>
"""
    elif mode == 'hybrid':
        html += """
        <div class="action-buttons">
            <p><strong>Phase 1: Planung abgeschlossen. Bereit für Phase 2: Ausführung.</strong></p>
            <button class="btn btn-approve" onclick="startExecution()">▶️ Mit Ausführung fortfahren</button>
        </div>
"""
    
    html += """
        <div class="timestamp">
            <p>Generiert am: """ + datetime.now().strftime('%d.%m.%Y um %H:%M:%S Uhr') + """</p>
        </div>
    </div>
</body>
</html>"""
    
    return html

def get_mode_instruction(todo):
    """Generiere Mode-spezifische Anweisungen für Claude"""
    
    mode = todo.get('mode', 'execute')
    title = todo.get('title', '')
    description = todo.get('description', '')
    
    if mode == 'plan':
        return f"""
╔════════════════════════════════════════════════════════════════╗
║                    🟡 PLANNING MODE AKTIV 🟡                   ║
╠════════════════════════════════════════════════════════════════╣
║ WICHTIG: NUR PLANEN - KEINE AUSFÜHRUNG!                       ║
╚════════════════════════════════════════════════════════════════╝

Aufgabe: {title}
Beschreibung: {description}

DEINE AUFGABE:
1. Analysiere die Anforderungen genau
2. Erstelle einen detaillierten Implementierungsplan
3. Identifiziere mögliche Probleme und Lösungen
4. Schätze den Zeitaufwand
5. FÜHRE NICHTS AUS - nur planen!

Erstelle den Plan als formatiertes HTML und speichere ihn direkt in der Datenbank.
Verwende das bereitgestellte HTML-Template und fülle alle Abschnitte aus.
"""
    
    elif mode == 'hybrid':
        if not todo.get('plan_approved', False):
            # Phase 1: Planung
            return f"""
╔════════════════════════════════════════════════════════════════╗
║                  🔵 HYBRID MODE - PHASE 1 🔵                   ║
║                         PLANUNG                                ║
╠════════════════════════════════════════════════════════════════╣
║ Erstelle einen Plan, der später ausgeführt wird               ║
╚════════════════════════════════════════════════════════════════╝

Aufgabe: {title}

Erstelle einen detaillierten Plan wie im PLAN mode.
Nach Freigabe folgt die Ausführung in Phase 2.
"""
        else:
            # Phase 2: Ausführung
            return f"""
╔════════════════════════════════════════════════════════════════╗
║                  🔵 HYBRID MODE - PHASE 2 🔵                   ║
║                        AUSFÜHRUNG                              ║
╠════════════════════════════════════════════════════════════════╣
║ Plan wurde freigegeben - jetzt ausführen!                     ║
╚════════════════════════════════════════════════════════════════╝

Aufgabe: {title}

Der Plan wurde genehmigt. Führe jetzt die geplanten Schritte aus.
Dokumentiere die Umsetzung und eventuelle Abweichungen vom Plan.
"""
    
    else:  # execute mode (default)
        return f"""
╔════════════════════════════════════════════════════════════════╗
║                  🟢 EXECUTE MODE AKTIV 🟢                      ║
║                   DIREKTE AUSFÜHRUNG                           ║
╠════════════════════════════════════════════════════════════════╣
║ Führe die Aufgabe direkt aus                                  ║
╚════════════════════════════════════════════════════════════════╝

Aufgabe: {title}
Beschreibung: {description}

Führe die Aufgabe wie gewohnt aus und dokumentiere das Ergebnis.
"""

if __name__ == "__main__":
    # Test mit Beispiel-Todo
    test_todo = {
        'id': 999,
        'title': 'Test Planning Mode',
        'description': 'Dies ist ein Test für den Planning Mode',
        'mode': 'plan',
        'priority': 'hoch',
        'scope': 'Todo Plugin'
    }
    
    print("Generating plan HTML...")
    html = generate_plan_html(test_todo)
    
    # Speichere Test-HTML
    with open('/tmp/test_plan.html', 'w') as f:
        f.write(html)
    
    print(f"✅ Test HTML saved to /tmp/test_plan.html")
    print("\nMode Instructions:")
    print(get_mode_instruction(test_todo))