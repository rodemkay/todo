# 📝 PLAN MODUS - Anleitung

## 🎯 Was ist der Plan-Modus?

Der Plan-Modus ist ein spezieller Ausführungsmodus, bei dem Claude:
- **NUR einen detaillierten Plan erstellt**
- **KEINE Änderungen durchführt**
- **HTML und Output generiert**
- **Alle Schritte dokumentiert**

## 📍 Wo finde ich den Plan-Modus?

### 1. **Im WordPress Admin:**
```
WordPress Admin → TODO → Todo bearbeiten → Ausführungsmodus-Sektion
```

### 2. **Position in der UI:**
- Nach den MCP-Server Checkboxen
- Vor dem Wiedervorlage-System
- Blaue Box mit dem Titel "🎯 Ausführungsmodus"

## 🔧 Wie aktiviere ich den Plan-Modus?

### Schritt 1: Todo bearbeiten
1. Gehe zu: `https://forexsignale.trade/staging/wp-admin/admin.php?page=todo`
2. Klicke auf "✏️ Bearbeiten" bei deinem Todo

### Schritt 2: Modus auswählen
In der **Ausführungsmodus-Sektion** findest du zwei Optionen:

#### 📝 **PLAN MODUS**
- Claude erstellt nur einen Plan
- KEINE Änderungen werden durchgeführt
- Perfekt für:
  - Komplexe Aufgaben planen
  - Risikoreiche Änderungen vorher durchdenken
  - Dokumentation erstellen

#### 🚀 **EXECUTE MODUS** (Standard)
- Claude führt Änderungen direkt aus
- Normaler Arbeitsmodus
- Sofortige Implementierung

### Schritt 3: Speichern
Klicke auf "📝 Änderungen speichern"

## 🔄 Plan-Modus Workflow

### 1. **Plan erstellen:**
```
Todo mit PLAN MODUS → An Claude senden → Plan wird erstellt
```

### 2. **Plan prüfen:**
- Claude erstellt detaillierten HTML-Output
- Alle Schritte sind dokumentiert
- KEINE Änderungen wurden durchgeführt

### 3. **Optional: Plan genehmigen:**
- Checkbox "✅ Plan genehmigt - Ausführung erlaubt"
- Modus auf EXECUTE ändern
- Erneut an Claude senden für Ausführung

## 💡 Anwendungsfälle

### Wann Plan-Modus verwenden?

1. **Große Refactorings:**
   - Erst Plan erstellen
   - Review durchführen
   - Dann ausführen

2. **Kritische Änderungen:**
   - Datenbank-Migrationen
   - Produktions-Deployments
   - Sicherheitsupdates

3. **Komplexe Features:**
   - Architektur planen
   - Abhängigkeiten identifizieren
   - Schritte dokumentieren

4. **Dokumentation:**
   - Implementierungsplan für Team
   - Technische Spezifikation
   - Aufwandsschätzung

## 🎨 UI-Elemente

### Mode Selector:
- **Radio Buttons** für Modus-Auswahl
- **Visuelle Hervorhebung** des aktiven Modus
- **Toast Notification** bei Änderung

### Plan Approval:
- Nur sichtbar im Plan-Modus
- Checkbox für Genehmigung
- Ermöglicht spätere Ausführung

## 📊 Datenbank-Felder

```sql
-- mode: 'plan' oder 'execute'
-- plan_approved: 0 oder 1

UPDATE stage_project_todos 
SET mode = 'plan', plan_approved = 0 
WHERE id = 251;
```

## 🔍 Im Hook-System

Der `planning_mode.py` Handler zeigt:
- Gelben Banner für PLAN MODUS
- Grünen Banner für EXECUTE MODUS
- Spezielle Anweisungen je nach Modus

## ⚡ Quick-Toggle via SQL

```bash
# Plan-Modus aktivieren
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
  wp db query 'UPDATE stage_project_todos SET mode=\"plan\" WHERE id=251'"

# Execute-Modus aktivieren  
ssh rodemkay@159.69.157.54 "cd /var/www/forexsignale/staging && \
  wp db query 'UPDATE stage_project_todos SET mode=\"execute\" WHERE id=251'"
```

## 🎯 Zusammenfassung

**Plan-Modus = Sicherheit + Dokumentation**
- Keine unerwarteten Änderungen
- Vollständige Planung vorab
- Review vor Ausführung möglich

**Execute-Modus = Geschwindigkeit + Effizienz**
- Sofortige Umsetzung
- Direktes Feedback
- Standard-Arbeitsweise

---

**Tipp:** Bei unsicheren oder kritischen Tasks IMMER erst Plan-Modus verwenden!

*Dokumentation für Todo #251*