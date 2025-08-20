#!/usr/bin/env php
<?php
/**
 * Todo Button Handler - Lokales Script für WordPress Button
 * Wird direkt vom Hetzner Server aufgerufen
 */

// Definiere den tmux Befehl
$command = './todo';
$tmux_session = 'claude:0';

// Führe den tmux send-keys Befehl aus
$tmux_cmd = sprintf(
    'tmux send-keys -t %s "%s" Enter 2>&1',
    escapeshellarg($tmux_session),
    escapeshellarg($command)
);

// Führe den Befehl aus
exec($tmux_cmd, $output, $return_code);

// Gib JSON Response zurück
$response = [
    'success' => ($return_code === 0),
    'command' => $command,
    'timestamp' => date('Y-m-d H:i:s'),
    'return_code' => $return_code
];

// Wenn Fehler, füge Output hinzu
if ($return_code !== 0) {
    $response['error'] = implode("\n", $output);
}

// Output als JSON
header('Content-Type: application/json');
echo json_encode($response);