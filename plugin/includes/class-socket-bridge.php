<?php
/**
 * Socket Bridge für bidirektionale Kommunikation
 * Ermöglicht Live-Terminal-Updates im WordPress Dashboard
 *
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Socket_Bridge {
    
    private $socket_host = '100.89.207.122';
    private $socket_port = 8899;
    private $timeout = 5;
    
    /**
     * Erstelle Socket-Verbindung zum RyzenServer
     */
    public function create_connection() {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return ['success' => false, 'error' => 'Socket creation failed'];
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        
        $result = @socket_connect($socket, $this->socket_host, $this->socket_port);
        if (!$result) {
            socket_close($socket);
            return ['success' => false, 'error' => 'Socket connection failed'];
        }
        
        return ['success' => true, 'socket' => $socket];
    }
    
    /**
     * Sende Befehl via Socket
     */
    public function send_command($command) {
        $connection = $this->create_connection();
        if (!$connection['success']) {
            return $connection;
        }
        
        $socket = $connection['socket'];
        
        $message = json_encode([
            'type' => 'command',
            'command' => $command,
            'timestamp' => time(),
            'source' => 'wordpress'
        ]);
        
        $result = @socket_write($socket, $message . "\n", strlen($message . "\n"));
        if ($result === false) {
            socket_close($socket);
            return ['success' => false, 'error' => 'Failed to send command'];
        }
        
        // Warte auf Antwort
        $response = @socket_read($socket, 1024);
        socket_close($socket);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'No response received'];
        }
        
        return [
            'success' => true,
            'message' => "Command '$command' sent via socket",
            'response' => trim($response),
            'method' => 'socket'
        ];
    }
    
    /**
     * Hole Live-Output via Socket
     */
    public function get_live_output() {
        $connection = $this->create_connection();
        if (!$connection['success']) {
            return $connection;
        }
        
        $socket = $connection['socket'];
        
        $request = json_encode([
            'type' => 'get_output',
            'timestamp' => time()
        ]);
        
        @socket_write($socket, $request . "\n", strlen($request . "\n"));
        
        $output = @socket_read($socket, 4096);
        socket_close($socket);
        
        if ($output === false) {
            return ['success' => false, 'error' => 'Failed to get output'];
        }
        
        return [
            'success' => true,
            'output' => trim($output),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Prüfe Socket-Server Status
     */
    public function check_server_status() {
        $connection = $this->create_connection();
        if (!$connection['success']) {
            return [
                'success' => false,
                'status' => 'offline',
                'error' => $connection['error']
            ];
        }
        
        $socket = $connection['socket'];
        
        $ping = json_encode(['type' => 'ping', 'timestamp' => time()]);
        @socket_write($socket, $ping . "\n", strlen($ping . "\n"));
        
        $response = @socket_read($socket, 256);
        socket_close($socket);
        
        if ($response && strpos($response, 'pong') !== false) {
            return [
                'success' => true,
                'status' => 'online',
                'message' => 'Socket server is running'
            ];
        }
        
        return [
            'success' => false,
            'status' => 'offline',
            'error' => 'Socket server not responding'
        ];
    }
}

/**
 * Socket Server Script für RyzenServer
 * Muss als separater Service laufen: python3 socket_server.py
 */
function generate_socket_server_script() {
    return '#!/usr/bin/env python3
"""
Socket Server für WordPress ↔ Claude CLI Kommunikation
Läuft auf RyzenServer und ermöglicht bidirektionale Kommunikation
"""

import socket
import json
import subprocess
import threading
import time
import sys
import signal

class ClaudeSocketServer:
    def __init__(self, host="0.0.0.0", port=8899):
        self.host = host
        self.port = port
        self.running = False
        self.clients = []
        
    def start_server(self):
        self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        
        try:
            self.socket.bind((self.host, self.port))
            self.socket.listen(5)
            self.running = True
            
            print(f"Claude Socket Server listening on {self.host}:{self.port}")
            
            while self.running:
                try:
                    client_socket, address = self.socket.accept()
                    print(f"Connection from {address}")
                    
                    client_thread = threading.Thread(
                        target=self.handle_client,
                        args=(client_socket, address)
                    )
                    client_thread.daemon = True
                    client_thread.start()
                    
                except socket.error as e:
                    if self.running:
                        print(f"Socket error: {e}")
                        
        except Exception as e:
            print(f"Server error: {e}")
        finally:
            self.socket.close()
    
    def handle_client(self, client_socket, address):
        try:
            while True:
                data = client_socket.recv(1024)
                if not data:
                    break
                    
                try:
                    message = json.loads(data.decode().strip())
                    response = self.process_message(message)
                    
                    client_socket.send((json.dumps(response) + "\n").encode())
                    
                except json.JSONDecodeError:
                    error_response = {"error": "Invalid JSON"}
                    client_socket.send((json.dumps(error_response) + "\n").encode())
                    
        except Exception as e:
            print(f"Client error: {e}")
        finally:
            client_socket.close()
    
    def process_message(self, message):
        msg_type = message.get("type")
        
        if msg_type == "ping":
            return {"type": "pong", "timestamp": time.time()}
            
        elif msg_type == "command":
            command = message.get("command", "")
            return self.execute_tmux_command(command)
            
        elif msg_type == "get_output":
            return self.get_tmux_output()
            
        else:
            return {"error": "Unknown message type"}
    
    def execute_tmux_command(self, command):
        try:
            # Sende Befehl an tmux session
            tmux_cmd = [
                "tmux", "send-keys", "-t", "claude:0",
                command, "Enter"
            ]
            
            result = subprocess.run(
                tmux_cmd,
                capture_output=True,
                text=True,
                timeout=10
            )
            
            return {
                "success": result.returncode == 0,
                "command": command,
                "output": result.stdout,
                "error": result.stderr if result.stderr else None,
                "timestamp": time.time()
            }
            
        except subprocess.TimeoutExpired:
            return {
                "success": False,
                "error": "Command timeout",
                "command": command
            }
        except Exception as e:
            return {
                "success": False,
                "error": str(e),
                "command": command
            }
    
    def get_tmux_output(self):
        try:
            # Hole tmux pane output
            result = subprocess.run(
                ["tmux", "capture-pane", "-t", "claude:0", "-p"],
                capture_output=True,
                text=True,
                timeout=5
            )
            
            return {
                "success": result.returncode == 0,
                "output": result.stdout,
                "error": result.stderr if result.stderr else None,
                "timestamp": time.time()
            }
            
        except Exception as e:
            return {
                "success": False,
                "error": str(e)
            }
    
    def stop_server(self):
        self.running = False
        if hasattr(self, "socket"):
            self.socket.close()

def signal_handler(sig, frame):
    print("\nShutting down server...")
    server.stop_server()
    sys.exit(0)

if __name__ == "__main__":
    server = ClaudeSocketServer()
    
    # Handle Ctrl+C gracefully
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    try:
        server.start_server()
    except KeyboardInterrupt:
        print("\nServer interrupted")
    finally:
        server.stop_server()
';
}