# TODO #363: Anhang-Upload Analyse

## Problem
Der Anhang wurde beim Erstellen von TODO #363 nicht gespeichert.

## Analyse-Ergebnisse

### Datenbank-Status
- `attachment_count`: 0
- `stage_todo_attachments`: Keine Einträge für TODO #363
- Upload-Verzeichnis `/todo-attachments/363/`: Existiert nicht

### Code-Verifizierung
✅ `Todo_Attachment_Handler` Klasse existiert und wird geladen
✅ Debug-Logging ist implementiert
✅ Form hat `enctype="multipart/form-data"`
✅ Input-Felder haben korrektes `name="attachments[]"`

### Vermutete Ursache
Das $_FILES Array ist beim Form-Submit leer. Mögliche Gründe:
1. JavaScript preventDefault() verhindert normales Form-Submit
2. AJAX-Submit überträgt keine Dateien
3. Browser-seitige Validierung schlägt fehl

## Empfohlene Lösung

### 1. Debug-Output aktivieren
Füge temporär in new-todo-v2.php Zeile 180 ein:
```php
echo "<pre>FILES: " . print_r($_FILES, true) . "</pre>";
echo "<pre>POST: " . print_r($_POST, true) . "</pre>";
die();
```

### 2. JavaScript-Submit prüfen
Suche nach event.preventDefault() im Form-Submit Handler und stelle sicher, dass bei Datei-Uploads das normale Submit erlaubt wird.

### 3. Alternative: FormData für AJAX
Wenn AJAX verwendet wird, muss FormData mit processData: false und contentType: false verwendet werden:
```javascript
var formData = new FormData($('#new-todo-form')[0]);
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function(response) {
        // Handle response
    }
});
```

## Test-Antwort zu "Was zeigt das Bild?"
Da der Anhang nicht hochgeladen wurde, kann ich das Bild nicht analysieren. Nach Behebung des Upload-Problems kann das Bild erneut hochgeladen und analysiert werden.