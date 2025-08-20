<?php
/**
 * SSH2 Extension Handler für direkte Terminal-Kommunikation
 * Primäre Methode für WordPress → Claude CLI Kommunikation
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class SSH2_Handler {
    
    private $connection = null;
    private $host = '100.89.207.122';  // Tailscale IP
    private $port = 22;
    private $username = 'rodemkay';
    private $timeout = 10;
    
    /**
     * Verbinde mit RyzenServer via SSH2
     */
    public function connect() {
        if (!function_exists('ssh2_connect')) {
            return ['success' => false, 'error' => 'SSH2 Extension nicht verfügbar'];
        }
        
        // Bestehende Verbindung wiederverwenden
        if ($this->connection && ssh2_exec($this->connection, 'echo test')) {
            return ['success' => true, 'message' => 'Bestehende Verbindung wiederverwendet'];
        }
        
        $this->connection = ssh2_connect($this->host, $this->port, [
            'hostkey' => 'ssh-rsa,ssh-dss',
            'kex' => 'diffie-hellman-group1-sha1,diffie-hellman-group14-sha1',
            'client_to_server' => [
                'crypt' => '3des-cbc,blowfish-cbc',
                'mac' => 'hmac-sha1',
                'comp' => 'none'
            ],
            'server_to_client' => [
                'crypt' => '3des-cbc,blowfish-cbc', 
                'mac' => 'hmac-sha1',
                'comp' => 'none'
            ]
        ]);
        
        if (!$this->connection) {
            return ['success' => false, 'error' => 'Verbindung zu RyzenServer fehlgeschlagen'];
        }
        
        // SSH Key Authentication (bevorzugt)
        $ssh_key_private = '/var/www/.ssh/id_rsa';
        if (file_exists($ssh_key_private)) {
            $auth_result = ssh2_auth_pubkey_file(
                $this->connection, 
                $this->username, 
                $ssh_key_private . '.pub', 
                $ssh_key_private
            );
        } else {
            // Fallback: Password Authentication (aus .env)
            $password = defined('HETZNER_SSH_PASS') ? HETZNER_SSH_PASS : '.Zynit333doka?';
            $auth_result = ssh2_auth_password($this->connection, $this->username, $password);
        }
        
        if (!$auth_result) {
            return ['success' => false, 'error' => 'SSH Authentifizierung fehlgeschlagen'];
        }
        
        return ['success' => true, 'message' => 'SSH2 Verbindung erfolgreich'];
    }
    
    /**
     * Sende Befehl direkt ins tmux Terminal
     */
    public function send_to_terminal($command) {
        $connect_result = $this->connect();
        if (!$connect_result['success']) {
            return $connect_result;
        }
        
        // tmux send-keys für direktes Terminal-Input
        $tmux_command = sprintf(
            'tmux send-keys -t claude:0 %s Enter',
            escapeshellarg($command)
        );
        
        $stream = ssh2_exec($this->connection, $tmux_command);
        if (!$stream) {
            return ['success' => false, 'error' => 'tmux send-keys fehlgeschlagen'];
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        return [
            'success' => true,
            'message' => "Befehl '$command' wurde an Claude Terminal gesendet",
            'output' => $output,
            'method' => 'ssh2_tmux',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Hole tmux Terminal Output
     */
    public function get_terminal_output() {
        $connect_result = $this->connect();
        if (!$connect_result['success']) {
            return $connect_result;
        }
        
        // tmux capture-pane für letzten Output
        $capture_command = 'tmux capture-pane -t claude:0 -p';
        
        $stream = ssh2_exec($this->connection, $capture_command);
        if (!$stream) {
            return ['success' => false, 'error' => 'tmux capture-pane fehlgeschlagen'];
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        return [
            'success' => true,
            'output' => $output,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Prüfe Claude tmux Session Status
     */
    public function check_claude_status() {
        $connect_result = $this->connect();
        if (!$connect_result['success']) {
            return $connect_result;
        }
        
        // tmux list-sessions für Status-Check
        $status_command = 'tmux list-sessions | grep claude';
        
        $stream = ssh2_exec($this->connection, $status_command);
        if (!$stream) {
            return ['success' => false, 'status' => 'offline', 'error' => 'tmux list-sessions fehlgeschlagen'];
        }
        
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        
        if (strpos($output, 'claude:') !== false) {
            return [
                'success' => true,
                'status' => 'online',
                'message' => 'Claude tmux session läuft',
                'session_info' => trim($output)
            ];
        } else {
            return [
                'success' => true,
                'status' => 'offline', 
                'message' => 'Claude tmux session nicht gefunden',
                'output' => $output
            ];
        }
    }
    
    /**
     * Verbindung schließen
     */
    public function disconnect() {
        if ($this->connection) {
            ssh2_disconnect($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * Destruktor - Verbindung automatisch schließen
     */
    public function __destruct() {
        $this->disconnect();
    }
}