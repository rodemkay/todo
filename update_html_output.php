#!/usr/bin/env php
<?php
// Script to update HTML output in database

$html_file = '/home/rodemkay/www/react/todo/docs/CLAUDE_CONTROL_FIX_PLAN.html';
$html_content = file_get_contents($html_file);

// Escape for MySQL
$escaped_html = addslashes($html_content);

// SSH command to update database
$ssh_command = "ssh rodemkay@100.67.210.46 \"cd /var/www/forexsignale/staging && wp db query \\\"UPDATE stage_project_todos SET claude_html_output = '" . $escaped_html . "' WHERE id = 224\\\"\"";

// Execute
exec($ssh_command, $output, $return_code);

if ($return_code === 0) {
    echo "✅ HTML-Output für Todo #224 erfolgreich gespeichert\n";
} else {
    echo "❌ Fehler beim Speichern: " . implode("\n", $output) . "\n";
    
    // Alternative: Save as base64
    $base64_html = base64_encode($html_content);
    $alt_command = "ssh rodemkay@100.67.210.46 \"cd /var/www/forexsignale/staging && wp db query \\\"UPDATE stage_project_todos SET claude_html_output = '" . $base64_html . "' WHERE id = 224\\\"\"";
    exec($alt_command, $output2, $return_code2);
    
    if ($return_code2 === 0) {
        echo "✅ HTML als Base64 gespeichert\n";
    }
}
?>