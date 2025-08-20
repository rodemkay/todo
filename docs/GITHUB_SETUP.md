# 🔗 GITHUB REPOSITORY SETUP

## 📍 AKTUELLER STATUS

Das TODO Projekt ist lokal vollständig vorbereitet und bereit für GitHub:
- ✅ Git Repository initialisiert
- ✅ Alle Dateien committed (2 Commits)
- ✅ Branch umbenannt zu `main`
- ✅ Remote URL konfiguriert

## 🚀 GITHUB REPOSITORY ERSTELLEN

### Option 1: Via Browser (Empfohlen)
1. Gehe zu https://github.com/new
2. Repository Name: `todo`
3. Beschreibung: "Task Management System with Claude Integration"
4. Private/Public nach Wahl
5. **WICHTIG:** KEINE README, .gitignore oder License hinzufügen!
6. Create Repository

### Option 2: GitHub CLI installieren
```bash
# Installation auf Ubuntu/Debian
curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
sudo apt update
sudo apt install gh

# Login
gh auth login

# Repository erstellen
gh repo create todo --private --source=. --remote=origin
```

### Option 3: Via API mit Personal Access Token
```bash
# Erstelle zuerst einen Personal Access Token auf GitHub:
# Settings → Developer settings → Personal access tokens → Generate new token
# Scopes: repo (full control)

# Dann:
curl -H "Authorization: token YOUR_TOKEN" \
     -d '{"name":"todo","private":true}' \
     https://api.github.com/user/repos
```

## 📤 CODE PUSHEN

Nach dem Erstellen des Repositories auf GitHub:

```bash
# Falls Remote noch nicht gesetzt
git remote add origin https://github.com/rodemkay/todo.git

# Push mit Credentials
git push -u origin main

# Bei Authentifizierungs-Fehler:
# Verwende Personal Access Token statt Passwort!
```

## 🔐 AUTHENTIFIZIERUNG

### Personal Access Token erstellen:
1. GitHub → Settings → Developer settings
2. Personal access tokens → Tokens (classic)
3. Generate new token
4. Scopes auswählen: `repo` (vollständig)
5. Token kopieren und sicher speichern

### Git mit Token konfigurieren:
```bash
# Methode 1: Bei Push eingeben
git push origin main
Username: rodemkay
Password: [DEIN_PERSONAL_ACCESS_TOKEN]

# Methode 2: In URL speichern (unsicher!)
git remote set-url origin https://rodemkay:TOKEN@github.com/rodemkay/todo.git

# Methode 3: Credential Helper (empfohlen)
git config --global credential.helper store
# Dann einmal mit Token pushen, wird gespeichert
```

## 📁 LOKALER STATUS

### Commits vorhanden:
```
11b3dfa - Add environment configuration and documentation
6eaf804 - Initial commit: TODO project setup
```

### Dateien:
- 130+ Dateien
- Vollständige Plugin-Migration
- Dokumentation
- Tests
- Deployment Scripts

### Remote konfiguriert:
```bash
origin  https://github.com/rodemkay/todo.git (push)
```

## ✅ NÄCHSTE SCHRITTE

1. **GitHub Repository erstellen** (manuell via Browser)
2. **Personal Access Token generieren** für Authentifizierung
3. **Code pushen** mit:
   ```bash
   git push -u origin main
   ```
4. **GitHub Actions einrichten** für CI/CD
5. **README auf GitHub anpassen** mit Badges etc.

## 🔄 ALTERNATIVE: LOKALES ARBEITEN

Falls GitHub-Push nicht sofort möglich:

```bash
# Weiter lokal arbeiten
git add .
git commit -m "message"

# Später alle Commits auf einmal pushen
git push origin main
```

## 📝 NOTIZEN

- Repository Name: `todo` (nicht wp-project-todos!)
- Alle sensitiven Daten in .env (nicht committed)
- .gitignore verhindert versehentliches Committen von Credentials
- Deploy-Script nutzt Tailscale IPs für bessere Performance

---

**Status:** Repository lokal bereit, wartet auf GitHub-Erstellung und Push  
**Datum:** 2025-08-20