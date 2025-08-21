#!/usr/bin/env python3
"""
========================================
WEBHOOK QUEUE MANAGEMENT SYSTEM
========================================
High-Performance Request Queue für Multi-Trigger Handling
- Priority-basierte Queue
- Rate Limiting
- Batch Processing
- Load Balancing
- Retry Mechanism
"""

import os
import sys
import time
import json
import sqlite3
import threading
import subprocess
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple, Any
from dataclasses import dataclass, asdict
from queue import Queue, PriorityQueue, Empty
from collections import defaultdict
import hashlib
import signal

@dataclass
class QueueTask:
    task_id: str
    command: str
    priority: int  # 1=highest, 10=lowest
    created_at: float
    max_retries: int = 3
    retry_count: int = 0
    estimated_duration: float = 2.0  # seconds
    category: str = "default"
    
    def __lt__(self, other):
        # Priority queue comparison (lower number = higher priority)
        if self.priority != other.priority:
            return self.priority < other.priority
        return self.created_at < other.created_at

@dataclass
class QueueStats:
    total_processed: int = 0
    successful: int = 0
    failed: int = 0
    retries: int = 0
    current_queue_size: int = 0
    avg_processing_time: float = 0.0
    peak_queue_size: int = 0
    rate_limited: int = 0

