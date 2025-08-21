#!/usr/bin/env python3
"""
========================================
WEBHOOK SYSTEM MONITORING DASHBOARD
========================================
Real-time Performance & Health Monitoring
- Live Metrics Collection
- Alert System
- Performance Analytics
- Health Status Dashboard
"""

import os
import sys
import time
import json
import psutil
import sqlite3
import subprocess
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, List, Optional, Tuple
import threading
from dataclasses import dataclass, asdict
from collections import deque, defaultdict

@dataclass
class WebhookMetrics:
    timestamp: float
    memory_usage_mb: float
    cpu_percent: float
    trigger_count: int
    response_time_ms: float
    error_count: int
    health_status: str
    active_connections: int

@dataclass
class AlertConfig:
    memory_threshold_mb: int = 50
    cpu_threshold_percent: float = 80.0
    response_time_threshold_ms: float = 1000.0
    error_rate_threshold: float = 0.05
    enabled: bool = True

class WebhookMonitor:
    def __init__(self, config_path: str = None):
        self.config_path = config_path or "/home/rodemkay/www/react/todo/hooks/config.json"
        self.metrics_db_path = "/tmp/webhook_metrics.db"
        self.log_files = {
            'trigger': '/tmp/claude_local_trigger.log',
            'performance': '/tmp/webhook_performance.log',
            'system': '/tmp/webhook_monitor.log'
        }
        
        # Performance Tracking
        self.metrics_history = deque(maxlen=1000)  # Last 1000 metrics
        self.alert_config = AlertConfig()
        self.running = False
        self.last_metrics = None
        
        # Error Tracking
        self.error_counts = defaultdict(int)
        self.performance_stats = {
            'avg_response_time': 0.0,
            'min_response_time': float('inf'),
            'max_response_time': 0.0,
            'total_requests': 0,
            'successful_requests': 0,
            'failed_requests': 0
        }
        
        self.init_database()
        self.load_config()
    
    def init_database(self):
        """Initialize SQLite database for metrics storage"""
        try:
            conn = sqlite3.connect(self.metrics_db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS webhook_metrics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp REAL,
                    memory_usage_mb REAL,
                    cpu_percent REAL,
                    trigger_count INTEGER,
                    response_time_ms REAL,
                    error_count INTEGER,
                    health_status TEXT,
                    active_connections INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS alerts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    alert_type TEXT,
                    message TEXT,
                    severity TEXT,
                    resolved BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            conn.commit()
            conn.close()
            self.log("Database initialized successfully", "INFO")
        except Exception as e:
            self.log(f"Database initialization failed: {e}", "ERROR")
    
    def load_config(self):
        """Load configuration from config.json"""
        try:
            if os.path.exists(self.config_path):
                with open(self.config_path, 'r') as f:
                    config = json.load(f)
                    
                monitoring_config = config.get('monitoring', {})
                if monitoring_config:
                    self.alert_config.memory_threshold_mb = monitoring_config.get('memory_threshold_mb', 50)
                    self.alert_config.cpu_threshold_percent = monitoring_config.get('cpu_threshold_percent', 80.0)
                    self.alert_config.enabled = monitoring_config.get('enabled', True)
                    
            self.log("Configuration loaded successfully", "INFO")
        except Exception as e:
            self.log(f"Config loading failed: {e}", "ERROR")
    
    def log(self, message: str, level: str = "INFO"):
        """Centralized logging"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_entry = f"{timestamp} [{level}] {message}"
        
        print(log_entry)  # Console output
        
        try:
            with open(self.log_files['system'], 'a') as f:
                f.write(log_entry + "\\n")
        except Exception:
            pass  # Don't fail on logging issues
    
    def get_process_info(self) -> Optional[psutil.Process]:
        """Get webhook watch script process"""
        try:
            # Find watch script process
            for proc in psutil.process_iter(['pid', 'cmdline']):
                if 'watch-local-trigger' in ' '.join(proc.info['cmdline'] or []):
                    return psutil.Process(proc.info['pid'])
        except Exception as e:
            self.log(f"Failed to get process info: {e}", "ERROR")
        return None
    
    def collect_metrics(self) -> WebhookMetrics:
        """Collect current system metrics"""
        start_time = time.time()
        
        try:
            # System metrics
            cpu_percent = psutil.cpu_percent(interval=0.1)
            memory = psutil.virtual_memory()
            
            # Process-specific metrics
            proc = self.get_process_info()
            process_memory = 0
            if proc:
                try:
                    process_memory = proc.memory_info().rss / 1024 / 1024  # MB
                except:
                    pass
            
            # Parse performance log for trigger metrics
            trigger_count, response_time = self.parse_performance_log()
            
            # Health status check
            health_status = self.check_health_status()
            
            # Active connections (tmux sessions)\n            active_connections = self.get_active_connections()
            
            # Error count from logs
            error_count = self.get_recent_error_count()
            
            collection_time_ms = (time.time() - start_time) * 1000
            
            metrics = WebhookMetrics(
                timestamp=time.time(),
                memory_usage_mb=process_memory,
                cpu_percent=cpu_percent,
                trigger_count=trigger_count,
                response_time_ms=response_time,
                error_count=error_count,
                health_status=health_status,
                active_connections=active_connections
            )
            
            # Store in history
            self.metrics_history.append(metrics)
            
            # Update performance stats
            self.update_performance_stats(metrics)
            
            # Store in database
            self.store_metrics(metrics)
            
            self.last_metrics = metrics
            return metrics
            
        except Exception as e:
            self.log(f"Metrics collection failed: {e}", "ERROR")
            return WebhookMetrics(
                timestamp=time.time(),
                memory_usage_mb=0,
                cpu_percent=0,
                trigger_count=0,
                response_time_ms=0,
                error_count=1,
                health_status="ERROR",
                active_connections=0
            )
    
    def parse_performance_log(self) -> Tuple[int, float]:
        """Parse performance log for metrics"""
        try:
            if not os.path.exists(self.log_files['performance']):
                return 0, 0.0
            
            trigger_count = 0
            total_duration = 0.0
            count = 0
            
            # Read last 100 lines for recent metrics
            with open(self.log_files['performance'], 'r') as f:
                lines = f.readlines()[-100:]
                
            for line in lines:
                if 'ACTION:EXECUTE_CMD' in line and 'DURATION:' in line:
                    try:
                        duration_part = line.split('DURATION:')[1].split('s')[0]
                        duration = float(duration_part)
                        total_duration += duration
                        count += 1
                        trigger_count += 1
                    except:
                        continue
            
            avg_response_time_ms = (total_duration / count * 1000) if count > 0 else 0.0
            return trigger_count, avg_response_time_ms
            
        except Exception as e:
            self.log(f"Performance log parsing failed: {e}", "ERROR")
            return 0, 0.0
    
    def check_health_status(self) -> str:
        """Check overall system health"""
        try:
            # Check if watch script is running
            proc = self.get_process_info()
            if not proc:
                return "UNHEALTHY"
            
            # Check tmux session
            result = subprocess.run(['tmux', 'has-session', '-t', 'claude'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                return "WARNING"
            
            # Check trigger file accessibility
            trigger_file = "/tmp/claude_todo_trigger.txt"
            trigger_dir = os.path.dirname(trigger_file)
            if not os.access(trigger_dir, os.W_OK):
                return "WARNING"
            
            return "HEALTHY"
            
        except Exception:
            return "ERROR"
    
    def get_active_connections(self) -> int:
        """Get number of active tmux connections"""
        try:
            result = subprocess.run(['tmux', 'list-sessions'], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                return len(result.stdout.strip().split('\\n'))
        except Exception:
            pass
        return 0
    
    def get_recent_error_count(self) -> int:
        """Count recent errors in logs"""
        try:
            error_count = 0
            current_time = time.time()
            
            # Check last 10 minutes of logs
            for log_file in [self.log_files['trigger'], self.log_files['system']]:
                if not os.path.exists(log_file):
                    continue
                    
                with open(log_file, 'r') as f:
                    lines = f.readlines()[-200:]  # Last 200 lines
                
                for line in lines:
                    if any(term in line.upper() for term in ['ERROR', 'FEHLER', 'WARNING']):
                        error_count += 1
            
            return error_count
        except Exception:
            return 0
    
    def update_performance_stats(self, metrics: WebhookMetrics):
        """Update cumulative performance statistics"""
        self.performance_stats['total_requests'] += metrics.trigger_count
        
        if metrics.error_count == 0 and metrics.trigger_count > 0:
            self.performance_stats['successful_requests'] += metrics.trigger_count
        else:
            self.performance_stats['failed_requests'] += metrics.error_count
        
        if metrics.response_time_ms > 0:
            if metrics.response_time_ms < self.performance_stats['min_response_time']:
                self.performance_stats['min_response_time'] = metrics.response_time_ms
            
            if metrics.response_time_ms > self.performance_stats['max_response_time']:
                self.performance_stats['max_response_time'] = metrics.response_time_ms
            
            # Calculate running average
            total_successful = self.performance_stats['successful_requests']
            if total_successful > 0:
                current_avg = self.performance_stats['avg_response_time']
                new_avg = ((current_avg * (total_successful - 1)) + metrics.response_time_ms) / total_successful
                self.performance_stats['avg_response_time'] = new_avg
    
    def store_metrics(self, metrics: WebhookMetrics):
        """Store metrics in database"""
        try:
            conn = sqlite3.connect(self.metrics_db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO webhook_metrics 
                (timestamp, memory_usage_mb, cpu_percent, trigger_count, 
                 response_time_ms, error_count, health_status, active_connections)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                metrics.timestamp,
                metrics.memory_usage_mb,
                metrics.cpu_percent,
                metrics.trigger_count,
                metrics.response_time_ms,
                metrics.error_count,
                metrics.health_status,
                metrics.active_connections
            ))
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to store metrics: {e}", "ERROR")
    
    def check_alerts(self, metrics: WebhookMetrics):
        """Check for alert conditions"""
        if not self.alert_config.enabled:
            return
        
        alerts = []
        
        # Memory threshold
        if metrics.memory_usage_mb > self.alert_config.memory_threshold_mb:
            alerts.append(("MEMORY_HIGH", f"Memory usage: {metrics.memory_usage_mb:.1f}MB", "WARNING"))
        
        # CPU threshold
        if metrics.cpu_percent > self.alert_config.cpu_threshold_percent:
            alerts.append(("CPU_HIGH", f"CPU usage: {metrics.cpu_percent:.1f}%", "WARNING"))
        
        # Response time threshold
        if metrics.response_time_ms > self.alert_config.response_time_threshold_ms:
            alerts.append(("RESPONSE_SLOW", f"Response time: {metrics.response_time_ms:.1f}ms", "WARNING"))
        
        # Health status
        if metrics.health_status in ["UNHEALTHY", "ERROR"]:
            alerts.append(("HEALTH_ISSUE", f"System health: {metrics.health_status}", "CRITICAL"))
        
        # Store alerts
        for alert_type, message, severity in alerts:
            self.store_alert(alert_type, message, severity)
            self.log(f"ALERT [{severity}] {alert_type}: {message}", "ALERT")
    
    def store_alert(self, alert_type: str, message: str, severity: str):
        """Store alert in database"""
        try:
            conn = sqlite3.connect(self.metrics_db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO alerts (alert_type, message, severity)
                VALUES (?, ?, ?)
            ''', (alert_type, message, severity))
            
            conn.commit()
            conn.close()
        except Exception as e:
            self.log(f"Failed to store alert: {e}", "ERROR")
    
    def get_dashboard_data(self) -> Dict:
        """Get data for dashboard display"""
        if not self.last_metrics:
            return {"error": "No metrics available"}
        
        # Recent metrics (last 50 data points)
        recent_metrics = list(self.metrics_history)[-50:]
        
        # Calculate trends
        memory_trend = self.calculate_trend([m.memory_usage_mb for m in recent_metrics])
        cpu_trend = self.calculate_trend([m.cpu_percent for m in recent_metrics])
        response_time_trend = self.calculate_trend([m.response_time_ms for m in recent_metrics])
        
        # Get recent alerts
        recent_alerts = self.get_recent_alerts()
        
        return {
            "current_metrics": asdict(self.last_metrics),
            "performance_stats": self.performance_stats.copy(),
            "trends": {
                "memory": memory_trend,
                "cpu": cpu_trend,
                "response_time": response_time_trend
            },
            "recent_alerts": recent_alerts,
            "system_info": {
                "uptime": self.get_system_uptime(),
                "total_memory_gb": psutil.virtual_memory().total / 1024**3,
                "disk_free_gb": psutil.disk_usage('/tmp').free / 1024**3
            },
            "alert_config": asdict(self.alert_config)
        }
    
    def calculate_trend(self, values: List[float]) -> str:
        """Calculate trend direction (UP/DOWN/STABLE)"""
        if len(values) < 2:
            return "STABLE"
        
        recent = values[-10:] if len(values) >= 10 else values
        if len(recent) < 2:
            return "STABLE"
        
        avg_first_half = sum(recent[:len(recent)//2]) / (len(recent)//2)
        avg_second_half = sum(recent[len(recent)//2:]) / (len(recent) - len(recent)//2)
        
        diff_percent = ((avg_second_half - avg_first_half) / avg_first_half * 100) if avg_first_half > 0 else 0
        
        if diff_percent > 5:
            return "UP"
        elif diff_percent < -5:
            return "DOWN"
        else:
            return "STABLE"
    
    def get_recent_alerts(self, limit: int = 10) -> List[Dict]:
        """Get recent alerts from database"""
        try:
            conn = sqlite3.connect(self.metrics_db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                SELECT alert_type, message, severity, created_at
                FROM alerts 
                WHERE created_at > datetime('now', '-1 hour')
                ORDER BY created_at DESC 
                LIMIT ?
            ''', (limit,))
            
            alerts = []
            for row in cursor.fetchall():
                alerts.append({
                    "type": row[0],
                    "message": row[1],
                    "severity": row[2],
                    "timestamp": row[3]
                })
            
            conn.close()
            return alerts
        except Exception:
            return []
    
    def get_system_uptime(self) -> str:
        """Get system uptime"""
        try:
            uptime_seconds = time.time() - psutil.boot_time()
            uptime_delta = timedelta(seconds=uptime_seconds)
            return str(uptime_delta).split('.')[0]  # Remove microseconds
        except Exception:
            return "Unknown"
    
    def start_monitoring(self, interval: float = 5.0):
        """Start continuous monitoring"""
        self.running = True
        self.log("Starting webhook monitoring...", "INFO")
        
        try:
            while self.running:
                start_time = time.time()
                
                # Collect metrics
                metrics = self.collect_metrics()
                
                # Check for alerts
                self.check_alerts(metrics)
                
                # Log current status every 10 intervals
                if int(time.time()) % 50 == 0:  # Every ~50 seconds
                    self.log(f"Status: {metrics.health_status}, "
                           f"Memory: {metrics.memory_usage_mb:.1f}MB, "
                           f"CPU: {metrics.cpu_percent:.1f}%, "
                           f"Response: {metrics.response_time_ms:.1f}ms", "STATUS")
                
                # Sleep for remaining interval time
                elapsed = time.time() - start_time
                sleep_time = max(0, interval - elapsed)
                time.sleep(sleep_time)
                
        except KeyboardInterrupt:
            self.log("Monitoring stopped by user", "INFO")
        except Exception as e:
            self.log(f"Monitoring error: {e}", "ERROR")
        finally:
            self.running = False
    
    def stop_monitoring(self):
        """Stop monitoring"""
        self.running = False
        self.log("Monitoring stopped", "INFO")

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Webhook System Monitor')
    parser.add_argument('--interval', type=float, default=5.0, 
                       help='Monitoring interval in seconds (default: 5.0)')
    parser.add_argument('--dashboard', action='store_true', 
                       help='Print dashboard data once and exit')
    parser.add_argument('--config', type=str, 
                       help='Path to config file')
    
    args = parser.parse_args()
    
    monitor = WebhookMonitor(config_path=args.config)
    
    if args.dashboard:
        # Single dashboard output
        data = monitor.get_dashboard_data()
        print(json.dumps(data, indent=2))
    else:
        # Continuous monitoring
        try:
            monitor.start_monitoring(interval=args.interval)
        except KeyboardInterrupt:
            monitor.stop_monitoring()

if __name__ == "__main__":
    main()