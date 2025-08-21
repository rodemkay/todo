# GitHub Push Anleitung

## Repository Status
- **Lokale Commits:** ✅ Erfolgreich erstellt
- **Remote Push:** ⚠️ Manueller Push erforderlich

## Letzter Commit
```
commit 7848957
Author: rodemkay
Date: 2025-08-20

Fix WordPress TODO plugin webhook integration
- Switched from trigger file mechanism to webhook server (port 9999)
- Buttons now work natively without manual JavaScript execution
- Loop button sends ./todo command via webhook
- Send to Claude button sends ./todo -id [ID] via webhook
- Both handlers properly integrated with tmux session 'claude:0'
- Successfully tested webhook endpoints and command execution
```

## Manuelle Push-Anleitung

### Option 1: Mit GitHub Personal Access Token
1. Gehe zu https://github.com/settings/tokens
2. Erstelle einen neuen Personal Access Token
3. Führe aus:
```bash
git remote set-url origin https://rodemkay:[TOKEN]@github.com/rodemkay/todo.git
git push origin main
```

### Option 2: Mit SSH-Key
1. Füge deinen SSH-Key zu GitHub hinzu:
```bash
cat ~/.ssh/id_rsa_github.pub
```
2. Kopiere den Output und füge ihn bei https://github.com/settings/keys hinzu
3. Pushe mit:
```bash
git push origin main
```

### Option 3: Neues Repository erstellen
1. Gehe zu https://github.com/new
2. Erstelle Repository "todo"
3. Folge den Anweisungen für "push an existing repository"

## Aktuelle Remote-URL
```
git@github.com:rodemkay/todo.git
```

## Lokaler Status
Alle Änderungen sind committed und bereit zum Push.