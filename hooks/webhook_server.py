#!/usr/bin/env python3
"""
Webhook Server für Todo Plugin
Empfängt HTTP-Requests und führt CLI-Befehle aus
"""

import json
import os
import subprocess
import sys
import time
from datetime import datetime
from http.server import BaseHTTPRequestHandler, HTTPServer
from socketserver import ThreadingMixIn
from threading import Thread
import signal
import logging

# Logging-Konfiguration
LOG_FILE = '/tmp/webhook.log'
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class WebhookHandler(BaseHTTPRequestHandler):
    
    def do_GET(self):
        """Health Check Endpoint"""
        if self.path == '/health':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            
            response = {
                'status': 'running',
                'server': 'webhook_server',
                'timestamp': datetime.now().isoformat(),
                'uptime': time.time() - server_start_time
            }
            
            self.wfile.write(json.dumps(response).encode())
            logger.info(f"Health check from {self.client_address[0]}")
        else:
            self.send_response(404)
            self.end_headers()
    
    def do_POST(self):
        """Webhook Endpoint"""
        if self.path == '/webhook':
            try:
                # Content-Length prüfen
                content_length = int(self.headers.get('Content-Length', 0))
                if content_length > 10000:  # Max 10KB
                    self.send_error(413, "Request too large")
                    return
                
                # Payload lesen
                post_data = self.rfile.read(content_length)
                
                try:
                    payload = json.loads(post_data.decode('utf-8'))
                except json.JSONDecodeError:
                    self.send_error(400, "Invalid JSON")
                    return
                
                logger.info(f"Webhook received from {self.client_address[0]}: {payload}")
                
                # Command aus Payload extrahieren
                command = payload.get('command', '')
                source = payload.get('source', 'unknown')
                
                if not command:
                    # Fallback für leere Commands - Standard "./todo"
                    command = './todo'
                
                # Command validieren (Sicherheit)
                if not self.is_safe_command(command):
                    self.send_error(400, "Unsafe command")
                    logger.warning(f"Unsafe command rejected: {command}")
                    return
                
                # Success Response senden BEVOR Command ausgeführt wird
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                
                response = {
                    'success': True,
                    'message': 'Command queued for execution',
                    'command': command,
                    'timestamp': datetime.now().isoformat()
                }
                
                self.wfile.write(json.dumps(response).encode())
                
                # Command in separatem Thread ausführen
                thread = Thread(target=self.execute_command_async, args=(command, source))
                thread.daemon = True
                thread.start()
                
            except Exception as e:
                logger.error(f"Webhook error: {e}")
                self.send_error(500, f"Internal error: {str(e)}")
        else:
            self.send_error(404, "Not found")
    
    def is_safe_command(self, command):
        """Prüft ob Command sicher ist"""
        # Nur erlaubte Commands
        allowed_patterns = [
            './todo',
            './todo -id',
            './todo complete',
            './todo status',
            './todo help'
        ]
        
        # Gefährliche Zeichen
        dangerous_chars = [';', '|', '&', '>', '<', '`', '$', '(', ')']
        
        if any(char in command for char in dangerous_chars):
            return False
        
        # Muss mit erlaubtem Pattern beginnen
        return any(command.startswith(pattern) for pattern in allowed_patterns)
    
    def execute_command_async(self, command, source):
        """Führt Command asynchron aus"""
        try:
            # Working Directory setzen
            work_dir = '/home/rodemkay/www/react/plugin-todo'
            
            logger.info(f"Executing command: {command} (source: {source})")
            
            # Command ausführen
            process = subprocess.Popen(
                command,
                shell=True,
                cwd=work_dir,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True
            )
            
            # Warte auf Completion
            stdout, stderr = process.communicate(timeout=300)  # 5 min timeout
            
            if process.returncode == 0:
                logger.info(f"Command executed successfully: {command}")
                if stdout.strip():
                    logger.info(f"Command output: {stdout.strip()}")
            else:
                logger.error(f"Command failed: {command}, Return code: {process.returncode}")
                if stderr.strip():
                    logger.error(f"Command error: {stderr.strip()}")
            
        except subprocess.TimeoutExpired:
            logger.error(f"Command timeout: {command}")
            process.kill()
        except Exception as e:
            logger.error(f"Command execution error: {e}")
    
    def log_message(self, format, *args):
        """Überschreibt Standard-Logging"""
        # Verwende unseren Logger statt stderr
        logger.info(f"{self.client_address[0]} - {format % args}")


class ThreadedHTTPServer(ThreadingMixIn, HTTPServer):
    """Thread-basierter HTTP Server"""
    allow_reuse_address = True


def signal_handler(sig, frame):
    """Graceful Shutdown"""
    logger.info("Webhook server shutting down...")
    if 'server' in globals():
        server.shutdown()
    sys.exit(0)


def main():
    global server_start_time, server
    
    # Server-Konfiguration
    HOST = '0.0.0.0'  # Alle Interfaces
    PORT = 8089
    
    server_start_time = time.time()
    
    # Signal Handler registrieren
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Server starten
    try:
        server = ThreadedHTTPServer((HOST, PORT), WebhookHandler)
        
        logger.info(f"Webhook server starting on {HOST}:{PORT}")
        logger.info(f"Health check: http://{HOST}:{PORT}/health")
        logger.info(f"Webhook endpoint: http://{HOST}:{PORT}/webhook")
        logger.info(f"Process ID: {os.getpid()}")
        
        # Server läuft permanent
        server.serve_forever()
        
    except OSError as e:
        if e.errno == 98:  # Address already in use
            logger.error(f"Port {PORT} already in use")
            sys.exit(1)
        else:
            raise
    except KeyboardInterrupt:
        logger.info("Server interrupted by user")
    except Exception as e:
        logger.error(f"Server error: {e}")
        sys.exit(1)
    finally:
        if 'server' in globals():
            server.shutdown()
        logger.info("Webhook server stopped")


if __name__ == '__main__':
    main()