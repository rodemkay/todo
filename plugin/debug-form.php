<?php
// Debug-Script für Form-Daten
if (!defined('ABSPATH')) {
    exit;
}

// Log alle POST-Daten
if (!empty($_POST)) {
    $debug_data = [
        'timestamp' => current_time('mysql'),
        'action' => $_POST['action'] ?? 'no-action',
        'title' => $_POST['title'] ?? 'NO TITLE',
        'description' => $_POST['description'] ?? 'NO DESCRIPTION',
        'description_length' => strlen($_POST['description'] ?? ''),
        'all_post_keys' => array_keys($_POST)
    ];
    
    // Schreibe in eine Debug-Datei
    $debug_file = WP_CONTENT_DIR . '/todo-form-debug.log';
    file_put_contents($debug_file, date('Y-m-d H:i:s') . ' - ' . json_encode($debug_data) . "\n", FILE_APPEND);
}