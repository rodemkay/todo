<?php
/**
 * SSH Handler using phpseclib v3
 * 
 * Handles SSH connections to RyzenServer for sending tmux commands
 * 
 * @package WP_Project_Todos
 */

// Autoloader für phpseclib
require_once dirname(__DIR__) . '/vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class WP_Todo_SSH_Handler {
    
    /**
     * SSH Connection settings
     */
    private $host = '100.89.207.122';
    private $port = 22;
    private $user = 'rodemkay';
    private $keyPath = '/var/www/.ssh/id_rsa';
    private $timeout = 5;
    
    /**
     * Send ./todo command to Claude via tmux
     * 
     * @return array Result with success status and output
     */
    public function sendTodoCommand() {
        return $this->sendCommand('./todo');
    }
    
    /**
     * Send custom command to Claude via tmux
     * 
     * @param string $command Command to send
     * @return array Result with success status and output
     */
    public function sendCommand($command = './todo') {
        try {
            // SSH-Verbindung herstellen
            $ssh = $this->connect();
            
            // tmux Befehl formatieren
            $tmuxCommand = sprintf(
                'tmux send-keys -t claude:0 "%s" Enter',
                addslashes($command)
            );
            
            // Befehl ausführen
            $output = $ssh->exec($tmuxCommand);
            
            // Zusätzlich Status prüfen
            $status = $ssh->exec('tmux has-session -t claude 2>&1 && echo "RUNNING" || echo "STOPPED"');
            
            return [
                'success' => true,
                'command' => $command,
                'output' => trim($output),
                'status' => trim($status),
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => 'phpseclib3'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $command,
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => 'phpseclib3'
            ];
        }
    }
    
    /**
     * Check Claude status
     * 
     * @return array Status information
     */
    public function checkStatus() {
        try {
            $ssh = $this->connect();
            
            // Prüfe ob tmux Session läuft
            $sessionCheck = $ssh->exec('tmux has-session -t claude 2>&1 && echo "RUNNING" || echo "STOPPED"');
            
            // Hole letzte Zeilen aus tmux
            $lastOutput = '';
            if (strpos($sessionCheck, 'RUNNING') !== false) {
                $lastOutput = $ssh->exec('tmux capture-pane -t claude:0 -p | tail -5');
            }
            
            return [
                'success' => true,
                'status' => trim($sessionCheck),
                'last_output' => $lastOutput,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Establish SSH connection
     * 
     * @return SSH2 SSH connection object
     * @throws Exception on connection failure
     */
    private function connect() {
        // Prüfe ob Key-Datei existiert
        if (!file_exists($this->keyPath)) {
            throw new \Exception('SSH Key not found: ' . $this->keyPath);
        }
        
        // Private Key laden
        $keyContent = file_get_contents($this->keyPath);
        if ($keyContent === false) {
            throw new \Exception('Cannot read SSH key: ' . $this->keyPath);
        }
        
        try {
            $key = PublicKeyLoader::load($keyContent);
        } catch (\Exception $e) {
            throw new \Exception('Invalid SSH key format: ' . $e->getMessage());
        }
        
        // SSH-Verbindung herstellen
        $ssh = new SSH2($this->host, $this->port);
        $ssh->setTimeout($this->timeout);
        
        // Mit Key authentifizieren
        if (!$ssh->login($this->user, $key)) {
            // Fallback: Versuche nochmal mit expliziter Key-Type-Angabe
            $key = PublicKeyLoader::load($keyContent, false); // ohne Passwort
            if (!$ssh->login($this->user, $key)) {
                throw new \Exception('SSH authentication failed for ' . $this->user . '@' . $this->host);
            }
        }
        
        return $ssh;
    }
    
    /**
     * Test SSH connection
     * 
     * @return array Test results
     */
    public function testConnection() {
        try {
            $ssh = $this->connect();
            
            // Einfacher Test-Befehl
            $hostname = $ssh->exec('hostname');
            $whoami = $ssh->exec('whoami');
            $pwd = $ssh->exec('pwd');
            
            return [
                'success' => true,
                'hostname' => trim($hostname),
                'user' => trim($whoami),
                'directory' => trim($pwd),
                'message' => 'SSH connection successful',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
}