class WebhookQueueManager:
    def __init__(self, config_path: str = None):
        self.config_path = config_path or "/home/rodemkay/www/react/todo/hooks/config.json"
        self.db_path = "/tmp/webhook_queue.db"
        self.log_file = "/tmp/webhook_queue.log"
        
        # Queue Configuration
        self.max_queue_size = 1000
        self.max_concurrent_workers = 3
        self.rate_limit_per_second = 10
        self.batch_size = 5
        self.processing_timeout = 30.0
        
        # Queues
        self.priority_queue = PriorityQueue()
        self.processing_queue = Queue()
        self.dead_letter_queue = Queue()
        
        # Workers
        self.workers = []
        self.running = False
        self.worker_threads = []
        
        # Rate Limiting
        self.rate_limiter = defaultdict(list)  # category -> timestamps
        
        # Statistics
        self.stats = QueueStats()
        self.performance_history = defaultdict(list)
        
        # Load configuration
        self.load_config()
        self.init_database()
        
        # Signal handlers
        signal.signal(signal.SIGINT, self.shutdown_handler)
        signal.signal(signal.SIGTERM, self.shutdown_handler)
    
    def load_config(self):
        """Load queue configuration"""
        try:
            if os.path.exists(self.config_path):
                with open(self.config_path, 'r') as f:
                    config = json.load(f)
                    
                queue_config = config.get('queue', {})
                self.max_queue_size = queue_config.get('max_queue_size', 1000)
                self.max_concurrent_workers = queue_config.get('max_concurrent_workers', 3)
                self.rate_limit_per_second = queue_config.get('rate_limit_per_second', 10)
                self.batch_size = queue_config.get('batch_size', 5)
                self.processing_timeout = queue_config.get('processing_timeout', 30.0)
                
            self.log("Queue configuration loaded", "INFO")
        except Exception as e:
            self.log(f"Failed to load config: {e}", "ERROR")
    
    def init_database(self):
        """Initialize SQLite database for queue persistence"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            # Queue tasks table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS queue_tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    task_id TEXT UNIQUE,
                    command TEXT,
                    priority INTEGER,
                    created_at REAL,
                    max_retries INTEGER,
                    retry_count INTEGER,
                    estimated_duration REAL,
                    category TEXT,
                    status TEXT DEFAULT 'queued',
                    started_at REAL,
                    completed_at REAL,
                    error_message TEXT
                )
            ''')
            
            # Queue statistics table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS queue_stats (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp REAL,
                    total_processed INTEGER,
                    successful INTEGER,
                    failed INTEGER,
                    retries INTEGER,
                    current_queue_size INTEGER,
                    avg_processing_time REAL,
                    peak_queue_size INTEGER,
                    rate_limited INTEGER
                )
            ''')
            
            # Performance metrics table
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS performance_metrics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    task_id TEXT,
                    category TEXT,
                    processing_time REAL,
                    queue_wait_time REAL,
                    worker_id TEXT,
                    timestamp REAL
                )
            ''')
            
            conn.commit()
            conn.close()
            
            # Restore queue from database on startup
            self.restore_queue_from_db()
            
            self.log("Queue database initialized", "INFO")
        except Exception as e:
            self.log(f"Database initialization failed: {e}", "ERROR")
    
    def log(self, message: str, level: str = "INFO"):
        """Thread-safe logging"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        thread_id = threading.current_thread().name
        log_entry = f"{timestamp} [{level}] [{thread_id}] {message}"
        
        print(log_entry)
        
        try:
            with open(self.log_file, 'a') as f:
                f.write(log_entry + "\\n")
        except Exception:
            pass
    
    def generate_task_id(self, command: str, priority: int) -> str:
        """Generate unique task ID"""
        timestamp = str(time.time())
        content = f"{command}_{priority}_{timestamp}"
        return hashlib.md5(content.encode()).hexdigest()[:12]
    
    def add_task(self, command: str, priority: int = 5, category: str = "default", 
                estimated_duration: float = 2.0, max_retries: int = 3) -> str:
        """Add task to queue"""
        try:
            # Check queue size limit
            if self.priority_queue.qsize() >= self.max_queue_size:
                self.log(f"Queue full, rejecting task: {command[:50]}...", "WARNING")
                self.stats.rate_limited += 1
                return None
            
            # Rate limiting check
            if not self.check_rate_limit(category):
                self.log(f"Rate limited task: {command[:50]}...", "WARNING")
                self.stats.rate_limited += 1
                return None
            
            # Create task
            task_id = self.generate_task_id(command, priority)
            task = QueueTask(
                task_id=task_id,
                command=command,
                priority=priority,
                created_at=time.time(),
                max_retries=max_retries,
                estimated_duration=estimated_duration,
                category=category
            )
            
            # Add to queue
            self.priority_queue.put(task)
            
            # Persist to database
            self.persist_task(task, "queued")
            
            # Update statistics
            current_size = self.priority_queue.qsize()
            if current_size > self.stats.peak_queue_size:
                self.stats.peak_queue_size = current_size
            
            self.log(f"Task queued: {task_id} - {command[:50]}... (Priority: {priority})", "INFO")
            return task_id
            
        except Exception as e:
            self.log(f"Failed to add task: {e}", "ERROR")
            return None
    
    def check_rate_limit(self, category: str) -> bool:
        """Check if category is within rate limit"""
        current_time = time.time()
        
        # Clean old timestamps (older than 1 second)
        self.rate_limiter[category] = [
            ts for ts in self.rate_limiter[category] 
            if current_time - ts < 1.0
        ]
        
        # Check current rate
        if len(self.rate_limiter[category]) >= self.rate_limit_per_second:
            return False
        
        # Add current timestamp
        self.rate_limiter[category].append(current_time)
        return True
    
    def persist_task(self, task: QueueTask, status: str):
        """Persist task to database"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT OR REPLACE INTO queue_tasks 
                (task_id, command, priority, created_at, max_retries, retry_count,
                 estimated_duration, category, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                task.task_id, task.command, task.priority, task.created_at,
                task.max_retries, task.retry_count, task.estimated_duration,
                task.category, status
            ))
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to persist task: {e}", "ERROR")
    
    def restore_queue_from_db(self):
        """Restore unfinished tasks from database"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                SELECT task_id, command, priority, created_at, max_retries,
                       retry_count, estimated_duration, category
                FROM queue_tasks 
                WHERE status IN ('queued', 'processing')
                ORDER BY priority ASC, created_at ASC
            ''')
            
            restored_count = 0
            for row in cursor.fetchall():
                task = QueueTask(
                    task_id=row[0],
                    command=row[1],
                    priority=row[2],
                    created_at=row[3],
                    max_retries=row[4],
                    retry_count=row[5],
                    estimated_duration=row[6],
                    category=row[7]
                )
                
                self.priority_queue.put(task)
                restored_count += 1
            
            conn.close()
            
            if restored_count > 0:
                self.log(f"Restored {restored_count} tasks from database", "INFO")
                
        except Exception as e:
            self.log(f"Failed to restore queue: {e}", "ERROR")
    
    def worker_thread(self, worker_id: str):
        """Worker thread for processing tasks"""
        self.log(f"Worker {worker_id} started", "INFO")
        
        while self.running:
            try:
                # Get task from queue (timeout to allow shutdown)
                try:
                    task = self.priority_queue.get(timeout=1.0)
                except Empty:
                    continue
                
                # Process task
                self.process_task(task, worker_id)
                
                # Mark task as done
                self.priority_queue.task_done()
                
            except Exception as e:
                self.log(f"Worker {worker_id} error: {e}", "ERROR")
        
        self.log(f"Worker {worker_id} stopped", "INFO")
    
    def process_task(self, task: QueueTask, worker_id: str):
        """Process a single task"""
        start_time = time.time()
        queue_wait_time = start_time - task.created_at
        
        self.log(f"Processing task {task.task_id}: {task.command[:50]}...", "INFO")
        
        # Update task status
        self.persist_task_update(task.task_id, "processing", start_time)
        
        success = False
        error_message = None
        
        try:
            # Execute command via tmux
            result = self.execute_command(task.command)
            
            if result:
                success = True
                self.stats.successful += 1
                self.log(f"Task {task.task_id} completed successfully", "INFO")
            else:
                raise Exception("Command execution failed")
                
        except Exception as e:
            error_message = str(e)
            self.log(f"Task {task.task_id} failed: {e}", "ERROR")
            
            # Retry logic
            if task.retry_count < task.max_retries:
                task.retry_count += 1
                self.stats.retries += 1
                
                # Re-queue with lower priority (higher number)
                task.priority = min(10, task.priority + 1)
                
                self.log(f"Retrying task {task.task_id} (attempt {task.retry_count})", "INFO")
                self.priority_queue.put(task)
                self.persist_task(task, "queued")
                return
            else:
                # Max retries reached, move to dead letter queue
                self.dead_letter_queue.put(task)
                self.stats.failed += 1
                self.log(f"Task {task.task_id} moved to dead letter queue", "ERROR")
        
        # Update statistics
        processing_time = time.time() - start_time
        self.stats.total_processed += 1
        
        # Update average processing time
        total_processed = self.stats.total_processed
        current_avg = self.stats.avg_processing_time
        self.stats.avg_processing_time = ((current_avg * (total_processed - 1)) + processing_time) / total_processed
        
        # Store performance metrics
        self.store_performance_metrics(task, processing_time, queue_wait_time, worker_id)
        
        # Update task status
        status = "completed" if success else "failed"
        self.persist_task_update(task.task_id, status, start_time, time.time(), error_message)
    
    def execute_command(self, command: str) -> bool:
        """Execute command via tmux"""
        try:
            # Check if tmux session exists
            check_result = subprocess.run(['tmux', 'has-session', '-t', 'claude'], 
                                        capture_output=True, text=True)
            
            if check_result.returncode != 0:
                raise Exception("tmux session 'claude' not found")
            
            # Send command to tmux session
            subprocess.run(['tmux', 'send-keys', '-t', 'claude:0', command], 
                         check=True, capture_output=True, text=True)
            subprocess.run(['tmux', 'send-keys', '-t', 'claude:0', 'Enter'], 
                         check=True, capture_output=True, text=True)
            
            # Small delay to ensure command is sent
            time.sleep(0.1)
            
            return True
            
        except Exception as e:
            self.log(f"Command execution failed: {e}", "ERROR")
            return False
    
    def persist_task_update(self, task_id: str, status: str, started_at: float = None, 
                          completed_at: float = None, error_message: str = None):
        """Update task status in database"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            update_fields = ["status = ?"]
            values = [status]
            
            if started_at:
                update_fields.append("started_at = ?")
                values.append(started_at)
            
            if completed_at:
                update_fields.append("completed_at = ?")
                values.append(completed_at)
            
            if error_message:
                update_fields.append("error_message = ?")
                values.append(error_message)
            
            values.append(task_id)
            
            cursor.execute(f'''
                UPDATE queue_tasks 
                SET {", ".join(update_fields)}
                WHERE task_id = ?
            ''', values)
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to update task status: {e}", "ERROR")
    
    def store_performance_metrics(self, task: QueueTask, processing_time: float, 
                                queue_wait_time: float, worker_id: str):
        """Store performance metrics"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO performance_metrics
                (task_id, category, processing_time, queue_wait_time, worker_id, timestamp)
                VALUES (?, ?, ?, ?, ?, ?)
            ''', (
                task.task_id, task.category, processing_time, 
                queue_wait_time, worker_id, time.time()
            ))
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to store performance metrics: {e}", "ERROR")
    
    def get_queue_status(self) -> Dict:
        """Get current queue status"""
        return {
            "queue_size": self.priority_queue.qsize(),
            "processing_queue_size": self.processing_queue.qsize(),
            "dead_letter_queue_size": self.dead_letter_queue.qsize(),
            "active_workers": len([t for t in self.worker_threads if t.is_alive()]),
            "stats": asdict(self.stats),
            "rate_limiter_status": {
                category: len(timestamps) 
                for category, timestamps in self.rate_limiter.items()
            }
        }
    
    def start_workers(self):
        """Start worker threads"""
        self.running = True
        
        for i in range(self.max_concurrent_workers):
            worker_id = f"worker-{i+1}"
            thread = threading.Thread(target=self.worker_thread, args=(worker_id,))
            thread.daemon = True
            thread.start()
            self.worker_threads.append(thread)
        
        self.log(f"Started {self.max_concurrent_workers} worker threads", "INFO")
    
    def stop_workers(self):
        """Stop worker threads"""
        self.running = False
        
        # Wait for workers to finish
        for thread in self.worker_threads:
            thread.join(timeout=5.0)
        
        self.log("All workers stopped", "INFO")
    
    def start_monitoring(self):
        """Start queue monitoring thread"""
        def monitor():
            while self.running:
                try:
                    # Update current queue size
                    self.stats.current_queue_size = self.priority_queue.qsize()
                    
                    # Store statistics
                    self.store_queue_stats()
                    
                    # Log status every 30 seconds
                    if int(time.time()) % 30 == 0:
                        status = self.get_queue_status()
                        self.log(f"Queue Status - Size: {status['queue_size']}, "
                               f"Workers: {status['active_workers']}, "
                               f"Processed: {status['stats']['total_processed']}, "
                               f"Success Rate: {self.calculate_success_rate():.1f}%", "STATUS")
                    
                    time.sleep(5.0)  # Monitor every 5 seconds
                    
                except Exception as e:
                    self.log(f"Monitoring error: {e}", "ERROR")
                    time.sleep(10.0)
        
        monitor_thread = threading.Thread(target=monitor)
        monitor_thread.daemon = True
        monitor_thread.start()
        
        self.log("Queue monitoring started", "INFO")
    
    def store_queue_stats(self):
        """Store queue statistics to database"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO queue_stats
                (timestamp, total_processed, successful, failed, retries,
                 current_queue_size, avg_processing_time, peak_queue_size, rate_limited)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                time.time(),
                self.stats.total_processed,
                self.stats.successful,
                self.stats.failed,
                self.stats.retries,
                self.stats.current_queue_size,
                self.stats.avg_processing_time,
                self.stats.peak_queue_size,
                self.stats.rate_limited
            ))
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to store queue stats: {e}", "ERROR")
    
    def calculate_success_rate(self) -> float:
        """Calculate success rate percentage"""
        total = self.stats.successful + self.stats.failed
        return (self.stats.successful / total * 100) if total > 0 else 100.0
    
    def shutdown_handler(self, signum, frame):
        """Handle shutdown signals"""
        self.log(f"Received shutdown signal {signum}", "INFO")
        self.shutdown()
    
    def shutdown(self):
        """Graceful shutdown"""
        self.log("Starting graceful shutdown...", "INFO")
        
        # Stop accepting new tasks
        self.running = False
        
        # Wait for current tasks to complete
        self.log("Waiting for current tasks to complete...", "INFO")
        
        try:
            # Wait for queue to empty (with timeout)
            start_time = time.time()
            while not self.priority_queue.empty() and (time.time() - start_time) < 30:
                time.sleep(1)
            
            # Stop workers
            self.stop_workers()
            
            # Store final statistics
            self.store_queue_stats()
            
            self.log("Shutdown complete", "INFO")
            
        except Exception as e:
            self.log(f"Shutdown error: {e}", "ERROR")

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Webhook Queue Manager')
    parser.add_argument('--config', type=str, help='Path to config file')
    parser.add_argument('--workers', type=int, default=3, help='Number of worker threads')
    parser.add_argument('--add-task', type=str, help='Add a single task to queue')
    parser.add_argument('--priority', type=int, default=5, help='Task priority (1-10)')
    parser.add_argument('--category', type=str, default='default', help='Task category')
    parser.add_argument('--status', action='store_true', help='Show queue status')
    
    args = parser.parse_args()
    
    # Create queue manager
    queue_manager = WebhookQueueManager(config_path=args.config)
    queue_manager.max_concurrent_workers = args.workers
    
    try:
        if args.add_task:
            # Add single task
            task_id = queue_manager.add_task(
                command=args.add_task,
                priority=args.priority,
                category=args.category
            )
            if task_id:
                print(f"Task added: {task_id}")
            else:
                print("Failed to add task")
                sys.exit(1)
        
        elif args.status:
            # Show status
            status = queue_manager.get_queue_status()
            print(json.dumps(status, indent=2))
        
        else:
            # Start queue processing
            queue_manager.start_workers()
            queue_manager.start_monitoring()
            
            print("Queue manager started. Press Ctrl+C to stop.")
            
            try:
                # Keep main thread alive
                while queue_manager.running:
                    time.sleep(1)
            except KeyboardInterrupt:
                print("\\nShutdown requested...")
            finally:
                queue_manager.shutdown()
    
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()