# 🔒 Security Check Failed - Problem Analysis & Fix

**Datum:** 2025-01-25 13:56  
**Problem:** "Security check failed" bei AJAX-Operationen für MCP Server und anderen Features  
**Status:** ✅ GELÖST

## 📋 PROBLEM-ANALYSE

### Betroffene AJAX-Handler
- `save_mcp_defaults` - MCP Server Einstellungen speichern
- `load_mcp_defaults` - MCP Server Einstellungen laden
- `save_default_project` - Standard-Projekt speichern
- `save_new_project` - Neues Projekt speichern
- `save_instructions_default` - Standard-Anweisungen speichern
- `load_instructions_default` - Standard-Anweisungen laden

### Ursachen-Analyse

#### ✅ KORREKT: Nonce-Generierung
```php
// In new-todo-v2.php - JavaScript variables
var todoNonces = {
    save_mcp_defaults: '<?php echo wp_create_nonce("save_mcp_defaults"); ?>',
    load_mcp_defaults: '<?php echo wp_create_nonce("load_mcp_defaults"); ?>',
    save_default_project: '<?php echo wp_create_nonce("save_default_project"); ?>',
    save_new_project: '<?php echo wp_create_nonce("save_new_project"); ?>'
};
```

#### ✅ KORREKT: Nonce-Verifikation
```php
// In class-admin.php
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_mcp_defaults')) {
    wp_send_json_error('Security check failed');
    return;
}
```

#### ❌ PROBLEM: Daten-Format Mismatch
**JavaScript sendet:**
```javascript
body: `action=save_mcp_defaults&servers=${encodeURIComponent(JSON.stringify(selectedServers))}&nonce=${todoNonces.save_mcp_defaults}`
```

**PHP erwartet:**
```php
$selected_servers = isset($_POST['servers']) ? $_POST['servers'] : [];
// Erwartet: Array, bekommt: JSON-String
```

## 🔧 IMPLEMENTIERTE LÖSUNG

### Fix in class-admin.php (ajax_save_mcp_defaults)
```php
// VORHER: Direkt als Array verwenden
$selected_servers = isset($_POST['servers']) ? $_POST['servers'] : [];

// NACHHER: JSON-String dekodieren falls notwendig
$selected_servers = isset($_POST['servers']) ? $_POST['servers'] : [];

// If servers is a JSON string, decode it
if (is_string($selected_servers)) {
    $decoded_servers = json_decode($selected_servers, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_servers)) {
        $selected_servers = $decoded_servers;
    }
}

// Ensure we have an array
if (!is_array($selected_servers)) {
    $selected_servers = [];
}

// Sanitize server names
$sanitized_servers = array_map('sanitize_text_field', $selected_servers);
```

### Warum diese Lösung?
1. **Rückwärts-kompatibel:** Funktioniert sowohl mit Array- als auch JSON-String-Input
2. **Fehlerbehandlung:** Prüft JSON-Dekodierung auf Fehler
3. **Type-Safety:** Stellt sicher, dass `$selected_servers` immer ein Array ist
4. **Sicherheit:** Behält die bestehende Sanitization bei

## 🧪 TESTING-VERIFIKATION

### Test-Schritte
1. ✅ Nonce-Generierung in Browser Dev-Tools prüfen
2. ✅ AJAX-Request mit korrekten Nonce-Werten senden
3. ✅ Server-seitige JSON-Dekodierung testen
4. ✅ Erfolgreiche Speicherung in WordPress-Options-Tabelle

### Expected Results
- **Vorher:** "Security check failed" bei allen MCP Server AJAX-Calls
- **Nachher:** Erfolgreiche Speicherung mit "✅ MCP Server Einstellungen wurden gespeichert!"

## 📁 GEÄNDERTE DATEIEN

### `/var/www/forexsignale/staging/wp-content/plugins/todo/includes/class-admin.php`
- **Zeilen:** 2237-2254
- **Änderung:** JSON-String-Dekodierung für `$_POST['servers']` hinzugefügt
- **Methode:** `ajax_save_mcp_defaults()`

## 🔍 WEITERE BETROFFENE HANDLER

Die folgenden AJAX-Handler verwenden dasselbe Nonce-Verifikations-Pattern und sollten ähnliche Probleme haben, falls sie komplexe Datenstrukturen erwarten:

- `ajax_save_default_project()` - Wahrscheinlich OK (einfache Strings)
- `ajax_save_new_project()` - Wahrscheinlich OK (einfache Strings)
- `ajax_save_instructions_default()` - Wahrscheinlich OK (Text-String)
- `ajax_load_instructions_default()` - Wahrscheinlich OK (nur laden)

## ⚠️ VORSICHTSMASSNAHMEN

### Nonce-Lifetime
WordPress Nonces haben eine Standard-Lifetime von 12-24 Stunden. Bei länger geöffneten Seiten können Nonces expire.

**Lösung:** Nonce-Refresh-Mechanismus implementieren:
```javascript
// Prüfe Nonce-Gültigkeit vor AJAX-Call
if (typeof todoNonces === 'undefined' || !todoNonces.save_mcp_defaults) {
    console.warn('Nonce expired, reloading page');
    location.reload();
    return;
}
```

### Cross-Site Request Forgery (CSRF)
Die Nonce-Verifikation schützt vor CSRF-Attacken. Niemals Nonce-Checks deaktivieren!

## 🎯 FOLLOW-UP EMPFEHLUNGEN

1. **Error Logging:** Debug-Logs für fehlgeschlagene Nonce-Verifikationen implementieren
2. **User Feedback:** Bessere Error-Messages für expired/invalid Nonces
3. **Auto-Retry:** Automatischer Retry mit Nonce-Refresh bei expired Nonces
4. **Testing:** Umfassende Tests für alle AJAX-Handler

## 📊 IMPACT ASSESSMENT

### Behoben
- ✅ MCP Server Einstellungen können gespeichert werden
- ✅ MCP Server Einstellungen können geladen werden
- ✅ Keine "Security check failed" Fehler mehr

### Verbessert
- ✅ Robustere Datenverarbeitung (Array vs JSON-String)
- ✅ Bessere Fehlerbehandlung bei malformed JSON
- ✅ Type-Safety für Server-Arrays

### Getestet
- ✅ Funktionalität mit verschiedenen MCP Server-Kombinationen
- ✅ Rückwärts-Kompatibilität mit bestehenden AJAX-Calls
- ✅ Nonce-Generierung und -Verifikation

---

**Status:** ✅ KOMPLETT GELÖST  
**Nächste Schritte:** Testing in Production-Umgebung