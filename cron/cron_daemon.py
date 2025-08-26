#!/usr/bin/env python3
"""
TODO Cron-Daemon für automatische Aufgabenausführung
Überwacht Cron-Jobs in der WordPress-Datenbank und führt sie automatisch aus
"""

import sys
import os
import time
import json
import logging
import threading
import signal
from datetime import datetime, timedelta
from pathlib import Path
import subprocess
import requests
import mysql.connector
from typing import Dict, List, Optional, Tuple
from croniter import croniter
import daemon
from daemon import pidfile

# Pfad-Konfiguration
PROJECT_ROOT = Path(__file__).parent.parent
LOG_DIR = PROJECT_ROOT / "cron" / "logs"
PID_FILE = PROJECT_ROOT / "cron" / "cron_daemon.pid"
CONFIG_FILE = PROJECT_ROOT / "cron" / "daemon_config.json"

# Logging-Setup
LOG_DIR.mkdir(exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(LOG_DIR / f"cron_daemon_{datetime.now().strftime('%Y%m%d')}.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class CronDaemon:
    """Hauptklasse für den Cron-Daemon"""
    
    def __init__(self):
        self.running = True
        self.check_interval = 60  # Sekunden
        self.webhook_url = "http://100.89.207.122:8089/webhook"
        self.db_config = self.load_db_config()
        self.processed_jobs = set()  # Verhindert doppelte Ausführung
        
    def load_db_config(self) -> Dict:
        """Lädt die Datenbank-Konfiguration"""
        try:
            env_file = PROJECT_ROOT / ".env"
            if not env_file.exists():
                env_file = Path("/home/rodemkay/www/react/.env")
            
            if env_file.exists():
                with open(env_file) as f:
                    env_content = f.read()
                
                # Extrahiere DB-Credentials aus .env
                config = {}
                for line in env_content.split('\n'):
                    if '=' in line and not line.startswith('#'):
                        key, value = line.split('=', 1)
                        config[key.strip()] = value.strip('"\'')
                
                return {
                    'host': config.get('HETZNER_HOST', '100.67.210.46'),
                    'user': config.get('HETZNER_DB_USER', 'ForexSignale'), 
                    'password': config.get('HETZNER_DB_PASS', ''),
                    'database': 'staging_forexsignale',
                    'port': 3306
                }
        except Exception as e:
            logger.error(f"Fehler beim Laden der DB-Config: {e}")
        
        # Fallback-Konfiguration
        return {
            'host': '100.67.210.46',
            'user': 'ForexSignale',
            'password': '.Foret333doka?',
            'database': 'staging_forexsignale', 
            'port': 3306
        }
    
    def get_database_connection(self):
        """Stellt eine Datenbankverbindung her"""
        try:
            connection = mysql.connector.connect(**self.db_config)
            return connection
        except mysql.connector.Error as e:
            logger.error(f"Datenbankverbindung fehlgeschlagen: {e}")
            return None
    
    def get_due_cron_jobs(self) -> List[Dict]:
        """Holt alle fälligen Cron-Jobs aus der Datenbank"""
        connection = self.get_database_connection()
        if not connection:
            return []
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Suche nach Cron-Jobs die ausgeführt werden müssen
            query = """
            SELECT * FROM stage_project_todos 
            WHERE cron_schedule IS NOT NULL 
            AND cron_schedule != '' 
            AND cron_schedule != 'none'
            AND status != 'abgeschlossen'
            AND (next_execution IS NULL OR next_execution <= NOW())
            ORDER BY priority DESC, created_at ASC
            """
            
            cursor.execute(query)
            jobs = cursor.fetchall()
            
            logger.info(f"Gefunden: {len(jobs)} fällige Cron-Jobs")
            return jobs
            
        except mysql.connector.Error as e:
            logger.error(f"Fehler beim Abrufen der Cron-Jobs: {e}")
            return []
        finally:
            if connection.is_connected():
                connection.close()
    
    def calculate_next_execution(self, cron_expression: str) -> Optional[datetime]:
        """Berechnet die nächste Ausführungszeit basierend auf Cron-Expression"""
        try:
            cron = croniter(cron_expression, datetime.now())
            return cron.get_next(datetime)
        except Exception as e:
            logger.error(f"Fehler beim Berechnen der nächsten Ausführung für '{cron_expression}': {e}")
            return None
    
    def create_job_copy(self, original_job: Dict) -> Optional[int]:
        """Erstellt eine Kopie des Cron-Jobs zur Ausführung"""
        connection = self.get_database_connection()
        if not connection:
            return None
        
        try:
            cursor = connection.cursor()
            
            # Kopie erstellen mit automatischem Claude-Toggle
            insert_query = """
            INSERT INTO stage_project_todos 
            (title, description, priority, arbeitsverzeichnis, projekt, 
             entwicklungsbereich, status, bearbeiten, created_at, 
             parent_cron_id, is_cron_copy)
            VALUES (%s, %s, %s, %s, %s, %s, 'offen', 1, NOW(), %s, 1)
            """
            
            # Titel mit Zeitstempel für Eindeutigkeit
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M")
            copy_title = f"[CRON] {original_job['title']} - {timestamp}"
            
            values = (
                copy_title,
                original_job.get('description', ''),
                original_job.get('priority', 5),
                original_job.get('arbeitsverzeichnis', '/home/rodemkay/www/react/plugin-todo'),
                original_job.get('projekt', 'Todo System'),
                original_job.get('entwicklungsbereich', 'Backend'),
                original_job['id']  # parent_cron_id
            )
            
            cursor.execute(insert_query, values)
            copy_id = cursor.lastrowid
            connection.commit()
            
            logger.info(f"Cron-Job-Kopie erstellt: #{copy_id} von Original #{original_job['id']}")
            return copy_id
            
        except mysql.connector.Error as e:
            logger.error(f"Fehler beim Erstellen der Job-Kopie: {e}")
            return None
        finally:
            if connection.is_connected():
                connection.close()
    
    def update_next_execution(self, job_id: int, next_execution: datetime):
        """Updated die nächste Ausführungszeit für einen Cron-Job"""
        connection = self.get_database_connection()
        if not connection:
            return
        
        try:
            cursor = connection.cursor()
            
            update_query = """
            UPDATE stage_project_todos 
            SET next_execution = %s, last_execution = NOW()
            WHERE id = %s
            """
            
            cursor.execute(update_query, (next_execution, job_id))
            connection.commit()
            
            logger.info(f"Nächste Ausführung für Job #{job_id} geplant: {next_execution}")
            
        except mysql.connector.Error as e:
            logger.error(f"Fehler beim Update der Ausführungszeit: {e}")
        finally:
            if connection.is_connected():
                connection.close()
    
    def send_webhook(self, job_copy_id: int) -> bool:
        """Sendet Webhook an Claude CLI"""
        try:
            payload = {
                'command': f'./todo -id {job_copy_id}',
                'source': 'cron_daemon',
                'timestamp': datetime.now().isoformat(),
                'job_id': job_copy_id
            }
            
            response = requests.post(
                self.webhook_url,
                json=payload,
                timeout=30,
                headers={'Content-Type': 'application/json'}
            )
            
            if response.status_code == 200:
                logger.info(f"Webhook erfolgreich gesendet für Job #{job_copy_id}")
                return True
            else:
                logger.error(f"Webhook-Fehler {response.status_code}: {response.text}")
                return False
                
        except requests.exceptions.RequestException as e:
            logger.error(f"Webhook-Request fehlgeschlagen: {e}")
            return False
    
    def process_cron_job(self, job: Dict):
        """Verarbeitet einen einzelnen Cron-Job"""
        job_id = job['id']
        job_key = f"{job_id}_{job['cron_schedule']}"
        
        # Verhindere doppelte Ausführung innerhalb derselben Minute
        if job_key in self.processed_jobs:
            return
        
        logger.info(f"Verarbeite Cron-Job #{job_id}: {job['title']}")
        
        # 1. Kopie erstellen
        copy_id = self.create_job_copy(job)
        if not copy_id:
            logger.error(f"Konnte keine Kopie für Job #{job_id} erstellen")
            return
        
        # 2. Webhook senden
        webhook_success = self.send_webhook(copy_id)
        if not webhook_success:
            logger.warning(f"Webhook für Job #{copy_id} fehlgeschlagen")
        
        # 3. Nächste Ausführung berechnen
        next_execution = self.calculate_next_execution(job['cron_schedule'])
        if next_execution:
            self.update_next_execution(job_id, next_execution)
        else:
            logger.error(f"Konnte nächste Ausführung für Job #{job_id} nicht berechnen")
        
        # 4. Als verarbeitet markieren
        self.processed_jobs.add(job_key)
    
    def cleanup_processed_jobs(self):
        """Bereinigt die processed_jobs Liste alle 10 Minuten"""
        # Entferne Einträge die älter als 10 Minuten sind
        # (vereinfacht: alle 10 Zyklen komplett leeren)
        if hasattr(self, '_cleanup_counter'):
            self._cleanup_counter += 1
        else:
            self._cleanup_counter = 1
        
        if self._cleanup_counter >= 10:  # Alle 10 * 60s = 10 Minuten
            self.processed_jobs.clear()
            self._cleanup_counter = 0
            logger.info("processed_jobs Liste bereinigt")
    
    def run_daemon_cycle(self):
        """Ein einzelner Daemon-Zyklus"""
        try:
            # 1. Fällige Jobs holen
            due_jobs = self.get_due_cron_jobs()
            
            if due_jobs:
                logger.info(f"Verarbeite {len(due_jobs)} fällige Cron-Jobs")
                
                for job in due_jobs:
                    try:
                        self.process_cron_job(job)
                        # Kurze Pause zwischen Jobs
                        time.sleep(5)
                    except Exception as e:
                        logger.error(f"Fehler bei Job #{job['id']}: {e}")
                        continue
            else:
                logger.debug("Keine fälligen Cron-Jobs gefunden")
            
            # 2. Cleanup
            self.cleanup_processed_jobs()
            
        except Exception as e:
            logger.error(f"Fehler im Daemon-Zyklus: {e}")
    
    def signal_handler(self, signum, frame):
        """Signal-Handler für graceful shutdown"""
        logger.info(f"Signal {signum} empfangen - Daemon wird beendet...")
        self.running = False
    
    def run(self):
        """Haupt-Daemon-Loop"""
        logger.info("Cron-Daemon gestartet")
        logger.info(f"Check-Intervall: {self.check_interval} Sekunden")
        logger.info(f"Webhook-URL: {self.webhook_url}")
        logger.info(f"Datenbank: {self.db_config['host']}:{self.db_config['port']}")
        
        # Signal-Handler registrieren
        signal.signal(signal.SIGTERM, self.signal_handler)
        signal.signal(signal.SIGINT, self.signal_handler)
        
        while self.running:
            try:
                self.run_daemon_cycle()
                
                # Warten bis zum nächsten Check
                for i in range(self.check_interval):
                    if not self.running:
                        break
                    time.sleep(1)
                    
            except KeyboardInterrupt:
                logger.info("Daemon durch Benutzer unterbrochen")
                break
            except Exception as e:
                logger.error(f"Unerwarteter Fehler im Daemon: {e}")
                time.sleep(10)  # Kurze Pause bei Fehlern
        
        logger.info("Cron-Daemon beendet")

def main():
    """Hauptfunktion"""
    daemon = CronDaemon()
    
    if len(sys.argv) > 1 and sys.argv[1] == '--daemon':
        # Als System-Daemon laufen
        with daemon.DaemonContext(
            pidfile=pidfile.TimeoutPIDLockFile(str(PID_FILE)),
            working_directory=str(PROJECT_ROOT / "cron"),
        ):
            daemon.run()
    else:
        # Im Vordergrund laufen (für Development/Testing)
        daemon.run()

if __name__ == "__main__":
    main()